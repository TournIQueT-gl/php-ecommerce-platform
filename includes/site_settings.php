<?php
declare(strict_types=1);

function site_settings_file(): string {
    return dirname(__DIR__) . '/storage/site-settings.json';
}

function site_settings_lock_file(): string {
    return dirname(__DIR__) . '/storage/site-settings.lock';
}

function site_settings_defaults(): array {
    return [
        'maintenance' => [
            'enabled' => false,
            'message' => 'We are performing scheduled maintenance. Please check back shortly.',
            'updated_at' => null,
        ],
        'analytics' => [
            'enabled' => true,
            'retention_days' => 90,
            'updated_at' => null,
        ],
    ];
}

function site_settings_load_unlocked(): array {
    $defaults = site_settings_defaults();
    $file = site_settings_file();
    if (!is_file($file)) return $defaults;

    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) return $defaults;

    $maintenance = is_array($data['maintenance'] ?? null) ? $data['maintenance'] : [];
    $defaults['maintenance']['enabled'] = !empty($maintenance['enabled']);
    $message = trim((string)($maintenance['message'] ?? ''));
    if ($message !== '') $defaults['maintenance']['message'] = substr($message, 0, 500);
    $defaults['maintenance']['updated_at'] = $maintenance['updated_at'] ?? null;

    $analytics = is_array($data['analytics'] ?? null) ? $data['analytics'] : [];
    if (array_key_exists('enabled',$analytics)) $defaults['analytics']['enabled'] = !empty($analytics['enabled']);
    $retention=(int)($analytics['retention_days']??90);
    $defaults['analytics']['retention_days'] = in_array($retention,[30,60,90,180],true)?$retention:90;
    $defaults['analytics']['updated_at'] = $analytics['updated_at'] ?? null;

    return $defaults;
}

function site_settings_load(): array {
    // Request-local cache: maintenance + analytics can ask for the same JSON several
    // times during one render. Writers publish by atomic rename and invalidate this
    // cache after a successful mutation.
    if(isset($GLOBALS['_site_settings_request_cache']) && is_array($GLOBALS['_site_settings_request_cache'])) return $GLOBALS['_site_settings_request_cache'];
    return $GLOBALS['_site_settings_request_cache']=site_settings_load_unlocked();
}

function site_settings_save_unlocked(array $settings): bool {
    $file = site_settings_file();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return false;

    $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = $file . '.tmp.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) return false;
    @chmod($tmp, 0660);
    if (@rename($tmp, $file)) { unset($GLOBALS['_site_settings_request_cache']); return true; }
    @unlink($tmp);
    return false;
}

function site_settings_with_lock(callable $callback): mixed {
    $dir=dirname(site_settings_file());
    if (!is_dir($dir) && !@mkdir($dir,0770,true) && !is_dir($dir)) return null;
    $handle=@fopen(site_settings_lock_file(),'c+');
    if(!$handle)return null;
    try{
        if(!@flock($handle,LOCK_EX))return null;
        return $callback();
    }finally{
        @flock($handle,LOCK_UN);
        @fclose($handle);
    }
}


function site_settings_mutate(callable $mutator): bool {
    return site_settings_with_lock(static function() use ($mutator): bool {
        $settings=site_settings_load_unlocked();
        $next=$mutator($settings);
        if(!is_array($next))return false;
        return site_settings_save_unlocked($next);
    }) === true;
}

function maintenance_state(): array {
    $settings = site_settings_load();
    return is_array($settings['maintenance'] ?? null) ? $settings['maintenance'] : site_settings_defaults()['maintenance'];
}

function maintenance_enabled(): bool {
    return !empty(maintenance_state()['enabled']);
}

function maintenance_set(bool $enabled, string $message): bool {
    $message = trim($message);
    if ($message === '') $message = site_settings_defaults()['maintenance']['message'];
    return site_settings_mutate(static function(array $settings) use ($enabled,$message): array {
        $settings['maintenance'] = [
            'enabled' => $enabled,
            'message' => substr($message, 0, 500),
            'updated_at' => gmdate('c'),
        ];
        return $settings;
    });
}

function analytics_settings(): array {
    $settings=site_settings_load();
    return is_array($settings['analytics']??null)?$settings['analytics']:site_settings_defaults()['analytics'];
}
