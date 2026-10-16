<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
use App\Http\Controllers\Admin\OverviewController;
use App\Support\View;
require_admin_capability('overview');
View::render('admin/overview.php',(new OverviewController())->handle());
