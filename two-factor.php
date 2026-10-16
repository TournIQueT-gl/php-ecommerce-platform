<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/totp.php';

use App\Http\Controllers\Auth\TwoFactorLoginController;
use App\Support\View;

init_web_request();
View::render('auth/two-factor.php', (new TwoFactorLoginController())->handle());
