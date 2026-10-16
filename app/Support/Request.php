<?php
declare(strict_types=1);

namespace App\Support;

final class Request
{
    /**
     * Return the address of the directly connected peer.
     */
    public static function remoteIp(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    /**
     * Return the real client IP only when the directly connected peer is an
     * explicitly trusted reverse proxy. Untrusted requests cannot spoof XFF,
     * Forwarded or Cloudflare headers into the application.
     */
    public static function clientIp(): string
    {
        $remote = self::remoteIp();
        if ($remote === 'unknown' || !self::isTrustedProxy($remote)) return $remote;

        $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if (filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        $xff = self::validIpList((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== []) {
            $xff[] = $remote;
            for ($i = count($xff) - 1; $i >= 0; $i--) {
                if (!self::isTrustedProxy($xff[$i])) return $xff[$i];
            }
            return $xff[0];
        }

        $forwarded = self::forwardedForIps((string)($_SERVER['HTTP_FORWARDED'] ?? ''));
        if ($forwarded !== []) {
            $forwarded[] = $remote;
            for ($i = count($forwarded) - 1; $i >= 0; $i--) {
                if (!self::isTrustedProxy($forwarded[$i])) return $forwarded[$i];
            }
            return $forwarded[0];
        }

        return $remote;
    }

    public static function isHttps(): bool
    {
        $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') return true;
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
        if (strtolower(trim((string)($_SERVER['REQUEST_SCHEME'] ?? ''))) === 'https') return true;

        $remote = self::remoteIp();
        if ($remote === 'unknown' || !self::isTrustedProxy($remote)) return false;

        $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
        if ($forwardedProto === 'https') return true;
        if (strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''))) === 'on') return true;
        if (preg_match('/(?:^|[;,\s])proto=https(?:[;,\s]|$)/i', (string)($_SERVER['HTTP_FORWARDED'] ?? ''))) return true;

        $cfVisitor = json_decode((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''), true);
        return is_array($cfVisitor) && strtolower((string)($cfVisitor['scheme'] ?? '')) === 'https';
    }

    public static function isTrustedProxy(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
        foreach (self::trustedProxyCidrs() as $cidr) {
            if (self::ipInCidr($ip, $cidr)) return true;
        }
        return false;
    }

    /** @return list<string> */
    private static function trustedProxyCidrs(): array
    {
        $cidrs = [];
        if (defined('TRUSTED_PROXY_CIDRS') && is_array(TRUSTED_PROXY_CIDRS)) {
            foreach (TRUSTED_PROXY_CIDRS as $cidr) {
                $cidr = trim((string)$cidr);
                if ($cidr !== '') $cidrs[] = $cidr;
            }
        }

        // Optional runtime override/extension for hosts where proxy ranges are
        // easier to configure as environment variables than in source code.
        $env = trim((string)(getenv('PERFUME_TRUSTED_PROXIES') ?: ''));
        if ($env !== '') {
            foreach (explode(',', $env) as $cidr) {
                $cidr = trim($cidr);
                if ($cidr !== '') $cidrs[] = $cidr;
            }
        }

        return array_values(array_unique($cidrs));
    }

    /** @return list<string> */
    private static function validIpList(string $header): array
    {
        $out = [];
        foreach (explode(',', $header) as $part) {
            $ip = trim($part, " \t\n\r\0\x0B\"");
            if (filter_var($ip, FILTER_VALIDATE_IP)) $out[] = $ip;
        }
        return $out;
    }

    /** @return list<string> */
    private static function forwardedForIps(string $header): array
    {
        $out = [];
        if ($header === '') return $out;
        if (preg_match_all('/(?:^|[;,])\s*for=(?:"?\[?)([0-9a-f:.]+)(?:\]?"?)(?::\d+)?/i', $header, $matches)) {
            foreach ($matches[1] as $ip) {
                $ip = trim((string)$ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) $out[] = $ip;
            }
        }
        return $out;
    }

    public static function ipInCidr(string $ip, string $cidr): bool
    {
        $cidr = trim($cidr);
        if ($cidr === '') return false;
        if (!str_contains($cidr, '/')) return hash_equals($cidr, $ip);

        [$network, $prefixText] = array_pad(explode('/', $cidr, 2), 2, '');
        if (!ctype_digit($prefixText)) return false;

        $ipBin = @inet_pton($ip);
        $networkBin = @inet_pton($network);
        if ($ipBin === false || $networkBin === false || strlen($ipBin) !== strlen($networkBin)) return false;

        $bits = strlen($ipBin) * 8;
        $prefix = (int)$prefixText;
        if ($prefix < 0 || $prefix > $bits) return false;

        $fullBytes = intdiv($prefix, 8);
        $remainder = $prefix % 8;
        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($networkBin, 0, $fullBytes)) return false;
        if ($remainder === 0) return true;

        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBin[$fullBytes]) & $mask) === (ord($networkBin[$fullBytes]) & $mask);
    }
}
