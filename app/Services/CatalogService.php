<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\CatalogRepository;
use App\Support\PublicException;
final class CatalogService
{
    public function __construct(private CatalogRepository $repo) {}
    public function productData(array $input): array
    {
        $name=trim((string)($input['name']??''));
        if($name===''||mb_strlen($name)>180)throw new PublicException('Product name is required and must be 180 characters or fewer.');
        $slug=$this->slug((string)($input['slug']??$name));if($slug==='')throw new PublicException('A valid product slug is required.');
        $gender=strtolower(trim((string)($input['gender']??'unisex')));if(!in_array($gender,['men','women','unisex'],true))$gender='unisex';
        $status=strtolower(trim((string)($input['status']??'draft')));if(!in_array($status,['draft','active','hidden','scheduled','archived'],true))$status='draft';
        return [
            'brand_id'=>$this->idOrNull($input['brand_id']??null),'fragrance_family_id'=>$this->idOrNull($input['fragrance_family_id']??null),
            'name'=>$name,'name_ar'=>null,'slug'=>$slug,
            'short_description'=>$this->nullText($input['short_description']??null,500),'short_description_ar'=>null,
            'description'=>$this->nullText($input['description']??null,20000),'description_ar'=>null,
            'gender'=>$gender,'concentration'=>$this->nullText($input['concentration']??null,40),
            'longevity_score'=>$this->scoreOrNull($input['longevity_score']??null),'projection_score'=>$this->scoreOrNull($input['projection_score']??null),'sillage_score'=>$this->scoreOrNull($input['sillage_score']??null),'fresh_score'=>$this->scoreOrNull($input['fresh_score']??null),'sweet_score'=>$this->scoreOrNull($input['sweet_score']??null),'woody_score'=>$this->scoreOrNull($input['woody_score']??null),'spicy_score'=>$this->scoreOrNull($input['spicy_score']??null),
            'season_tags'=>$this->tagList($input['season_tags']??null,160),'occasion_tags'=>$this->tagList($input['occasion_tags']??null,200),
            'main_image_path'=>$this->nullText($input['main_image_path']??null,255),'gallery_json'=>$this->galleryJson($input['gallery_json']??null),'seo_title'=>$this->nullText($input['seo_title']??null,180),'seo_description'=>$this->nullText($input['seo_description']??null,320),'status'=>$status,
            'is_featured'=>!empty($input['is_featured'])?1:0,'is_bestseller'=>!empty($input['is_bestseller'])?1:0,'is_new_arrival'=>!empty($input['is_new_arrival'])?1:0,'is_exclusive'=>!empty($input['is_exclusive'])?1:0,
            'published_at'=>$status==='active'?(string)($input['published_at']??'') ?: gmdate('Y-m-d H:i:s'):null,
        ];
    }
    public function variantData(array $input,string $fallbackSku=''): array
    {
        $label=trim((string)($input['label']??''));$sku=strtoupper(trim((string)($input['sku']??'')));
        if($label===''||mb_strlen($label)>100)throw new PublicException('Variant label is required.');
        if($sku==='')$sku=strtoupper(trim($fallbackSku));
        if($sku===''||mb_strlen($sku)>80)throw new PublicException('SKU could not be generated. Enter one manually.');
        $price=(float)($input['price']??0);if($price<=0)throw new PublicException('Variant price must be greater than zero.');
        $status=in_array(($input['status']??'active'),['active','hidden','archived'],true)?(string)$input['status']:'active';
        return ['label'=>$label,'size_ml'=>$this->decimalOrNull($input['size_ml']??null),'sku'=>$sku,'barcode'=>$this->nullText($input['barcode']??null,80),'cost_price'=>$this->decimalOrNull($input['cost_price']??null),'price'=>number_format($price,2,'.',''),'sale_price'=>$this->decimalOrNull($input['sale_price']??null),'currency'=>strtoupper(substr(trim((string)($input['currency']??'EGP')),0,3)) ?: 'EGP','stock_qty'=>max(0,(int)($input['stock_qty']??0)),'low_stock_threshold'=>max(0,(int)($input['low_stock_threshold']??3)),'sort_order'=>(int)($input['sort_order']??0),'status'=>$status];
    }
    public function brandData(array $input): array { $name=trim((string)($input['name']??''));if($name===''||mb_strlen($name)>120)throw new PublicException('Brand name is required.');return [$name,null,$this->slug((string)($input['slug']??$name))]; }
    private function slug(string $v): string {
        $original=trim($v);
        $ascii=$original;
        if(function_exists('iconv')){ $converted=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$original); if(is_string($converted)&&trim($converted)!=='')$ascii=$converted; }
        $ascii=strtolower(trim($ascii));$ascii=preg_replace('/[^a-z0-9]+/','-',$ascii)??'';$ascii=trim($ascii,'-');
        if($ascii!=='')return $ascii;
        return 'product-'.substr(hash('sha256',mb_strtolower($original,'UTF-8')),0,12);
    }
    private function nullText(mixed $v,int $max): ?string {$s=trim((string)$v);if($s==='')return null;return mb_substr($s,0,$max);}
    private function idOrNull(mixed $v): ?int {$i=(int)$v;return $i>0?$i:null;}
    private function decimalOrNull(mixed $v): ?string {$s=trim((string)$v);if($s===''||!is_numeric($s))return null;return number_format(max(0,(float)$s),2,'.','');}
    private function scoreOrNull(mixed $v): ?int {$s=trim((string)$v);if($s==='')return null;$i=(int)$s;return max(0,min(10,$i));}
    private function tagList(mixed $v,int $max): ?string { if(is_array($v)){$v=implode(',',array_values(array_filter(array_map(static fn($x)=>trim((string)$x),$v),static fn($x)=>$x!=='')));} return $this->nullText($v,$max);}
    private function galleryJson(mixed $v): ?string { if(is_array($v))$arr=$v;else{$raw=trim((string)$v);if($raw==='')return null;$arr=json_decode($raw,true);if(!is_array($arr))return null;} $out=[];foreach($arr as $item){$path=trim((string)$item);if($path!=='' && strlen($path)<=255)$out[]=$path;} return $out?json_encode(array_values(array_unique($out)),JSON_UNESCAPED_SLASHES):null;}
}
