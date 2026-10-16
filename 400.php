<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(400, 'Bad request', 'BAD REQUEST.', 'The request could not be understood.');
