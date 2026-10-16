<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findForSession(int $userId): ?array
    {
        try {
            $stmt=$this->db->prepare('SELECT id,username,email,email_verified_at,is_admin,admin_role,is_active,notes,last_login_at,created_at FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
        } catch (\PDOException) {
            $stmt=$this->db->prepare('SELECT id,username,NULL AS email,NULL AS email_verified_at,is_admin,NULL AS admin_role,is_active,notes,last_login_at,created_at FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
        }
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function findCredentialsByUsername(string $username): ?array
    {
        try {
            $stmt=$this->db->prepare('SELECT id,username,email,email_verified_at,password_hash,is_active,is_admin,admin_role FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$username]);
        } catch (\PDOException) {
            $stmt=$this->db->prepare('SELECT id,username,NULL AS email,NULL AS email_verified_at,password_hash,is_active,is_admin,NULL AS admin_role FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$username]);
        }
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function findAdminIdentity(int $userId): ?array
    {
        try {
            $stmt=$this->db->prepare('SELECT id,username,is_admin,admin_role,is_active FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
        } catch (\PDOException) {
            $stmt=$this->db->prepare('SELECT id,username,is_admin,NULL AS admin_role,is_active FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
        }
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function usernameExists(string $username): bool
    {
        $stmt=$this->db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $stmt->execute([$username]);
        return (bool)$stmt->fetchColumn();
    }

    public function emailColumnsReady(): bool
    {
        try {
            $stmt=$this->db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name IN ('email','email_verified_at')");
            $stmt->execute();
            return (int)$stmt->fetchColumn()===2;
        } catch (\Throwable) {
            return false;
        }
    }

    public function idByUsername(string $username): ?int
    {
        $stmt=$this->db->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
        $stmt->execute([trim($username)]);
        $id=$stmt->fetchColumn();
        return $id!==false?(int)$id:null;
    }

    public function usernameOrEmail(string $identity): ?array
    {
        $identity=trim($identity);
        if($identity==='')return null;
        try {
            $stmt=$this->db->prepare('SELECT id,username,email,email_verified_at,is_active FROM users WHERE username=? OR email=? LIMIT 1');
            $stmt->execute([$identity,strtolower($identity)]);
        } catch (\PDOException) {
            $stmt=$this->db->prepare('SELECT id,username,NULL AS email,NULL AS email_verified_at,is_active FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$identity]);
        }
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function create(string $username,string $passwordHash,?string $email=null): int
    {
        $email=$email!==null&&trim($email)!==''?strtolower(trim($email)):null;
        try {
            $stmt=$this->db->prepare('INSERT INTO users(username,email,password_hash,is_admin,is_active) VALUES(?,?,?,0,1)');
            $stmt->execute([$username,$email,$passwordHash]);
        } catch (\PDOException $e) {
            if($email!==null)throw $e;
            $stmt=$this->db->prepare('INSERT INTO users(username,password_hash,is_admin,is_active) VALUES(?,?,0,1)');
            $stmt->execute([$username,$passwordHash]);
        }
        return (int)$this->db->lastInsertId();
    }

    public function touchLastLogin(int $userId): void
    {
        $this->db->prepare('UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$userId]);
    }

    public function passwordHash(int $userId): string
    {
        $stmt=$this->db->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        return (string)($stmt->fetchColumn()?:'');
    }

    public function updatePasswordHash(int $userId,string $passwordHash): void
    {
        $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$passwordHash,$userId]);
    }

    public function activeAdminPasswordHash(int $userId): string
    {
        $stmt=$this->db->prepare('SELECT password_hash FROM users WHERE id=? AND is_admin=1 AND is_active=1 LIMIT 1');
        $stmt->execute([$userId]);
        return (string)($stmt->fetchColumn()?:'');
    }

    public function isActiveAdmin(int $userId): bool
    {
        $stmt=$this->db->prepare('SELECT is_admin,is_active FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row=$stmt->fetch();
        return is_array($row)&&(bool)$row['is_admin']&&(bool)$row['is_active'];
    }

    public function findForAdmin(int $userId): ?array
    {
        $emailSelect=$this->emailColumnsReady()?'email,email_verified_at,':'NULL AS email,NULL AS email_verified_at,';
        $stmt=$this->db->prepare('SELECT id,username,'.$emailSelect.'is_admin,admin_role,is_active,notes,last_login_at,created_at FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row=$stmt->fetch();
        return is_array($row)?$row:null;
    }

    public function lockActiveAdminInvariant(): void
    {
        $stmt=$this->db->query('SELECT id FROM users WHERE is_admin=1 AND is_active=1 ORDER BY id FOR UPDATE');
        $stmt->fetchAll();
    }

    public function activeAdminCount(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users WHERE is_admin=1 AND is_active=1 AND admin_role IN ('owner','admin','support')")->fetchColumn();
    }

    public function ownerCount(): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM users WHERE is_admin=1 AND is_active=1 AND admin_role='owner'")->fetchColumn();
    }

    public function setAdminRole(int $userId,?string $role): void
    {
        $role=$role!==null?strtolower(trim($role)):null;
        if($role===''||$role==='none')$role=null;
        $this->db->prepare('UPDATE users SET is_admin=?,admin_role=? WHERE id=?')->execute([$role!==null?1:0,$role,$userId]);
    }


    public function accessDirectory(): array
    {
        $emailSelect=$this->emailColumnsReady()?'email,':'NULL AS email,';
        return $this->db->query('SELECT id,username,'.$emailSelect.'is_admin,admin_role,is_active FROM users ORDER BY username ASC LIMIT 500')->fetchAll();
    }

    public function adminDirectory(): array
    {
        $emailSelect=$this->emailColumnsReady()?'email,email_verified_at,':'NULL AS email,NULL AS email_verified_at,';
        return $this->db->query('SELECT id,username,'.$emailSelect.'is_admin,admin_role,is_active,last_login_at,created_at FROM users WHERE is_admin=1 ORDER BY username ASC LIMIT 300')->fetchAll();
    }
}
