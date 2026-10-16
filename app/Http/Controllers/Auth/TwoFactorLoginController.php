<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\ServiceFactory;

final class TwoFactorLoginController
{
    public function handle(): array
    {
        if (!\admin_2fa_schema_ready()) {
            header('Location: ' . \route_url('login.php'));
            exit;
        }

        $pendingId = (int)($_SESSION['pending_admin_2fa_user_id'] ?? 0);
        $startedAt = (int)($_SESSION['pending_admin_2fa_started_at'] ?? 0);
        $username = (string)($_SESSION['pending_admin_2fa_username'] ?? '');
        if ($pendingId <= 0 || $startedAt <= 0 || (time() - $startedAt) > 300) {
            unset($_SESSION['pending_admin_2fa_user_id'], $_SESSION['pending_admin_2fa_started_at'], $_SESSION['pending_admin_2fa_username']);
            header('Location: ' . \route_url('login.php'));
            exit;
        }

        $pendingUser = ServiceFactory::users(\db())->findAdminIdentity($pendingId);
        if (!$pendingUser || !(bool)$pendingUser['is_admin'] || !(bool)$pendingUser['is_active']) {
            \destroy_web_session();
            header('Location: ' . \route_url('login.php'));
            exit;
        }
        $username = (string)$pendingUser['username'];

        $error = '';
        try {
            $state = \admin_2fa_state($pendingId);
            if (empty($state['enabled'])) {
                \destroy_web_session();
                header('Location: ' . \route_url('login.php'));
                exit;
            }
        } catch (\Throwable $e) {
            \App\Support\Logger::error('Admin 2FA secret unavailable on verification screen', ['admin_id'=>$pendingId,'message'=>$e->getMessage()]);
            http_response_code(503);
            $error='Two-factor authentication is temporarily unavailable because its protected secret cannot be read. Restore the matching private TOTP key or ask the site owner to reset 2FA. Reference: '.\App\Support\Logger::requestId();
            return compact('username','error');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $code = trim((string)($_POST['code'] ?? ''));
            $retry=\web_rate_limit_retry('admin_2fa_login',8,900,(string)$pendingId);
            if($retry>0){
                header('Retry-After: '.$retry);http_response_code(429);
                $error='Too many incorrect two-factor attempts. Try again in a few minutes.';
                return compact('username','error');
            }
            try {
                $result = \admin_2fa_verify_or_consume($pendingId, $code);
            } catch (\Throwable $e) {
                \App\Support\Logger::error('Admin 2FA verification secret unavailable', ['admin_id'=>$pendingId,'message'=>$e->getMessage()]);
                http_response_code(503);
                $error='Two-factor authentication is temporarily unavailable because its protected secret cannot be read. Restore the matching private TOTP key or ask the site owner to reset 2FA. Reference: '.\App\Support\Logger::requestId();
                return compact('username','error');
            }
            if (!$result['ok']) {
                $retry=\web_rate_limit_failure('admin_2fa_login',8,900,(string)$pendingId);
                if($retry>0){header('Retry-After: '.$retry);http_response_code(429);}
                $error = 'Invalid authenticator or recovery code.';
                \App\Support\Logger::warning('Admin 2FA verification failed', ['admin_id' => $pendingId]);
                try { ServiceFactory::loginSecurity(\db())->record($pendingId,$username,false,'2fa',\client_ip(),\web_user_agent(),'Invalid two-factor code'); } catch (\Throwable $e) {}
            } else {
                \web_rate_limit_clear('admin_2fa_login', (string)$pendingId);
                session_regenerate_id(true);
                // 2FA completion is a privilege boundary. Rotate the CSRF secret with
                // the authenticated admin session just as password login does.
                unset($_SESSION['csrf_token']);
                \csrf_token();
                $_SESSION['user_id'] = $pendingId;
                $_SESSION['_web_last_activity'] = time();
                $_SESSION['admin_2fa_verified_for'] = $pendingId;
                $_SESSION['admin_2fa_verified_at'] = time();
                unset($_SESSION['pending_admin_2fa_user_id'], $_SESSION['pending_admin_2fa_started_at'], $_SESSION['pending_admin_2fa_username']);
                ServiceFactory::users(\db())->touchLastLogin($pendingId);
                \web_register_current_session($pendingId);
                try { ServiceFactory::accountActivity(\db())->add($pendingId,'web_login','Website admin login','2FA verified',\client_ip()); } catch (\Throwable $e) {}
                try { ServiceFactory::loginSecurity(\db())->record($pendingId,$username,true,'2fa',\client_ip(),\web_user_agent(),$result['recovery']?'Recovery code sign-in':'Authenticator sign-in'); } catch (\Throwable $e) {}
                \visitor_analytics_event('login_completed',$pendingId,['stage'=>$result['recovery']?'recovery':'2fa']);
                try {
                    ServiceFactory::audit(\db())->add(
                        $pendingId,
                        $pendingId,
                        $result['recovery'] ? 'admin_2fa_recovery_login' : 'admin_2fa_login',
                        $result['recovery'] ? 'Admin signed in using a recovery code' : 'Admin completed TOTP sign-in'
                    );
                } catch (\Throwable $e) {
                    \App\Support\Logger::exception($e, ['operation' => 'admin_2fa_audit', 'admin_id' => $pendingId]);
                }
                header('Location: ' . \route_url('admin/index.php'));
                exit;
            }
        }
        return compact('username','error');
    }
}
