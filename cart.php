<?php
declare(strict_types=1);
$cartAjax=(string)($_GET['_ajax']??'')==='cart';if($cartAjax)ob_start();
require_once __DIR__.'/includes/auth.php';
use App\Http\Controllers\CartController;
use App\Support\View;
init_web_request();
View::render('store/cart.php',(new CartController())->handle());
