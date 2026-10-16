<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
verify_csrf();
$event=substr(trim((string)($_POST['event']??'')),0,64);
$allowed=['product_viewed','product_added_to_cart','search_used','checkout_started','checkout_completed','wishlist_added'];
if(!in_array($event,$allowed,true)){http_response_code(422);echo json_encode(['ok'=>false]);exit;}
$metadata=[];
foreach(['product_id','product_slug','query','source','variant_id'] as $key){if(isset($_POST[$key])&&$_POST[$key]!=='')$metadata[$key]=substr((string)$_POST[$key],0,160);}
try{
    if(function_exists('visitor_analytics_event'))visitor_analytics_event($event,!empty($_SESSION['user_id'])?(int)$_SESSION['user_id']:null,$metadata);
    echo json_encode(['ok'=>true]);
}catch(Throwable){http_response_code(500);echo json_encode(['ok'=>false]);}
