<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_once __DIR__.'/../includes/site_settings.php';
require_once __DIR__.'/../includes/backups.php';
use App\Http\Controllers\Admin\OperationsController;
use App\Support\View;
require_admin_capability('operations');
View::render('admin/operations.php',(new OperationsController())->handle());
