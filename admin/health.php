<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
require_once __DIR__.'/../includes/backups.php';
use App\Http\Controllers\Admin\HealthController;
use App\Support\View;
require_admin_capability('health');
View::render('admin/health.php',(new HealthController())->handle());
