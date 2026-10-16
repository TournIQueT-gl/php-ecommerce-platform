<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/public_layout.php';

use App\Support\View;

init_web_request();
View::render('public/returns.php',['settings'=>store_runtime_settings()]);
