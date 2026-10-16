<?php
declare(strict_types=1);
require_once __DIR__.'/store_helpers.php';

function store_icon(string $name): string {
    $icons = [
        'home'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 11.2 12 4l8.5 7.2"></path><path d="M5.5 10.2V20h13v-9.8"></path><path d="M9.5 20v-5.6h5V20"></path></svg>',
        'search'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>',
        'user'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20c.7-4 3-6 6.5-6s5.8 2 6.5 6"></path></svg>',
        'heart'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 8.7c0 5.2-8.5 10.2-8.5 10.2S3.5 13.9 3.5 8.7A4.7 4.7 0 0 1 12 5.9a4.7 4.7 0 0 1 8.5 2.8Z"></path></svg>',
        'bag'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.5 8.5h13l-1 11h-11z"></path><path d="M9 9V6.5a3 3 0 0 1 6 0V9"></path></svg>',
        'menu'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>',
        'close'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"></path></svg>',
        'instagram'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4"></rect><circle cx="12" cy="12" r="3.6"></circle><circle cx="17.4" cy="6.8" r=".7" class="fill-current"></circle></svg>',
        'facebook'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 5.2h2.6V2.3c-.5-.1-2-.3-3.7-.3-3.6 0-6 2.2-6 6.2v3.5H4v3.3h3.4v8.3h4.1V15h3.2l.5-3.3h-3.7V8.5c0-1 .3-1.7 1.7-1.7h1.3z" class="fill-current no-stroke"></path></svg>',
        'tiktok'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 3.2c.5 2.5 1.9 4 4.3 4.7v3.2c-1.6 0-3-.5-4.3-1.4v6.4a5.5 5.5 0 1 1-4.7-5.4v3.3a2.3 2.3 0 1 0 1.5 2.1V3.2z" class="fill-current no-stroke"></path></svg>',
        'whatsapp'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.8a8 8 0 0 1-11.9 7l-4.1 1.1 1.1-4a8 8 0 1 1 14.9-4.1Z"></path><path d="M8.5 8.1c.2-.4.4-.4.7-.4h.5c.2 0 .4.1.5.4l.8 1.8c.1.3 0 .5-.1.7l-.7.8c.8 1.6 2 2.7 3.6 3.5l.8-1c.2-.2.4-.3.7-.2l1.8.9c.3.1.4.3.4.6 0 .7-.3 1.4-.8 1.8-.6.5-1.4.8-2.2.7-2.1-.3-4.2-1.4-5.8-3-1.5-1.5-2.6-3.5-2.9-5.5-.1-.4 0-.8.2-1.1Z"></path></svg>',
        'youtube'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.3 7.1c-.2-1-1-1.8-2-2C16.6 4.7 12 4.7 12 4.7s-4.6 0-6.3.4c-1 .2-1.8 1-2 2C3.3 8.8 3.3 12 3.3 12s0 3.2.4 4.9c.2 1 1 1.8 2 2 1.7.4 6.3.4 6.3.4s4.6 0 6.3-.4c1-.2 1.8-1 2-2 .4-1.7.4-4.9.4-4.9s0-3.2-.4-4.9Z"></path><path d="m10.2 15.3 4.4-3.3-4.4-3.3z"></path></svg>',
        'plus'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>',
        'arrow'=>'<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M14 7l5 5-5 5"></path></svg>',
    ];
    return $icons[$name] ?? '';
}

function store_default_links(string $group): array {
    return match($group){
        'navigation'=>[
            ['label'=>'Men','target'=>'men','url'=>''],['label'=>'Women','target'=>'women','url'=>''],['label'=>'Unisex','target'=>'unisex','url'=>''],['label'=>'Niche','target'=>'niche','url'=>''],['label'=>'Arabian Perfumes','target'=>'arabian','url'=>''],['label'=>'Gifts','target'=>'gifts','url'=>''],['label'=>'Offers','target'=>'offers','url'=>''],
        ],
        'shop'=>[
            ['label'=>'Men','target'=>'men','url'=>''],['label'=>'Women','target'=>'women','url'=>''],['label'=>'Unisex','target'=>'unisex','url'=>''],['label'=>'New arrivals','target'=>'new','url'=>''],['label'=>'Offers','target'=>'offers','url'=>''],
        ],
        'discover'=>[
            ['label'=>'Browse all products','target'=>'catalog','url'=>''],['label'=>'New arrivals','target'=>'new','url'=>''],['label'=>'Best sellers','target'=>'bestselling','url'=>''],['label'=>'Wishlist','target'=>'wishlist','url'=>''],
        ],
        'help'=>[
            ['label'=>'Shipping & delivery','target'=>'shipping','url'=>''],['label'=>'Returns & refunds','target'=>'returns','url'=>''],['label'=>'Contact us','target'=>'support','url'=>''],['label'=>'FAQ','target'=>'faq','url'=>''],['label'=>'Privacy','target'=>'privacy','url'=>''],
        ],
        default=>[],
    };
}

/** Skip disabled optional-module destinations in configurable public links. */
function store_filter_module_links(array $links): array {
    if(store_module_enabled('wishlist'))return $links;
    return array_values(array_filter($links,static fn($row)=>is_array($row)&&($row['target']??'')!=='wishlist'));
}

function store_configured_links(array $settings,string $group): array {
    $raw='';$fallback=store_default_links($group);
    if($group==='navigation'){
        $legacy=['men'=>'nav_men_label','women'=>'nav_women_label','unisex'=>'nav_unisex_label','niche'=>'nav_niche_label','arabian'=>'nav_arabian_label','gifts'=>'nav_gifts_label','offers'=>'nav_offers_label'];
        foreach($fallback as &$row){$field=$legacy[(string)$row['target']]??'';if($field!==''&&trim((string)($settings[$field]??''))!=='')$row['label']=trim((string)$settings[$field]);}unset($row);
        $raw=(string)($settings['navigation_json']??'');
    }
    else{
        $all=json_decode((string)($settings['footer_links_json']??''),true);
        if(is_array($all)&&isset($all[$group])&&is_array($all[$group]))return store_filter_module_links(array_values(array_filter($all[$group],static fn($row)=>is_array($row)&&trim((string)($row['label']??''))!=='')));
        return store_filter_module_links($fallback);
    }
    if($raw!==''){$decoded=json_decode($raw,true);if(is_array($decoded)){$rows=array_values(array_filter($decoded,static fn($row)=>is_array($row)&&trim((string)($row['label']??''))!==''));if($rows)return store_filter_module_links($rows);}}
    return store_filter_module_links($fallback);
}

function store_link_href(array $link,string $accountHref): string {
    $target=(string)($link['target']??'catalog');
    return match($target){
        'home'=>route_url('index.php'),'catalog'=>store_catalog_url(),'men'=>store_catalog_url(['gender'=>'men']),'women'=>store_catalog_url(['gender'=>'women']),'unisex'=>store_catalog_url(['gender'=>'unisex']),
        'niche'=>store_catalog_url(['collection'=>'niche']),'arabian'=>store_catalog_url(['collection'=>'arabian']),'gifts'=>store_catalog_url(['collection'=>'gifts']),'offers'=>store_catalog_url(['sale'=>'1']),
        'new'=>store_catalog_url(['sort'=>'new']),'bestselling'=>store_catalog_url(['sort'=>'bestselling']),'wishlist'=>route_url('wishlist.php'),'account'=>$accountHref,'support'=>route_url('support.php'),
        'shipping'=>route_url('shipping-delivery.php'),'returns'=>route_url('returns.php'),'faq'=>route_url('faq.php'),'privacy'=>route_url('privacy.php'),
        'custom'=>preg_match('~^https://~i',(string)($link['url']??''))?(string)$link['url']:url((string)($link['url']??'')),
        default=>store_catalog_url(),
    };
}

function store_link_active(string $target,string $active): bool {
    return ($target==='men'&&$active==='men')||($target==='women'&&$active==='women')||($target==='unisex'&&$active==='unisex');
}

function store_public_departments(): array {
    static $departments=null;
    if($departments!==null)return $departments;
    try {
        $repo=\App\Support\ServiceFactory::storefront(\db());
        try {return $departments=$repo->navigationDepartments();}
        catch(\Throwable){return $departments=$repo->activeDepartments();}
    }
    catch(\Throwable){ return $departments=[]; }
}

function store_page_start(string $title,string $description='',array $options=[]): void {
    $locale=store_lang();$enabledLocales=store_available_languages();
    $storeName=trim((string)($options['store_name']??store_runtime_name()))?:store_runtime_name();
    $settings=is_array($options['settings']??null)?$options['settings']:[];
    // The published theme is read independently from draft settings and overrides presentation only.
    $themeSettings=store_runtime_settings();
    $publishedTheme=\App\Storefront\StoreThemeConfig::decode($themeSettings['v5_theme_published']??'');
    // Never expose an unpublished theme to customers or support-only admins.
    // Store settings permission and the regular admin 2FA gate both apply to
    // the preview, just as they do to the Store Experience editor itself.
    if(!empty($_GET['theme_draft_preview'])){
        $viewer=current_user();
        if(is_array($viewer) && !empty($viewer['is_admin'])){
            require_once __DIR__.'/admin.php';
            if(admin_can('store_settings',$viewer)){
                require_admin();
                $candidate=\App\Storefront\StoreThemeConfig::decode($themeSettings['v5_theme_draft']??'');
                if($candidate!==null)$publishedTheme=$candidate;
            }
        }
    }
    if($publishedTheme!==null)$storeName=$publishedTheme['store_name'];
    $settings=\App\Storefront\StoreThemeConfig::overlaySettings($settings,$publishedTheme);
    $currency=strtoupper(trim((string)($settings['currency']??'EGP'))?:'EGP');
    $freeShipping=(float)($settings['free_shipping_threshold']??1000);
    $logoSubtitle=$publishedTheme!==null?$publishedTheme['logo_subtitle']:trim((string)($settings['logo_subtitle']??''));
    $utilityDelivery=trim((string)($settings['utility_delivery_text']??''));
    if($utilityDelivery===''){$utilityDelivery='Delivery across Egypt';}
    $utilityAuthentic=store_t(trim((string)($settings['utility_authentic_text']??'100% Authentic Products')));
    $utilityReturns=store_t(trim((string)($settings['utility_returns_text']??'Order Support')));
    $navigationLinks=store_configured_links($settings,'navigation');
    $storeDepartments=store_localize_rows(store_public_departments(),'department');
    foreach($storeDepartments as &$localizedDep){if(isset($localizedDep['categories'])&&is_array($localizedDep['categories']))$localizedDep['categories']=store_localize_rows($localizedDep['categories'],'category');}unset($localizedDep);
    $isMixedStore=count($storeDepartments)>1;
    // Published configuration is per installation. Drafts are never consumed here.
    $v5Navigation=\App\Storefront\StoreNavigationConfig::decode($settings['v5_navigation_published']??'',$storeDepartments)
        ?? \App\Storefront\StoreNavigationConfig::defaults($storeDepartments);
    $v5ShopDepartments=$storeDepartments;
    if($v5Navigation!==null)$v5ShopDepartments=array_values(array_filter($storeDepartments,static fn($d)=>in_array((string)$d['code'],$v5Navigation['departments'],true)));
    $v5AudienceDepartments=array_values(array_filter(\App\Storefront\StoreNavigationConfig::audienceDepartments($v5ShopDepartments),static fn($d)=>in_array((string)$d['code'],$v5Navigation['audience_departments']??[],true)));
    $v5OtherDepartments=array_values(array_filter($v5ShopDepartments,static fn($d)=>!in_array(strtolower((string)($d['code']??'')),['perfume','perfumes','fragrance','fragrances','fashion','clothing','apparel'],true)));
    $v5VisibleAudiences=$v5Navigation['audiences']??[];
    $v5HeaderLinks=\App\Storefront\StoreNavigationConfig::visibleHeaderLinks($v5Navigation);
    // Hide redundant display-only category links in mega menus. The category remains
    // accessible via catalog filters and its normal URL; catalog data is never deleted.
    $navCategoryKey=static function(string $label):string{
        $label=preg_replace('/^(?:\s*\[(?:TEST|DEMO)\]\s*)+/iu','',$label)??$label;
        $label=preg_replace('/\s+/u',' ',trim($label))??trim($label);
        return function_exists('mb_strtolower')?mb_strtolower($label,'UTF-8'):strtolower($label);
    };
    // Keep a single department index for electronics, home and other non-audience catalogs.
    $v5DropdownDepartments=$v5ShopDepartments; // One complete, admin-controlled department index.
    $v5NavigationBrands=[];
    try { $v5NavigationBrands=store_localize_rows(\App\Support\ServiceFactory::storefront(db())->navigationBrands(24),'brand'); }
    catch(\Throwable) { $v5NavigationBrands=[]; }

    $designHex=static function(mixed $value,string $fallback): string {
        $value=trim((string)$value);
        return preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):$fallback;
    };
    $designAccent=$designHex($settings['design_accent']??'','#b08a4c');
    $designBackground=$designHex($settings['design_background']??'','#f7f4ee');
    $designText=$designHex($settings['design_text']??'','#181613');
    $designFooter=$designHex($settings['design_footer']??'','#171512');
    $designRadius=(int)($settings['card_radius']??2);
    if($publishedTheme!==null){
        $designAccent=$publishedTheme['accent'];$designBackground=$publishedTheme['background'];
        $designText=$publishedTheme['text'];$designFooter=$publishedTheme['footer'];
        $designRadius=$publishedTheme['radius'];
    }
    if(!in_array($designRadius,[0,2,8,16],true)){$designRadius=2;}
    $GLOBALS['_store_layout_name']=$storeName;
    $user=$options['user']??current_user();
    $accountHref=$user?route_url('dashboard.php'):route_url('login.php');
    $description=$description!==''?$description:'Explore our curated products and collections.';
    $active=(string)($options['active']??'');
    $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
    $cartCount=store_cart_count();
    $mobileTab=match($script){
        'index.php'=>'home',
        'wishlist.php'=>'wishlist',
        'cart.php'=>'bag',
        'dashboard.php','login.php','register.php','account-orders.php','account-activity.php','account-email.php','account-security.php','two-factor.php'=>'account',
        default=>''
    };
    $GLOBALS['_store_layout_account_href']=$accountHref;
    $GLOBALS['_store_layout_cart_count']=$cartCount;
    $GLOBALS['_store_layout_mobile_tab']=$mobileTab;
    $GLOBALS['_store_layout_settings']=$settings;
    $GLOBALS['_store_layout_navigation']=$navigationLinks;
    $previewEmbed=!empty($options['preview_embed']);
    web_emit_server_timing('store');
    ?>
<!doctype html>
<html lang="<?=store_h($locale)?>" dir="<?=store_h(store_lang_dir())?>" class="no-js">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?=store_h($description)?>">
<?php $canonical=trim((string)($options['canonical']??''));$robots=trim((string)($options['robots']??'index,follow'));$ogImage=trim((string)($options['og_image']??''));$jsonLd=$options['json_ld']??null;$preloadDesktop=trim((string)($options['preload_desktop_image']??''));$preloadDesktopSet=trim((string)($options['preload_desktop_srcset']??''));$preloadMobile=trim((string)($options['preload_mobile_image']??''));$preloadMobileSet=trim((string)($options['preload_mobile_srcset']??''));
// Each indexable language URL gets its own canonical, with reciprocal alternate links.
// Do not publish alternates for private accounts, checkout or noindex search/filter pages.
$hasLocaleAlternates=count($enabledLocales)>1 && $canonical!=='' && !str_contains(strtolower($robots),'noindex');
$seoCanonical=$hasLocaleAlternates?store_absolute_url(store_language_url($locale,$canonical)):$canonical;
$localeDefault=\App\Storefront\StoreLocale::config(store_runtime_settings())['default'];
?>
<meta name="robots" content="<?=store_h($robots)?>"><?php if($seoCanonical!==''):?><link rel="canonical" href="<?=store_h($seoCanonical)?>"><?php endif;?>
<?php if($hasLocaleAlternates):foreach($enabledLocales as $alternateLocale):?><link rel="alternate" hreflang="<?=store_h($alternateLocale)?>" href="<?=store_h(store_absolute_url(store_language_url($alternateLocale,$canonical)))?>"><?php endforeach;?><link rel="alternate" hreflang="x-default" href="<?=store_h(store_absolute_url(store_language_url($localeDefault,$canonical)))?>"><?php endif;?>
<meta property="og:type" content="website"><meta property="og:title" content="<?=store_h($title.' · '.$storeName)?>"><meta property="og:description" content="<?=store_h($description)?>"><?php if($seoCanonical!==''):?><meta property="og:url" content="<?=store_h($seoCanonical)?>"><?php endif;?><?php if($ogImage!==''):?><meta property="og:image" content="<?=store_h($ogImage)?>"><?php endif;?>
<meta name="twitter:card" content="<?=$ogImage!==''?'summary_large_image':'summary'?>">
<?php if($preloadDesktop!==''):?><link rel="preload" as="image" href="<?=store_h($preloadDesktop)?>"<?php if($preloadDesktopSet!==''):?> imagesrcset="<?=store_h($preloadDesktopSet)?>" imagesizes="100vw"<?php endif;?> media="(min-width: 761px)" fetchpriority="high"><?php endif;?>
<?php if($preloadMobile!==''):?><link rel="preload" as="image" href="<?=store_h($preloadMobile)?>"<?php if($preloadMobileSet!==''):?> imagesrcset="<?=store_h($preloadMobileSet)?>" imagesizes="100vw"<?php endif;?> media="(max-width: 760px)" fetchpriority="high"><?php endif;?>
<meta name="theme-color" content="<?=store_h($designBackground)?>"><meta name="sm-csrf" content="<?=store_h(csrf_token())?>"><?php if(is_array($jsonLd)):?><script type="application/ld+json"><?=json_encode($jsonLd,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><?php endif;?>
<title><?=store_h($title.' · '.$storeName)?></title>
<link rel="icon" type="image/svg+xml" href="<?=store_h(asset_url('assets/images/favicon-store.svg'))?>"><link rel="alternate icon" type="image/x-icon" href="<?=store_h(asset_url('favicon.ico'))?>"><link rel="apple-touch-icon" href="<?=store_h(asset_url('apple-touch-icon.png'))?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" data-deferred-fonts><noscript><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&family=Noto+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
<link rel="stylesheet" href="<?=store_h(asset_url('assets/css/storefront.css'))?>"><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/alpha6-storefront.css'))?>"><?php if($publishedTheme!==null):?><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/store-theme.css'))?>"><?php endif;?><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/store-locale.css'))?>"><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/storefront-configuration-public.css'))?>"><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/storefront-responsive-v5.css'))?>"><link rel="stylesheet" href="<?=store_h(asset_url('assets/css/store-retail-v7.css'))?>">
<style>:root{--gold:<?=store_h($designAccent)?>;--ivory:<?=store_h($designBackground)?>;--ink:<?=store_h($designText)?>;--dark:<?=store_h($designFooter)?>;--store-card-radius:<?=$designRadius?>px}</style>
</head>
<body class="store-body<?=$previewEmbed?' store-preview-embed':''?><?=$publishedTheme!==null?' store-theme-'.store_h($publishedTheme['font']).' store-density-'.store_h($publishedTheme['density']):''?> store-front-layout-<?=store_h($publishedTheme['storefront_layout']??'classic')?> store-header-layout-<?=store_h($publishedTheme['header_layout']??'balanced')?><?=$v5VisibleAudiences?' store-audiences-enabled':''?> store-header-density-<?=store_h($publishedTheme['header_density']??'comfortable')?> store-navigation-modern store-nav-rc7<?=store_h($publishedTheme!==null?' store-nav-style-'.$publishedTheme['navigation_style'].' store-width-'.$publishedTheme['content_width'].' store-photo-'.$publishedTheme['product_image_shape'].' store-fit-'.$publishedTheme['product_image_fit'].' store-columns-'.$publishedTheme['catalog_columns']:'')?><?=$publishedTheme!==null&&!$publishedTheme['sticky_header']?' store-header-not-sticky':''?>" data-foundation-pulse-url="<?=store_h(route_url('foundation-pulse.php'))?>">
<?php if($publishedTheme!==null?$publishedTheme['show_utility_bar']:(string)($settings['show_utility_bar']??'1')==='1'):?>
<div class="store-utility-bar">
  <div class="store-utility-left"><?php $utilityMessages=array_values(array_filter([$utilityDelivery,$utilityAuthentic,$utilityReturns],static fn($value)=>$value!==''));foreach($utilityMessages as $i=>$msg):if($i):?><i></i><?php endif;?><span><?=store_h(store_t($msg))?></span><?php endforeach;?></div>
  <div class="store-utility-right"><span><?=store_h($currency)?></span><i></i><a href="<?=store_h(route_url('support.php'))?>"><?=store_h(store_t('Customer support'))?></a></div>
</div>
<?php endif;?>
<header class="store-header" data-store-header>
  <button class="store-menu-button" type="button" data-store-menu aria-label="<?=store_h(store_t('Open menu'))?>" aria-controls="store-mobile-nav" aria-expanded="false"><?=store_icon('menu')?></button>
  <a class="store-logo" href="<?=store_h(route_url('index.php'))?>" aria-label="<?=store_h($storeName.' · '.store_t('Home'))?>"><span><?=store_h($storeName)?></span><?php if($logoSubtitle!==''):?><small><?=store_h($logoSubtitle)?></small><?php endif;?></a>
  <form class="store-desktop-search" action="<?=store_h(route_url('perfumes.php'))?>" method="get" role="search" data-store-search-open aria-controls="store-search-drawer" aria-expanded="false"><?=store_icon('search')?><input type="search" name="q" maxlength="120" data-desktop-search-input placeholder="<?=store_h(store_t('Search products, brands, categories...'))?>" aria-label="<?=store_h(store_t('Search products, brands and categories'))?>" aria-controls="store-search-live-results" aria-autocomplete="list" autocomplete="off"><span class="store-search-shortcut" aria-hidden="true">/</span></form>
  <nav class="store-nav" id="store-mobile-nav" data-store-nav aria-label="<?=store_h(store_t('Primary navigation'))?>">
    <div class="store-nav-mobile-head">
      <a class="store-logo store-nav-logo" href="<?=store_h(route_url('index.php'))?>"><span><?=store_h($storeName)?></span><?php if($logoSubtitle!==''):?><small><?=store_h($logoSubtitle)?></small><?php endif;?></a>
      <button class="store-mobile-close" type="button" data-store-menu-close aria-label="<?=store_h(store_t('Close menu'))?>"><?=store_icon('close')?></button>
    </div>
    <button class="store-nav-search" type="button" data-store-search-open aria-controls="store-search-drawer" aria-expanded="false"><?=store_icon('search')?><span><?=store_h(store_t('Search products, brands, categories...'))?></span></button>
    <a class="store-nav-account" href="<?=store_h($accountHref)?>"><span class="store-nav-account-icon"><?=store_icon('user')?></span><span><small><?=store_h($user?store_t('YOUR ACCOUNT'):store_t('WELCOME TO').' '.$storeName)?></small><strong><?=$user?store_h(store_t('View account & orders')):store_h(store_t('Sign in or create an account'))?></strong></span><b class="store-nav-account-arrow" aria-hidden="true">→</b></a>
    <div class="store-nav-label"><?=store_h(store_t('Shop by category'))?></div>
    <?php foreach($v5VisibleAudiences as $audience):
      $audienceName=match($audience){'men'=>'Men','women'=>'Women','unisex'=>'Unisex',default=>'Shop'};
      $audienceHref=store_catalog_url(['gender'=>$audience]); ?>
      <details class="sc-audience-menu sc-shop-menu sc-link-desktop sc-link-mobile sc-shop-audiences modern-nav-disclosure" data-shop-audience>
        <summary><?=store_h(store_t($audienceName))?><span class="sc-caret" aria-hidden="true">⌄</span></summary>
        <div class="sc-audience-panel modern-nav-panel uc-retail-popover uc-retail-audience" style="--uc-cols:<?=min(3,max(1,count($v5AudienceDepartments)))?>" role="group" aria-label="<?=store_h(store_t($audienceName))?>">
          <a class="sc-audience-all" href="<?=store_h($audienceHref)?>"><?=store_h(store_t(match($audience){'men'=>'Shop all men','women'=>'Shop all women','unisex'=>'Shop all unisex',default=>'Shop all'}))?> <span>→</span></a>
          <?php foreach($v5AudienceDepartments as $dep):$depCode=(string)$dep['code']; ?>
            <div class="sc-audience-group"><a class="sc-audience-department" href="<?=store_h(store_catalog_url(['gender'=>$audience,'department'=>$depCode]))?>"><?=store_h(store_t((string)$dep['name']))?></a>
              <?php $menuSeen=[];$departmentLabelKey=$navCategoryKey((string)$dep['name']);foreach(array_slice($dep['categories']??[],0,8) as $cat):$categoryKey=$navCategoryKey((string)$cat['name']);if($categoryKey===$departmentLabelKey||isset($menuSeen[$categoryKey]))continue;$menuSeen[$categoryKey]=true;?><a href="<?=store_h(store_catalog_url(['gender'=>$audience,'department'=>$depCode,'category'=>(string)$cat['slug']]))?>"><?=store_h(store_t((string)$cat['name']))?></a><?php endforeach;?>
            </div>
          <?php endforeach;?>
        </div>
      </details>
    <?php endforeach;?>
    <?php foreach($v5HeaderLinks as $navItem):
      $kind=$navItem['type'];$label=store_t((string)$navItem['label']);$code=(string)$navItem['code'];
      $displayClasses=($navItem['desktop']?' sc-link-desktop':'').($navItem['mobile']?' sc-link-mobile':'');
      $navUrl=match($kind){'home'=>route_url('index.php'),'catalog'=>store_catalog_url(),'new'=>store_catalog_url(['sort'=>'new']),'offers'=>store_catalog_url(['sale'=>'1']),'department'=>store_catalog_url(['department'=>$code]),'men','women','unisex'=>store_catalog_url(['gender'=>$kind]),default=>store_catalog_url()};
      if($kind==='shop'):
      $shopMenuLabel=in_array(strtolower(trim((string)$navItem['label'])),['shop','other departments','departments'],true)?store_t('Departments'):$label;
    ?>
      <details class="alpha6-shop-menu sc-shop-menu modern-nav-disclosure<?=$displayClasses?>" data-shop-departments>
        <summary><?=store_h($shopMenuLabel)?> <span class="sc-caret" aria-hidden="true">⌄</span></summary>
        <div class="alpha6-shop-panel sc-shop-panel modern-nav-panel uc-retail-popover uc-retail-departments sc-style-<?=store_h($v5Navigation['style'])?>" style="--uc-cols:<?=min(3,max(1,count($v5DropdownDepartments)))?>">
          <div class="sc-shop-head"><strong><?=store_h(store_t('Explore departments'))?></strong><a href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('Shop all'))?> <span aria-hidden="true">→</span></a></div>
          <?php if($v5NavigationBrands):?>
          <div class="sc-index-tabs" role="tablist" aria-label="<?=store_h(store_t('Browse the store'))?>" data-nav-index-tabs>
            <button type="button" role="tab" aria-selected="true" tabindex="0" data-nav-index-tab="departments"><?=store_h(store_t('Departments'))?></button>
            <button type="button" role="tab" aria-selected="false" tabindex="-1" data-nav-index-tab="brands"><?=store_h(store_t('Brands'))?></button>
          </div>
          <?php endif;?>
          <div data-nav-index-panel="departments" role="tabpanel" class="sc-index-panel"><div class="sc-shop-grid">
            <?php foreach($v5DropdownDepartments as $dep):$depCode=(string)$dep['code']; ?>
              <section class="sc-shop-group"><a class="sc-department-title" href="<?=store_h(store_catalog_url(['department'=>$depCode]))?>"><?=store_h(store_t((string)$dep['name']))?></a>
                <?php $menuSeen=[];$departmentLabelKey=$navCategoryKey((string)$dep['name']);foreach(array_slice($dep['categories']??[],0,8) as $cat):$categoryKey=$navCategoryKey((string)$cat['name']);if($categoryKey===$departmentLabelKey||isset($menuSeen[$categoryKey]))continue;$menuSeen[$categoryKey]=true;?><a class="sc-category" href="<?=store_h(store_catalog_url(['department'=>$depCode,'category'=>(string)$cat['slug']]))?>"><?=store_h(store_t((string)$cat['name']))?></a><?php endforeach;?>
              </section>
            <?php endforeach;?>
          </div></div>
          <?php if($v5NavigationBrands):?><div data-nav-index-panel="brands" role="tabpanel" class="sc-index-panel" hidden>
            <div class="sc-brand-index">
              <?php foreach($v5NavigationBrands as $brand):?><a href="<?=store_h(store_catalog_url(['brand'=>(string)$brand['slug']]))?>"><?=store_h((string)$brand['name'])?></a><?php endforeach;?>
            </div>
            <a class="sc-brand-browse-all" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('Browse all products'))?> →</a>
          </div><?php endif;?>
          <?php if(!$v5DropdownDepartments):?><a class="sc-shop-empty" href="<?=store_h(store_catalog_url())?>"><?=store_h(store_t('Browse all products'))?> →</a><?php endif;?>
        </div>
      </details>
    <?php else: ?>
      <a class="sc-custom-link sc-kind-<?=store_h($kind)?><?=$displayClasses?><?=$kind==='offers'?' sale':''?>" href="<?=store_h($navUrl)?>"><?=store_h($label)?><span aria-hidden="true">›</span></a>
    <?php endif;endforeach;?>
    <div class="store-nav-mobile-footer"><?php if(count($enabledLocales)>1):?><nav class="store-language-switch" aria-label="<?=store_h(store_t('Language'))?>"><?php foreach($enabledLocales as $loc):?><a href="<?=store_h(store_language_url($loc))?>" lang="<?=$loc?>" <?=$loc===$locale?'aria-current="true"':''?>><?=$loc==='ar'?'العربية':'English'?></a><?php endforeach;?></nav><?php endif;?><span><?=store_h($currency)?></span><a href="<?=store_h(route_url('support.php'))?>"><?=store_h(store_t('Contact Us'))?> <span>›</span></a></div>
  </nav>
  <div class="store-actions"><?php if(count($enabledLocales)>1):?><details class="store-language-menu store-language-header"><summary aria-label="<?=store_h(store_t('Language'))?>"><?=store_h(strtoupper($locale))?><span aria-hidden="true">⌄</span></summary><div class="store-language-options"><?php foreach($enabledLocales as $loc):?><a href="<?=store_h(store_language_url($loc))?>" lang="<?=$loc?>" <?=$loc===$locale?'aria-current="true"':''?>><?=$loc==='ar'?'العربية':'English'?></a><?php endforeach;?></div></details><?php endif;?>
    <button class="store-action store-search-action" type="button" data-store-search-open aria-label="<?=store_h(store_t('Search'))?>" aria-controls="store-search-drawer" aria-expanded="false"><?=store_icon('search')?></button>
    <a class="store-action store-account-action" href="<?=store_h($accountHref)?>" aria-label="<?=store_h(store_t('Account'))?>"><?=store_icon('user')?></a>
    <?php if(store_module_enabled('wishlist')):?>
    <a class="store-action store-wishlist-action store-action-desktop" href="<?=store_h(route_url('wishlist.php'))?>" aria-label="<?=store_h(store_t('Wishlist'))?>"><?=store_icon('heart')?></a>
    <?php endif;?>
    <a class="store-action store-cart-link" href="<?=store_h(route_url('cart.php'))?>" aria-label="<?=store_h(store_t('Shopping bag'))?>"><?=store_icon('bag')?><?php if($cartCount>0):?><b><?=$cartCount?></b><?php endif;?></a>
  </div>
</header>
<div class="store-mobile-backdrop" data-store-menu-backdrop></div>
<button class="store-search-backdrop" type="button" data-store-search-backdrop aria-label="<?=store_h(store_t('Close search'))?>" tabindex="-1"></button>
<aside class="store-search-drawer" id="store-search-drawer" data-store-search data-search-endpoint="<?=store_h(route_url('search-suggest.php'))?>" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="store-search-title">
  <div class="store-search-head"><strong id="store-search-title"><?=store_h(store_t('Search the store'))?></strong><button type="button" data-store-search-close aria-label="<?=store_h(store_t('Close search'))?>"><?=store_icon('close')?></button></div>
  <form action="<?=store_h(route_url('perfumes.php'))?>" method="get" class="store-search-form" data-store-search-form><span><?=store_icon('search')?></span><input type="search" name="q" maxlength="120" placeholder="<?=store_h(store_t('Search products, brands, categories...'))?>" autocomplete="off" aria-label="<?=store_h(store_t('Search products, brands and categories'))?>" aria-autocomplete="list" aria-controls="store-search-live-results" data-store-search-input></form>
  <div class="store-search-live" id="store-search-live-results" data-store-search-live hidden></div>
  <p class="store-search-status" data-store-search-status role="status" aria-live="polite"></p>
  <?php $searchSuggestions=preg_split('/[\r\n]+/',trim((string)($settings['search_suggestions']??'')),-1,PREG_SPLIT_NO_EMPTY)?:[];if($searchSuggestions):?><div class="store-search-suggestions"><small><?=store_h(store_t('SUGGESTED SEARCHES'))?></small><?php foreach(array_slice($searchSuggestions,0,8) as $suggestion):$suggestion=trim((string)$suggestion);if($suggestion==='')continue;?><a href="<?=store_h(store_catalog_url(['q'=>$suggestion]))?>"><?=store_h($suggestion)?></a><?php endforeach;?></div><?php endif;?>
</aside>
<main class="store-main">
<?php }

function store_page_end(): void {
    $storeName=(string)($GLOBALS['_store_layout_name']??store_runtime_name());
    $accountHref=(string)($GLOBALS['_store_layout_account_href']??route_url('login.php'));
    $cartCount=(int)($GLOBALS['_store_layout_cart_count']??0);
    $mobileTab=(string)($GLOBALS['_store_layout_mobile_tab']??'');
    $layoutSettings=is_array($GLOBALS['_store_layout_settings']??null)?$GLOBALS['_store_layout_settings']:[];$layoutCurrency=strtoupper((string)($layoutSettings['currency']??'EGP'));
    $logoSubtitle=trim((string)($layoutSettings['logo_subtitle']??''));
    $footerAbout=store_t(trim((string)($layoutSettings['footer_about']??'Discover thoughtfully selected products for every need.')));
    $footerShop=store_t(trim((string)($layoutSettings['footer_shop_title']??'SHOP')));
    $footerDiscover=store_t(trim((string)($layoutSettings['footer_discover_title']??'DISCOVER')));
    $footerHelp=store_t(trim((string)($layoutSettings['footer_help_title']??'HELP')));
    $footerCountry=trim((string)($layoutSettings['footer_country_label']??'Egypt'));
    $copyrightOwner=trim((string)($layoutSettings['copyright_owner']??$storeName))?:$storeName;
    $copyrightSuffix=trim((string)($layoutSettings['copyright_suffix']??'All rights reserved.'));
    $copyrightYear=(string)($layoutSettings['copyright_auto_year']??'1')==='1'?date('Y'):(string)max(2000,(int)($layoutSettings['copyright_year']??date('Y')));
    $developerName=trim((string)($layoutSettings['developer_name']??''));$developerUrl=trim((string)($layoutSettings['developer_url']??''));$showDeveloper=(string)($layoutSettings['show_developer_credit']??'0')==='1'&&$developerName!=='';
    $footerLinks=['shop'=>store_configured_links($layoutSettings,'shop'),'discover'=>store_configured_links($layoutSettings,'discover'),'help'=>store_configured_links($layoutSettings,'help')];
    if(count(store_public_departments())>1 && trim((string)($layoutSettings['footer_links_json']??''))===''){
        $footerLinks['shop']=[['label'=>'All Products','target'=>'catalog'],['label'=>'New Arrivals','target'=>'new'],['label'=>'Offers','target'=>'offers']];
    }

    $footerVisible=static fn(string $k):bool=>(string)($layoutSettings[$k]??'1')==='1';
    $showFooterBrand=$footerVisible('footer_show_brand');
    $showShop=$footerVisible('footer_show_shop')&&!empty($footerLinks['shop']);
    $showDiscover=$footerVisible('footer_show_discover')&&!empty($footerLinks['discover']);
    $showHelp=$footerVisible('footer_show_help')&&!empty($footerLinks['help']);
    $footerCols=(int)$showFooterBrand+(int)$showShop+(int)$showDiscover+(int)$showHelp;
    ?>
</main>

<?php if($footerCols>0):?><footer class="store-footer store-footer-custom" style="--footer-columns:<?=max(1,$footerCols)?>">
  <?php if($showFooterBrand):?><div class="store-footer-brand"><a class="store-logo light" href="<?=store_h(route_url('index.php'))?>"><span><?=store_h($storeName)?></span><?php if($logoSubtitle!==''):?><small><?=store_h($logoSubtitle)?></small><?php endif;?></a><?php if($footerAbout!==''):?><p><?=store_h($footerAbout)?></p><?php endif;?><?php $socialSettings=is_array($GLOBALS['_store_layout_settings']??null)?$GLOBALS['_store_layout_settings']:[];$socialLinks=[['instagram','Instagram',(string)($socialSettings['social_instagram']??'')],['facebook','Facebook',(string)($socialSettings['social_facebook']??'')],['tiktok','TikTok',(string)($socialSettings['social_tiktok']??'')],['whatsapp','WhatsApp',(string)($socialSettings['social_whatsapp']??'')],['youtube','YouTube',(string)($socialSettings['social_youtube']??'')]];$hasSocials=count(array_filter($socialLinks,static fn($x)=>trim($x[2])!==''))>0;?><?php if($hasSocials):?><div class="store-socials" aria-label="Social media"><?php foreach($socialLinks as [$icon,$label,$href]):if(trim($href)==='')continue;?><a href="<?=store_h($href)?>" target="_blank" rel="noopener noreferrer" aria-label="<?=store_h($label)?>" title="<?=store_h($label)?>"><?=store_icon($icon)?></a><?php endforeach;?></div><?php endif;?></div><?php endif;?>
  <?php if($showShop):?><details class="store-footer-group" open><summary><h4><?=store_h(store_t($footerShop!==''?$footerShop:'Shop'))?></h4><span aria-hidden="true">⌄</span></summary><?php foreach($footerLinks['shop'] as $link):?><a href="<?=store_h(store_link_href($link,$accountHref))?>"><?=store_h(store_t((string)$link['label']))?></a><?php endforeach;?></details><?php endif;?>
  <?php if($showDiscover):?><details class="store-footer-group" open><summary><h4><?=store_h(store_t($footerDiscover!==''?$footerDiscover:'Discover'))?></h4><span aria-hidden="true">⌄</span></summary><?php foreach($footerLinks['discover'] as $link):?><a href="<?=store_h(store_link_href($link,$accountHref))?>"><?=store_h(store_t((string)$link['label']))?></a><?php endforeach;?></details><?php endif;?>
  <?php if($showHelp):?><details class="store-footer-group" open><summary><h4><?=store_h(store_t($footerHelp!==''?$footerHelp:'Help'))?></h4><span aria-hidden="true">⌄</span></summary><?php foreach($footerLinks['help'] as $link):?><a href="<?=store_h(store_link_href($link,$accountHref))?>"><?=store_h(store_t((string)$link['label']))?></a><?php endforeach;?></details><?php endif;?>
</footer><?php endif;?>
<?php if($footerVisible('footer_show_copyright')||$showDeveloper||$footerVisible('footer_show_locale')):?><div class="store-footer-bottom"><?php if($footerVisible('footer_show_copyright')):?><span>© <?=store_h($copyrightYear)?> <?=store_h($copyrightOwner)?><?php if($copyrightSuffix!==''):?>. <?=store_h(store_t($copyrightSuffix))?><?php endif;?></span><?php endif;?><?php if($showDeveloper):?><span class="store-developer-credit">Developed by <?php if($developerUrl!==''):?><a href="<?=store_h($developerUrl)?>" target="_blank" rel="noopener noreferrer"><?=store_h($developerName)?></a><?php else:?><?=store_h($developerName)?><?php endif;?></span><?php endif;?><?php if($footerVisible('footer_show_locale')):?><span><?=store_h($layoutCurrency)?><?php if($footerCountry!==''):?> · <?=store_h($footerCountry)?><?php endif;?></span><?php endif;?></div><?php endif;?>
<nav class="store-mobile-tabs" aria-label="<?=store_h(store_t('Mobile shortcuts'))?>">
  <a class="<?=$mobileTab==='home'?'is-active':''?>" href="<?=store_h(route_url('index.php'))?>"><span class="store-mobile-tab-icon"><?=store_icon('home')?></span><small><?=store_h(store_t('Home'))?></small></a>
  <button class="store-mobile-search-tab" type="button" data-store-search-open data-mobile-search-tab aria-controls="store-search-drawer" aria-expanded="false"><span class="store-mobile-tab-icon"><?=store_icon('search')?></span><small><?=store_h(store_t('Search'))?></small></button>
  <?php if(store_module_enabled('wishlist')):?>
  <a class="<?=$mobileTab==='wishlist'?'is-active':''?>" href="<?=store_h(route_url('wishlist.php'))?>"><span class="store-mobile-tab-icon"><?=store_icon('heart')?></span><small><?=store_h(store_t('Wishlist'))?></small></a>
  <?php endif;?>
  <a class="<?=$mobileTab==='bag'?'is-active':''?> store-mobile-bag-tab" href="<?=store_h(route_url('cart.php'))?>"><span class="store-mobile-tab-icon"><?=store_icon('bag')?><?php if($cartCount>0):?><b><?=$cartCount?></b><?php endif;?></span><small><?=store_h(store_t('Bag'))?></small></a>
  <a class="<?=$mobileTab==='account'?'is-active':''?>" href="<?=store_h($accountHref)?>"><span class="store-mobile-tab-icon"><?=store_icon('user')?></span><small><?=store_h(store_t('Account'))?></small></a>
</nav>
<?php $storeUiKeys=['APPLY FILTERS','SHOW ALL PRODUCTS','LOAD MORE','results','showing','QUICK RESULTS','Result','View all results for','No matches for','Press Enter to search the full catalog.','No instant matches. Press Enter for full results.','quick results available.','Quick result available.','Searching…','Instant results are unavailable. Press Enter to search the catalog.','Updating products…','Wishlist could not be updated. Please try again.','Added to your bag.','LOADING…','of','products','Bank transfer / InstaPay','Cash on Delivery','No payment is taken online. We will contact you with Bank transfer / InstaPay details.','Payment is collected on delivery.','Complete delivery details to calculate your exact total.','Complete delivery details','PLACE ORDER','FREE','Standard','free shipping unlocked','Estimated','days','Total updated. Price, stock and delivery will be verified again when you place the order.','Updating total…','Check your delivery details.','Could not refresh the total. Check your connection and try again before placing the order.','PLACING ORDER…','Saving bag…','Bag saved.','Could not update your bag.','Please wait until your bag is saved.'];$storeUiTranslations=[];foreach($storeUiKeys as $storeUiKey)$storeUiTranslations[$storeUiKey]=store_t($storeUiKey);?>
<script type="application/json" id="storefront-ui-translations"><?=json_encode($storeUiTranslations,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script>
<script src="<?=store_h(asset_url('assets/js/store.js'))?>" defer></script><script src="<?=store_h(asset_url('assets/js/alpha6-storefront.js'))?>" defer></script><script src="<?=store_h(asset_url('assets/js/store-header.js'))?>" defer></script><script src="<?=store_h(asset_url('assets/js/performance.js'))?>" defer></script>
</body></html>
<?php }
