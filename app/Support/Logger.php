<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

final class Logger
{
    private const MAX_VALUE_LENGTH = 2000;
    private const RETENTION_DAYS = 30;
    private static ?string $requestId = null;
    private static bool $pruned = false;

    public static function requestId(): string
    {
        if (self::$requestId !== null) return self::$requestId;
        try {
            self::$requestId = bin2hex(random_bytes(8));
        } catch (Throwable) {
            self::$requestId = substr(hash('sha256', uniqid('', true)), 0, 16);
        }
        return self::$requestId;
    }

    public static function directory(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }

    public static function writable(): bool
    {
        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return false;
        return is_writable($dir);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function exception(Throwable $e, array $context = []): void
    {
        self::error('Unhandled exception', array_merge($context, [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]));
    }

    public static function sanitizeContext(array $context): array
    {
        return self::sanitizeArray($context, 0);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $record = [
            'time_utc' => gmdate('c'),
            'level' => $level,
            'request_id' => self::requestId(),
            'message' => self::truncate($message),
            'context' => self::sanitizeContext($context),
        ];

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) return;

        $dir = self::directory();
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        self::pruneOldLogs($dir);
        $file = $dir . '/app-' . gmdate('Y-m-d') . '.log';
        if (@file_put_contents($file, $json . PHP_EOL, FILE_APPEND | LOCK_EX) !== false) {
            @chmod($file, 0660);
        }
    }


    private static function pruneOldLogs(string $dir): void
    {
        if (self::$pruned) return;
        self::$pruned = true;
        $cutoff = time() - (self::RETENTION_DAYS * 86400);
        foreach ((array)glob($dir . '/app-????-??-??.log') as $file) {
            if (!is_file($file)) continue;
            $mtime = (int)(@filemtime($file) ?: 0);
            if ($mtime > 0 && $mtime < $cutoff) @unlink($file);
        }
    }

    private static function sanitizeArray(array $value, int $depth): array
    {
        if ($depth >= 4) return ['_truncated' => true];
        $out = [];
        foreach ($value as $key => $item) {
            $name = (string)$key;
            if (self::isSensitiveKey($name)) {
                $out[$name] = '[REDACTED]';
                continue;
            }
            if (is_array($item)) {
                $out[$name] = self::sanitizeArray($item, $depth + 1);
            } elseif (is_scalar($item) || $item === null) {
                $out[$name] = is_string($item) ? self::truncate($item) : $item;
            } elseif ($item instanceof Throwable) {
                $out[$name] = get_class($item) . ': ' . self::truncate($item->getMessage());
            } else {
                $out[$name] = '[' . get_debug_type($item) . ']';
            }
        }
        return $out;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/(?:pass(?:word)?|secret|token|authorization|cookie|csrf|session|api[_-]?key|private[_-]?key|credential)/i', $key);
    }

    private static function truncate(string $value): string
    {
        if (strlen($value) <= self::MAX_VALUE_LENGTH) return $value;
        return substr($value, 0, self::MAX_VALUE_LENGTH) . '…';
    }
}
