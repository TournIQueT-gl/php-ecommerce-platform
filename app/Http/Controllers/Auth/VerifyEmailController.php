<?php
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Support\ServiceFactory;

final class VerifyEmailController
{
    private const RESULT_FLASH='_verify_email_result';

    public function handle(): array
    {
        $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
        if($method==='GET'&&!empty($_GET['done'])){
            $flash=$_SESSION[self::RESULT_FLASH]??null;
            unset($_SESSION[self::RESULT_FLASH]);
            if(is_array($flash)&&array_key_exists('ok',$flash)){
                return ['result'=>$flash,'token'=>'','loggedIn'=>!empty($_SESSION['user_id'])];
            }
        }
        $incomingToken=trim((string)($_GET['token']??''));
        if($method==='GET'&&preg_match('/^[a-f0-9]{64}$/i',$incomingToken)){
            $_SESSION['_verify_email_token']=$incomingToken;
            header('Location: '.\route_url('verify-email.php').'?confirm=1',true,302);
            exit;
        }
        $token=trim((string)($_POST['token']??($_SESSION['_verify_email_token']??$incomingToken)));
        $result=['ok'=>null,'message'=>'Confirm below to verify this email address.'];

        if($method==='POST'){
            \verify_csrf();
            if($token==='')$result=['ok'=>false,'message'=>'This verification link is invalid.'];
            else{
                try{
                    $result=ServiceFactory::email(\db())->verifyEmail($token);
                    if(!empty($result['ok'])&&!empty($result['user_id'])&&!empty($result['email'])){
                        unset($_SESSION['_verify_email_token']);
                        $claimed=ServiceFactory::commerceRepository(\db())->claimGuestOrdersByVerifiedEmail((int)$result['user_id'],(string)$result['email']);
                        if($claimed>0)$result['message'].=' '.($claimed===1?'One guest order was':'Several guest orders were').' added to your account.';
                        $_SESSION[self::RESULT_FLASH]=$result;
                        header('Location: '.\route_url('verify-email.php').'?done=1',true,303);
                        exit;
                    }elseif(($result['ok']??null)===false){unset($_SESSION['_verify_email_token']);}
                }catch(\Throwable $e){$result=['ok'=>false,'message'=>\web_safe_exception_message($e,'verify_email','Email verification could not be completed.')];}
            }
        }elseif($token==='' || !preg_match('/^[a-f0-9]{64}$/i',$token)){
            $result=['ok'=>false,'message'=>'This verification link is invalid.'];
        }

        return ['result'=>$result,'token'=>$token,'loggedIn'=>!empty($_SESSION['user_id'])];
    }
}
