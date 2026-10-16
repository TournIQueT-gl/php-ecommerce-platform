<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Support\View;

init_web_request();
View::render('auth/forgot-password.php',(new ForgotPasswordController())->handle());
