<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\MigrationRepository;
use PDO;
use RuntimeException;

final class MigrationRunner
{
    private const BASELINE_MAX_MIGRATION = 37;

    public function __construct(private PDO $db, private MigrationRepository $migrations, private string $directory) {}

    public function applyPending(): array
    {
        $this->installBaselineWhenDatabaseIsEmpty();
        $this->migrations->ensureTable();

        $lockName='auraa_schema_migration_v1';
        $lock=$this->db->prepare('SELECT GET_LOCK(?,30)');
        $lock->execute([$lockName]);
        $locked=(int)$lock->fetchColumn()===1;
        $lock->closeCursor();
        if(!$locked) throw new RuntimeException('Could not acquire the database migration lock.');

        try {
            $applied=$this->migrations->applied();
            $done=[];$skipped=[];
            $files=(array)glob(rtrim($this->directory,'/\\').'/*.sql');
            sort($files,SORT_STRING);

            foreach($files as $file){
                $base=basename($file);
                $version=preg_replace('/\.sql$/','',$base)??$base;
                $sql=(string)file_get_contents($file);
                $checksum=hash_file('sha256',$file)?:'';
                $description=$this->description($sql,$version);

                if(isset($applied[$version])){
                    $stored=(string)($applied[$version]['checksum']??'');
                    if($stored==='manual'||$stored==='baseline'||$stored===''){
                        $this->migrations->updateMetadata($version,$description,$checksum);
                    }elseif(!hash_equals($stored,$checksum)){
                        throw new RuntimeException('Applied migration checksum changed: '.$version);
                    }
                    $skipped[]=$version;
                    continue;
                }

                // Some v4.2.0 hotfix deployments manually added one or more return-refund
                // columns before migration 043 was formally recorded. Preserve the original
                // migration file/checksum, complete only the missing pieces, then record that
                // exact packaged migration as satisfied. This avoids duplicate-column failure
                // without rewriting immutable migration history.
                if($this->repairPartialReturnAuditMigration($version,$description,$checksum)){
                    $applied[$version]=['version'=>$version,'description'=>$description,'checksum'=>$checksum];
                    $done[]=$version;
                    continue;
                }

                foreach($this->splitStatements($sql) as $statement)$this->executeStatement($statement);
                $this->migrations->record($version,$description,$checksum);
                $applied[$version]=['version'=>$version,'description'=>$description,'checksum'=>$checksum];
                $done[]=$version;
            }

            return ['applied'=>$done,'skipped'=>$skipped,'current'=>$this->migrations->currentVersion()];
        } finally {
            try{
                $unlock=$this->db->prepare('SELECT RELEASE_LOCK(?)');
                $unlock->execute([$lockName]);
                $unlock->fetchColumn();
                $unlock->closeCursor();
            }catch(\Throwable){}
        }
    }

    private function installBaselineWhenDatabaseIsEmpty(): void
    {
        $hasUsers=$this->tableExists('users');
        $hasStore=$this->tableExists('store_products');

        if($hasUsers&&$hasStore)return;
        if($hasUsers!==$hasStore){
            throw new RuntimeException('Partial or legacy database schema detected. Use a clean database for a fresh install, or upgrade an existing store database that is already at schema 036 or newer.');
        }

        $baseline=dirname($this->directory).'/baseline/perfume_store_v2_3_0.sql';
        if(!is_file($baseline))throw new RuntimeException('Fresh-install database baseline is missing.');
        $sql=(string)file_get_contents($baseline);
        if(trim($sql)==='')throw new RuntimeException('Fresh-install database baseline is empty.');

        foreach($this->splitStatements($sql) as $statement)$this->executeStatement($statement);
        if(!$this->tableExists('users')||!$this->tableExists('store_products')){
            throw new RuntimeException('Fresh-install database baseline did not create the expected store schema.');
        }

        // The v2.3 baseline already contains the result of migrations 025–037.
        // Mark that history as satisfied before the normal migration loop runs,
        // otherwise a fresh database would replay old DDL against the baseline.
        $this->migrations->ensureTable();
        $this->recordBaselineHistory();
    }

    private function recordBaselineHistory(): void
    {
        $files=(array)glob(rtrim($this->directory,'/\\').'/*.sql');
        sort($files,SORT_STRING);
        foreach($files as $file){
            $base=basename($file);
            if(!preg_match('/^(\d+)_/', $base, $m)) continue;
            if((int)$m[1] > self::BASELINE_MAX_MIGRATION) continue;
            $version=preg_replace('/\.sql$/','',$base)??$base;
            $sql=(string)file_get_contents($file);
            $this->migrations->recordIfMissing($version,$this->description($sql,$version),'baseline');
        }
    }

    private function repairPartialReturnAuditMigration(string $version,string $description,string $checksum): bool
    {
        if($version!=='043_v4_2_0_stabilization'||!$this->tableExists('store_returns'))return false;

        $columns=[
            'refund_method'=>"ALTER TABLE store_returns ADD COLUMN refund_method VARCHAR(40) NULL AFTER refund_amount",
            'refund_reference'=>"ALTER TABLE store_returns ADD COLUMN refund_reference VARCHAR(160) NULL AFTER refund_method",
            'refunded_at'=>"ALTER TABLE store_returns ADD COLUMN refunded_at DATETIME NULL AFTER refund_reference",
        ];
        $present=[];
        foreach(array_keys($columns) as $column)$present[$column]=$this->columnExists('store_returns',$column);
        if(!in_array(true,$present,true))return false;

        foreach($columns as $column=>$statement){
            if(!$present[$column])$this->executeStatement($statement);
        }
        $this->executeStatement("INSERT INTO store_settings(setting_key,setting_value) VALUES ('return_window_days','14') ON DUPLICATE KEY UPDATE setting_value=setting_value");
        $this->migrations->record($version,$description,$checksum);
        return true;
    }

    private function tableExists(string $table): bool
    {
        try{
            $stmt=$this->db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            $exists=(int)$stmt->fetchColumn()>0;
            $stmt->closeCursor();
            return $exists;
        }catch(\Throwable){return false;}
    }

    private function columnExists(string $table,string $column): bool
    {
        try{
            $stmt=$this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
            $stmt->execute([$table,$column]);
            $exists=(int)$stmt->fetchColumn()>0;
            $stmt->closeCursor();
            return $exists;
        }catch(\Throwable){return false;}
    }

    /**
     * Execute one migration statement and always release any result set before
     * the next statement. MySQL's PREPARE/EXECUTE commands can expose a result
     * cursor even for DDL; leaving it open triggers SQLSTATE 2014 on the next
     * query when native prepares/unbuffered results are in use.
     */
    private function executeStatement(string $statement): void
    {
        $stmt=$this->db->query($statement);
        if($stmt!==false){
            $stmt->closeCursor();
        }
    }

    private function description(string $sql,string $version): string
    {
        foreach(preg_split('/\R/',$sql)?:[] as $line){
            $line=trim($line);
            if(str_starts_with($line,'--')){
                $text=trim(substr($line,2));
                if($text!==''&&!str_starts_with(strtolower($text),'auraa perfumes'))return substr($text,0,255);
            }
        }
        return str_replace('_',' ',$version);
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        $out=[];$buf='';$quote=null;$lineComment=false;$blockComment=false;$len=strlen($sql);
        for($i=0;$i<$len;$i++){
            $c=$sql[$i];$n=$i+1<$len?$sql[$i+1]:'';
            if($lineComment){if($c==="\n"){$lineComment=false;$buf.=$c;}continue;}
            if($blockComment){if($c==='*'&&$n==='/'){$blockComment=false;$i++;}continue;}
            if($quote===null){
                if(($c==='-'&&$n==='-'&&($i+2>=$len||ctype_space($sql[$i+2])))||$c==='#'){$lineComment=true;if($c==='-')$i++;continue;}
                if($c==='/'&&$n==='*'){$blockComment=true;$i++;continue;}
                if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}
                if($c===';'){if(trim($buf)!=='')$out[]=trim($buf);$buf='';continue;}
                $buf.=$c;continue;
            }
            $buf.=$c;
            if($c==='\\'&&$quote!=="`"){$i++;if($i<$len)$buf.=$sql[$i];continue;}
            if($c===$quote){if($i+1<$len&&$sql[$i+1]===$quote){$buf.=$sql[++$i];continue;}$quote=null;}
        }
        if(trim($buf)!=='')$out[]=trim($buf);
        return $out;
    }
}
