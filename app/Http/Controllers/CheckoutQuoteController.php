<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class CheckoutQuoteController
{
    public function handle(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){
            http_response_code(405);echo json_encode(['ok'=>false,'error'=>\store_t('Method not allowed.')]);exit;
        }
        try{
            \verify_csrf();
            $retry=max(\store_rate_limit_consume('checkout_quote_session',120,60,session_id()),\store_rate_limit_consume('checkout_quote_ip',360,60));
            if($retry>0){header('Retry-After: '.$retry);http_response_code(429);echo json_encode(['ok'=>false,'error'=>\store_t('Too many checkout updates. Wait a moment and try again.')]);exit;}
            $sessionToken=(string)($_SESSION['_checkout_token']??'');
            $token=(string)($_POST['checkout_token']??'');
            if($sessionToken===''||$token===''||!hash_equals($sessionToken,$token))throw new PublicException('This checkout form expired. Refresh the page and try again.');
            $user=\current_user();
            $store=ServiceFactory::storefront(\db());$settings=$store->schemaReady()?$store->settings():[];
            $checkout=ServiceFactory::checkout(\db());
            $quote=$checkout->quoteCodOrder($_POST,\store_cart_items(),$settings,$user);
            $_SESSION['_checkout_quote']=[
                'fingerprint'=>$checkout->inputFingerprint($_POST),
                'quote'=>[
                    'currency'=>(string)$quote['currency'],'subtotal'=>(float)$quote['subtotal'],'discount_total'=>(float)$quote['discount_total'],
                    'shipping_total'=>(float)$quote['shipping_total'],'grand_total'=>(float)$quote['grand_total'],'coupon_code'=>(string)$quote['coupon_code'],
                    'payment_method'=>(string)$quote['payment_method'],'shipping'=>[
                        'zone_id'=>$quote['shipping']['zone_id']??null,
                        'zone_name'=>(string)($quote['shipping']['zone_name']??''),
                        'standard_fee'=>(float)($quote['shipping']['standard_fee']??0),
                        'free_shipping_applied'=>!empty($quote['shipping']['free_shipping_applied']),
                        'days_min'=>$quote['shipping']['days_min']??null,
                        'days_max'=>$quote['shipping']['days_max']??null,
                    ],'items'=>array_map(static fn(array $item):array=>[
                        'variant_id'=>(int)$item['variant_id'],'quantity'=>(int)$item['quantity'],'unit_price'=>(float)$item['unit_price'],
                    ],$quote['items']),
                ],
            ];
            echo json_encode(['ok'=>true,'quote'=>[
                'currency'=>(string)$quote['currency'],'subtotal'=>(float)$quote['subtotal'],'discount_total'=>(float)$quote['discount_total'],
                'shipping_total'=>(float)$quote['shipping_total'],'grand_total'=>(float)$quote['grand_total'],'coupon_code'=>(string)$quote['coupon_code'],
                'payment_method'=>(string)$quote['payment_method'],'shipping'=>[
                    'zone_name'=>(string)($quote['shipping']['zone_name']??''),'standard_fee'=>(float)($quote['shipping']['standard_fee']??0),
                    'free_shipping_applied'=>!empty($quote['shipping']['free_shipping_applied']),'days_min'=>$quote['shipping']['days_min']??null,'days_max'=>$quote['shipping']['days_max']??null,
                ],
            ]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
        }catch(PublicException $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>\store_public_message_t($e->getMessage())]);exit;}
        catch(\Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>\store_public_message_t(\web_safe_exception_message($e,'checkout_quote_api','Order total could not be calculated.'))]);exit;}
    }
}
