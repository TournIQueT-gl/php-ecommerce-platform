<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class CheckoutController
{
    public function handle(): array
    {
        $message='';$error='';$settings=[];$user=null;$items=[];$subtotal=0.0;$quote=null;$shippingZones=[];$governorates=\store_egypt_governorates();
        try{$user=\current_user();}catch(\Throwable){}
        try{
            $store=ServiceFactory::storefront(\db());if($store->schemaReady())$settings=$store->settings();
            if(!\store_catalog_enabled($settings))throw new PublicException('Checkout is temporarily unavailable.');
            $codEnabled=(string)($settings['cod_enabled']??'1')==='1';$manualTransferEnabled=(string)($settings['manual_transfer_enabled']??'1')==='1';
            if(!$codEnabled&&!$manualTransferEnabled)throw new PublicException('Checkout payment methods are temporarily unavailable.');
            $commerce=ServiceFactory::commerceRepository(\db());
            if(!$commerce->checkoutSchemaReady())throw new PublicException('Checkout is not available until the commerce database migration is applied.');
            $items=$commerce->cartSnapshot(\store_cart_items());
            $shippingZones=$commerce->shippingZones();
            foreach($items as $row)$subtotal+=(float)$row['unit_price']*(int)$row['quantity'];
        }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'checkout_load','Checkout is temporarily unavailable.');}

        if(empty($_SESSION['_checkout_token']))$_SESSION['_checkout_token']=bin2hex(random_bytes(24));
        if(empty($_SESSION['_checkout_idempotency_key']))$_SESSION['_checkout_idempotency_key']=bin2hex(random_bytes(24));
        $checkoutToken=(string)$_SESSION['_checkout_token'];$idempotencyKey=(string)$_SESSION['_checkout_idempotency_key'];
        $checkout=ServiceFactory::checkout(\db());

        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();
            $postedIdempotency=trim((string)($_POST['order_idempotency_key']??''));
            $completed=is_array($_SESSION['_checkout_completed_keys']??null)?$_SESSION['_checkout_completed_keys']:[];
            if($postedIdempotency!==''&&isset($completed[$postedIdempotency])&&is_array($completed[$postedIdempotency])){$_SESSION['_last_order']=$completed[$postedIdempotency];header('Location: '.\route_url('order-confirmation.php'),true,303);exit;}
            $retry=max(\store_rate_limit_consume('checkout_place_session',6,900,session_id()),\store_rate_limit_consume('checkout_place_ip',30,900));
            if($retry>0){header('Retry-After: '.$retry);http_response_code(429);$error='Too many order attempts. Wait a few minutes and try again.';}
            $token=(string)($_POST['checkout_token']??'');
            if($error!==''){}elseif($checkoutToken===''||$token===''||!hash_equals($checkoutToken,$token)){
                $error='This checkout form expired. Refresh the page and try again.';
            }elseif($postedIdempotency===''||$idempotencyKey===''||!hash_equals($idempotencyKey,$postedIdempotency)){
                $error='This order form expired. Refresh the page and try again.';
            }else{
                try{
                    $savedQuote=is_array($_SESSION['_checkout_quote']??null)?$_SESSION['_checkout_quote']:null;
                    $expectedQuote=null;
                    if($savedQuote!==null&&hash_equals((string)($savedQuote['fingerprint']??''),$checkout->inputFingerprint($_POST))&&is_array($savedQuote['quote']??null)){
                        $expectedQuote=$savedQuote['quote'];
                    }
                    if($expectedQuote===null)throw new PublicException('Your total is still updating. Review the displayed total, then place the order again.');
                    $result=$checkout->placeCodOrder($_POST,\store_cart_items(),$settings,$user,$expectedQuote,$postedIdempotency);
                    unset($_SESSION['_checkout_address_notice']);
                    if($user && !empty($_POST['save_delivery_address']) && (int)($_POST['saved_address_id']??0)===0){
                        try{
                            (new \App\Services\CustomerAddressService(\db()))->save((int)$user['id'],[
                                'label'=>(string)($_POST['delivery_address_label']??''),
                                'recipient_name'=>(string)($_POST['customer_name']??''),
                                'phone'=>(string)($_POST['phone']??''),
                                'governorate'=>(string)($_POST['governorate']??''),
                                'city'=>(string)($_POST['city']??''),
                                'address_line1'=>(string)($_POST['address']??''),
                                'building'=>(string)($_POST['building']??''),
                            ]);
                            $_SESSION['_checkout_address_notice']='Delivery address saved to your account.';
                        }catch(\Throwable $e){
                            \App\Support\Logger::warning('Checkout address book save failed',['user_id'=>(int)$user['id'],'error'=>$e->getMessage()]);
                            $_SESSION['_checkout_address_notice']='Your order was placed, but your address could not be saved. You can add it from your account.';
                        }
                    }
                    $_SESSION['_store_cart']=[];unset($_SESSION['_checkout_token'],$_SESSION['_checkout_quote'],$_SESSION['_checkout_idempotency_key']);$_SESSION['_last_order']=$result;
                    $completed=is_array($_SESSION['_checkout_completed_keys']??null)?$_SESSION['_checkout_completed_keys']:[];$completed[$postedIdempotency]=$result;$_SESSION['_checkout_completed_keys']=array_slice($completed,-5,null,true);
                    if(empty($result['user_id'])&&!empty($result['guest_access_token'])){
                        $claims=is_array($_SESSION['_guest_order_claims']??null)?$_SESSION['_guest_order_claims']:[];
                        $claims[]=['order_number'=>(string)$result['order_number'],'token'=>(string)$result['guest_access_token'],'email'=>strtolower(trim((string)($result['email']??''))),'created_at'=>time()];
                        $_SESSION['_guest_order_claims']=array_slice($claims,-8);
                    }
                    if(function_exists('visitor_analytics_event'))\visitor_analytics_event('checkout_completed',$user?(int)$user['id']:null,['order_number'=>$result['order_number'],'order_id'=>$result['order_id']]);
                    header('Location: '.\route_url('order-confirmation.php'),true,303);exit;
                }catch(PublicException $e){$error=$e->getMessage();}
                catch(\Throwable $e){$error=\web_safe_exception_message($e,'checkout_place_order','Your order could not be placed.');}
            }
        }elseif($items&&function_exists('visitor_analytics_event')){
            \visitor_analytics_event('checkout_started',$user?(int)$user['id']:null,['items'=>count($items)]);
        }

        $savedAddresses=[];
        if($user){
            try{$savedAddresses=(new \App\Services\CustomerAddressService(\db()))->all((int)$user['id']);}
            catch(\Throwable $e){\App\Support\Logger::warning('Saved delivery addresses unavailable',['message'=>$e->getMessage()]);}
        }
        return ['savedAddresses'=>$savedAddresses,'settings'=>$settings,'user'=>$user,'items'=>$items,'subtotal'=>$subtotal,'message'=>$message,'error'=>$error,'checkoutToken'=>$checkoutToken,'idempotencyKey'=>$idempotencyKey,'quote'=>$quote,'shippingZones'=>$shippingZones,'governorates'=>$governorates];
    }
}
