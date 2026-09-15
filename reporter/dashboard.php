<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole(['student','staff']);
$u=currentUser();
$stT=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE reported_by=?");$stT->execute([$u['id']]);$total=$stT->fetchColumn();
$stP=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE reported_by=? AND status='pending'");$stP->execute([$u['id']]);$pending=$stP->fetchColumn();
$stI=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE reported_by=? AND status='in_progress'");$stI->execute([$u['id']]);$progress=$stI->fetchColumn();
$stR=$pdo->prepare("SELECT COUNT(*) FROM issues WHERE reported_by=? AND status='resolved'");$stR->execute([$u['id']]);$resolved=$stR->fetchColumn();
$rs=$pdo->prepare("SELECT i.*,c.category_name FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id WHERE i.reported_by=? ORDER BY i.created_at DESC LIMIT 5");
$rs->execute([$u['id']]);$recentIssues=$rs->fetchAll();
$unread=getUnreadCount($pdo,$u['id']);
$pageTitle='Dashboard';$pageSubtitle='Welcome back, '.explode(' ',$u['name'])[0];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard – FixMyCampus</title>
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
        <div class="stat-card"><div class="stat-value"><?=$total?></div><div class="stat-label">Total reported</div></div>
        <div class="stat-card"><div class="stat-value"><?=$pending?></div><div class="stat-label">Pending</div></div>
        <div class="stat-card"><div class="stat-value"><?=$progress?></div><div class="stat-label">In progress</div></div>
        <div class="stat-card"><div class="stat-value"><?=$resolved?></div><div class="stat-label">Resolved</div></div>
      </div>
      <div class="content-grid">
        <div class="panel">
          <div class="panel-header"><span>Recent issues</span><a href="my_issues.php">View all</a></div>
          <?php if(empty($recentIssues)):?>
            <div class="empty-state"><p>No issues yet.</p><a href="report_issue.php" class="btn btn-primary">Report an issue</a></div>
          <?php else:?>
            <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
              <thead><tr><th scope="col">#</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
              <tbody>
              <?php foreach($recentIssues as $iss):?>
              <tr>
                <td><span class="issue-id">#<?=$iss['issue_id']?></span></td>
                <td class="issue-title"><?=htmlspecialchars($iss['title'])?></td>
                <td class="text-muted"><?=htmlspecialchars($iss['category_name']??'—')?></td>
                <td><?=getStatusBadge($iss['status'])?></td>
                <td><span class="issue-id"><?=date('d M',strtotime($iss['created_at']))?></span></td>
                <td><a href="view_issue.php?id=<?=$iss['issue_id']?>" class="btn-sm-icon" aria-label="View issue"><i class="bi bi-arrow-right"></i></a></td>
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
              <a href="report_issue.php" class="quick-action">Report new issue</a>
              <a href="my_issues.php" class="quick-action">Track my issues</a>
              <a href="notifications.php" class="quick-action">Notifications<?php if($unread>0):?><span class="menu-badge"><?=$unread?></span><?php endif;?></a>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
</body></html>
