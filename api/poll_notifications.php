<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/notification_helper.php';

$u = currentUser();
if (!$u) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

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
