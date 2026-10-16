<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\ServiceFactory;

final class ForgotPasswordController
{
    private const FLASH_KEY='password_recovery';

    public function handle(): array
    {
        if(!empty($_SESSION['user_id'])){
            header('Location: '.\route_url('account-security.php'));
            exit;
        }
        $service=ServiceFactory::passwordRecovery(\db());
        $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
        $incomingToken=trim((string)($_GET['token']??''));
        if($method==='GET'&&preg_match('/^[a-f0-9]{64}$/i',$incomingToken)){
            $_SESSION['_password_recovery_token']=$incomingToken;
            header('Location: '.\route_url('forgot-password.php').'?reset=1',true,302);
            exit;
        }
        $token=trim((string)($_POST['token']??($_SESSION['_password_recovery_token']??$incomingToken)));
        $sent=!empty($_GET['sent']);
        $error='';

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $action=(string)($_POST['action']??'request');
            if($action==='request'){
                $username=trim((string)($_POST['username']??''));
                $retry=max(
                    \web_rate_limit_consume('password_recovery_user',5,3600,strtolower($username)),
                    \web_rate_limit_consume('password_recovery_ip',20,3600)
                );
                if($retry>0){header('Retry-After: '.$retry);http_response_code(429);$error='Too many recovery requests. Try again later.';}
                else try{
                    $service->request($username,\client_ip(),\web_user_agent());
                    // Always return the same public response. Do not reveal whether
                    // the identity exists, has verified email, or requires admin help.
                    header('Location: '.\route_url('forgot-password.php').'?sent=1',true,303);
                    exit;
                }catch(\Throwable $e){
                    $error=\web_safe_exception_message($e,'password_recovery_request','Recovery request could not be created right now.');
                }
            }elseif($action==='reset'){
                $retry=\web_rate_limit_consume('password_recovery_reset',10,1800,$token);
                if($retry>0){header('Retry-After: '.$retry);http_response_code(429);$error='Too many reset attempts. Try again later.';}
                else try{
                    $result=$service->reset($token,(string)($_POST['new_password']??''),(string)($_POST['confirm_password']??''),\client_ip(),\web_user_agent());
                    if(!empty($result['ok'])){
                        unset($_SESSION['_password_recovery_token']);
                        header('Location: '.\route_url('login.php').'?reset=success',true,303);
                        exit;
                    }
                    $error=(string)$result['message'];
                }catch(\Throwable $e){
                    $error=\web_safe_exception_message($e,'password_recovery_reset','Password reset could not be completed.');
                }
            }else{
                $error='Unknown recovery action.';
            }
        }

        $request=null;
        if($token!==''){
            try{$request=$service->inspect($token);}catch(\Throwable $e){$request=null;}
            if($request===null){unset($_SESSION['_password_recovery_token']);if($error==='')$error='This recovery link is invalid or no longer available.';$token='';}
        }
        return compact('token','request','error','sent');
    }
}
