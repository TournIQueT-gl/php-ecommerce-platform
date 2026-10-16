<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Support\View;

init_web_request();
View::render('auth/verify-email.php',(new VerifyEmailController())->handle());
