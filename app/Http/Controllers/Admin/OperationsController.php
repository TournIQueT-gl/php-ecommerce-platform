<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Services\MigrationRunner;
use App\Support\SecretStore;
use App\Support\ServiceFactory;

final class OperationsController
{
    private const FLASH_KEY='admin_operations';

    public function handle(): array
    {
        if(\admin_current_role()!=='owner')throw new \RuntimeException('Owner access is required for store operations.');
        $db=\db();
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            \verify_csrf();$action=(string)($_POST['action']??'');$message='';$error='';
            if(!\admin_require_password_from_post($error)){
                \web_post_redirect('admin/operations.php',self::FLASH_KEY,$message,$error);
            }
            if($action==='database_migrate'){
                try{$runner=new MigrationRunner($db,ServiceFactory::migrations($db),dirname(__DIR__,4).'/database/migrations');$result=$runner->applyPending();$message='Database updates complete. '.($result['applied']?'Applied: '.implode(', ',$result['applied']).'.':'No pending migrations.');\admin_audit('database_migrated',$message);}catch(\Throwable $e){$error=\web_safe_exception_message($e,'database_migrate','Database update failed. No further migrations were applied after the failing statement.');}
            }elseif($action==='secret_storage_migrate'){
                if(\admin_current_role()!=='owner')$error='Only the owner can migrate production encryption keys.';
                else try{$result=SecretStore::migrateLegacyKeys();$message='Persistent secret storage is ready outside the website deployment directory.';\admin_audit('secret_storage_migrated','Secret storage verified; copied '.count((array)($result['copied']??[])).' key(s).');}catch(\Throwable $e){$error=\web_safe_exception_message($e,'secret_storage_migrate','Secret-storage migration failed without replacing the existing keys.');}
            }elseif($action==='maintenance_update'){
                $enabled=((string)($_POST['maintenance_enabled']??''))==='1';$copy=trim((string)($_POST['maintenance_message']??''));
                if(strlen($copy)>500)$error='Maintenance message is too long.';elseif(!\maintenance_set($enabled,$copy))$error='Could not save maintenance settings. Check storage permissions.';else{$message=$enabled?'Maintenance mode enabled.':'Maintenance mode disabled.';\admin_audit('maintenance_changed',$message);}
            }elseif($action==='scheduler_run_now'){
                try{$result=ServiceFactory::scheduler($db)->runManualMaintenance();$message='Store maintenance completed. Queued events, email, visitor intelligence and cleanup were processed.';\admin_audit('scheduler_manual_run','Manual store maintenance executed: '.json_encode($result,JSON_UNESCAPED_SLASHES));}catch(\Throwable $e){$error=\web_safe_exception_message($e,'scheduler_run_now','Maintenance run failed. Review the application log.');}
            }elseif($action==='cleanup_frontend_assets'){
                // Legacy POST requests must fail closed: pre-v5 asset allowlists incorrectly
                // classify live alpha6 CSS/JS files as obsolete. Never delete via web admin.
                $error='Automatic asset deletion is disabled. Review release files against a verified deployment manifest before removing anything.';
                \admin_audit('frontend_cleanup_blocked','Blocked obsolete asset-cleanup request; files unchanged.');
            }elseif($action==='backup_create'){
                try{$created=\backup_create_snapshot(false);$message='Recovery snapshot created: '.$created['name'];\admin_audit('backup_created',$message);}catch(\Throwable $e){$error=\web_safe_exception_message($e,'backup_create','Backup failed.');}
            }elseif($action==='backup_delete'){
                $name=\backup_identifier((string)($_POST['backup_name']??''));if($name===''||!\backup_delete($name))$error='Backup could not be deleted.';else{$message='Backup deleted.';\admin_audit('backup_deleted','Deleted recovery snapshot '.$name);}
            }elseif($action==='backup_download'){
                $name=\backup_identifier((string)($_POST['backup_name']??''));$path=\backup_file_path($name);
                if(!$path)$error='Backup file not found.';else{\admin_audit('backup_downloaded','Downloaded recovery snapshot '.$name);$type=str_ends_with($name,'.zip')?'application/zip':'application/sql';header('Content-Type: '.$type);header('Content-Length: '.(string)filesize($path));header('Content-Disposition: attachment; filename="'.addcslashes($name,'"\\').'"');header('Cache-Control: private, no-store');if(session_status()===PHP_SESSION_ACTIVE)session_write_close();readfile($path);exit;}
            }else $error='Unknown operations action.';
            \web_post_redirect('admin/operations.php',self::FLASH_KEY,$message,$error);
        }

        $flash=\web_flash_take(self::FLASH_KEY);$maintenance=\maintenance_state();$backups=\backup_list();$backupDir=\backup_storage_dir();$backupWritable=is_dir($backupDir)&&is_writable($backupDir);$backupStorage=\backup_storage_status();$backupLatest=\backup_latest_status();$supportStorage=\private_storage_status('support');
        try{$schemaVersion=ServiceFactory::migrations($db)->currentVersion();}catch(\Throwable){$schemaVersion=null;}
        $migrationFiles=(array)glob(dirname(__DIR__,4).'/database/migrations/*.sql');sort($migrationFiles,SORT_STRING);$packagedSchemaVersion=$migrationFiles?(preg_replace('/\.sql$/','',basename((string)end($migrationFiles)))?:''):'';$databaseUpdatesCurrent=$packagedSchemaVersion!==''&&$schemaVersion!==null&&hash_equals($packagedSchemaVersion,$schemaVersion);
        try{$secretStorage=SecretStore::storageStatus();}catch(\Throwable $e){$secretStorage=['deployment_safe'=>false,'external_ready'=>false,'external_dir'=>null,'legacy_dir'=>dirname(__DIR__,4).'/storage/private','has_conflict'=>true,'purposes'=>[],'error'=>$e->getMessage()];}
        try{$schedulerMode=ServiceFactory::scheduler($db)->modeStatus();}catch(\Throwable){$schedulerMode=['configured'=>\foundation_scheduler_mode(),'effective'=>'web','cli_healthy'=>false,'web_enabled'=>FOUNDATION_WEB_SCHEDULER_ENABLED];}
        try{$eventBacklog=ServiceFactory::domainEvents($db)->backlogStats();}catch(\Throwable){$eventBacklog=['pending'=>0,'stalled'=>0];}
        try{$emailBacklog=ServiceFactory::email($db)->schemaReady()?ServiceFactory::email($db)->stats():['pending'=>0,'failed'=>0];}catch(\Throwable){$emailBacklog=['pending'=>0,'failed'=>0];}
        $obsoleteFrontendAssets=$this->obsoleteFrontendAssets();
        return ['message'=>$flash['message'],'error'=>$flash['error'],'maintenance'=>$maintenance,'backups'=>$backups,'backupDir'=>$backupDir,'backupWritable'=>$backupWritable,'backupStorage'=>$backupStorage,'backupLatest'=>$backupLatest,'supportStorage'=>$supportStorage,'schemaVersion'=>$schemaVersion,'packagedSchemaVersion'=>$packagedSchemaVersion,'databaseUpdatesCurrent'=>$databaseUpdatesCurrent,'secretStorage'=>$secretStorage,'schedulerMode'=>$schedulerMode,'eventBacklog'=>$eventBacklog,'emailBacklog'=>$emailBacklog,'obsoleteFrontendAssets'=>$obsoleteFrontendAssets];
    }

    /** Advisory only: known legacy paths that merit manual deployment review.
     * No generic unknown-file detection and no web-exposed deletion operation.
     */
    private function obsoleteFrontendAssets(): array
    {
        $root=dirname(__DIR__,4);
        $oldFiles=[
            'status.php','REAL-MEDIA-STAGING.txt','bin/seed-demo-catalog.php','bin/apply-real-media.php',
            'includes/auth_layout.php','assets/js/js-ready.js','app/Repositories/MaintenanceRepository.php',
            'app/Repositories/AdminNoteRepository.php','app/Support/Format.php','app/Support/ApiErrorHandler.php',
            'app/Security/AdminPolicy.php','app/Services/RealCatalogSeeder.php','app/Services/QaCatalogMediaService.php',
            'database/seed/real_catalog.json','REAL-QA-CATALOG.md','bin/clear-store-content.php',
            'database/README-COMMERCE-M1.txt','database/baseline/v1_9_5_schema.sql',
            'tests/api-sha256.json','ADMIN-AUDIT-v4.2.4.txt',
        ];
        $oldDirs=['database/demo','assets/images/store/demo','assets/images/store/sharp',
            'assets/images/store/concept','uploads/media/qa-catalog'];
        $candidates=[];
        foreach($oldFiles as $relative)if(is_file($root.'/'.$relative))$candidates[]=$relative;
        foreach($oldDirs as $relative)if(is_dir($root.'/'.$relative))$candidates[]=$relative.'/';
        sort($candidates,SORT_STRING);
        return $candidates;
    }
}
