<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\TwoFactorRepository;
use App\Support\SecretStore;

final class TwoFactorService
{
    public function __construct(private TwoFactorRepository $repo) {}
    public function schemaReady(): bool { return $this->repo->schemaReady(); }
    public function encryptedStorageReady(): bool { return SecretStore::available() && $this->repo->secretStorageLength() >= 128; }
    public function stateRow(int $userId): array
    {
        return $this->decryptStateRow($userId,$this->repo->stateRow($userId));
    }
    public function stateRowForUpdate(int $userId): array
    {
        return $this->decryptStateRow($userId,$this->repo->stateRowForUpdate($userId));
    }
    private function decryptStateRow(int $userId,array $row): array
    {
        $stored=(string)($row['admin_totp_secret']??'');
        if($stored!==''){
            if(str_starts_with($stored,'v1:')){
                $row['admin_totp_secret']=SecretStore::decrypt($stored,'totp');
            }else{
                // One-time compatibility path for a TOTP secret created before v1.7.2.
                // Keep legacy 2FA usable during a staggered deploy; once migration 025
                // widens the column, transparently replace the plaintext with ciphertext.
                if($this->encryptedStorageReady())$this->repo->updateSecret($userId,SecretStore::encrypt($stored,'totp'));
                $row['admin_totp_secret']=$stored;
            }
        }
        return $row;
    }
    public function enable(int $userId,string $secret,string $hashJson): void
    {
        if(!$this->encryptedStorageReady())throw new \RuntimeException('Run migration 025 and ensure OpenSSL is available before enabling 2FA.');
        $this->repo->enable($userId,SecretStore::encrypt($secret,'totp'),$hashJson);
    }
    public function disable(int $userId): void { $this->repo->disable($userId); }
    public function updateRecoveryHashes(int $userId,string $hashJson): void { $this->repo->updateRecoveryHashes($userId,$hashJson); }
}
