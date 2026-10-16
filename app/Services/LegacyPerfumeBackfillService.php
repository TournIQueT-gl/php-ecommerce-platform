<?php
declare(strict_types=1);

namespace App\Services;

use App\Catalog\LegacyPerfumeMapper as Mapper;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Opt-in, transaction-scoped legacy perfume data import.
 * Does not mutate legacy product/variant values, primary keys, order or stock rows.
 * Never invoked automatically by the migration runner or public requests.
 */
final class LegacyPerfumeBackfillService
{
    private const ATTRIBUTES = [
        'volume_ml','fragrance_family','concentration','fragrance_gender',
        'longevity_score','projection_score','sillage_score','fresh_score',
        'sweet_score','woody_score','spicy_score','season_tags','occasion_tags',
    ];

    public function __construct(private PDO $db) {}

    public function run(bool $apply = false): array
    {
        $schema = $this->fetchScalar('SELECT DATABASE()');
        $database = (string) $schema;
        $lockName = 'v5_perfume_backfill_'.substr(hash('sha256',$database),0,25);
        if ($apply) {
            $lock=$this->db->prepare('SELECT GET_LOCK(?,10)');
            $lock->execute([$lockName]);
            $acquired=(int)$lock->fetchColumn()===1;
            $lock->closeCursor();
            if (!$acquired) throw new RuntimeException('Could not obtain backfill lock.');
        }
        try {
            return $this->perform($apply, $database);
        } finally {
            if ($apply) {
                try {$stmt=$this->db->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lockName]);$stmt->closeCursor();}catch(Throwable){}
            }
        }
    }

    private function perform(bool $apply, string $database): array
    {
        $this->assertSchema();
        if ($apply) $this->db->beginTransaction();
        try {
            $before=$this->legacyFingerprint();
            $definitions=[];
            foreach ($this->fetchRows('SELECT id,code,scope,input_type FROM catalog_attributes') as $row) {
                $definitions[(string)$row['code']] = $row;
            }
            foreach (self::ATTRIBUTES as $code) {
                if (!isset($definitions[$code])) throw new RuntimeException('Required definition not installed: '.$code.'. Run migrations first.');
                $expectedScope=$code==='volume_ml'?'variant':'product';
                $expectedType=in_array($code,['volume_ml','fragrance_family','concentration','fragrance_gender'],true)?'select':(in_array($code,Mapper::SCORE_COLUMNS,true)?'number':'text');
                if ($definitions[$code]['scope']!==$expectedScope || $definitions[$code]['input_type']!==$expectedType) {
                    throw new RuntimeException('Existing catalog attribute definition is incompatible: '.$code);
                }
                $definitions[$code]=(int)$definitions[$code]['id'];
            }
            $families=[];
            foreach ($this->fetchRows('SELECT id,name FROM fragrance_families') as $row) {
                $families[(int)$row['id']] = (string)$row['name'];
            }
            $products=$this->fetchRows('SELECT id,fragrance_family_id,concentration,gender,longevity_score,projection_score,sillage_score,fresh_score,sweet_score,woody_score,spicy_score,season_tags,occasion_tags FROM store_products ORDER BY id');
            $variants=$this->fetchRows('SELECT id,product_id,size_ml,option_signature FROM store_product_variants ORDER BY product_id,id');
            $existingProducts=[];
            foreach ($this->fetchRows('SELECT product_id,attribute_id,option_id,value_text,value_decimal FROM catalog_product_attribute_values') as $row) {
                $existingProducts[$row['product_id'].':'.$row['attribute_id']]=$row;
            }
            $existingVariants=[];
            foreach ($this->fetchRows('SELECT variant_id,attribute_id,option_id FROM catalog_variant_option_values') as $row) {
                $existingVariants[$row['variant_id'].':'.$row['attribute_id']]=(int)$row['option_id'];
            }
            $existingOptions=[];
            foreach ($this->fetchRows('SELECT id,attribute_id,code,label,numeric_value FROM catalog_attribute_options') as $row) {
                $existingOptions[$row['attribute_id'].':'.$row['code']] = $row;
            }
            $stats=['products_examined'=>count($products),'variants_examined'=>count($variants),
                'mapped_product_values'=>0,'mapped_variant_volumes'=>0,
                'variants_without_volume'=>0,'products_without_perfume_signals'=>0,
                'existing_values_preserved'=>0,'conflicts'=>[],
                'fragrance_note_links_preserved'=> (int)$this->fetchScalar('SELECT COUNT(*) FROM store_product_notes')];
            $variantAnalysis=Mapper::analyzeLegacyVariants($variants);
            $stats['conflicts']=array_merge($stats['conflicts'],$variantAnalysis['conflicts']);
            $candidateProducts=[];
            foreach ($variantAnalysis['mapped'] as $mapped) $candidateProducts[(int)$mapped['product_id']]=true;
            foreach ($products as $p) {
                $id=(int)$p['id'];
                $isPerfume=isset($candidateProducts[$id]) || $p['fragrance_family_id'] !== null || trim((string)$p['concentration'])!=='';
                if (!$isPerfume) {$stats['products_without_perfume_signals']++;continue;}
                $candidateProducts[$id]=true;
                $values=[];
                $familyId=$p['fragrance_family_id'] !== null?(int)$p['fragrance_family_id']:0;
                if ($familyId>0) {
                    if (!isset($families[$familyId])) $stats['conflicts'][]='Product #'.$id.': missing fragrance family #'.$familyId;
                    else $values['fragrance_family']=['option'=>['code'=>'family_'.$familyId,'label'=>$families[$familyId],'numeric_value'=>null]];
                }
                foreach (['concentration'=>'concentration','gender'=>'fragrance_gender'] as $column=>$code) {
                    $raw=trim((string)($p[$column]??''));
                    if ($raw!=='') $values[$code]=['option'=>Mapper::textOption($code,$raw)];
                }
                foreach (Mapper::SCORE_COLUMNS as $column) {
                    if ($p[$column] !== null && $p[$column] !== '') $values[$column]=['decimal'=>(string)$p[$column]];
                }
                foreach (['season_tags','occasion_tags'] as $column) {
                    $raw=trim((string)($p[$column]??''));
                    if ($raw!=='') $values[$column]=['text'=>$raw];
                }
                foreach ($values as $code=>$value) {
                    $attributeId=$definitions[$code];
                    $key=$id.':'.$attributeId;
                    if (isset($existingProducts[$key])) {
                        $this->assertExistingProductValue($existingProducts[$key],$attributeId,$value,$existingOptions,$stats,$id,$code);
                        continue;
                    }
                    $stats['mapped_product_values']++;
                    if ($apply) {
                        $optionId=isset($value['option']) ? $this->upsertOption($attributeId,$value['option'],$existingOptions) : null;
                        $stmt=$this->db->prepare('INSERT INTO catalog_product_attribute_values (product_id,attribute_id,option_id,value_text,value_decimal) VALUES (?,?,?,?,?)');
                        $stmt->execute([$id,$attributeId,$optionId,$value['text']??null,$value['decimal']??null]);
                        $stmt->closeCursor();
                    }
                }
            }
            foreach ($variants as $v) {
                $id=(int)$v['id'];$productId=(int)$v['product_id'];
                if (!isset($candidateProducts[$productId])) continue;
                if (!isset($variantAnalysis['mapped'][$id])) {$stats['variants_without_volume']++;continue;}
                $volume=$variantAnalysis['mapped'][$id]['option'];
                $key=$id.':'.$definitions['volume_ml'];
                if (isset($existingVariants[$key])) {
                    $oldId=$existingVariants[$key];
                    $oldOption=$this->optionById($oldId,$existingOptions);
                    if (!$oldOption || (string)$oldOption['code']!==$volume['code']) $stats['conflicts'][]='Variant #'.$id.': an existing volume option differs';
                    else $stats['existing_values_preserved']++;
                } else {
                    $stats['mapped_variant_volumes']++;
                }
            }
            // Never partially apply a conflicting backfill.
            if ($apply && $stats['conflicts']) throw new RuntimeException('Backfill blocked: '.count($stats['conflicts']).' conflicts; run --dry-run for details.');
            if ($apply) {
                foreach ($variants as $v) {
                    $id=(int)$v['id'];$productId=(int)$v['product_id'];
                    if (!isset($candidateProducts[$productId]))continue;
                    if (!isset($variantAnalysis['mapped'][$id]))continue;
                    $volume=$variantAnalysis['mapped'][$id]['option'];
                    $attributeId=$definitions['volume_ml'];
                    if (!isset($existingVariants[$id.':'.$attributeId])) {
                        $optionId=$this->upsertOption($attributeId,$volume,$existingOptions);
                        $stmt=$this->db->prepare('INSERT INTO catalog_variant_option_values (variant_id,attribute_id,option_id) VALUES (?,?,?)');
                        $stmt->execute([$id,$attributeId,$optionId]);$stmt->closeCursor();
                    }
                    $sig=$variantAnalysis['mapped'][$id]['signature'];
                    $stmt=$this->db->prepare('UPDATE store_product_variants SET option_signature=? WHERE id=? AND (option_signature IS NULL OR option_signature=?)');
                    $stmt->execute([$sig,$id,$sig]);$stmt->closeCursor();
                }
                $this->associateExistingPerfumeCategories(array_keys($candidateProducts),$definitions);
                $after=$this->legacyFingerprint();
                if ($before!==$after) throw new RuntimeException('Legacy integrity fingerprint changed; transaction rolled back.');
                $this->db->commit();
            }
            return ['mode'=>$apply?'applied':'dry-run','schema_database'=>$database,'legacy_fingerprint_before'=>$before,
                'legacy_fingerprint_after'=>$apply?$after:$before,'stats'=>$stats,
                'warning'=>'No live production write is authorized. Missing sizes remain unmapped; fragrance-note relationships stay in their existing module.'];
        } catch(Throwable $e) {
            if ($apply && $this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    private function assertSchema(): void
    {
        $required=['catalog_attributes','catalog_attribute_options','catalog_category_attributes',
            'catalog_product_attribute_values','catalog_variant_option_values'];
        $placeholders=implode(',',array_fill(0,count($required),'?'));
        $stmt=$this->db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.$placeholders.')');
        $stmt->execute($required);$available=$stmt->fetchAll(PDO::FETCH_COLUMN);$stmt->closeCursor();
        if ($missing=array_diff($required,$available))throw new RuntimeException('Missing schema: '.implode(', ',$missing).'. Run bin/migrate.php first on a CLONE.');
    }

    private function upsertOption(int $attr,array $option,array &$existing): int
    {
        $key=$attr.':'.$option['code'];
        if (isset($existing[$key])) return (int)$existing[$key]['id'];
        $stmt=$this->db->prepare('INSERT INTO catalog_attribute_options(attribute_id,code,label,numeric_value) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $stmt->execute([$attr,$option['code'],$option['label'],$option['numeric_value']]);$stmt->closeCursor();
        $id=(int)$this->db->lastInsertId();
        $existing[$key]=['id'=>$id,'attribute_id'=>$attr,'code'=>$option['code'],'label'=>$option['label'],'numeric_value'=>$option['numeric_value']];
        return $id;
    }

    private function optionById(int $optionId,array $options): ?array
    {
        foreach ($options as $option) if ((int)$option['id']===$optionId)return $option;
        return null;
    }

    private function assertExistingProductValue(array $existing,int $attributeId,array $value,array $options,array &$stats,int $productId,string $code): void
    {
        $same=false;
        if (isset($value['option'])) {
            $old=$existing['option_id']!==null?$this->optionById((int)$existing['option_id'],$options):null;
            $same=$old!==null && (int)$old['attribute_id']===$attributeId && $old['code']===$value['option']['code'];
        } elseif (isset($value['decimal'])) {
            $same=$existing['value_decimal']!==null && (float)$existing['value_decimal']===(float)$value['decimal'];
        } elseif (isset($value['text'])) {
            $same=(string)$existing['value_text']===$value['text'];
        }
        if (!$same)$stats['conflicts'][]='Product #'.$productId.': stored attribute '.$code.' differs from legacy';
        else $stats['existing_values_preserved']++;
    }

    private function associateExistingPerfumeCategories(array $productIds,array $definitions): void
    {
        if (!$productIds)return;
        // Use existing numerical identifiers, never user-supplied category strings.
        $categoryIds=[];
        foreach (array_chunk(array_map('intval',$productIds),250) as $chunk) {
            $stmt=$this->db->prepare('SELECT DISTINCT category_id FROM store_product_categories WHERE product_id IN ('.implode(',',array_fill(0,count($chunk),'?')).')');
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $categoryIds[(int)$id]=true;
            $stmt->closeCursor();
        }
        $insert=$this->db->prepare('INSERT INTO catalog_category_attributes(category_id,attribute_id,is_filterable,is_variant_axis) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE category_id=category_id');
        foreach (array_keys($categoryIds) as $categoryId) {
            foreach (self::ATTRIBUTES as $code) {
                $insert->execute([$categoryId,$definitions[$code],
                    in_array($code,['volume_ml','fragrance_family','concentration','fragrance_gender'],true)?1:0,
                    $code==='volume_ml'?1:0]);
                $insert->closeCursor();
            }
        }
    }

    /** Release cursors explicitly for older MySQL/MariaDB native-prepared drivers. */
    private function fetchRows(string $sql): array
    {
        $stmt=$this->db->query($sql);
        try { return $stmt->fetchAll(PDO::FETCH_ASSOC); }
        finally { $stmt->closeCursor(); }
    }

    private function fetchScalar(string $sql): mixed
    {
        $stmt=$this->db->query($sql);
        try { return $stmt->fetchColumn(); }
        finally { $stmt->closeCursor(); }
    }

    private function legacyFingerprint(): array
    {
        $queries=[
            'products'=>'SELECT COUNT(*) FROM store_products',
            'variants'=>'SELECT COUNT(*) FROM store_product_variants',
            'orders'=>'SELECT COUNT(*) FROM store_orders',
            'order_items'=>'SELECT COUNT(*) FROM store_order_items',
            'stock_movements'=>'SELECT COUNT(*) FROM store_inventory_movements',
            'stock_total'=>'SELECT COALESCE(SUM(stock_qty),0) FROM store_product_variants',
            'reserved_total'=>'SELECT COALESCE(SUM(reserved_qty),0) FROM store_product_variants',
            'stock_movement_total'=>'SELECT COALESCE(SUM(quantity_delta),0) FROM store_inventory_movements',
            'order_amount_total'=>'SELECT COALESCE(SUM(grand_total),0) FROM store_orders',
            'product_id_total'=>'SELECT COALESCE(SUM(id),0) FROM store_products',
            'variant_id_total'=>'SELECT COALESCE(SUM(id),0) FROM store_product_variants',
            'variant_price_total'=>'SELECT COALESCE(SUM(price),0) FROM store_product_variants',
            'variant_sale_total'=>'SELECT COALESCE(SUM(sale_price),0) FROM store_product_variants',
            'variant_cost_total'=>'SELECT COALESCE(SUM(cost_price),0) FROM store_product_variants',
            'fragrance_note_links'=>'SELECT COUNT(*) FROM store_product_notes',
            'inventory_movement_id_total'=>'SELECT COALESCE(SUM(id),0) FROM store_inventory_movements',
        ];
        $out=[];
        foreach ($queries as $label=>$query){$stmt=$this->db->query($query);$out[$label]=(string)$stmt->fetchColumn();$stmt->closeCursor();}
        return $out;
    }
}
