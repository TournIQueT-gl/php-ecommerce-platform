<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\Logger;
use App\Support\PublicException;
use App\Support\ServiceFactory;

final class ProductController
{
    public function handle(): array
    {
        $slug=substr(trim((string)($_GET['slug']??$_POST['slug']??'')),0,200);
        $schemaReady=false;$databaseReady=false;$settings=[];$product=null;$variants=[];$notes=[];$related=[];$department=null;$specifications=[];$variantOptions=[];$reviews=[];$reviewSummary=['count'=>0,'average'=>0.0];$canReview=false;$wishlisted=false;$setupMessage='';$message='';$error='';$user=null;
        try{$user=\current_user();}catch(\Throwable){}
        try{
            $repo=ServiceFactory::storefront(\db());$databaseReady=true;$schemaReady=$repo->schemaReady();
            if($schemaReady&&$slug!==''){
                $settings=$repo->settings();if(!\store_catalog_enabled($settings)){$setupMessage='The catalog is temporarily unavailable.';}else{$product=$repo->productBySlug($slug);
                if($product){
                    $department=$repo->departmentForProduct((int)$product['id']);
                    $specifications=$repo->productSpecifications((int)$product['id']);
                    $variantOptions=$repo->variantAttributeOptions((int)$product['id']);
                    $variants=$repo->productVariants((int)$product['id']);$notes=$repo->productNotes((int)$product['id']);if(\store_module_enabled('recommendations',$settings))$related=$repo->relatedProducts($product,4);
                    $commerce=ServiceFactory::commerceRepository(\db());if(\store_module_enabled('reviews',$settings)){$reviews=$commerce->productReviews((int)$product['id']);$reviewSummary=$commerce->reviewSummary((int)$product['id']);}
                    if($user){if(\store_module_enabled('reviews',$settings))$canReview=$commerce->verifiedPurchaseOrderId((int)$user['id'],(int)$product['id'])!==null;if(\store_module_enabled('wishlist',$settings))$wishlisted=$commerce->isWishlisted((int)$user['id'],(int)$product['id']);}
                }}
            }
        }catch(\Throwable $e){$setupMessage='The product database is unavailable. Check the store database configuration.';Logger::warning('Product database unavailable',['message'=>$e->getMessage()]);}

        if($product&&($_SERVER['REQUEST_METHOD']??'GET')==='POST'&&(string)($_POST['action']??'')==='review'){
            \verify_csrf();
            try{
                if(!\store_module_enabled('reviews',$settings))throw new PublicException('Customer reviews are turned off for this store.');
                if(!$user)throw new PublicException('Sign in before writing a review.');
                $commerce=ServiceFactory::commerceRepository(\db());$orderId=$commerce->verifiedPurchaseOrderId((int)$user['id'],(int)$product['id']);
                if($orderId===null)throw new PublicException('Reviews are available after a delivered purchase of this product.');
                $rating=max(0,(int)($_POST['rating']??0));if($rating<1||$rating>5)throw new PublicException('Choose a rating from 1 to 5.');
                $title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));if(mb_strlen($title)>160)throw new PublicException('Review title is too long.');if($body===''||mb_strlen($body)>4000)throw new PublicException('Write a short review of up to 4000 characters.');
                $commerce->upsertReview((int)$product['id'],(int)$user['id'],$orderId,$rating,$title!==''?$title:null,$body);$message='Your review was submitted for moderation. Thank you.';
                $reviewRevision=bin2hex(random_bytes(8));
                try{ServiceFactory::notifications(\db())->broadcastToAdminRoles(['owner','admin'],'review.submitted','Review awaiting moderation',(string)$product['name'].' · '.$rating.'/5 by '.(string)($user['username']??'Customer'),['product_id'=>(int)$product['id'],'product_slug'=>$slug,'order_id'=>$orderId],'review-pending:'.(int)$product['id'].':'.(int)$user['id'].':'.$reviewRevision);}catch(\Throwable $e){Logger::warning('Review moderation notification failed',['product_id'=>(int)$product['id'],'user_id'=>(int)$user['id'],'message'=>$e->getMessage()]);}
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'product_review','Your review could not be saved.');}
            $_SESSION['_product_flash'][$slug]=['message'=>$message,'error'=>$error];header('Location: '.\route_url('product.php').'?slug='.rawurlencode($slug).'#reviews',true,303);exit;
        }
        $flash=$_SESSION['_product_flash'][$slug]??null;if(is_array($flash)){unset($_SESSION['_product_flash'][$slug]);$message=(string)($flash['message']??'');$error=(string)($flash['error']??'');}
        if($product&&($_SERVER['REQUEST_METHOD']??'GET')==='GET'&&function_exists('visitor_analytics_event'))\visitor_analytics_event('product_viewed',$user?(int)$user['id']:null,['product_id'=>(int)$product['id'],'product_slug'=>$slug]);
        $product=$product?\store_localize_entity($product,'product'):null;
        $related=\store_localize_rows($related,'product');
        return compact('slug','schemaReady','databaseReady','settings','product','variants','notes','related','reviews','reviewSummary','canReview','wishlisted','setupMessage','message','error','user','department','specifications','variantOptions');
    }
}
