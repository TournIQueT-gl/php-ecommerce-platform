<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\AnnouncementsController;use App\Support\View;require_admin_capability('announcements');View::render('admin/announcements.php',(new AnnouncementsController())->handle());
