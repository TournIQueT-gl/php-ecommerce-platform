<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(500, 'Server error', 'SYSTEM ERROR.', 'The website hit an unexpected error. Please try again shortly.', 'BACK TO HOME', null, \App\Support\Logger::requestId());
