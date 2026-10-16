<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\NotificationsController;use App\Support\View;require_admin();View::render('admin/notifications.php',(new NotificationsController())->handle());
