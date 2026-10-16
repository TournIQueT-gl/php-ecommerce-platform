<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class SecretStore
{
    private const PREFIX = 'v1:';

    public static function legacyDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/private';
    }

    /**
     * Persistent secret directory outside the replaceable web root.
     *
     * Preferred order:
     * 1. PERFUME_SECRET_DIR environment variable.
     * 2. $HOME/.auraa-secrets on shared hosting.
     * 3. Hosting-layout inference for /home/<user>/domains/<host>/public_html.
     *
     * Local/development layouts that do not expose a safe external home path keep
     * using the legacy storage/private directory until explicitly configured.
     */
    public static function externalDirectory(): ?string
    {
        $configured = trim((string)getenv('PERFUME_SECRET_DIR'));
        if ($configured !== '') {
            if (!self::isAbsolutePath($configured)) {
                throw new RuntimeException('PERFUME_SECRET_DIR must be an absolute path.');
            }
            return self::cleanPath($configured);
        }

        // Shared-hosting document roots commonly use
        // /home/<user>/domains/<host>/public_html. Prefer deriving the account home
        // from that known layout before trusting a provider-specific HOME value.
        $root = dirname(__DIR__, 2);
        $normalized = str_replace('\\', '/', $root);
        if (preg_match('#^(.+)/domains/[^/]+/public_html/?$#i', $normalized, $m)) {
            return rtrim(str_replace('/', DIRECTORY_SEPARATOR, (string)$m[1]), DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR . '.auraa-secrets';
        }

        $home = trim((string)getenv('HOME'));
        if ($home !== '' && self::isAbsolutePath($home)) {
            return self::cleanPath($home) . DIRECTORY_SEPARATOR . '.auraa-secrets';
        }

        return null;
    }

    public static function keyPath(string $purpose): string
    {
        $purpose = self::purpose($purpose);
        $legacy = self::legacyDirectory() . DIRECTORY_SEPARATOR . $purpose . '.key';
        $externalDir = self::externalDirectory();
        $external = $externalDir !== null ? $externalDir . DIRECTORY_SEPARATOR . $purpose . '.key' : null;

        if ($external !== null && @is_file($external) && is_file($legacy)) {
            $externalRaw = @file_get_contents($external);
            $legacyRaw = @file_get_contents($legacy);
            if (!is_string($externalRaw) || strlen($externalRaw) !== 32 || !is_string($legacyRaw) || strlen($legacyRaw) !== 32) {
                throw new RuntimeException('Conflicting secret-key copies exist and one is invalid. Resolve the external/legacy key copies before continuing.');
            }
            if (!hash_equals($externalRaw, $legacyRaw)) {
                throw new RuntimeException('Conflicting secret-key copies exist. The external and legacy keys do not match.');
            }
            return $external;
        }

        if ($external !== null && @is_file($external)) return $external;
        if (is_file($legacy)) return $legacy;
        return $external ?? $legacy;
    }

    public static function available(): bool
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }

    public static function encrypt(string $plain, string $purpose): string
    {
        if ($plain === '') return '';
        if (!self::available()) throw new RuntimeException('OpenSSL is required to protect application secrets.');
        $key = self::getOrCreateKey($purpose);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('Could not encrypt application secret.');
        return self::PREFIX . rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public static function decrypt(?string $encoded, string $purpose): string
    {
        $encoded = trim((string)$encoded);
        if ($encoded === '') return '';
        if (!str_starts_with($encoded, self::PREFIX)) throw new RuntimeException('Unsupported protected secret format.');
        if (!self::available()) throw new RuntimeException('OpenSSL is required to read application secrets.');
        $payload = substr($encoded, strlen(self::PREFIX));
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $raw = base64_decode(strtr($payload, '-_', '+/'), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Protected secret data is invalid.');
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::loadExistingKey($purpose), OPENSSL_RAW_DATA, $iv, $tag, '');
        if (!is_string($plain)) throw new RuntimeException('Could not decrypt application secret. The configured encryption key does not match this ciphertext.');
        return $plain;
    }

    /** @return array<string,string> basename => active path */
    public static function keyFiles(): array
    {
        $names = [];
        foreach (array_filter([self::legacyDirectory(), self::externalDirectory()]) as $dir) {
            foreach ((array)@glob(rtrim((string)$dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.key') as $file) {
                if (!is_file($file)) continue;
                $name = basename($file);
                if (!preg_match('/^([a-z0-9_-]{1,32})\.key$/i', $name, $m)) continue;
                $names[strtolower((string)$m[1])] = true;
            }
        }
        $files = [];
        foreach (array_keys($names) as $purpose) {
            $path = self::keyPath($purpose);
            if (is_file($path)) $files[$purpose . '.key'] = $path;
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    public static function storageStatus(): array
    {
        $root = self::cleanPath(dirname(__DIR__, 2));
        $legacyDir = self::legacyDirectory();
        $externalDir = self::externalDirectory();
        $externalOutsideWebRoot = $externalDir !== null && !self::pathInside(self::cleanPath($externalDir), $root);
        $states = [];
        $hasLegacyOnly = false;
        $hasConflict = false;

        foreach (['totp','mail','guest-order-event'] as $purpose) {
            $legacy = $legacyDir . DIRECTORY_SEPARATOR . $purpose . '.key';
            $external = $externalDir !== null ? $externalDir . DIRECTORY_SEPARATOR . $purpose . '.key' : null;
            $legacyExists = is_file($legacy);
            $externalExists = $external !== null && @is_file($external);
            $conflict = false;
            $same = false;
            if ($legacyExists && $externalExists) {
                $a = @file_get_contents($legacy);
                $b = @file_get_contents((string)$external);
                $same = is_string($a) && strlen($a) === 32 && is_string($b) && strlen($b) === 32 && hash_equals($a, $b);
                $conflict = !$same;
            }
            if ($conflict) {
                $location = 'conflict';
                $hasConflict = true;
            } elseif ($externalExists) {
                $location = 'external';
            } elseif ($legacyExists) {
                $location = 'legacy';
                $hasLegacyOnly = true;
            } else {
                $location = 'missing';
            }
            $states[$purpose] = [
                'location'=>$location,
                'legacy_exists'=>$legacyExists,
                'external_exists'=>$externalExists,
                'mirrored'=>$same,
            ];
        }

        $externalReady = false;
        if ($externalDir !== null && $externalOutsideWebRoot) {
            if (@is_dir($externalDir)) {
                $externalReady = @is_readable($externalDir) && @is_writable($externalDir);
            } else {
                $parent = dirname($externalDir);
                $externalReady = @is_dir($parent) && @is_writable($parent);
            }
        }

        return [
            'legacy_dir'=>$legacyDir,
            'external_dir'=>$externalDir,
            'external_outside_web_root'=>$externalOutsideWebRoot,
            'external_ready'=>$externalReady,
            'deployment_safe'=>$externalOutsideWebRoot && $externalReady && !$hasLegacyOnly && !$hasConflict,
            'has_legacy_only'=>$hasLegacyOnly,
            'has_conflict'=>$hasConflict,
            'purposes'=>$states,
        ];
    }

    /**
     * Copy valid legacy keys to persistent external storage.
     * Legacy copies are deliberately retained as rollback copies; once both copies
     * match, keyPath() prefers the external copy.
     */
    public static function migrateLegacyKeys(): array
    {
        $externalDir = self::externalDirectory();
        if ($externalDir === null) {
            throw new RuntimeException('No external secret directory could be determined. Set PERFUME_SECRET_DIR to an absolute path outside public_html.');
        }
        $root = self::cleanPath(dirname(__DIR__, 2));
        if (self::pathInside(self::cleanPath($externalDir), $root)) {
            throw new RuntimeException('External secret storage must be outside the website deployment directory.');
        }
        if (!@is_dir($externalDir) && !@mkdir($externalDir, 0700, true) && !@is_dir($externalDir)) {
            throw new RuntimeException('Could not create external secret storage. Check hosting permissions or configure PERFUME_SECRET_DIR.');
        }
        @chmod($externalDir, 0700);
        if (!@is_readable($externalDir) || !@is_writable($externalDir)) {
            throw new RuntimeException('External secret storage is not readable/writable by PHP.');
        }

        $legacyDir = self::legacyDirectory();
        $candidates = [];
        foreach ((array)@glob($legacyDir . DIRECTORY_SEPARATOR . '*.key') as $legacy) {
            $name = basename($legacy);
            if (!preg_match('/^([a-z0-9_-]{1,32})\.key$/i', $name, $m)) continue;
            $candidates[strtolower((string)$m[1])] = $legacy;
        }

        $copied = [];
        $already = [];
        foreach ($candidates as $purpose => $legacy) {
            $raw = @file_get_contents($legacy);
            if (!is_string($raw) || strlen($raw) !== 32) {
                throw new RuntimeException('Legacy ' . $purpose . ' key is invalid or unreadable; migration stopped without changing it.');
            }
            $target = $externalDir . DIRECTORY_SEPARATOR . $purpose . '.key';
            if (@is_file($target)) {
                $existing = @file_get_contents($target);
                if (!is_string($existing) || strlen($existing) !== 32 || !hash_equals($raw, $existing)) {
                    throw new RuntimeException('External ' . $purpose . ' key already exists but does not match the legacy key. Migration stopped to prevent lockout.');
                }
                $already[] = $purpose;
                continue;
            }

            $tmp = $target . '.tmp.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $raw, LOCK_EX) !== 32) {
                @unlink($tmp);
                throw new RuntimeException('Could not write external ' . $purpose . ' key.');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $target)) {
                @unlink($tmp);
                throw new RuntimeException('Could not activate external ' . $purpose . ' key.');
            }
            @chmod($target, 0600);
            $check = @file_get_contents($target);
            if (!is_string($check) || !hash_equals($raw, $check)) {
                @unlink($target);
                throw new RuntimeException('External ' . $purpose . ' key verification failed.');
            }
            $copied[] = $purpose;
        }

        return [
            'external_dir'=>$externalDir,
            'copied'=>$copied,
            'already'=>$already,
            'status'=>self::storageStatus(),
        ];
    }

    private static function loadExistingKey(string $purpose): string
    {
        $file = self::keyPath($purpose);
        if (!is_file($file)) {
            throw new RuntimeException('The encryption key is missing. Restore the matching external secret key or the legacy storage/private backup before reading protected secrets.');
        }
        $raw = @file_get_contents($file);
        if (is_string($raw) && strlen($raw) === 32) return $raw;
        throw new RuntimeException('The encryption key is invalid or unreadable.');
    }

    private static function getOrCreateKey(string $purpose): string
    {
        $purpose = self::purpose($purpose);
        $file = self::keyPath($purpose);
        if (is_file($file)) return self::loadExistingKey($purpose);

        // New keys prefer persistent external storage. Existing legacy keys are
        // never moved automatically by a normal request; use Admin → Operations.
        $externalDir = self::externalDirectory();
        if ($externalDir !== null) {
            $root = self::cleanPath(dirname(__DIR__, 2));
            $outside = !self::pathInside(self::cleanPath($externalDir), $root);
            $externalWritable = @is_dir($externalDir)
                ? @is_writable($externalDir)
                : (@is_dir(dirname($externalDir)) && @is_writable(dirname($externalDir)));
            if ($outside && $externalWritable) {
                $file = $externalDir . DIRECTORY_SEPARATOR . $purpose . '.key';
            } else {
                // Keep functionality on constrained shared hosting. Health/Operations
                // will continue to flag this as legacy deployment-unsafe storage.
                $file = self::legacyDirectory() . DIRECTORY_SEPARATOR . $purpose . '.key';
            }
        }

        $dir = dirname($file);
        $dirMode = ($externalDir !== null && self::cleanPath($dir) === self::cleanPath($externalDir)) ? 0700 : 0770;
        if (!@is_dir($dir) && !@mkdir($dir, $dirMode, true) && !@is_dir($dir)) {
            throw new RuntimeException('Could not create private storage for the encryption key.');
        }
        if ($dirMode === 0700) @chmod($dir, 0700);

        $lockFile = $file . '.lock';
        $lock = @fopen($lockFile, 'c+');
        if (!$lock) throw new RuntimeException('Could not lock private secret storage.');
        try {
            if (!@flock($lock, LOCK_EX)) throw new RuntimeException('Could not lock private secret storage.');
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                if (is_string($raw) && strlen($raw) === 32) return $raw;
                throw new RuntimeException('The encryption key is invalid.');
            }
            $key = random_bytes(32);
            $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $key, LOCK_EX) !== 32) {
                @unlink($tmp);
                throw new RuntimeException('Could not save the encryption key.');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
                throw new RuntimeException('Could not activate the encryption key.');
            }
            @chmod($file, 0600);
            return $key;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    private static function purpose(string $purpose): string
    {
        $purpose = strtolower(trim($purpose));
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $purpose)) throw new RuntimeException('Invalid secret purpose.');
        return $purpose;
    }

    private static function cleanPath(string $path): string
    {
        $path = rtrim(trim($path), "\\/");
        return $path === '' ? DIRECTORY_SEPARATOR : $path;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\\\') || (bool)preg_match('/^[A-Za-z]:[\\\\\/]/', $path);
    }

    private static function pathInside(string $candidate, string $root): bool
    {
        $c = strtolower(str_replace('\\', '/', rtrim($candidate, "\\/")));
        $r = strtolower(str_replace('\\', '/', rtrim($root, "\\/")));
        return $c === $r || str_starts_with($c, $r . '/');
    }
}
