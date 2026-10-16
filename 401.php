<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(401, 'Sign in required', 'SIGN IN REQUIRED.', 'Please sign in before opening this page.');
