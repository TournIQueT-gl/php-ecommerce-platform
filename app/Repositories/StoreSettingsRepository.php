<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class StoreSettingsRepository
{
    public function __construct(private PDO $db) {}
    public function schemaReady(): bool {try{$s=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_settings'");return (int)$s->fetchColumn()===1;}catch(\Throwable){return false;}}
    public function all(): array {$rows=$this->db->query('SELECT setting_key,setting_value FROM store_settings ORDER BY setting_key')->fetchAll();$out=[];foreach($rows as $r)$out[(string)$r['setting_key']]=$r['setting_value'];return $out;}
    public function setMany(array $settings,int $adminId): void {$s=$this->db->prepare('INSERT INTO store_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');foreach($settings as $k=>$v)$s->execute([$k,$v,$adminId]);}
}
