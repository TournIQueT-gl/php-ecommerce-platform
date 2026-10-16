<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(404, 'Not found', 'PAGE NOT FOUND.', 'The page you requested does not exist or has moved.');
