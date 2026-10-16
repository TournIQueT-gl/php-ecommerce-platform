<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
use App\Http\Controllers\SearchSuggestController;
init_web_request();
(new SearchSuggestController())->handle();
