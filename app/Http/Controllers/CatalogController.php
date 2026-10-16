<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Support\Logger;
use App\Support\ServiceFactory;

final class CatalogController
{
    public function handle(): array
    {
        $query=mb_substr(trim((string)($_GET['q']??'')),0,120);
        $requestedSort=substr(trim((string)($_GET['sort']??'')),0,20);
        $allowedSorts=['bestselling','new','price_asc','price_desc'];if($query!=='')$allowedSorts[]='relevance';
        $sort=in_array($requestedSort,$allowedSorts,true)?$requestedSort:($query!==''?'relevance':'bestselling');
        $requestedCollection=substr(trim((string)($_GET['collection']??'')),0,80);
        $allowedCollections=['niche','arabian','gifts'];
        $collection=in_array($requestedCollection,$allowedCollections,true)?$requestedCollection:'';
        $requestedGender=strtolower(substr(trim((string)($_GET['gender']??'')),0,20));
        $gender=in_array($requestedGender,['men','women','unisex'],true)?$requestedGender:'';
        $filters=[
            'q'=>$query,
            'department'=>substr(trim((string)($_GET['department']??'')),0,80),
            'spec'=>array_slice(array_filter(array_map(static fn($v)=>is_string($v)?substr(trim($v),0,140):'',array_filter((array)($_GET['spec']??[]),static fn($v)=>is_string($v))),static fn($v)=>$v!==''),0,8,true),
            'gender'=>$gender,
            'brand'=>substr(trim((string)($_GET['brand']??'')),0,140),
            'family'=>substr(trim((string)($_GET['family']??'')),0,120),
            'category'=>substr(trim((string)($_GET['category']??'')),0,140),
            'note'=>substr(trim((string)($_GET['note']??'')),0,120),
            'season'=>substr(trim((string)($_GET['season']??'')),0,40),
            'occasion'=>substr(trim((string)($_GET['occasion']??'')),0,40),
            'sort'=>$sort,
            'sale'=>!empty($_GET['sale'])?'1':'',
            'min_price'=>max(0,(float)($_GET['min_price']??0)),
            'max_price'=>max(0,(float)($_GET['max_price']??0)),
            'collection'=>$collection,
            'concentration'=>substr(trim((string)($_GET['concentration']??'')),0,60),
        ];
        if($filters['min_price']>0&&$filters['max_price']>0&&$filters['min_price']>$filters['max_price']){[$filters['min_price'],$filters['max_price']]=[$filters['max_price'],$filters['min_price']];}
        $page=max(1,(int)($_GET['page']??1));$perPage=24;
        $schemaReady=false;$databaseReady=false;$products=[];$brands=[];$categories=[];$families=[];$notes=[];$concentrations=[];$facetCounts=[];$settings=[];$setupMessage='';$totalProducts=0;$totalPages=0;$departments=[];$attributeFacets=[];$seoFacetValidity=['brand'=>true,'category'=>true];
        try{
            $repo=ServiceFactory::storefront(\db());$databaseReady=true;$schemaReady=$repo->schemaReady();
            if($schemaReady){$settings=$repo->settings();if(!\store_catalog_enabled($settings)){$setupMessage='The catalog is temporarily unavailable.';}else{$departments=$repo->activeDepartments();
                    if($filters['department']!==''&&!in_array($filters['department'],array_column($departments,'code'),true)){
                        // Invalid/inactive department is never interpreted as 'All products'.
                        $filters['department']='__invalid_department__';
                    }
                    $catalogPage=$repo->catalogPage($filters,$page,$perPage);$products=$catalogPage['items'];$totalProducts=(int)$catalogPage['total'];$page=(int)$catalogPage['page'];$totalPages=(int)$catalogPage['pages'];$brands=$filters['department']!==''?$repo->brandsForDepartment($filters['department']):$repo->featuredBrands(100);$categories=$filters['department']!==''?$repo->departmentCategories($filters['department']):$repo->categories(100);
                    $products=\store_localize_rows($products,'product');$departments=\store_localize_rows($departments,'department');$brands=\store_localize_rows($brands,'brand');$categories=\store_localize_rows($categories,'category');
                    $attributeFacets=$repo->catalogAttributeFacets($filters['department'],(string)$filters['category']);$families=$repo->fragranceFamilies();$notes=$repo->fragranceNotes(200);$concentrations=$repo->concentrations();$facetCounts=$repo->catalogFacetCounts($filters);if($filters['brand']!=='')$seoFacetValidity['brand']=$repo->publicPrimaryFacetExists('brand',$filters['brand']);if($filters['category']!=='')$seoFacetValidity['category']=$repo->publicPrimaryFacetExists('category',$filters['category']);}}else{$setupMessage='Apply the commerce schema to enable the live catalog.';}
        }catch(\Throwable $e){$setupMessage='The catalog database is unavailable. Check the store database configuration.';Logger::warning('Catalog database unavailable',['message'=>$e->getMessage()]);}
        $user=null;try{$user=\current_user();}catch(\Throwable){}
        if($filters['q']!==''&&function_exists('visitor_analytics_event')){
            $searchKey=hash('sha256',mb_strtolower(trim((string)$filters['q'])));$seen=is_array($_SESSION['_analytics_search_seen']??null)?$_SESSION['_analytics_search_seen']:[];$now=time();
            if(($now-(int)($seen[$searchKey]??0))>300){\visitor_analytics_event('search_used',$user?(int)$user['id']:null,['query'=>$filters['q']]);$seen[$searchKey]=$now;$_SESSION['_analytics_search_seen']=array_slice($seen,-20,null,true);}
        }
        return compact('filters','page','perPage','totalProducts','totalPages','schemaReady','databaseReady','products','brands','categories','families','notes','concentrations','facetCounts','settings','setupMessage','seoFacetValidity','user','departments','attributeFacets');
    }

}