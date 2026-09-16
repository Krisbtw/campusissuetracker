<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('maintenance');
$u = currentUser();

$statusFilter   = $_GET['status']   ?? '';
$priorityFilter = $_GET['priority'] ?? '';
$search         = trim($_GET['search'] ?? '');

$where = ["i.assigned_to = ?", "i.parent_id IS NULL"];
$params = [$u['id']];
if ($statusFilter)   { $where[] = "i.status=?";   $params[] = $statusFilter; }
if ($search) {
    $searchLower = '%' . mb_strtolower($search, 'UTF-8') . '%';
    $where[] = "(LOWER(i.title) LIKE ? OR LOWER(i.location) LIKE ? OR LOWER(c.category_name) LIKE ? OR LOWER(u.full_name) LIKE ? OR LOWER(i.description) LIKE ?)";
    $params[] = $searchLower; $params[] = $searchLower; $params[] = $searchLower; $params[] = $searchLower; $params[] = $searchLower;
}

$sql = "SELECT i.*,c.category_name,u.full_name AS reporter FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users u ON i.reported_by=u.user_id WHERE ".implode(' AND ',$where)." ORDER BY CASE i.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END, i.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $issues=$stmt->fetchAll();

$pageTitle='My Assignments'; $pageSubtitle='Issues assigned to you';
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Assignments – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
      </div>
      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" class="filter-control" aria-label="Search">
        <select name="status" class="filter-control" aria-label="Filter by status">
          <option value="">All status</option>
          <?php foreach(['in_progress','resolved','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="priority" class="filter-control" aria-label="Filter by priority">
          <option value="">All priority</option>
          <?php foreach(['low','medium','high','critical'] as $p): ?>
            <option value="<?= $p ?>" <?= $priorityFilter===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="my_assignments.php" class="btn btn-secondary">Clear</a>
      </form>

      <div class="panel">
        <div class="panel-header"><span>Assigned issues (<?= count($issues) ?>)</span></div>
        <?php if(empty($issues)): ?>
          <div class="empty-state"><p>No assignments yet.</p></div>
        <?php else: ?>
          <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
            <thead><tr><th scope="col">#</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Location</th><th scope="col">Reporter</th><th scope="col">Status</th><th scope="col">Assigned</th><th scope="col">Action</th></tr></thead>
            <tbody>
            <?php foreach($issues as $iss): ?>
            <tr>
              <td><span class="issue-id">#<?= $iss['issue_id'] ?></span></td>
              <td class="issue-title">
                <?= htmlspecialchars($iss['title']) ?>
                <?php if (!empty($iss['is_parent'])): ?>
                  <span class="badge badge-amber" style="margin-left:6px;font-size:11px;"><i class="bi bi-collection me-1"></i>Cluster (<?= $iss['affected_count'] ?> reports)</span>
                <?php endif; ?>
              </td>
              <td class="text-muted"><?= htmlspecialchars($iss['category_name']??'N/A') ?></td>
              <td class="text-muted"><?= htmlspecialchars($iss['location']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($iss['reporter']) ?></td>
              <td><?= getStatusBadge($iss['status']) ?></td>
              <td class="text-muted"><?= timeAgo($iss['updated_at']??$iss['created_at']) ?></td>
              <td>
                <a href="update_issue.php?id=<?= $iss['issue_id'] ?>" class="btn-sm-icon" title="Update status"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
</body>
</html>
