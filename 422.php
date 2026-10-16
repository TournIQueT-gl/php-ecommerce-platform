<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
render_error_page(422, 'Unprocessable request', 'CHECK YOUR DETAILS.', 'Some submitted information could not be accepted.');
