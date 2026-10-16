<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NotificationRepository
{
    public function __construct(private PDO $db) {}

    private function typeFilter(?array $types, ?array $excludeTypes, array &$params): string
    {
        $filter='';
        if($types!==null){
            $types=array_values(array_unique(array_filter(array_map('strval',$types),static fn(string $v):bool=>$v!=='')));
            if(!$types)return ' AND 1=0';
            $filter.=' AND type IN ('.implode(',',array_fill(0,count($types),'?')).')';
            $params=array_merge($params,$types);
        }
        if($excludeTypes!==null){
            $excludeTypes=array_values(array_unique(array_filter(array_map('strval',$excludeTypes),static fn(string $v):bool=>$v!=='')));
            if($excludeTypes){
                $filter.=' AND type NOT IN ('.implode(',',array_fill(0,count($excludeTypes),'?')).')';
                $params=array_merge($params,$excludeTypes);
            }
        }
        return $filter;
    }

    public function create(int $userId, string $type, string $title, string $body, array $data = [], ?string $dedupeKey = null): int
    {
        if ($userId <= 0) return 0;
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $sql = 'INSERT '.($dedupeKey !== null ? 'IGNORE ' : '').'INTO notifications(user_id,type,title,body,data_json,dedupe_key) VALUES(?,?,?,?,?,?)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, substr($type,0,64), substr($title,0,160), substr($body,0,1000), $json, $dedupeKey !== null ? substr($dedupeKey,0,100) : null]);
        $id = (int)$this->db->lastInsertId();
        if ($id > 0 || $dedupeKey === null) return $id;
        $find = $this->db->prepare('SELECT id FROM notifications WHERE user_id=? AND dedupe_key=? LIMIT 1');
        $find->execute([$userId, substr($dedupeKey,0,100)]);
        return (int)($find->fetchColumn() ?: 0);
    }

    public function broadcastToActiveUsers(string $type, string $title, string $body, array $data = [], string $dedupeKey = ''): int
    {
        $json=json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $dedupeKey=substr(trim($dedupeKey),0,100);
        if($dedupeKey==='')throw new \InvalidArgumentException('Broadcast notifications require a dedupe key.');
        $stmt=$this->db->prepare(
            "INSERT IGNORE INTO notifications(user_id,type,title,body,data_json,dedupe_key)
             SELECT u.id,?,?,?,?,?
             FROM users u
             LEFT JOIN feature_flags ff ON ff.flag_key='notification_center'
             LEFT JOIN user_feature_flags uff ON uff.user_id=u.id AND uff.flag_key='notification_center'
             WHERE u.is_active=1 AND COALESCE(uff.enabled,ff.default_enabled,0)=1"
        );
        $stmt->execute([substr($type,0,64),substr($title,0,160),substr($body,0,1000),$json,$dedupeKey]);
        return $stmt->rowCount();
    }

    public function unreadCount(int $userId, ?array $types=null, ?array $excludeTypes=null): int
    {
        $params=[$userId];
        $filter=$this->typeFilter($types,$excludeTypes,$params);
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL'.$filter);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<int> */
    public function unreadSupportTicketIds(int $userId, bool $admin = false): array
    {
        $types=$admin?['support.user_replied','support.ticket_created']:['support.admin_replied'];
        $placeholders=implode(',',array_fill(0,count($types),'?'));
        $stmt=$this->db->prepare('SELECT data_json FROM notifications WHERE user_id=? AND read_at IS NULL AND type IN ('.$placeholders.')');
        $stmt->execute(array_merge([$userId],$types));
        $ids=[];
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $json){
            $data=json_decode((string)$json,true);
            $ticketId=is_array($data)?(int)($data['ticket_id']??0):0;
            if($ticketId>0)$ids[$ticketId]=true;
        }
        return array_map('intval',array_keys($ids));
    }

    public function recent(int $userId, int $limit = 30): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=$this->db->prepare('SELECT id,type,title,body,data_json,read_at,created_at FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT '.$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function page(int $userId, int $page = 1, int $perPage = 25, ?array $types=null, ?array $excludeTypes=null): array
    {
        $page=max(1,$page);$perPage=max(1,min(100,$perPage));
        $params=[$userId];$filter=$this->typeFilter($types,$excludeTypes,$params);
        $count=$this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?'.$filter);
        $count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $stmt=$this->db->prepare('SELECT id,type,title,body,data_json,read_at,created_at FROM notifications WHERE user_id=?'.$filter.' ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset);
        $stmt->execute($params);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public function findForUser(int $userId, int $notificationId, ?array $types=null, ?array $excludeTypes=null): ?array
    {
        $params=[$notificationId,$userId];$filter=$this->typeFilter($types,$excludeTypes,$params);
        $stmt=$this->db->prepare('SELECT id,user_id,type,title,body,data_json,read_at,created_at FROM notifications WHERE id=? AND user_id=?'.$filter.' LIMIT 1');
        $stmt->execute($params);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        $stmt=$this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=? AND user_id=?');
        $stmt->execute([$notificationId,$userId]);
        return $stmt->rowCount()>0;
    }

    public function markAllRead(int $userId, ?array $types=null, ?array $excludeTypes=null): int
    {
        $params=[$userId];$filter=$this->typeFilter($types,$excludeTypes,$params);
        $stmt=$this->db->prepare('UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE user_id=? AND read_at IS NULL'.$filter);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function markSupportTicketRead(int $userId, int $ticketId): int
    {
        if($userId<=0||$ticketId<=0)return 0;
        $stmt=$this->db->prepare("SELECT id,data_json FROM notifications WHERE user_id=? AND type='support.admin_replied' AND read_at IS NULL ORDER BY id DESC");
        $stmt->execute([$userId]);
        $ids=[];
        foreach($stmt->fetchAll() as $row){
            $data=json_decode((string)($row['data_json']??'{}'),true);
            if(is_array($data) && (int)($data['ticket_id']??0)===$ticketId)$ids[]=(int)$row['id'];
        }
        if(!$ids)return 0;
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $params=array_merge([$userId],$ids);
        $update=$this->db->prepare('UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE user_id=? AND id IN ('.$placeholders.') AND read_at IS NULL');
        $update->execute($params);
        return $update->rowCount();
    }

    public function broadcastToAdmins(string $type,string $title,string $body,array $data=[],string $dedupeKey=''): int
    {
        $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $dedupeKey=substr(trim($dedupeKey),0,100);
        if($dedupeKey==='')throw new \InvalidArgumentException('Admin notifications require a dedupe key.');
        $stmt=$this->db->prepare(
            "INSERT IGNORE INTO notifications(user_id,type,title,body,data_json,dedupe_key)
             SELECT u.id,?,?,?,?,? FROM users u
             WHERE u.is_active=1 AND u.is_admin=1"
        );
        $stmt->execute([substr($type,0,64),substr($title,0,160),substr($body,0,1000),$json,$dedupeKey]);
        return $stmt->rowCount();
    }

    public function broadcastToAdminRoles(array $roles,string $type,string $title,string $body,array $data=[],string $dedupeKey=''): int
    {
        $roles=array_values(array_unique(array_intersect(['owner','admin','support'],array_map(static fn($v):string=>strtolower(trim((string)$v)),$roles))));
        if(!$roles)return 0;
        $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $dedupeKey=substr(trim($dedupeKey),0,100);
        if($dedupeKey==='')throw new \InvalidArgumentException('Admin notifications require a dedupe key.');
        $placeholders=implode(',',array_fill(0,count($roles),'?'));
        $stmt=$this->db->prepare(
            "INSERT IGNORE INTO notifications(user_id,type,title,body,data_json,dedupe_key)
             SELECT u.id,?,?,?,?,? FROM users u
             WHERE u.is_active=1 AND u.is_admin=1 AND u.admin_role IN (".$placeholders.")"
        );
        $stmt->execute(array_merge([substr($type,0,64),substr($title,0,160),substr($body,0,1000),$json,$dedupeKey],$roles));
        return $stmt->rowCount();
    }

    public function broadcastToAudience(string $audience,string $type,string $title,string $body,array $data=[],string $dedupeKey=''): int
    {
        $audience=in_array($audience,['all','customers','admins'],true)?$audience:'all';
        $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $dedupeKey=substr(trim($dedupeKey),0,100);
        if($dedupeKey==='')throw new \InvalidArgumentException('Broadcast notifications require a dedupe key.');
        $extra='';
        if($audience==='customers')$extra=' AND u.is_admin=0';
        elseif($audience==='admins')$extra=' AND u.is_admin=1';
        $stmt=$this->db->prepare(
            "INSERT IGNORE INTO notifications(user_id,type,title,body,data_json,dedupe_key)
             SELECT u.id,?,?,?,?,?
             FROM users u
             LEFT JOIN feature_flags ff ON ff.flag_key='notification_center'
             LEFT JOIN user_feature_flags uff ON uff.user_id=u.id AND uff.flag_key='notification_center'
             WHERE u.is_active=1 AND COALESCE(uff.enabled,ff.default_enabled,0)=1".$extra
        );
        $stmt->execute([substr($type,0,64),substr($title,0,160),substr($body,0,1000),$json,$dedupeKey]);
        return $stmt->rowCount();
    }

    public function pruneRead(int $days = 180): int
    {
        $days=max(1,min(3650,$days));
        return $this->db->exec('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < (UTC_TIMESTAMP() - INTERVAL '.$days.' DAY)');
    }
}
