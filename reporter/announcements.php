<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireLogin();
$u = currentUser();

$urgencyFilter = trim($_GET['urgency'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');

$sql = "
    SELECT a.*, u.full_name as creator_name
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.user_id
    WHERE 1=1
";
$params = [];

if (!empty($urgencyFilter) && in_array($urgencyFilter, ['info', 'warning', 'critical'])) {
    $sql .= " AND a.urgency = ?";
    $params[] = $urgencyFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (LOWER(a.title) LIKE ? OR LOWER(a.message) LIKE ?)";
    $params[] = '%' . strtolower($searchQuery) . '%';
    $params[] = '%' . strtolower($searchQuery) . '%';
}

$sql .= " ORDER BY a.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allAnnouncements = [];
}

$now = time();
$activeList = [];
$pastList = [];

foreach ($allAnnouncements as $a) {
    $isExpired = (!empty($a['expires_at']) && strtotime($a['expires_at']) <= $now);
    if (!empty($a['is_active']) && !$isExpired) {
        $activeList[] = $a;
    } else {
        $pastList[] = $a;
    }
}

$pageTitle = 'Campus Announcements';
$pageSubtitle = 'Stay informed with live alerts, shutdowns, and campus updates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Announcements – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/animations.css">
<style>
.ann-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
  gap: 18px;
  margin-top: 16px;
}
.ann-card {
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, #e2e8f0);
  border-radius: var(--r-md, 14px);
  padding: 20px;
  position: relative;
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.ann-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px -4px rgba(0, 0, 0, 0.08);
}
.ann-card.is-active {
  box-shadow: 0 4px 18px -2px rgba(0, 0, 0, 0.08);
}
.ann-card.urgency-critical.is-active {
  box-shadow: 0 6px 22px -2px rgba(225, 29, 72, 0.16);
}
.ann-card.urgency-warning.is-active {
  box-shadow: 0 6px 22px -2px rgba(217, 119, 6, 0.16);
}
.ann-card.urgency-info.is-active {
  box-shadow: 0 6px 22px -2px rgba(37, 99, 235, 0.14);
}
.ann-card.is-expired {
  opacity: 0.78;
  background: rgba(248, 250, 252, 0.65);
}
.ann-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}
.ann-title {
  font-weight: 700;
  font-size: 16px;
  color: var(--text-primary, #1e293b);
  line-height: 1.35;
  margin-bottom: 6px;
}
.ann-body {
  font-size: 14px;
  line-height: 1.55;
  color: var(--text-secondary, #475569);
  margin-bottom: 16px;
}
.ann-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  font-size: 12px;
  color: var(--text-muted, #94a3b8);
  border-top: 1px solid var(--border-subtle, #f1f5f9);
  padding-top: 12px;
}
.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-secondary, #64748b);
  background: var(--surface, #fff);
  border: 1px solid var(--border, #cbd5e1);
  text-decoration: none;
  transition: all 0.15s ease;
}
.filter-chip:hover {
  background: var(--surface-hover, #f8fafc);
  color: var(--text-primary, #0f172a);
}
.filter-chip.active {
  background: var(--primary, #7b1e2b);
  color: #fff;
  border-color: var(--primary, #7b1e2b);
}
</style>
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div class="page-head" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
          <h1 class="page-title"><i class="bi bi-megaphone-fill me-2" style="color:var(--burg,#7b1e2b);"></i>Campus Announcements</h1>
          <p class="page-sub">Current alerts, scheduled maintenance, and important campus broadcasts</p>
        </div>
        <div style="display:flex;gap:10px;">
          <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
          <a href="report_issue.php" class="btn btn-primary"><i class="bi bi-plus-circle-fill me-1"></i>Report Issue</a>
        </div>
      </div>

      <!-- Search & Filters -->
      <div class="panel" style="margin-bottom:20px;padding:16px 20px;">
        <form method="GET" action="announcements.php" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:13px;font-weight:600;color:var(--text-muted);margin-right:4px;">Urgency:</span>
            <a href="announcements.php<?= $searchQuery ? '?q=' . urlencode($searchQuery) : '' ?>" class="filter-chip <?= empty($urgencyFilter) ? 'active' : '' ?>">All</a>
            <a href="announcements.php?urgency=critical<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" class="filter-chip <?= $urgencyFilter === 'critical' ? 'active' : '' ?>">Critical</a>
            <a href="announcements.php?urgency=warning<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" class="filter-chip <?= $urgencyFilter === 'warning' ? 'active' : '' ?>">Warning</a>
            <a href="announcements.php?urgency=info<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>" class="filter-chip <?= $urgencyFilter === 'info' ? 'active' : '' ?>">Info</a>
          </div>

          <div style="display:flex;align-items:center;gap:8px;">
            <?php if (!empty($urgencyFilter)): ?>
              <input type="hidden" name="urgency" value="<?= htmlspecialchars($urgencyFilter) ?>">
            <?php endif; ?>
            <div style="position:relative;width:240px;">
              <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
              <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Search notices..." style="padding-left:32px;height:36px;font-size:13px;">
            </div>
            <?php if (!empty($searchQuery) || !empty($urgencyFilter)): ?>
              <a href="announcements.php" class="btn btn-secondary btn-sm" title="Clear filters"><i class="bi bi-x-circle"></i></a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Active Broadcasts -->
      <?php if (!empty($activeList)): ?>
        <div style="margin-bottom:30px;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <h2 style="font-size:18px;font-weight:700;margin:0;color:var(--text-primary);">
              <span class="pulse-dot" style="background:#10b981;width:8px;height:8px;display:inline-block;margin-right:6px;"></span>
              Active Broadcasts (<?= count($activeList) ?>)
            </h2>
          </div>
          <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">These notices are actively posted by campus administration</p>

          <div class="ann-grid">
            <?php foreach ($activeList as $a):
              $urgency = $a['urgency'] ?? 'info';
              $icon = 'bi-megaphone-fill';
              $badgeClass = 'badge-blue';
              if ($urgency === 'critical') {
                  $icon = 'bi-exclamation-octagon-fill';
                  $badgeClass = 'badge-rose';
              } elseif ($urgency === 'warning') {
                  $icon = 'bi-exclamation-triangle-fill';
                  $badgeClass = 'badge-amber';
              }
            ?>
              <article class="ann-card is-active urgency-<?= $urgency ?> spotlight-card">
                <div>
                  <div class="ann-head">
                    <div style="display:flex;align-items:center;gap:6px;">
                      <span class="badge <?= $badgeClass ?>" style="display:inline-flex;align-items:center;gap:4px;">
                        <i class="bi <?= $icon ?>"></i> <?= ucfirst($urgency) ?>
                      </span>
                      <span class="badge badge-emerald" style="font-size:11px;">Active</span>
                    </div>
                    <?php if (!empty($a['expires_at'])): ?>
                      <?php 
                        $diff = strtotime($a['expires_at']) - time();
                        $hours = round($diff / 3600);
                        if ($hours <= 1) {
                            $mins = max(1, round($diff / 60));
                            $expText = "{$mins} mins left";
                        } elseif ($hours < 24) {
                            $expText = "{$hours} hrs left";
                        } else {
                            $days = ceil($hours / 24);
                            $expText = "{$days} days left";
                        }
                      ?>
                      <span style="font-size:11px;color:var(--emerald);font-weight:600;display:inline-flex;align-items:center;gap:3px;" title="Expires: <?= date('M d, Y h:i A', strtotime($a['expires_at'])) ?>">
                        <i class="bi bi-hourglass-split"></i> <?= $expText ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <h3 class="ann-title"><?= htmlspecialchars($a['title']) ?></h3>
                  <div class="ann-body"><?= nl2br(htmlspecialchars($a['message'])) ?></div>
                </div>

                <div class="ann-meta">
                  <span><i class="bi bi-clock me-1"></i>Posted <?= date('M d, Y', strtotime($a['created_at'])) ?></span>
                  <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($a['creator_name'] ?? 'Admin') ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Past / Previous Broadcasts -->
      <div style="margin-top:20px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
          <h2 style="font-size:18px;font-weight:700;margin:0;color:var(--text-primary);">
            <i class="bi bi-clock-history me-1" style="color:var(--text-muted);"></i>
            Past Notices (<?= count($pastList) ?>)
          </h2>
        </div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">Historical notices and expired broadcasts for your reference</p>

        <?php if (empty($pastList) && empty($activeList)): ?>
          <div class="panel" style="text-align:center;padding:50px 20px;">
            <i class="bi bi-megaphone" style="font-size:3rem;color:var(--text-muted);opacity:0.3;display:block;margin-bottom:12px;"></i>
            <h3 style="font-size:16px;font-weight:600;margin-bottom:6px;">No announcements found</h3>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
              <?= (!empty($searchQuery) || !empty($urgencyFilter)) ? 'Try clearing your search or urgency filter.' : 'There are currently no broadcast announcements published.' ?>
            </p>
            <?php if (!empty($searchQuery) || !empty($urgencyFilter)): ?>
              <a href="announcements.php" class="btn btn-secondary">Clear Filters</a>
            <?php endif; ?>
          </div>
        <?php elseif (empty($pastList)): ?>
          <div class="panel" style="text-align:center;padding:30px 20px;color:var(--text-muted);font-size:13px;">
            All current announcements are actively broadcasting above.
          </div>
        <?php else: ?>
          <div class="ann-grid">
            <?php foreach ($pastList as $a):
              $urgency = $a['urgency'] ?? 'info';
              $isExpired = (!empty($a['expires_at']) && strtotime($a['expires_at']) <= $now);
            ?>
              <article class="ann-card is-expired">
                <div>
                  <div class="ann-head">
                    <span class="badge badge-neutral" style="text-transform:capitalize;">
                      <?= htmlspecialchars($urgency) ?>
                    </span>
                    <span class="badge badge-neutral" style="font-size:11px;">
                      <?= $isExpired ? 'Expired' : 'Archived' ?>
                    </span>
                  </div>

                  <h3 class="ann-title" style="color:var(--text-secondary);"><?= htmlspecialchars($a['title']) ?></h3>
                  <div class="ann-body" style="font-size:13px;"><?= nl2br(htmlspecialchars($a['message'])) ?></div>
                </div>

                <div class="ann-meta">
                  <span><i class="bi bi-calendar-event me-1"></i><?= date('M d, Y', strtotime($a['created_at'])) ?></span>
                  <?php if (!empty($a['expires_at'])): ?>
                    <span title="Ended on <?= date('M d, Y', strtotime($a['expires_at'])) ?>"><i class="bi bi-calendar-check me-1"></i>Ended <?= date('M d', strtotime($a['expires_at'])) ?></span>
                  <?php else: ?>
                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($a['creator_name'] ?? 'Admin') ?></span>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </main>
  </div>
</div>
<script src="../assets/js/animations.js"></script>
<script src="../assets/js/push_notifications.js"></script>
</body>
</html>
