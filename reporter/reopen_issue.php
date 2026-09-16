<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';

// Ensure student login
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$u = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_id = intval($_POST['issue_id'] ?? 0);
    $reason   = trim($_POST['reason'] ?? '');

    if ($issue_id <= 0) {
        $_SESSION['flash_error'] = 'Invalid issue ID.';
        header('Location: my_issues.php');
        exit();
    }

    if (empty($reason)) {
        $_SESSION['flash_error'] = 'Please state the reason why the problem is not fixed.';
        header('Location: view_issue.php?id=' . $issue_id);
        exit();
    }

    // Fetch issue to verify ownership & status
    $stmt = $pdo->prepare("SELECT * FROM issues WHERE issue_id = ? AND reported_by = ?");
    $stmt->execute([$issue_id, $u['id']]);
    $issue = $stmt->fetch();

    if (!$issue) {
        // Fallback check if user is admin/staff
        $stmt = $pdo->prepare("SELECT * FROM issues WHERE issue_id = ?");
        $stmt->execute([$issue_id]);
        $issue = $stmt->fetch();
    }

    if (!$issue) {
        $_SESSION['flash_error'] = 'Issue not found or permission denied.';
        header('Location: my_issues.php');
        exit();
    }

    if ($issue['status'] !== 'resolved') {
        $_SESSION['flash_error'] = 'Only resolved issues can be re-opened.';
        header('Location: view_issue.php?id=' . $issue_id);
        exit();
    }

    // Update status to in_progress and increment reopen_count
    $pdo->prepare("UPDATE issues SET status = 'in_progress', reopen_count = COALESCE(reopen_count, 0) + 1, updated_at = NOW() WHERE issue_id = ?")->execute([$issue_id]);

    // Log status timeline change
    $logNote = "Issue re-opened by student " . $u['name'] . ": " . $reason;
    logStatusChange($pdo, $issue_id, $u['id'], 'resolved', 'in_progress', $logNote);

    // If this is a child report in a merged incident, ALSO re-open the parent incident!
    $parentAssigned = null;
    if (!empty($issue['parent_id'])) {
        $parentId = (int)$issue['parent_id'];
        $pdo->prepare("UPDATE issues SET status = 'in_progress', reopen_count = COALESCE(reopen_count, 0) + 1, updated_at = NOW() WHERE issue_id = ?")->execute([$parentId]);
        logStatusChange($pdo, $parentId, $u['id'], 'resolved', 'in_progress', "Incident re-opened because student {$u['name']} reported linked issue #{$issue_id} is not fixed: {$reason}");
        
        $pStmt = $pdo->prepare("SELECT assigned_to, title FROM issues WHERE issue_id = ?");
        $pStmt->execute([$parentId]);
        $parentRow = $pStmt->fetch();
        $parentAssigned = $parentRow['assigned_to'] ?? null;
        if (!empty($parentAssigned)) {
            sendNotification($pdo, $parentAssigned, $parentId, "⚠️ Parent Incident #{$parentId} was RE-OPENED because student {$u['name']} (Ticket #{$issue_id}) marked it NOT FIXED: \"{$reason}\"", 'warning');
        }
    }

    // Also sync to child issues if parent incident
    if (!empty($issue['is_parent'])) {
        $cStmt = $pdo->prepare("SELECT issue_id, reported_by FROM issues WHERE parent_id = ?");
        $cStmt->execute([$issue_id]);
        foreach ($cStmt->fetchAll() as $ch) {
            $pdo->prepare("UPDATE issues SET status = 'in_progress', reopen_count = COALESCE(reopen_count, 0) + 1, updated_at = NOW() WHERE issue_id = ?")->execute([$ch['issue_id']]);
            logStatusChange($pdo, $ch['issue_id'], $u['id'], 'resolved', 'in_progress', "Status re-opened via Parent Incident #{$issue_id}. Reason: {$reason}");
        }
    }

    // Send notification to technician directly assigned to this issue
    $targetTech = !empty($issue['assigned_to']) ? $issue['assigned_to'] : $parentAssigned;
    if (!empty($targetTech) && $targetTech !== $parentAssigned) {
        sendNotification($pdo, $targetTech, $issue_id, "⚠️ Issue #{$issue_id} was RE-OPENED by student {$u['name']}. Reason: {$reason}", 'warning');
    }

    // Always notify all administrators
    $admins = $pdo->query("SELECT user_id FROM users WHERE role='admin'")->fetchAll();
    foreach ($admins as $admin) {
        sendNotification($pdo, $admin['user_id'], $issue_id, "⚠️ Issue #{$issue_id} RE-OPENED by student {$u['name']}. Reason: {$reason}", 'warning');
    }

    $_SESSION['flash_success'] = "Issue #{$issue_id} has been re-opened and assigned staff notified.";
    header('Location: view_issue.php?id=' . $issue_id);
    exit();
} else {
    header('Location: my_issues.php');
    exit();
}
