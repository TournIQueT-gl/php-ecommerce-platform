<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SchedulerStateRepository
{
    public function __construct(private PDO $db) {}

    public function acquire(string $taskKey): bool
    {
        $name='auraa:'.substr(hash('sha256',$taskKey),0,40);
        $stmt=$this->db->prepare('SELECT GET_LOCK(?,0)');
        $stmt->execute([$name]);
        return (int)$stmt->fetchColumn()===1;
    }

    public function release(string $taskKey): void
    {
        $name='auraa:'.substr(hash('sha256',$taskKey),0,40);
        $stmt=$this->db->prepare('SELECT RELEASE_LOCK(?)');
        $stmt->execute([$name]);
    }

    public function isDue(string $taskKey, int $intervalSeconds): bool
    {
        $stmt=$this->db->prepare('SELECT UNIX_TIMESTAMP(last_finished_at) FROM scheduler_state WHERE task_key=? LIMIT 1');
        $stmt->execute([$taskKey]);
        $last=$stmt->fetchColumn();
        return $last===false || $last===null || (time()-(int)$last)>=max(1,$intervalSeconds);
    }

    public function markStarted(string $taskKey): void
    {
        $stmt=$this->db->prepare("INSERT INTO scheduler_state(task_key,last_started_at,last_status,last_error) VALUES(?,UTC_TIMESTAMP(),'running',NULL) ON DUPLICATE KEY UPDATE last_started_at=UTC_TIMESTAMP(),last_status='running',last_error=NULL");
        $stmt->execute([$taskKey]);
    }

    public function markFinished(string $taskKey, int $durationMs): void
    {
        $stmt=$this->db->prepare("UPDATE scheduler_state SET last_finished_at=UTC_TIMESTAMP(),last_status='ok',last_error=NULL,last_duration_ms=? WHERE task_key=?");
        $stmt->execute([max(0,$durationMs),$taskKey]);
    }

    public function state(string $taskKey): ?array
    {
        $stmt=$this->db->prepare('SELECT task_key,last_started_at,last_finished_at,last_status,last_error,last_duration_ms,updated_at FROM scheduler_state WHERE task_key=? LIMIT 1');
        $stmt->execute([$taskKey]);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function markFailed(string $taskKey, string $error, int $durationMs): void
    {
        $stmt=$this->db->prepare("UPDATE scheduler_state SET last_finished_at=UTC_TIMESTAMP(),last_status='failed',last_error=?,last_duration_ms=? WHERE task_key=?");
        $stmt->execute([substr($error,0,1000),max(0,$durationMs),$taskKey]);
    }
}
