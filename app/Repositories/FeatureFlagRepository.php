<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class FeatureFlagRepository
{
    public function __construct(private PDO $db) {}
    public function all(): array { return $this->db->query('SELECT * FROM feature_flags ORDER BY flag_key')->fetchAll(); }
    public function forUser(int $userId): array { $s=$this->db->prepare('SELECT f.flag_key,f.label,f.description,f.default_enabled,COALESCE(uf.enabled,f.default_enabled) AS enabled FROM feature_flags f LEFT JOIN user_feature_flags uf ON uf.flag_key=f.flag_key AND uf.user_id=? ORDER BY f.flag_key');$s->execute([$userId]);return $s->fetchAll(); }
    public function enabled(int $userId,string $flagKey): bool { $s=$this->db->prepare('SELECT COALESCE(uf.enabled,f.default_enabled) FROM feature_flags f LEFT JOIN user_feature_flags uf ON uf.flag_key=f.flag_key AND uf.user_id=? WHERE f.flag_key=? LIMIT 1');$s->execute([$userId,$flagKey]);$v=$s->fetchColumn();return $v!==false && (bool)$v; }
    public function setForUser(int $userId,string $flagKey,bool $enabled): void { $this->db->prepare('INSERT INTO user_feature_flags(user_id,flag_key,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),updated_at=UTC_TIMESTAMP()')->execute([$userId,$flagKey,$enabled?1:0]); }

    public function defaultEnabled(string $flagKey): bool
    {
        $s=$this->db->prepare('SELECT default_enabled FROM feature_flags WHERE flag_key=? LIMIT 1');
        $s->execute([$flagKey]);$v=$s->fetchColumn();
        return $v!==false && (bool)$v;
    }

    public function exists(string $flagKey): bool
    {
        $s=$this->db->prepare('SELECT 1 FROM feature_flags WHERE flag_key=? LIMIT 1');
        $s->execute([$flagKey]);
        return $s->fetchColumn()!==false;
    }

    public function setDefault(string $flagKey,bool $enabled): bool
    {
        $s=$this->db->prepare('UPDATE feature_flags SET default_enabled=? WHERE flag_key=?');
        $s->execute([$enabled?1:0,$flagKey]);
        return $s->rowCount()>0;
    }
}
