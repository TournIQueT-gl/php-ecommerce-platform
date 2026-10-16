<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/store_helpers.php';
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
$base=rtrim((string)CANONICAL_SITE_URL,'/');
$urls=[
    ['/',null,'daily',1.0],
    ['/perfumes',null,'daily',.9],
    ['/shipping-delivery',null,'monthly',.6],
    ['/returns',null,'monthly',.6],
    ['/faq',null,'monthly',.5],
    ['/privacy',null,'monthly',.3],
];
try{
    $repo=\App\Support\ServiceFactory::storefront(db());
    if($repo->schemaReady()){
        foreach($repo->sitemapProducts(5000) as $product){
            $slug=trim((string)($product['slug']??''));if($slug==='')continue;
            $lastmod='';$updated=trim((string)($product['updated_at']??''));
            if($updated!==''){$ts=strtotime($updated.' UTC');if($ts!==false)$lastmod=gmdate('Y-m-d',$ts);}
            $urls[]=['/product?slug='.rawurlencode($slug),$lastmod?:null,'weekly',.8];
        }
    }
}catch(Throwable $e){\App\Support\Logger::warning('Sitemap product load failed',['message'=>$e->getMessage()]);}
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach($urls as [$path,$lastmod,$frequency,$priority]){
    $loc=$base.$path;
    echo '  <url><loc>'.htmlspecialchars($loc,ENT_XML1,'UTF-8').'</loc>';
    if($lastmod)echo '<lastmod>'.htmlspecialchars((string)$lastmod,ENT_XML1,'UTF-8').'</lastmod>';
    echo '<changefreq>'.$frequency.'</changefreq><priority>'.number_format((float)$priority,1).'</priority></url>'."\n";
}
echo "</urlset>\n";
