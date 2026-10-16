<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('store_settings');
use App\Storefront\StoreModulesConfig;
use App\Support\ServiceFactory;

$h=static fn(mixed $s):string=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$pdo=db();$repo=ServiceFactory::storeSettingsRepository($pdo);$error='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $action=(string)($_POST['intent']??'');
        if(!in_array($action,['save','publish','restore'],true))throw new InvalidArgumentException('Invalid module action.');
        if(!$repo->schemaReady())throw new RuntimeException('Store settings table is unavailable.');
        $payload=$action==='restore'?'':json_encode(StoreModulesConfig::fromPost($_POST),JSON_THROW_ON_ERROR);
        $pdo->beginTransaction();
        try{
            $adminId=(int)($_SESSION['user_id']??0);
            $pdo->prepare("INSERT IGNORE INTO store_settings(setting_key,setting_value,updated_by) VALUES('v5_modules_revision','0',?)")->execute([$adminId]);
            $lock=$pdo->query("SELECT setting_value FROM store_settings WHERE setting_key='v5_modules_revision' FOR UPDATE");
            $rev=(int)($lock->fetchColumn()?:0);
            if($rev!==(int)($_POST['revision']??-1))throw new RuntimeException('Store modules were changed in another tab. Reload and try again.');
            $settings=$repo->all();
            $changes=['v5_modules_revision'=>(string)($rev+1)];
            if($action!=='restore')$changes['v5_modules_draft']=$payload;
            if($action==='publish'){
                $changes['v5_modules_previous']=(string)($settings['v5_modules_published']??'');
                $changes['v5_modules_published']=$payload;
            }
            if($action==='restore'){
                $changes['v5_modules_published']=(string)($settings['v5_modules_previous']??'');
                $changes['v5_modules_previous']='';
            }
            $repo->setMany($changes,$adminId);
            admin_audit('store_modules_'.$action,'Store module configuration '.$action);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        storefront_cache_clear();
        web_post_redirect('admin/store-modules.php','v5_modules',$action==='publish'?'Modules published.':($action==='restore'?'Previous published modules restored.':'Module draft saved.'),'');
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():web_safe_exception_message($e,'store_modules_save','Store modules could not be saved.');}
}
$settings=$repo->all();$revision=(int)($settings['v5_modules_revision']??0);
$published=StoreModulesConfig::decode($settings['v5_modules_published']??'');
$draft=StoreModulesConfig::decode($settings['v5_modules_draft']??'')??$published??StoreModulesConfig::defaults();
$modules=StoreModulesConfig::definitions();$flashRow=web_flash_take('v5_modules');
$flash=$flashRow['message']??'';$error=$error?:($flashRow['error']??'');
require __DIR__.'/../views/admin/store-modules.php';
