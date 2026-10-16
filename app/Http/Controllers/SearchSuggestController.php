<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\Logger;
use App\Support\ServiceFactory;
use App\Storefront\StoreLocale;

final class SearchSuggestController
{
    public function handle(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, max-age=30');
        $query=mb_substr(trim((string)($_GET['q']??'')),0,120);
        if(mb_strlen($query)<3){$this->json(['ok'=>true,'query'=>$query,'items'=>[]]);}
        $retry=\web_rate_limit_consume('store_search_suggest',60,60);if($retry>0){header('Retry-After: '.$retry);$this->json(['ok'=>false,'query'=>$query,'items'=>[],'message'=>'Too many search requests.'],429);}
        try{
            $repo=ServiceFactory::storefront(\db());
            $settings=$repo->schemaReady()?$repo->settings():[];
            if(!$repo->schemaReady()||!\store_catalog_enabled($settings))$this->json(['ok'=>true,'query'=>$query,'items'=>[]]);
            $items=[];
            foreach($repo->searchSuggestions($query,8) as $row){
                $type=(string)($row['_search_type']??'product');
                if($type==='product'||$type==='brand')$row=StoreLocale::rows([$row],$type)[0];
                if($type==='note'&&StoreLocale::current()==='ar'&&!empty($row['name_ar']))$row['name']=$row['name_ar'];
                if($type==='brand'){
                    $items[]=['type'=>'Brand','label'=>(string)$row['name'],'meta'=>StoreLocale::tr('Shop this brand'),'url'=>\store_catalog_url(['brand'=>(string)$row['slug']]),'image'=>!empty($row['logo_path'])?\url((string)$row['logo_path']):''];
                    continue;
                }
                if($type==='note'){
                    $items[]=['type'=>'Note','label'=>(string)$row['name'],'meta'=>StoreLocale::tr('Shop this note'),'url'=>\store_catalog_url(['note'=>(string)$row['slug']]),'image'=>(($image=\store_image_path($row['image_path']??''))!=='')?\url(\store_image_variant_path($image,'sm')):''];
                    continue;
                }
                $metaParts=array_values(array_filter([(string)($row['brand_name']??''),((string)($row['department_code']??'')==='perfumes'?(string)($row['concentration']??''):''),(string)($row['first_available_variant_label']??'')]));
                $items[]=['type'=>'Product','label'=>(string)$row['name'],'meta'=>implode(' · ',$metaParts),'url'=>\store_product_url($row),'image'=>(($image=\store_image_path($row['main_image_path']??''))!=='')?\url(\store_image_variant_path($image,'sm')):''];
            }
            $this->json(['ok'=>true,'query'=>$query,'items'=>$items]);
        }catch(\Throwable $e){
            Logger::warning('Store search suggestions unavailable',['message'=>$e->getMessage()]);
            $this->json(['ok'=>false,'query'=>$query,'items'=>[]],503);
        }
    }

    private function json(array $payload,int $status=200): never
    {
        http_response_code($status);
        echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }
}
