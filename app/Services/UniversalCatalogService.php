<?php
declare(strict_types=1);
namespace App\Services;

use App\Catalog\VariantMatrix;
use App\Repositories\CatalogRepository;
use App\Support\PublicException;
use App\Support\ProductGallery;
use PDO;

/** Alpha 3 adapter onto the existing product/variant/inventory tables. */
final class UniversalCatalogService
{
    private const LEGACY_CODES=['volume_ml','fragrance_family','concentration','fragrance_gender','longevity_score','projection_score','sillage_score','fresh_score','sweet_score','woody_score','spicy_score','season_tags','occasion_tags'];
    public function __construct(private PDO $db,private CatalogRepository $catalog,private InventoryService $inventory) {}

    public function ready(): bool
    {
        $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('catalog_attributes','catalog_attribute_options','catalog_category_attributes','catalog_product_attribute_values','catalog_variant_option_values')");
        if((int)$stmt->fetchColumn()!==5)return false;
        $preset=$this->db->query("SELECT COUNT(*) FROM catalog_attributes WHERE code IN ('size','color','storage_gb')");
        if((int)$preset->fetchColumn()!==3)return false;
        $media=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='store_product_variants' AND COLUMN_NAME IN ('main_image_path','gallery_json')");
        return (int)$media->fetchColumn()===2;
    }
    public function attributes(): array {return $this->db->query("SELECT * FROM catalog_attributes WHERE status='active' ORDER BY scope,sort_order,id")->fetchAll(PDO::FETCH_ASSOC);}
    public function options(): array {return $this->db->query("SELECT o.* FROM catalog_attribute_options o JOIN catalog_attributes a ON a.id=o.attribute_id WHERE o.status='active' AND a.status='active' ORDER BY o.attribute_id,o.sort_order,o.id")->fetchAll(PDO::FETCH_ASSOC);}
    public function assignments(int $categoryId): array
    {
        $stmt=$this->db->prepare('SELECT ca.* FROM catalog_category_attributes ca WHERE ca.category_id=? ORDER BY ca.sort_order,ca.attribute_id');
        $stmt->execute([$categoryId]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function productValues(int $productId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM catalog_product_attribute_values WHERE product_id=?');$stmt->execute([$productId]);
        $map=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$map[(int)$row['attribute_id']]=$row;return $map;
    }
    public function ensureCategory(int $categoryId): void
    {
        if($categoryId<=0)throw new PublicException('Choose a category first.');
        $s=$this->db->prepare("SELECT id FROM store_categories WHERE id=? AND status='active' LIMIT 1");$s->execute([$categoryId]);
        if(!$s->fetchColumn())throw new PublicException('Category does not exist or is archived.');
    }
    public function configureCategory(int $categoryId,array $chosen,array $axes,array $required): void
    {
        $this->ensureCategory($categoryId);
        $valid=[];foreach($this->attributes() as $row)$valid[(int)$row['id']]=$row;
        $chosen=array_values(array_unique(array_map('intval',$chosen)));
        $axes=array_map('intval',$axes);$required=array_map('intval',$required);
        if(count($chosen)>45)throw new PublicException('Too many category attributes.');
        $this->db->beginTransaction();
        try{
            // Existing product values are never deleted when assignments are changed.
            $del=$this->db->prepare('DELETE FROM catalog_category_attributes WHERE category_id=?');$del->execute([$categoryId]);
            $add=$this->db->prepare('INSERT INTO catalog_category_attributes (category_id,attribute_id,is_required,is_filterable,is_variant_axis,sort_order) VALUES(?,?,?,?,?,?)');
            foreach($chosen as $index=>$id){
                if(!isset($valid[$id]))throw new PublicException('Unknown attribute selected.');
                $row=$valid[$id];$isAxis=in_array($id,$axes,true);
                if($isAxis && ($row['scope']!=='variant'||$row['input_type']!=='select'))throw new PublicException('Only variant select fields may form SKU combinations.');
                if($isAxis && in_array((string)$row['code'],self::LEGACY_CODES,true))throw new PublicException('Legacy perfume volume is managed through the existing product editor until the backfill is verified.');
                $add->execute([$categoryId,$id,in_array($id,$required,true)?1:0,(int)$row['is_filterable'],$isAxis?1:0,$index*10]);
            }
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function addOptions(int $attributeId,string $lines): int
    {
        $s=$this->db->prepare("SELECT * FROM catalog_attributes WHERE id=? AND status='active'");$s->execute([$attributeId]);$attr=$s->fetch(PDO::FETCH_ASSOC);
        if(!$attr || $attr['input_type']!=='select')throw new PublicException('Select a valid dropdown attribute.');
        $labels=preg_split('/\r\n|\r|\n|,/u',$lines)?:[];
        $labels=array_values(array_unique(array_filter(array_map('trim',$labels),static fn($s)=>$s!=='')));
        if(count($labels)>50)throw new PublicException('Maximum 50 options at once.');
        if(!$labels)throw new PublicException('Enter at least one option.');
        $create=$this->db->prepare('INSERT INTO catalog_attribute_options(attribute_id,code,label,sort_order) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE code=code');
        $check=$this->db->prepare('SELECT label FROM catalog_attribute_options WHERE attribute_id=? AND code=?');
        $count=0;
        $this->db->beginTransaction();
        try{
            foreach($labels as $i=>$label){
                if(mb_strlen($label)>160)throw new PublicException('Option is too long.');
                $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',\App\Catalog\LegacyPerfumeMapper::textOption((string)$attr['code'],$label)['label']),'-'));
                if($slug==='')$slug='value-'.substr(hash('sha256',$label),0,16);
                $slug=mb_substr($slug,0,140);
                $check->execute([$attributeId,$slug]);$existing=$check->fetchColumn();$check->closeCursor();
                if($existing!==false && (string)$existing!==$label)throw new PublicException('Option code conflict for '.$label.'. Use a distinct option name.');
                if($existing===false){$create->execute([$attributeId,$slug,$label,$i*10]);$count++;}
            }
            $this->db->commit();return $count;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function saveProduct(array $input,?array $existing,string $uploadedImage='',?callable $beforeCommit=null,array $uploadedGallery=[]): int
    {
        $categoryId=(int)($input['category_id']??0);$this->ensureCategory($categoryId);
        $name=trim((string)($input['name']??''));
        if($name===''||mb_strlen($name)>180)throw new PublicException('Product name is required (maximum 180 characters).');
        $brand=(int)($input['brand_id']??0);
        if($brand>0){$b=$this->db->prepare("SELECT id FROM store_brands WHERE id=? AND status='active'");$b->execute([$brand]);if(!$b->fetchColumn())throw new PublicException('Select a valid brand.');}
        $status=(string)($input['status']??'draft');
        if(!in_array($status,['draft','active','hidden','archived','scheduled'],true))throw new PublicException('Invalid product status.');
        $gender=strtolower(trim((string)($input['gender']??$existing['gender']??'unisex')));
        if(!in_array($gender,['men','women','unisex'],true))throw new PublicException('Select Men, Women or Unisex for audience-specific products.');
        $short=trim((string)($input['short_description']??''));$description=trim((string)($input['description']??''));
        $seoTitle=trim((string)($input['seo_title']??$existing['seo_title']??''));
        $seoDescription=trim((string)($input['seo_description']??$existing['seo_description']??''));
        if(mb_strlen($seoTitle)>180||mb_strlen($seoDescription)>320)throw new PublicException('SEO title or description is too long.');
        if(mb_strlen($short)>500||mb_strlen($description)>20000)throw new PublicException('Product description is too long.');
        $attributes=$input['attributes']??[];if(!is_array($attributes))throw new PublicException('Invalid specifications.');
        $id=(int)($existing['id']??0);
        $galleryPlan=ProductGallery::arrange($existing,$input,$uploadedImage,$uploadedGallery);
        $galleryJson=json_encode($galleryPlan['gallery'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        if($id===0 && $status!=='draft')throw new PublicException('New products begin as Draft. Add a SKU, then publish from the editor.');
        $this->db->beginTransaction();
        try{
            if($id>0){
                // Keep fragrance columns, images, SEO and existing secondary categories intact.
                if($status==='active'){$count=$this->db->prepare('SELECT COUNT(*) FROM store_product_variants WHERE product_id=? AND status=\'active\'');$count->execute([$id]);if((int)$count->fetchColumn()===0)throw new PublicException('Add an active SKU before publishing this product.');}
                $image=$galleryPlan['main'];
                $s=$this->db->prepare('UPDATE store_products SET name=?,brand_id=?,gender=?,short_description=?,description=?,seo_title=?,seo_description=?,status=?,main_image_path=?,gallery_json=?,published_at=CASE WHEN ?=\'active\' AND published_at IS NULL THEN UTC_TIMESTAMP() WHEN ?<>\'active\' THEN NULL ELSE published_at END WHERE id=?');
                $s->execute([$name,$brand?:null,$gender,$short?:null,$description?:null,$seoTitle?:null,$seoDescription?:null,$status,$image?:null,$galleryJson,$status,$status,$id]);
                $existingCategoryIds=$this->catalog->productCategoryIds($id);
                if(!in_array($categoryId,$existingCategoryIds,true))$this->catalog->syncCategories($id,array_merge($existingCategoryIds,[$categoryId]));
                // DepartmentService::syncProduct will normalize category links in the same transaction.
            }else{
                // New universal products deliberately have NULL perfume-specific columns.
                $base=(new CatalogService($this->catalog))->productData(['name'=>$name,'brand_id'=>$brand,'gender'=>$gender,'short_description'=>$short,'description'=>$description,'seo_title'=>$seoTitle,'seo_description'=>$seoDescription,'status'=>'draft','main_image_path'=>$galleryPlan['main']]);
                $id=$this->catalog->createProductUnique($base);
                $gal=$this->db->prepare('UPDATE store_products SET gallery_json=? WHERE id=?');$gal->execute([$galleryJson,$id]);
                $this->catalog->syncCategories($id,[$categoryId]);
            }
            $this->saveProductAttributes($id,$categoryId,$attributes);
            if($beforeCommit!==null)$beforeCommit($id,$categoryId);
            $this->db->commit();return $id;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function saveProductAttributes(int $productId,int $categoryId,array $posted): void
    {
        $attrById=[];foreach($this->attributes() as $attr)$attrById[(int)$attr['id']]=$attr;
        $stmt=$this->db->prepare('DELETE FROM catalog_product_attribute_values WHERE product_id=? AND attribute_id=?');
        $put=$this->db->prepare('INSERT INTO catalog_product_attribute_values(product_id,attribute_id,option_id,value_text,value_decimal,value_boolean) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE option_id=VALUES(option_id),value_text=VALUES(value_text),value_decimal=VALUES(value_decimal),value_boolean=VALUES(value_boolean)');
        foreach($this->assignments($categoryId) as $binding){
            $id=(int)$binding['attribute_id'];$attr=$attrById[$id]??null;
            if(!$attr||$attr['scope']!=='product'||in_array((string)$attr['code'],self::LEGACY_CODES,true))continue;
            $raw=isset($posted[$id])&&is_scalar($posted[$id])?trim((string)$posted[$id]):'';
            if($raw==='' && (int)$binding['is_required']===1)throw new PublicException($attr['label'].' is required.');
            if($raw===''){$stmt->execute([$productId,$id]);continue;}
            $optionId=null;$text=null;$decimal=null;$boolean=null;
            if($attr['input_type']==='select'){
                if(!preg_match('/^[1-9][0-9]*$/',$raw))throw new PublicException('Invalid option for '.$attr['label']);
                $verify=$this->db->prepare("SELECT id FROM catalog_attribute_options WHERE id=? AND attribute_id=? AND status='active'");$verify->execute([(int)$raw,$id]);
                if(!$verify->fetchColumn())throw new PublicException('Invalid option for '.$attr['label']);$optionId=(int)$raw;
            }elseif($attr['input_type']==='number'){
                if(!preg_match('/^-?\d{1,12}(?:\.\d{1,4})?$/',$raw))throw new PublicException('Enter a valid number for '.$attr['label']);
                $decimal=$raw;
            }elseif($attr['input_type']==='boolean'){
                if(!in_array($raw,['0','1'],true))throw new PublicException('Invalid yes/no value.');$boolean=(int)$raw;
            }else{
                if(mb_strlen($raw)>500)throw new PublicException('Specification is too long: '.$attr['label']);$text=$raw;
            }
            $put->execute([$productId,$id,$optionId,$text,$decimal,$boolean]);
        }
    }
    public function generateVariants(int $productId,int $categoryId,array $posted,array $input): array
    {
        $product=$this->catalog->findProduct($productId);if(!$product)throw new PublicException('Product not found.');
        $this->ensureAssigned($productId,$categoryId);
        $bindings=$this->assignments($categoryId);$definitions=[];foreach($this->attributes() as $attr)$definitions[(int)$attr['id']]=$attr;
        $axes=[];foreach($bindings as $binding){if((int)$binding['is_variant_axis']===1)$axes[(int)$binding['attribute_id']]=true;}
        foreach($posted as $id=>$ids)if(!isset($axes[(int)$id]))throw new PublicException('Selected an attribute outside this category.');
        if(count($axes)!==count($posted))throw new PublicException('Choose values for every enabled variant attribute.');
        $selections=[];$optionMap=[];
        foreach($axes as $id=>$_){
            $attr=$definitions[$id]??null;
            if(!$attr||$attr['input_type']!=='select'||$attr['scope']!=='variant')throw new PublicException('Invalid variant configuration.');
            $selections[$id]=$posted[$id]??[];
        }
        $rows=VariantMatrix::expand($selections,60);
        foreach($this->options() as $opt)$optionMap[(int)$opt['attribute_id']][(int)$opt['id']]=$opt;
        $price=$this->money($input['price']??null,true,'Price');$sale=$this->money($input['sale_price']??'',false,'Sale price');
        $cost=$this->money($input['cost_price']??'',false,'Cost');
        if($sale!==null && (float)$sale >= (float)$price)throw new PublicException('Sale price must be lower than price.');
        $stock=$this->whole($input['stock_qty']??0,1000000,'Initial stock');
        $threshold=$this->whole($input['low_stock_threshold']??3,100000,'Low-stock threshold');
        $created=[];$skipped=0;$this->db->beginTransaction();
        try{
            $lock=$this->db->prepare('SELECT id FROM store_products WHERE id=? FOR UPDATE');$lock->execute([$productId]);if(!$lock->fetchColumn())throw new PublicException('Product not found.');$lock->closeCursor();
            $existing=$this->db->prepare('SELECT id FROM store_product_variants WHERE product_id=? AND option_signature=? LIMIT 1');
            $existingLabel=$this->db->prepare('SELECT id FROM store_product_variants WHERE product_id=? AND label=? AND option_signature IS NULL LIMIT 1');
            $addOptions=$this->db->prepare('INSERT INTO catalog_variant_option_values(variant_id,attribute_id,option_id) VALUES(?,?,?)');
            $updateSignature=$this->db->prepare('UPDATE store_product_variants SET option_signature=? WHERE id=?');
            foreach($rows as $selection){
                $labels=[];$codes=[];
                foreach($selection as $attrId=>$optionId){
                    if(!isset($optionMap[$attrId][$optionId]))throw new PublicException('One or more selected options no longer exist.');
                    $opt=$optionMap[$attrId][$optionId];$labels[]=(string)$opt['label'];$codes[(string)$definitions[$attrId]['code']]=(string)$opt['code'];
                }
                $signature=VariantMatrix::signature($codes);$label=$labels?implode(' / ',$labels):'Standard';
                if(mb_strlen($label)>100)throw new PublicException('Combined variant label is too long.');
                $existing->execute([$productId,$signature]);$duplicate=$existing->fetchColumn();$existing->closeCursor();
                $existingLabel->execute([$productId,$label]);$legacyDuplicate=$existingLabel->fetchColumn();$existingLabel->closeCursor();
                if($duplicate||$legacyDuplicate){$skipped++;continue;}
                $sku=$this->nextUniversalSku($productId,$label);
                $variant=['label'=>$label,'size_ml'=>null,'sku'=>$sku,'barcode'=>null,'cost_price'=>$cost,'price'=>$price,'sale_price'=>$sale,'currency'=>(string)(\store_runtime_settings()['currency']??'EGP'),'stock_qty'=>0,'low_stock_threshold'=>$threshold,'sort_order'=>0,'status'=>'active'];
                $id=$this->catalog->addVariant($productId,$variant);
                $updateSignature->execute([$signature,$id]);
                foreach($selection as $attrId=>$optionId)$addOptions->execute([$id,$attrId,$optionId]);
                if($stock>0)$this->inventory->setStock($id,$stock,'universal_initial_stock','Universal Product Editor initial stock',(int)($_SESSION['user_id']??0),'product',(string)$productId);
                $created[]=$id;
            }
            $this->db->commit();return ['created'=>count($created),'skipped'=>$skipped];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Archive or restore existing products atomically; restored products remain drafts. */
    public function bulkProductStatus(array $rawIds,string $action): int
    {
        if(!in_array($action,['archive','restore'],true))throw new PublicException('Choose Archive or Restore as draft.');
        if(!$rawIds || count($rawIds)>100)throw new PublicException('Select between 1 and 100 products.');
        $ids=[];
        foreach($rawIds as $raw){
            if(!is_scalar($raw) || !preg_match('/^[1-9][0-9]{0,9}$/',(string)$raw))throw new PublicException('Invalid product selection.');
            $id=(int)$raw;if($id>0)$ids[$id]=$id;
        }
        $ids=array_values($ids);sort($ids,SORT_NUMERIC);
        if(!$ids)throw new PublicException('Select at least one product.');
        $this->db->beginTransaction();
        try{
            $marks=implode(',',array_fill(0,count($ids),'?'));
            $find=$this->db->prepare('SELECT id,status FROM store_products WHERE id IN ('.$marks.') FOR UPDATE');
            $find->execute($ids);
            $rows=$find->fetchAll(PDO::FETCH_ASSOC);$find->closeCursor();
            if(count($rows)!==count($ids))throw new PublicException('One or more selected products no longer exist. Refresh the list.');
            $changed=0;
            foreach($rows as $row){
                $id=(int)$row['id'];$archived=(string)$row['status']==='archived';
                if($action==='archive'&&!$archived){if(!$this->catalog->archiveProduct($id))throw new PublicException('Could not archive a selected product.');$changed++;}
                if($action==='restore'&&$archived){if(!$this->catalog->restoreProduct($id))throw new PublicException('Could not restore a selected product.');$changed++;}
            }
            if($changed===0)throw new PublicException($action==='archive'?'The selected products are already archived.':'Select archived products to restore.');
            $this->db->commit();
            return $changed;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function updateVariant(int $productId,int $variantId,array $input,string $uploadedImage='',array $uploadedGallery=[]): void
    {
        $variant=$this->catalog->findVariant($variantId);
        if(!$variant||(int)$variant['product_id']!==$productId)throw new PublicException('Variant not found.');
        $price=$this->money($input['price']??null,true,'Price');$sale=$this->money($input['sale_price']??'',false,'Sale price');
        $cost=$this->money($input['cost_price']??'',false,'Cost');
        if($sale!==null && (float)$sale >= (float)$price)throw new PublicException('Sale price must be lower than price.');
        $stock=$this->whole($input['stock_qty']??$variant['stock_qty'],1000000,'Stock');
        $threshold=$this->whole($input['low_stock_threshold']??3,100000,'Low-stock threshold');
        $status=(string)($input['status']??$variant['status']);if(!in_array($status,['active','hidden','archived'],true))throw new PublicException('Invalid variant status.');
        $sku=strtoupper(trim((string)($input['sku']??$variant['sku'])));
        $barcode=trim((string)($input['barcode']??$variant['barcode']??''));
        if(mb_strlen($barcode)>80)throw new PublicException('Barcode is too long.');
        if($sku===''||mb_strlen($sku)>80||!preg_match('/^[A-Z0-9][A-Z0-9_.-]*$/',$sku))throw new PublicException('Enter a valid SKU (letters, numbers, dots, dashes).');
        $this->db->beginTransaction();
        try{
            $lock=$this->db->prepare('SELECT * FROM store_product_variants WHERE id=? AND product_id=? FOR UPDATE');$lock->execute([$variantId,$productId]);$locked=$lock->fetch(PDO::FETCH_ASSOC);$lock->closeCursor();
            if(!$locked)throw new PublicException('Variant no longer exists.');
            if($stock<(int)$locked['reserved_qty'])throw new PublicException('Stock cannot be lower than the reserved quantity.');
            // Gallery order and removals are limited to images already owned by this SKU.
            $media=ProductGallery::arrange($locked,$input,$uploadedImage,$uploadedGallery);
            $mediaJson=json_encode($media['gallery'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $data=['label'=>$locked['label'],'size_ml'=>$locked['size_ml'],'sku'=>$sku,'barcode'=>$barcode?:null,'cost_price'=>$cost,'price'=>$price,'sale_price'=>$sale,'currency'=>$locked['currency'],'stock_qty'=>(int)$locked['stock_qty'],'low_stock_threshold'=>$threshold,'sort_order'=>(int)$locked['sort_order'],'status'=>$status];
            $this->catalog->updateVariant($variantId,$data);
            $saveMedia=$this->db->prepare('UPDATE store_product_variants SET main_image_path=?,gallery_json=? WHERE id=? AND product_id=?');
            $saveMedia->execute([$media['main']?:null,$mediaJson,$variantId,$productId]);
            $this->inventory->setStock($variantId,$stock,'universal_stock_adjustment','Universal Product Editor stock update',(int)($_SESSION['user_id']??0),'product',(string)$productId);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function ensureAssigned(int $productId,int $categoryId): void
    {
        $s=$this->db->prepare('SELECT 1 FROM store_product_categories WHERE product_id=? AND category_id=?');$s->execute([$productId,$categoryId]);if(!$s->fetchColumn())throw new PublicException('Category is not assigned to this product.');
    }
    private function nextUniversalSku(int $productId,string $label): string
    {
        $suffix=trim((string)preg_replace('/[^A-Z0-9]+/','-',strtoupper($label)),'-');
        $suffix=substr($suffix?:'STANDARD',0,45);
        $base='UC-P'.$productId.'-'.$suffix;$candidate=$base;$n=2;
        $check=$this->db->prepare('SELECT 1 FROM store_product_variants WHERE sku=? LIMIT 1');
        while(true){$check->execute([$candidate]);$exists=(bool)$check->fetchColumn();$check->closeCursor();if(!$exists)return $candidate;
            if($n>10000)throw new PublicException('Cannot generate a unique SKU.');
            $candidate=$base.'-'.$n++;
        }
    }
    private function money(mixed $raw,bool $required,string $label): ?string
    {
        $value=is_scalar($raw)?trim((string)$raw):'';
        if($value===''&&!$required)return null;
        if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$value)||($required&&(float)$value<=0))throw new PublicException('Enter a valid '.$label.'.');
        return number_format((float)$value,2,'.','');
    }
    private function whole(mixed $raw,int $max,string $label): int
    {
        $value=is_scalar($raw)?trim((string)$raw):'';
        if(!preg_match('/^\d+$/',$value)||(int)$value>$max)throw new PublicException('Enter a valid '.$label.'.');
        return (int)$value;
    }
}
