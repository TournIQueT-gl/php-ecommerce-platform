<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\WebSessionRepository;
use App\Repositories\NotificationRepository;
use App\Events\DomainEvent;
use App\Events\EventDispatcher;
use App\Support\Validation;
use PDO;
use Throwable;

final class AccountService
{
    public function __construct(
        private PDO $db,
        private UserRepository $users,
        private ?WebSessionRepository $webSessions = null,
        private ?EventDispatcher $events = null,
        private ?NotificationRepository $notifications = null
    ) {}

    public function logoutWebSession(int $userId,int $sessionRowId,string $currentSessionId): array
    {
        if($this->webSessions===null||!$this->webSessions->schemaReady())return ['ok'=>false,'message'=>'Website session management is temporarily unavailable.'];
        foreach($this->webSessions->listForUser($userId,100) as $row){
            if((int)$row['id']!==$sessionRowId)continue;
            $currentHash=$currentSessionId!==''?WebSessionRepository::hash($currentSessionId):'';
            if($currentHash!==''&&hash_equals((string)$row['session_hash'],$currentHash))return ['ok'=>false,'message'=>'Use Logout to close your current browser session.'];
            $count=$this->webSessions->revoke($userId,$sessionRowId,'user_logout');
            return ['ok'=>true,'count'=>$count,'message'=>$count>0?'Browser session logged out.':'That browser session was already closed.'];
        }
        return ['ok'=>false,'message'=>'Browser session not found.'];
    }

    public function logoutOtherWebSessions(int $userId,string $currentSessionId): array
    {
        if($this->webSessions===null||!$this->webSessions->schemaReady())return ['ok'=>false,'message'=>'Website session management is temporarily unavailable.'];
        $currentHash=$currentSessionId!==''?WebSessionRepository::hash($currentSessionId):'';
        $count=$this->webSessions->revokeAllExceptHash($userId,$currentHash,'user_logout_others');
        return ['ok'=>true,'count'=>$count,'message'=>$count>0?'Other browser sessions logged out.':'No other active browser sessions were found.'];
    }

    public function changePassword(int $userId,string $currentPassword,string $newPassword,string $confirmPassword): array
    {
        $passwordError=Validation::passwordChangeError($currentPassword,$newPassword,$confirmPassword);
        if($passwordError!==null)return ['ok'=>false,'message'=>$passwordError];
        $reauthIdentity='user:'.$userId.'|'.\client_ip();
        $retry=\web_rate_limit_retry('account_password_reauth',8,900,$reauthIdentity);
        if($retry>0)return ['ok'=>false,'message'=>'Too many password attempts. Try again later.'];
        $hash=$this->users->passwordHash($userId);
        if($hash===''||!password_verify($currentPassword,$hash)){
            \web_rate_limit_failure('account_password_reauth',8,900,$reauthIdentity);
            return ['ok'=>false,'message'=>'Current password is incorrect.'];
        }
        \web_rate_limit_clear('account_password_reauth',$reauthIdentity);
        if(password_verify($newPassword,$hash))return ['ok'=>false,'message'=>'Choose a new password different from your current password.'];

        $this->db->beginTransaction();
        try{
            $this->users->updatePasswordHash($userId,password_hash($newPassword,PASSWORD_DEFAULT));
            $freshHash=$this->users->passwordHash($userId);
            if($freshHash===''||!password_verify($newPassword,$freshHash)||password_verify($currentPassword,$freshHash))throw new \RuntimeException('Password update verification failed.');
            if($this->webSessions!==null&&$this->webSessions->schemaReady())$this->webSessions->revokeAll($userId,'password_changed');
            $this->db->commit();
            try{\App\Support\ServiceFactory::accountActivity($this->db)->add($userId,'password_changed','Password changed','All website sessions were invalidated.',\client_ip());}catch(\Throwable){}
            try{
                $eventId=$this->events?->dispatch(new DomainEvent('security.password_changed',$userId,$userId));
                if($eventId&&$this->notifications!==null)$this->notifications->create($userId,'security.password_changed','Password changed','Your website password was changed. All previous browser sessions were closed.',['event_id'=>$eventId],'event:'.$eventId);
            }catch(\Throwable){}
            return ['ok'=>true,'message'=>'Password changed. Sign in again with your new password.'];
        }catch(Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }
}
