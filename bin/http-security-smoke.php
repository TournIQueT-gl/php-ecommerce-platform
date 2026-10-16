<?php
/**
 * Read-only live HTTP smoke check for an explicitly supplied staging/production origin.
 * Example: php bin/http-security-smoke.php https://shop.example.com --json
 * It makes HEAD/GET requests only to public entry points and known private paths.
 * Does not authenticate, submit forms, print response bodies, or change server data.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args = array_slice($argv, 1);
$asJson = in_array('--json', $args, true);
$args = array_values(array_filter($args, static fn($x) => $x !== '--json'));
if (count($args) !== 1) { fwrite(STDERR, "Usage: php bin/http-security-smoke.php https://your-domain.example [--json]\n"); exit(1); }
$origin = rtrim((string)$args[0], '/');
$parts = parse_url($origin);
$host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || $host === '' ||
    !preg_match('/^[a-z0-9.-]+$/i', $host) || in_array($host, ['localhost','127.0.0.1'], true) ||
    isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) ||
    isset($parts['port']) || $host === 'example.com' || str_ends_with($host, '.example')) {
    fwrite(STDERR, "Supply an explicit HTTPS site origin without credentials, query, fragment or port.\n"); exit(1);
}
if (!filter_var($origin, FILTER_VALIDATE_URL)) { fwrite(STDERR, "Invalid site URL.\n"); exit(1); }
$paths = [
    '/' => 'public',
    '/robots.txt' => 'public',
    '/bin/release-gate.php' => 'private',
    '/config/app.php' => 'private',
    '/database/baseline/perfume_store_v2_3_0.sql' => 'private',
    '/tests/v5-release-gate.php' => 'private',
    '/.htaccess' => 'private',
    '/PRODUCTION-READINESS-RELEASE-GATE.md' => 'private',
];
$results = [];
foreach ($paths as $path => $type) {
    $url = $origin . $path;
    // Avoid following redirects: a 302 to login/another site cannot prove protection.
    $context = stream_context_create(['http' => [
        'method'=>'GET', 'follow_location'=>0, 'max_redirects'=>0,
        'timeout'=>8, 'ignore_errors'=>true,
        'header'=>"User-Agent: UniversalCommerceReadOnlyReleaseSmoke/1.0\r\nConnection: close\r\n"
    ], 'ssl' => ['verify_peer'=>true,'verify_peer_name'=>true]]);
    $headers = @get_headers($url, true, $context);
    $status = 0;
    if (is_array($headers) && preg_match('/^HTTP\/\S+\s+(\d{3})/', (string)($headers[0] ?? ''), $match)) $status = (int)$match[1];
    $ok = $type === 'private' ? in_array($status, [403,404], true) : ($status >= 200 && $status < 300);
    // HTTPS static assets sometimes return 304 with caching proxies; no cache headers are sent by this probe.
    $results[] = ['path'=>$path, 'expect'=>$type, 'http_status'=>$status, 'passed'=>$ok];
}
$failures = count(array_filter($results, static fn($r)=>!$r['passed']));
$payload = ['decision'=>$failures===0?'HTTP_SMOKE_PASS':'BLOCKED','origin'=>$origin,
    'failures'=>$failures, 'results'=>$results,
    'note'=>'HTTP disclosure smoke only; not a live security audit, checkout test or production approval.'];
if ($asJson) echo json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
else { echo 'HTTP SECURITY SMOKE: '.$payload['decision'].PHP_EOL; foreach ($results as $r) echo ($r['passed']?'PASS':'FAIL').' '.$r['http_status'].' '.$r['path'].PHP_EOL; }
exit($failures===0 ? 0 : 2);
