<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class AnnouncementRepository
{
    public function __construct(private PDO $db) {}
    public function active(): array { return $this->activeForUser(false,false); }
    public function activeForUser(bool $isAdmin,bool $isCustomer): array
    {
        try{
            $rows=$this->db->query("SELECT * FROM announcements WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP()) ORDER BY is_pinned DESC,FIELD(priority,'critical','high','normal','low'),id DESC LIMIT 20")->fetchAll();
            return array_values(array_filter($rows,static function(array $row)use($isAdmin,$isCustomer):bool{
                $audience=(string)($row['audience']??'all');
                return $audience==='all'||($audience==='admins'&&$isAdmin)||($audience==='customers'&&$isCustomer);
            }));
        }catch(\PDOException){
            return $this->db->query("SELECT * FROM announcements WHERE is_active=1 AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP()) ORDER BY id DESC LIMIT 20")->fetchAll();
        }
    }
    public function all(): array { return $this->adminPage(1,200)['rows']; }
    public function adminPage(int $page=1,int $perPage=30): array
    {
        $perPage=max(1,min(100,$perPage));$total=(int)$this->db->query('SELECT COUNT(*) FROM announcements')->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($page,$pages));$offset=($page-1)*$perPage;
        $rows=$this->db->query("SELECT a.*,u.username AS creator_username FROM announcements a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.id DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function create(int $adminId,string $title,string $message,string $type,?string $startsAt,?string $endsAt,string $audience='all',string $priority='normal',bool $pinned=false): int
    {
        $s=$this->db->prepare('INSERT INTO announcements(title,message,type,audience,priority,is_pinned,is_active,starts_at,ends_at,created_by) VALUES(?,?,?,?,?,?,1,?,?,?)');
        $s->execute([$title,$message,$type,$audience,$priority,$pinned?1:0,$startsAt,$endsAt,$adminId]);return (int)$this->db->lastInsertId();
    }
    public function toggle(int $id): bool { $s=$this->db->prepare('UPDATE announcements SET is_active=IF(is_active=1,0,1) WHERE id=?');$s->execute([$id]);return $s->rowCount()>0; }
    public function setActiveMany(array $ids,bool $active): int
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids)return 0;$placeholders=implode(',',array_fill(0,count($ids),'?'));
        $s=$this->db->prepare('UPDATE announcements SET is_active=? WHERE id IN ('.$placeholders.')');$s->execute(array_merge([$active?1:0],$ids));return $s->rowCount();
    }
    public function deleteMany(array $ids): int
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));
        if(!$ids)return 0;$placeholders=implode(',',array_fill(0,count($ids),'?'));$s=$this->db->prepare('DELETE FROM announcements WHERE id IN ('.$placeholders.')');$s->execute($ids);return $s->rowCount();
    }
    public function delete(int $id): bool { $s=$this->db->prepare('DELETE FROM announcements WHERE id=?');$s->execute([$id]);return $s->rowCount()>0; }
    public function dueForNotification(int $limit=30): array
    {
        $limit=max(1,min(100,$limit));
        return $this->db->query("SELECT * FROM announcements WHERE is_active=1 AND notification_sent_at IS NULL AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP()) ORDER BY id ASC LIMIT {$limit}")->fetchAll();
    }
    public function markNotificationSent(int $id): void { $this->db->prepare('UPDATE announcements SET notification_sent_at=COALESCE(notification_sent_at,UTC_TIMESTAMP()) WHERE id=?')->execute([$id]); }
    public function scheduledCount(): int { return (int)$this->db->query("SELECT COUNT(*) FROM announcements WHERE is_active=1 AND starts_at>UTC_TIMESTAMP()")->fetchColumn(); }
}
