<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\CatalogRepository;
use App\Support\PublicException;
use PDO;

/** Safe quick setup for the universal editor, backed by the existing taxonomy tables. */
final class UniversalSetupService
{
    public function __construct(private PDO $db, private CatalogRepository $catalog) {}

    /** Validate a custom category field without modifying the database. */
    public static function attributeDefinition(array $input): array
    {
        $label=trim((string)($input['attribute_label']??''));
        if($label==='' || mb_strlen($label)>140 || preg_match('/\p{L}/u',$label)!==1)
            throw new PublicException('Enter an attribute name (1–140 characters).');
        $code=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','_',$label),'_'));
        if($code==='' || mb_strlen($code)>100)
            throw new PublicException('Use an English attribute name so it has a valid URL-safe code.');
        $type=(string)($input['attribute_input_type']??'text');
        $scope=(string)($input['attribute_scope']??'product');
        if(!in_array($type,['text','select','number','boolean'],true) || !in_array($scope,['product','variant'],true))
            throw new PublicException('Choose a valid attribute type and scope.');
        if($scope==='variant' && $type!=='select')
            throw new PublicException('SKU options must be a dropdown with defined choices.');
        $unit=trim((string)($input['attribute_unit']??''));
        if(mb_strlen($unit)>24)throw new PublicException('Unit must be 24 characters or fewer.');
        $axis=$scope==='variant' && !empty($input['attribute_variant_axis']);
        $required=!empty($input['attribute_required']);
        $filterable=$type==='select' && !empty($input['attribute_filterable']);
        $rawOptions=trim((string)($input['attribute_options']??''));
        $options=$type==='select'?array_values(array_unique(array_filter(array_map('trim',preg_split('/\r\n|\r|\n|,/u',$rawOptions)?:[]),static fn($v)=>$v!==''))):[];
        if(count($options)>50)throw new PublicException('Maximum 50 dropdown choices.');
        if($axis && !$options)throw new PublicException('Add at least one choice for a SKU option.');
        foreach($options as $option)if(mb_strlen($option)>160)throw new PublicException('Dropdown choices must be 160 characters or fewer.');
        return compact('label','code','type','scope','unit','axis','required','filterable','options');
    }

    /** Add a custom attribute to one category without changing any existing assignments. */
    public function createAttribute(int $categoryId,array $input): array
    {
        $def=self::attributeDefinition($input);
        $q=$this->db->prepare("SELECT id FROM store_categories WHERE id=? AND status='active'");
        $q->execute([$categoryId]);
        if(!$q->fetchColumn())throw new PublicException('Choose an active category.');
        $q=$this->db->prepare('SELECT id FROM catalog_attributes WHERE code=?');
        $q->execute([$def['code']]);
        if($q->fetchColumn())throw new PublicException('This attribute already exists. Select it from the attribute list instead.');
        $this->db->beginTransaction();
        try{
            $insert=$this->db->prepare('INSERT INTO catalog_attributes(code,label,input_type,scope,unit,is_filterable,is_required_default,sort_order,status) VALUES(?,?,?,?,?,?,?,?,?)');
            $insert->execute([$def['code'],$def['label'],$def['type'],$def['scope'],$def['unit']?:null,$def['filterable']?1:0,$def['required']?1:0,1000,'active']);
            $id=(int)$this->db->lastInsertId();
            $link=$this->db->prepare('INSERT INTO catalog_category_attributes(category_id,attribute_id,is_required,is_filterable,is_variant_axis,sort_order) VALUES(?,?,?,?,?,?)');
            $link->execute([$categoryId,$id,$def['required']?1:0,$def['filterable']?1:0,$def['axis']?1:0,1000]);
            if($def['options']){
                $add=$this->db->prepare('INSERT INTO catalog_attribute_options(attribute_id,code,label,sort_order) VALUES(?,?,?,?)');
                $seen=[];
                foreach($def['options'] as $index=>$label){
                    $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-', $label),'-'));
                    if($slug==='')$slug='value-'.substr(hash('sha256',$label),0,16);
                    $slug=mb_substr($slug,0,140);
                    if(isset($seen[$slug]))throw new PublicException('Two choices have the same identifier: '.$label);
                    $seen[$slug]=true;
                    $add->execute([$id,$slug,$label,$index*10]);
                }
            }
            $this->db->commit();
            return ['id'=>$id,'label'=>$def['label'],'category_id'=>$categoryId];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function create(string $type, string $name, int $parentId=0, string $imagePath='', ?callable $beforeCommit=null): array
    {
        if(!in_array($type,['brand','category'],true))throw new PublicException('Only brands and categories can be created here.');
        $name=trim($name);
        if($name==='' || mb_strlen($name)>120 || preg_match('/\p{L}/u',$name)!==1)
            throw new PublicException('Enter a valid name of 1–120 characters.');
        $table=$type==='brand'?'store_brands':'store_categories';
        $duplicate=$this->db->prepare("SELECT id FROM {$table} WHERE LOWER(name)=LOWER(?) LIMIT 1");
        $duplicate->execute([$name]);
        if($duplicate->fetchColumn())throw new PublicException('This '. $type .' already exists. Select it from the list or manage it in Catalog Setup.');
        $slug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'));
        if($slug==='')throw new PublicException('The name needs an English URL slug. Use English letters for the brand or category name.');
        $slug=mb_substr($slug,0,120);
        $base=$slug;$suffix=2;
        while($this->catalog->taxonomySlugExists($type,$slug)){
            $slug=$base.'-'.$suffix++;
            if($suffix>10000)throw new PublicException('Unable to generate a unique URL slug.');
        }
        if($type==='brand' && $parentId!==0)throw new PublicException('Brands cannot have parent categories.');
        if($parentId>0){
            $q=$this->db->prepare("SELECT id FROM store_categories WHERE id=? AND status='active'");
            $q->execute([$parentId]);if(!$q->fetchColumn())throw new PublicException('Choose an active parent category.');
        }
        $this->db->beginTransaction();
        try{
            $id=$this->catalog->createTaxonomy($type,$name,null,$slug,0,'active',$imagePath);
            if($type==='category' && $parentId>0){
                $q=$this->db->prepare('UPDATE store_categories SET parent_id=? WHERE id=?');$q->execute([$parentId,$id]);
            }
            if($beforeCommit!==null)$beforeCommit($id);
            $this->db->commit();
            return ['id'=>$id,'name'=>$name,'slug'=>$slug,'type'=>$type,'parent_id'=>$parentId];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function applyPreset(UniversalCatalogService $editor,int $categoryId,string $preset): int
    {
        $recipes=[
            'clothing'=>['attributes'=>['size','color','material','fit','weight_grams'],'axes'=>['size','color'],'required'=>['size','color']],
            'electronics'=>['attributes'=>['storage_gb','color','ram_gb','warranty_months','weight_grams'],'axes'=>['storage_gb','color'],'required'=>['storage_gb']],
            'general'=>['attributes'=>['color','weight_grams'],'axes'=>['color'],'required'=>[]],
        ];
        if(!isset($recipes[$preset]))throw new PublicException('Unknown industry preset.');
        $editor->ensureCategory($categoryId);
        if($editor->assignments($categoryId))throw new PublicException('This category already has attributes. Edit its assignments below instead of replacing them with a preset.');
        $byCode=[];foreach($editor->attributes() as $a)$byCode[(string)$a['code']]=(int)$a['id'];
        $getIds=static function(array $codes)use($byCode):array{
            $missing=array_diff($codes,array_keys($byCode));
            if($missing)throw new PublicException('Preset attribute definitions are missing: '.implode(', ',$missing).'. Run migration 048.');
            return array_map(static fn($code)=>$byCode[$code],$codes);
        };
        $recipe=$recipes[$preset];$ids=$getIds($recipe['attributes']);
        $editor->configureCategory($categoryId,$ids,$getIds($recipe['axes']),$getIds($recipe['required']));
        return count($ids);
    }
}
