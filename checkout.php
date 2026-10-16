<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
use App\Http\Controllers\CheckoutController;
use App\Support\View;
init_web_request();
View::render('store/checkout.php',(new CheckoutController())->handle());
