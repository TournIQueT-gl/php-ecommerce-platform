<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
use App\Http\Controllers\CatalogController;
use App\Support\View;
init_web_request();
store_apply_configured_redirect();
View::render('store/catalog.php',(new CatalogController())->handle());
