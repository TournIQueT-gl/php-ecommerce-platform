<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;
use App\Support\PublicException;

final class CatalogRepository
{
    public function __construct(private PDO $db) {}

    public function schemaReady(): bool
    {
        try{$s=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('store_products','store_product_variants','store_brands')");return (int)$s->fetchColumn()===3;}catch(\Throwable){return false;}
    }

    public function products(int $limit=250): array
    {
        $limit=max(1,min(1000,$limit));
        return $this->db->query("SELECT p.*,b.name AS brand_name,(SELECT COUNT(*) FROM store_product_variants v WHERE v.product_id=p.id) AS variant_count,(SELECT SUM(GREATEST(v.stock_qty,0)) FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS available_stock,(SELECT MIN(CASE WHEN v.sale_price IS NOT NULL AND v.sale_price>0 THEN v.sale_price ELSE v.price END) FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS from_price,(SELECT v2.currency FROM store_product_variants v2 WHERE v2.product_id=p.id AND v2.status='active' ORDER BY v2.sort_order,v2.id LIMIT 1) AS currency FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id ORDER BY p.updated_at DESC,p.id DESC LIMIT {$limit}")->fetchAll();
    }

    /** @return array{rows:array,total:int,page:int,per_page:int,total_pages:int} */
    public function productPage(string $query='',string $status='',int $page=1,int $perPage=30,int $departmentId=0): array
    {
        $query=trim($query);$status=trim($status);$page=max(1,$page);$perPage=max(10,min(100,$perPage));
        $where=[];$args=[];
        if($query!==''){$where[]='(p.name LIKE ? OR p.slug LIKE ? OR b.name LIKE ? OR EXISTS(SELECT 1 FROM store_product_variants sv WHERE sv.product_id=p.id AND sv.sku LIKE ?))';$like='%'.$query.'%';array_push($args,$like,$like,$like,$like);}
        if(in_array($status,['active','draft','hidden','archived'],true)){$where[]='p.status=?';$args[]=$status;}
        if($departmentId>0){$where[]='EXISTS(SELECT 1 FROM commerce_product_departments pd WHERE pd.product_id=p.id AND pd.department_id=?)';$args[]=$departmentId;}
        elseif($departmentId===-1){$where[]='NOT EXISTS(SELECT 1 FROM commerce_product_departments pd WHERE pd.product_id=p.id)';}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->db->prepare("SELECT COUNT(*) FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id{$whereSql}");$count->execute($args);$total=(int)$count->fetchColumn();
        $totalPages=max(1,(int)ceil($total/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;
        $sql="SELECT p.*,b.name AS brand_name,(SELECT COUNT(*) FROM store_product_variants v WHERE v.product_id=p.id) AS variant_count,(SELECT SUM(GREATEST(v.stock_qty,0)) FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS available_stock,(SELECT MIN(CASE WHEN v.sale_price IS NOT NULL AND v.sale_price>0 THEN v.sale_price ELSE v.price END) FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS from_price,(SELECT v2.currency FROM store_product_variants v2 WHERE v2.product_id=p.id AND v2.status='active' ORDER BY v2.sort_order,v2.id LIMIT 1) AS currency FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id{$whereSql} ORDER BY p.updated_at DESC,p.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt=$this->db->prepare($sql);$stmt->execute($args);
        return ['rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'total_pages'=>$totalPages];
    }

    public function brands(bool $includeArchived=false): array { return $this->taxonomyRows('store_brands',$includeArchived); }
    public function categories(bool $includeArchived=false): array { return $this->taxonomyRows('store_categories',$includeArchived); }
    public function families(bool $includeArchived=false): array { return $this->taxonomyRows('fragrance_families',$includeArchived); }
    public function notes(bool $includeArchived=false): array { return $this->taxonomyRows('fragrance_notes',$includeArchived); }

    public function findProduct(int $id): ?array
    {
        $s=$this->db->prepare('SELECT * FROM store_products WHERE id=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();return is_array($r)?$r:null;
    }

    public function createProduct(array $data): int
    {
        $s=$this->db->prepare('INSERT INTO store_products(brand_id,fragrance_family_id,name,name_ar,slug,short_description,short_description_ar,description,description_ar,gender,concentration,longevity_score,projection_score,sillage_score,fresh_score,sweet_score,woody_score,spicy_score,season_tags,occasion_tags,main_image_path,gallery_json,seo_title,seo_description,status,is_featured,is_bestseller,is_new_arrival,is_exclusive,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$data['brand_id'],$data['fragrance_family_id'],$data['name'],$data['name_ar'],$data['slug'],$data['short_description'],$data['short_description_ar'],$data['description'],$data['description_ar'],$data['gender'],$data['concentration'],$data['longevity_score'],$data['projection_score'],$data['sillage_score'],$data['fresh_score'],$data['sweet_score'],$data['woody_score'],$data['spicy_score'],$data['season_tags'],$data['occasion_tags'],$data['main_image_path'],$data['gallery_json'],$data['seo_title'],$data['seo_description'],$data['status'],$data['is_featured'],$data['is_bestseller'],$data['is_new_arrival'],$data['is_exclusive'],$data['published_at']]);
        return (int)$this->db->lastInsertId();
    }


    public function createProductUnique(array $data): int
    {
        $base=trim((string)($data['slug']??''),' -');if($base==='')$base='product';
        for($attempt=0;$attempt<8;$attempt++){
            if($attempt>0)$data['slug']=$this->uniqueProductSlug($base);
            try{return $this->createProduct($data);}
            catch(\PDOException $e){
                $driver=(int)($e->errorInfo[1]??0);$message=(string)$e->getMessage();
                if($driver!==1062||stripos($message,'uq_store_products_slug')===false)throw $e;
            }
        }
        throw new \RuntimeException('Could not allocate a unique product slug.');
    }

    public function updateProduct(int $id,array $data): bool
    {
        $s=$this->db->prepare('UPDATE store_products SET brand_id=?,fragrance_family_id=?,name=?,name_ar=?,slug=?,short_description=?,short_description_ar=?,description=?,description_ar=?,gender=?,concentration=?,longevity_score=?,projection_score=?,sillage_score=?,fresh_score=?,sweet_score=?,woody_score=?,spicy_score=?,season_tags=?,occasion_tags=?,main_image_path=?,gallery_json=?,seo_title=?,seo_description=?,status=?,is_featured=?,is_bestseller=?,is_new_arrival=?,is_exclusive=?,published_at=? WHERE id=?');
        $s->execute([$data['brand_id'],$data['fragrance_family_id'],$data['name'],$data['name_ar'],$data['slug'],$data['short_description'],$data['short_description_ar'],$data['description'],$data['description_ar'],$data['gender'],$data['concentration'],$data['longevity_score'],$data['projection_score'],$data['sillage_score'],$data['fresh_score'],$data['sweet_score'],$data['woody_score'],$data['spicy_score'],$data['season_tags'],$data['occasion_tags'],$data['main_image_path'],$data['gallery_json'],$data['seo_title'],$data['seo_description'],$data['status'],$data['is_featured'],$data['is_bestseller'],$data['is_new_arrival'],$data['is_exclusive'],$data['published_at'],$id]);
        return $s->rowCount()>0;
    }


    public function uniqueProductSlug(string $base,?int $excludeId=null): string
    {
        $base=trim($base,'-')!==''?trim($base,'-'):'product';$slug=$base;$n=2;
        while(true){$sql='SELECT id FROM store_products WHERE slug=?'.($excludeId!==null?' AND id<>?':'').' LIMIT 1';$q=$this->db->prepare($sql);$args=[$slug];if($excludeId!==null)$args[]=$excludeId;$q->execute($args);if(!$q->fetchColumn())return $slug;$slug=$base.'-'.$n++;}
    }

    public function uniqueBrandSlug(string $base): string
    {
        $base=trim($base,'-')!==''?trim($base,'-'):'brand';$slug=$base;$n=2;
        while(true){$q=$this->db->prepare('SELECT id FROM store_brands WHERE slug=? LIMIT 1');$q->execute([$slug]);if(!$q->fetchColumn())return $slug;$slug=$base.'-'.$n++;}
    }

    public function nextVariantSku(int $productId,string $label=''): string
    {
        $suffix=strtoupper((string)preg_replace('/[^A-Z0-9]+/','-',strtoupper(trim($label))));$suffix=trim($suffix,'-');if($suffix==='')$suffix='VAR';
        $base='HO-P'.$productId.'-'.$suffix;$sku=$base;$n=2;
        while(true){$q=$this->db->prepare('SELECT id FROM store_product_variants WHERE sku=? LIMIT 1');$q->execute([$sku]);if(!$q->fetchColumn())return $sku;$sku=$base.'-'.$n++;}
    }

    public function archiveProduct(int $id): bool { $s=$this->db->prepare("UPDATE store_products SET status='archived' WHERE id=?");$s->execute([$id]);return $s->rowCount()>0; }
    public function restoreProduct(int $id): bool { $s=$this->db->prepare("UPDATE store_products SET status='draft' WHERE id=? AND status='archived'");$s->execute([$id]);return $s->rowCount()>0; }
    /**
     * Permanent deletion is only for empty, archived catalog records.
     * Financial, customer and stock history must retain stable product/variant IDs.
     * InnoDB row locks also prevent concurrent history inserts referencing these rows.
     */
    public function deleteProduct(int $id): bool
    {
        $owns=!$this->db->inTransaction();
        if($owns)$this->db->beginTransaction();
        try{
            $record=$this->db->prepare('SELECT status FROM store_products WHERE id=? FOR UPDATE');
            $record->execute([$id]);
            $status=$record->fetchColumn();
            if($status===false){if($owns)$this->db->commit();return false;}
            if($status!=='archived')throw new PublicException('Archive the product before permanently deleting it.');

            // Lock variants before checking history to avoid concurrent inventory/order races.
            $lock=$this->db->prepare('SELECT id FROM store_product_variants WHERE product_id=? FOR UPDATE');
            $lock->execute([$id]);$lock->fetchAll(PDO::FETCH_COLUMN);
            if($this->catalogReferenceExists(
                'SELECT 1 FROM store_inventory_movements m JOIN store_product_variants v ON v.id=m.variant_id WHERE v.product_id=? LIMIT 1',[$id]
            )||$this->catalogReferenceExists(
                'SELECT 1 FROM store_order_items oi WHERE oi.product_id=? OR oi.variant_id IN (SELECT id FROM store_product_variants WHERE product_id=?) LIMIT 1',[$id,$id]
            )||$this->catalogReferenceExists('SELECT 1 FROM store_reviews WHERE product_id=? LIMIT 1',[$id])){
                throw new PublicException('This product has orders, reviews or inventory movements. Archive it instead; business history cannot be deleted.');
            }
            if($this->catalogReferenceExists(
                'SELECT 1 FROM store_product_variants WHERE product_id=? AND (stock_qty<>0 OR reserved_qty<>0) LIMIT 1',[$id]
            ))throw new PublicException('This product still has stock or reservations. Resolve quantities and archive it instead.');

            $stmt=$this->db->prepare('DELETE FROM store_products WHERE id=?');
            $stmt->execute([$id]);$deleted=$stmt->rowCount()>0;
            if($owns)$this->db->commit();
            return $deleted;
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function catalogReferenceExists(string $sql,array $params): bool
    {
        $query=$this->db->prepare($sql);$query->execute($params);
        return $query->fetchColumn()!==false;
    }

    public function productInventoryHistoryCount(int $id): int { $s=$this->db->prepare('SELECT COUNT(*) FROM store_inventory_movements m INNER JOIN store_product_variants v ON v.id=m.variant_id WHERE v.product_id=?');$s->execute([$id]);return (int)$s->fetchColumn(); }

    public function variants(int $productId): array { $s=$this->db->prepare('SELECT * FROM store_product_variants WHERE product_id=? ORDER BY sort_order,id');$s->execute([$productId]);return $s->fetchAll(); }
    public function findVariant(int $id): ?array { $s=$this->db->prepare('SELECT * FROM store_product_variants WHERE id=? LIMIT 1');$s->execute([$id]);$r=$s->fetch();return is_array($r)?$r:null; }

    public function addVariant(int $productId,array $data): int
    {
        $s=$this->db->prepare('INSERT INTO store_product_variants(product_id,label,size_ml,sku,barcode,cost_price,price,sale_price,currency,stock_qty,low_stock_threshold,sort_order,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $s->execute([$productId,$data['label'],$data['size_ml'],$data['sku'],$data['barcode'],$data['cost_price'],$data['price'],$data['sale_price'],$data['currency'],$data['stock_qty'],$data['low_stock_threshold'],$data['sort_order'],$data['status']]);
        return (int)$this->db->lastInsertId();
    }

    public function updateVariant(int $id,array $data): bool
    {
        $s=$this->db->prepare('UPDATE store_product_variants SET label=?,size_ml=?,sku=?,barcode=?,cost_price=?,price=?,sale_price=?,currency=?,stock_qty=?,low_stock_threshold=?,sort_order=?,status=? WHERE id=?');
        $s->execute([$data['label'],$data['size_ml'],$data['sku'],$data['barcode'],$data['cost_price'],$data['price'],$data['sale_price'],$data['currency'],$data['stock_qty'],$data['low_stock_threshold'],$data['sort_order'],$data['status'],$id]);
        return $s->rowCount()>0;
    }

    public function archiveVariant(int $id): bool { $s=$this->db->prepare("UPDATE store_product_variants SET status='archived' WHERE id=?");$s->execute([$id]);return $s->rowCount()>0; }
    public function restoreVariant(int $id): bool { $s=$this->db->prepare("UPDATE store_product_variants SET status='active' WHERE id=? AND status='archived'");$s->execute([$id]);return $s->rowCount()>0; }
    public function deleteVariant(int $id): bool
    {
        $owns=!$this->db->inTransaction();
        if($owns)$this->db->beginTransaction();
        try{
            $lock=$this->db->prepare('SELECT status,stock_qty,reserved_qty FROM store_product_variants WHERE id=? FOR UPDATE');
            $lock->execute([$id]);$variant=$lock->fetch(PDO::FETCH_ASSOC);
            if(!is_array($variant)){if($owns)$this->db->commit();return false;}
            if((string)$variant['status']!=='archived')throw new PublicException('Archive the variant before permanently deleting it.');
            if($this->catalogReferenceExists('SELECT 1 FROM store_inventory_movements WHERE variant_id=? LIMIT 1',[$id])
                ||$this->catalogReferenceExists('SELECT 1 FROM store_order_items WHERE variant_id=? LIMIT 1',[$id])){
                throw new PublicException('This variant has orders or inventory movements. Archive it instead; business history cannot be deleted.');
            }
            if((int)$variant['stock_qty']!==0||(int)$variant['reserved_qty']!==0){
                throw new PublicException('This variant still has stock or reservations. Resolve quantities and archive it instead.');
            }
            $stmt=$this->db->prepare('DELETE FROM store_product_variants WHERE id=?');
            $stmt->execute([$id]);$deleted=$stmt->rowCount()>0;
            if($owns)$this->db->commit();return $deleted;
        }catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function variantInventoryHistoryCount(int $id): int { $s=$this->db->prepare('SELECT COUNT(*) FROM store_inventory_movements WHERE variant_id=?');$s->execute([$id]);return (int)$s->fetchColumn(); }


    public function productCategoryIds(int $productId): array { $s=$this->db->prepare('SELECT category_id FROM store_product_categories WHERE product_id=? ORDER BY category_id');$s->execute([$productId]);return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN)); }
    public function productNotes(int $productId): array { $s=$this->db->prepare('SELECT note_id,note_layer,sort_order FROM store_product_notes WHERE product_id=? ORDER BY FIELD(note_layer,\'top\',\'heart\',\'base\',\'other\'),sort_order,note_id');$s->execute([$productId]);return $s->fetchAll(); }
    public function syncCategories(int $productId,array $categoryIds): void { $categoryIds=array_values(array_unique(array_filter(array_map('intval',$categoryIds),static fn($v)=>$v>0)));$owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();try{$d=$this->db->prepare('DELETE FROM store_product_categories WHERE product_id=?');$d->execute([$productId]);if($categoryIds){$i=$this->db->prepare('INSERT INTO store_product_categories(product_id,category_id) VALUES(?,?)');foreach($categoryIds as $cid)$i->execute([$productId,$cid]);}if($owns)$this->db->commit();}catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}
    public function syncNotes(int $productId,array $layers): void { $allowed=['top','heart','base','other'];$owns=!$this->db->inTransaction();if($owns)$this->db->beginTransaction();try{$d=$this->db->prepare('DELETE FROM store_product_notes WHERE product_id=?');$d->execute([$productId]);$i=$this->db->prepare('INSERT INTO store_product_notes(product_id,note_id,note_layer,sort_order) VALUES(?,?,?,?)');foreach($allowed as $layer){$ids=$layers[$layer]??[];if(!is_array($ids))continue;$sort=0;foreach(array_values(array_unique(array_filter(array_map('intval',$ids),static fn($v)=>$v>0))) as $nid){$i->execute([$productId,$nid,$layer,$sort++]);}}if($owns)$this->db->commit();}catch(\Throwable $e){if($owns&&$this->db->inTransaction())$this->db->rollBack();throw $e;}}

    public function createBrand(string $name,?string $nameAr,string $slug): int
    {
        $s=$this->db->prepare("INSERT INTO store_brands(name,name_ar,slug,status) VALUES(?,?,?,'active')");$s->execute([$name,$nameAr,$slug]);return (int)$this->db->lastInsertId();
    }
    public function createTaxonomy(string $type,string $name,?string $nameAr,string $slug,int $sortOrder,string $status,string $imagePath=''): int
    {
        [$table,$imageColumn]=$this->taxonomyTable($type);
        if($imageColumn!==null){$sql="INSERT INTO {$table}(name,name_ar,slug,{$imageColumn},sort_order,status) VALUES(?,?,?,?,?,?)";$args=[$name,$nameAr,$slug,$imagePath!==''?$imagePath:null,$sortOrder,$status];}
        else{$sql="INSERT INTO {$table}(name,name_ar,slug,sort_order,status) VALUES(?,?,?,?,?)";$args=[$name,$nameAr,$slug,$sortOrder,$status];}
        $s=$this->db->prepare($sql);$s->execute($args);return (int)$this->db->lastInsertId();
    }

    public function updateTaxonomy(string $type,int $id,string $name,?string $nameAr,string $slug,int $sortOrder,string $status,string $imagePath=''): bool
    {
        [$table,$imageColumn]=$this->taxonomyTable($type);
        if($imageColumn!==null){$sql="UPDATE {$table} SET name=?,name_ar=?,slug=?,{$imageColumn}=?,sort_order=?,status=? WHERE id=?";$args=[$name,$nameAr,$slug,$imagePath!==''?$imagePath:null,$sortOrder,$status,$id];}
        else{$sql="UPDATE {$table} SET name=?,name_ar=?,slug=?,sort_order=?,status=? WHERE id=?";$args=[$name,$nameAr,$slug,$sortOrder,$status,$id];}
        $s=$this->db->prepare($sql);$s->execute($args);return true;
    }

    public function taxonomySlugExists(string $type,string $slug,int $excludeId=0): bool
    {
        [$table]=$this->taxonomyTable($type);
        $sql="SELECT id FROM {$table} WHERE slug=?".($excludeId>0?' AND id<>?':'').' LIMIT 1';
        $args=$excludeId>0?[$slug,$excludeId]:[$slug];
        $stmt=$this->db->prepare($sql);$stmt->execute($args);return (bool)$stmt->fetchColumn();
    }

    public function archiveTaxonomy(string $type,int $id): bool
    {
        [$table]=$this->taxonomyTable($type);$s=$this->db->prepare("UPDATE {$table} SET status='archived' WHERE id=?");$s->execute([$id]);return $s->rowCount()>0;
    }

    public function deleteTaxonomy(string $type,int $id): bool
    {
        [$table]=$this->taxonomyTable($type);$s=$this->db->prepare("DELETE FROM {$table} WHERE id=?");$s->execute([$id]);return $s->rowCount()>0;
    }

    /** @return array<int,int> */
    public function taxonomyUsageCounts(string $type): array
    {
        $sql=match($type){
            'brand'=>"SELECT b.id,((SELECT COUNT(*) FROM store_products p WHERE p.brand_id=b.id)+(SELECT COUNT(*) FROM homepage_section_items h WHERE h.brand_id=b.id)) usage_count FROM store_brands b",
            'category'=>"SELECT c.id,((SELECT COUNT(*) FROM store_product_categories pc WHERE pc.category_id=c.id)+(SELECT COUNT(*) FROM homepage_section_items h WHERE h.category_id=c.id)+(SELECT COUNT(*) FROM store_categories child WHERE child.parent_id=c.id)) usage_count FROM store_categories c",
            'family'=>"SELECT f.id,(SELECT COUNT(*) FROM store_products p WHERE p.fragrance_family_id=f.id) usage_count FROM fragrance_families f",
            'note'=>"SELECT n.id,(SELECT COUNT(*) FROM store_product_notes pn WHERE pn.note_id=n.id) usage_count FROM fragrance_notes n",
            default=>throw new \InvalidArgumentException('Invalid taxonomy type.'),
        };
        $rows=$this->db->query($sql)->fetchAll();$out=[];foreach($rows as $row)$out[(int)$row['id']]=(int)$row['usage_count'];return $out;
    }


    public function taxonomyById(string $type,int $id): ?array
    {
        [$table]=$this->taxonomyTable($type);$q=$this->db->prepare("SELECT * FROM {$table} WHERE id=? LIMIT 1");$q->execute([$id]);$row=$q->fetch();return is_array($row)?$row:null;
    }

    public function recordRedirect(string $sourcePath,string $destinationUrl,int $httpCode=301): void
    {
        $sourcePath=trim($sourcePath);$destinationUrl=trim($destinationUrl);if($sourcePath===''||$destinationUrl===''||$sourcePath===$destinationUrl)return;
        $httpCode=in_array($httpCode,[301,302,307,308],true)?$httpCode:301;
        $q=$this->db->prepare("INSERT INTO store_redirects(source_path,destination_url,http_code,status) VALUES(?,?,?,'active') ON DUPLICATE KEY UPDATE destination_url=VALUES(destination_url),http_code=VALUES(http_code),status='active'");$q->execute([$sourcePath,$destinationUrl,$httpCode]);
    }

    private function taxonomyRows(string $table,bool $includeArchived): array
    {
        $allowed=['store_brands','store_categories','fragrance_families','fragrance_notes'];if(!in_array($table,$allowed,true))throw new \InvalidArgumentException('Invalid taxonomy table.');
        $where=$includeArchived?'':" WHERE status<>'archived'";
        return $this->db->query("SELECT * FROM {$table}{$where} ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'hidden' THEN 1 ELSE 2 END,sort_order,name,id")->fetchAll();
    }

    private function taxonomyTable(string $type): array
    {
        return match($type){
            'brand'=>['store_brands','logo_path'],
            'category'=>['store_categories','image_path'],
            'family'=>['fragrance_families',null],
            'note'=>['fragrance_notes','image_path'],
            default=>throw new \InvalidArgumentException('Invalid taxonomy type.'),
        };
    }

}
