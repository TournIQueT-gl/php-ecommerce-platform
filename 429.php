<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(429, 'Too many requests', 'TOO MANY REQUESTS.', 'Too many attempts were received. Wait briefly and try again.');
