<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(419, 'Session expired', 'SESSION EXPIRED.', 'Your security session expired. Sign in again before repeating the action.', 'SIGN IN AGAIN', route_url('login.php'));
