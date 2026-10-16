<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('cms_manage');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$q=mb_substr(trim((string)($_GET['q']??'')),0,120);
if(mb_strlen($q)<2){echo json_encode(['ok'=>true,'items'=>[]]);exit;}
try{
    $db=db();$escaped=strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']);$like='%'.$escaped.'%';$prefix=$escaped.'%';
    $stmt=$db->prepare("SELECT p.id,p.name,p.main_image_path,p.status,p.published_at,(p.status='active' AND (p.published_at IS NULL OR p.published_at<=UTC_TIMESTAMP())) AS is_sellable,b.name AS brand_name,(SELECT SUM(GREATEST(v.stock_qty,0)) FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS stock_qty,(SELECT GROUP_CONCAT(v.label ORDER BY v.sort_order,v.id SEPARATOR ' · ') FROM store_product_variants v WHERE v.product_id=p.id AND v.status='active') AS variants FROM store_products p LEFT JOIN store_brands b ON b.id=p.brand_id WHERE p.status<>'archived' AND (p.name LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM store_product_variants sv WHERE sv.product_id=p.id AND sv.status<>'archived' AND (sv.sku LIKE ? ESCAPE '!' OR sv.label LIKE ? ESCAPE '!'))) ORDER BY CASE WHEN p.name LIKE ? ESCAPE '!' THEN 0 ELSE 1 END,p.name,p.id LIMIT 18");
    $stmt->execute([$like,$like,$like,$like,$prefix]);
    $items=[];
    foreach($stmt->fetchAll() as $row){
        $image=store_image_path($row['main_image_path']??'');
        $items[]=['id'=>(int)$row['id'],'name'=>(string)$row['name'],'brand_name'=>(string)($row['brand_name']??''),'status'=>(string)($row['status']??''),'sellable'=>!empty($row['is_sellable']),'stock_qty'=>(int)($row['stock_qty']??0),'variants'=>(string)($row['variants']??''),'image_url'=>$image!==''?url(store_image_variant_path($image,'sm')):''];
    }
    echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    \App\Support\Logger::warning('Homepage product search failed',['message'=>$e->getMessage()]);
    http_response_code(500);echo json_encode(['ok'=>false,'items'=>[]]);
}
