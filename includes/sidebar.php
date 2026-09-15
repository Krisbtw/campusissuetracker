<?php
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/../config/db.php';
$u=currentUser();
$unread=getUnreadCount($pdo,$u['id']);
$initials=strtoupper(substr($u['name'],0,1));
$isAdmin=$u['role']==='admin';
$isMaint=$u['role']==='maintenance';
$base=BASE_URL;
$reporterMenu=[
  ['icon'=>'bi-house','label'=>'Dashboard','href'=>$base.'reporter/dashboard.php'],
  ['icon'=>'bi-plus-square','label'=>'Report Issue','href'=>$base.'reporter/report_issue.php'],
  ['icon'=>'bi-list-ul','label'=>'My Issues','href'=>$base.'reporter/my_issues.php'],
  ['icon'=>'bi-bell','label'=>'Notifications','href'=>$base.'reporter/notifications.php','badge'=>$unread],
];
$adminMenu=[
  ['icon'=>'bi-grid-1x2','label'=>'Dashboard','href'=>$base.'admin/dashboard.php'],
  ['icon'=>'bi-list-ul','label'=>'All Issues','href'=>$base.'admin/issues.php'],
  ['icon'=>'bi-people','label'=>'Users','href'=>$base.'admin/users.php'],
  ['icon'=>'bi-bar-chart-line','label'=>'Reports','href'=>$base.'admin/reports.php'],
  ['icon'=>'bi-bell','label'=>'Notifications','href'=>$base.'admin/notifications.php','badge'=>$unread],
];
$maintMenu=[
  ['icon'=>'bi-house','label'=>'Dashboard','href'=>$base.'maintenance/dashboard.php'],
  ['icon'=>'bi-wrench','label'=>'My Assignments','href'=>$base.'maintenance/my_assignments.php'],
  ['icon'=>'bi-bell','label'=>'Notifications','href'=>$base.'maintenance/notifications.php','badge'=>$unread],
];
$menu=$isAdmin?$adminMenu:($isMaint?$maintMenu:$reporterMenu);
$currentFile=basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<nav class="sidebar" id="sidebar" aria-label="Main navigation">
  <a class="sidebar-logo" href="<?=$base?>index.php">
    <span class="s-text">FixMyCampus</span>
  </a>
  <div class="sidebar-menu">
    <div class="menu-label">Navigation</div>
    <?php foreach($menu as $item):?>
      <a href="<?=$item['href']?>" class="menu-item<?=(basename($item['href'])===$currentFile)?' active':''?>">
        <i class="bi <?=$item['icon']?>"></i>
        <span><?=$item['label']?></span>
        <?php if(!empty($item['badge'])&&$item['badge']>0):?>
          <span class="menu-badge"><?=$item['badge']?></span>
        <?php endif;?>
      </a>
    <?php endforeach;?>
  </div>
  <div class="sidebar-footer">
    <div class="user-avatar-sm"><?=$initials?></div>
    <div class="sf-info">
      <div class="sf-name"><?=htmlspecialchars($u['name'])?></div>
      <div class="sf-role"><?=$u['role']?></div>
    </div>
    <a href="<?=$base?>logout.php" class="btn-icon-sm" title="Logout" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></a>
  </div>
</nav>
<script>
function openSidebar(){document.getElementById('sidebar').classList.add('open');document.getElementById('sidebarOverlay').classList.add('open');}
function closeSidebar(){document.getElementById('sidebar').classList.remove('open');document.getElementById('sidebarOverlay').classList.remove('open');}
</script>
