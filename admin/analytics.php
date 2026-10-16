<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';
use App\Http\Controllers\Admin\AnalyticsController;
use App\Support\View;
require_admin_capability('analytics_view');
View::render('admin/analytics.php',(new AnalyticsController())->handle());
