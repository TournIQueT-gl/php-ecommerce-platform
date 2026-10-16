<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\CatalogController;use App\Support\View;require_admin_capability('catalog_manage');View::render('admin/catalog.php',(new CatalogController())->handle());
