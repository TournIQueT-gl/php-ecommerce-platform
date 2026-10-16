<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('store_settings');
use App\Storefront\StoreNavigationConfig;
use App\Support\ServiceFactory;
$h=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$repo=ServiceFactory::storeSettingsRepository(db());
$settings=$repo->all();
$departments=[];
try{$departments=ServiceFactory::storefront(db())->navigationDepartments();}catch(Throwable $e){\App\Support\Logger::warning('Store configuration department load failed',['error'=>$e->getMessage()]);}
$flash='';$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $intent=(string)($_POST['intent']??'save');
        if(!in_array($intent,['save','publish','restore'],true))throw new InvalidArgumentException('Invalid action.');
        if(!$repo->schemaReady())throw new RuntimeException('Run the existing commerce migrations first.');
        $json='';
        if($intent!=='restore'){
            $config=StoreNavigationConfig::fromPost($_POST,$departments);
            $json=json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        }
        $pdo=db();$pdo->beginTransaction();
        try{
            // Prevent one browser tab from silently overwriting another admin's edits.
            $lock=$pdo->prepare("SELECT setting_value FROM store_settings WHERE setting_key='v5_navigation_revision' FOR UPDATE");$lock->execute();$rev=(int)($lock->fetchColumn()?:0);
            if($rev!==(int)($_POST['revision']??-1))throw new RuntimeException('The navigation changed in another tab. Reload before saving.');
            $values=['v5_navigation_revision'=>(string)($rev+1)];
            if($intent!=='restore')$values['v5_navigation_draft']=$json;
            if($intent==='publish')$values['v5_navigation_published']=$json;
            if($intent==='restore')$values['v5_navigation_published']='';
            $repo->setMany($values,(int)$_SESSION['user_id']);
            admin_audit('store_navigation_'.$intent, 'Storefront navigation: '. $intent .'.');
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        storefront_cache_clear();
        web_post_redirect('admin/storefront-configuration.php','v5_navigation',$intent==='publish'?'Navigation published.':($intent==='restore'?'Legacy navigation restored.':'Draft saved.'),'');
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():web_safe_exception_message($e,'v5_navigation_save','Navigation could not be saved.');}
}
$settings=$repo->all();$revision=(int)($settings['v5_navigation_revision']??0);
$draft=StoreNavigationConfig::decode($settings['v5_navigation_draft']??'',$departments)??StoreNavigationConfig::defaults($departments);
$published=StoreNavigationConfig::decode($settings['v5_navigation_published']??'',$departments);
$fl=web_flash_take('v5_navigation');$flash=$fl['message']??'';$error=$error?:($fl['error']??'');
require __DIR__.'/../views/admin/storefront-configuration.php';
