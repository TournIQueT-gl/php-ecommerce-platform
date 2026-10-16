<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AccountActivityRepository
{
    public function __construct(private PDO $db) {}

    public function add(int $userId, string $type, string $title, ?string $details = null, ?string $ip = null): void
    {
        if ($userId <= 0) return;
        $this->db->prepare('INSERT INTO account_activity(user_id,event_type,title,details,ip_address) VALUES(?,?,?,?,?)')
            ->execute([$userId, substr($type,0,48), substr($title,0,160), $details !== null ? substr($details,0,1000) : null, $ip !== null ? substr($ip,0,45) : null]);
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $limit=max(1,min(200,$limit));
        $stmt=$this->db->prepare('SELECT id,event_type,title,details,ip_address,created_at FROM account_activity WHERE user_id=? ORDER BY id DESC LIMIT '.$limit);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function recent(int $limit = 20): array
    {
        $limit=max(1,min(200,$limit));
        return $this->db->query('SELECT a.*,u.username FROM account_activity a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT '.$limit)->fetchAll();
    }

    public function pageForUser(int $userId, int $page = 1, int $perPage = 25): array
    {
        $perPage=max(1,min(100,$perPage));$page=max(1,$page);
        $count=$this->db->prepare('SELECT COUNT(*) FROM account_activity WHERE user_id=?');
        $count->execute([$userId]);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $stmt=$this->db->prepare('SELECT id,event_type,title,details,ip_address,created_at FROM account_activity WHERE user_id=? ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset);
        $stmt->execute([$userId]);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }
}
