<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CustomerAddressService;
use App\Support\PublicException;

final class AccountAddressesController
{
    public function handle(): array
    {
        $user=\current_user();if(!$user){header('Location: '.\route_url('login.php'));exit;}
        $uid=(int)$user['id'];$service=new CustomerAddressService(\db());
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $message='';$error='';
            try{
                switch((string)($_POST['action']??'')){
                    case 'save':$service->save($uid,$_POST);$message='Delivery address saved.';break;
                    case 'delete':$service->remove($uid,(int)($_POST['id']??0));$message='Address removed.';break;
                    case 'make_default':$service->setDefault($uid,(int)($_POST['id']??0));$message='Default address updated.';break;
                    default:throw new PublicException('Unknown address action.');
                }
            }catch(PublicException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error=\web_safe_exception_message($e,'address_save','Could not update your saved addresses.');}
            \web_post_redirect('account-addresses.php','account_addresses',$message,$error);
        }
        $flash=\web_flash_take('account_addresses');$addresses=[];$error=$flash['error'];
        try{$addresses=$service->all($uid);}catch(\Throwable $e){$error=\web_safe_exception_message($e,'address_load','Saved addresses are temporarily unavailable.');}
        return ['user'=>$user,'addresses'=>$addresses,'message'=>$flash['message'],'error'=>$error,'governorates'=>\store_egypt_governorates()];
    }
}
