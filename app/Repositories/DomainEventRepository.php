<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Events\DomainEvent;
use App\Support\SecretStore;
use PDO;

final class DomainEventRepository
{
    public function __construct(private PDO $db) {}

    public function enqueue(DomainEvent $event): int
    {
        $data=$event->payload;
        $guestToken=trim((string)($data['guest_access_token']??''));
        if($guestToken!==''){$data['guest_access_token_protected']=SecretStore::encrypt($guestToken,'guest-order-event');unset($data['guest_access_token']);}
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $stmt = $this->db->prepare('INSERT INTO domain_event_outbox(event_key,subject_user_id,actor_user_id,payload_json,occurred_at) VALUES(?,?,?,?,?)');
        $stmt->execute([$event->key, $event->subjectUserId, $event->actorUserId, $payload, $event->occurredAtUtc]);
        return (int)$this->db->lastInsertId();
    }

    public function pending(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->query('SELECT id,event_key,subject_user_id,actor_user_id,payload_json,occurred_at,attempts FROM domain_event_outbox WHERE processed_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT '.$limit)->fetchAll();
    }

    public function pendingWebSafe(int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        return $this->db->query('SELECT id,event_key,subject_user_id,actor_user_id,payload_json,occurred_at,attempts FROM domain_event_outbox WHERE processed_at IS NULL AND attempts < 5 ORDER BY id ASC LIMIT '.$limit)->fetchAll();
    }

    public function markProcessed(int $id): void
    {
        $this->db->prepare('UPDATE domain_event_outbox SET processed_at=UTC_TIMESTAMP(),last_error=NULL WHERE id=?')->execute([$id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->prepare('UPDATE domain_event_outbox SET attempts=attempts+1,last_error=? WHERE id=?')
            ->execute([substr($error, 0, 1000), $id]);
    }

    public function backlogStats(): array
    {
        $row=$this->db->query("SELECT SUM(processed_at IS NULL AND attempts<5) AS pending,SUM(processed_at IS NULL AND attempts>=5) AS stalled,MIN(CASE WHEN processed_at IS NULL AND attempts<5 THEN occurred_at END) AS oldest_pending_at FROM domain_event_outbox")->fetch();
        return [
            'pending'=>(int)($row['pending']??0),
            'stalled'=>(int)($row['stalled']??0),
            'oldest_pending_at'=>$row['oldest_pending_at']??null,
        ];
    }

    public function pruneProcessed(int $days = 30): int
    {
        $days = max(1, min(3650, $days));
        return $this->db->exec('DELETE FROM domain_event_outbox WHERE processed_at IS NOT NULL AND processed_at < (UTC_TIMESTAMP() - INTERVAL '.$days.' DAY)');
    }
}
