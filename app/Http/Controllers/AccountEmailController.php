<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ServiceFactory;

final class AccountEmailController
{
    private const FLASH_KEY='account_email';

    public function handle(): array
    {
        $user=\current_user();
        if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $userId=(int)$user['id'];
        $service=ServiceFactory::email(\db());

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $action=(string)($_POST['action']??'');$message='';$error='';
            try{
                if($action==='change_email'){
                    $result=$service->changeUserEmail($userId,(string)($_POST['email']??''),(string)($_POST['current_password']??''));
                    if(!empty($result['ok']))$message=(string)$result['message'];else$error=(string)$result['message'];
                }elseif($action==='resend_verification'){
                    $retry=\web_rate_limit_consume('email_verify_resend',5,3600,(string)$userId);
                    if($retry>0)$error='Too many verification-email requests. Try again later.';
                    else{
                        $profile=$service->profile($userId);
                        $pending=(string)($profile['identity']['pending_email']??'');
                        $email=$pending!==''?$pending:(string)($profile['identity']['email']??'');
                        if($email==='')$error='Add an email address first.';
                        elseif($pending===''&&!empty($profile['identity']['email_verified_at']))$message='Your email address is already verified.';
                        elseif($service->requestVerification($userId,$email))$message='Verification email queued.';
                        else $error='Verification email could not be queued. Email delivery may not be configured yet.';
                    }
                }elseif($action==='preferences'){
                    $result=$service->updatePreferences($userId,$_POST);
                    if(!empty($result['ok']))$message=(string)$result['message'];else$error=(string)$result['message'];
                }else{$error='Unknown email action.';}
            }catch(\Throwable $e){$error=\web_safe_exception_message($e,'account_email_action','Email settings could not be updated.');}
            \web_post_redirect('account-email.php',self::FLASH_KEY,$message,$error);
        }

        $flash=\web_flash_take(self::FLASH_KEY);
        $profile=['identity'=>['email'=>null,'email_verified_at'=>null],'preferences'=>[],'delivery_enabled'=>false];
        $schemaReady=false;
        try{$schemaReady=$service->schemaReady();if($schemaReady)$profile=$service->profile($userId);}catch(\Throwable $e){\App\Support\Logger::warning('Account email profile unavailable',['user_id'=>$userId,'message'=>$e->getMessage()]);}
        return ['user'=>$user,'message'=>$flash['message'],'error'=>$flash['error'],'schemaReady'=>$schemaReady,'profile'=>$profile];
    }
}
