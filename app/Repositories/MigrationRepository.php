<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class MigrationRepository
{
    public function __construct(private PDO $db) {}

    public function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(64) NOT NULL, description VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function applied(): array
    {
        try {
            $stmt=$this->db->query('SELECT version,description,checksum,applied_at FROM schema_migrations ORDER BY applied_at ASC,version ASC');
            $rows=$stmt->fetchAll();
            $stmt->closeCursor();
            $out=[]; foreach($rows as $row) $out[(string)$row['version']]=$row;
            return $out;
        } catch (Throwable) { return []; }
    }

    public function record(string $version, string $description, string $checksum): void
    {
        $stmt=$this->db->prepare('INSERT INTO schema_migrations(version,description,checksum) VALUES(?,?,?)');
        $stmt->execute([$version,substr($description,0,255),$checksum]);
        $stmt->closeCursor();
    }

    public function recordIfMissing(string $version, string $description, string $checksum='baseline'): void
    {
        $stmt=$this->db->prepare('INSERT IGNORE INTO schema_migrations(version,description,checksum) VALUES(?,?,?)');
        $stmt->execute([$version,substr($description,0,255),$checksum]);
        $stmt->closeCursor();
    }

    public function updateMetadata(string $version, string $description, string $checksum): void
    {
        $stmt=$this->db->prepare('UPDATE schema_migrations SET description=?,checksum=? WHERE version=?');
        $stmt->execute([substr($description,0,255),$checksum,$version]);
        $stmt->closeCursor();
    }

    public function currentVersion(): ?string
    {
        try {
            $stmt=$this->db->query('SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1');
            $v=$stmt->fetchColumn();
            $stmt->closeCursor();
            return $v===false?null:(string)$v;
        } catch (Throwable) { return null; }
    }
}
