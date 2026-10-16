<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(403, 'Access denied', 'ACCESS DENIED.', 'You do not have permission to access this area.');
