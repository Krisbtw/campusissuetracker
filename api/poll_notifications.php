<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification_helper.php';

ensureSession($_GET);
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$u = currentUser();

try {
    $unreadCount = getUnreadCount($pdo, $u['id']);
    $latest = getNotifications($pdo, $u['id'], 3);

    echo json_encode([
        'success' => true,
        'unread' => (int)$unreadCount,
        'notifications' => $latest
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
