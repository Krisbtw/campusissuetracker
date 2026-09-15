<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireLogin();
$u=currentUser();
if(isset($_GET['mark_all'])){markAllRead($pdo,$u['id']);header('Location: notifications.php');exit();}
if(isset($_GET['read'])){
  $nid=intval($_GET['read']);
  $pdo->prepare("UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?")->execute([$nid,$u['id']]);
  $n=$pdo->prepare("SELECT issue_id FROM notifications WHERE notification_id=?");$n->execute([$nid]);$row=$n->fetch();
  $role_path=$u['role']==='admin'?'../admin':($u['role']==='maintenance'?'../maintenance':'.');
  if($row&&$row['issue_id']){header("Location: {$role_path}/view_issue.php?id={$row['issue_id']}");}
  else{header('Location: notifications.php');}
  exit();
}
$allNotifs=$pdo->prepare("SELECT n.*,i.title as issue_title FROM notifications n LEFT JOIN issues i ON n.issue_id=i.issue_id WHERE n.user_id=? ORDER BY n.created_at DESC");
$allNotifs->execute([$u['id']]);$allNotifs=$allNotifs->fetchAll();
$unreadCount=array_reduce($allNotifs,fn($c,$n)=>$c+($n['is_read']?0:1),0);
$pageTitle='Notifications';$pageSubtitle='All your alerts and updates';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notifications – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php';?>
  <div class="main-content">
    <?php include '../includes/topbar.php';?>
    <main class="page-content">
      <div class="page-head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <h1 class="page-title">Notifications</h1>
        <?php if($unreadCount>0):?>
          <a href="?mark_all=1" class="btn btn-primary">Mark all read (<?=$unreadCount?>)</a>
        <?php endif;?>
      </div>
      <div class="panel">
        <?php if(empty($allNotifs)):?>
          <div class="empty-state">No notifications yet.</div>
        <?php else:?>
          <?php
          $iconMap=['info'=>'bi-info-circle','success'=>'bi-check-circle','warning'=>'bi-exclamation-triangle','danger'=>'bi-exclamation-circle'];
          foreach($allNotifs as $n):
            $icon=$iconMap[$n['notif_type']]??'bi-bell';
          ?>
          <a href="?read=<?=$n['notification_id']?>" class="notif-page-item<?=$n['is_read']?'':' unread'?>">
            <div class="notif-page-icon"><i class="bi <?=$icon?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div class="notif-page-msg"><?=htmlspecialchars($n['message'])?></div>
              <?php if($n['issue_title']):?>
                <span class="notif-page-link"><?=htmlspecialchars($n['issue_title'])?></span>
              <?php endif;?>
              <div class="notif-page-time"><?=date('d M Y, h:i A',strtotime($n['created_at']))?></div>
            </div>
            <?php if(!$n['is_read']):?><div class="notif-unread-dot"></div><?php endif;?>
          </a>
          <?php endforeach;?>
        <?php endif;?>
      </div>
    </main>
  </div>
</div>
</body></html>
