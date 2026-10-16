<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

final class EmailRepository
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        try {
            $tables = ['mail_settings','email_preferences','email_verification_tokens','email_deliveries'];
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ($placeholders)");
            $stmt->execute($tables);
            if ((int)$stmt->fetchColumn() !== count($tables)) return false;
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name IN ('email','email_verified_at')");
            $stmt->execute();
            return (int)$stmt->fetchColumn() === 2;
        } catch (\Throwable) {
            return false;
        }
    }

    public function pendingEmailSchemaReady(): bool
    {
        try{
            $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name IN ('pending_email','pending_email_requested_at')");
            return (int)$stmt->fetchColumn()===2;
        }catch(\Throwable){return false;}
    }

    public function settings(): array
    {
        $defaults = [
            'id'=>1,'enabled'=>0,'smtp_host'=>'','smtp_port'=>587,'smtp_encryption'=>'tls',
            'smtp_username'=>null,'smtp_password_cipher'=>null,'from_email'=>'',
            'from_name'=>'Perfume Store','reply_to_email'=>null,'site_url'=>null,'updated_by'=>null,'updated_at'=>null,
        ];
        if (!$this->schemaReady()) return $defaults;
        $row = $this->db->query('SELECT * FROM mail_settings WHERE id=1 LIMIT 1')->fetch();
        return is_array($row) ? array_merge($defaults, $row) : $defaults;
    }

    public function saveSettings(array $settings, int $adminId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO mail_settings(id,enabled,smtp_host,smtp_port,smtp_encryption,smtp_username,smtp_password_cipher,from_email,from_name,reply_to_email,site_url,updated_by,updated_at)
             VALUES(1,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_username=VALUES(smtp_username),smtp_password_cipher=VALUES(smtp_password_cipher),from_email=VALUES(from_email),from_name=VALUES(from_name),reply_to_email=VALUES(reply_to_email),site_url=VALUES(site_url),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()'
        );
        $stmt->execute([
            !empty($settings['enabled'])?1:0,
            (string)$settings['smtp_host'],
            (int)$settings['smtp_port'],
            (string)$settings['smtp_encryption'],
            ($settings['smtp_username']??'')!==''?(string)$settings['smtp_username']:null,
            ($settings['smtp_password_cipher']??'')!==''?(string)$settings['smtp_password_cipher']:null,
            (string)$settings['from_email'],
            (string)$settings['from_name'],
            ($settings['reply_to_email']??'')!==''?(string)$settings['reply_to_email']:null,
            ($settings['site_url']??'')!==''?(string)$settings['site_url']:null,
            $adminId>0?$adminId:null,
        ]);
    }

    public function userEmail(int $userId): array
    {
        if (!$this->schemaReady()) return ['email'=>null,'email_verified_at'=>null,'pending_email'=>null,'pending_email_requested_at'=>null];
        $pending=$this->pendingEmailSchemaReady();
        $sql=$pending
            ? 'SELECT email,email_verified_at,pending_email,pending_email_requested_at FROM users WHERE id=? LIMIT 1'
            : 'SELECT email,email_verified_at,NULL AS pending_email,NULL AS pending_email_requested_at FROM users WHERE id=? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : ['email'=>null,'email_verified_at'=>null,'pending_email'=>null,'pending_email_requested_at'=>null];
    }

    public function userByEmail(string $email): ?array
    {
        if (!$this->schemaReady()) return null;
        $stmt = $this->db->prepare('SELECT id,username,email,email_verified_at,is_active FROM users WHERE email=? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function emailExists(string $email, int $exceptUserId=0): bool
    {
        if (!$this->schemaReady()) return false;
        $email=strtolower(trim($email));
        $pending=$this->pendingEmailSchemaReady();
        $sql=$pending?'SELECT id FROM users WHERE (email=? OR pending_email=?)':'SELECT id FROM users WHERE email=?';
        $params=$pending?[$email,$email]:[$email];
        if($exceptUserId>0){$sql.=' AND id<>?';$params[]=$exceptUserId;}
        $sql.=' LIMIT 1';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);
        return $stmt->fetchColumn()!==false;
    }

    public function setUserEmail(int $userId, ?string $email, bool $verified=false): void
    {
        $email=$email!==null?strtolower(trim($email)):null;
        $stmt=$this->db->prepare('UPDATE users SET email=?,email_verified_at=? WHERE id=?');
        $stmt->execute([$email,($email!==null&&$verified)?gmdate('Y-m-d H:i:s'):null,$userId]);
    }

    public function setPendingEmail(int $userId,string $email): void
    {
        if(!$this->pendingEmailSchemaReady())throw new \RuntimeException('Pending email schema is not installed.');
        $stmt=$this->db->prepare('UPDATE users SET pending_email=?,pending_email_requested_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([strtolower(trim($email)),$userId]);
    }

    public function clearPendingEmail(int $userId): void
    {
        if(!$this->pendingEmailSchemaReady())return;
        $this->db->prepare('UPDATE users SET pending_email=NULL,pending_email_requested_at=NULL WHERE id=?')->execute([$userId]);
    }

    public function promotePendingEmail(int $userId,string $email): bool
    {
        if(!$this->pendingEmailSchemaReady())return false;
        $email=strtolower(trim($email));
        $stmt=$this->db->prepare('UPDATE users SET email=?,email_verified_at=UTC_TIMESTAMP(),pending_email=NULL,pending_email_requested_at=NULL WHERE id=? AND pending_email=?');
        $stmt->execute([$email,$userId,$email]);
        return $stmt->rowCount()>0;
    }

    public function markEmailVerified(int $userId, string $email): bool
    {
        $stmt=$this->db->prepare('UPDATE users SET email_verified_at=UTC_TIMESTAMP() WHERE id=? AND email=?');
        $stmt->execute([$userId,strtolower(trim($email))]);
        return $stmt->rowCount()>0;
    }

    public function preferences(int $userId): array
    {
        $defaults=['support_enabled'=>1,'orders_enabled'=>1,'marketing_enabled'=>1,'maintenance_enabled'=>1,'announcements_enabled'=>1];
        if(!$this->schemaReady())return $defaults;
        $stmt=$this->db->prepare('SELECT support_enabled,orders_enabled,marketing_enabled,maintenance_enabled,announcements_enabled FROM email_preferences WHERE user_id=? LIMIT 1');
        $stmt->execute([$userId]);$row=$stmt->fetch();
        return is_array($row)?array_merge($defaults,$row):$defaults;
    }

    public function savePreferences(int $userId,array $prefs): void
    {
        $stmt=$this->db->prepare('INSERT INTO email_preferences(user_id,support_enabled,orders_enabled,marketing_enabled,maintenance_enabled,announcements_enabled) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE support_enabled=VALUES(support_enabled),orders_enabled=VALUES(orders_enabled),marketing_enabled=VALUES(marketing_enabled),maintenance_enabled=VALUES(maintenance_enabled),announcements_enabled=VALUES(announcements_enabled),updated_at=UTC_TIMESTAMP()');
        $stmt->execute([$userId,!empty($prefs['support'])?1:0,!empty($prefs['orders'])?1:0,!empty($prefs['marketing'])?1:0,!empty($prefs['maintenance'])?1:0,!empty($prefs['announcements'])?1:0]);
    }

    public function createVerificationToken(int $userId,string $email,string $tokenHash,int $ttlHours=24): int
    {
        $ttlHours=max(1,min(72,$ttlHours));
        $this->db->prepare('UPDATE email_verification_tokens SET cancelled_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL AND cancelled_at IS NULL')->execute([$userId]);
        $stmt=$this->db->prepare('INSERT INTO email_verification_tokens(user_id,email,token_hash,created_at,expires_at) VALUES(?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()+INTERVAL '.$ttlHours.' HOUR)');
        $stmt->execute([$userId,strtolower(trim($email)),$tokenHash]);
        return (int)$this->db->lastInsertId();
    }

    public function verificationByHash(string $hash): ?array
    {
        $pending=$this->pendingEmailSchemaReady();
        $pendingSelect=$pending?'u.pending_email':'NULL AS pending_email';
        $stmt=$this->db->prepare('SELECT v.*,u.username,u.email AS current_email,'.$pendingSelect.',u.is_active FROM email_verification_tokens v JOIN users u ON u.id=v.user_id WHERE v.token_hash=? LIMIT 1');
        $stmt->execute([$hash]);$row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function markVerificationUsed(int $id): bool
    {
        $stmt=$this->db->prepare('UPDATE email_verification_tokens SET used_at=UTC_TIMESTAMP() WHERE id=? AND used_at IS NULL AND cancelled_at IS NULL AND expires_at>UTC_TIMESTAMP()');
        $stmt->execute([$id]);return $stmt->rowCount()>0;
    }

    public function queue(?int $userId,string $recipient,string $category,string $subject,string $text,string $html,?string $dedupeKey=null): int
    {
        $recipient=strtolower(trim($recipient));
        $dedupeKey=$dedupeKey!==null?substr(trim($dedupeKey),0,160):null;
        $sql='INSERT '.($dedupeKey!==null&&$dedupeKey!==''?'IGNORE ':'').'INTO email_deliveries(user_id,recipient,category,subject,body_text,body_html,status,attempts,last_error,dedupe_key,created_at,next_attempt_at) VALUES(?,?,?,?,?,?,\'pending\',0,NULL,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())';
        $stmt=$this->db->prepare($sql);
        $stmt->execute([$userId&&$userId>0?$userId:null,$recipient,substr($category,0,32),substr($subject,0,190),$text,$html,$dedupeKey!==''?$dedupeKey:null]);
        $id=(int)$this->db->lastInsertId();
        if($id>0||$dedupeKey===null||$dedupeKey==='')return $id;
        $find=$this->db->prepare('SELECT id FROM email_deliveries WHERE recipient=? AND dedupe_key=? LIMIT 1');$find->execute([$recipient,$dedupeKey]);return (int)($find->fetchColumn()?:0);
    }

    public function pending(int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        return $this->db->query("SELECT * FROM email_deliveries WHERE ((status IN ('pending','failed') AND attempts<5 AND next_attempt_at<=UTC_TIMESTAMP()) OR (status='sending' AND last_attempt_at<UTC_TIMESTAMP()-INTERVAL 15 MINUTE)) ORDER BY id ASC LIMIT ".$limit)->fetchAll();
    }

    public function claim(int $id): bool
    {
        $stmt=$this->db->prepare("UPDATE email_deliveries SET status='sending',last_attempt_at=UTC_TIMESTAMP() WHERE id=? AND ((status IN ('pending','failed') AND attempts<5 AND next_attempt_at<=UTC_TIMESTAMP()) OR (status='sending' AND last_attempt_at<UTC_TIMESTAMP()-INTERVAL 15 MINUTE))");
        $stmt->execute([$id]);
        return $stmt->rowCount()>0;
    }

    public function markSent(int $id): void
    {
        $stmt=$this->db->prepare("UPDATE email_deliveries SET status='sent',attempts=attempts+1,last_error=NULL,last_attempt_at=UTC_TIMESTAMP(),sent_at=UTC_TIMESTAMP(),body_text=NULL,body_html=NULL WHERE id=? AND status='sending'");
        $stmt->execute([$id]);
    }

    public function markFailed(int $id,string $error,int $delaySeconds): void
    {
        $delaySeconds=max(60,min(86400,$delaySeconds));
        $stmt=$this->db->prepare("UPDATE email_deliveries SET status='failed',attempts=attempts+1,last_error=?,last_attempt_at=UTC_TIMESTAMP(),next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ".$delaySeconds." SECOND) WHERE id=? AND status='sending'");
        $stmt->execute([substr($error,0,1000),$id]);
    }

    public function markExpired(int $id,string $reason='Delivery expired before it could be sent.'): void
    {
        $stmt=$this->db->prepare("UPDATE email_deliveries SET status='expired',last_error=?,last_attempt_at=UTC_TIMESTAMP(),body_text=NULL,body_html=NULL WHERE id=? AND status IN ('pending','failed','sending')");
        $stmt->execute([substr($reason,0,1000),$id]);
    }

    public function retry(int $id): bool
    {
        $stmt=$this->db->prepare("UPDATE email_deliveries SET status='pending',attempts=0,last_error=NULL,next_attempt_at=UTC_TIMESTAMP() WHERE id=? AND status='failed' AND body_text IS NOT NULL");
        $stmt->execute([$id]);return $stmt->rowCount()>0;
    }

    public function recent(int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->query('SELECT id,user_id,recipient,category,subject,status,attempts,last_error,created_at,last_attempt_at,sent_at FROM email_deliveries ORDER BY id DESC LIMIT '.$limit)->fetchAll();
    }

    public function stats(): array
    {
        if(!$this->schemaReady())return ['pending'=>0,'sent_24h'=>0,'failed'=>0,'oldest_pending_at'=>null];
        $row=$this->db->query("SELECT SUM(status IN ('pending','sending')) AS pending,SUM(status='failed') AS failed,SUM(status='sent' AND sent_at>=UTC_TIMESTAMP()-INTERVAL 24 HOUR) AS sent_24h,MIN(CASE WHEN status IN ('pending','sending') THEN created_at END) AS oldest_pending_at FROM email_deliveries")->fetch();
        return ['pending'=>(int)($row['pending']??0),'sent_24h'=>(int)($row['sent_24h']??0),'failed'=>(int)($row['failed']??0),'oldest_pending_at'=>$row['oldest_pending_at']??null];
    }

    public function verifiedRecipient(int $userId): ?array
    {
        $stmt=$this->db->prepare('SELECT id,username,email,email_verified_at,is_active,is_admin FROM users WHERE id=? AND is_active=1 AND email IS NOT NULL AND email<>\'\' AND email_verified_at IS NOT NULL LIMIT 1');
        $stmt->execute([$userId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function verifiedRecipients(string $audience='all'): array
    {
        $audience=in_array($audience,['all','customers','admins'],true)?$audience:'all';
        $where="is_active=1 AND email IS NOT NULL AND email<>'' AND email_verified_at IS NOT NULL";
        if($audience==='customers')$where.=' AND is_admin=0';
        elseif($audience==='admins')$where.=' AND is_admin=1';
        return $this->db->query('SELECT id,username,email,is_admin,admin_role FROM users WHERE '.$where.' ORDER BY id ASC')->fetchAll();
    }

    public function verifiedAdminRecipients(array $roles): array
    {
        $roles=array_values(array_unique(array_intersect(['owner','admin','support'],array_map(static fn($v):string=>strtolower(trim((string)$v)),$roles))));
        if(!$roles)return [];
        $placeholders=implode(',',array_fill(0,count($roles),'?'));
        $stmt=$this->db->prepare("SELECT id,username,email,is_admin,admin_role FROM users WHERE is_active=1 AND is_admin=1 AND admin_role IN ($placeholders) AND email IS NOT NULL AND email<>'' AND email_verified_at IS NOT NULL ORDER BY id ASC");
        $stmt->execute($roles);
        return $stmt->fetchAll();
    }

    public function prune(int $days=90): int
    {
        $days=max(30,min(3650,$days));
        $a=(int)$this->db->exec("DELETE FROM email_deliveries WHERE ((status='sent' AND sent_at < UTC_TIMESTAMP()-INTERVAL ".$days." DAY) OR (status IN ('expired','failed') AND created_at < UTC_TIMESTAMP()-INTERVAL ".$days." DAY))");
        $b=(int)$this->db->exec("DELETE FROM email_verification_tokens WHERE (used_at IS NOT NULL OR cancelled_at IS NOT NULL OR expires_at<UTC_TIMESTAMP()) AND created_at < UTC_TIMESTAMP()-INTERVAL ".$days." DAY");
        return $a+$b;
    }
}
