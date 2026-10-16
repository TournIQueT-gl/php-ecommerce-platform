<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/private_storage.php';
require_once __DIR__ . '/visitor_analytics.php';
require_once __DIR__ . '/ip_access.php';
require_once __DIR__ . '/store_helpers.php';
require_once __DIR__ . '/localization.php';

// Shared-hosting/XAMPP builds do not always enable mbstring. Keep the storefront
// functional without making that optional PHP extension a hard requirement.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int {
        if($value==='')return 0;
        $count=preg_match_all('/./us',$value,$matches);
        return $count===false?strlen($value):$count;
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        if($value==='')return '';
        $ok=preg_match_all('/./us',$value,$matches);
        if($ok===false)return $length===null?substr($value,$start):substr($value,$start,$length);
        $chars=$matches[0];$count=count($chars);
        if($start<0)$start=max(0,$count+$start);
        if($length===null)return implode('',array_slice($chars,$start));
        if($length<0)$length=max(0,$count-$start+$length);
        return implode('',array_slice($chars,$start,$length));
    }
}
if (!function_exists('mb_strtolower')) {
    // strtolower changes ASCII letters only and leaves UTF-8 bytes intact. This is
    // sufficient for the current case-insensitive search hash/slug fallback while
    // avoiding corruption on hosts without mbstring.
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}

function web_is_local_host(): bool {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Use an application-specific cookie name so localhost projects cannot collide
    // with another PHP application's generic PHPSESSID cookie.
    session_name('PERFUMESTORESESSID' . substr(hash('sha256', BASE_PATH === '' ? '/' : BASE_PATH), 0, 8));
    $secure = web_request_is_https() || (REQUIRE_HTTPS && !web_is_local_host());
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_PATH === '' ? '/' : BASE_PATH,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

function base_security_headers(): void {
    static $sent = false;
    if ($sent) return;
    $sent = true;

    $https = web_request_is_https();
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}


function csrf_cookie_name(): string {
    return 'PERFUMESTORECSRF' . substr(hash('sha256', BASE_PATH === '' ? '/' : BASE_PATH), 0, 8);
}

function csrf_same_origin_request(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return false;
    foreach (['HTTP_ORIGIN','HTTP_REFERER'] as $key) {
        $raw = trim((string)($_SERVER[$key] ?? ''));
        if ($raw === '') continue;
        $parts = parse_url($raw);
        if (!is_array($parts)) return false;
        $originHost = strtolower((string)($parts['host'] ?? ''));
        $originPort = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return ($originHost . $originPort) === $host;
    }
    // Normal HTML form posts may omit Origin/Referer due to privacy tooling.
    // SameSite=Lax session + CSRF cookies still prevent cross-site POST delivery.
    return true;
}

function csrf_sync_cookie(string $token): void {
    if (headers_sent()) return;
    $secure = web_request_is_https() || (REQUIRE_HTTPS && !web_is_local_host());
    setcookie(csrf_cookie_name(), $token, [
        'expires' => 0,
        'path' => BASE_PATH === '' ? '/' : BASE_PATH,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[csrf_cookie_name()] = $token;
}

function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $token = (string)$_SESSION['csrf_token'];
    csrf_sync_cookie($token);
    return $token;
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void {
    start_secure_session();
    $provided = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    $sessionMatch = $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    $cookieToken = (string)($_COOKIE[csrf_cookie_name()] ?? '');
    $cookieMatch = $cookieToken !== '' && $provided !== '' && hash_equals($cookieToken, $provided) && csrf_same_origin_request();
    if (!$sessionMatch && !$cookieMatch) {
        \App\Support\Logger::warning('CSRF validation failed', [
            'path' => web_request_path(),
            'session_cookie_present' => isset($_COOKIE[session_name()]),
            'session_id_present' => session_id() !== '',
            'csrf_session_present' => $expected !== '',
            'csrf_post_present' => $provided !== '',
            'csrf_cookie_present' => $cookieToken !== '',
            'session_cookie_name' => session_name(),
        ]);
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        if (str_contains($accept, 'application/json') || in_array($requestedWith, ['PerfumeStore-Wishlist','PerfumeStore-Cart','PerfumeStore-Builder','XMLHttpRequest'], true) || (string)($_GET['_ajax'] ?? '') !== '') {
            if(ob_get_level()>0)@ob_clean();
            http_response_code(419);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode([
                'ok' => false,
                'authenticated' => !empty($_SESSION['user_id']),
                'message' => 'Your session expired. Refresh the page and try again.',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
        require_once __DIR__ . '/error_page.php';
        $returnTo = (string)($_SERVER['REQUEST_URI'] ?? route_url());
        render_error_page(419, 'Session expired', 'SESSION EXPIRED.', 'Your security session expired. Refresh the page and try the action again.', 'REFRESH PAGE', $returnTo);
    }
    if (!$sessionMatch && $cookieMatch) {
        $_SESSION['csrf_token'] = $provided;
    }
}


function web_datetime_epoch(int|string|null $epoch, string $fallback = '—'): string {
    if ($epoch === null || $epoch === '' || !is_numeric($epoch)) return $fallback;
    $ts = (int)$epoch;
    if ($ts <= 0) return $fallback;

    try {
        $display = new DateTimeZone(WEB_DISPLAY_TIMEZONE);
        return (new DateTimeImmutable('@' . $ts))->setTimezone($display)->format('Y-m-d H:i:s T');
    } catch (Throwable $e) {
        return $fallback;
    }
}


function utc_epoch_from_db(?string $utcValue): ?int {
    if ($utcValue === null || trim($utcValue) === '') return null;
    try {
        $utc = new DateTimeZone('UTC');
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', trim($utcValue), $utc);
        if (!$dt) $dt = new DateTimeImmutable(trim($utcValue), $utc);
        return $dt->getTimestamp();
    } catch (Throwable $e) {
        return null;
    }
}

function web_local_datetime(?string $utcValue, string $fallback = '—'): string {
    $epoch = utc_epoch_from_db($utcValue);
    return web_local_datetime_epoch($epoch, $fallback);
}

function web_local_datetime_epoch(int|string|null $epoch, string $fallback = '—'): string {
    if ($epoch === null || $epoch === '' || !is_numeric($epoch) || (int)$epoch <= 0) {
        return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }
    $ts = (int)$epoch;
    $serverFallback = web_datetime_epoch($ts, $fallback);
    return '<time class="js-local-time" data-local-epoch="' . $ts . '" title="UTC: ' . htmlspecialchars(gmdate('Y-m-d H:i:s', $ts) . ' UTC', ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($serverFallback, ENT_QUOTES, 'UTF-8') . '</time>';
}


function client_ip(): string {
    return \App\Support\Request::clientIp();
}

function web_user_agent(): string {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown browser'), 0, 500);
}

function web_user_agent_label(?string $ua): string {
    $ua=(string)$ua;
    $browser='Browser';
    if (stripos($ua,'Edg/')!==false) $browser='Microsoft Edge';
    elseif (stripos($ua,'Firefox/')!==false) $browser='Firefox';
    elseif (stripos($ua,'Chrome/')!==false) $browser='Chrome';
    elseif (stripos($ua,'Safari/')!==false) $browser='Safari';
    $os='Unknown OS';
    if (stripos($ua,'Windows')!==false) $os='Windows';
    elseif (stripos($ua,'Android')!==false) $os='Android';
    elseif (stripos($ua,'iPhone')!==false||stripos($ua,'iPad')!==false) $os='iOS';
    elseif (stripos($ua,'Mac OS X')!==false) $os='macOS';
    elseif (stripos($ua,'Linux')!==false) $os='Linux';
    return $browser.' · '.$os;
}

function web_notification_excluded_types_for_user(?array $user): array {
    if(!$user || empty($user['is_admin']))return [];
    $role=strtolower(trim((string)($user['admin_role']??'')));
    if($role!=='support')return [];
    // Support admins can also use the normal Account notification center. Keep
    // privileged Admin-only event details hidden there as well as in Admin.
    return ['security.password_recovery_requested'];
}

function web_session_request_cache_reset(): void {
    unset($GLOBALS['_web_session_allowed_cache'], $GLOBALS['_web_current_user_cache']);
}

function web_register_current_session(int $userId): void {
    if($userId<=0||session_id()==='')return;
    web_session_request_cache_reset();
    try{\App\Support\ServiceFactory::webSessions(db())->register($userId,session_id(),client_ip(),web_user_agent());}catch(\Throwable $e){\App\Support\Logger::warning('Web session registration unavailable',['user_id'=>$userId,'message'=>$e->getMessage()]);}
}

function web_current_session_allowed(int $userId): bool {
    if($userId<=0||session_id()==='')return true;
    $cacheKey=$userId.'|'.session_id();
    if(isset($GLOBALS['_web_session_allowed_cache'][$cacheKey])){
        return (bool)$GLOBALS['_web_session_allowed_cache'][$cacheKey];
    }
    try {
        $repository = \App\Support\ServiceFactory::webSessions(db());
        // Keep compatibility with installations that genuinely predate the web_sessions
        // table, but once the table exists a repository failure must not silently bypass
        // an explicit session revocation.
        if (!$repository->schemaInstalled()) {
            $GLOBALS['_web_session_allowed_cache'][$cacheKey]=true;
            return true;
        }
        $allowed=$repository->ensureCurrent($userId,session_id(),client_ip(),web_user_agent());
        $GLOBALS['_web_session_allowed_cache'][$cacheKey]=$allowed;
        return $allowed;
    } catch (\Throwable $e) {
        \App\Support\Logger::warning('Web session validation failed closed', [
            'user_id'=>$userId,
            'message'=>$e->getMessage(),
        ]);
        $GLOBALS['_web_session_allowed_cache'][$cacheKey]=false;
        return false;
    }
}

function web_revoke_current_session(int $userId,string $reason='logout'): void {
    if($userId<=0||session_id()==='')return;
    web_session_request_cache_reset();
    try{\App\Support\ServiceFactory::webSessions(db())->revokeCurrent($userId,session_id(),$reason);}catch(\Throwable){}
}

function web_request_is_https(): bool {
    return \App\Support\Request::isHttps();
}


function web_flash_set(string $key, string $message = '', string $error = ''): void {
    start_secure_session();
    if (!isset($_SESSION['_web_flash']) || !is_array($_SESSION['_web_flash'])) {
        $_SESSION['_web_flash'] = [];
    }
    $_SESSION['_web_flash'][$key] = ['message' => $message, 'error' => $error];
}

function web_flash_take(string $key): array {
    start_secure_session();
    $flash = $_SESSION['_web_flash'][$key] ?? ['message' => '', 'error' => ''];
    unset($_SESSION['_web_flash'][$key]);
    if (isset($_SESSION['_web_flash']) && $_SESSION['_web_flash'] === []) unset($_SESSION['_web_flash']);
    return [
        'message' => (string)($flash['message'] ?? ''),
        'error' => (string)($flash['error'] ?? ''),
    ];
}

function web_post_redirect(string $route, string $flashKey, string $message = '', string $error = '', string $query = ''): never {
    web_flash_set($flashKey, $message, $error);
    $location = route_url($route);
    if ($query !== '') $location .= (str_starts_with($query, '?') ? $query : '?' . $query);
    header('Location: ' . $location, true, 303);
    exit;
}

function web_safe_exception_message(\Throwable $e, string $operation, string $publicMessage = 'Something went wrong.'): string {
    \App\Support\Logger::exception($e, ['operation' => $operation]);
    return rtrim($publicMessage) . ' Reference: ' . \App\Support\Logger::requestId();
}

function enforce_web_https(): void {
    // Local XAMPP/WAMP development should work over normal http://localhost without
    // certificate warnings. Production still enforces HTTPS exactly as before.
    if (!REQUIRE_HTTPS || web_request_is_https() || web_is_local_host()) return;

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(?::\d{1,5})?$/', $host)) {
        http_response_code(400);
        exit('Bad request');
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? route_url());
    header('Location: https://' . $host . $uri, true, 302);
    exit;
}

function web_security_headers(): void {
    // Browser pages receive one consistent content-security policy.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https:; media-src 'self' https: blob:; font-src 'self' data: https://fonts.gstatic.com; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}


function web_emit_server_timing(string $metric='app'): void {
    if(headers_sent())return;
    $metric=preg_replace('/[^a-zA-Z0-9_-]+/','',trim($metric))?:'app';
    $start=(float)($_SERVER['REQUEST_TIME_FLOAT']??microtime(true));
    $duration=max(0.0,(microtime(true)-$start)*1000.0);
    header('Server-Timing: '.$metric.';dur='.number_format($duration,1,'.','').';desc="PHP to layout"',false);
}

function web_private_response_headers(): void {
    // Prevent authenticated/account/admin responses and auth redirects from being reused by browsers or intermediaries.
    header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function web_request_path(): string {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
        $path = substr($path, strlen(BASE_PATH)) ?: '/';
    }
    return '/' . ltrim($path, '/');
}

function web_session_is_admin(): bool {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) return false;
    try {
        $stmt = db()->prepare('SELECT is_admin,is_active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row && (bool)$row['is_admin'] && (bool)$row['is_active'];
    } catch (Throwable $e) {
        return false;
    }
}

function enforce_web_maintenance(): void {
    if (!maintenance_enabled()) return;

    $path = strtolower(web_request_path());
    $allowed = [
        '/maintenance', '/maintenance.php',
        '/login', '/login.php',
        '/forgot-password', '/forgot-password.php',
        '/verify-email', '/verify-email.php',
        '/two-factor', '/two-factor.php',
        '/logout', '/logout.php',
    ];
    if (in_array($path, $allowed, true)) return;
    if (str_starts_with($path, '/admin/') || $path === '/admin' || web_session_is_admin()) return;

    header('Location: ' . route_url('maintenance.php'), true, 302);
    exit;
}

function web_apply_robots_policy(): void {
    $path = strtolower(web_request_path());
    $indexable = ['/', '/index.php', '/perfumes', '/perfumes.php', '/product', '/product.php', '/faq', '/faq.php', '/privacy', '/privacy.php', '/shipping-delivery', '/shipping-delivery.php', '/returns', '/returns.php', '/sitemap.xml', '/sitemap.php'];
    if (!in_array($path, $indexable, true)) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
}

function web_foundation_pulse_due(): bool {
    if (!FOUNDATION_WEB_SCHEDULER_ENABLED || PHP_SAPI === 'cli' || \foundation_scheduler_mode()==='cli') return false;
    $localMarkerDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    $marker = (is_dir($localMarkerDir) && is_writable($localMarkerDir))
        ? $localMarkerDir . DIRECTORY_SEPARATOR . '.web_scheduler_tick'
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'auraa_web_scheduler_' . substr(hash('sha256', dirname(__DIR__)), 0, 18) . '.tick';
    $handle=@fopen($marker,'c+');
    if($handle===false)return false;
    if(!@flock($handle,LOCK_EX|LOCK_NB)){@fclose($handle);return false;}
    try{
        @rewind($handle);$last=(int)trim((string)stream_get_contents($handle));$now=time();
        if($last>0&&($now-$last)<FOUNDATION_WEB_SCHEDULER_INTERVAL_SECONDS)return false;
        @ftruncate($handle,0);@rewind($handle);@fwrite($handle,(string)$now);@fflush($handle);
        return true;
    }finally{@flock($handle,LOCK_UN);@fclose($handle);}
}

function web_run_foundation_pulse(): array {
    if(!web_foundation_pulse_due())return ['web_scheduler'=>['status'=>'throttled']];
    try{return \App\Support\ServiceFactory::scheduler(db())->runWebFallback(FOUNDATION_WEB_EVENT_BATCH_SIZE,false);}
    catch(\Throwable $e){
        \App\Support\Logger::warning('Web scheduler pulse failed',['exception'=>get_class($e),'message'=>$e->getMessage()]);
        return ['web_scheduler'=>['status'=>'failed']];
    }
}

function web_schedule_foundation_tick(): void {
    // v4.2.5: intentionally no-op. Maintenance is launched by a separate browser
    // pulse after the page has loaded, so Apache/mod_php never holds a navigation
    // response open for event/email/cleanup work.
}

function init_web_request(): void {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;
    // Browser pages always establish their session before any view output. This keeps
    // CSRF stable on extensionless routes and subdirectory installs such as XAMPP.
    start_secure_session();
    // These settings are intentionally browser/admin-only.
    \App\Support\ErrorHandler::register();
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    enforce_web_https();
    web_security_headers();
    web_apply_robots_policy();
    $requestPath=strtolower(web_request_path());
    if (in_array($requestPath, ['/login','/login.php','/register','/register.php','/forgot-password','/forgot-password.php','/verify-email','/verify-email.php','/two-factor','/two-factor.php','/track-order','/track-order.php'], true)) {
        web_private_response_headers();
    }
    if(in_array($requestPath,['/forgot-password','/forgot-password.php','/verify-email','/verify-email.php','/track-order','/track-order.php'],true)){
        header('Referrer-Policy: no-referrer');
    }

    if (!empty($_SESSION['user_id'])) {
        $now = time();
        $last = (int)($_SESSION['_web_last_activity'] ?? 0);
        if ($last > 0 && ($now - $last) > WEB_SESSION_IDLE_SECONDS) {
            web_revoke_current_session((int)$_SESSION['user_id'],'idle_timeout');
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            $_SESSION['_web_last_activity'] = $now;
        }
    }

    enforce_web_maintenance();
    // Pageview persistence and free-host maintenance run through the detached
    // browser pulse after load; keep the navigation request focused on rendering.
}

function destroy_web_session(): void {
    web_session_request_cache_reset();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function rate_limit_file(string $scope, string $identity = ''): string {
    $identity = trim($identity);
    // Login account buckets must follow the account across source-IP rotation.
    // IP buckets remain separate and still include client_ip(). Keep the change
    // deliberately scoped to login throttles so other identity-bound controls
    // preserve their existing behavior.
    $accountWide=in_array($scope,['web_login_user_fail'],true)&&$identity!=='';
    $networkKey=$accountWide?'account-wide':client_ip();
    // Salt with the deployment root so multiple applications sharing one system
    // temp directory cannot accidentally share throttle buckets.
    $key = hash('sha256', dirname(__DIR__) . '|' . $scope . '|' . $networkKey . '|' . $identity);
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'auraa_rl_' . $key . '.json';
}

function rate_limit_prune_files(int $olderThanSeconds=172800): int {
    $olderThanSeconds=max(3600,$olderThanSeconds);$deleted=0;$now=time();
    foreach((array)glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'auraa_rl_*.json') as $path){
        if(!is_file($path))continue;$mtime=(int)@filemtime($path);
        if($mtime>0&&($now-$mtime)>$olderThanSeconds&&@unlink($path))$deleted++;
    }
    return $deleted;
}

function rate_limit_state(string $scope, int $windowSeconds, string $identity = '', bool $increment = false): array {
    $now = time();
    $file = rate_limit_file($scope, $identity);
    $handle = @fopen($file, 'c+');
    if (!$handle) {
        \App\Support\Logger::warning('Rate-limit storage unavailable', ['scope'=>$scope]);
        return ['available'=>false,'start'=>$now,'attempts'=>0];
    }

    $data = ['start'=>$now,'attempts'=>0];
    $locked = false;
    try {
        $locked = @flock($handle, LOCK_EX);
        if (!$locked) {
            \App\Support\Logger::warning('Rate-limit lock unavailable', ['scope'=>$scope]);
            return ['available'=>false,'start'=>$now,'attempts'=>0];
        }
        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) $data = $decoded;
        if (($now - (int)($data['start'] ?? 0)) >= $windowSeconds) {
            $data = ['start'=>$now,'attempts'=>0];
        }
        if ($increment) {
            $data['attempts'] = (int)($data['attempts'] ?? 0) + 1;
            $encoded = json_encode($data);
            if ($encoded !== false) {
                rewind($handle);
                @ftruncate($handle, 0);
                @fwrite($handle, $encoded);
                @fflush($handle);
            }
        }
    } finally {
        if ($locked) @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    return [
        'available'=>true,
        'start'=>(int)($data['start'] ?? $now),
        'attempts'=>(int)($data['attempts'] ?? 0),
    ];
}

function rate_limit_retry_after(array $state, int $maxAttempts, int $windowSeconds, bool $blockAtLimit = true): int {
    if (empty($state['available'])) return 0; // Preserve the existing fail-open behavior when local limiter storage is unavailable.
    $attempts = (int)($state['attempts'] ?? 0);
    $blocked = $blockAtLimit ? ($attempts >= $maxAttempts) : ($attempts > $maxAttempts);
    if (!$blocked) return 0;
    return max(1, $windowSeconds - (time() - (int)($state['start'] ?? time())));
}

function rate_limit_clear(string $scope, string $identity = ''): void {
    $file = rate_limit_file($scope, $identity);
    $handle = @fopen($file, 'c+');
    if (!$handle) return;
    try {
        if (!@flock($handle, LOCK_EX)) return;
        @ftruncate($handle, 0);
        @fflush($handle);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }
}

function web_rate_limit_clear(string $scope, string $identity = ''): void {
    rate_limit_clear($scope, $identity);
}

function web_rate_limit_retry(string $scope, int $maxAttempts, int $windowSeconds, string $identity = ''): int {
    $state=rate_limit_state($scope,$windowSeconds,$identity,false);
    if(empty($state['available'])) return min(300,max(30,$windowSeconds)); // Browser auth/security actions fail closed when limiter storage is unavailable.
    return rate_limit_retry_after($state,$maxAttempts,$windowSeconds,true);
}

function web_rate_limit_failure(string $scope, int $maxAttempts, int $windowSeconds, string $identity = ''): int {
    $state=rate_limit_state($scope,$windowSeconds,$identity,true);
    if(empty($state['available'])) return min(300,max(30,$windowSeconds));
    return rate_limit_retry_after($state,$maxAttempts,$windowSeconds,false);
}

function web_rate_limit_consume(string $scope, int $maxAttempts, int $windowSeconds, string $identity = ''): int {
    $state=rate_limit_state($scope,$windowSeconds,$identity,false);
    if(empty($state['available'])) return min(300,max(30,$windowSeconds));
    $retry=rate_limit_retry_after($state,$maxAttempts,$windowSeconds,true);
    if($retry>0)return $retry;
    $state=rate_limit_state($scope,$windowSeconds,$identity,true);
    if(empty($state['available'])) return min(300,max(30,$windowSeconds));
    return rate_limit_retry_after($state,$maxAttempts,$windowSeconds,false);
}


base_security_headers();
// Enforce explicit admin IP/CIDR blocks before browser sessions or visitor cookies.
ip_access_enforce_request();
// Establish browser sessions before any page can emit output. The tiny cost is
// worth the reliability for auth/CSRF, especially on localhost/subdirectory installs.
start_secure_session();
