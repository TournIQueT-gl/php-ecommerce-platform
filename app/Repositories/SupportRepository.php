<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SupportRepository
{
    public function __construct(private PDO $db) {}

    public function createTicket(int $userId,string $category,string $subject,string $priority='normal'): int
    {
        $stmt=$this->db->prepare('INSERT INTO support_tickets(user_id,category,subject,status,priority) VALUES(?,?,?,\'open\',?)');
        $stmt->execute([$userId,$category,$subject,$priority]);
        return (int)$this->db->lastInsertId();
    }

    public function createTicketWithMessage(int $userId,string $category,string $subject,string $priority,?string $message,?array $attachment=null): array
    {
        $startedTransaction=!$this->db->inTransaction();
        if($startedTransaction)$this->db->beginTransaction();
        try{
            $ticketId=$this->createTicket($userId,$category,$subject,$priority);
            $messageId=$this->addMessage($ticketId,$userId,false,$message,$attachment);
            if($startedTransaction)$this->db->commit();
            return ['ticket_id'=>$ticketId,'message_id'=>$messageId];
        }catch(\Throwable $e){
            if($startedTransaction&&$this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function addUserMessageIfOpen(int $ticketId,int $userId,?string $message,?array $attachment=null): int
    {
        $startedTransaction=!$this->db->inTransaction();
        if($startedTransaction)$this->db->beginTransaction();
        try{
            $lock=$this->db->prepare('SELECT status,assigned_admin_id FROM support_tickets WHERE id=? AND user_id=? LIMIT 1 FOR UPDATE');
            $lock->execute([$ticketId,$userId]);
            $ticket=$lock->fetch();
            if(!is_array($ticket))throw new \RuntimeException('Support ticket was not found.');
            if((string)$ticket['status']==='closed')throw new \DomainException('Support ticket is closed.');
            $messageId=$this->addMessage($ticketId,$userId,false,$message,$attachment);
            $assigned=isset($ticket['assigned_admin_id'])&&$ticket['assigned_admin_id']!==null?(int)$ticket['assigned_admin_id']:null;
            $this->setStatus($ticketId,'open',$assigned);
            if($startedTransaction)$this->db->commit();
            return $messageId;
        }catch(\Throwable $e){
            if($startedTransaction&&$this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function addMessageAndSetStatus(int $ticketId,int $senderUserId,bool $isAdmin,?string $message,?array $attachment,string $status,?int $assignedAdminId=null): int
    {
        $startedTransaction=!$this->db->inTransaction();
        if($startedTransaction)$this->db->beginTransaction();
        try{
            $messageId=$this->addMessage($ticketId,$senderUserId,$isAdmin,$message,$attachment);
            $this->setStatus($ticketId,$status,$assignedAdminId);
            if($startedTransaction)$this->db->commit();
            return $messageId;
        }catch(\Throwable $e){
            if($startedTransaction&&$this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function addMessage(int $ticketId,int $senderUserId,bool $isAdmin,?string $message,?array $attachment=null): int
    {
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) $this->db->beginTransaction();

        try {
            $stmt=$this->db->prepare('INSERT INTO support_messages(ticket_id,sender_user_id,sender_is_admin,message,attachment_original_name,attachment_stored_name,attachment_size,attachment_sha256) VALUES(?,?,?,?,?,?,?,?)');
            $stmt->execute([$ticketId,$senderUserId,$isAdmin?1:0,$message,$attachment['original_name']??null,$attachment['stored_name']??null,$attachment['size']??null,$attachment['sha256']??null]);

            // Capture the insert id immediately. Do not ask PDO for it after another statement.
            $messageId=(int)$this->db->lastInsertId();
            if($messageId<=0) throw new \RuntimeException('Support reply could not be saved.');

            // Verify the exact row that was just inserted before reporting success.
            $verify=$this->db->prepare('SELECT id,ticket_id,sender_user_id,sender_is_admin FROM support_messages WHERE id=? AND ticket_id=? LIMIT 1');
            $verify->execute([$messageId,$ticketId]);
            $saved=$verify->fetch();
            if(!is_array($saved)
                || (int)$saved['sender_user_id']!==$senderUserId
                || (int)$saved['sender_is_admin']!==($isAdmin?1:0)) {
                throw new \RuntimeException('Support reply verification failed.');
            }

            $this->db->prepare('UPDATE support_tickets SET updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$ticketId]);
            if($startedTransaction) $this->db->commit();
            return $messageId;
        } catch (\Throwable $e) {
            if($startedTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function ticketForUser(int $ticketId,int $userId): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM support_tickets WHERE id=? AND user_id=? LIMIT 1');$stmt->execute([$ticketId,$userId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function ticketAdmin(int $ticketId): ?array
    {
        $stmt=$this->db->prepare('SELECT t.*,u.username,a.username AS assigned_admin_username FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id LEFT JOIN users a ON a.id=t.assigned_admin_id WHERE t.id=? LIMIT 1');$stmt->execute([$ticketId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function messages(int $ticketId): array
    {
        $stmt=$this->db->prepare('SELECT m.*,u.username FROM support_messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE m.ticket_id=? ORDER BY m.created_at ASC,m.id ASC');
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function messagesForUser(int $ticketId,int $userId): array
    {
        // Ownership is checked first, then users read the exact same conversation
        // query as Admin. This prevents the two sides from drifting apart.
        if(!$this->ticketForUser($ticketId,$userId)) return [];
        return $this->messages($ticketId);
    }

    public function messagesForUserAfter(int $ticketId,int $userId,int $afterId,int $limit=50): array
    {
        if(!$this->ticketForUser($ticketId,$userId)) return [];
        $limit=max(1,min(100,$limit));$afterId=max(0,$afterId);
        $stmt=$this->db->prepare('SELECT m.*,u.username FROM support_messages m LEFT JOIN users u ON u.id=m.sender_user_id WHERE m.ticket_id=? AND m.id>? ORDER BY m.id ASC LIMIT '.$limit);
        $stmt->execute([$ticketId,$afterId]);
        return $stmt->fetchAll();
    }

    public function ticketsForUser(int $userId,int $limit=50): array
    {
        $limit=max(1,min(100,$limit));
        $sql="SELECT t.*,
            (SELECT sm.message FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=1 AND sm.message IS NOT NULL AND sm.message<>'' ORDER BY sm.id DESC LIMIT 1) AS latest_admin_message,
            (SELECT sm.created_at FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=1 ORDER BY sm.id DESC LIMIT 1) AS latest_admin_reply_at,
            (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=1 AND sm.id>COALESCE((SELECT r.last_read_message_id FROM support_ticket_reads r WHERE r.user_id=? AND r.ticket_id=t.id LIMIT 1),0)) AS unread_count
            FROM support_tickets t WHERE t.user_id=? ORDER BY t.updated_at DESC LIMIT ".$limit;
        try{
            $stmt=$this->db->prepare($sql);
            $stmt->execute([$userId,$userId]);
            return $stmt->fetchAll();
        }catch(\Throwable){
            // During a rolling deployment the read-state migration may not have
            // been applied yet. Keep the existing support center usable.
            $fallback="SELECT t.*,
                (SELECT sm.message FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=1 AND sm.message IS NOT NULL AND sm.message<>'' ORDER BY sm.id DESC LIMIT 1) AS latest_admin_message,
                (SELECT sm.created_at FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=1 ORDER BY sm.id DESC LIMIT 1) AS latest_admin_reply_at,
                0 AS unread_count
                FROM support_tickets t WHERE t.user_id=? ORDER BY t.updated_at DESC LIMIT ".$limit;
            $stmt=$this->db->prepare($fallback);$stmt->execute([$userId]);return $stmt->fetchAll();
        }
    }

    public function adminPage(string $status,string $query,int $page,int $perPage,int $readerAdminId=0): array
    {
        $allowed=['all','open','waiting','closed'];if(!in_array($status,$allowed,true))$status='all';
        $where=[];$params=[];
        if($status!=='all'){$where[]='t.status=?';$params[]=$status;}
        if($query!==''){$where[]='(u.username LIKE ? OR t.subject LIKE ? OR CAST(t.id AS CHAR)=?)';$like='%'.$query.'%';array_push($params,$like,$like,$query);}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $base=' FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id';
        $c=$this->db->prepare('SELECT COUNT(*)'.$base.$whereSql);$c->execute($params);$total=(int)$c->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,$page),$pages);$offset=($page-1)*$perPage;
        $select='SELECT t.*,u.username';
        $selectParams=$params;
        if($readerAdminId>0){
            $select.=',(SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id=t.id AND sm.sender_is_admin=0 AND sm.id>COALESCE((SELECT r.last_read_message_id FROM support_ticket_reads r WHERE r.user_id=? AND r.ticket_id=t.id LIMIT 1),0)) AS unread_count';
            $selectParams=array_merge([$readerAdminId],$selectParams);
        }else{$select.=',0 AS unread_count';}
        try{
            $stmt=$this->db->prepare($select.$base.$whereSql.' ORDER BY FIELD(t.status,\'open\',\'waiting\',\'closed\'),t.updated_at DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset);$stmt->execute($selectParams);
            $rows=$stmt->fetchAll();
        }catch(\Throwable){
            $stmt=$this->db->prepare('SELECT t.*,u.username,0 AS unread_count'.$base.$whereSql.' ORDER BY FIELD(t.status,\'open\',\'waiting\',\'closed\'),t.updated_at DESC LIMIT '.(int)$perPage.' OFFSET '.(int)$offset);$stmt->execute($params);
            $rows=$stmt->fetchAll();
        }
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    public function setStatus(int $ticketId,string $status,?int $assignedAdminId=null): void
    {
        $closed=$status==='closed'?'UTC_TIMESTAMP()':'NULL';
        $sql='UPDATE support_tickets SET status=?,assigned_admin_id=?,closed_at='.$closed.',updated_at=UTC_TIMESTAMP() WHERE id=?';
        $this->db->prepare($sql)->execute([$status,$assignedAdminId,$ticketId]);
    }

    public function stats(): array
    {
        $rows=$this->db->query("SELECT status,COUNT(*) c FROM support_tickets GROUP BY status")->fetchAll();$out=['open'=>0,'waiting'=>0,'closed'=>0];foreach($rows as $r)$out[(string)$r['status']]=(int)$r['c'];return $out;
    }

    public function attachmentMessage(int $messageId): ?array
    {
        $stmt=$this->db->prepare('SELECT m.*,t.user_id AS ticket_user_id FROM support_messages m INNER JOIN support_tickets t ON t.id=m.ticket_id WHERE m.id=? LIMIT 1');$stmt->execute([$messageId]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function allForUserBundle(int $userId): array
    {
        $stmt=$this->db->prepare('SELECT t.*,u.username FROM support_tickets t LEFT JOIN users u ON u.id=t.user_id WHERE t.user_id=? ORDER BY t.id DESC');$stmt->execute([$userId]);return $stmt->fetchAll();
    }

    public function expiredAttachmentRows(int $days,int $limit=200): array
    {
        $days=max(30,min(3650,$days));$limit=max(1,min(1000,$limit));
        $sql="SELECT m.id,m.attachment_stored_name FROM support_messages m INNER JOIN support_tickets t ON t.id=m.ticket_id WHERE t.status='closed' AND t.closed_at IS NOT NULL AND t.closed_at < (UTC_TIMESTAMP() - INTERVAL ".$days." DAY) AND m.attachment_stored_name IS NOT NULL AND m.attachment_stored_name<>'' ORDER BY m.id ASC LIMIT ".$limit;
        return $this->db->query($sql)->fetchAll();
    }

    public function clearAttachmentMetadata(int $messageId): void
    {
        $this->db->prepare('UPDATE support_messages SET attachment_original_name=NULL,attachment_stored_name=NULL,attachment_size=NULL,attachment_sha256=NULL WHERE id=?')->execute([$messageId]);
    }
}
