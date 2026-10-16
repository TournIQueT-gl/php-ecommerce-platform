<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class CartController
{
    public function handle(): array
    {
        $message='';$error='';
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');
            try{
                if($action==='add'){$this->add();$message='Added to your bag.';}
                elseif($action==='update' && !empty($_POST['remove_key'])){$_POST['key']=(string)$_POST['remove_key'];$this->remove();$message='Item removed.';}
                elseif($action==='update'){$this->update();$message='Bag updated.';}
                elseif($action==='remove'){$this->remove();$message='Item removed.';}
                elseif($action==='clear'){$_SESSION['_store_cart']=[];$message='Bag cleared.';}
                else throw new PublicException('Unknown bag action.');
            }catch(PublicException $e){$error=$e->getMessage();}
            catch(\Throwable $e){$error=\web_safe_exception_message($e,'store_cart','Your bag could not be updated.');}
            $ajax=(string)($_GET['_ajax']??'')==='cart' || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''),'PerfumeStore-Cart')===0 || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
            if($ajax){
                if(ob_get_level()>0)@ob_clean();
                header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
                http_response_code($error===''?200:422);
                $stored=\store_cart_items();$currencies=array_values(array_unique(array_filter(array_map(static fn($item)=>strtoupper((string)($item['currency']??'')),$stored))));
                $currency=count($currencies)===1?$currencies[0]:'EGP';$lines=[];
                foreach($stored as $key=>$item){$lines[(string)$key]=['quantity'=>(int)($item['quantity']??0),'total'=>\store_money((float)($item['unit_price']??0)*(int)($item['quantity']??0),(string)($item['currency']??$currency))];}
                echo json_encode(['ok'=>$error==='','message'=>$error!==''?$error:$message,'cart_count'=>\store_cart_count(),'subtotal'=>\store_cart_total(),'subtotal_display'=>\store_money(\store_cart_total(),$currency),'lines'=>$lines],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
            }
            $_SESSION['_cart_flash']=['message'=>$message,'error'=>$error];header('Location: '.\route_url('cart.php'),true,303);exit;
        }
        $flash=$_SESSION['_cart_flash']??['message'=>'','error'=>''];unset($_SESSION['_cart_flash']);$error=(string)($flash['error']??'');
        $settings=[];try{$repo=ServiceFactory::storefront(\db());if($repo->schemaReady())$settings=$repo->settings();}catch(\Throwable){}
        $user=null;try{$user=\current_user();}catch(\Throwable){}
        $items=\store_cart_items();
        try{$commerce=ServiceFactory::commerceRepository(\db());if($commerce->cartSchemaReady()&&$items){$before=count($items);$fresh=$commerce->cartSnapshot($items);$_SESSION['_store_cart']=$fresh;$items=$fresh;if(count($fresh)<$before&&$error==='')$error='One or more unavailable items were removed from your bag.';}}catch(\Throwable){}
        return ['items'=>$items,'subtotal'=>\store_cart_total(),'message'=>(string)($flash['message']??''),'error'=>$error,'settings'=>$settings,'user'=>$user];
    }

    private function add(): void
    {
        $slug=substr(trim((string)($_POST['slug']??'')),0,200);if($slug==='')throw new PublicException('Choose a product first.');
        $qty=max(1,min(10,(int)($_POST['quantity']??1)));$variantId=max(0,(int)($_POST['variant_id']??0));
        $repo=ServiceFactory::storefront(\db());if(!$repo->schemaReady())throw new PublicException('The catalog is temporarily unavailable.');
        if(!\store_catalog_enabled($repo->settings()))throw new PublicException('The catalog is temporarily unavailable.');
        $product=$repo->productBySlug($slug);if(!$product)throw new PublicException('That perfume is unavailable.');
        if($variantId>0)$variant=$repo->productVariant((int)$product['id'],$variantId);
        else{
            $available=array_values(array_filter($repo->productVariants((int)$product['id']),static fn($v)=>(int)($v['available_qty']??0)>0));
            if(count($available)>1)throw new PublicException('Choose a size before adding this perfume.');
            $variant=$available[0]??null;
        }
        if(!$variant)throw new PublicException('Choose an available size before adding this perfume.');
        $available=max(0,(int)($variant['available_qty']??0));if($available<=0)throw new PublicException('That size is currently out of stock.');
        $unit=(float)((float)($variant['sale_price']??0)>0&&(float)$variant['sale_price']<(float)$variant['price']?$variant['sale_price']:$variant['price']);
        $key=\store_cart_key($slug,(int)$variant['id'],(string)$variant['label']);$cart=\store_cart_items();$existing=max(0,(int)($cart[$key]['quantity']??0));$quantity=min(10,$existing+$qty);
        if($quantity>$available)throw new PublicException('Only '.$available.' of that size '.($available===1?'is':'are').' currently available.');
        $cart[$key]=['key'=>$key,'slug'=>$slug,'product_id'=>(int)$product['id'],'variant_id'=>(int)$variant['id'],'name'=>(string)($product['name']??'Perfume'),'brand'=>(string)($product['brand_name']??''),'label'=>(string)($variant['label']??''),'unit_price'=>$unit,'currency'=>(string)($variant['currency']??'EGP'),'quantity'=>$quantity,'image'=>(string)($product['main_image_path']??'')];
        $_SESSION['_store_cart']=$cart;
        if(function_exists('visitor_analytics_event'))\visitor_analytics_event('product_added_to_cart',!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,['product_id'=>(int)$product['id'],'product_slug'=>$slug,'variant_id'=>(int)$variant['id']]);
    }

    private function update(): void
    {
        $cart=\store_cart_items();$quantities=$_POST['quantity']??[];if(!is_array($quantities))return;
        $commerce=ServiceFactory::commerceRepository(\db());$fresh=$commerce->cartSnapshot($cart);
        if(count($fresh)!==count($cart))throw new PublicException('One or more bag items are no longer available. Remove them and try again.');
        foreach($quantities as $key=>$qty){if(!isset($fresh[$key]))continue;$n=max(0,min(10,(int)$qty));if($n===0){unset($fresh[$key]);continue;}$available=max(0,(int)($fresh[$key]['available_qty']??0));if($n>$available)throw new PublicException($fresh[$key]['name'].' · '.$fresh[$key]['label'].' only has '.$available.' left in stock.');$fresh[$key]['quantity']=$n;}
        $_SESSION['_store_cart']=$fresh;
    }

    private function remove(): void
    {
        $key=(string)($_POST['key']??'');$cart=\store_cart_items();unset($cart[$key]);$_SESSION['_store_cart']=$cart;
    }
}
