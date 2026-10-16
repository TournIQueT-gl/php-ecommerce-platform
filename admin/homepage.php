<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\HomepageController;use App\Support\View;require_admin_capability('cms_manage');View::render('admin/homepage.php',(new HomepageController())->handle());
