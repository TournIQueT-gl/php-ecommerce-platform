<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\PublicException;
use App\Support\ServiceFactory;

final class WishlistController
{
    public function handle(): array
    {
        $isStateRequest=(($_SERVER['REQUEST_METHOD']??'GET')==='GET' && isset($_GET['wishlist_state']));
        $isAjax=(string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='PerfumeStore-Wishlist' || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
        $user=\current_user();
        if(!\store_module_enabled('wishlist')){
            if($isStateRequest)$this->json(['ok'=>true,'authenticated'=>(bool)$user,'saved'=>[]]);
            if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
                \verify_csrf();
                if($isAjax)$this->json(['ok'=>false,'authenticated'=>(bool)$user,'message'=>'Wishlist is not available for this store.'],403);
                return ['user'=>$user,'products'=>[],'message'=>'','error'=>'Wishlist is not available for this store.'];
            }
            return ['user'=>$user,'products'=>[],'message'=>'','error'=>'Wishlist is not available for this store.'];
        }


        if($isStateRequest){
            if(!$user)$this->json(['ok'=>true,'authenticated'=>false,'saved'=>[]]);
            try{
                $store=ServiceFactory::storefront(\db());$settings=$store->schemaReady()?$store->settings():[];
                if(!\store_catalog_enabled($settings))$this->json(['ok'=>true,'authenticated'=>true,'saved'=>[]]);
                $ids=array_filter(array_map('intval',explode(',',substr((string)($_GET['ids']??''),0,1200))),static fn(int $id):bool=>$id>0);
                $saved=ServiceFactory::commerceRepository(\db())->wishlistedProductIds((int)$user['id'],$ids);
                $this->json(['ok'=>true,'authenticated'=>true,'saved'=>$saved]);
            }catch(\Throwable $e){
                \App\Support\Logger::warning('Wishlist state unavailable',['message'=>$e->getMessage()]);
                $this->json(['ok'=>false,'authenticated'=>true,'saved'=>[]],500);
            }
        }

        if(!$user){
            if($isAjax)$this->json(['ok'=>false,'authenticated'=>false,'message'=>'Sign in to save products.','login_url'=>\route_url('login.php')],401);
            header('Location: '.\route_url('login.php'));exit;
        }

        $store=ServiceFactory::storefront(\db());$settings=$store->schemaReady()?$store->settings():[];$catalogEnabled=\store_catalog_enabled($settings);
        $repo=ServiceFactory::commerceRepository(\db());
        if(!$repo->wishlistSchemaReady()){
            if($isAjax)$this->json(['ok'=>false,'authenticated'=>true,'message'=>'Wishlist storage is not installed. Run the database migrations, then refresh the page.'],503);
            return ['user'=>$user,'products'=>[],'message'=>'','error'=>'Wishlist storage is not installed. Run the database migrations.'];
        }
        $message='';$error=$catalogEnabled?'':'The catalog is temporarily unavailable.';$saved=null;$productId=0;
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'toggle');
            try{
                if(!$catalogEnabled)throw new PublicException('The catalog is temporarily unavailable.');
                $productId=max(0,(int)($_POST['product_id']??0));
                if($productId<=0){$slug=substr(trim((string)($_POST['slug']??'')),0,200);if($slug!=='')$productId=(int)($repo->wishlistProductIdBySlug($slug)??0);}
                if($productId<=0)throw new PublicException('That product is not available.');

                if($action==='toggle')$action=$repo->isWishlisted((int)$user['id'],$productId)?'remove':'add';
                if($action==='remove'){$repo->removeWishlist((int)$user['id'],$productId);$saved=false;$message='Removed from your wishlist.';}
                elseif($action==='add'){$repo->addWishlist((int)$user['id'],$productId);$saved=true;$message='Saved to your wishlist.';if(function_exists('visitor_analytics_event'))\visitor_analytics_event('wishlist_added',(int)$user['id'],['product_id'=>$productId]);}
                else throw new PublicException('Unknown wishlist action.');
            }catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'wishlist_action','Your wishlist could not be updated.');}

            if($isAjax){
                if($error!=='')$this->json(['ok'=>false,'authenticated'=>true,'message'=>$error,'product_id'=>$productId],422);
                $this->json(['ok'=>true,'authenticated'=>true,'saved'=>$saved,'message'=>$message,'product_id'=>$productId]);
            }

            $_SESSION['_wishlist_flash']=['message'=>$message,'error'=>$error];
            $return=trim((string)($_POST['return_to']??''));
            if($return===''||!str_starts_with($return,'/')||str_starts_with($return,'//')||str_contains($return,'\\')||preg_match('/[\x00-\x1F\x7F]/',$return)===1)$return=\route_url('wishlist.php');
            header('Location: '.$return,true,303);exit;
        }
        $flash=$_SESSION['_wishlist_flash']??['message'=>'','error'=>''];unset($_SESSION['_wishlist_flash']);
        $rows=[];try{if($catalogEnabled)$rows=\App\Storefront\StoreLocale::rows($repo->wishlistItems((int)$user['id']),'product');}catch(\Throwable $e){$error=\web_safe_exception_message($e,'wishlist_load','Your wishlist is temporarily unavailable.');}
        return ['user'=>$user,'products'=>$rows,'message'=>(string)($flash['message']??''),'error'=>$error!==''?$error:(string)($flash['error']??'')];
    }

    private function json(array $payload,int $status=200): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
        if(isset($payload['message'])&&is_string($payload['message']))$payload['message']=\store_public_message_t($payload['message']);
        echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
    }
}
