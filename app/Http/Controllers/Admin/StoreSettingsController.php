<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Support\PublicException;
use App\Support\ServiceFactory;
final class StoreSettingsController
{
    public function handle(): array
    {
        $repo=ServiceFactory::storeSettingsRepository(\db());$service=ServiceFactory::storeSettings();
        if(!$repo->schemaReady())return ['schemaReady'=>false,'settings'=>[],'message'=>'','error'=>'Commerce schema is not installed yet. Run migration 034 first.'];
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$message='';$error='';
            try{$settings=$service->normalize($_POST);$repo->setMany($settings,(int)$_SESSION['user_id']);\storefront_cache_clear();\admin_audit('store_settings_updated','Store identity and commerce settings updated.');$message='Store settings saved.';}catch(PublicException $e){$error=$e->getMessage();}catch(\Throwable $e){$error=\web_safe_exception_message($e,'store_settings_action','Store settings could not be saved.');}
            $tab=(string)($_POST['active_tab']??'global');if(!in_array($tab,['global','navigation','footer','pages','social'],true))$tab='global';
            \web_post_redirect('admin/store-settings.php','admin_store_settings',$message,$error,'tab='.$tab);
        }$flash=\web_flash_take('admin_store_settings');return ['schemaReady'=>true,'settings'=>$repo->all(),'message'=>$flash['message'],'error'=>$flash['error']];
    }
}
