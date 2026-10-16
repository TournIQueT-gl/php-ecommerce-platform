<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PasswordRecoveryRepository
{
    public function __construct(private PDO $db) {}

    public function create(int $userId,string $requestHash,string $ip,string $userAgent,int $ttlHours=24): int
    {
        $ttlHours=max(1,min(72,$ttlHours));
        // A public request may replace an older pending request, but it must never
        // invalidate an administrator-approved reset link.
        $cancel=$this->db->prepare("UPDATE password_recovery_requests SET status='cancelled',cancelled_at=UTC_TIMESTAMP() WHERE user_id=? AND status='pending'");
        $cancel->execute([$userId]);
        $stmt=$this->db->prepare('INSERT INTO password_recovery_requests(user_id,request_hash,status,requested_ip,requested_user_agent,created_at,expires_at) VALUES(?,?,\'pending\',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL '.$ttlHours.' HOUR)');
        $stmt->execute([$userId,$requestHash,substr($ip,0,64),substr($userAgent,0,500)]);
        return (int)$this->db->lastInsertId();
    }

    public function createApproved(int $userId,string $requestHash,int $adminId,string $ip,string $userAgent,int $minutes=30): int
    {
        $minutes=max(5,min(120,$minutes));
        $this->cancelPendingForUser($userId);
        $stmt=$this->db->prepare('INSERT INTO password_recovery_requests(user_id,request_hash,status,requested_ip,requested_user_agent,created_at,expires_at,approved_by,approved_at,reset_expires_at) VALUES(?,?,\'approved\',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 24 HOUR,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL '.$minutes.' MINUTE)');
        $stmt->execute([$userId,$requestHash,substr($ip,0,64),substr($userAgent,0,500),$adminId]);
        return (int)$this->db->lastInsertId();
    }

    public function createApprovedByEmail(int $userId,string $requestHash,string $ip,string $userAgent,int $minutes=30): int
    {
        $minutes=max(5,min(120,$minutes));
        $this->cancelPendingForUser($userId);
        $stmt=$this->db->prepare('INSERT INTO password_recovery_requests(user_id,request_hash,status,requested_ip,requested_user_agent,created_at,expires_at,approved_by,approved_at,reset_expires_at) VALUES(?,?,\'approved\',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL 24 HOUR,NULL,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL '.$minutes.' MINUTE)');
        $stmt->execute([$userId,$requestHash,substr($ip,0,64),substr($userAgent,0,500)]);
        return (int)$this->db->lastInsertId();
    }

    public function findByHash(string $hash): ?array
    {
        $stmt=$this->db->prepare('SELECT r.*,u.username,u.is_active FROM password_recovery_requests r JOIN users u ON u.id=r.user_id WHERE r.request_hash=? LIMIT 1');
        $stmt->execute([$hash]);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function pendingForUser(int $userId,int $limit=10): array
    {
        $this->expireOld();
        $limit=max(1,min(50,$limit));
        $stmt=$this->db->prepare("SELECT r.*,a.username AS approved_by_username FROM password_recovery_requests r LEFT JOIN users a ON a.id=r.approved_by WHERE r.user_id=? ORDER BY r.id DESC LIMIT ".$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function approve(int $requestId,int $userId,int $adminId,int $minutes=30): bool
    {
        $minutes=max(5,min(120,$minutes));
        $stmt=$this->db->prepare("UPDATE password_recovery_requests SET status='approved',approved_by=?,approved_at=UTC_TIMESTAMP(),reset_expires_at=UTC_TIMESTAMP()+INTERVAL ".$minutes." MINUTE WHERE id=? AND user_id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()");
        $stmt->execute([$adminId,$requestId,$userId]);
        return $stmt->rowCount()>0;
    }

    public function cancel(int $requestId,int $userId): bool
    {
        $stmt=$this->db->prepare("UPDATE password_recovery_requests SET status='cancelled',cancelled_at=UTC_TIMESTAMP() WHERE id=? AND user_id=? AND status IN ('pending','approved')");
        $stmt->execute([$requestId,$userId]);
        return $stmt->rowCount()>0;
    }

    public function markUsed(int $requestId): bool
    {
        $stmt=$this->db->prepare("UPDATE password_recovery_requests SET status='used',used_at=UTC_TIMESTAMP() WHERE id=? AND status='approved' AND reset_expires_at>UTC_TIMESTAMP()");
        $stmt->execute([$requestId]);
        return $stmt->rowCount()>0;
    }

    public function cancelPendingForUser(int $userId,int $exceptId=0): int
    {
        $sql="UPDATE password_recovery_requests SET status='cancelled',cancelled_at=UTC_TIMESTAMP() WHERE user_id=? AND status IN ('pending','approved')";
        $params=[$userId];
        if($exceptId>0){$sql.=' AND id<>?';$params[]=$exceptId;}
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->rowCount();
    }

    public function expireOld(): int
    {
        $a=$this->db->exec("UPDATE password_recovery_requests SET status='expired' WHERE status='pending' AND expires_at<=UTC_TIMESTAMP()");
        $b=$this->db->exec("UPDATE password_recovery_requests SET status='expired' WHERE status='approved' AND reset_expires_at<=UTC_TIMESTAMP()");
        return (int)$a+(int)$b;
    }

    public function prune(int $days=90): int
    {
        $days=max(30,min(3650,$days));
        $this->expireOld();
        return (int)$this->db->exec("DELETE FROM password_recovery_requests WHERE status IN ('used','expired','cancelled') AND created_at < (UTC_TIMESTAMP() - INTERVAL ".$days." DAY)");
    }
}
