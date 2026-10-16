<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\ServiceFactory;

final class RegisterController
{
    public function handle(): array
    {
        if (!empty($_SESSION['user_id'])) {
            header('Location: ' . \route_url('dashboard.php'));
            exit;
        }

        $error = '';
        $username = '';
        $email = strtolower(trim((string)($_SESSION['_register_email_prefill'] ?? '')));
        if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$email='';
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            \verify_csrf();
            $retry=\web_rate_limit_consume('web_register_ip',10,3600);
            if($retry>0){
                header('Retry-After: '.$retry);http_response_code(429);
                $error='Too many registration attempts from this connection. Try again later.';
                return compact('error','username','email');
            }
            $username = trim((string)($_POST['username'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $result = ServiceFactory::auth(\db())->register($username, $password, $confirm, $email !== '' ? $email : null);
            if (!$result['ok']) {
                $error = (string)$result['error'];
            } else {
                session_regenerate_id(true);
                // Registration crosses the same anonymous -> authenticated boundary as
                // login. Rotate the CSRF secret too so pre-auth form tokens cannot survive it.
                unset($_SESSION['csrf_token']);
                \csrf_token();
                unset($_SESSION['_register_email_prefill']);
                $_SESSION['user_id'] = (int)$result['user_id'];
                $_SESSION['_web_last_activity'] = time();
                \web_register_current_session((int)$result['user_id']);
                try{
                    $claims=is_array($_SESSION['_guest_order_claims']??null)?$_SESSION['_guest_order_claims']:[];$remaining=[];$claimed=0;$claimCutoff=time()-3600;
                    $commerce=ServiceFactory::commerceRepository(\db());$accountEmail=strtolower(trim((string)$email));
                    foreach($claims as $claim){
                        if(!is_array($claim)){continue;}
                        $createdAt=(int)($claim['created_at']??0);if($createdAt<=0||$createdAt<$claimCutoff)continue;
                        $claimEmail=strtolower(trim((string)($claim['email']??'')));
                        if($claimEmail!==''&&($accountEmail===''||!hash_equals($claimEmail,$accountEmail))){$remaining[]=$claim;continue;}
                        if($commerce->claimGuestOrderByAccess((string)($claim['order_number']??''),(string)($claim['token']??''),(int)$result['user_id'],$claimEmail!==''?$accountEmail:null)){$claimed++;continue;}
                        $remaining[]=$claim;
                    }
                    $_SESSION['_guest_order_claims']=$remaining;
                    if($claimed>0)$_SESSION['_flash_guest_orders_claimed']=$claimed;
                }catch(\Throwable $e){\App\Support\Logger::warning('Guest order claim after registration failed',['user_id'=>(int)$result['user_id'],'message'=>$e->getMessage()]);}
                if ($email !== '') {
                    try { ServiceFactory::email(\db())->requestVerification((int)$result['user_id'], $email); } catch (\Throwable $e) { \App\Support\Logger::warning('Registration email verification could not be queued',['user_id'=>(int)$result['user_id'],'message'=>$e->getMessage()]); }
                }
                try { ServiceFactory::loginSecurity(\db())->record((int)$result['user_id'],$username,true,'registration',\client_ip(),\web_user_agent(),'Customer account created'); } catch (\Throwable $e) {}
                \visitor_analytics_event('registration_completed',(int)$result['user_id']);
                header('Location: ' . \route_url('dashboard.php'));
                exit;
            }
        }
        return compact('error','username','email');
    }
}
