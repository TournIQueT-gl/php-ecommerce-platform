<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DomainEventRepository;
use App\Repositories\NotificationRepository;
use App\Support\Logger;
use App\Support\SecretStore;
use Throwable;

final class EventProcessor
{
    public function __construct(private DomainEventRepository $events, private NotificationRepository $notifications, private ?EmailService $email = null) {}

    public function processPending(int $limit = 100): array { return $this->processRows($this->events->pending($limit)); }
    public function processPendingWebSafe(int $limit = 25): array { return $this->processRows($this->events->pendingWebSafe($limit)); }

    private function processRows(array $rows): array
    {
        $processed=0;$failed=0;$notified=0;
        foreach($rows as $row){
            $id=(int)$row['id'];
            try{
                $payload=json_decode((string)($row['payload_json']??'{}'),true,32,JSON_THROW_ON_ERROR);
                if(!is_array($payload))$payload=[];
                $protected=trim((string)($payload['guest_access_token_protected']??''));if($protected!==''){$payload['guest_access_token']=SecretStore::decrypt($protected,'guest-order-event');unset($payload['guest_access_token_protected']);}
                $eventKey=(string)$row['event_key'];
                $template=$this->notificationTemplate($eventKey,$payload);
                $userId=(int)($row['subject_user_id']??0);
                if($template!==null && in_array($eventKey,['support.user_replied','support.ticket_created'],true)){
                    $notified += $this->notifications->broadcastToAdminRoles(['owner','admin','support'],$eventKey,$template['title'],$template['body'],array_merge($payload,['event_id'=>$id]),'event:'.$id);
                } elseif($template!==null && $eventKey==='order.created'){
                    $notificationPayload=$payload;unset($notificationPayload['guest_access_token']);
                    $adminPayload=$notificationPayload;$adminPayload['admin_target']=true;
                    $orderId=(int)($payload['order_id']??0);
                    $adminDedupe=$orderId>0?'order-created-admin:'.$orderId:'event:'.$id;
                    $notified += $this->notifications->broadcastToAdminRoles(['owner','admin'],$eventKey,'New store order','Order '.(string)($payload['order_number']??('#'.$orderId)).' was placed and needs processing.',array_merge($adminPayload,['event_id'=>$id]),$adminDedupe);
                    if($userId>0){
                        $this->notifications->create($userId,$eventKey,'Order received','We received order '.(string)($payload['order_number']??('#'.(int)($payload['order_id']??0))).' and will keep you updated here.',array_merge($notificationPayload,['event_id'=>$id]),'event:'.$id);
                        $notified++;
                    }
                } elseif($template!==null && $eventKey==='return.requested'){
                    $notified += $this->notifications->broadcastToAdminRoles(['owner','admin'],$eventKey,$template['title'],$template['body'],array_merge($payload,['event_id'=>$id]),'event:'.$id);
                    if($userId>0){$this->notifications->create($userId,$eventKey,'Return request received','Your return request #'.(int)($payload['return_id']??0).' is waiting for review.',array_merge($payload,['event_id'=>$id]),'event:'.$id.':customer');$notified++;}
                } elseif($template!==null && $eventKey==='security.password_recovery_requested'){
                    $notified += $this->notifications->broadcastToAdminRoles(['owner','admin'],$eventKey,$template['title'],$template['body'],array_merge($payload,['event_id'=>$id]),'event:'.$id);
                } elseif($template!==null && $userId>0){
                    $this->notifications->create($userId,$eventKey,$template['title'],$template['body'],array_merge($payload,['event_id'=>$id]),'event:'.$id);
                    $notified++;
                }
                if($this->email!==null)$this->email->emailEvent($eventKey,$userId>0?$userId:null,$payload,$id);
                $this->events->markProcessed($id);$processed++;
            }catch(Throwable $e){
                $this->events->markFailed($id,$e->getMessage());$failed++;
                Logger::warning('Domain event processing failed',['event_id'=>$id,'event_key'=>$row['event_key']??'unknown','exception'=>get_class($e),'message'=>$e->getMessage()]);
            }
        }
        return compact('processed','failed','notified');
    }

    private function notificationTemplate(string $key,array $payload): ?array
    {
        return match($key){
            'support.admin_replied' => ['title'=>'Support replied','body'=>'Ticket #'.(int)($payload['ticket_id']??0).' has a new response.'],
            'support.user_replied' => ['title'=>'New support reply','body'=>'Ticket #'.(int)($payload['ticket_id']??0).' has a new customer response.'],
            'support.ticket_created' => ['title'=>'New support ticket','body'=>'Ticket #'.(int)($payload['ticket_id']??0).' · '.(string)($payload['subject']??'New support request').'.'],
            'order.created' => ['title'=>'New store order','body'=>'Order #'.(int)($payload['order_id']??0).' was placed and needs processing.'],
            'order.status_changed' => ['title'=>'Order updated','body'=>'Order #'.(int)($payload['order_id']??0).' is now '.str_replace('_',' ',(string)($payload['status']??'updated')).'.'],
            'order.shipped' => ['title'=>'Order shipped','body'=>'Order #'.(int)($payload['order_id']??0).' is on the way.'],
            'order.refunded' => ['title'=>'Order refunded','body'=>'A refund was recorded for order #'.(int)($payload['order_id']??0).'.'],
            'return.requested' => ['title'=>'Return requested','body'=>'Return #'.(int)($payload['return_id']??0).' for order '.(string)($payload['order_number']??('#'.(int)($payload['order_id']??0))).' needs review.'],
            'return.approved' => ['title'=>'Return approved','body'=>'Return #'.(int)($payload['return_id']??0).' was approved.'],
            'return.rejected' => ['title'=>'Return not approved','body'=>'Return #'.(int)($payload['return_id']??0).' was not approved.'],
            'return.completed' => ['title'=>'Return completed','body'=>'Return #'.(int)($payload['return_id']??0).' was completed.'],
            'account.disabled' => ['title'=>'Account disabled','body'=>'Your account was disabled by an administrator.'],
            'account.enabled' => ['title'=>'Account enabled','body'=>'Your account was enabled by an administrator.'],
            'security.password_changed' => ['title'=>'Password changed','body'=>'Your password was changed. Other browser sessions were closed.'],
            'security.password_reset' => ['title'=>'Password reset completed','body'=>'Your password was reset through the recovery flow. Previous browser sessions were closed.'],
            'security.password_recovery_approved' => ['title'=>'Password recovery approved','body'=>'Your password recovery request was approved for 30 minutes.'],
            'security.password_recovery_cancelled' => ['title'=>'Password recovery cancelled','body'=>'Your password recovery request was cancelled.'],
            'security.web_session_revoked' => ['title'=>'Browser session revoked','body'=>'A browser session was revoked from your account.'],
            'security.web_sessions_revoked' => ['title'=>'Browser sessions revoked','body'=>max(1,(int)($payload['count']??1)).' browser session(s) were revoked from your account.'],
            default => null,
        };
    }
}
