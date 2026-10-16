<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(503, 'Maintenance', 'TEMPORARILY UNAVAILABLE.', 'The store is temporarily unavailable. Please try again shortly.', 'TRY AGAIN', route_url('maintenance.php'));
