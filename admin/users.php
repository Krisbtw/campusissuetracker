<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u = currentUser();

$msg = $err = '';

// Approve staff user
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $aid = intval($_GET['approve']);
    $tStmt = $pdo->prepare("SELECT user_id, full_name, role FROM users WHERE user_id=?");
    $tStmt->execute([$aid]);
    $tu = $tStmt->fetch();
    if ($tu) {
        $pdo->prepare("UPDATE users SET status='active' WHERE user_id=?")->execute([$aid]);
        sendNotification($pdo, $aid, null, "Your staff account has been verified and approved by the administrator! You now have full access to sign in.", 'success');
        $msg = 'Staff account for "' . htmlspecialchars($tu['full_name']) . '" has been approved and activated.';
    } else {
        $err = 'User not found.';
    }
}

// Reject staff user
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $rid = intval($_GET['reject']);
    $tStmt = $pdo->prepare("SELECT user_id, full_name, role FROM users WHERE user_id=?");
    $tStmt->execute([$rid]);
    $tu = $tStmt->fetch();
    if ($tu && $tu['role'] !== 'admin') {
        $pdo->prepare("UPDATE users SET status='rejected' WHERE user_id=?")->execute([$rid]);
        sendNotification($pdo, $rid, null, "Your staff registration request was rejected by an administrator.", 'rose');
        $msg = 'Staff registration request for "' . htmlspecialchars($tu['full_name']) . '" has been rejected.';
    } else {
        $err = 'User cannot be rejected.';
    }
}

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = intval($_GET['delete']);
    if ($did !== $u['id']) {
        $pdo->prepare("DELETE FROM users WHERE user_id=? AND role != 'admin'")->execute([$did]);
        $msg = 'User deleted.';
    } else { $err = 'Cannot delete yourself.'; }
}

$roleFilter   = $_GET['role']   ?? '';
$statusFilter = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$where=['1=1']; $params=[];
if ($roleFilter)   { $where[]="u.role=?";   $params[]=$roleFilter; }
if ($statusFilter) { $where[]="u.status=?"; $params[]=$statusFilter; }
if ($search)     {
    $searchLower = '%' . mb_strtolower($search, 'UTF-8') . '%';
    $where[] = "(LOWER(u.full_name) LIKE ? OR LOWER(u.email) LIKE ? OR LOWER(u.department) LIKE ?)";
    $params[] = $searchLower; $params[] = $searchLower; $params[] = $searchLower;
}

// Fetch pending staff for the highlighted review card
$pendingStaff = $pdo->query("SELECT * FROM users WHERE role='staff' AND status='pending' ORDER BY created_at ASC")->fetchAll();

$sql="SELECT u.*,(SELECT COUNT(*) FROM issues i WHERE i.reported_by=u.user_id) AS issue_count FROM users u WHERE ".implode(' AND ',$where)." ORDER BY CASE WHEN u.status='pending' THEN 0 ELSE 1 END, u.created_at DESC";
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
        <p class="page-sub">Manage campus accounts and verify staff registrations</p>
      </div>
      <?php if($msg): ?><div class="alert-banner alert-success" role="status"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <!-- Pending Staff Verification Banner/Panel -->
      <?php if (!empty($pendingStaff)): ?>
      <div class="panel border-beam-card spotlight-card fade-in-up" style="border-left:4px solid var(--amber);margin-bottom:20px;">
        <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
          <div style="display:flex;align-items:center;gap:8px;">
            <span class="pulse-dot pulse-dot-amber"></span>
            <i class="bi bi-shield-exclamation" style="color:var(--amber);font-size:1.15rem;"></i>
            <span style="font-weight:600;">Pending Staff Verifications (<?= count($pendingStaff) ?>)</span>
          </div>
          <span class="badge badge-amber">Action Required</span>
        </div>
        <div class="panel-body">
          <p class="muted-note" style="margin-bottom:14px;">The following users registered with the <b>Staff</b> role and require administrator verification before they can sign in:</p>
          <div class="table-scroll" role="region" aria-label="Pending staff table" tabindex="0">
            <table class="table-dark-custom">
              <thead>
                <tr>
                  <th scope="col">Staff Applicant</th>
                  <th scope="col">Email</th>
                  <th scope="col">Department</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Registered</th>
                  <th scope="col">Verification Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingStaff as $ps): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="user-avatar-sm" style="background:var(--burg);color:#fff;"><?= strtoupper(substr($ps['full_name'],0,1)) ?></div>
                      <div>
                        <span style="font-weight:600;"><?= htmlspecialchars($ps['full_name']) ?></span>
                        <div class="muted-note" style="font-size:11px;">Staff applicant</div>
                      </div>
                    </div>
                  </td>
                  <td class="text-muted"><?= htmlspecialchars($ps['email']) ?></td>
                  <td><span class="badge badge-blue"><?= htmlspecialchars($ps['department'] ?? '—') ?></span></td>
                  <td class="text-muted"><?= htmlspecialchars($ps['phone'] ?? '—') ?></td>
                  <td class="text-muted"><?= date('d M Y, h:i A', strtotime($ps['created_at'])) ?><br><span style="font-size:11px;"><?= timeAgo($ps['created_at']) ?></span></td>
                  <td>
                    <div style="display:flex;gap:8px;align-items:center;">
                      <a href="?approve=<?= $ps['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn btn-sm" style="background:var(--emerald);color:#fff;border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;">
                        <i class="bi bi-check-lg"></i> Accept
                      </a>
                      <a href="?reject=<?= $ps['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn btn-sm" style="background:transparent;border:1px solid var(--rose);color:var(--rose);border-radius:6px;padding:6px 14px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:5px;" onclick="return confirm('Reject this staff application?')">
                        <i class="bi bi-x-lg"></i> Reject
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search name, email, department..." value="<?= htmlspecialchars($search) ?>" class="filter-control" aria-label="Search">
        <select name="role" class="filter-control" aria-label="Filter by role">
          <option value="">All roles</option>
          <?php foreach(['student','staff','admin','maintenance'] as $r): ?>
            <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="filter-control" aria-label="Filter by status">
          <option value="">All statuses</option>
          <option value="active"   <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
          <option value="pending"  <?= $statusFilter==='pending'?'selected':'' ?>>Pending verification</option>
          <option value="rejected" <?= $statusFilter==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="users.php" class="btn btn-secondary">Clear</a>
      </form>

      <div class="panel spotlight-card fade-in-up stagger-2">
        <div class="panel-header"><span>Users (<?= count($users) ?>)</span></div>
        <div class="table-scroll" role="region" aria-label="Users table" tabindex="0"><table class="table-dark-custom">
          <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col">Department</th><th scope="col">Phone</th><th scope="col">Issues</th><th scope="col">Joined</th><th scope="col">Action</th></tr></thead>
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
            <td>
              <?php
              $st = $usr['status'] ?? 'active';
              if ($st === 'active'): ?>
                <span class="badge badge-emerald">Active</span>
              <?php elseif ($st === 'pending'): ?>
                <span class="badge badge-amber"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
              <?php elseif ($st === 'rejected'): ?>
                <span class="badge badge-rose">Rejected</span>
              <?php else: ?>
                <span class="badge badge-neutral"><?= htmlspecialchars(ucfirst($st)) ?></span>
              <?php endif; ?>
            </td>
            <td class="text-muted"><?= htmlspecialchars($usr['department']??'—') ?></td>
            <td class="text-muted"><?= htmlspecialchars($usr['phone']??'—') ?></td>
            <td class="issue-id"><?= $usr['issue_count'] ?></td>
            <td class="text-muted"><?= date('d M Y',strtotime($usr['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;align-items:center;">
                <?php if (($usr['status'] ?? 'active') === 'pending'): ?>
                  <a href="?approve=<?= $usr['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn-sm-icon" style="color:var(--emerald);" title="Accept Staff"><i class="bi bi-check-circle-fill"></i></a>
                  <a href="?reject=<?= $usr['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn-sm-icon" style="color:var(--rose);" onclick="return confirm('Reject this staff application?')" title="Reject Staff"><i class="bi bi-x-circle-fill"></i></a>
                <?php elseif (($usr['status'] ?? 'active') === 'rejected'): ?>
                  <a href="?approve=<?= $usr['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn-sm-icon" style="color:var(--emerald);" title="Re-approve Staff"><i class="bi bi-arrow-clockwise"></i></a>
                <?php endif; ?>

                <?php if($usr['user_id'] !== $u['id'] && $usr['role'] !== 'admin'): ?>
                  <a href="?delete=<?= $usr['user_id'] ?>&<?= http_build_query(array_filter(['role'=>$roleFilter,'status'=>$statusFilter,'search'=>$search])) ?>" class="btn-sm-icon danger" onclick="return confirm('Delete this user?')" title="Delete"><i class="bi bi-trash"></i></a>
                <?php else: ?>
                  <span class="muted-note">Protected</span>
                <?php endif; ?>
              </div>
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
