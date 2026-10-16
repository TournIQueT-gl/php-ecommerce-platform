<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Support\Request;
use PDO;

final class IpAccessRepository
{
    private ?bool $schemaReadyCache = null;

    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        if ($this->schemaReadyCache !== null) return $this->schemaReadyCache;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='access_ip_blocks'");
            return $this->schemaReadyCache = ((int)$stmt->fetchColumn() === 1);
        } catch (\Throwable) {
            return $this->schemaReadyCache = false;
        }
    }

    public function activeBlocks(): array
    {
        if (!$this->schemaReady()) return [];
        return $this->db->query("SELECT b.*,u.username AS blocked_by_username FROM access_ip_blocks b LEFT JOIN users u ON u.id=b.blocked_by_user_id WHERE b.is_active=1 AND (b.expires_at IS NULL OR b.expires_at>UTC_TIMESTAMP()) ORDER BY b.created_at DESC,b.id DESC")->fetchAll();
    }

    public function activeEnforcementBlocks(): array
    {
        if (!$this->schemaReady()) return [];
        return $this->db->query("SELECT id,cidr,reason,expires_at FROM access_ip_blocks WHERE is_active=1 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) ORDER BY id DESC")->fetchAll();
    }

    public function allRecent(int $limit=150): array
    {
        if (!$this->schemaReady()) return [];
        $limit=max(1,min(500,$limit));
        return $this->db->query("SELECT b.*,u.username AS blocked_by_username FROM access_ip_blocks b LEFT JOIN users u ON u.id=b.blocked_by_user_id ORDER BY b.id DESC LIMIT {$limit}")->fetchAll();
    }

    public function findActiveMatch(string $ip): ?array
    {
        if (!filter_var($ip,FILTER_VALIDATE_IP)) return null;
        foreach ($this->activeBlocks() as $row) {
            $cidr=(string)($row['cidr']??'');
            if ($cidr!=='' && Request::ipInCidr($ip,$cidr)) return $row;
        }
        return null;
    }

    public function create(string $target,string $reason,string $note,?string $expiresAt,int $adminId): int
    {
        if (!$this->schemaReady()) throw new \RuntimeException('IP access-control database update is not installed.');
        $cidr=self::normalizeCidr($target);
        if ($cidr===null) throw new \InvalidArgumentException('Enter a valid IPv4/IPv6 address or CIDR network.');
        $check=$this->db->prepare("SELECT id FROM access_ip_blocks WHERE cidr=? AND is_active=1 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP()) LIMIT 1");$check->execute([$cidr]);
        if($check->fetchColumn()!==false)throw new \DomainException('An active block already exists for this exact IP/network.');
        $reason=strtolower(trim($reason));
        if (!in_array($reason,['scanner','abuse','fraud','manual','other'],true)) $reason='manual';
        $note=trim($note);
        if ($expiresAt!==null && trim($expiresAt)==='') $expiresAt=null;
        $stmt=$this->db->prepare('INSERT INTO access_ip_blocks(cidr,reason,admin_note,expires_at,is_active,blocked_by_user_id,created_at,updated_at) VALUES(?,?,?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$cidr,$reason,$note!==''?substr($note,0,500):null,$expiresAt,$adminId]);
        return (int)$this->db->lastInsertId();
    }

    public function deactivate(int $id): bool
    {
        if (!$this->schemaReady()) return false;
        $stmt=$this->db->prepare('UPDATE access_ip_blocks SET is_active=0,updated_at=UTC_TIMESTAMP() WHERE id=? AND is_active=1');
        $stmt->execute([$id]);
        return $stmt->rowCount()===1;
    }

    public function recordHit(int $id): void
    {
        if (!$this->schemaReady()) return;
        // Rejected floods must not turn into a write-amplification attack. One sampled
        // hit per 15-second window is enough for operational visibility; enforcement
        // itself still rejects every matching request immediately.
        $stmt=$this->db->prepare('UPDATE access_ip_blocks SET hit_count=hit_count+1,last_hit_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND (last_hit_at IS NULL OR last_hit_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 SECOND))');
        $stmt->execute([$id]);
    }

    public static function normalizeCidr(string $value): ?string
    {
        $value=trim($value);
        if ($value==='') return null;
        if (!str_contains($value,'/')) {
            if (!filter_var($value,FILTER_VALIDATE_IP)) return null;
            return $value.(str_contains($value,':')?'/128':'/32');
        }
        [$ip,$prefixText]=array_pad(explode('/',$value,2),2,'');
        $ip=trim($ip);$prefixText=trim($prefixText);
        if (!filter_var($ip,FILTER_VALIDATE_IP)||!ctype_digit($prefixText)) return null;
        $packed=@inet_pton($ip);
        if ($packed===false) return null;
        $bits=strlen($packed)*8;$prefix=(int)$prefixText;
        if ($prefix<0||$prefix>$bits) return null;
        $bytes=array_values(unpack('C*',$packed));
        $remaining=$prefix;
        foreach($bytes as $i=>$byte){
            if($remaining>=8){$remaining-=8;continue;}
            if($remaining<=0){$bytes[$i]=0;continue;}
            $mask=(0xFF << (8-$remaining)) & 0xFF;
            $bytes[$i]=$byte & $mask;$remaining=0;
        }
        $network=inet_ntop(pack('C*',...$bytes));
        if($network===false)return null;
        return $network.'/'.$prefix;
    }
}
