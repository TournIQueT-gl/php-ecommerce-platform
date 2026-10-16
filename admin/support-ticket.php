<?php
declare(strict_types=1);require_once __DIR__.'/../includes/admin.php';require_once __DIR__.'/../includes/support.php';use App\Http\Controllers\Admin\SupportTicketController;use App\Support\View;require_admin_capability('support');View::render('admin/support-ticket.php',(new SupportTicketController())->handle());
