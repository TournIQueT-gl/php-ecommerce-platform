<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\ServiceFactory;

final class LoginController
{
    public function handle(): array
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . \route_url('dashboard.php'));
            exit;
        }

        $error = '';
        $username = '';
        $notice='';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $loginIdentity=strtolower($username);
            $retry=max(
                \web_rate_limit_retry('web_login_user_fail',8,900,$loginIdentity),
                \web_rate_limit_retry('web_login_ip_fail',60,900)
            );
            if($retry>0){
                header('Retry-After: '.$retry);
                http_response_code(429);
                $error='Too many failed sign-in attempts. Try again in a few minutes.';
                return compact('error','username','notice');
            }

            $result = ServiceFactory::auth(\db())->attempt($username, $password);
            if (!$result['ok']) {
                $retry=max(
                    \web_rate_limit_failure('web_login_user_fail',8,900,$loginIdentity),
                    \web_rate_limit_failure('web_login_ip_fail',60,900)
                );
                if($retry>0){header('Retry-After: '.$retry);http_response_code(429);}
                $error = (string)$result['error'];
                \App\Support\Logger::warning('Website login failed', ['username' => $username]);
                try {
                    $knownId=ServiceFactory::users(\db())->idByUsername($username);
                    ServiceFactory::loginSecurity(\db())->record($knownId,$username,false,'password',\client_ip(),\web_user_agent(),'Invalid credentials');
                } catch (\Throwable $e) {}
            } else {
                $user = $result['user'];
                session_regenerate_id(true);
                // Rotate the CSRF token together with the authenticated session ID.
                unset($_SESSION['csrf_token']);
                \csrf_token();
                \web_rate_limit_clear('web_login_user_fail', $loginIdentity);

                $userId = (int)$user['id'];
                $needsAdmin2fa = false;
                if (!empty($user['is_admin'])) {
                    if (!\admin_2fa_schema_ready()) {
                        \App\Support\Logger::error('Admin 2FA schema unavailable during login', ['user_id'=>$userId]);
                        http_response_code(503);
                        $error='Administrator sign-in is temporarily unavailable because two-factor security could not be verified. Apply the required database updates before signing in. Reference: '.\App\Support\Logger::requestId();
                        return compact('error','username','notice');
                    }
                    try {
                        $state = \admin_2fa_state($userId);
                        $needsAdmin2fa = (bool)$state['enabled'];
                    } catch (\Throwable $e) {
                        \App\Support\Logger::error('Admin 2FA secret unavailable during login', ['user_id'=>$userId,'message'=>$e->getMessage()]);
                        http_response_code(503);
                        $error='Administrator sign-in is temporarily unavailable because two-factor security could not be read. Ask the site owner to reset 2FA if needed. Reference: '.\App\Support\Logger::requestId();
                        return compact('error','username','notice');
                    }
                }

                if ($needsAdmin2fa) {
                    unset($_SESSION['user_id'], $_SESSION['admin_2fa_verified_for'], $_SESSION['admin_2fa_verified_at']);
                    $_SESSION['pending_admin_2fa_user_id'] = $userId;
                    $_SESSION['pending_admin_2fa_started_at'] = time();
                    $_SESSION['pending_admin_2fa_username'] = $username;
                    header('Location: ' . \route_url('two-factor.php'));
                    exit;
                }

                $_SESSION['user_id'] = $userId;
                $_SESSION['_web_last_activity'] = time();
                unset($_SESSION['pending_admin_2fa_user_id'], $_SESSION['pending_admin_2fa_started_at'], $_SESSION['pending_admin_2fa_username']);
                ServiceFactory::users(\db())->touchLastLogin($userId);
                \web_register_current_session($userId);
                try {
                    $commerce=ServiceFactory::commerceRepository(\db());
                    $identity=ServiceFactory::emailRepository(\db())->userEmail($userId);
                    $accountEmail=strtolower(trim((string)($identity['email']??'')));
                    $claims=is_array($_SESSION['_guest_order_claims']??null)?$_SESSION['_guest_order_claims']:[];$remaining=[];$claimed=0;$claimCutoff=time()-3600;
                    foreach($claims as $claim){
                        if(!is_array($claim))continue;
                        $createdAt=(int)($claim['created_at']??0);if($createdAt<=0||$createdAt<$claimCutoff)continue;
                        $claimEmail=strtolower(trim((string)($claim['email']??'')));
                        if($claimEmail!==''&&($accountEmail===''||!hash_equals($claimEmail,$accountEmail))){$remaining[]=$claim;continue;}
                        if($commerce->claimGuestOrderByAccess((string)($claim['order_number']??''),(string)($claim['token']??''),$userId,$claimEmail!==''?$accountEmail:null)){$claimed++;continue;}
                        $remaining[]=$claim;
                    }
                    $_SESSION['_guest_order_claims']=$remaining;
                    if(!empty($identity['email_verified_at'])&&$accountEmail!=='')$claimed+=$commerce->claimGuestOrdersByVerifiedEmail($userId,$accountEmail);
                    if($claimed>0)$_SESSION['_flash_guest_orders_claimed']=$claimed;
                } catch (\Throwable $e) { \App\Support\Logger::warning('Guest order claim after login failed',['user_id'=>$userId,'message'=>$e->getMessage()]); }
                try { ServiceFactory::accountActivity(\db())->add($userId,'web_login','Website login','Successful customer sign-in',\client_ip()); } catch (\Throwable $e) {}
                try { ServiceFactory::loginSecurity(\db())->record($userId,$username,true,'password',\client_ip(),\web_user_agent(),'Website sign-in'); } catch (\Throwable $e) {}
                \visitor_analytics_event('login_completed',$userId,['stage'=>'password']);
                header('Location: ' . \route_url('dashboard.php'));
                exit;
            }
        }

        if((string)($_GET['session']??'')==='revoked')$notice='That browser session was closed. Sign in again if you still need access.';
        if((string)($_GET['reset']??'')==='success')$notice='Password reset complete. Sign in with your new password.';
        if((string)($_GET['password']??'')==='changed')$notice='Password changed. All previous browser sessions were closed. Sign in with your new password.';
        return compact('error','username','notice');
    }
}
