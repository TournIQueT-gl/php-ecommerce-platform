<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/public_layout.php';

use App\Support\ServiceFactory;
use App\Support\View;

init_web_request();
$settings=store_runtime_settings();
$zones=[];
try{$zones=ServiceFactory::commerceRepository(db())->shippingZones();}catch(\Throwable){}
View::render('public/shipping-delivery.php',['settings'=>$settings,'zones'=>$zones]);
