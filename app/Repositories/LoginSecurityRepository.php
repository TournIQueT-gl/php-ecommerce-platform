<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LoginSecurityRepository
{
    public function __construct(private PDO $db) {}

    public function record(?int $userId,string $username,bool $success,string $stage,string $ip,string $userAgent,string $details=''): void
    {
        $stmt=$this->db->prepare('INSERT INTO login_security_events(user_id,username_snapshot,success,auth_stage,ip_address,user_agent,details,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())');
        $stmt->execute([$userId && $userId>0?$userId:null,substr(trim($username),0,64),$success?1:0,substr($stage,0,32),substr($ip,0,64),substr($userAgent,0,500),$details!==''?substr($details,0,500):null]);
    }

    public function forUser(int $userId,int $limit=30): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=$this->db->prepare('SELECT id,user_id,username_snapshot,success,auth_stage,ip_address,user_agent,details,created_at FROM login_security_events WHERE user_id=? ORDER BY id DESC LIMIT '.$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function prune(int $days=180): int
    {
        $days=max(30,min(3650,$days));
        return (int)$this->db->exec('DELETE FROM login_security_events WHERE created_at < (UTC_TIMESTAMP() - INTERVAL '.$days.' DAY)');
    }
}
