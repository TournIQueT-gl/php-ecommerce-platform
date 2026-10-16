<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WebSessionRepository
{
    public function __construct(private PDO $db) {}

    public static function hash(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    public function schemaReady(): bool
    {
        try {
            return $this->schemaInstalled();
        } catch (\Throwable) {
            return false;
        }
    }

    public function schemaInstalled(): bool
    {
        $stmt=$this->db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='web_sessions'");
        return (int)$stmt->fetchColumn() === 1;
    }

    public function ensureCurrent(int $userId, string $sessionId, string $ip, string $userAgent): bool
    {
        if ($userId <= 0 || $sessionId === '') return true;
        $hash=self::hash($sessionId);
        $stmt=$this->db->prepare('SELECT id,user_id,revoked_at,last_seen_at,ip_address,user_agent FROM web_sessions WHERE session_hash=? LIMIT 1');
        $stmt->execute([$hash]);
        $row=$stmt->fetch();
        if (is_array($row)) {
            if ((int)$row['user_id']!==$userId || !empty($row['revoked_at'])) return false;
            $safeIp=substr($ip,0,64);$safeUa=substr($userAgent,0,500);
            $lastSeen=strtotime((string)($row['last_seen_at']??'').' UTC')?:0;
            $identityChanged=!hash_equals((string)($row['ip_address']??''),$safeIp)
                || !hash_equals((string)($row['user_agent']??''),$safeUa);
            if($identityChanged || $lastSeen<=time()-60){
                $this->db->prepare('UPDATE web_sessions SET last_seen_at=UTC_TIMESTAMP(),ip_address=?,user_agent=? WHERE id=?')
                    ->execute([$safeIp,$safeUa,(int)$row['id']]);
            }
            return true;
        }
        $insert=$this->db->prepare('INSERT INTO web_sessions(user_id,session_hash,ip_address,user_agent,created_at,last_seen_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $insert->execute([$userId,$hash,substr($ip,0,64),substr($userAgent,0,500)]);
        return true;
    }

    public function register(int $userId, string $sessionId, string $ip, string $userAgent): void
    {
        if ($userId <= 0 || $sessionId === '') return;
        $hash=self::hash($sessionId);
        $stmt=$this->db->prepare('INSERT INTO web_sessions(user_id,session_hash,ip_address,user_agent,created_at,last_seen_at,revoked_at,revoked_reason) VALUES(?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),NULL,NULL) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),ip_address=VALUES(ip_address),user_agent=VALUES(user_agent),last_seen_at=UTC_TIMESTAMP(),revoked_at=NULL,revoked_reason=NULL');
        $stmt->execute([$userId,$hash,substr($ip,0,64),substr($userAgent,0,500)]);
    }

    public function listForUser(int $userId, int $limit=30): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=$this->db->prepare('SELECT id,user_id,session_hash,ip_address,user_agent,created_at,last_seen_at,revoked_at,revoked_reason FROM web_sessions WHERE user_id=? ORDER BY (revoked_at IS NULL) DESC,last_seen_at DESC,id DESC LIMIT '.$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function revokeForUser(int $userId, int $sessionRowId, string $reason='revoked'): bool
    {
        $stmt=$this->db->prepare('UPDATE web_sessions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),revoked_reason=COALESCE(revoked_reason,?) WHERE id=? AND user_id=? AND revoked_at IS NULL');
        $stmt->execute([substr($reason,0,160),$sessionRowId,$userId]);
        return $stmt->rowCount()>0;
    }

    public function revokeCurrent(int $userId, string $sessionId, string $reason='logout'): bool
    {
        if ($sessionId==='') return false;
        $stmt=$this->db->prepare('UPDATE web_sessions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),revoked_reason=COALESCE(revoked_reason,?) WHERE user_id=? AND session_hash=? AND revoked_at IS NULL');
        $stmt->execute([substr($reason,0,160),$userId,self::hash($sessionId)]);
        return $stmt->rowCount()>0;
    }

    public function revokeOthers(int $userId, string $currentSessionId, string $reason='security_action'): int
    {
        $currentHash=$currentSessionId!==''?self::hash($currentSessionId):'';
        if ($currentHash==='') return $this->revokeAll($userId,$reason);
        $stmt=$this->db->prepare('UPDATE web_sessions SET revoked_at=UTC_TIMESTAMP(),revoked_reason=? WHERE user_id=? AND revoked_at IS NULL AND session_hash<>?');
        $stmt->execute([substr($reason,0,160),$userId,$currentHash]);
        return $stmt->rowCount();
    }

    public function revokeAll(int $userId, string $reason='security_action'): int
    {
        $stmt=$this->db->prepare('UPDATE web_sessions SET revoked_at=UTC_TIMESTAMP(),revoked_reason=? WHERE user_id=? AND revoked_at IS NULL');
        $stmt->execute([substr($reason,0,160),$userId]);
        return $stmt->rowCount();
    }

    public function prune(int $days=90): int
    {
        $days=max(7,min(3650,$days));
        return (int)$this->db->exec('DELETE FROM web_sessions WHERE revoked_at IS NOT NULL AND revoked_at < (UTC_TIMESTAMP() - INTERVAL '.$days.' DAY)');
    }
}
