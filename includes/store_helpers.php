<?php
declare(strict_types=1);

function store_egypt_governorates(): array {
    return ['Cairo','Giza','Alexandria','Dakahlia','Red Sea','Beheira','Fayoum','Gharbia','Ismailia','Menofia','Minya','Qalyubia','New Valley','Suez','Aswan','Assiut','Beni Suef','Port Said','Damietta','Sharqia','South Sinai','Kafr El Sheikh','Matrouh','Luxor','Qena','North Sinai','Sohag'];
}

function store_rate_limit_consume(string $scope,int $maxAttempts,int $windowSeconds,string $identity=''): int {
    $state=rate_limit_state($scope,$windowSeconds,$identity,false);
    if(empty($state['available']))return 0;
    $retry=rate_limit_retry_after($state,$maxAttempts,$windowSeconds,true);if($retry>0)return $retry;
    $state=rate_limit_state($scope,$windowSeconds,$identity,true);if(empty($state['available']))return 0;
    return rate_limit_retry_after($state,$maxAttempts,$windowSeconds,false);
}

function store_h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


function store_runtime_settings(): array {
    static $cached = null;
    if (is_array($cached)) return $cached;
    $defaults = [
        'store_name' => 'Online Store',
        'store_tagline' => 'Curated fragrance for every mood, moment and memory.',
        'currency' => 'EGP',
        'free_shipping_threshold' => '0',
        'catalog_enabled' => '1',
        'cod_enabled' => '1',
        'manual_transfer_enabled' => '1',
    ];
    try {
        // Share the raw store-settings rows with StorefrontRepository during this
        // request. This avoids reading the same table again when the controller and
        // layout both need store configuration.
        $loaded=$GLOBALS['_store_settings_db_cache']??null;
        if(!is_array($loaded)){
            $repo = \App\Support\ServiceFactory::storeSettingsRepository(db());
            $loaded=$repo->all();
            if(is_array($loaded))$GLOBALS['_store_settings_db_cache']=$loaded;
        }
        if (is_array($loaded)) $defaults = array_merge($defaults, $loaded);
    } catch (\Throwable) {}
    $name = trim((string)($defaults['store_name'] ?? ''));
    if ($name === '') $defaults['store_name'] = 'Online Store';
    $published=\App\Storefront\StoreThemeConfig::decode($defaults['v5_theme_published']??'');
    $defaults=\App\Storefront\StoreThemeConfig::overlaySettings($defaults,$published);
    $cached = $defaults;
    return $cached;
}



function storefront_cache_clear(): int {
    static $cleared=false;
    if($cleared)return 0;
    $cleared=true;
    $dir=dirname(__DIR__).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'cache';
    if(!is_dir($dir))return 0;
    $removed=0;
    foreach((array)glob($dir.DIRECTORY_SEPARATOR.'storefront-*.json') as $file){
        if(is_file($file)&&@unlink($file))$removed++;
    }
    return $removed;
}

/** Only published store modules affect public actions; drafts are admin-only. */
function store_module_enabled(string $key, ?array $settings=null): bool {
    $config=$settings??store_runtime_settings();
    return \App\Storefront\StoreModulesConfig::enabled($config,$key);
}

function store_catalog_enabled(?array $settings=null): bool {
    $settings=$settings??store_runtime_settings();
    return (string)($settings['catalog_enabled']??'1')==='1';
}

function store_runtime_name(): string {
    $settings = store_runtime_settings();
    $published=\App\Storefront\StoreThemeConfig::decode($settings['v5_theme_published']??'');
    if($published!==null)return $published['store_name'];
    return trim((string)($settings['store_name'] ?? '')) ?: 'Online Store';
}

function store_runtime_tagline(): string {
    $settings = store_runtime_settings();
    $published=\App\Storefront\StoreThemeConfig::decode($settings['v5_theme_published']??'');
    if($published!==null)return $published['tagline'];
    return trim((string)($settings['store_tagline'] ?? '')) ?: 'Curated fragrance for every mood, moment and memory.';
}

function store_public_support_url(): string {
    return route_url('support.php');
}


function store_apply_configured_redirect(): void {
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true))return;
    if(trim((string)($_SERVER['QUERY_STRING']??''))==='')return;
    $uri=(string)($_SERVER['REQUEST_URI']??'');if($uri==='')return;
    $source=parse_url($uri,PHP_URL_PATH);if(!is_string($source)||$source==='')return;
    $query=(string)($_SERVER['QUERY_STRING']??'');$source.=$query!==''?'?'.$query:'';
    try{
        $q=\db()->prepare("SELECT destination_url,http_code FROM store_redirects WHERE source_path=? AND status='active' LIMIT 1");$q->execute([$source]);$row=$q->fetch();if(!is_array($row))return;
        $destination=trim((string)($row['destination_url']??''));if($destination===''||preg_match('~^https?://~i',$destination))return;
        $code=(int)($row['http_code']??301);if(!in_array($code,[301,302,307,308],true))$code=301;header('Location: '.$destination,true,$code);exit;
    }catch(\Throwable){}
}

function store_money(mixed $value, string $currency='EGP'): string {
    if ($value === null || $value === '') return 'COMING SOON';
    return number_format((float)$value, 0) . ' ' . strtoupper($currency ?: 'EGP');
}

function store_absolute_url(string $path): string {
    if(preg_match('~^https?://~i',$path))return $path;
    $base=rtrim((string)CANONICAL_SITE_URL,'/');
    return $base.'/'.ltrim($path,'/');
}

function store_product_url(array $product): string {
    $slug = trim((string)($product['slug'] ?? $product['product_slug'] ?? ''));
    return route_url('product.php') . ($slug !== '' ? '?slug=' . rawurlencode($slug) : '');
}

function store_catalog_url(array $params=[]): string {
    $base = route_url('perfumes.php');
    $params = array_filter($params, static fn($v) => $v !== null && $v !== '');
    return $params ? $base . '?' . http_build_query($params) : $base;
}

function store_image_path(mixed $path, string $fallback=''): string {
    static $existsCache=[];
    $value=trim((string)$path);
    if($value==='') return trim($fallback);
    if(preg_match('~^https?://~i',$value)) return $value;
    $value=ltrim(str_replace('\\','/',$value),'/');
    if($value===''||str_contains($value,'../')) return trim($fallback);
    if(array_key_exists($value,$existsCache)) return $existsCache[$value] ? $value : trim($fallback);
    $root=dirname(__DIR__);
    $full=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$value);
    $existsCache[$value]=is_file($full);
    return $existsCache[$value] ? $value : trim($fallback);
}

function store_image_variant_path(mixed $path,string $size='md'): string {
    $master=store_image_path($path);
    if($master==='') return '';
    if(preg_match('~^https?://~i',$master)) return $master;
    if(!in_array($size,['sm','md','lg'],true)) return $master;
    $dot=strrpos($master,'.');
    if($dot===false) return $master;
    $candidate=substr($master,0,$dot).'-'.$size.'.webp';
    return store_image_path($candidate)!==''?$candidate:$master;
}

function store_image_srcset(mixed $path): string {
    $master=store_image_path($path);
    if($master===''||preg_match('~^https?://~i',$master)) return '';
    $entries=[];
    $sm=store_image_variant_path($master,'sm');
    $md=store_image_variant_path($master,'md');
    $lg=store_image_variant_path($master,'lg');
    if($sm!==$master)$entries[]=url($sm).' 640w';
    if($md!==$master&&$md!==$sm)$entries[]=url($md).' 1200w';
    if($lg!==$master&&$lg!==$md)$entries[]=url($lg).' 1920w';
    return implode(', ',$entries);
}

function store_media_placeholder(string $label='Image coming soon',string $class=''): string {
    $classes='store-media-placeholder'.($class!==''?' '.preg_replace('/[^a-zA-Z0-9_-]+/',' ',trim($class)):'');
    return '<div class="'.store_h($classes).'" role="img" aria-label="'.store_h($label).' · '.store_h(store_t('photo unavailable')).'"><span aria-hidden="true">▧</span><small>'.store_h(store_t('Photo coming soon')).'</small></div>';
}

function store_cart_items(): array {
    $cart=$_SESSION['_store_cart']??[];
    return is_array($cart)?$cart:[];
}

function store_cart_count(): int {
    $count=0;
    foreach(store_cart_items() as $item)$count+=max(0,(int)($item['quantity']??0));
    return $count;
}

function store_cart_total(): float {
    $total=0.0;
    foreach(store_cart_items() as $item)$total+=max(0,(float)($item['unit_price']??0))*max(0,(int)($item['quantity']??0));
    return $total;
}

function store_cart_key(string $slug, int $variantId=0, string $label=''): string {
    return hash('sha256',strtolower(trim($slug)).'|'.$variantId.'|'.strtolower(trim($label)));
}


/** Real, attributed source assets for original generic curated demo artwork only.
 * Merchant-uploaded imagery always wins. Never use generic illustrations to pose
 * as official brand artwork. See CURATED-IMAGE-ATTRIBUTIONS.txt.
 */
function store_curated_visual(string $kind,string $name,mixed $currentPath): string {
    $path=store_image_path($currentPath);
    // Never inject stock photographs or pseudo-logos; merchant artwork only.
    if($path===''||str_contains($path,'/curated/'))return '';
    return $path;
}
