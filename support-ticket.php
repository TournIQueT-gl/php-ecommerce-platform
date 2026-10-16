<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';require_once __DIR__.'/includes/account_layout.php';require_once __DIR__.'/includes/support.php';
use App\Http\Controllers\SupportTicketController;use App\Support\View;
require_login();View::render('account/support-ticket.php',(new SupportTicketController())->handle());
