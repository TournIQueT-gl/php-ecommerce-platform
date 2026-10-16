<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin_layout.php';
require_once __DIR__ . '/totp.php';

use App\Support\ServiceFactory;

function require_admin(): void {
    require_login();
    $user = current_user();

    if (!$user || !(bool)$user['is_admin']) {
        require_once __DIR__ . '/error_page.php';
        render_error_page(403, 'Access denied', 'ACCESS DENIED.', 'Administrator access is required for this area.', 'BACK TO DASHBOARD', route_url('dashboard.php'));
    }
    $role=strtolower(trim((string)($user['admin_role']??'')));
    if(!in_array($role,['owner','admin','support'],true)){
        \App\Support\Logger::error('Invalid admin role denied closed',['user_id'=>(int)$user['id'],'role'=>$role]);
        require_once __DIR__ . '/error_page.php';
        render_error_page(403, 'Access denied', 'ADMIN ROLE INVALID.', 'This administrator account has an invalid role assignment. An owner must repair the account before admin access can continue.', 'BACK TO DASHBOARD', route_url('dashboard.php'));
    }

    if (!admin_2fa_schema_ready()) {
        \App\Support\Logger::error('Admin 2FA schema unavailable; admin access denied closed',['user_id'=>(int)$user['id']]);
        require_once __DIR__ . '/error_page.php';
        render_error_page(503, 'Admin security unavailable', 'SECURITY CHECK UNAVAILABLE.', 'Administrator access is temporarily unavailable because the required 2FA security schema could not be verified.', 'BACK TO DASHBOARD', route_url('dashboard.php'));
    }
    try {
        $state = admin_2fa_state((int)$user['id']);
    } catch (\Throwable $e) {
        \App\Support\Logger::error('Admin 2FA secret unavailable; admin access denied closed', ['user_id'=>(int)$user['id'],'message'=>$e->getMessage()]);
        require_once __DIR__ . '/error_page.php';
        render_error_page(503, 'Admin security unavailable', 'TWO-FACTOR SECURITY UNAVAILABLE.', 'Administrator access is blocked because the protected 2FA secret cannot be read. Restore the matching persistent TOTP key (or its recovery-backup copy) or reset 2FA for this administrator.', 'BACK TO DASHBOARD', route_url('dashboard.php'));
    }
    if ($state['enabled'] && (int)($_SESSION['admin_2fa_verified_for'] ?? 0) !== (int)$user['id']) {
        $_SESSION['pending_admin_2fa_user_id'] = (int)$user['id'];
        $_SESSION['pending_admin_2fa_started_at'] = time();
        unset($_SESSION['user_id'], $_SESSION['admin_2fa_verified_for'], $_SESSION['admin_2fa_verified_at']);
        web_session_request_cache_reset();
        session_regenerate_id(true);
        header('Location: ' . route_url('two-factor.php'));
        exit;
    }
}


function admin_current_role(?array $user = null): string {
    $user ??= current_user();
    if (!$user || empty($user['is_admin'])) return 'none';
    $role = strtolower(trim((string)($user['admin_role'] ?? '')));
    return in_array($role, ['owner','admin','support'], true) ? $role : 'none';
}

function admin_can(string $capability, ?array $user = null): bool {
    $role = admin_current_role($user);
    if ($role === 'owner') return true;
    $map = [
        'admin' => ['overview','users','support','activity','analytics_view','analytics_manage','seo_manage','account_admin','catalog_manage','cms_manage','orders_manage','inventory_manage','marketing_manage','store_settings','email','security','announcements'],
        'support' => ['overview','users','support'],
    ];
    return in_array($capability, $map[$role] ?? [], true);
}

function admin_notification_types_for_role(?array $user=null): ?array {
    $role=admin_current_role($user);
    // Support staff should only receive/view the support queue events their role
    // is authorized to act on. Owner/Admin can view the full admin inbox.
    return $role==='support' ? ['support.ticket_created','support.user_replied'] : null;
}

function require_admin_capability(string $capability): void {
    require_admin();
    if (!admin_can($capability)) {
        require_once __DIR__ . '/error_page.php';
        render_error_page(403, 'Access denied', 'ACCESS DENIED.', 'Your admin role does not allow this action.', 'BACK TO ADMIN', route_url('admin/index.php'));
    }
}

function admin_audit(string $action, string $details, ?int $userId = null): void {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    if ($adminId <= 0) return;
    $targetUserId = ($userId !== null && $userId > 0) ? $userId : $adminId;
    $db = db();
    $strict = $db->inTransaction();
    try {
        ServiceFactory::audit($db)->add($adminId, $targetUserId, $action, $details);
        \App\Support\Logger::info('Admin audit event', [
            'action' => $action,
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
            'details' => $details,
        ]);
    } catch (\Throwable $e) {
        // If the business mutation is still transactional, propagate so the caller
        // can roll everything back atomically. Once a mutation is already committed
        // (or used autocommit), never report a false action failure that invites a
        // duplicate retry; preserve the incident in the protected application log.
        \App\Support\Logger::exception($e, [
            'operation' => 'admin_audit',
            'action' => $action,
            'admin_id' => $adminId,
            'target_user_id' => $targetUserId,
        ]);
        if ($strict) throw $e;
    }
}

function admin_verify_password(string $password): bool {
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    if ($adminId <= 0 || $password === '' || strlen($password) > 256) return false;
    $identity=(string)$adminId;
    $retry=web_rate_limit_retry('admin_reauth',8,900,$identity);
    if($retry>0){header('Retry-After: '.$retry);http_response_code(429);return false;}

    $hash = ServiceFactory::users(db())->activeAdminPasswordHash($adminId);
    if ($hash !== '' && password_verify($password, $hash)) {
        web_rate_limit_clear('admin_reauth', $identity);
        return true;
    }

    $retry=web_rate_limit_failure('admin_reauth',8,900,$identity);
    if($retry>0){header('Retry-After: '.$retry);http_response_code(429);}
    return false;
}

function admin_require_password_from_post(string &$error, string $field = 'admin_password'): bool {
    $password = (string)($_POST[$field] ?? '');
    if ($password === '') $password = (string)($_POST[$field.'_noscript'] ?? '');
    if (!admin_verify_password($password)) {
        $error = http_response_code()===429
            ? 'Too many incorrect admin-password attempts. Try again in a few minutes.'
            : 'Enter your current admin password to confirm this action.';
        return false;
    }
    return true;
}

function admin_password_field(string $label = 'Confirm admin password'): string {
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    // JavaScript-enhanced Admin uses the single shared re-authentication dialog.
    // The noscript field keeps destructive actions usable without JavaScript.
    return '<input type="hidden" name="admin_password" value="" data-admin-reauth-input data-admin-reauth-required="1" data-admin-reauth-label="'.$safeLabel.'">'
        . '<noscript><label class="admin-field admin-reauth-field"><span>'.$safeLabel.'</span><input type="password" name="admin_password_noscript" maxlength="256" autocomplete="current-password" placeholder="Current admin password"></label></noscript>';
}



function admin_preserved_query(array $allowedKeys,array $extra=[]): string {
    $raw=[];
    parse_str((string)($_SERVER['QUERY_STRING']??''),$raw);
    $out=[];
    foreach($allowedKeys as $key){
        if(!is_string($key)||$key===''||!array_key_exists($key,$raw)||is_array($raw[$key]))continue;
        $value=substr(trim((string)$raw[$key]),0,180);
        if($value!=='')$out[$key]=$value;
    }
    foreach($extra as $key=>$value){
        if(!is_string($key)||$key==='')continue;
        if($value===null||$value===''){unset($out[$key]);continue;}
        if(is_scalar($value))$out[$key]=substr((string)$value,0,180);
    }
    return http_build_query($out);
}

function admin_browser_timezone_from_post(): string {
    $timezone = trim((string)($_POST['browser_timezone'] ?? ''));
    if ($timezone !== '') {
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable) {
        }
    }
    return WEB_DISPLAY_TIMEZONE;
}

function admin_parse_utc_datetime(string $value, string $sourceTimezone = 'UTC'): ?string {
    $value = trim($value);
    if ($value === '') return null;
    try {
        $timezone = new DateTimeZone($sourceTimezone);
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        if (!$dt) {
            $dt = new DateTimeImmutable($value, $timezone);
        }
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}
