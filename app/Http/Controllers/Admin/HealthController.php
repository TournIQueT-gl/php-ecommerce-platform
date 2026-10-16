<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Support\ServiceFactory;
use App\Support\SecretStore;

final class HealthController
{
    public function handle(): array
    {
        if(\admin_current_role()!=='owner')throw new \RuntimeException('Owner access is required for system health.');
        $db=\db();$required=['users','web_sessions','login_security_events','audit_log','account_activity','support_tickets','support_messages','notifications','schema_migrations','domain_event_outbox','scheduler_state','mail_settings','email_preferences','email_deliveries','visitor_sessions','visitor_pageviews','visitor_events','store_settings','store_products','store_product_variants','store_orders','store_order_items','store_payments'];
        $missing=[];foreach($required as $table){try{$s=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);if((int)$s->fetchColumn()!==1)$missing[]=$table;}catch(\Throwable){$missing[]=$table;}}
        $dbOk=!$missing;$storeReady=false;try{$storeReady=ServiceFactory::storefront($db)->schemaReady();}catch(\Throwable){}
        $activeSessions=0;try{$activeSessions=(int)$db->query('SELECT COUNT(*) FROM web_sessions WHERE revoked_at IS NULL AND last_seen_at>=UTC_TIMESTAMP()-INTERVAL 30 DAY')->fetchColumn();}catch(\Throwable){}
        $twoFactorReady=false;try{$twoFactorReady=ServiceFactory::twoFactorRepository($db)->schemaReady();}catch(\Throwable){}
        $emailReady=false;try{$emailReady=ServiceFactory::email($db)->schemaReady();}catch(\Throwable){}
        $analyticsReady=false;try{$analyticsReady=ServiceFactory::visitorAnalytics($db)->schemaReady();}catch(\Throwable){}
        $backupDir=\backup_storage_dir();$backupWritable=is_dir($backupDir)&&is_writable($backupDir);$logDir=dirname(__DIR__,4).'/storage/logs';$logWritable=is_dir($logDir)&&is_writable($logDir);$maintenance=\maintenance_state();
        $schemaVersion=null;try{$schemaVersion=ServiceFactory::migrations($db)->currentVersion();}catch(\Throwable){}
        $migrationFiles=(array)glob(dirname(__DIR__,4).'/database/migrations/*.sql');sort($migrationFiles,SORT_STRING);$packagedSchemaVersion=$migrationFiles?(preg_replace('/\.sql$/','',basename((string)end($migrationFiles)))?:''):'';$databaseUpdatesCurrent=$packagedSchemaVersion!==''&&$schemaVersion!==null&&hash_equals($packagedSchemaVersion,$schemaVersion);
        $schedulerMode=['configured'=>'auto','effective'=>'web','cli_healthy'=>false,'web_enabled'=>false];try{$schedulerMode=ServiceFactory::scheduler($db)->modeStatus();}catch(\Throwable){}
        $fallbackMediaCount=0;try{$s=$db->query("SELECT COUNT(*) FROM store_products WHERE status='active' AND main_image_path LIKE 'uploads/media/catalog/curated/%'");$fallbackMediaCount=(int)$s->fetchColumn();}catch(\Throwable){}
        $supportReady=false;$supportNote='Configure a public WhatsApp URL or transactional reply-to email.';try{$runtime=\store_runtime_settings();$wa=trim((string)($runtime['social_whatsapp']??''));$mail=ServiceFactory::emailRepository($db)->settings();$supportEmail=trim((string)($mail['reply_to_email']??$mail['from_email']??''));$supportReady=($wa!==''&&filter_var($wa,FILTER_VALIDATE_URL)!==false&&preg_match('~^https://~i',$wa)===1)||($supportEmail!==''&&filter_var($supportEmail,FILTER_VALIDATE_EMAIL)!==false);if($supportReady)$supportNote=$wa!==''?'Guest support is available through the configured WhatsApp destination.':'Guest support is available through the configured support email.';}catch(\Throwable){}
        $canonicalHost=strtolower((string)(parse_url((string)CANONICAL_SITE_URL,PHP_URL_HOST)??''));$canonicalScheme=strtolower((string)(parse_url((string)CANONICAL_SITE_URL,PHP_URL_SCHEME)??''));$localRequest=function_exists('web_is_local_host')&&web_is_local_host();$canonicalConfigured=defined('CANONICAL_SITE_URL_CONFIGURED')?CANONICAL_SITE_URL_CONFIGURED:(trim((string)(getenv('PERFUME_CANONICAL_SITE_URL')?:''))!=='');$canonicalOk=$localRequest||($canonicalConfigured&&$canonicalHost!==''&&!in_array($canonicalHost,['localhost','127.0.0.1','::1'],true)&&$canonicalScheme==='https');$canonicalBlocking=!$localRequest&&!$canonicalOk;
        $secretStorage=[];try{$secretStorage=SecretStore::storageStatus();}catch(\Throwable $e){$secretStorage=['external_outside_web_root'=>false,'external_ready'=>false,'has_conflict'=>true,'has_legacy_only'=>false,'purposes'=>[],'error'=>$e->getMessage()];}
        $protectedSecretsReady=SecretStore::available()&&($localRequest||(!empty($secretStorage['external_outside_web_root'])&&!empty($secretStorage['external_ready'])&&empty($secretStorage['has_conflict'])&&empty($secretStorage['has_legacy_only'])));
        $guestSecretState=(string)($secretStorage['purposes']['guest-order-event']['location']??'missing');
        $shippingCoverageReady=false;$shippingCoverageNote='Shipping configuration could not be verified.';
        try{
            $rows=$db->query("SELECT governorate FROM shipping_zones WHERE status='active'")->fetchAll(\PDO::FETCH_COLUMN);$specific=[];$hasDefault=false;
            foreach($rows as $gov){$gov=trim((string)$gov);if($gov===''){$hasDefault=true;continue;}$specific[strtolower($gov)]=true;}
            $requiredGovs=\store_egypt_governorates();$missingGovs=[];foreach($requiredGovs as $gov){if(!isset($specific[strtolower($gov)]))$missingGovs[]=$gov;}
            $shippingCoverageReady=count($specific)===0||$hasDefault||$missingGovs===[];
            $shippingCoverageNote=$shippingCoverageReady?(count($specific)===0?'All governorates use the global standard delivery settings.':($hasDefault?'Specific zones fall back safely to Rest of Egypt / default.':'All 27 governorates have explicit active zones.')):'Add an active Rest of Egypt / default zone or explicit zones for: '.implode(', ',$missingGovs).'.';
        }catch(\Throwable){}
        $items=[
            ['label'=>'Database connectivity','ok'=>true,'value'=>'CONNECTED','note'=>'PDO connection and schema inspection succeeded.'],
            ['label'=>'Required store tables','ok'=>$dbOk,'value'=>$dbOk?'READY':count($missing).' MISSING','note'=>$missing?'Missing: '.implode(', ',$missing):'Core account, store, order and analytics tables are present.'],
            ['label'=>'Commerce schema','ok'=>$storeReady,'value'=>$storeReady?'READY':'MIGRATION NEEDED','note'=>'Product, variant, inventory and CMS foundation.'],
            ['label'=>'Database migration level','ok'=>$databaseUpdatesCurrent,'blocking'=>!$databaseUpdatesCurrent,'value'=>$databaseUpdatesCurrent?'CURRENT':'PRODUCTION BLOCKER','note'=>$databaseUpdatesCurrent?'Database is at packaged schema '.$packagedSchemaVersion.'.':'Apply packaged migrations before launch. Database: '.(string)($schemaVersion??'UNVERSIONED').' · Package: '.($packagedSchemaVersion!==''?$packagedSchemaVersion:'UNKNOWN').'.'],
            ['label'=>'Account sessions','ok'=>true,'value'=>$activeSessions.' ACTIVE','note'=>'Website browser sessions seen in the last 30 days.'],
            ['label'=>'Admin two-factor','ok'=>$twoFactorReady,'value'=>$twoFactorReady?'READY':'CHECK SCHEMA','note'=>'Administrative TOTP capability.'],
            ['label'=>'Email subsystem','ok'=>$emailReady,'value'=>$emailReady?'READY':'CHECK CONFIG','note'=>'Recovery and transactional email storage/queue.'],
            ['label'=>'Visitor analytics','ok'=>$analyticsReady,'value'=>$analyticsReady?'READY':'CHECK SCHEMA','note'=>'Traffic and acquisition analytics.'],
            ['label'=>'Public customer support','ok'=>$supportReady,'value'=>$supportReady?'READY':'CONFIGURE','note'=>$supportNote],
            ['label'=>'Protected secret storage','ok'=>$protectedSecretsReady,'blocking'=>!$localRequest&&!$protectedSecretsReady,'value'=>$localRequest?'LOCAL DEV':($protectedSecretsReady?'READY':'PRODUCTION BLOCKER'),'note'=>$protectedSecretsReady?'OpenSSL and persistent external secret storage are ready. Guest-order event key: '.$guestSecretState.'.':'Configure writable secret storage outside the web root (PERFUME_SECRET_DIR) with OpenSSL available before launch.'],
            ['label'=>'Egypt delivery coverage','ok'=>$shippingCoverageReady,'blocking'=>!$shippingCoverageReady,'value'=>$shippingCoverageReady?'READY':'PRODUCTION BLOCKER','note'=>$shippingCoverageNote],
            ['label'=>'Recovery storage','ok'=>$backupWritable,'value'=>$backupWritable?'WRITABLE':'NOT WRITABLE','note'=>$backupDir],
            ['label'=>'Application logs','ok'=>$logWritable,'value'=>$logWritable?'WRITABLE':'NOT WRITABLE','note'=>$logDir],
            ['label'=>'Storefront availability','ok'=>empty($maintenance['enabled']),'value'=>empty($maintenance['enabled'])?'OPEN':'MAINTENANCE','note'=>'Normal customer access state.'],
            ['label'=>'Canonical public URL','ok'=>$canonicalOk,'blocking'=>$canonicalBlocking,'value'=>$localRequest?'LOCAL DEV':($canonicalOk?'HTTPS READY':'PRODUCTION BLOCKER'),'note'=>$canonicalBlocking?'Set PERFUME_CANONICAL_SITE_URL to the real public HTTPS origin before launch. Current: '.(string)CANONICAL_SITE_URL:(string)CANONICAL_SITE_URL],
            ['label'=>'Production product photography','ok'=>$fallbackMediaCount===0,'blocking'=>$fallbackMediaCount>0,'value'=>$fallbackMediaCount===0?'READY':'PRODUCTION BLOCKER','note'=>$fallbackMediaCount===0?'Active products are not using packaged fallback artwork.':$fallbackMediaCount.' active product'.($fallbackMediaCount===1?' is':'s are').' still using packaged fallback artwork. Replace it with authorized production photography before launch.'],
        ];
        $productionBlockers=array_values(array_filter($items,static fn(array $item): bool=>!empty($item['blocking'])&&!$item['ok']));
        return compact('items','productionBlockers','missing','dbOk','storeReady','activeSessions','twoFactorReady','emailReady','analyticsReady','supportReady','backupDir','backupWritable','logDir','logWritable','maintenance','schemaVersion','packagedSchemaVersion','databaseUpdatesCurrent','schedulerMode','fallbackMediaCount','secretStorage','protectedSecretsReady','shippingCoverageReady','shippingCoverageNote');
    }
}
