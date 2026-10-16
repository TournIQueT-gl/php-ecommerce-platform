<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin.php';

use App\Http\Controllers\Admin\ActivityController;
use App\Support\View;

require_admin_capability('activity');
View::render('admin/activity.php', (new ActivityController())->handle());
