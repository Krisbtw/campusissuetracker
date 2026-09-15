<?php
/**
 * FixMyCampus - Notification Helper
 * Call these functions to create notifications in the DB
 */
require_once __DIR__ . '/../config/db.php';

function sendNotification($pdo, $user_id, $issue_id, $message, $type = 'info') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, issue_id, message, notif_type) VALUES (?,?,?,?)");
    $stmt->execute([$user_id, $issue_id, $message, $type]);
}

function getUnreadCount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

function getNotifications($pdo, $user_id, $limit = 10) {
    $limit = (int)$limit;
    $stmt = $pdo->prepare("SELECT n.*, i.title as issue_title FROM notifications n LEFT JOIN issues i ON n.issue_id = i.issue_id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT {$limit}");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function markAllRead($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

function logStatusChange($pdo, $issue_id, $changed_by, $old_status, $new_status, $remarks = '') {
    $stmt = $pdo->prepare("INSERT INTO status_history (issue_id, changed_by, old_status, new_status, remarks) VALUES (?,?,?,?,?)");
    $stmt->execute([$issue_id, $changed_by, $old_status, $new_status, $remarks]);
}

function getPriorityBadge($priority) {
    $map = [
        'low'      => '<span class="badge badge-neutral">Low</span>',
        'medium'   => '<span class="badge badge-zinc">Medium</span>',
        'high'     => '<span class="badge badge-amber">High</span>',
        'critical' => '<span class="badge badge-rose">Critical</span>',
    ];
    return $map[$priority] ?? '<span class="badge badge-neutral">' . ucfirst($priority) . '</span>';
}

function getStatusBadge($status) {
    $map = [
        'pending'     => '<span class="badge badge-zinc">Pending</span>',
        'in_progress' => '<span class="badge badge-amber">In Progress</span>',
        'resolved'    => '<span class="badge badge-emerald">Resolved</span>',
        'closed'      => '<span class="badge badge-neutral">Closed</span>',
        'rejected'    => '<span class="badge badge-rose">Rejected</span>',
    ];
    return $map[$status] ?? '<span class="badge badge-neutral">' . ucfirst($status) . '</span>';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('d M Y', $time);
}

function getSlaBadge($createdAt, $priority, $status) {
    if (in_array($status, ['resolved', 'closed', 'rejected'])) {
        return '<span class="badge badge-emerald sla-badge">SLA Met</span>';
    }

    $hoursMap = [
        'critical' => 4,
        'high'     => 24,
        'medium'   => 48,
        'low'      => 72
    ];
    $targetHours = $hoursMap[strtolower($priority)] ?? 48;
    $createdTime = strtotime($createdAt);
    $targetTime  = $createdTime + ($targetHours * 3600);
    $diff = $targetTime - time();

    if ($diff > 0) {
        $hrs = floor($diff / 3600);
        $mins = floor(($diff % 3600) / 60);
        $timeStr = ($hrs > 0 ? "{$hrs}h " : "") . "{$mins}m left";
        $badgeClass = ($hrs < 2) ? "badge-amber" : "badge-emerald";
        return "<span class=\"badge sla-badge {$badgeClass}\"><i class=\"bi bi-stopwatch\"></i> {$timeStr}</span>";
    } else {
        $absDiff = abs($diff);
        $hrs = floor($absDiff / 3600);
        $mins = floor(($absDiff % 3600) / 60);
        $timeStr = ($hrs > 0 ? "{$hrs}h " : "") . "{$mins}m";
        return "<span class=\"badge badge-rose sla-badge\"><i class=\"bi bi-exclamation-triangle-fill\"></i> SLA Overdue by {$timeStr}</span>";
    }
}
