<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final class HomepageRepository
{
    public function __construct(private PDO $db) {}
    public function schemaReady(): bool { try{$s=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('homepage_sections','homepage_section_items')");return (int)$s->fetchColumn()===2;}catch(\Throwable){return false;} }
    public function sections(): array { return $this->db->query('SELECT * FROM homepage_sections ORDER BY sort_order,id')->fetchAll(); }
    public function findSection(int $id): ?array {$s=$this->db->prepare('SELECT * FROM homepage_sections WHERE id=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();return is_array($r)?$r:null;}
    public function items(int $sectionId): array
    {
        $sql="SELECT i.*,p.name AS product_name,p.main_image_path AS product_image,p.status AS product_status,p.published_at AS product_published_at,
                     b.name AS brand_name,b.slug AS brand_slug,b.logo_path AS brand_logo_path,b.cover_path AS brand_cover_path,
                     (SELECT p2.main_image_path FROM store_products p2
                       WHERE p2.brand_id=COALESCE(i.brand_id,p.brand_id)
                         AND p2.status='active'
                         AND NULLIF(TRIM(p2.main_image_path),'') IS NOT NULL
                       ORDER BY p2.is_featured DESC,p2.id
                       LIMIT 1) AS brand_product_image_path,
                     c.name AS category_name,c.slug AS category_slug,c.image_path AS category_image_path
                FROM homepage_section_items i
                LEFT JOIN store_products p ON p.id=i.product_id
                LEFT JOIN store_brands b ON b.id=COALESCE(i.brand_id,p.brand_id)
                LEFT JOIN store_categories c ON c.id=i.category_id
               WHERE i.section_id=?
               ORDER BY i.sort_order,i.id";
        $s=$this->db->prepare($sql);$s->execute([$sectionId]);return $s->fetchAll();
    }

    public function findItem(int $id): ?array {$s=$this->db->prepare('SELECT * FROM homepage_section_items WHERE id=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();return is_array($r)?$r:null;}

    public function uniqueSectionKey(string $base,?int $excludeId=null): string
    {
        $base=trim($base,'-')!==''?trim($base,'-'):'section';$key=$base;$n=2;
        while(true){$sql='SELECT id FROM homepage_sections WHERE section_key=?'.($excludeId!==null?' AND id<>?':'').' LIMIT 1';$q=$this->db->prepare($sql);$args=[$key];if($excludeId!==null)$args[]=$excludeId;$q->execute($args);if(!$q->fetchColumn())return $key;$key=$base.'-'.$n++;}
    }
    public function nextSectionSort(): int { $v=$this->db->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM homepage_sections')->fetchColumn();return (int)$v; }

    public function itemBelongsToSection(int $itemId,int $sectionId): bool
    {
        $q=$this->db->prepare('SELECT 1 FROM homepage_section_items WHERE id=? AND section_id=? LIMIT 1');$q->execute([$itemId,$sectionId]);return $q->fetchColumn()!==false;
    }

    public function referenceExists(string $kind,int $id,bool $requireActive=false): bool
    {
        if($id<=0)return false;
        [$table,$column]=match($kind){'product'=>['store_products','id'],'brand'=>['store_brands','id'],'category'=>['store_categories','id'],default=>['','']};
        if($table==='')return false;
        $status=$requireActive?"status='active'":"status<>'archived'";
        $published=$kind==='product'&&$requireActive?" AND (published_at IS NULL OR published_at<=UTC_TIMESTAMP())":'';
        $q=$this->db->prepare("SELECT 1 FROM {$table} WHERE {$column}=? AND {$status}{$published} LIMIT 1");$q->execute([$id]);return $q->fetchColumn()!==false;
    }

    public function duplicateReferenceExists(int $sectionId,string $field,int $value,?int $excludeItemId=null): bool
    {
        if(!in_array($field,['product_id','brand_id','category_id'],true)||$value<=0)return false;
        $sql="SELECT 1 FROM homepage_section_items WHERE section_id=? AND {$field}=?".($excludeItemId!==null?' AND id<>?':'').' LIMIT 1';
        $q=$this->db->prepare($sql);$args=[$sectionId,$value];if($excludeItemId!==null)$args[]=$excludeItemId;$q->execute($args);return $q->fetchColumn()!==false;
    }

    public function renderableItemCount(int $sectionId,string $sectionType): int
    {
        $join='';$extra='';
        if($sectionType==='product_carousel'){$join=' JOIN store_products p ON p.id=i.product_id';$extra=" AND p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())";}
        elseif($sectionType==='brand_grid'){$join=' JOIN store_brands b ON b.id=i.brand_id';$extra=" AND b.status='active'";}
        elseif($sectionType==='category_grid'){$join=' JOIN store_categories c ON c.id=i.category_id';$extra=" AND c.status='active'";}
        elseif(in_array($sectionType,['scent_grid','notes_grid','wardrobe_grid','custom'],true)){$extra=" AND NULLIF(TRIM(i.title),'') IS NOT NULL";}
        elseif(in_array($sectionType,['hero','editorial_banner','gift_banner'],true)){$extra=" AND (NULLIF(TRIM(i.title),'') IS NOT NULL OR NULLIF(TRIM(i.subtitle),'') IS NOT NULL OR NULLIF(TRIM(i.eyebrow),'') IS NOT NULL OR NULLIF(TRIM(i.image_path),'') IS NOT NULL OR NULLIF(TRIM(i.mobile_image_path),'') IS NOT NULL OR NULLIF(TRIM(i.cta_label),'') IS NOT NULL)";}
        $q=$this->db->prepare("SELECT COUNT(*) FROM homepage_section_items i{$join} WHERE i.section_id=? AND i.status='active'{$extra}");$q->execute([$sectionId]);return (int)$q->fetchColumn();
    }

    public function automaticProductSourceCount(string $source): int
    {
        $where="p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())";
        if($source==='featured')$where.=' AND p.is_featured=1';
        elseif($source==='bestselling')$where.=' AND p.is_bestseller=1';
        elseif($source==='new')$where.=' AND p.is_new_arrival=1';
        elseif($source==='sale')$where.=" AND EXISTS(SELECT 1 FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active' AND v.sale_price IS NOT NULL AND v.sale_price>0 AND v.sale_price<v.price)";
        else return 0;
        return (int)$this->db->query("SELECT COUNT(*) FROM store_products p WHERE {$where}")->fetchColumn();
    }

    public function publishSectionNow(int $id): void
    {
        $q=$this->db->prepare("UPDATE homepage_sections SET status='active',starts_at=NULL WHERE id=?");$q->execute([$id]);
    }
    public function moveSection(int $id,string $direction): void
    {
        $this->atomic(function()use($id,$direction):void{
            $rows=$this->db->query('SELECT id FROM homepage_sections ORDER BY sort_order,id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);$ids=array_map('intval',$rows);$idx=array_search($id,$ids,true);if($idx===false)return;$target=$direction==='up'?$idx-1:$idx+1;if($target<0||$target>=count($ids))return;[$ids[$idx],$ids[$target]]=[$ids[$target],$ids[$idx]];$q=$this->db->prepare('UPDATE homepage_sections SET sort_order=? WHERE id=?');foreach($ids as $i=>$sid)$q->execute([($i+1)*10,$sid]);
        });
    }
    public function setSectionStatus(int $id,string $status): void { if(!in_array($status,['active','hidden','draft','scheduled','archived'],true))$status='hidden';$q=$this->db->prepare('UPDATE homepage_sections SET status=? WHERE id=?');$q->execute([$status,$id]); }
    public function nextItemSort(int $sectionId): int { $q=$this->db->prepare('SELECT COALESCE(MAX(sort_order),0)+10 FROM homepage_section_items WHERE section_id=?');$q->execute([$sectionId]);return (int)$q->fetchColumn(); }
    public function moveItem(int $sectionId,int $id,string $direction): void
    {
        $this->atomic(function()use($sectionId,$id,$direction):void{
            $q=$this->db->prepare('SELECT id FROM homepage_section_items WHERE section_id=? ORDER BY sort_order,id FOR UPDATE');$q->execute([$sectionId]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$idx=array_search($id,$ids,true);if($idx===false)return;$target=$direction==='up'?$idx-1:$idx+1;if($target<0||$target>=count($ids))return;[$ids[$idx],$ids[$target]]=[$ids[$target],$ids[$idx]];$u=$this->db->prepare('UPDATE homepage_section_items SET sort_order=? WHERE id=?');foreach($ids as $i=>$iid)$u->execute([($i+1)*10,$iid]);
        });
    }


    public function reorderSections(array $ids): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($v)=>$v>0)));if(!$ids)return;
        $this->atomic(function()use($ids):void{
            $known=array_map('intval',$this->db->query('SELECT id FROM homepage_sections ORDER BY sort_order,id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN));
            if(count($ids)!==count($known)||array_diff($ids,$known)||array_diff($known,$ids))throw new \App\Support\PublicException('Section order changed. Refresh the builder and try again.');
            $q=$this->db->prepare('UPDATE homepage_sections SET sort_order=? WHERE id=?');foreach($ids as $i=>$id)$q->execute([($i+1)*10,$id]);
        });
    }
    public function reorderItems(int $sectionId,array $ids): void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn($v)=>$v>0)));if(!$ids)return;
        $this->atomic(function()use($sectionId,$ids):void{
            $q=$this->db->prepare('SELECT id FROM homepage_section_items WHERE section_id=? ORDER BY sort_order,id FOR UPDATE');$q->execute([$sectionId]);$known=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
            if(count($ids)!==count($known)||array_diff($ids,$known)||array_diff($known,$ids))throw new \App\Support\PublicException('Content order changed. Refresh the builder and try again.');
            $u=$this->db->prepare('UPDATE homepage_section_items SET sort_order=? WHERE id=?');foreach($ids as $i=>$id)$u->execute([($i+1)*10,$id]);
        });
    }
    public function duplicateSection(int $sectionId,int $adminId): ?int
    {
        $source=$this->findSection($sectionId);if(!$source)return null;
        $this->db->beginTransaction();
        try{
            $key=$this->uniqueSectionKey((string)$source['section_key'].'-copy');
            $data=$source;$data['section_key']=$key;$data['title']=trim((string)($source['title']??''))!==''?(string)$source['title'].' Copy':'Section Copy';$data['sort_order']=$this->nextSectionSort();$data['status']='draft';$data['starts_at']=null;$data['ends_at']=null;
            $newId=$this->createSection($data,$adminId);
            $q=$this->db->prepare('SELECT * FROM homepage_section_items WHERE section_id=? ORDER BY sort_order,id');$q->execute([$sectionId]);
            foreach($q->fetchAll() as $item){$d=$item;$this->addItem($newId,$d);}
            $this->db->commit();return $newId;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function createSection(array $d,int $adminId): int {$s=$this->db->prepare('INSERT INTO homepage_sections(section_key,section_type,title,title_ar,subtitle,subtitle_ar,settings_json,sort_order,status,starts_at,ends_at,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute([$d['section_key'],$d['section_type'],$d['title'],$d['title_ar'],$d['subtitle'],$d['subtitle_ar'],$d['settings_json'],$d['sort_order'],$d['status'],$d['starts_at'],$d['ends_at'],$adminId,$adminId]);return (int)$this->db->lastInsertId();}
    public function createSectionsAtomically(array $rows,int $adminId): bool { $this->db->beginTransaction();try{$existing=$this->db->query('SELECT id FROM homepage_sections ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_COLUMN);if($existing){$this->db->rollBack();return false;}foreach($rows as $d)$this->createSection($d,$adminId);$this->db->commit();return true;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;} }
    public function updateSection(int $id,array $d,int $adminId): bool {$s=$this->db->prepare('UPDATE homepage_sections SET section_key=?,section_type=?,title=?,title_ar=?,subtitle=?,subtitle_ar=?,settings_json=?,sort_order=?,status=?,starts_at=?,ends_at=?,updated_by=? WHERE id=?');$s->execute([$d['section_key'],$d['section_type'],$d['title'],$d['title_ar'],$d['subtitle'],$d['subtitle_ar'],$d['settings_json'],$d['sort_order'],$d['status'],$d['starts_at'],$d['ends_at'],$adminId,$id]);return $s->rowCount()>0;}
    public function deleteSection(int $id): bool {$s=$this->db->prepare('DELETE FROM homepage_sections WHERE id=?');$s->execute([$id]);return $s->rowCount()>0;}
    public function addItem(int $sectionId,array $d): int {$s=$this->db->prepare('INSERT INTO homepage_section_items(section_id,product_id,brand_id,category_id,title,title_ar,subtitle,subtitle_ar,image_path,mobile_image_path,eyebrow,cta_label,cta_url,settings_json,sort_order,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute([$sectionId,$d['product_id'],$d['brand_id'],$d['category_id'],$d['title'],$d['title_ar'],$d['subtitle'],$d['subtitle_ar'],$d['image_path'],$d['mobile_image_path'],$d['eyebrow'],$d['cta_label'],$d['cta_url'],$d['settings_json'],$d['sort_order'],$d['status']]);return (int)$this->db->lastInsertId();}
    public function updateItem(int $id,array $d): bool {$s=$this->db->prepare('UPDATE homepage_section_items SET product_id=?,brand_id=?,category_id=?,title=?,title_ar=?,subtitle=?,subtitle_ar=?,image_path=?,mobile_image_path=?,eyebrow=?,cta_label=?,cta_url=?,settings_json=?,sort_order=?,status=? WHERE id=?');$s->execute([$d['product_id'],$d['brand_id'],$d['category_id'],$d['title'],$d['title_ar'],$d['subtitle'],$d['subtitle_ar'],$d['image_path'],$d['mobile_image_path'],$d['eyebrow'],$d['cta_label'],$d['cta_url'],$d['settings_json'],$d['sort_order'],$d['status'],$id]);return $s->rowCount()>0;}
    public function deleteItem(int $id): bool {$s=$this->db->prepare('DELETE FROM homepage_section_items WHERE id=?');$s->execute([$id]);return $s->rowCount()>0;}
    public function brands(): array {return $this->db->query("SELECT id,name,slug,logo_path,cover_path,status FROM store_brands WHERE status<>'archived' ORDER BY name,id")->fetchAll();}
    public function categories(): array {return $this->db->query("SELECT id,name,status FROM store_categories WHERE status<>'archived' ORDER BY name,id")->fetchAll();}
    public function notes(): array {return $this->db->query("SELECT id,name,slug,image_path,status FROM fragrance_notes WHERE status<>'archived' ORDER BY name,id")->fetchAll();}
    public function findNote(int $id): ?array {$q=$this->db->prepare("SELECT id,name,slug,image_path,status FROM fragrance_notes WHERE id=? AND status<>'archived' LIMIT 1");$q->execute([$id]);$r=$q->fetch();return is_array($r)?$r:null;}
    private function atomic(callable $work): void
    {
        $owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();
        try{$work();if($owns)$this->db->commit();}
        catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

}
