<?php
declare(strict_types=1);
require_once __DIR__ . '/site_settings.php';
require_once __DIR__ . '/private_storage.php';

function backup_storage_dir(): string {
    return private_storage_primary_dir('backups');
}

function backup_storage_dirs(): array {
    return private_storage_dirs('backups');
}

function backup_storage_status(): array {
    return private_storage_status('backups');
}

function backup_safe_id(string $name): string {
    return basename($name);
}

function backup_file_path(string $name): ?string {
    $safe = backup_safe_id($name);
    if ($safe === '' || !preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.(?:zip|sql)$/', $safe)) return null;
    foreach(backup_storage_dirs() as $dir){
        $path = $dir . '/' . $safe;
        if(is_file($path))return $path;
    }
    return null;
}

function backup_list(): array {
    $items = [];$seen=[];
    foreach(backup_storage_dirs() as $dir){
        foreach ((array)glob($dir . '/backup-*') as $path) {
            if (!is_file($path)) continue;
            $name = basename($path);
            if(isset($seen[$name]))continue;
            if (!preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}\.(?:zip|sql)$/', $name)) continue;
            $seen[$name]=true;
            $items[] = [
                'name' => $name,
                'size' => (int)(filesize($path) ?: 0),
                'created_at' => (int)(filemtime($path) ?: 0),
                'path'=>$path,
                'external'=>private_storage_is_external($path),
            ];
        }
    }
    usort($items, static fn(array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
    return $items;
}

function backup_prune_rotation(?array $items=null): array {
    $items=$items??backup_list();
    if(!$items)return ['deleted'=>0,'kept'=>0,'errors'=>[]];
    $now=time();$deleted=0;$errors=[];$kept=0;
    foreach($items as $index=>$item){
        $ageDays=max(0,(int)floor(($now-(int)$item['created_at'])/86400));
        $mustKeep=$index<BACKUP_MINIMUM_KEEP;
        $overCount=$index>=BACKUP_MAX_FILES;
        $expired=$ageDays>BACKUP_RETENTION_DAYS;
        if(!$mustKeep && ($overCount||$expired)){
            $path=(string)($item['path']??'');
            if($path!==''&&is_file($path)&&@unlink($path)){$deleted++;continue;}
            $errors[]=(string)($item['name']??'unknown');
        }
        $kept++;
    }
    return ['deleted'=>$deleted,'kept'=>$kept,'errors'=>$errors];
}

function backup_latest_status(): array {
    $items=backup_list();$latest=$items[0]??null;
    $age=$latest?max(0,time()-(int)$latest['created_at']):null;
    return [
        'latest'=>$latest,
        'age_seconds'=>$age,
        'fresh'=>$age!==null && $age<=BACKUP_FRESHNESS_WARNING_DAYS*86400,
        'count'=>count($items),
        'storage'=>backup_storage_status(),
    ];
}

function backup_identifier(string $name): string {
    return preg_replace('/[^A-Za-z0-9._-]/', '', basename($name)) ?? '';
}

function backup_sql_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function backup_database_to_file(string $sqlPath): void {
    $pdo = db();
    $fh = @fopen($sqlPath, 'wb');
    if (!$fh) throw new RuntimeException('Could not create database backup file.');
    $snapshotStarted=false;

    try {
        // A backup is a recovery artifact, so table contents must come from one
        // database point in time instead of a sequence of unrelated live reads.
        // MySQL/InnoDB REPEATABLE READ keeps all SELECTs in this export on the
        // same snapshot while normal application writes continue.
        if(!$pdo->inTransaction()){
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            $snapshotStarted=true;
        }

        fwrite($fh, "-- Perfume Store database backup\n");
        fwrite($fh, '-- Created UTC: ' . gmdate('Y-m-d H:i:s') . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $tableRow) {
            $table = (string)($tableRow[0] ?? '');
            if ($table === '') continue;
            $qid = backup_sql_identifier($table);

            $createRow = $pdo->query('SHOW CREATE TABLE ' . $qid)->fetch(PDO::FETCH_NUM);
            $createSql = (string)($createRow[1] ?? '');
            fwrite($fh, "DROP TABLE IF EXISTS {$qid};\n{$createSql};\n\n");

            $stmt = $pdo->query('SELECT * FROM ' . $qid);
            $columns = null;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($columns === null) $columns = array_keys($row);
                $names = implode(',', array_map('backup_sql_identifier', $columns));
                $values = [];
                foreach ($columns as $column) {
                    $value = $row[$column];
                    $values[] = $value === null ? 'NULL' : $pdo->quote((string)$value);
                }
                fwrite($fh, 'INSERT INTO ' . $qid . ' (' . $names . ') VALUES (' . implode(',', $values) . ");\n");
            }
            fwrite($fh, "\n");
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        if($snapshotStarted && $pdo->inTransaction())$pdo->commit();
    } catch (Throwable $e) {
        if($snapshotStarted && $pdo->inTransaction())$pdo->rollBack();
        throw $e;
    } finally {
        fclose($fh);
    }
    @chmod($sqlPath, 0600);
}

function backup_create_snapshot(bool $includeLegacyRuntime=false): array {
    $dir = backup_storage_dir();
    if (!is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Backup storage is not writable.');
    if (!class_exists('ZipArchive')) throw new RuntimeException('ZipArchive is required for a complete recovery snapshot.');

    $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $sqlName = 'backup-' . $stamp . '.sql';
    $sqlPath = $dir . '/' . $sqlName;
    backup_database_to_file($sqlPath);

    $zipName = 'backup-' . $stamp . '.zip';
    $zipPath = $dir . '/' . $zipName;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($sqlPath);
        throw new RuntimeException('Could not create the recovery snapshot archive.');
    }

    try {
        $zip->addFile($sqlPath, 'database.sql');
        $manifest=[
            'format'=>'auraa-store-backup-v1',
            'created_utc'=>gmdate('c'),
            'archive_encrypted'=>false,
            'database'=>true,
            'site_settings'=>false,
            'private_keys'=>[],
            'support_attachments'=>0,
            'application_source'=>false,
            'storage'=>[
                'backups_external'=>private_storage_is_external($dir),
                'support_external'=>private_storage_is_external(private_storage_primary_dir('support')),
            ],
        ];

        $settings = site_settings_file();
        if (is_file($settings)) { $zip->addFile($settings, 'site-settings.json'); $manifest['site_settings']=true; }

        try{
            $secretStatus=\App\Support\SecretStore::storageStatus();
            $manifest['secret_storage']=['deployment_safe'=>!empty($secretStatus['deployment_safe']),'external_configured'=>!empty($secretStatus['external_dir'])];
            foreach(\App\Support\SecretStore::keyFiles() as $name=>$keyFile){
                if(!is_file($keyFile))continue;
                $zip->addFile($keyFile,'private-keys/'.$name);
                $manifest['private_keys'][]=$name;
            }
        }catch(Throwable $e){$manifest['secret_storage']=['deployment_safe'=>false,'error'=>$e->getMessage()];}

        $added=[];
        foreach(private_storage_dirs('support') as $source){
            foreach((array)glob($source.'/*') as $runtimeFile){
                $base=basename($runtimeFile);
                if(!is_file($runtimeFile)||isset($added[$base])||in_array($base,['.htaccess','.gitkeep'],true))continue;
                $zip->addFile($runtimeFile,'runtime/support/'.$base);
                $added[$base]=true;$manifest['support_attachments']++;
            }
        }

        $zip->addFromString('manifest.json',(json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)?:'{}')."\n");
        $zip->addFromString('RESTORE.txt', "Perfume Store recovery snapshot.\n\n1. Import database.sql into the target database.\n2. Restore site-settings.json when present.\n3. Restore private-keys/ to the configured persistent secret directory when required by encrypted settings.\n4. Restore runtime/support files to the active private support storage directory.\n5. Re-deploy the matching application source separately.\n\nThis archive is NOT encrypted. Store it privately/off-server because it can contain customer support attachments and encryption keys. It does not contain database credentials or application source code.\n");
        if (!$zip->close()) throw new RuntimeException('Could not finalize the recovery snapshot archive.');
        @chmod($zipPath,0600);
        @unlink($sqlPath);
        $rotation=backup_prune_rotation();
        return ['name'=>$zipName,'path'=>$zipPath,'warnings'=>[],'integrity'=>[],'rotation'=>$rotation];
    } catch (Throwable $e) {
        @$zip->close();@unlink($zipPath);@unlink($sqlPath);throw $e;
    }
}

function backup_delete(string $name): bool {
    $path = backup_file_path($name);
    return $path ? @unlink($path) : false;
}
