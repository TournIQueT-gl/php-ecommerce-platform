<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\ServiceFactory;

final class GuestOrderTrackingController
{
    private const SESSION_TTL=3600;
    private const SESSION_LIMIT=8;

    public function handle(): array
    {
        // A tracking token is a bearer secret. Never let it become a same-origin
        // referrer and never cache a page that contains private order details.
        header('Referrer-Policy: no-referrer');
        if(function_exists('web_private_response_headers'))\web_private_response_headers();

        $orderNumber=substr(trim((string)($_GET['order']??'')),0,40);
        $incomingToken=substr(trim((string)($_GET['token']??'')),0,128);
        $order=null;$items=[];$history=[];$error='';

        $access=is_array($_SESSION['_guest_tracking_access']??null)?$_SESSION['_guest_tracking_access']:[];
        $now=time();
        foreach($access as $number=>$entry){
            if(!is_array($entry)||(int)($entry['expires_at']??0)<=$now)unset($access[$number]);
        }
        $_SESSION['_guest_tracking_access']=$access;

        try{
            $db=\db();$repo=ServiceFactory::commerceRepository($db);
            if(!$repo->checkoutSchemaReady())throw new \RuntimeException('The latest commerce database update has not been applied.');

            if($orderNumber===''||($incomingToken===''&&!isset($access[$orderNumber]))){
                $error='Use the private tracking link from your order confirmation or order email.';
            }else{
                $token=$incomingToken!==''?$incomingToken:(string)($access[$orderNumber]['token']??'');
                $order=$repo->guestOrderByAccess($orderNumber,$token);
                if(!$order){
                    unset($_SESSION['_guest_tracking_access'][$orderNumber]);
                    $error='This order tracking link is invalid or no longer available.';
                }else{
                    if($incomingToken!==''){
                        $_SESSION['_guest_tracking_access'][$orderNumber]=['token'=>$incomingToken,'expires_at'=>$now+self::SESSION_TTL];
                        if(count($_SESSION['_guest_tracking_access'])>self::SESSION_LIMIT){
                            $_SESSION['_guest_tracking_access']=array_slice($_SESSION['_guest_tracking_access'],-self::SESSION_LIMIT,null,true);
                        }
                        // Consume the bearer token once, then keep it out of the address
                        // bar/referrer chain for the rest of this browser session.
                        header('Location: '.\route_url('track-order.php').'?order='.rawurlencode($orderNumber),true,302);
                        exit;
                    }
                    $q=$db->prepare('SELECT product_name,brand_name,variant_label,image_path,quantity,unit_price,line_total FROM store_order_items WHERE order_id=? ORDER BY id');$q->execute([(int)$order['id']]);$items=$q->fetchAll();
                    $h=$db->prepare('SELECT status,payment_status,fulfillment_status,note,created_at FROM store_order_status_history WHERE order_id=? ORDER BY created_at,id');$h->execute([(int)$order['id']]);$history=$h->fetchAll();
                }
            }
        }catch(\Throwable $e){\App\Support\Logger::warning('Guest order tracking unavailable',['order'=>$orderNumber,'message'=>$e->getMessage()]);$error='Order tracking is temporarily unavailable.';}
        return compact('orderNumber','order','items','history','error');
    }
}
