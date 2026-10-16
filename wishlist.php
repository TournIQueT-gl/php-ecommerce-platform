<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';

use App\Http\Controllers\WishlistController;
use App\Support\Logger;
use App\Support\View;

init_web_request();

$isWishlistAjax=(string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='PerfumeStore-Wishlist'
    || str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');

try {
    $data=(new WishlistController())->handle();
} catch (Throwable $e) {
    Logger::error('Wishlist endpoint failed',[
        'exception'=>get_class($e),
        'message'=>$e->getMessage(),
        'path'=>(string)($_SERVER['REQUEST_URI']??'/wishlist'),
    ]);
    if ($isWishlistAjax) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok'=>false,
            'authenticated'=>!empty($_SESSION['user_id']),
            'message'=>'Wishlist service is temporarily unavailable. Refresh the page and try again.',
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }
    throw $e;
}

require_once __DIR__.'/includes/account_layout.php';
View::render('account/wishlist.php',$data);
