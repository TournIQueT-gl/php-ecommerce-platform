<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/store_layout.php';
use App\Http\Controllers\GuestOrderTrackingController;
use App\Support\View;
init_web_request();
View::render('store/order-tracking.php',(new GuestOrderTrackingController())->handle());
