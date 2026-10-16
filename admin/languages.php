<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('store_settings');
use App\Repositories\StoreSettingsRepository;
use App\Support\ServiceFactory;
use App\Storefront\StoreLocale;
$h=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$pdo=db();$repo=ServiceFactory::storeSettingsRepository($pdo);$error='';$notice='';
$entityTables=['product'=>'store_products','category'=>'store_categories','brand'=>'store_brands','department'=>'commerce_departments','section'=>'homepage_sections','section_item'=>'homepage_section_items'];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    verify_csrf();
    try{
        $intent=(string)($_POST['intent']??'');
        if($intent==='translation'){
            require_admin_capability('catalog_manage');
            $type=(string)($_POST['entity_type']??'');$entityId=(int)($_POST['entity_id']??0);
            if(!isset($entityTables[$type])||$entityId<=0)throw new InvalidArgumentException('Choose a valid catalog record.');
            $table=$entityTables[$type];
            $labelColumn=in_array($type,['section','section_item'],true)?'title':'name';
            $row=$pdo->prepare("SELECT id,{$labelColumn} AS name FROM {$table} WHERE id=? LIMIT 1");$row->execute([$entityId]);
            if(!$row->fetch())throw new InvalidArgumentException('That record does not exist.');
            $translationFields=in_array($type,['section','section_item'],true)?['title'=>180,'subtitle'=>500,'eyebrow'=>120,'cta_label'=>100]:['name'=>180,'description'=>12000,'short_description'=>500,'seo_title'=>180,'seo_description'=>320];
            if($type!=='product')unset($translationFields['short_description']);
            $pdo->beginTransaction();
            try{
                $save=$pdo->prepare("INSERT INTO store_entity_translations(entity_type,entity_id,locale,field_key,text_value) VALUES(?,?,'ar',?,?) ON DUPLICATE KEY UPDATE text_value=VALUES(text_value)");
                $delete=$pdo->prepare("DELETE FROM store_entity_translations WHERE entity_type=? AND entity_id=? AND locale='ar' AND field_key=?");
                foreach($translationFields as $field=>$max){
                    $value=trim((string)($_POST['translation'][$field]??''));
                    if(mb_strlen($value)>$max)throw new InvalidArgumentException($field.' translation is too long.');
                    if($value==='')$delete->execute([$type,$entityId,$field]);else $save->execute([$type,$entityId,$field,$value]);
                }
                admin_audit('catalog_translation_update','Arabic translation updated for '.$type.' #'.$entityId);
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            $notice='Arabic translation saved. Original English content is untouched.';
            $_GET['entity_type']=$type;$_GET['entity_id']=$entityId;
        }else{
            if(!in_array($intent,['save','publish','restore'],true))throw new InvalidArgumentException('Unsupported action.');
            $enabled=array_values(array_intersect(['en','ar'],array_keys(array_filter((array)($_POST['enabled']??[]),static fn($v)=>$v==='1'))));
            if(!$enabled)throw new InvalidArgumentException('Enable at least one language.');
            $default=(string)($_POST['default']??'en');
            if(!in_array($default,$enabled,true))throw new InvalidArgumentException('Default language must be enabled.');
            $payload=json_encode(['enabled'=>$enabled,'default'=>$default],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $pdo->beginTransaction();
            try{
                $pdo->exec("INSERT IGNORE INTO store_settings(setting_key,setting_value) VALUES('v5_locales_revision','0')");
                $lock=$pdo->query("SELECT setting_value FROM store_settings WHERE setting_key='v5_locales_revision' FOR UPDATE");
                $rev=(int)$lock->fetchColumn();
                if($rev!==(int)($_POST['revision']??-1))throw new RuntimeException('Language settings changed in another tab. Refresh and retry.');
                $changes=['v5_locales_revision'=>(string)($rev+1)];
                if($intent!=='restore')$changes['v5_locales_draft']=$payload;
                if($intent==='publish'){$all=$repo->all();$changes['v5_locales_previous']=(string)($all['v5_locales_published']??'');$changes['v5_locales_published']=$payload;}
                if($intent==='restore'){$all=$repo->all();$changes['v5_locales_published']=(string)($all['v5_locales_previous']??'');$changes['v5_locales_previous']='';}
                $repo->setMany($changes,(int)($_SESSION['user_id']??0));
                admin_audit('store_languages_'.$intent,'Store languages '.$intent);
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            storefront_cache_clear();$notice=$intent==='publish'?'Languages published.':($intent==='restore'?'Previous language configuration restored.':'Language draft saved.');
        }
    }catch(Throwable $e){$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():web_safe_exception_message($e,'store_language','Language settings could not be saved.');}
}
$settings=$repo->all();$revision=(int)($settings['v5_locales_revision']??0);
$draftRaw=$settings['v5_locales_draft']??($settings['v5_locales_published']??null);
$draft=StoreLocale::config(['v5_locales_published'=>$draftRaw]);
$published=StoreLocale::config($settings);
$type=(string)($_GET['entity_type']??'product');if(!isset($entityTables[$type]))$type='product';
$entityId=max(0,(int)($_GET['entity_id']??0));$record=null;$translation=[];$examples=[];
$recordSearch=mb_substr(trim((string)($_GET['q']??'')),0,100);
try{
    $table=$entityTables[$type];$labelCol=in_array($type,['section','section_item'],true)?'title':'name';$descCol=in_array($type,['section','section_item'],true)?'subtitle':'description';
    if($recordSearch!==''){
        $literal=str_replace(['!','%','_'],['!!','!%','!_'],$recordSearch);
        $s=$pdo->prepare("SELECT id,{$labelCol} AS name FROM {$table} WHERE {$labelCol} LIKE ? ESCAPE '!' ORDER BY {$labelCol},id LIMIT 50");
        $s->execute(['%'.$literal.'%']);
    }else{$s=$pdo->query("SELECT id,{$labelCol} AS name FROM {$table} ORDER BY id DESC LIMIT 50");}
    $examples=$s->fetchAll(PDO::FETCH_ASSOC);
    if($entityId>0){$s=$pdo->prepare("SELECT id,{$labelCol} AS name,{$descCol} AS description FROM {$table} WHERE id=? LIMIT 1");$s->execute([$entityId]);$record=$s->fetch(PDO::FETCH_ASSOC)?:null;
        if($record){$q=$pdo->prepare("SELECT field_key,text_value FROM store_entity_translations WHERE entity_type=? AND entity_id=? AND locale='ar'");$q->execute([$type,$entityId]);$translation=$q->fetchAll(PDO::FETCH_KEY_PAIR);}
    }
}catch(Throwable $e){$error=$error?:'Catalog translation table is not ready. Run migration 051 on the development database.';}
require_once __DIR__.'/../includes/admin_layout.php';
admin_page_start('Languages & Translations','languages','STORE SETUP','Languages','& Translations','Choose languages and manage Arabic catalog copy. English content stays unchanged.');
?>
<style>.loc-wrap{max-width:1160px;margin:0 auto}.loc-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.loc-panel{padding:25px;background:#fff;border:1px solid #e5dfd8;border-radius:14px}.loc-panel h2{margin:0 0 12px;font-size:1.3rem}.loc-panel p{color:#756d64;line-height:1.5}.loc-form{display:flex;flex-direction:column;gap:15px}.loc-form label{display:flex;flex-direction:column;gap:7px;font-weight:600}.loc-form input:not([type=checkbox]),.loc-form select,.loc-form textarea{width:100%;min-height:42px;padding:10px;border:1px solid #d4cdc6;border-radius:8px;box-sizing:border-box;background:#fff;color:#242020}.loc-form textarea{min-height:85px;resize:vertical}.loc-actions{display:flex;gap:9px;flex-wrap:wrap}.loc-check{display:flex!important;flex-direction:row!important;align-items:center}.loc-alert{padding:13px 15px;border-radius:9px;margin-bottom:14px;background:#f4eee7}.loc-alert.error{background:#fce8e8}.loc-examples{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.loc-examples a{font-size:.8rem;padding:6px 9px;background:#f1eee9;border-radius:8px}.loc-help{margin-top:18px;padding:18px;background:#faf6ef;border-radius:12px}@media(max-width:800px){.loc-grid{grid-template-columns:1fr}.loc-panel{padding:18px}}</style>
<div class="loc-wrap">
<?php if($notice):?><div role="status" class="loc-alert"><?=$h($notice)?></div><?php endif;?>
<?php if($error):?><div role="alert" class="loc-alert error"><?=$h($error)?></div><?php endif;?>
<div class="loc-grid">
<section class="loc-panel"><h2>Storefront languages</h2><p>Configure this client installation only. Save Draft does not affect customers until Publish.</p>
<form class="loc-form" method="post" action="<?=$h(route_url('admin/languages.php'))?>">
<?=csrf_field()?><input type="hidden" name="revision" value="<?=$revision?>">
<label class="loc-check"><input type="checkbox" name="enabled[en]" value="1" <?=in_array('en',$draft['enabled'],true)?'checked':''?>> English (LTR)</label>
<label class="loc-check"><input type="checkbox" name="enabled[ar]" value="1" <?=in_array('ar',$draft['enabled'],true)?'checked':''?>> العربية — Arabic (RTL)</label>
<label>Default storefront language<select name="default"><option value="en" <?=$draft['default']==='en'?'selected':''?>>English</option><option value="ar" <?=$draft['default']==='ar'?'selected':''?>>العربية</option></select></label>
<div class="loc-actions"><button class="app-button admin-button secondary" name="intent" value="save" type="submit">Save Draft</button><button class="app-button admin-button primary" name="intent" value="publish" type="submit">Publish Languages</button><button class="app-button admin-button quiet" name="intent" value="restore" type="submit" data-confirm="Restore the previously published languages?">Restore Previous</button></div></form>
<p><strong>Live:</strong> <?=$h(implode(', ',$published['enabled']))?> (default: <?=$h($published['default'])?>)</p></section>
<section class="loc-panel"><h2>Catalog translations</h2><p>Translate product, category, brand, and department names/descriptions. Blank Arabic fields fall back to English. Existing Arabic catalog columns are respected.</p>
<form class="loc-form" method="get" action="<?=$h(route_url('admin/languages.php'))?>"><label>Content type<select name="entity_type"><?php foreach($entityTables as $key=>$table):?><option value="<?=$h($key)?>" <?=$key===$type?'selected':''?>><?=ucfirst($h($key))?></option><?php endforeach;?></select></label><label>Find a record by its name or title<input type="search" name="q" maxlength="100" value="<?=$h($recordSearch)?>" placeholder="Type part of a product, brand or section title"></label><button class="app-button admin-button secondary">Search Records</button></form>
<?php if($examples):?><form class="loc-form" method="get" action="<?=$h(route_url('admin/languages.php'))?>" style="margin-top:16px"><input type="hidden" name="entity_type" value="<?=$h($type)?>"><input type="hidden" name="q" value="<?=$h($recordSearch)?>"><label>Select a matching record<select name="entity_id" required><option value="">Choose a record…</option><?php foreach($examples as $item):?><option value="<?=(int)$item['id']?>" <?=(int)$item['id']===$entityId?'selected':''?>><?=$h($item['name'])?> (#<?=(int)$item['id']?>)</option><?php endforeach;?></select></label><button class="app-button admin-button secondary">Open Translation Editor</button></form><?php else:?><p>No matching records. Try another search.</p><?php endif;?>
</section></div>
<?php if($record):?><section class="loc-panel" style="margin-top:18px"><h2>Arabic content for <?=$h($record['name'])?> · #<?=$entityId?></h2><form class="loc-form" method="post" action="<?=$h(route_url('admin/languages.php'))?>"><?=csrf_field()?><input type="hidden" name="intent" value="translation"><input type="hidden" name="entity_type" value="<?=$h($type)?>"><input type="hidden" name="entity_id" value="<?=$entityId?>">
<?php if(in_array($type,['section','section_item'],true)):?>
<label>Arabic heading <input dir="rtl" lang="ar" name="translation[title]" maxlength="180" value="<?=$h($translation['title']??'')?>"></label>
<label>Arabic supporting text <textarea dir="rtl" lang="ar" name="translation[subtitle]" maxlength="500"><?=$h($translation['subtitle']??'')?></textarea></label>
<label>Arabic eyebrow <input dir="rtl" lang="ar" name="translation[eyebrow]" maxlength="120" value="<?=$h($translation['eyebrow']??'')?>"></label>
<label>Arabic button text <input dir="rtl" lang="ar" name="translation[cta_label]" maxlength="100" value="<?=$h($translation['cta_label']??'')?>"></label>
<?php else:?>
<label>Arabic name <input type="text" dir="rtl" lang="ar" maxlength="180" name="translation[name]" value="<?=$h($translation['name']??'')?>" placeholder="الاسم بالعربية"></label>
<label>Arabic description <textarea dir="rtl" lang="ar" name="translation[description]" maxlength="12000"><?=$h($translation['description']??'')?></textarea></label>
<?php if($type==='product'):?><label>Arabic short description <textarea dir="rtl" lang="ar" name="translation[short_description]" maxlength="500"><?=$h($translation['short_description']??'')?></textarea></label><?php endif;?>
<label>Arabic SEO title <input dir="rtl" lang="ar" name="translation[seo_title]" maxlength="180" value="<?=$h($translation['seo_title']??'')?>"></label><label>Arabic SEO description <textarea dir="rtl" lang="ar" name="translation[seo_description]" maxlength="320"><?=$h($translation['seo_description']??'')?></textarea></label>
<?php endif;?>
<div class="loc-actions"><button class="app-button admin-button primary" type="submit">Save Arabic Translation</button></div></form></section><?php endif;?>
<div class="loc-help"><strong>Translation policy:</strong> This editor does not auto-translate customer content. Only saved translations are displayed. Checkout, transactional emails, account and admin areas may still contain English until their translations are completed and reviewed.</div>
</div>
<?php admin_page_end();
