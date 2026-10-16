<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
use App\Http\Controllers\ProductController;
use App\Support\View;
init_web_request();
store_apply_configured_redirect();
$data=(new ProductController())->handle();
if(empty($data['product'])){require_once __DIR__.'/includes/error_page.php';render_error_page(404,'Product Not Found','PRODUCT NOT FOUND.','This product is not available in the catalog.');}
View::render('store/product.php',$data);
