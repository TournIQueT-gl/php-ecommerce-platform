<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SupportReadRepository
{
    public function __construct(private PDO $db) {}

    public function markThrough(int $userId, int $ticketId, int $lastMessageId): void
    {
        if ($userId <= 0 || $ticketId <= 0 || $lastMessageId < 0) return;
        $stmt=$this->db->prepare('INSERT INTO support_ticket_reads(user_id,ticket_id,last_read_message_id,last_read_at) VALUES(?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_read_message_id=GREATEST(last_read_message_id,VALUES(last_read_message_id)),last_read_at=UTC_TIMESTAMP()');
        $stmt->execute([$userId,$ticketId,$lastMessageId]);
    }

    public function markLatest(int $userId, int $ticketId): int
    {
        $stmt=$this->db->prepare('SELECT COALESCE(MAX(id),0) FROM support_messages WHERE ticket_id=?');
        $stmt->execute([$ticketId]);
        $last=(int)$stmt->fetchColumn();
        $this->markThrough($userId,$ticketId,$last);
        return $last;
    }

    public function lastReadMessageId(int $userId, int $ticketId): int
    {
        $stmt=$this->db->prepare('SELECT last_read_message_id FROM support_ticket_reads WHERE user_id=? AND ticket_id=? LIMIT 1');
        $stmt->execute([$userId,$ticketId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public function unreadForUserTicket(int $userId, int $ticketId): int
    {
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM support_messages m WHERE m.ticket_id=? AND m.sender_user_id<>? AND m.id>COALESCE((SELECT r.last_read_message_id FROM support_ticket_reads r WHERE r.user_id=? AND r.ticket_id=? LIMIT 1),0)');
        $stmt->execute([$ticketId,$userId,$userId,$ticketId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<int> */
    public function unreadTicketIdsForUser(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT t.id FROM support_tickets t WHERE t.user_id=? AND EXISTS (SELECT 1 FROM support_messages m WHERE m.ticket_id=t.id AND m.sender_is_admin=1 AND m.id>COALESCE((SELECT r.last_read_message_id FROM support_ticket_reads r WHERE r.user_id=? AND r.ticket_id=t.id LIMIT 1),0))');
        $stmt->execute([$userId,$userId]);
        return array_values(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    /** @return list<int> */
    public function unreadTicketIdsForAdmin(int $adminId): array
    {
        $stmt=$this->db->prepare("SELECT t.id FROM support_tickets t WHERE t.status<>'closed' AND EXISTS (SELECT 1 FROM support_messages m WHERE m.ticket_id=t.id AND m.sender_is_admin=0 AND m.id>COALESCE((SELECT r.last_read_message_id FROM support_ticket_reads r WHERE r.user_id=? AND r.ticket_id=t.id LIMIT 1),0))");
        $stmt->execute([$adminId]);
        return array_values(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function unreadTicketCountForUser(int $userId): int
    {
        return count($this->unreadTicketIdsForUser($userId));
    }

    public function unreadTicketCountForAdmin(int $adminId): int
    {
        return count($this->unreadTicketIdsForAdmin($adminId));
    }
}
