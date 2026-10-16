<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\PublicException;
use PDO;

/** Customer-owned address book. Does not modify historical order snapshots. */
final class CustomerAddressService
{
    public function __construct(private PDO $db) {}

    public function all(int $userId): array
    {
        $q=$this->db->prepare('SELECT * FROM customer_addresses WHERE user_id=? ORDER BY is_default DESC,id DESC LIMIT 20');
        $q->execute([$userId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $userId,int $id): ?array
    {
        $q=$this->db->prepare('SELECT * FROM customer_addresses WHERE user_id=? AND id=? LIMIT 1');
        $q->execute([$userId,$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function save(int $userId,array $input): void
    {
        $id=max(0,(int)($input['id']??0));
        $str=static fn(string $key):string=>trim((string)($input[$key]??''));
        $fields=['label'=>80,'recipient_name'=>160,'phone'=>40,'governorate'=>120,'city'=>120,'address_line1'=>255,'address_line2'=>255,'building'=>80,'floor'=>40,'apartment'=>40,'postal_code'=>40,'notes'=>500];
        $row=[];
        foreach($fields as $key=>$limit){$row[$key]=$str($key);if(mb_strlen($row[$key],'UTF-8')>$limit)throw new PublicException('One of the address fields is too long.');}
        if($row['recipient_name']===''||$row['city']===''||$row['address_line1']==='')throw new PublicException('Enter recipient, city and street address.');
        if(!in_array($row['governorate'],\store_egypt_governorates(),true))throw new PublicException('Choose a valid Egyptian governorate.');
        $digits=(string)preg_replace('/\D+/','',$row['phone']);
        if(strlen($digits)<10||strlen($digits)>15)throw new PublicException('Enter a valid delivery phone number.');
        $default=!empty($input['is_default']);
        $this->db->beginTransaction();
        try {
            $lock=$this->db->prepare('SELECT id,is_default FROM customer_addresses WHERE user_id=? FOR UPDATE');
            $lock->execute([$userId]);$existing=$lock->fetchAll(PDO::FETCH_ASSOC);
            if($id!==0&&!in_array($id,array_map('intval',array_column($existing,'id')),true))throw new PublicException('Address not found.');
            if($id===0&&count($existing)>=10)throw new PublicException('You can save up to 10 delivery addresses.');
            $wasDefault=false;foreach($existing as $old){if((int)$old['id']===$id&&((int)$old['is_default']===1))$wasDefault=true;}
            $default=$default||$wasDefault||count($existing)===0;
            if($default){$reset=$this->db->prepare('UPDATE customer_addresses SET is_default=0 WHERE user_id=?');$reset->execute([$userId]);}
            $columns=array_keys($fields);
            $values=array_map(static fn(string $key)=>$row[$key],$columns);
            if($id>0){
                $assign=implode(',',array_map(static fn(string $key)=>$key.'=?',$columns));
                $sql='UPDATE customer_addresses SET '.$assign.',is_default=? WHERE id=? AND user_id=?';
                $q=$this->db->prepare($sql);$q->execute([...$values,(int)$default,$id,$userId]);
            } else {
                $q=$this->db->prepare('INSERT INTO customer_addresses (user_id,'.implode(',',$columns).',is_default) VALUES ('.implode(',',array_fill(0,count($columns)+2,'?')).')');
                $q->execute([$userId,...$values,(int)$default]);
            }
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function remove(int $userId,int $id): void
    {
        if($id<1)throw new PublicException('Choose an address to remove.');
        $this->db->beginTransaction();
        try {
            $row=$this->db->prepare('SELECT id,is_default FROM customer_addresses WHERE id=? AND user_id=? FOR UPDATE');
            $row->execute([$id,$userId]);$current=$row->fetch(PDO::FETCH_ASSOC);
            if(!$current)throw new PublicException('Address not found.');
            $del=$this->db->prepare('DELETE FROM customer_addresses WHERE id=? AND user_id=?');$del->execute([$id,$userId]);
            if((int)$current['is_default']===1){
                $next=$this->db->prepare('SELECT id FROM customer_addresses WHERE user_id=? ORDER BY id DESC LIMIT 1');$next->execute([$userId]);
                $nextId=(int)$next->fetchColumn();
                if($nextId){$promote=$this->db->prepare('UPDATE customer_addresses SET is_default=1 WHERE id=? AND user_id=?');$promote->execute([$nextId,$userId]);}
            }
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function setDefault(int $userId,int $id): void
    {
        if(!$this->get($userId,$id))throw new PublicException('Address not found.');
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('UPDATE customer_addresses SET is_default=0 WHERE user_id=?');$q->execute([$userId]);
            $q=$this->db->prepare('UPDATE customer_addresses SET is_default=1 WHERE user_id=? AND id=?');$q->execute([$userId,$id]);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
