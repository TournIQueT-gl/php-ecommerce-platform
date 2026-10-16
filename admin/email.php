<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin.php';

use App\Http\Controllers\Admin\EmailController;
use App\Support\View;

require_admin_capability('email');
View::render('admin/email.php',(new EmailController())->handle());
