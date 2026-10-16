<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

use App\Support\ServiceFactory;

function require_login(): void {
    init_web_request();
    // Authenticated pages must never be served from browser/proxy cache. Besides
    // protecting private data, this ensures ticket replies and account state are
    // always re-read from the database after an admin action.
    web_private_response_headers();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . route_url('login.php'));
        exit;
    }
    if (!web_current_session_allowed((int)$_SESSION['user_id'])) {
        destroy_web_session();
        header('Location: ' . route_url('login.php') . '?session=revoked', true, 303);
        exit;
    }
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $userId=(int)$_SESSION['user_id'];
    if (array_key_exists($userId,$GLOBALS['_web_current_user_cache']??[])) {
        $cached=$GLOBALS['_web_current_user_cache'][$userId];
        return is_array($cached)?$cached:null;
    }
    if (!web_current_session_allowed($userId)) {
        destroy_web_session();
        $GLOBALS['_web_current_user_cache'][$userId]=null;
        return null;
    }

    $user = ServiceFactory::users(db())->findForSession($userId);

    if (!$user || !(bool)$user['is_active']) {
        $_SESSION = [];
        web_session_request_cache_reset();
        if (session_status()===PHP_SESSION_ACTIVE) session_regenerate_id(true);
        $GLOBALS['_web_current_user_cache'][$userId]=null;
        return null;
    }

    $GLOBALS['_web_current_user_cache'][$userId]=$user;
    return $user;
}
