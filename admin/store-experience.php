<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('store_settings');
use App\Repositories\StoreSettingsRepository;
use App\Storefront\StoreThemeConfig;
use App\Support\ServiceFactory;

$h=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$pdo=db();$repo=ServiceFactory::storeSettingsRepository($pdo);
$settings=$repo->all();$error='';$flash='';
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $intent=(string)($_POST['intent']??'');
        if(!in_array($intent,['save','publish','restore'],true))throw new InvalidArgumentException('Invalid action.');
        if(!$repo->schemaReady())throw new RuntimeException('Store settings are unavailable; run the existing migrations first.');
        $json='';
        if($intent!=='restore'){$payload=StoreThemeConfig::fromPost($_POST);$json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
        $pdo->beginTransaction();
        try{
            // The revision row must exist before SELECT FOR UPDATE can serialize concurrent first-time writes.
            $ensure=$pdo->prepare("INSERT IGNORE INTO store_settings(setting_key,setting_value,updated_by) VALUES('v5_theme_revision','0',?)");
            $ensure->execute([(int)($_SESSION['user_id']??0)]);
            $lock=$pdo->prepare("SELECT setting_value FROM store_settings WHERE setting_key='v5_theme_revision' FOR UPDATE");
            $lock->execute();$rev=(int)($lock->fetchColumn()?:0);
            if($rev!==(int)($_POST['revision']??-1))throw new RuntimeException('Theme settings changed in another tab. Reload before saving.');
            $changes=['v5_theme_revision'=>(string)($rev+1)];
            if($intent!=='restore')$changes['v5_theme_draft']=$json;
            if($intent==='publish'){
                // Retain a restorable copy of the current published theme.
                $current=$repo->all();
                $changes['v5_theme_previous']=(string)($current['v5_theme_published']??'');
                $changes['v5_theme_published']=$json;
            }
            if($intent==='restore'){
                $current=$repo->all();
                $changes['v5_theme_published']=(string)($current['v5_theme_previous']??'');
                $changes['v5_theme_previous']='';
            }
            $repo->setMany($changes,(int)($_SESSION['user_id']??0));
            admin_audit('store_theme_'.$intent,'Store theme: '.$intent.'.');
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        storefront_cache_clear();
        web_post_redirect('admin/store-experience.php','v5_theme',$intent==='publish'?'Store theme published.':($intent==='restore'?'Previous theme or Store Designer settings restored.':'Theme draft saved.'),'');
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():web_safe_exception_message($e,'v5_theme_save','Store theme could not be saved.');}
}
$settings=$repo->all();$revision=(int)($settings['v5_theme_revision']??0);
$published=StoreThemeConfig::decode($settings['v5_theme_published']??'');
$draft=StoreThemeConfig::decode($settings['v5_theme_draft']??'')??$published??StoreThemeConfig::defaults($settings);
$presets=StoreThemeConfig::presets();

$flashRow=web_flash_take('v5_theme');$flash=$flashRow['message']??'';$error=$error?:($flashRow['error']??'');
$departments=[];
try{$departments=ServiceFactory::storefront($pdo)->navigationDepartments();}catch(Throwable $e){\App\Support\Logger::warning('Theme setup departments unavailable',['error'=>$e->getMessage()]);}
$previewNavigation=\App\Storefront\StoreNavigationConfig::decode($settings['v5_navigation_published']??'',$departments)
    ?? \App\Storefront\StoreNavigationConfig::defaults($departments);
require __DIR__.'/../views/admin/store-experience.php';
