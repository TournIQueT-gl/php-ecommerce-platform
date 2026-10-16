<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin.php';

use App\Support\ServiceFactory;

require_admin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    $admin = current_user();
    $adminId = (int)($admin['id'] ?? 0);
    $state = ServiceFactory::navigationState(db())->forUser(
        $adminId,
        true,
        admin_notification_types_for_role($admin)
    );

    echo json_encode([
        'ok' => true,
        'unread' => (int)($state['global_unread'] ?? 0),
        'has_unread' => !empty($state['has_unread']),
        'href' => (string)($state['notification_href'] ?? route_url('admin/notifications.php')),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    \App\Support\Logger::error('Admin navigation-state endpoint failed', ['message' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Notification state is temporarily unavailable.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
