<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(405, 'Method not allowed', 'METHOD NOT ALLOWED.', 'This action cannot be completed using the current request method.');
