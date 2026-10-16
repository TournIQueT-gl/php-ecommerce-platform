<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/admin.php';
require_admin_capability('catalog_manage');
require_once __DIR__.'/../includes/admin_layout.php';
$data=(new \App\Http\Controllers\Admin\TaxonomyController())->handle();
extract($data);
require __DIR__.'/../views/admin/taxonomy.php';
