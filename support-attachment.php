<?php
declare(strict_types=1);require_once __DIR__.'/includes/admin.php';require_once __DIR__.'/includes/support.php';use App\Http\Controllers\SupportAttachmentController;require_login();(new SupportAttachmentController())->handle();
