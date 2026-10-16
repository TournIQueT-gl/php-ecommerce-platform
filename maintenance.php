<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/error_page.php';
init_web_request();
$state=maintenance_state();
render_error_page(503,'Maintenance','TEMPORARILY UNAVAILABLE.',(string)($state['message']??'We are performing scheduled maintenance. Please check back shortly.'),'TRY AGAIN',route_url('maintenance.php'));
