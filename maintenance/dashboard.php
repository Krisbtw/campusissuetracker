<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('maintenance');
$u=currentUser();
$stA=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE assigned_to=?");$stA->execute([$u['id']]);$assigned=$stA->fetchColumn();
$stP=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE assigned_to=? AND status='in_progress'");$stP->execute([$u['id']]);$inprog=$stP->fetchColumn();
$stD=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE assigned_to=? AND status='resolved'");$stD->execute([$u['id']]);$done=$stD->fetchColumn();
$myIssues=$pdo->prepare("SELECT i.*,c.category_name,u.full_name AS reporter FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users u ON i.reported_by=u.user_id WHERE i.assigned_to=? ORDER BY CASE i.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END, i.created_at DESC LIMIT 8");
$myIssues->execute([$u['id']]);$assignments=$myIssues->fetchAll();
$unread=getUnreadCount($pdo,$u['id']);
$pageTitle='Dashboard';$pageSubtitle='Your active assignments';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Maintenance Dashboard – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php';?>
  <div class="main-content">
    <?php include '../includes/topbar.php';?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title"><?=htmlspecialchars($pageTitle)?></h1>
        <p class="page-sub"><?=htmlspecialchars($pageSubtitle)?></p>
      </div>
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-value"><?=$assigned?></div><div class="stat-label">Assigned</div></div>
        <div class="stat-card"><div class="stat-value"><?=$inprog?></div><div class="stat-label">In progress</div></div>
        <div class="stat-card"><div class="stat-value"><?=$done?></div><div class="stat-label">Resolved</div></div>
      </div>
      <div class="content-grid">
        <div class="panel">
          <div class="panel-header"><span>My assignments</span><a href="my_assignments.php">View all</a></div>
          <?php if(empty($assignments)):?>
            <div class="empty-state"><p>No issues assigned.</p></div>
          <?php else:?>
            <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
              <thead><tr><th scope="col">#</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Location</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
              <tbody>
              <?php foreach($assignments as $iss):?>
              <tr>
                <td><span class="issue-id">#<?=$iss['issue_id']?></span></td>
                <td class="issue-title"><?=htmlspecialchars($iss['title'])?></td>
                <td class="text-muted"><?=htmlspecialchars($iss['category_name']??'—')?></td>
                <td class="text-muted"><?=htmlspecialchars($iss['location']??'—')?></td>
                <td><?=getStatusBadge($iss['status'])?></td>
                <td><a href="update_issue.php?id=<?=$iss['issue_id']?>" class="btn-sm-icon" title="Update"><i class="bi bi-pencil"></i></a></td>
              </tr>
              <?php endforeach;?>
              </tbody>
            </table></div>
          <?php endif;?>
        </div>
        <div>
          <div class="panel">
            <div class="panel-header">Quick actions</div>
            <div class="panel-body">
              <a href="my_assignments.php" class="quick-action">All assignments</a>
              <a href="my_assignments.php?status=in_progress" class="quick-action">In progress</a>
              <a href="notifications.php" class="quick-action">Notifications<?php if($unread>0):?><span class="menu-badge"><?=$unread?></span><?php endif;?></a>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
</body></html>
