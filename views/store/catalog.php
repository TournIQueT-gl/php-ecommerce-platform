<?php
require_once __DIR__.'/../../includes/store_layout.php';
$storeName=trim((string)($settings['store_name']??store_runtime_name()))?:store_runtime_name();
$gender=(string)($filters['gender']??'');$displayCurrency=strtoupper((string)($settings['currency']??'EGP'));
$departmentCode=(string)($filters['department']??'');
$departmentNames=[];foreach(($departments??[]) as $d)$departmentNames[(string)$d['code']]=store_t((string)$d['name']);
$departmentName=$departmentNames[$departmentCode]??store_t('All Products');
$isPerfumeDepartment=$departmentCode==='perfumes';
$title=$departmentName;
$departmentIntro='';
// Optional per-department presentation copy, published via Store Configuration.
if($departmentCode!==''&&isset($departmentNames[$departmentCode])){
    $published=\App\Storefront\StoreNavigationConfig::decode($settings['v5_navigation_published']??'',(array)($departments??[]));
    $landing=$published['landing'][$departmentCode]??[];
    if(is_array($landing)){
        $title=store_t(trim((string)($landing['heading']??'')))?:$departmentName;
        $departmentIntro=store_t((string)($landing['intro']??''));
    }
}
if($gender!=='' && ($departmentCode==='' || in_array(strtolower($departmentCode),['perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'],true)))$title=store_t(ucfirst($gender)).' '.($departmentCode===''?store_t('Products'):$departmentName);
if(!empty($filters['q']))$title=store_t('Search').': “'.(string)$filters['q'].'”';
$totalProducts=(int)($totalProducts??count($products));
$page=max(1,(int)($page??1));
$perPage=max(1,(int)($perPage??24));
$totalPages=max(0,(int)($totalPages??($totalProducts>0?ceil($totalProducts/$perPage):0)));
$shownFrom=$totalProducts>0?(($page-1)*$perPage)+1:0;
$shownTo=$totalProducts>0?min($page*$perPage,$totalProducts):0;

$brands=store_localize_rows($brands,'brand');$categories=store_localize_rows($categories,'category');
$brandLookup=[];foreach($brands as $b)$brandLookup[(string)($b['slug']??'')]=(string)($b['name']??'');
$categoryLookup=[];foreach($categories as $c)$categoryLookup[(string)($c['slug']??'')]=store_t((string)($c['name']??''));
$familyLookup=[];foreach($families as $f)$familyLookup[(string)($f['slug']??'')]=store_t((string)($f['name']??''));
$noteLookup=[];foreach($notes as $n)$noteLookup[(string)($n['slug']??'')]=store_t((string)($n['name']??''));
$collectionLookup=['niche'=>'Niche','arabian'=>'Arabian Perfumes','gifts'=>'Gift Sets'];

$cleanParams=static function(array $source):array{
    $out=[];foreach($source as $k=>$v){if($k==='page'||$v===''||$v===0||$v===0.0||$v===null)continue;$out[$k]=$v;}return $out;
};
$pageUrl=static function(int $target)use($filters,$cleanParams):string{$params=$cleanParams($filters);if($target>1)$params['page']=$target;return store_catalog_url($params);};
$removeFilterUrl=static function(string $key)use($filters,$cleanParams):string{$params=$cleanParams($filters);if(str_starts_with($key,'spec_')){unset($params['spec'][substr($key,5)]);if(empty($params['spec']))unset($params['spec']);}else unset($params[$key]);unset($params['page']);return store_catalog_url($params);};

$activeFilters=[];
foreach($filters as $key=>$value){
    if(in_array($key,['sort','spec'],true)||$value===''||$value===0||$value===0.0||$value===null)continue;
    $label=match($key){
        'q'=>store_t('Search').': '.$value,
        'department'=>$departmentName,
        'gender'=>store_t(ucfirst((string)$value)),
        'brand'=>$brandLookup[(string)$value]??str_replace('-',' ',ucwords((string)$value,'-')),
        'family'=>$familyLookup[(string)$value]??ucwords(str_replace('-',' ',(string)$value)),
        'category'=>$categoryLookup[(string)$value]??ucwords(str_replace('-',' ',(string)$value)),
        'note'=>$noteLookup[(string)$value]??ucwords(str_replace('-',' ',(string)$value)),
        'season'=>store_t(ucfirst((string)$value)),
        'occasion'=>store_t(ucfirst((string)$value)),
        'concentration'=>(string)$value,
        'collection'=>store_t($collectionLookup[(string)$value]??ucfirst((string)$value)),
        'sale'=>store_t('On sale only'),
        'min_price'=>store_t('From').' '.number_format((float)$value).' '.$displayCurrency,
        'max_price'=>store_t('Up to').' '.number_format((float)$value).' '.$displayCurrency,
        default=>ucfirst(str_replace('_',' ',$key)).': '.$value,
    };
    $activeFilters[$key]=$label;
}
foreach((array)($filters['spec']??[]) as $key=>$value){if($value==='')continue;$activeFilters['spec_'.$key]=((string)($attributeFacets[$key]['label']??$key)).': '.(string)($attributeFacets[$key]['options'][$value]??$value);}
$activeCount=count($activeFilters);
$sortOptions=[];if(!empty($filters['q']))$sortOptions['relevance']='Most Relevant';$sortOptions+=[
    'bestselling'=>'Best Selling',
    'new'=>'Newest',
    'price_asc'=>'Price: Low to High',
    'price_desc'=>'Price: High to Low',
];
$sortOptions=array_map('store_t',$sortOptions);
$currentSort=(string)($filters['sort']??(!empty($filters['q'])?'relevance':'bestselling'));
if(!isset($sortOptions[$currentSort]))$currentSort=!empty($filters['q'])?'relevance':'bestselling';
$sortUrl=static function(string $sort)use($filters,$cleanParams):string{
    $params=$cleanParams($filters);$params['sort']=$sort;unset($params['page']);return store_catalog_url($params);
};
$facetCounts=is_array($facetCounts??null)?$facetCounts:[];
$facetCount=static function(string $facet,string $value)use($facetCounts):string{
    $n=(int)($facetCounts[$facet][$value]??0);
    return $n>0?number_format($n):'0';
};

$canonicalParams=$cleanParams($filters);unset($canonicalParams['sort'],$canonicalParams['page']);
$seoPrimary=['gender','brand','category','collection','sale','department'];$seoActive=[];foreach($canonicalParams as $key=>$value){if($value!==''&&$value!==0&&$value!==0.0&&$value!==null)$seoActive[]=$key;}
$seoFacetValidity=is_array($seoFacetValidity??null)?$seoFacetValidity:['brand'=>true,'category'=>true];
$invalidSeoFacet=empty($seoFacetValidity['brand'])||empty($seoFacetValidity['category']);
$complexFacet=$invalidSeoFacet||!empty($filters['q'])||$page>1||count($seoActive)>1||array_diff($seoActive,$seoPrimary);
if($complexFacet){$catalogCanonical=store_absolute_url(store_catalog_url());$catalogRobots='noindex,follow';}
else{$catalogCanonical=store_absolute_url(store_catalog_url($canonicalParams));$catalogRobots='index,follow';}
store_page_start($title,'Browse our products and explore collections from every department.',['store_name'=>$storeName,'user'=>$user,'active'=>$gender,'settings'=>$settings,'canonical'=>$catalogCanonical,'robots'=>$catalogRobots]);
?>
<section class="catalog-hero">
  <div class="breadcrumbs"><a href="<?=store_h(route_url('index.php'))?>"><?=store_h(store_t('Home'))?></a><span>›</span><span><?=store_h($departmentName)?></span><?php if($gender && ($departmentCode==='' || in_array(strtolower($departmentCode),['perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'],true))):?><span>›</span><span><?=store_h(store_t(ucfirst($gender)))?></span><?php endif;?></div>
  <div class="catalog-title-row">
    <div><h1><?=store_h($title)?></h1><?php if($departmentIntro!=='' && empty($filters['q']) && empty($filters['gender'])):?><p><?=store_h($departmentIntro)?></p><?php endif;?><p><?=number_format($totalProducts)?> <?=store_h(store_t('products'))?></p></div>
    <div class="catalog-toolbar">
      <button class="filter-mobile-button" type="button" data-filter-toggle aria-controls="catalog-filter-panel" aria-expanded="false"><?=store_h(store_t('FILTER'))?><?=$activeCount>0?' · '.$activeCount:''?></button>
      <div class="catalog-sort-menu" data-sort-menu>
        <button class="catalog-sort-toggle" type="button" data-sort-toggle aria-haspopup="true" aria-controls="catalog-sort-popover" aria-expanded="false"><span><?=store_h($sortOptions[$currentSort])?></span><b>⌄</b></button>
        <div class="catalog-sort-popover" id="catalog-sort-popover" data-sort-popover aria-label="<?=store_h(store_t('Sort products'))?>">
          <?php foreach($sortOptions as $value=>$label):?><a href="<?=store_h($sortUrl($value))?>" class="<?=$currentSort===$value?'is-selected':''?>"><span><?=store_h($label)?></span><?php if($currentSort===$value):?><b>✓</b><?php endif;?></a><?php endforeach;?>
        </div>
      </div>
    </div>
  </div>
  <?php if($activeFilters):?><div class="catalog-filter-chips" aria-label="<?=store_h(store_t('Active filters'))?>"><?php foreach($activeFilters as $key=>$label):?><a href="<?=store_h($removeFilterUrl($key))?>"><span><?=store_h($label)?></span><b>×</b></a><?php endforeach;?><a class="clear-all" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('Clear all'))?></a></div><?php endif;?>
</section>
<section class="catalog-layout">
  <aside class="catalog-filter" id="catalog-filter-panel" data-catalog-filter>
    <form method="get" action="<?=store_h(route_url('perfumes.php'))?>" id="catalog-filter-form" class="catalog-filter-form">
      <?php if(!empty($filters['q'])):?><input type="hidden" name="q" value="<?=store_h($filters['q'])?>"><?php endif;?>
      <?php if(!empty($filters['sort'])):?><input type="hidden" name="sort" value="<?=store_h($filters['sort'])?>"><?php endif;?>
      <?php if(!empty($filters['collection'])):?><input type="hidden" name="collection" value="<?=store_h($filters['collection'])?>"><?php endif;?>
      <div class="catalog-filter-head"><div><span><?=store_h(store_t('REFINE YOUR SEARCH'))?></span><strong><?=store_h(store_t('Filters'))?></strong></div><button type="button" data-filter-close aria-label="<?=store_h(store_t('Close filters'))?>"><?=store_icon('close')?></button></div>
      <div class="catalog-filter-actions catalog-filter-actions-top"><a href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('CLEAR ALL'))?></a><button class="store-btn dark" type="submit" data-filter-apply data-label-apply="<?=store_h(store_t('APPLY FILTERS'))?>" data-label-show="<?=store_h(store_t('SHOW ALL PRODUCTS'))?>"><?=store_h(store_t('APPLY FILTERS'))?><?=$activeCount>0?' · '.$activeCount:''?></button></div>
      <div class="catalog-filter-scroll" data-filter-scroll>
      <div class="filter-block alpha6-department-filter"><h3><?=store_h(store_t('Shop by Department'))?></h3><label class="filter-option"><input type="radio" name="department" value="" <?=($departmentCode==='')?'checked':''?>><span><?=store_h(store_t('All Products'))?></span></label><?php foreach(($departments??[]) as $dep):?><label class="filter-option"><input type="radio" name="department" value="<?=store_h((string)$dep['code'])?>" <?=$departmentCode===(string)$dep['code']?'checked':''?>><span><?=store_h((string)$dep['name'])?></span></label><?php endforeach;?></div>

        <?php if($departmentCode===''||in_array(strtolower($departmentCode),['perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'],true)): ?><div class="filter-block"><h3><?=store_h(store_t('Shop for'))?></h3><?php foreach([''=>'All','men'=>'Men','women'=>'Women','unisex'=>'Unisex'] as $value=>$label):?><label class="filter-option"><input type="radio" name="gender" value="<?=$value?>" <?=$gender===$value?'checked':''?>><span><?=store_h(store_t($label))?></span><?php if($value!==''):?><small><?=$facetCount('gender',$value)?></small><?php endif;?></label><?php endforeach;?></div>
        <?php endif; ?><div class="filter-block"><h3><?=store_h(store_t('Category'))?></h3><label class="filter-option"><input type="radio" name="category" value="" <?=empty($filters['category'])?'checked':''?>><span><?=store_h(store_t('All categories'))?></span></label><?php foreach($categories as $c):$slug=(string)($c['slug']??'');?><label class="filter-option"><input type="radio" name="category" value="<?=store_h($slug)?>" <?=($filters['category']??'')===$slug?'checked':''?>><span><?=store_h($c['name']??'Category')?></span><small><?=$facetCount('category',$slug)?></small></label><?php endforeach;?></div>
        <div class="filter-block"><h3><?=store_h(store_t('Brand'))?></h3><input class="filter-search" type="search" placeholder="<?=store_h(store_t('Search brand...'))?>" autocomplete="off" data-brand-filter-search><div class="filter-option-list"><label class="filter-option" data-brand-option><input type="radio" name="brand" value="" <?=empty($filters['brand'])?'checked':''?>><span><?=store_h(store_t('All brands'))?></span></label><?php foreach($brands as $b):$slug=(string)($b['slug']??strtolower(str_replace(' ','-',(string)$b['name'])));?><label class="filter-option" data-brand-option><input type="radio" name="brand" value="<?=store_h($slug)?>" <?=($filters['brand']??'')===$slug?'checked':''?>><span><?=store_h($b['name'])?></span><small><?=$facetCount('brand',$slug)?></small></label><?php endforeach;?></div></div>
        <div class="filter-block"><h3><?=store_h(store_t('Price Range'))?></h3><div class="filter-range"><input type="number" name="min_price" min="0" step="50" placeholder="<?=store_h(store_t('Min'))?>" value="<?=($filters['min_price']??0)>0?store_h($filters['min_price']):''?>"><input type="number" name="max_price" min="0" step="50" placeholder="<?=store_h(store_t('Max'))?>" value="<?=($filters['max_price']??0)>0?store_h($filters['max_price']):''?>"></div></div>
        <?php if($isPerfumeDepartment): ?><div class="filter-block"><h3><?=store_h(store_t('Fragrance Family'))?></h3><label class="filter-option"><input type="radio" name="family" value="" <?=empty($filters['family'])?'checked':''?>><span><?=store_h(store_t('All families'))?></span></label><?php foreach($families as $f):$slug=(string)($f['slug']??'');?><label class="filter-option"><input type="radio" name="family" value="<?=store_h($slug)?>" <?=($filters['family']??'')===$slug?'checked':''?>><span><?=store_h($f['name'])?></span><small><?=$facetCount('family',$slug)?></small></label><?php endforeach;?></div>
        <div class="filter-block"><h3><?=store_h(store_t('Concentration'))?></h3><label class="filter-option"><input type="radio" name="concentration" value="" <?=empty($filters['concentration'])?'checked':''?>><span><?=store_h(store_t('All concentrations'))?></span></label><?php foreach($concentrations as $c):?><label class="filter-option"><input type="radio" name="concentration" value="<?=store_h($c)?>" <?=($filters['concentration']??'')===$c?'checked':''?>><span><?=store_h($c)?></span><small><?=$facetCount('concentration',$c)?></small></label><?php endforeach;?></div>
        <div class="filter-block"><h3><?=store_h(store_t('Season'))?></h3><?php foreach([''=>'All seasons','summer'=>'Summer','winter'=>'Winter','spring'=>'Spring','autumn'=>'Autumn'] as $value=>$label):?><label class="filter-option"><input type="radio" name="season" value="<?=$value?>" <?=($filters['season']??'')===$value?'checked':''?>><span><?=store_h(store_t($label))?></span><?php if($value!==''):?><small><?=$facetCount('season',$value)?></small><?php endif;?></label><?php endforeach;?></div>
        <div class="filter-block"><h3><?=store_h(store_t('Occasion'))?></h3><?php foreach([''=>'All occasions','daily'=>'Daily','office'=>'Office','date'=>'Date','night'=>'Night','events'=>'Events'] as $value=>$label):?><label class="filter-option"><input type="radio" name="occasion" value="<?=$value?>" <?=($filters['occasion']??'')===$value?'checked':''?>><span><?=store_h(store_t($label))?></span><?php if($value!==''):?><small><?=$facetCount('occasion',$value)?></small><?php endif;?></label><?php endforeach;?></div>
        <div class="filter-block"><h3><?=store_h(store_t('Notes'))?></h3><input class="filter-search" type="search" placeholder="<?=store_h(store_t('Search notes...'))?>" autocomplete="off" data-note-filter-search><div class="filter-option-list" data-note-filter-list><label class="filter-option" data-note-option><input type="radio" name="note" value="" <?=empty($filters['note'])?'checked':''?>><span><?=store_h(store_t('All notes'))?></span></label><?php foreach($notes as $n):$slug=(string)($n['slug']??'');?><label class="filter-option" data-note-option><input type="radio" name="note" value="<?=store_h($slug)?>" <?=($filters['note']??'')===$slug?'checked':''?>><span><?=store_h($n['name']??'Note')?></span><small><?=$facetCount('note',$slug)?></small></label><?php endforeach;?></div></div>
        <?php endif;?>
        <?php foreach(($attributeFacets??[]) as $attributeCode=>$attributeFacet): ?>
        <div class="filter-block"><h3><?=store_h((string)$attributeFacet['label'])?></h3>
        <label class="filter-option"><input type="radio" name="spec[<?=store_h($attributeCode)?>]" value="" <?=empty($filters['spec'][$attributeCode])?'checked':''?>><span><?=store_h(store_t('All'))?> <?=store_h((string)$attributeFacet['label'])?></span></label>
        <?php foreach($attributeFacet['options'] as $optionCode=>$optionLabel):?><label class="filter-option"><input type="radio" name="spec[<?=store_h($attributeCode)?>]" value="<?=store_h($optionCode)?>" <?=($filters['spec'][$attributeCode]??'')===$optionCode?'checked':''?>><span><?=store_h($optionLabel)?></span></label><?php endforeach;?></div>
        <?php endforeach;?>
        <div class="filter-block filter-sale"><h3><?=store_h(store_t('Offers'))?></h3><label class="filter-option"><input type="checkbox" name="sale" value="1" <?=!empty($filters['sale'])?'checked':''?>><span><?=store_h(store_t('On sale only'))?></span></label></div>
      </div>
      <div class="catalog-filter-actions catalog-filter-actions-bottom"><a href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('CLEAR ALL'))?></a><button class="store-btn dark" type="submit" data-filter-apply data-label-apply="<?=store_h(store_t('APPLY FILTERS'))?>" data-label-show="<?=store_h(store_t('SHOW ALL PRODUCTS'))?>"><?=store_h(store_t('APPLY FILTERS'))?><?=$activeCount>0?' · '.$activeCount:''?></button></div>
    </form>
  </aside>
  <button class="catalog-filter-backdrop" type="button" data-filter-backdrop aria-label="<?=store_h(store_t('Close filters'))?>"></button>
  <div class="catalog-results">
    <div class="catalog-results-head"><span data-results-progress><?=number_format($totalProducts)?> <?=store_h(store_t('results'))?> · <?=store_h(store_t('showing'))?> <?=$shownFrom?>–<?=$shownTo?></span><?php if($activeFilters):?><a href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('Clear all'))?></a><?php endif;?></div>
    <?php if(!$products):?><div class="catalog-empty"><h2><?=store_h(store_t('No products matched those filters.'))?></h2><p><?=store_h(store_t('Try removing one or two filters, or explore the full catalog.'))?></p><a class="store-btn dark" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('All Products'))?></a></div><?php else:?><div class="product-grid" data-product-grid data-total="<?=$totalProducts?>"><?php foreach($products as $productRow){include __DIR__.'/_product_card.php';}?></div><?php endif;?>

    <?php if($totalPages>1):?>
      <div class="catalog-paging">
        <div class="catalog-paging-progress"><span><?=number_format($shownTo)?> of <?=number_format($totalProducts)?> <?=store_h(store_t('products'))?></span><i style="--progress:<?=store_h((string)min(100,($shownTo/max(1,$totalProducts))*100))?>%"></i></div>
        <?php if($page<$totalPages):?><a class="catalog-load-more" href="<?=store_h($pageUrl($page+1))?>" data-load-more data-next-page="<?=$page+1?>"><span><?=store_h(store_t('LOAD'))?> <?=min($perPage,$totalProducts-$shownTo)?> <?=store_h(store_t('MORE'))?></span></a><?php endif;?>
        <nav class="catalog-pagination" aria-label="<?=store_h(store_t('Catalog pages'))?>">
          <?php if($page>1):?><a class="page-arrow" href="<?=store_h($pageUrl($page-1))?>" aria-label="<?=store_h(store_t('Previous page'))?>">←</a><?php endif;?>
          <?php
          $window=[];
          for($i=1;$i<=$totalPages;$i++){if($i===1||$i===$totalPages||abs($i-$page)<=2)$window[]=$i;}
          $last=0;foreach($window as $i):if($last&&$i>$last+1):?><span class="page-gap">…</span><?php endif;?><a class="<?=$i===$page?'is-current':''?>" href="<?=store_h($pageUrl($i))?>" <?=$i===$page?'aria-current="page"':''?>><?=$i?></a><?php $last=$i;endforeach;?>
          <?php if($page<$totalPages):?><a class="page-arrow" href="<?=store_h($pageUrl($page+1))?>" aria-label="<?=store_h(store_t('Next page'))?>">→</a><?php endif;?>
        </nav>
      </div>
    <?php endif;?>
    <?php if(!$schemaReady):?><aside class="migration-notice"><?=store_h(($user&&!empty($user['is_admin']))?($setupMessage??'Store setup is incomplete.'):'The catalog is temporarily unavailable. Please try again shortly.')?></aside><?php endif;?>
  </div>
</section>
<?php store_page_end(); ?>
