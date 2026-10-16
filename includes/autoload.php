<?php
declare(strict_types=1);

// Composer is preferred when available. The fallback keeps the deployed site
// self-contained on hosts where Composer is not available at runtime.
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') return;

    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require_once $file;
});
