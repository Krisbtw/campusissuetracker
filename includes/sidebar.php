<?php
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/../config/db.php';
$u = currentUser();
$unread = getUnreadCount($pdo, $u['id']);
$initials = strtoupper(substr($u['name'], 0, 1));
$isAdmin = $u['role'] === 'admin';
$isMaint = $u['role'] === 'maintenance';
$base = BASE_URL;

$reporterMenu = [
  ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'href' => $base . 'reporter/dashboard.php'],
  ['icon' => 'bi-plus-circle-fill', 'label' => 'Report Issue', 'href' => $base . 'reporter/report_issue.php'],
  ['icon' => 'bi-card-checklist', 'label' => 'My Issues', 'href' => $base . 'reporter/my_issues.php'],
  ['icon' => 'bi-bell-fill', 'label' => 'Notifications', 'href' => $base . 'reporter/notifications.php', 'badge' => $unread],
];

$adminMenu = [
  ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'href' => $base . 'admin/dashboard.php'],
  ['icon' => 'bi-card-checklist', 'label' => 'All Issues', 'href' => $base . 'admin/issues.php'],
  ['icon' => 'bi-people-fill', 'label' => 'Users', 'href' => $base . 'admin/users.php'],
  ['icon' => 'bi-bar-chart-fill', 'label' => 'Reports', 'href' => $base . 'admin/reports.php'],
  ['icon' => 'bi-bell-fill', 'label' => 'Notifications', 'href' => $base . 'admin/notifications.php', 'badge' => $unread],
];

$maintMenu = [
  ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard', 'href' => $base . 'maintenance/dashboard.php'],
  ['icon' => 'bi-tools', 'label' => 'My Assignments', 'href' => $base . 'maintenance/my_assignments.php'],
  ['icon' => 'bi-bell-fill', 'label' => 'Notifications', 'href' => $base . 'maintenance/notifications.php', 'badge' => $unread],
];

$menu = $isAdmin ? $adminMenu : ($isMaint ? $maintMenu : $reporterMenu);
$currentFile = basename($_SERVER['PHP_SELF']);
$portalTag = $isAdmin ? 'Admin Console' : ($isMaint ? 'Maintenance Team' : 'Campus Portal');
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
  <div class="sidebar-header">
    <a class="sidebar-brand" href="<?=$base?>index.php">
      <div class="sidebar-brand-icon">
        <i class="bi bi-building-fill-gear"></i>
      </div>
      <div class="sidebar-brand-text">
        <span class="brand-name">FixMyCampus</span>
        <span class="brand-tag"><?=$portalTag?></span>
      </div>
    </a>
    <button type="button" class="sidebar-close-btn" onclick="closeSidebar()" aria-label="Close menu">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div class="sidebar-menu">
    <div class="menu-label">Menu</div>
    <?php foreach($menu as $item): 
      $isActive = (basename($item['href']) === $currentFile);
    ?>
      <a href="<?=$item['href']?>" class="menu-item<?=$isActive ? ' active' : ''?>">
        <i class="bi <?=$item['icon']?>"></i>
        <span class="menu-text"><?=$item['label']?></span>
        <?php if(!empty($item['badge']) && $item['badge'] > 0):?>
          <span class="menu-badge"><?=$item['badge']?></span>
        <?php endif;?>
      </a>
    <?php endforeach;?>
  </div>

  <div class="sidebar-footer">
    <div class="sf-user">
      <div class="user-avatar-sm"><?=$initials?></div>
      <div class="sf-info">
        <div class="sf-name"><?=htmlspecialchars($u['name'])?></div>
        <div class="sf-role"><?=htmlspecialchars(ucfirst($u['role']))?></div>
      </div>
    </div>
    <a href="<?=$base?>logout.php" class="sidebar-logout-btn" title="Sign out" aria-label="Log out">
      <i class="bi bi-box-arrow-right"></i>
      <span>Logout</span>
    </a>
  </div>
</aside>
<script>
function openSidebar(){
  document.getElementById('sidebar')?.classList.add('open');
  document.getElementById('sidebarOverlay')?.classList.add('open');
}
function closeSidebar(){
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay')?.classList.remove('open');
}
</script>
