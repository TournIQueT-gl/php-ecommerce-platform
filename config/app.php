<?php
declare(strict_types=1);

/*
 * Portable web-root detection.
 * - Production in the document root resolves to ''.
 * - XAMPP/WAMP installs such as C:\\xampp\\htdocs\\saas resolve to '/perfumestore'.
 * - PERFUME_BASE_PATH can override detection (for unusual reverse-proxy layouts).
 */
function app_detect_base_path(): string {
    $override = trim((string)(getenv('PERFUME_BASE_PATH') ?: ''));
    if ($override !== '') {
        $override = '/' . trim(str_replace('\\', '/', $override), '/');
        return $override === '/' ? '' : $override;
    }
    if (PHP_SAPI === 'cli') return '';
    $docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = realpath(dirname(__DIR__));
    if ($docRoot === false || $appRoot === false) return '';
    $doc = rtrim(str_replace('\\', '/', $docRoot), '/');
    $app = rtrim(str_replace('\\', '/', $appRoot), '/');
    $docCmp = strtolower($doc);
    $appCmp = strtolower($app);
    if ($appCmp === $docCmp) return '';
    if (str_starts_with($appCmp, $docCmp . '/')) {
        $relative = substr($app, strlen($doc));
        return '/' . trim($relative, '/');
    }
    return '';
}
if (!defined('BASE_PATH')) define('BASE_PATH', app_detect_base_path());
if (!defined('REQUIRE_HTTPS')) define('REQUIRE_HTTPS', true); // Enforced in production; localhost is exempt for XAMPP/WAMP development.
if (!defined('CANONICAL_SITE_URL_CONFIGURED')) define('CANONICAL_SITE_URL_CONFIGURED', trim((string)(getenv('PERFUME_CANONICAL_SITE_URL') ?: '')) !== '');
if (!defined('CANONICAL_SITE_URL')) define('CANONICAL_SITE_URL', CANONICAL_SITE_URL_CONFIGURED ? trim((string)getenv('PERFUME_CANONICAL_SITE_URL')) : 'http://localhost');
// Production secret/runtime locations may be overridden with PERFUME_* environment variables.
// Only directly connected trusted proxies may supply forwarded client/scheme headers.
const TRUSTED_PROXY_CIDRS = ['127.0.0.1/32', '::1/128'];
const WEB_SESSION_IDLE_SECONDS = 7200;
const WEB_DISPLAY_TIMEZONE = 'Africa/Cairo';
const FOUNDATION_NOTIFICATION_RETENTION_DAYS = 180;
const FOUNDATION_EVENT_RETENTION_DAYS = 30;
const FOUNDATION_EVENT_BATCH_SIZE = 200;
// Scheduler modes: auto prefers a healthy CLI scheduler and otherwise uses the
// bounded browser fallback; web always uses the browser fallback; cli disables
// automatic browser work. Keep CLI support for future VPS/paid hosting.
const FOUNDATION_SCHEDULER_MODE = 'auto'; // auto | web | cli
const FOUNDATION_WEB_SCHEDULER_ENABLED = true;
const FOUNDATION_WEB_SCHEDULER_INTERVAL_SECONDS = 60;
const FOUNDATION_WEB_EVENT_BATCH_SIZE = 3;
const FOUNDATION_WEB_EMAIL_BATCH_SIZE = 1;
const FOUNDATION_WEB_IP_BATCH_SIZE = 1;
const FOUNDATION_WEB_TIME_BUDGET_MS = 4500;
const FOUNDATION_WEB_EMAIL_INTERVAL_SECONDS = 90;
const FOUNDATION_WEB_IP_INTERVAL_SECONDS = 90;
const FOUNDATION_WEB_CLEANUP_INTERVAL_SECONDS = 86400;
const FOUNDATION_FULL_SCHEDULER_STALE_SECONDS = 600; // Auto mode prefers CLI only while its heartbeat is fresh.
const SUPPORT_ATTACHMENT_RETENTION_DAYS = 180; // Closed-ticket attachments are removed after this window.
const BACKUP_RETENTION_DAYS = 30; // Automatic local snapshot rotation window.
const BACKUP_MINIMUM_KEEP = 3; // Never auto-delete the newest three recovery snapshots.
const BACKUP_MAX_FILES = 10; // Hard local cap; copy important snapshots off-server before rotation.
const BACKUP_FRESHNESS_WARNING_DAYS = 7;
// Visitor network intelligence is discovered from tracked session IPs and resolved outside
// the controller/view render path. CLI uses larger batches; the free-hosting web fallback
// resolves one cached IP at a time and Admin/Owner can run a manual batch explicitly.
const IP_INTELLIGENCE_REMOTE_PROVIDER = 'https://ipwho.is/';
const IP_INTELLIGENCE_BACKGROUND_BATCH = 5;

function foundation_scheduler_mode(): string {
    $env=strtolower(trim((string)(getenv('PERFUME_SCHEDULER_MODE')?:'')));
    $mode=$env!==''?$env:strtolower(trim((string)FOUNDATION_SCHEDULER_MODE));
    return in_array($mode,['auto','web','cli'],true)?$mode:'auto';
}

function url(string $path = ''): string {
    // Product/homepage media may use an approved HTTPS asset URL. Keep local
    // application paths base-aware while leaving secure remote media intact.
    if (preg_match('#^https://#i', $path) === 1 && filter_var($path, FILTER_VALIDATE_URL) !== false) {
        return $path;
    }
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset_url(string $path): string {
    static $versionCache=[];
    $path=ltrim($path,'/');
    $href=url($path);
    if(array_key_exists($path,$versionCache)) return $versionCache[$path]===null?$href:$href.'?v='.$versionCache[$path];
    $file=dirname(__DIR__).'/'.$path;
    // Our CSS/JS uses long-lived immutable caching. ZIP deployment or file copying
    // can preserve mtimes even when bytes change, so mtime-based versions are unsafe.
    // Hash the content instead (once per asset per request); keep legacy URLs for
    // missing files so a missing asset cannot trigger a fatal error.
    $digest=is_file($file)&&is_readable($file)?@hash_file('sha256',$file):false;
    $versionCache[$path]=is_string($digest)?substr($digest,0,16):null;
    return $versionCache[$path]===null?$href:$href.'?v='.$versionCache[$path];
}

// Browser/admin routes use extensionless URLs.
function route_url(string $path = ''): string {
    $path = ltrim($path, '/');
    $path = preg_replace('#(^|/)index\.php$#i', '$1', $path) ?? $path;
    $path = preg_replace('/\.php$/i', '', $path) ?? $path;
    return BASE_PATH . '/' . ltrim($path, '/');
}
