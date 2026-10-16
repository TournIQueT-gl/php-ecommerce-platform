<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DomainEventRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\SchedulerStateRepository;
use App\Repositories\WebSessionRepository;
use App\Repositories\LoginSecurityRepository;
use App\Repositories\PasswordRecoveryRepository;
use App\Repositories\SupportRepository;
use App\Repositories\AnnouncementRepository;
use App\Repositories\VisitorAnalyticsRepository;
use Throwable;

/** Lightweight store maintenance scheduler. */
final class FoundationScheduler
{
    public function __construct(
        private SchedulerStateRepository $state,
        private EventProcessor $events,
        private NotificationRepository $notifications,
        private DomainEventRepository $eventRepository,
        private ?WebSessionRepository $webSessions = null,
        private ?LoginSecurityRepository $loginSecurity = null,
        private ?PasswordRecoveryRepository $recovery = null,
        private ?EmailService $email = null,
        private ?SupportRepository $support = null,
        private ?AnnouncementRepository $announcements = null,
        private ?VisitorAnalyticsRepository $visitorAnalytics = null,
        private ?IpIntelligenceService $ipIntelligence = null
    ) {}

    public function runDue(int $eventBatchSize = FOUNDATION_EVENT_BATCH_SIZE,int $emailBatchSize = 20): array
    {
        $eventBatchSize=max(1,min(FOUNDATION_EVENT_BATCH_SIZE,$eventBatchSize));
        $emailBatchSize=max(1,min(20,$emailBatchSize));
        $tasks=[
            'full_scheduler_heartbeat'=>[60,fn()=>['mode'=>PHP_SAPI],'full_scheduler_heartbeat'],
            'process_domain_events_cli'=>[60,fn()=> $this->events->processPending($eventBatchSize),'domain_event_worker'],
            'publish_due_announcements'=>[60,fn()=> $this->publishDueAnnouncements(30),'announcement_worker'],
            'process_email_queue'=>[60,fn()=> $this->email?->processPending($emailBatchSize) ?? ['status'=>'disabled'],'email_queue_worker'],
            'cleanup_store_data'=>[86400,fn()=> $this->cleanupData(false),'cleanup_worker'],
        ];
        if($this->visitorAnalytics!==null && $this->ipIntelligence!==null){
            $tasks['resolve_visitor_ip_intelligence']=[60,fn()=> $this->resolveVisitorIpIntelligence(IP_INTELLIGENCE_BACKGROUND_BATCH,4),'visitor_intelligence_worker'];
        }
        return $this->runTasks($tasks,null,false);
    }

    public function runWebFallback(int $eventBatchSize = FOUNDATION_WEB_EVENT_BATCH_SIZE,bool $responseDetached=false): array
    {
        $mode=\foundation_scheduler_mode();
        if(!FOUNDATION_WEB_SCHEDULER_ENABLED||$mode==='cli')return ['web_scheduler'=>['status'=>'disabled_cli_mode']];
        if($mode==='auto'&&$this->fullSchedulerHealthy())return ['web_scheduler'=>['status'=>'standby_cli_healthy']];
        $eventBatchSize=max(1,min(10,$eventBatchSize));
        $emailBatch=max(1,min(3,(int)FOUNDATION_WEB_EMAIL_BATCH_SIZE));
        $ipBatch=max(1,min(3,(int)FOUNDATION_WEB_IP_BATCH_SIZE));
        $budget=$responseDetached?max(1000,(int)FOUNDATION_WEB_TIME_BUDGET_MS):1900;
        $remoteTimeout=$responseDetached?4:2;
        $tasks=[
            'web_scheduler_heartbeat'=>[FOUNDATION_WEB_SCHEDULER_INTERVAL_SECONDS,fn()=>['mode'=>'web','detached'=>$responseDetached],'web_scheduler_heartbeat'],
            'process_domain_events_web'=>[60,fn()=> $this->events->processPendingWebSafe($eventBatchSize),'domain_event_worker'],
            'publish_due_announcements_web'=>[60,fn()=> $this->publishDueAnnouncements(10),'announcement_worker'],
            'process_email_queue_web'=>[FOUNDATION_WEB_EMAIL_INTERVAL_SECONDS,fn()=> $this->email?->processPending($emailBatch,$remoteTimeout) ?? ['status'=>'disabled'],'email_queue_worker'],
            'resolve_visitor_ip_intelligence_web'=>[FOUNDATION_WEB_IP_INTERVAL_SECONDS,fn()=> $this->resolveVisitorIpIntelligence($ipBatch,$remoteTimeout),'visitor_intelligence_worker'],
            'cleanup_store_data_web'=>[FOUNDATION_WEB_CLEANUP_INTERVAL_SECONDS,fn()=> $this->cleanupData(true),'cleanup_worker'],
        ];
        return $this->runTasks($tasks,$budget,false);
    }

    public function runManualMaintenance(): array
    {
        $tasks=[
            'process_domain_events_web'=>[1,fn()=> $this->events->processPending(10),'domain_event_worker'],
            'publish_due_announcements_web'=>[1,fn()=> $this->publishDueAnnouncements(30),'announcement_worker'],
            'process_email_queue_web'=>[1,fn()=> $this->email?->processPending(5,4) ?? ['status'=>'disabled'],'email_queue_worker'],
            'resolve_visitor_ip_intelligence_web'=>[1,fn()=> $this->resolveVisitorIpIntelligence(5,3),'visitor_intelligence_worker'],
            'cleanup_store_data_web'=>[1,fn()=> $this->cleanupData(false),'cleanup_worker'],
            'web_scheduler_heartbeat'=>[1,fn()=>['mode'=>'manual-web'],'web_scheduler_heartbeat'],
        ];
        return $this->runTasks($tasks,null,true);
    }

    public function modeStatus(): array
    {
        $configured=\foundation_scheduler_mode();$cliHealthy=$this->fullSchedulerHealthy();
        $effective=$configured==='cli'?'cli':($configured==='web'?'web':($cliHealthy?'cli':'web'));
        return ['configured'=>$configured,'effective'=>$effective,'cli_healthy'=>$cliHealthy,'web_enabled'=>(bool)FOUNDATION_WEB_SCHEDULER_ENABLED];
    }

    public function fullSchedulerHealthy(): bool
    {
        try{$row=$this->state->state('full_scheduler_heartbeat');if(!$row||strtolower((string)($row['last_status']??''))!=='ok')return false;$finished=trim((string)($row['last_finished_at']??''));if($finished==='')return false;$epoch=strtotime($finished.' UTC');return $epoch!==false&&(time()-$epoch)<=FOUNDATION_FULL_SCHEDULER_STALE_SECONDS;}catch(Throwable){return false;}
    }

    private function runTasks(array $tasks,?int $budgetMs=null,bool $force=false): array
    {
        $results=[];$loopStarted=microtime(true);
        foreach($tasks as $key=>$definition){
            if($budgetMs!==null&&((microtime(true)-$loopStarted)*1000)>=$budgetMs){$results[$key]=['status'=>'budget_exhausted'];continue;}
            [$interval,$runner]=$definition;$lockKey=(string)($definition[2]??$key);
            if(!$this->state->acquire($lockKey)){$results[$key]=['status'=>'locked'];continue;}
            try{
                if(!$force&&!$this->state->isDue($key,(int)$interval)){$results[$key]=['status'=>'not_due'];continue;}
                $started=microtime(true);$this->state->markStarted($key);
                try{$detail=$runner();$duration=(int)round((microtime(true)-$started)*1000);$this->state->markFinished($key,$duration);$results[$key]=['status'=>'ok','duration_ms'=>$duration,'detail'=>$detail];}
                catch(Throwable $e){$duration=(int)round((microtime(true)-$started)*1000);$this->state->markFailed($key,$e->getMessage(),$duration);$results[$key]=['status'=>'failed','duration_ms'=>$duration,'error'=>$e->getMessage()];}
            }finally{$this->state->release($lockKey);}
        }
        return $results;
    }

    private function publishDueAnnouncements(int $limit=30): array
    {
        if($this->announcements===null)return ['status'=>'disabled','processed'=>0,'notifications'=>0,'emails'=>0];
        $processed=0;$notifications=0;$emails=0;
        foreach($this->announcements->dueForNotification($limit) as $row){
            $id=(int)($row['id']??0);if($id<=0)continue;
            $audience=(string)($row['audience']??'all');if(!in_array($audience,['all','customers','admins'],true))$audience='all';
            $title=trim((string)($row['title']??'Store announcement'));$body=trim((string)($row['message']??''));$type=(string)($row['type']??'info');
            $dedupe='announcement:'.$id;
            $notifications += $this->notifications->broadcastToAudience($audience,'announcement.published',$title,$body,['announcement_id'=>$id,'announcement_type'=>$type],$dedupe);
            if($this->email!==null){$cta=rtrim((string)CANONICAL_SITE_URL,'/').\route_url('dashboard.php').'#announcements';$emails += $this->email->queueAudience($audience,'announcements',$title,$body,'OPEN ACCOUNT',$cta,$dedupe);}
            $this->announcements->markNotificationSent($id);$processed++;
        }
        return compact('processed','notifications','emails');
    }

    private function resolveVisitorIpIntelligence(int $limit=5,int $timeoutSeconds=4): array
    {
        if($this->visitorAnalytics===null||$this->ipIntelligence===null||!$this->visitorAnalytics->intelligenceLifecycleReady())return ['status'=>'disabled'];
        $resolved=0;$failed=0;$private=0;$processed=0;
        foreach($this->visitorAnalytics->ipIntelligenceCandidates($limit) as $ip){$processed++;try{$result=$this->ipIntelligence->resolve($ip,true,$timeoutSeconds);if(!empty($result['ok'])){$this->visitorAnalytics->saveIpIntelligence($result);$resolved++;continue;}$kind=(string)($result['kind']??'error');$permanent=in_array($kind,['private','invalid'],true);$this->visitorAnalytics->markIpIntelligenceFailure($ip,(string)($result['error']??'Lookup failed.'),$permanent);if($permanent)$private++;else$failed++;}catch(Throwable $e){$this->visitorAnalytics->markIpIntelligenceFailure($ip,$e->getMessage(),false);$failed++;}}
        return compact('processed','resolved','failed','private');
    }

    private function cleanupData(bool $web): array
    {
        return [
            'notifications_deleted'=>$this->notifications->pruneRead(FOUNDATION_NOTIFICATION_RETENTION_DAYS),
            'events_deleted'=>$this->eventRepository->pruneProcessed(FOUNDATION_EVENT_RETENTION_DAYS),
            'web_sessions_deleted'=>(($this->webSessions!==null&&$this->webSessions->schemaReady())?$this->webSessions->prune(90):0),
            'login_events_deleted'=>$this->safePruneLogin(),
            'recovery_requests_deleted'=>$this->safePruneRecovery(),
            'email_records_deleted'=>$this->safePruneEmail(),
            'support_attachments_deleted'=>$web?0:$this->safePruneSupportAttachments(),
            'visitor_sessions_reclassified'=>$this->safeReclassifyVisitorAnalytics(),
            'visitor_sessions_deleted'=>$this->safePruneVisitorAnalytics(),
            'rate_limit_files_deleted'=>function_exists('rate_limit_prune_files')?\rate_limit_prune_files(172800):0,
        ];
    }
    private function safePruneLogin(): int{if($this->loginSecurity===null)return 0;try{return $this->loginSecurity->prune(180);}catch(Throwable){return 0;}}
    private function safePruneRecovery(): int{if($this->recovery===null)return 0;try{return $this->recovery->prune(90);}catch(Throwable){return 0;}}
    private function safePruneEmail(): int{if($this->email===null||!$this->email->schemaReady())return 0;try{return $this->email->prune(90);}catch(Throwable){return 0;}}
    private function safePruneSupportAttachments(): int{if($this->support===null)return 0;$deleted=0;try{foreach($this->support->expiredAttachmentRows(SUPPORT_ATTACHMENT_RETENTION_DAYS,200) as $row){$stored=basename((string)($row['attachment_stored_name']??''));if($stored!==''&&\private_storage_delete('support',$stored)){$this->support->clearAttachmentMetadata((int)$row['id']);$deleted++;}}}catch(Throwable){}return $deleted;}
    private function safeReclassifyVisitorAnalytics(): int{if($this->visitorAnalytics===null||!$this->visitorAnalytics->schemaReady())return 0;try{return $this->visitorAnalytics->reclassifyInvalidGuestSessions(\visitor_analytics_real_page_paths());}catch(Throwable){return 0;}}
    private function safePruneVisitorAnalytics(): int{if($this->visitorAnalytics===null||!$this->visitorAnalytics->schemaReady())return 0;try{$settings=\analytics_settings();return $this->visitorAnalytics->prune((int)($settings['retention_days']??90));}catch(Throwable){return 0;}}
}
