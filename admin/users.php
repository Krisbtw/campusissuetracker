<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u = currentUser();

$msg = $err = '';

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = intval($_GET['delete']);
    if ($did !== $u['id']) {
        $pdo->prepare("DELETE FROM users WHERE user_id=? AND role != 'admin'")->execute([$did]);
        $msg = 'User deleted.';
    } else { $err = 'Cannot delete yourself.'; }
}

$roleFilter = $_GET['role'] ?? '';
$search     = trim($_GET['search'] ?? '');
$where=['1=1']; $params=[];
if ($roleFilter) { $where[]="role=?"; $params[]=$roleFilter; }
if ($search)     { $where[]="(full_name LIKE ? OR email LIKE ? OR department LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }

$sql="SELECT u.*,(SELECT COUNT(*) FROM issues i WHERE i.reported_by=u.user_id) AS issue_count FROM users u WHERE ".implode(' AND ',$where)." ORDER BY u.created_at DESC";
$stmt=$pdo->prepare($sql); $stmt->execute($params);
$users=$stmt->fetchAll();

$pageTitle='Users Management'; $pageSubtitle='View and manage registered users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Users – FixMyCampus Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title">Users</h1>
      </div>
      <?php if($msg): ?><div class="alert-banner alert-success" role="status"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search name, email, department..." value="<?= htmlspecialchars($search) ?>" class="filter-control" aria-label="Search">
        <select name="role" class="filter-control" aria-label="Filter by role">
          <option value="">All roles</option>
          <?php foreach(['student','staff','admin','maintenance'] as $r): ?>
            <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="users.php" class="btn btn-secondary">Clear</a>
      </form>

      <div class="panel">
        <div class="panel-header"><span>Users (<?= count($users) ?>)</span></div>
        <div class="table-scroll" role="region" aria-label="Users table" tabindex="0"><table class="table-dark-custom">
          <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Department</th><th scope="col">Phone</th><th scope="col">Issues</th><th scope="col">Joined</th><th scope="col">Action</th></tr></thead>
          <tbody>
          <?php foreach($users as $usr): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="user-avatar-sm"><?= strtoupper(substr($usr['full_name'],0,1)) ?></div>
                <span style="font-weight:500;"><?= htmlspecialchars($usr['full_name']) ?></span>
              </div>
            </td>
            <td class="text-muted"><?= htmlspecialchars($usr['email']) ?></td>
            <td><span class="badge badge-neutral"><?= ucfirst($usr['role']) ?></span></td>
            <td class="text-muted"><?= htmlspecialchars($usr['department']??'—') ?></td>
            <td class="text-muted"><?= htmlspecialchars($usr['phone']??'—') ?></td>
            <td class="issue-id"><?= $usr['issue_count'] ?></td>
            <td class="text-muted"><?= date('d M Y',strtotime($usr['created_at'])) ?></td>
            <td>
              <?php if($usr['user_id'] !== $u['id'] && $usr['role'] !== 'admin'): ?>
                <a href="?delete=<?= $usr['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'search'=>$search])) ?>" class="btn-sm-icon danger" onclick="return confirm('Delete this user?')" title="Delete"><i class="bi bi-trash"></i></a>
              <?php else: ?>
                <span class="muted-note">Protected</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    </main>
  </div>
</div>
</body>
</html>
