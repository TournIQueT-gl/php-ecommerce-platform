<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Support\ServiceFactory;

const ADMIN_TOTP_PERIOD = 30;
const ADMIN_TOTP_DIGITS = 6;
const ADMIN_TOTP_RECOVERY_COUNT = 8;

function admin_2fa_schema_ready(): bool {
    static $ready = null;
    if (is_bool($ready)) return $ready;
    return $ready = ServiceFactory::twoFactor(db())->schemaReady();
}

function totp_base32_encode(string $bytes): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $length = strlen($bytes);
    for ($i = 0; $i < $length; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0, $n = strlen($bits); $i < $n; $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0');
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function totp_base32_decode(string $value): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $value) ?? '');
    if ($value === '') return '';
    $bits = '';
    foreach (str_split($value) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) return '';
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0, $n = strlen($bits); $i + 8 <= $n; $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }
    return $out;
}

function totp_generate_secret(int $bytes = 20): string {
    return totp_base32_encode(random_bytes(max(16, min(64, $bytes))));
}

function totp_secret_is_valid(string $secret): bool {
    $secret = strtoupper(trim($secret));
    return preg_match('/^[A-Z2-7]{32}$/', $secret) === 1;
}

function totp_format_secret(string $secret): string {
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    return trim(chunk_split($secret, 4, ' '));
}

function totp_code_at(string $secret, int $timestamp): string {
    $key = totp_base32_decode($secret);
    if ($key === '') return '';
    $counter = intdiv($timestamp, ADMIN_TOTP_PERIOD);
    $high = ($counter >> 32) & 0xFFFFFFFF;
    $low = $counter & 0xFFFFFFFF;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $binary = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);
    $mod = 10 ** ADMIN_TOTP_DIGITS;
    return str_pad((string)($binary % $mod), ADMIN_TOTP_DIGITS, '0', STR_PAD_LEFT);
}

function totp_verify_code(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D+/', '', $code) ?? '';
    if (strlen($code) !== ADMIN_TOTP_DIGITS) return false;
    $now = time();
    for ($step = -$window; $step <= $window; $step++) {
        $expected = totp_code_at($secret, $now + ($step * ADMIN_TOTP_PERIOD));
        if ($expected !== '' && hash_equals($expected, $code)) return true;
    }
    return false;
}

function totp_otpauth_uri(string $secret, string $username): string {
    $issuerName=store_runtime_name();
    $label = rawurlencode($issuerName . ':' . $username);
    $issuer = rawurlencode($issuerName);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=' . ADMIN_TOTP_DIGITS . '&period=' . ADMIN_TOTP_PERIOD;
}

function totp_normalize_recovery_code(string $code): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
}

function totp_recovery_hash(string $code): string {
    return hash('sha256', totp_normalize_recovery_code($code));
}

function totp_generate_recovery_codes(int $count = ADMIN_TOTP_RECOVERY_COUNT): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return $codes;
}

function totp_encode_recovery_hashes(array $codes): string {
    $hashes = array_values(array_map(static fn(string $code): string => totp_recovery_hash($code), $codes));
    return json_encode($hashes, JSON_UNESCAPED_SLASHES) ?: '[]';
}

function totp_decode_recovery_hashes(?string $json): array {
    if ($json === null || trim($json) === '') return [];
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return [];
    return array_values(array_filter($decoded, static fn($v): bool => is_string($v) && preg_match('/^[a-f0-9]{64}$/i', $v) === 1));
}

function admin_2fa_state(int $userId): array {
    if (!admin_2fa_schema_ready()) {
        return ['enabled' => false, 'secret' => null, 'recovery_codes' => [], 'enabled_at' => null];
    }
    $row = ServiceFactory::twoFactor(db())->stateRow($userId);
    return [
        'enabled' => !empty($row['admin_totp_enabled']) && !empty($row['admin_totp_secret']),
        'secret' => $row['admin_totp_secret'] ?? null,
        'recovery_codes' => totp_decode_recovery_hashes($row['admin_totp_recovery_codes'] ?? null),
        'enabled_at' => $row['admin_totp_enabled_at'] ?? null,
    ];
}

function admin_2fa_verify_or_consume(int $userId, string $code): array {
    if (!admin_2fa_schema_ready()) return ['ok' => false, 'recovery' => false];
    $db=db();
    $db->beginTransaction();
    try {
        // Lock the user's 2FA state so one recovery code cannot be accepted by
        // two concurrent requests before either request removes its hash.
        $row=ServiceFactory::twoFactor($db)->stateRowForUpdate($userId);
        $enabled=!empty($row['admin_totp_enabled']) && !empty($row['admin_totp_secret']);
        if(!$enabled){$db->commit();return ['ok'=>false,'recovery'=>false];}

        $secret=(string)($row['admin_totp_secret']??'');
        if(totp_verify_code($secret,$code)){$db->commit();return ['ok'=>true,'recovery'=>false];}

        $normalized=totp_normalize_recovery_code($code);
        if($normalized===''){$db->commit();return ['ok'=>false,'recovery'=>false];}
        $candidate=hash('sha256',$normalized);
        $hashes=totp_decode_recovery_hashes($row['admin_totp_recovery_codes']??null);
        foreach($hashes as $index=>$stored){
            if(hash_equals((string)$stored,$candidate)){
                unset($hashes[$index]);
                $json=json_encode(array_values($hashes),JSON_UNESCAPED_SLASHES)?:'[]';
                ServiceFactory::twoFactor($db)->updateRecoveryHashes($userId,$json);
                $db->commit();
                return ['ok'=>true,'recovery'=>true];
            }
        }
        $db->commit();
        return ['ok'=>false,'recovery'=>false];
    } catch (Throwable $e) {
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
}
