<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(409, 'Conflict', 'REQUEST CONFLICT.', 'The request conflicts with the current state. Refresh and try again.');
