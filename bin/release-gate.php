<?php
declare(strict_types=1);
/**
 * Universal Commerce production release gate (read-only).
 *
 * Usage: php bin/release-gate.php [--json] [--offline] [--diagnose]
 *  --json    Machine-readable aggregate report (never emits DB credentials or customer data).
 *  --offline Check files/runtime only; always BLOCKED for production certification.
 *  --diagnose Emit safe failure phase, exception type, source file, and line on unexpected failure; never secret values.
 *
 * Exit codes: 0 = passed with no blockers, 2 = release blocked, 1 = unexpected internal error.
 * This is NOT a substitute for live checkout, browser, security and backup-restore testing.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$accepted = ['--json', '--offline', '--diagnose'];
foreach ($args as $arg) {
    if (!in_array($arg, $accepted, true)) {
        fwrite(STDERR, "Usage: php bin/release-gate.php [--json] [--offline] [--diagnose]\n");
        exit(1);
    }
}
$offline = in_array('--offline', $args, true);
$asJson = in_array('--json', $args, true);
$diagnose = in_array('--diagnose', $args, true);
$phase = 'initialization';
$checks = [];
$seenCheckIds = [];
$add = static function (string $id, string $status, string $message, ?int $count = null) use (&$checks, &$seenCheckIds): void {
    if (!in_array($status, ['pass', 'warn', 'block'], true)) {
        throw new LogicException('Invalid gate status');
    }
    // One result per release gate. Duplicate results can hide a failed or stale check.
    if (isset($seenCheckIds[$id])) throw new LogicException('Duplicate release check: ' . $id);
    $seenCheckIds[$id] = true;
    $item = ['id' => $id, 'status' => $status, 'message' => $message];
    if ($count !== null) $item['count'] = $count;
    $checks[] = $item;
};

try {
    $phase = 'load_app_config';
    require_once $root . '/config/app.php';
    $phase = 'load_database_config';
    require_once $root . '/config/database.php';
    $phase = 'load_secret_store';
    require_once $root . '/app/Support/SecretStore.php';
    $phase = 'load_store_helpers';
    require_once $root . '/includes/store_helpers.php';
    $phase = 'runtime_and_environment_checks';
    $add('php_version', PHP_VERSION_ID >= 80100 ? 'pass' : 'block', 'PHP 8.1+ required; running PHP ' . PHP_VERSION);
    foreach (['pdo_mysql', 'openssl', 'fileinfo', 'gd', 'json'] as $extension) {
        $add('extension_' . $extension, extension_loaded($extension) ? 'pass' : 'block',
            $extension . (extension_loaded($extension) ? ' is available' : ' is missing'));
    }
    $add('php_display_errors', ini_get('display_errors') === '1' || strtolower((string)ini_get('display_errors')) === 'on' ? 'block' : 'pass',
         'Production must not display PHP errors to visitors');
    $canonical = trim((string)CANONICAL_SITE_URL);
    $url = filter_var($canonical, FILTER_VALIDATE_URL) !== false ? parse_url($canonical) : false;
    $https = is_array($url) && strtolower((string)($url['scheme'] ?? '')) === 'https';
    $host = $https ? strtolower((string)($url['host'] ?? '')) : '';
    $realDomain = $host !== '' && !in_array($host, ['localhost','127.0.0.1','::1'], true) && !str_ends_with($host, '.test');
    // Older installed config/app.php revisions do not define this flag. In that
    // case report a configuration blocker instead of an uncaught PHP Error.
    // Never infer that production is configured merely from a fallback URL.
    $canonicalUrlConfigured = defined('CANONICAL_SITE_URL_CONFIGURED')
        && (bool)constant('CANONICAL_SITE_URL_CONFIGURED');
    $add('canonical_https', $canonicalUrlConfigured && $realDomain ? 'pass' : 'block',
        'Set PERFUME_CANONICAL_SITE_URL to the real public HTTPS domain (never localhost); ensure config/app.php is from the matching release');

    // Existing keys must survive code deployments. Checking status does not create keys.
    try {
        $secretStatus = \App\Support\SecretStore::storageStatus();
        $secretSafe = !empty($secretStatus['deployment_safe']) && \App\Support\SecretStore::available();
        $add('external_secret_storage', $secretSafe ? 'pass' : 'block',
            $secretSafe ? 'Persistent external secret storage is ready' :
            'Configure writable encryption key storage outside the web root; resolve legacy-only or conflicting key copies');
    } catch (Throwable $ignored) {
        $add('external_secret_storage', 'block', 'External secret storage could not be verified safely');
    }

    foreach (['.htaccess', 'uploads/.htaccess', 'bin/v5-preflight.php'] as $path) {
        $add('release_file_' . str_replace(['/', '.'], '_', $path), is_file($root . '/' . $path) ? 'pass' : 'block',
            $path . (is_file($root . '/' . $path) ? ' exists' : ' is missing'));
    }
    $rootRules = (string)@file_get_contents($root . '/.htaccess');
    $add('protected_internal_dirs', str_contains($rootRules, 'app|bin|config|database|includes|storage|tests|vendor|views') ? 'pass' : 'block',
        'Apache must deny HTTP access to internal application directories');
    $add('protected_archive_files', str_contains($rootRules, 'zip|7z|rar|tgz') ? 'pass' : 'block',
        'Apache must deny direct HTTP downloads of accidental backup archives');
    foreach (['storage/logs', 'storage/cache', 'storage/backups', 'storage/support', 'uploads'] as $path) {
        if (!is_dir($root . '/' . $path)) {
            $add('directory_' . str_replace('/', '_', $path), 'block', $path . ' is missing');
        } elseif (!is_writable($root . '/' . $path)) {
            $add('directory_' . str_replace('/', '_', $path), 'block', $path . ' is not writable by this PHP runtime');
        } else {
            $add('directory_' . str_replace('/', '_', $path), 'pass', $path . ' is writable');
        }
    }
    // A writable backup directory is not evidence of a successful restore.
    $add('backup_restore_evidence', 'warn',
        'Restore verification is not automated: document a successful isolated staging restore before launch');
    $add('live_http_security_probe', 'warn',
        'Run bin/http-security-smoke.php against deployed HTTPS host to verify server-level file restrictions');
    $migrationFiles = glob($root . '/database/migrations/*.sql') ?: [];
    $migrationVersions = array_map(static fn($file) => basename($file, '.sql'), $migrationFiles);
    $add('migration_files', count($migrationVersions) >= 20 ? 'pass' : 'block',
        'Packaged migration files present', count($migrationVersions));
    $demoSeeder = $root . '/bin/seed-showcase.php';
    // Production distributions do not ship demo-data mutation utilities.
    // Developer packages may retain a CLI-only seeder, but web-accessible or
    // unguarded seeders must never be accepted on a production host.
    $demoSeederSafe = !is_file($demoSeeder) ||
        str_contains((string)@file_get_contents($demoSeeder), "if(PHP_SAPI!=='cli')");
    $realQaSeeder = $root . '/bin/seed-real-test-catalog.php';
    $realQaSeederSafe = !is_file($realQaSeeder) ||
        str_contains((string)@file_get_contents($realQaSeeder), "if (PHP_SAPI !== 'cli')");
    $add('demo_seeder_guard', $demoSeederSafe && $realQaSeederSafe ? 'pass' : 'block',
        !is_file($demoSeeder) && !is_file($realQaSeeder)
            ? 'Development seeders are excluded from this production distribution'
            : 'Any remaining QA seeder must be CLI-only and explicitly guarded');

    $phase = 'database_read_only_checks';
    if ($offline) {
        $add('live_database', 'block', 'Offline run: database, real catalog and schema have NOT been validated');
    } elseif (!extension_loaded('pdo_mysql')) {
        $add('live_database', 'block', 'Cannot inspect MySQL: PHP pdo_mysql extension missing');
    } else {
        $dbStep = 'connect';
        try {
            // Only SELECT statements below. No migration, updates, inserts, or secret initialization.
            $db = db();
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $add('live_database', 'pass', 'MySQL connection established (read-only checks)');
            $tableExists = static function (string $table) use ($db): bool {
                $st = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
                $st->execute([$table]);
                return (int)$st->fetchColumn() > 0;
            };
            $dbStep = 'required_tables';
            $required = ['schema_migrations', 'store_products', 'store_product_variants', 'store_categories', 'commerce_departments', 'store_orders', 'shipping_zones', 'mail_settings'];
            $missing = array_values(array_filter($required, static fn($t) => !$tableExists($t)));
            $add('required_tables', !$missing ? 'pass' : 'block', $missing ? 'Missing tables: ' . implode(', ', $missing) : 'Required commerce tables exist');
            $dbStep = 'migration_records_and_checksums';
            if ($tableExists('schema_migrations')) {
                $installed = [];
                foreach ($db->query('SELECT version, checksum FROM schema_migrations') as $row) {
                    $installed[(string)$row['version']] = (string)($row['checksum'] ?? '');
                }
                $pending = array_values(array_diff($migrationVersions, array_keys($installed)));
                $add('pending_migrations', !$pending ? 'pass' : 'block',
                    !$pending ? 'All packaged migrations recorded' : 'Missing migrations: ' . implode(', ', $pending), count($pending));
                $bad = [];
                foreach ($migrationFiles as $file) {
                    $name = basename($file, '.sql');
                    if (!isset($installed[$name])) continue;
                    $stored = $installed[$name];
                    if (in_array($stored, ['', 'manual', 'baseline'], true)) continue;
                    $expected = hash_file('sha256', $file);
                    if ($expected === false || !hash_equals($stored, $expected)) $bad[] = $name;
                }
                $add('migration_checksums', !$bad ? 'pass' : 'block',
                    !$bad ? 'Recorded migration checksums match the package (baseline/manual entries exempt)' :
                    'Migration checksum mismatch: ' . implode(', ', $bad), count($bad));
            }
            // Delivery must cover the 27 accepted governorates or provide a default zone.
            // An entirely empty zones table uses the application's global shipping configuration.
            $dbStep = 'shipping_zone_read';
            if ($tableExists('shipping_zones')) {
                $zoneRows = $db->query("SELECT governorate FROM shipping_zones WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
                $specific = []; $fallback = false;
                foreach ($zoneRows as $zone) {
                    $value = trim((string)$zone);
                    if ($value === '') { $fallback = true; continue; }
                    $specific[strtolower($value)] = true;
                }
                $missingCoverage = [];
                foreach (store_egypt_governorates() as $governorate) {
                    if (!isset($specific[strtolower($governorate)])) $missingCoverage[] = $governorate;
                }
                $coverageGood = !$specific || $fallback || !$missingCoverage;
                $add('shipping_coverage', $coverageGood ? 'pass' : 'block',
                    $coverageGood ? 'Delivery settings cover supported Egyptian governorates or use global fallback' :
                    'Active delivery zones exclude governorates without a default fallback: ' . implode(', ', $missingCoverage),
                    count($missingCoverage));
            }
            // This is a configuration check, not proof that mail reaches its recipients.
            $dbStep = 'email_settings';
            if ($tableExists('mail_settings')) {
                $mail = $db->query('SELECT enabled, smtp_host, from_email FROM mail_settings WHERE id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                $mailReady = is_array($mail) && (int)$mail['enabled'] === 1 &&
                    trim((string)$mail['smtp_host']) !== '' &&
                    filter_var((string)$mail['from_email'], FILTER_VALIDATE_EMAIL) !== false;
                $add('email_configuration', $mailReady ? 'pass' : 'block',
                    $mailReady ? 'Outbound SMTP settings are enabled and complete (delivery still needs a real test)' :
                    'Configure enabled outbound email and verify delivery on staging before launch');
            }
            // A configured SMTP server is not enough if the queue stalls. Counts only;
            // never expose recipients, message bodies, provider secrets or errors.
            $dbStep = 'email_queue';
            // DELAYED is a reserved MySQL keyword. Keep result aliases nonreserved
            // so this read-only query works on MySQL and MariaDB deployments.
            if ($tableExists('email_deliveries')) {
                $queue = $db->query("SELECT
                    SUM(status IN ('pending','sending') AND created_at < UTC_TIMESTAMP() - INTERVAL 60 MINUTE) AS pending_over_hour,
                    SUM(status IN ('pending','sending') AND created_at < UTC_TIMESTAMP() - INTERVAL 24 HOUR) AS very_old,
                    SUM(status='failed' AND attempts>=5) AS exhausted
                    FROM email_deliveries")->fetch(PDO::FETCH_ASSOC) ?: [];
                $delayed = (int)($queue['pending_over_hour'] ?? 0);
                $veryOld = (int)($queue['very_old'] ?? 0);
                $exhausted = (int)($queue['exhausted'] ?? 0);
                $add('email_queue_age', $veryOld > 0 ? 'block' : ($delayed > 0 ? 'warn' : 'pass'),
                    $veryOld > 0 ? 'Mail has been queued for over 24 hours; check delivery processing' :
                    ($delayed > 0 ? 'Mail has been queued for over an hour; check scheduling' : 'No aged pending email'), $delayed);
                $add('email_exhausted_retries', $exhausted > 0 ? 'warn' : 'pass',
                    $exhausted > 0 ? 'Some failed messages have exhausted retries; inspect the admin email log' : 'No exhausted email retries', $exhausted);
            } else {
                $add('email_queue_age', 'block', 'Email delivery queue schema is missing');
            }
            $dbStep = 'email_scheduler';
            if ($tableExists('scheduler_state')) {
                $scheduler = $db->query("SELECT MAX(last_finished_at) AS last_finished
                    FROM scheduler_state WHERE task_key IN ('email_queue_worker','process_email_queue_web') AND last_status='ok'")->fetch(PDO::FETCH_ASSOC) ?: [];
                $last = trim((string)($scheduler['last_finished'] ?? ''));
                $lastUtc = $last !== '' ? strtotime($last . ' UTC') : false;
                $recent = $lastUtc !== false && $lastUtc <= time() + 300 && time() - $lastUtc <= 86400;
                $add('email_worker_evidence', $recent ? 'pass' : 'warn',
                    $recent ? 'Email worker ran successfully in the last 24 hours (delivery still needs inbox testing)' :
                    'No recent successful email worker run; verify cron or the traffic-triggered fallback');
            } else {
                $add('email_worker_evidence', 'warn', 'Scheduler state schema is unavailable');
            }
            $dbStep = 'product_catalog';
            if ($tableExists('store_products')) {
                $count = static function (string $sql) use ($db): int { return (int)$db->query($sql)->fetchColumn(); };
                $demoProducts = $count("SELECT COUNT(*) FROM store_products WHERE status='active' AND (slug LIKE 'demo-a77-%' OR slug LIKE 'qaoff-%' OR name LIKE '%[DEMO]%' OR name LIKE '%[TEST]%' OR name LIKE '[QA] %' OR name LIKE 'DEMO %')");
                $published = $count("SELECT COUNT(*) FROM store_products WHERE status='active'");
                $add('published_catalog', $published > 0 ? 'pass' : 'block',
                    $published > 0 ? 'Published product catalog is non-empty' : 'No active products are published', $published);
                $add('published_demo_products', $demoProducts === 0 ? 'pass' : 'block',
                    $demoProducts ? 'Demo-labelled products are publicly active; unpublish and review them' : 'No active demo-labelled products', $demoProducts);
                $untranslated = $count("SELECT COUNT(*) FROM store_products WHERE status='active' AND (name_ar IS NULL OR TRIM(name_ar)='')");
                $add('arabic_product_names', $untranslated === 0 ? 'pass' : 'warn',
                    $untranslated ? 'Active products without Arabic names (verify storefront language requirements)' : 'All active products have Arabic names', $untranslated);
                $missingImages = $count("SELECT COUNT(*) FROM store_products WHERE status='active' AND (main_image_path IS NULL OR TRIM(main_image_path)='') AND (gallery_json IS NULL OR TRIM(gallery_json)='' OR gallery_json='[]')");
                $fallbackCount = $count("SELECT COUNT(*) FROM store_products WHERE status='active' AND main_image_path LIKE 'uploads/media/catalog/curated/%'");
                $add('temporary_catalog_artwork', $fallbackCount === 0 ? 'pass' : 'block',
                    $fallbackCount ? 'Active products still use bundled temporary catalog artwork' : 'No active products use packaged fallback catalog artwork',
                    $fallbackCount);
                $add('product_media', $missingImages === 0 ? 'pass' : 'warn',
                    $missingImages ? 'Active products without main/gallery media references (review before launch)' : 'Active products have media references', $missingImages);
            }
            $dbStep = 'variant_integrity';
            if ($tableExists('store_product_variants')) {
                $invalid = (int)$db->query("SELECT COUNT(*) FROM store_product_variants WHERE price <= 0 OR stock_qty < 0 OR reserved_qty < 0 OR reserved_qty > stock_qty")->fetchColumn();
                $add('variant_data_integrity', $invalid === 0 ? 'pass' : 'block',
                    $invalid ? 'Variants have invalid price or inventory values' : 'Variant price and stock fields pass basic validity checks', $invalid);
            if ($tableExists('store_products')) {
                    $withoutVariants = (int)$db->query("SELECT COUNT(*) FROM store_products p WHERE p.status='active' AND NOT EXISTS (SELECT 1 FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active')")->fetchColumn();
                    $add('published_product_variants', $withoutVariants === 0 ? 'pass' : 'block',
                        $withoutVariants ? 'Published products without any active purchasable variant' : 'All published products have an active variant', $withoutVariants);
                }
            }
            $dbStep = 'brands';
            if ($tableExists('store_brands')) {
                $demoBrands = (int)$db->query("SELECT COUNT(*) FROM store_brands WHERE status='active' AND (slug LIKE 'demo-%' OR slug LIKE 'qaoff-brand-%' OR name LIKE '%[DEMO]%' OR name LIKE '%[TEST]%')")->fetchColumn();
                $add('published_demo_brands', $demoBrands === 0 ? 'pass' : 'block',
                    $demoBrands ? 'Demo-labelled brands are still active' : 'No active demo-labelled brands', $demoBrands);
            }
            $dbStep = 'categories';
            if ($tableExists('store_categories')) {
                $demoCats = (int)$db->query("SELECT COUNT(*) FROM store_categories WHERE status='active' AND (slug LIKE 'demo-a77-%' OR slug LIKE 'qaoff-category-%' OR name LIKE '%[DEMO]%' OR name LIKE '%[TEST]%')")->fetchColumn();
                $add('published_demo_categories', $demoCats === 0 ? 'pass' : 'block',
                    $demoCats ? 'Demo-labelled categories are still active' : 'No active demo-labelled categories', $demoCats);
            }
        } catch (Throwable $error) {
            // No credentials, connection strings, raw SQL payloads, or exception messages are printed.
            // The connection check may already be marked PASS; keep result IDs unique.
            $message = 'Database checks could not complete. Review the database schema on the target host.';
            if ($diagnose) {
                $source = str_replace('\\', '/', $error->getFile());
                $rootPath = rtrim(str_replace('\\', '/', $root), '/');
                $relativeSource = str_starts_with(strtolower($source), strtolower($rootPath . '/'))
                    ? substr($source, strlen($rootPath) + 1)
                    : '[outside-project]';
                $exceptionCode = (string)$error->getCode();
                if (!preg_match('/\A[A-Za-z0-9]{1,10}\z/D', $exceptionCode)) {
                    $exceptionCode = 'unavailable';
                }
                $message = sprintf(
                    'Database check failed at %s; exception=%s; source=%s:%d; code=%s. No database changes were made.',
                    $dbStep, get_class($error), $relativeSource, $error->getLine(), $exceptionCode
                );
            }
            $add('live_database_checks', 'block', $message);
        }
    }

    $phase = 'report_serialization';
    $blocked = count(array_filter($checks, static fn($c) => $c['status'] === 'block'));
    $warnings = count(array_filter($checks, static fn($c) => $c['status'] === 'warn'));
    $report = [
        'tool' => 'universal-commerce-release-gate',
        'schema_version' => 1,
        'generated_utc' => gmdate('c'),
        'decision' => $blocked === 0 ? 'CONDITIONAL_PASS' : 'BLOCKED',
        'not_a_live_e2e_certificate' => true,
        'check_count' => count($checks),
        'blockers' => $blocked,
        'warnings' => $warnings,
        'checks' => $checks,
        'next_step' => 'Even with conditional pass, run live checkout, admin CRUD, RTL/mobile, email, permissions and restore drills before launch.',
    ];
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
    } else {
        echo 'RELEASE GATE: ' . $report['decision'] . ' | ' . $blocked . ' blockers | ' . $warnings . ' warnings' . PHP_EOL;
        foreach ($checks as $c) echo strtoupper($c['status']) . ' ' . $c['id'] . ': ' . $c['message'] . (isset($c['count']) ? ' (' . $c['count'] . ')' : '') . PHP_EOL;
        echo 'CAUTION: This is not a substitute for the live acceptance checklist.' . PHP_EOL;
    }
    exit($blocked === 0 ? 0 : 2);
} catch (Throwable $error) {
    // Never display exception messages or stack traces: they can contain private
    // configuration paths, SQL payloads, and connection details. The exception
    // type plus relative source and phase is sufficient for a first diagnosis.
    if ($diagnose) {
        $source = str_replace('\\', '/', $error->getFile());
        $rootPath = rtrim(str_replace('\\', '/', $root), '/');
        $relativeSource = str_starts_with(strtolower($source), strtolower($rootPath . '/'))
            ? substr($source, strlen($rootPath) + 1)
            : '[outside-project]';
        $diagnostic = [
            'decision' => 'INTERNAL_ERROR',
            'phase' => $phase,
            'exception_type' => get_class($error),
            'source_file' => $relativeSource,
            'source_line' => $error->getLine(),
            'credentials_included' => false,
            'production_approved' => false,
        ];
        if ($asJson) {
            fwrite(STDERR, (string)json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL);
        } else {
            fwrite(STDERR, sprintf(
                "INTERNAL_ERROR at %s: %s in %s:%d; no production approval granted.\n",
                $diagnostic['phase'], $diagnostic['exception_type'],
                $diagnostic['source_file'], $diagnostic['source_line']
            ));
        }
    } else {
        fwrite(STDERR, "Release gate encountered an unexpected error; no production approval granted. Re-run with --diagnose for safe troubleshooting.\n");
    }
    exit(1);
}
