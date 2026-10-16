<?php
require_once __DIR__.'/../../includes/store_layout.php';
$storeName=trim((string)($settings['store_name']??store_runtime_name()))?:store_runtime_name();
$layoutOptions=['store_name'=>$storeName,'user'=>$user,'settings'=>$settings,'canonical'=>store_absolute_url(route_url('index.php')),'preview_embed'=>!empty($homepagePreview)&&!empty($_GET['homepage_preview_embed']),'robots'=>!empty($homepagePreview)?'noindex,nofollow':'index,follow'];
// Preload the configured hero art before body parsing so the LCP image starts as
// early as possible. The actual <picture> remains the source of truth.
foreach(($sections??[]) as $homeSection){
    if((string)($homeSection['section_type']??'')!=='hero')continue;
    $heroItem=is_array($homeSection['items']??null)?($homeSection['items'][0]??[]):[];
    $desktopMaster=store_image_path($heroItem['image_path']??'');
    $mobileMaster=store_image_path($heroItem['mobile_image_path']??'');
    if($desktopMaster!==''){
        $desktop=store_image_variant_path($desktopMaster,'lg');
        $layoutOptions['preload_desktop_image']=url($desktop);
        $layoutOptions['preload_desktop_srcset']=store_image_srcset($desktopMaster);
    }
    $mobileSource=$mobileMaster!==''?$mobileMaster:$desktopMaster;
    if($mobileSource!==''){
        $mobile=store_image_variant_path($mobileSource,'md');
        $layoutOptions['preload_mobile_image']=url($mobile);
        $layoutOptions['preload_mobile_srcset']=store_image_srcset($mobileSource);
    }
    break;
}
$visibleDepartments=store_public_departments();
// Only a genuinely perfume-exclusive store receives fragrance-specific defaults.
$perfumeOnly=count($visibleDepartments)===1 && (string)($visibleDepartments[0]['code']??'')==='perfumes';
$homeTitle=$perfumeOnly?'Luxury Perfumes':'Discover Our Collections';
$homeDescription=$perfumeOnly?'Discover signature fragrances and curated perfumery.':'Explore our departments and carefully selected products.';
store_page_start(store_t($homeTitle),store_t($homeDescription),$layoutOptions);
if(!empty($homepagePreview)):?><div class="homepage-preview-notice"><strong><?=store_h(store_t('DRAFT PREVIEW'))?></strong><span><?=store_h(store_t('Only administrators can see this version.'))?></span><a href="<?=store_h(route_url('admin/homepage.php'))?>"><?=store_h(store_t('Back to builder'))?></a></div><?php endif;
if(!$sections):?>
<section class="store-empty-home">
  <div><span><?=store_h(strtoupper($storeName))?></span><h1><?=store_h(store_t($perfumeOnly?'Find your next signature scent.':'Find something you’ll love.'))?></h1><p><?=store_h(store_t($perfumeOnly?'Our fragrance edit is being prepared. Explore the catalog to discover available perfumes.':'Our curated collections are being prepared. Explore everything in the store.'))?></p><a class="store-btn gold" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t($perfumeOnly?'SHOP PERFUMES →':'SHOP ALL PRODUCTS →'))?></a><?php if($user&&!empty($user['is_admin'])):?><a class="store-admin-inline-link" href="<?=store_h(route_url('admin/homepage.php'))?>"><?=store_h(store_t('Manage homepage'))?></a><?php endif;?></div>
</section>
<?php else:foreach($sections as $section){include __DIR__.'/section.php';}endif;
if(!$schemaReady):?><aside class="migration-notice"><?=store_h(($user&&!empty($user['is_admin']))?($setupMessage??'Store setup is incomplete.'):'The store is temporarily unavailable. Please try again shortly.')?></aside><?php endif;
store_page_end();
?>
