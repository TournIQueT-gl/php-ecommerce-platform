<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\AccessController;use App\Support\View;require_admin_capability('access');View::render('admin/access.php',(new AccessController())->handle());
