<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class TwoFactorRepository
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME IN ('admin_totp_secret','admin_totp_enabled','admin_totp_recovery_codes','admin_totp_enabled_at')");
            return (int)$stmt->fetchColumn() === 4;
        } catch (Throwable) {
            return false;
        }
    }

    public function secretStorageLength(): int
    {
        try {
            $stmt=$this->db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='admin_totp_secret' LIMIT 1");
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    public function stateRow(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT admin_totp_enabled,admin_totp_secret,admin_totp_recovery_codes,admin_totp_enabled_at FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    public function stateRowForUpdate(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT admin_totp_enabled,admin_totp_secret,admin_totp_recovery_codes,admin_totp_enabled_at FROM users WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    public function enable(int $userId, string $secret, string $recoveryHashesJson): void
    {
        $this->db->prepare('UPDATE users SET admin_totp_secret=?,admin_totp_enabled=1,admin_totp_recovery_codes=?,admin_totp_enabled_at=UTC_TIMESTAMP() WHERE id=? AND is_admin=1')
            ->execute([$secret,$recoveryHashesJson,$userId]);
    }

    public function updateSecret(int $userId,string $secret): void
    {
        $this->db->prepare('UPDATE users SET admin_totp_secret=? WHERE id=? AND is_admin=1')->execute([$secret,$userId]);
    }

    public function disable(int $userId): void
    {
        $this->db->prepare('UPDATE users SET admin_totp_secret=NULL,admin_totp_enabled=0,admin_totp_recovery_codes=NULL,admin_totp_enabled_at=NULL WHERE id=?')->execute([$userId]);
    }

    public function updateRecoveryHashes(int $userId, string $recoveryHashesJson): void
    {
        $this->db->prepare('UPDATE users SET admin_totp_recovery_codes=? WHERE id=?')->execute([$recoveryHashesJson,$userId]);
    }
}
