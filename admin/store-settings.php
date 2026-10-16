<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';use App\Http\Controllers\Admin\StoreSettingsController;use App\Support\View;require_admin_capability('store_settings');View::render('admin/store-settings.php',(new StoreSettingsController())->handle());
