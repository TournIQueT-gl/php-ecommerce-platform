<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuditLogRepository
{
    public function __construct(private PDO $db) {}

    public function add(int $adminId, int $userId, string $action, string $details): void
    {
        $this->db->prepare('INSERT INTO audit_log(admin_id,user_id,action,details) VALUES(?,?,?,?)')
            ->execute([$adminId, $userId, $action, substr($details, 0, 1000)]);
    }
    public function forUser(int $userId, int $limit = 20): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=$this->db->prepare('SELECT l.*,a.username AS admin_username FROM audit_log l LEFT JOIN users a ON a.id=l.admin_id WHERE l.user_id=? ORDER BY l.created_at DESC LIMIT '.$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 8): array
    {
        $limit=max(1,min(100,$limit));
        return $this->db->query('SELECT l.action,l.details,l.created_at,a.username AS admin_username,u.username AS target_username,l.user_id FROM audit_log l LEFT JOIN users a ON a.id=l.admin_id LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT '.$limit)->fetchAll();
    }

    public function page(string $query,string $action,int $page,int $perPage): array
    {
        $where=[];$params=[];
        if($query!==''){$where[]='(a.username LIKE ? OR u.username LIKE ? OR l.details LIKE ?)';$like='%'.$query.'%';array_push($params,$like,$like,$like);}
        if($action!==''){$where[]='l.action=?';$params[]=$action;}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $base=' FROM audit_log l LEFT JOIN users a ON a.id=l.admin_id LEFT JOIN users u ON u.id=l.user_id';
        $count=$this->db->prepare('SELECT COUNT(*)'.$base.$whereSql);$count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);$offset=($page-1)*$perPage;
        $stmt=$this->db->prepare('SELECT l.*,a.username AS admin_username,u.username AS target_username'.$base.$whereSql.' ORDER BY l.created_at DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset);$stmt->execute($params);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    public function actions(): array
    {
        return $this->db->query("SELECT DISTINCT action FROM audit_log WHERE action IS NOT NULL AND action<>'' ORDER BY action LIMIT 200")->fetchAll(PDO::FETCH_COLUMN);
    }

}
