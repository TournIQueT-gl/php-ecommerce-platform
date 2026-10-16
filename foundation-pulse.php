<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
init_web_request();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
verify_csrf();
try{
    if(!empty($_POST['track_pageview'])&&function_exists('visitor_analytics_track_client_pageview')){
        visitor_analytics_track_client_pageview([
            'path'=>substr((string)($_POST['path']??''),0,255),
            'referrer'=>substr((string)($_POST['referrer']??''),0,1000),
            'utm_source'=>substr((string)($_POST['utm_source']??''),0,100),
            'utm_medium'=>substr((string)($_POST['utm_medium']??''),0,100),
            'utm_campaign'=>substr((string)($_POST['utm_campaign']??''),0,150),
        ]);
    }
    if(session_status()===PHP_SESSION_ACTIVE)@session_write_close();
    $maintenance=web_run_foundation_pulse();
    echo json_encode(['ok'=>true,'maintenance'=>$maintenance],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    \App\Support\Logger::warning('Foundation browser pulse failed',['message'=>$e->getMessage()]);
    http_response_code(500);echo json_encode(['ok'=>false]);
}
