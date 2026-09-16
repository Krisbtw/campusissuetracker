<?php
require_once __DIR__ . '/notification_helper.php';
require_once __DIR__ . '/../config/db.php';
$u=currentUser();
$unread=getUnreadCount($pdo,$u['id']);
$base=BASE_URL;
$role=$u['role'];
$notifUrl=$base.($role==='admin'?'admin':($role==='maintenance'?'maintenance':'reporter')).'/notifications.php';
$searchUrl=$base.($role==='admin'?'admin/issues.php':'reporter/my_issues.php');
$notifs=getNotifications($pdo,$u['id'],5);
$isAdmin=$role==='admin';
$isMaint=$role==='maintenance';
$reporterMenu=[
  ['label'=>'Dashboard','href'=>$base.'reporter/dashboard.php'],
  ['label'=>'Report Issue','href'=>$base.'reporter/report_issue.php'],
  ['label'=>'My Issues','href'=>$base.'reporter/my_issues.php'],
  ['label'=>'Notifications','href'=>$base.'reporter/notifications.php','badge'=>$unread],
];
$adminMenu=[
  ['label'=>'Dashboard','href'=>$base.'admin/dashboard.php'],
  ['label'=>'All Issues','href'=>$base.'admin/issues.php'],
  ['label'=>'Users','href'=>$base.'admin/users.php'],
  ['label'=>'Reports','href'=>$base.'admin/reports.php'],
  ['label'=>'Notifications','href'=>$base.'admin/notifications.php','badge'=>$unread],
];
$maintMenu=[
  ['label'=>'Dashboard','href'=>$base.'maintenance/dashboard.php'],
  ['label'=>'My Assignments','href'=>$base.'maintenance/my_assignments.php'],
  ['label'=>'Notifications','href'=>$base.'maintenance/notifications.php','badge'=>$unread],
];
$navMenu=$isAdmin?$adminMenu:($isMaint?$maintMenu:$reporterMenu);
$currentFile=basename($_SERVER['PHP_SELF']);
?>
<header class="topbar">
  <div class="topbar-left">
    <button class="btn-hamburger" id="menuToggle" onclick="openSidebar()" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-brand-mobile">
      <a class="app-brand" href="<?=$base?>index.php">FixMyCampus</a>
    </div>
  </div>
  <div class="topbar-right">
    <button class="search-trigger" onclick="openSearch()" aria-label="Search">
      <i class="bi bi-search"></i>
      <span>Search...</span>
      <kbd>⌘K</kbd>
    </button>
    <div style="position:relative;">
      <button class="notif-btn" id="notifBtn" onclick="toggleNotif()" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <?php if($unread>0):?><span class="notif-badge"><?=$unread>9?'9+':$unread?></span><?php endif;?>
      </button>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-head">
          <span>Notifications</span>
          <?php if($unread>0):?><a href="<?=$notifUrl?>?mark_all=1">Mark all read</a><?php endif;?>
        </div>
        <div class="notif-list">
          <?php if(empty($notifs)):?>
            <div style="padding:20px;text-align:center;font-size:1rem;color:var(--text-muted);">No notifications yet</div>
          <?php else:foreach($notifs as $n):?>
            <a href="<?=$notifUrl?>?read=<?=$n['notification_id']?>" class="notif-item<?=$n['is_read']?'':' unread'?>">
              <div class="notif-icon"><i class="bi bi-<?=$n['notif_type']==='success'?'check-circle':($n['notif_type']==='danger'?'exclamation-circle':($n['notif_type']==='warning'?'exclamation-triangle':'info-circle'))?>"></i></div>
              <div>
                <div class="notif-msg"><?=htmlspecialchars(substr($n['message'],0,80)).(strlen($n['message'])>80?'...':'')?></div>
                <div class="notif-time"><?=timeAgo($n['created_at'])?></div>
              </div>
            </a>
          <?php endforeach;endif;?>
        </div>
        <div class="notif-foot"><a href="<?=$notifUrl?>">View all</a></div>
      </div>
    </div>
    <div class="user-menu-wrapper">
      <button type="button" class="topbar-user-btn" id="userMenuBtn" onclick="toggleUserMenu()" aria-expanded="false" aria-label="User account menu">
        <div class="user-avatar-sm"><?=strtoupper(substr($u['name'],0,1))?></div>
        <div class="d-sm-block">
          <div class="topbar-user-name">
            <span><?=htmlspecialchars(explode(' ',$u['name'])[0])?></span>
            <i class="bi bi-chevron-down u-chevron"></i>
          </div>
          <div class="topbar-user-role"><?=$role?></div>
        </div>
      </button>

      <div class="user-dropdown-menu" id="userDropdownMenu">
        <div class="ud-header">
          <div class="ud-avatar"><?=strtoupper(substr($u['name'],0,1))?></div>
          <div class="ud-info">
            <div class="ud-name"><?=htmlspecialchars($u['name'])?></div>
            <div class="ud-email"><?=htmlspecialchars($u['email'] ?? '')?></div>
            <span class="ud-role-badge"><?=htmlspecialchars(ucfirst($role))?></span>
          </div>
        </div>
        <div class="ud-divider"></div>
        <div class="ud-links">
          <a href="<?=$base?>logout.php" class="ud-item ud-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </div>
  </div>
</header>
<div class="search-modal" id="searchModal" onclick="closeSearchOnBg(event)">
  <div class="search-box" role="dialog" aria-label="Search issues">
    <form method="GET" action="<?=$searchUrl?>">
      <div class="search-input-row">
        <i class="bi bi-search"></i>
        <input type="text" name="search" id="searchInput" aria-label="Search issues" placeholder="Search issues by title, location, or reporter..." autocomplete="off" class="form-control">
        <button type="button" onclick="closeSearch()" class="search-dismiss" aria-label="Close search">Esc</button>
      </div>
    </form>
    <div class="search-hint">Press Enter to search — results open in Issues list.</div>
  </div>
</div>
<script>
function toggleNotif(){
  document.getElementById('notifDropdown').classList.toggle('show');
  document.getElementById('userDropdownMenu')?.classList.remove('show');
}
function toggleUserMenu(){
  const dd = document.getElementById('userDropdownMenu');
  const btn = document.getElementById('userMenuBtn');
  const isOpen = dd.classList.toggle('show');
  btn.setAttribute('aria-expanded', isOpen);
  document.getElementById('notifDropdown')?.classList.remove('show');
}
document.addEventListener('click',function(e){
  if(!e.target.closest('#notifBtn')&&!e.target.closest('#notifDropdown')){
    document.getElementById('notifDropdown')?.classList.remove('show');
  }
  if(!e.target.closest('#userMenuBtn')&&!e.target.closest('#userDropdownMenu')){
    const dd = document.getElementById('userDropdownMenu');
    if(dd && dd.classList.contains('show')){
      dd.classList.remove('show');
      document.getElementById('userMenuBtn')?.setAttribute('aria-expanded', 'false');
    }
  }
});
function openSearch(){document.getElementById('searchModal').classList.add('open');setTimeout(()=>document.getElementById('searchInput').focus(),50);}
function closeSearch(){document.getElementById('searchModal').classList.remove('open');}
function closeSearchOnBg(e){if(e.target===document.getElementById('searchModal'))closeSearch();}
document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&e.key==='k'){e.preventDefault();openSearch();}if(e.key==='Escape')closeSearch();});
<script src="<?=$base?>assets/js/animations.js"></script>
<script src="<?=$base?>assets/js/push_notifications.js"></script>
