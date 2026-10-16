<?php
declare(strict_types=1);

namespace App\Services;

use App\Events\DomainEvent;
use App\Events\EventDispatcher;
use App\Repositories\AccountActivityRepository;
use App\Repositories\LoginSecurityRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PasswordRecoveryRepository;
use App\Repositories\UserRepository;
use App\Repositories\WebSessionRepository;
use App\Support\Validation;
use PDO;
use Throwable;

final class PasswordRecoveryService
{
    public function __construct(
        private PDO $db,
        private UserRepository $users,
        private PasswordRecoveryRepository $requests,
        private WebSessionRepository $webSessions,
        private AccountActivityRepository $activity,
        private LoginSecurityRepository $loginSecurity,
        private ?EventDispatcher $events=null,
        private ?NotificationRepository $notifications=null,
        private ?EmailService $email=null
    ) {}

    public function request(string $identity,string $ip,string $userAgent): array
    {
        $identity=trim($identity);
        $decoyToken=bin2hex(random_bytes(32));
        if($identity===''||strlen($identity)>254){
            return ['token'=>$decoyToken,'request_id'=>null,'email_sent'=>false];
        }
        $user=$this->users->usernameOrEmail($identity);
        if(!$user || empty($user['is_active'])) return ['token'=>$decoyToken,'request_id'=>null,'email_sent'=>false];
        $userId=(int)$user['id'];

        // Verified-email recovery gets a separate one-time token that is never
        // returned to the browser. If queueing fails, fall back to the existing
        // administrator-approval flow instead of stranding the user.
        if($this->email!==null && $this->email->deliveryEnabled() && !empty($user['email']) && !empty($user['email_verified_at'])){
            $emailToken=bin2hex(random_bytes(32));
            $requestId=$this->requests->createApprovedByEmail($userId,hash('sha256',$emailToken),$ip,$userAgent,30);
            try{
                if($this->email->queuePasswordReset($userId,$emailToken,$requestId)){
                    try{$this->activity->add($userId,'password_recovery_emailed','Password recovery email queued','One-time reset link queued for the verified email address.',$ip);}catch(Throwable){}
                    return ['token'=>$decoyToken,'request_id'=>$requestId,'email_sent'=>true];
                }
            }catch(Throwable $e){
                \App\Support\Logger::warning('Password recovery email queue failed',['user_id'=>$userId,'message'=>$e->getMessage()]);
            }
            $this->requests->cancel($requestId,$userId);
        }

        $token=bin2hex(random_bytes(32));
        $id=$this->requests->create($userId,hash('sha256',$token),$ip,$userAgent,24);
        try{$this->activity->add($userId,'password_recovery_requested','Password recovery requested','Pending administrator approval',$ip);}catch(Throwable){}
        try{
            $eventId=$this->events?->dispatch(new DomainEvent('security.password_recovery_requested',$userId,null,['request_id'=>$id,'user_id'=>$userId,'username'=>(string)$user['username']]));
            if($eventId && $this->notifications!==null)$this->notifications->broadcastToAdminRoles(['owner','admin'],'security.password_recovery_requested','Password recovery requested',''.(string)$user['username'].' requested password recovery. Review the request before approving it.',['request_id'=>$id,'user_id'=>$userId,'username'=>(string)$user['username'],'event_id'=>$eventId],'event:'.$eventId);
        }catch(Throwable){}
        return ['token'=>$token,'request_id'=>$id,'email_sent'=>false];
    }

    public function inspect(string $token): ?array
    {
        $token=trim($token);
        if(!preg_match('/^[a-f0-9]{64}$/i',$token))return null;
        $row=$this->requests->findByHash(hash('sha256',strtolower($token)));
        if(!$row)return null;
        $now=time();
        $status=(string)$row['status'];
        if($status==='pending' && strtotime((string)$row['expires_at'].' UTC')<=$now)$status='expired';
        if($status==='approved' && (!empty($row['reset_expires_at']) && strtotime((string)$row['reset_expires_at'].' UTC')<=$now))$status='expired';
        $row['effective_status']=$status;
        return $row;
    }

    public function approve(int $requestId,int $userId,int $adminId): array
    {
        $ok=$this->requests->approve($requestId,$userId,$adminId,30);
        if($ok){
            try{
                $eventId=$this->events?->dispatch(new DomainEvent('security.password_recovery_approved',$userId,$adminId,['request_id'=>$requestId]));
                if($eventId && $this->notifications!==null)$this->notifications->create($userId,'security.password_recovery_approved','Password recovery approved','Your recovery request was verified. An administrator must provide you with the fresh one-time reset link generated for this recovery.',['request_id'=>$requestId,'event_id'=>$eventId],'event:'.$eventId);
            }catch(Throwable){}
        }
        return ['ok'=>$ok,'message'=>$ok?'Recovery request verified. Generate and share a fresh one-time reset link with the user.':'Recovery request is no longer pending or has expired.'];
    }

    public function cancel(int $requestId,int $userId): array
    {
        $ok=$this->requests->cancel($requestId,$userId);
        if($ok){
            try{
                $eventId=$this->events?->dispatch(new DomainEvent('security.password_recovery_cancelled',$userId,null,['request_id'=>$requestId]));
                if($eventId && $this->notifications!==null)$this->notifications->create($userId,'security.password_recovery_cancelled','Password recovery cancelled','Your password recovery request was cancelled.',['request_id'=>$requestId,'event_id'=>$eventId],'event:'.$eventId);
            }catch(Throwable){}
        }
        return ['ok'=>$ok,'message'=>$ok?'Recovery request cancelled.':'Recovery request is no longer active.'];
    }

    public function createAdminReset(int $userId,int $adminId,string $ip,string $userAgent): array
    {
        $token=bin2hex(random_bytes(32));
        $id=$this->requests->createApproved($userId,hash('sha256',$token),$adminId,$ip,$userAgent,30);
        return ['ok'=>true,'message'=>'One-time password reset link created. It expires in 30 minutes.','token'=>$token,'request_id'=>$id];
    }

    public function reset(string $token,string $newPassword,string $confirmPassword,string $ip,string $userAgent): array
    {
        if(strlen($newPassword)<8||strlen($newPassword)>256)return ['ok'=>false,'message'=>'New password must be 8-256 characters.'];
        if($newPassword!==$confirmPassword)return ['ok'=>false,'message'=>'New passwords do not match.'];
        $request=$this->inspect($token);
        if(!$request || ($request['effective_status']??'')!=='approved' || empty($request['is_active'])){
            return ['ok'=>false,'message'=>'This recovery request is not approved, has expired, or has already been used.'];
        }
        $userId=(int)$request['user_id'];
        $currentHash=$this->users->passwordHash($userId);
        if($currentHash!=='' && password_verify($newPassword,$currentHash))return ['ok'=>false,'message'=>'Choose a password different from the current password.'];

        $this->db->beginTransaction();
        try{
            $this->users->updatePasswordHash($userId,password_hash($newPassword,PASSWORD_DEFAULT));
            $freshHash=$this->users->passwordHash($userId);
            if($freshHash==='' || !password_verify($newPassword,$freshHash) || hash_equals($currentHash,$freshHash)){
                throw new \RuntimeException('Password reset verification failed.');
            }
            if(!$this->requests->markUsed((int)$request['id'])){
                $this->db->rollBack();
                return ['ok'=>false,'message'=>'This recovery request is no longer valid.'];
            }
            $this->requests->cancelPendingForUser($userId,(int)$request['id']);
            $this->webSessions->revokeAll($userId,'password_reset');
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}

        try{$this->activity->add($userId,'password_reset','Password reset completed','All website sessions were invalidated.',$ip);}catch(Throwable){}
        try{$this->loginSecurity->record($userId,(string)$request['username'],true,'password_reset',$ip,$userAgent,'Password reset completed');}catch(Throwable){}
        try{
            $eventId=$this->events?->dispatch(new DomainEvent('security.password_reset',$userId,null,['request_id'=>(int)$request['id']]));
            if($eventId && $this->notifications!==null)$this->notifications->create($userId,'security.password_reset','Password reset completed','Your password was reset. All previous browser sessions were closed.',['request_id'=>(int)$request['id'],'event_id'=>$eventId],'event:'.$eventId);
        }catch(Throwable){}
        return ['ok'=>true,'message'=>'Password reset complete. Sign in with your new password.'];
    }
}
