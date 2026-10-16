<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\NotificationDetailController;use App\Support\View;require_admin();View::render('admin/notification-detail.php',(new NotificationDetailController())->handle());
