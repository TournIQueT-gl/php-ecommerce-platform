<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
use App\Http\Controllers\Admin\UniversalCatalogController;
use App\Support\View;
require_admin_capability('catalog_manage');
View::render('admin/universal-catalog.php',(new UniversalCatalogController())->handle());
