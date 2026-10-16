<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/account_layout.php';
use App\Http\Controllers\NotificationDetailController;
use App\Support\View;
require_login();
View::render('account/notification-detail.php',(new NotificationDetailController())->handle());
