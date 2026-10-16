<?php
declare(strict_types=1);

/**
 * Private database configuration.
 * Priority:
 * 1) PERFUME_DB_CONFIG -> absolute PHP config file path.
 * 2) config/database.local.php -> local/server-only config.
 * 3) PERFUME_DB_HOST / NAME / USER / PASS environment variables.
 */
function db_config(): array {
    static $config = null;
    if (is_array($config)) return $config;

    $external = trim((string)(getenv('PERFUME_DB_CONFIG') ?: ''));
    $local = __DIR__ . '/database.local.php';
    if ($external !== '' && is_file($external)) {
        $loaded = require $external;
        if (is_array($loaded)) { $config = $loaded; }
    }
    if (!is_array($config) && is_file($local)) {
        $loaded = require $local;
        if (is_array($loaded)) { $config = $loaded; }
    }
    if (!is_array($config)) {
        $env = [
            'host' => trim((string)(getenv('PERFUME_DB_HOST') ?: 'localhost')),
            'name' => trim((string)(getenv('PERFUME_DB_NAME') ?: '')),
            'user' => trim((string)(getenv('PERFUME_DB_USER') ?: '')),
            'pass' => (string)(getenv('PERFUME_DB_PASS') ?: ''),
        ];
        if ($env['name'] !== '' && $env['user'] !== '') { $config = $env; }
    }

    if (!is_array($config)) {
        throw new RuntimeException('Database credentials are not configured. Create config/database.local.php from database.local.php.example or set PERFUME_DB_* environment variables.');
    }

    $config = [
        'host' => trim((string)($config['host'] ?? 'localhost')) ?: 'localhost',
        'name' => trim((string)($config['name'] ?? '')),
        'user' => trim((string)($config['user'] ?? '')),
        'pass' => (string)($config['pass'] ?? ''),
    ];
    if ($config['name'] === '' || $config['user'] === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }
    return $config;
}


function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $cfg = db_config();
    $pdo = new PDO(
        'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['name'] . ';charset=utf8mb4',
        $cfg['user'],
        $cfg['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}
