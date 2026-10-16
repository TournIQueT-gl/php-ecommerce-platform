<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/error_page.php';
init_web_request();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    render_error_page(405, 'Method not allowed', 'POST REQUIRED.', 'Logout requires a protected form submission.', 'BACK TO ACCOUNT', !empty($_SESSION['user_id']) ? route_url('dashboard.php') : route_url('login.php'));
}
verify_csrf();
try { if(!empty($_SESSION['user_id'])) \App\Support\ServiceFactory::accountActivity(db())->add((int)$_SESSION['user_id'],'web_logout','Website logout','Session ended',client_ip()); } catch(Throwable $e) {}
if(!empty($_SESSION['user_id'])) web_revoke_current_session((int)$_SESSION['user_id'],'logout');
destroy_web_session();
header('Location: ' . route_url('login.php'), true, 303);
exit;
