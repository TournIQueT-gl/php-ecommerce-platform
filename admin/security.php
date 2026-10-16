<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin.php';

use App\Http\Controllers\Admin\SecurityController;
use App\Support\View;

require_admin_capability('security');
View::render('admin/security.php', (new SecurityController())->handle());
