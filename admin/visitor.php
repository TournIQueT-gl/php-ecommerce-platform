<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\VisitorController;use App\Support\View;require_admin_capability('analytics_manage');View::render('admin/visitor.php',(new VisitorController())->handle());
