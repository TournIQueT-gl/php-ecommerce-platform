<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;
    private static bool $handlingFatal = false;

    public static function register(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        if (!headers_sent()) header('X-Request-ID: ' . Logger::requestId());

        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handlePhpError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) return false;
        Logger::warning('PHP runtime error', array_merge(self::requestContext(), [
            'severity' => $severity,
            'error' => $message,
            'file' => $file,
            'line' => $line,
        ]));
        return false;
    }

    public static function handleException(Throwable $e): never
    {
        Logger::exception($e, self::requestContext());
        self::render500();
    }

    public static function handleShutdown(): void
    {
        if (self::$handlingFatal) return;
        $error = error_get_last();
        if (!is_array($error)) return;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) return;

        self::$handlingFatal = true;
        Logger::error('Fatal PHP error', array_merge(self::requestContext(), [
            'severity' => (int)($error['type'] ?? 0),
            'error' => (string)($error['message'] ?? 'Unknown fatal error'),
            'file' => (string)($error['file'] ?? ''),
            'line' => (int)($error['line'] ?? 0),
        ]));

        if (!headers_sent()) {
            try {
                self::render500();
            } catch (Throwable) {
                http_response_code(500);
                echo 'Server error. Reference: ' . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8');
            }
        }
    }

    private static function render500(): never
    {
        self::$handlingFatal = true;
        $root = dirname(__DIR__, 2);
        $errorPage = $root . '/includes/error_page.php';
        if (is_file($errorPage)) require_once $errorPage;

        if (function_exists('render_error_page')) {
            \render_error_page(
                500,
                'Server error',
                'SYSTEM ERROR.',
                'The website hit an unexpected error. Please try again shortly.',
                'BACK TO HOME',
                function_exists('url') ? \url() : '/',
                Logger::requestId()
            );
        }

        http_response_code(500);
        echo 'Server error. Reference: ' . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8');
        exit;
    }

    private static function requestContext(): array
    {
        return [
            'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'path' => (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'),
            'user_id' => (int)($_SESSION['user_id'] ?? 0),
            'remote_ip' => function_exists('client_ip') ? \client_ip() : (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
    }
}
