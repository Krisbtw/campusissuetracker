<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
require_once '../includes/announcement_helper.php';
requireRole('admin');

$u = currentUser();

foreach (['pending','in_progress','resolved','closed','rejected'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM issues WHERE status=? AND parent_id IS NULL");
    $st->execute([$s]);
    $stats[$s] = $st->fetchColumn();
}

$stats['total'] = $pdo->query("SELECT COUNT(*) FROM issues WHERE parent_id IS NULL")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','staff')")->fetchColumn();
$criticalOpen = $pdo->query("SELECT COUNT(*) FROM issues WHERE priority='critical' AND status NOT IN ('resolved','closed') AND parent_id IS NULL")->fetchColumn();
$pendingStaffCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND status='pending'")->fetchColumn();

$recent = $pdo->query("SELECT i.*,c.category_name,u.full_name AS reporter FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users u ON i.reported_by=u.user_id WHERE i.parent_id IS NULL ORDER BY i.created_at DESC LIMIT 7")->fetchAll();

$catStats = $pdo->query("SELECT c.category_name, COUNT(i.issue_id) as cnt FROM categories c LEFT JOIN issues i ON i.category_id=c.category_id AND i.parent_id IS NULL GROUP BY c.category_id, c.category_name ORDER BY cnt DESC LIMIT 6")->fetchAll();

$hotspotStats = $pdo->query("SELECT location, COUNT(*) as cnt FROM issues WHERE status NOT IN ('resolved','closed','rejected') AND parent_id IS NULL GROUP BY location ORDER BY cnt DESC LIMIT 5")->fetchAll();
$totalHotspotCnt = array_sum(array_column($hotspotStats, 'cnt')) ?: 1;

// Prepare 6-month timeline structure
$months = [];
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m_key = date('Y-m', strtotime("-$i months"));
    $m_label = date('M Y', strtotime("-$i months"));
    $months[$m_key] = $m_label;
    $chart_data[$m_key] = 0;
}

// Aggregate counts from database
$isPgsql = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$dateFmt = $isPgsql ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')";
$stmt = $pdo->query("
    SELECT {$dateFmt} AS ym, COUNT(*) AS count
    FROM issues
    GROUP BY {$dateFmt}
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($chart_data[$row['ym']])) {
        $chart_data[$row['ym']] = (int)$row['count'];
    }
}

// Encode labels and data values for the Chart rendering component
$chart_labels_json = json_encode(array_values($months));
$chart_data_json = json_encode(array_values($chart_data));

$pageTitle = 'Dashboard';
$pageSubtitle = 'System overview';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Dashboard – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/animations.css">
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

      <?= renderActiveAnnouncement($pdo) ?>

      <?php if (!empty($pendingStaffCount) && $pendingStaffCount > 0): ?>
        <div class="alert-banner alert-warning fade-in-up" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;background:rgba(217,119,6,0.12);border:1px solid var(--amber);color:var(--text);">
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="pulse-dot pulse-dot-amber"></span>
            <i class="bi bi-shield-exclamation" style="font-size:1.3rem;color:var(--amber);"></i>
            <span><strong><?= $pendingStaffCount ?> staff applicant<?= $pendingStaffCount > 1 ? 's are' : ' is' ?> awaiting verification.</strong> Review and approve or reject to grant access.</span>
          </div>
          <a href="users.php?status=pending" class="btn btn-secondary btn-sm" style="font-weight:600;white-space:nowrap;">Review applicants →</a>
        </div>
      <?php endif; ?>

      <div class="stat-grid">
        <div class="stat-card spotlight-card fade-in-up stagger-1"><div class="stat-value"><?=$stats['total']?></div><div class="stat-label">Total issues</div></div>
        <div class="stat-card spotlight-card fade-in-up stagger-2"><div class="stat-value"><?=$stats['pending']?></div><div class="stat-label"><span class="pulse-dot pulse-dot-amber" style="margin-right:5px;"></span>Pending</div></div>
        <div class="stat-card spotlight-card fade-in-up stagger-3"><div class="stat-value"><?=$stats['in_progress']?></div><div class="stat-label"><span class="pulse-dot pulse-dot-amber" style="margin-right:5px;"></span>In progress</div></div>
        <div class="stat-card spotlight-card fade-in-up stagger-4"><div class="stat-value"><?=$stats['resolved']?></div><div class="stat-label"><span class="pulse-dot pulse-dot-emerald" style="margin-right:5px;"></span>Resolved</div></div>
        <div class="stat-card spotlight-card fade-in-up stagger-5"><div class="stat-value"><?=$criticalOpen?></div><div class="stat-label"><span class="pulse-dot pulse-dot-rose" style="margin-right:5px;"></span>Critical open</div></div>
        <div class="stat-card spotlight-card fade-in-up stagger-6"><div class="stat-value"><?=$totalUsers?></div><div class="stat-label">Registered users</div></div>
      </div>

      <div class="content-grid">
        <div style="display:flex;flex-direction:column;gap:14px;">
          <!-- Recent Issues Table -->
          <div class="panel spotlight-card fade-in-up stagger-4">
            <div class="panel-header"><span>Recent issues</span><a href="issues.php">View all</a></div>
            <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Title</th>
                  <th scope="col">Reporter</th>
                  <th scope="col">Category</th>
                  <th scope="col">Priority</th>
                  <th scope="col">Status</th>
                  <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($recent as $r): ?>
              <tr>
                <td><span class="issue-id">#<?=$r['issue_id']?></span></td>
                <td class="issue-title">
                  <?=htmlspecialchars($r['title'])?>
                  <?php if(!empty($r['affected_count']) && $r['affected_count'] > 1): ?>
                    <span class="badge badge-amber"><?=$r['affected_count']?> affected</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted"><?=htmlspecialchars($r['reporter'] ?? 'Unknown')?></td>
                <td class="text-muted"><?=htmlspecialchars($r['category_name'] ?? '—')?></td>
                <td><?=getPriorityBadge($r['priority'])?></td>
                <td><?=getStatusBadge($r['status'])?></td>
                <td><a href="view_issue.php?id=<?=$r['issue_id']?>" class="btn-sm-icon" aria-label="View issue"><i class="bi bi-eye"></i></a></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table></div>
          </div>

          <!-- Monthly Trend Chart Card -->
          <div class="panel">
            <div class="panel-header">Monthly trend (Mar 2026 – Aug 2026)</div>
            <div class="panel-body">
              <div style="position:relative;height:220px;width:100%;">
                <canvas id="dashboardMonthlyChart"></canvas>
              </div>
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px;">
          <!-- By Category Card -->
          <div class="panel">
            <div class="panel-header">By category</div>
            <div class="panel-body">
              <?php
              $cntArr = array_column($catStats, 'cnt');
              $maxCat = (!empty($cntArr) && max($cntArr) > 0) ? max($cntArr) : 1;
              foreach($catStats as $cs):
                $pct = round(($cs['cnt'] / $maxCat) * 100);
              ?>
              <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                  <span class="muted-note" style="font-weight:500;color:var(--text);"><?=htmlspecialchars($cs['category_name'])?></span>
                  <span class="muted-note"><?=$cs['cnt']?></span>
                </div>
                <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?=$pct?>%"></div></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Location Hotspot Analytics Card -->
          <div class="panel">
            <div class="panel-header">Top issue hotspots</div>
            <div class="panel-body">
              <?php if(empty($hotspotStats)): ?>
                <div class="muted-note">No active hotspots found.</div>
              <?php else: ?>
                <?php foreach($hotspotStats as $hs):
                  $hsPct = round(($hs['cnt'] / $totalHotspotCnt) * 100);
                ?>
                <div style="margin-bottom:12px;">
                  <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span class="muted-note" style="font-weight:500;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;"><?=htmlspecialchars(ucwords($hs['location']))?></span>
                    <span class="muted-note"><?=$hs['cnt']?> issues (<?=$hsPct?>%)</span>
                  </div>
                  <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?=$hsPct?>%;"></div></div>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- Quick Actions Card -->
          <div class="panel">
            <div class="panel-header">Quick actions</div>
            <div class="panel-body">
              <a href="issues.php?status=pending" class="quick-action"><i class="bi bi-clock"></i>Review pending</a>
              <a href="issues.php?priority=critical" class="quick-action"><i class="bi bi-exclamation-triangle"></i>Critical issues</a>
              <a href="users.php" class="quick-action"><i class="bi bi-people"></i>Manage users</a>
              <a href="reports.php" class="quick-action"><i class="bi bi-bar-chart-line"></i>View reports</a>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const ctx = document.getElementById('dashboardMonthlyChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= $chart_labels_json ?>,
        datasets: [{
          label: 'Issues reported',
          data: <?= $chart_data_json ?>,
          backgroundColor: '#4A0E17',
          borderWidth: 0,
          borderRadius: 4,
          hoverBackgroundColor: '#7B1E2B'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#4A0E17',
            titleColor: '#FFD8BE',
            bodyColor: '#FFFFFF',
            padding: 10,
            cornerRadius: 6,
            titleFont: { family: 'Inter, sans-serif', size: 13 },
            bodyFont: { family: 'Inter, sans-serif', size: 13 }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              color: '#5C5148',
              font: { family: 'Inter, sans-serif', size: 12 }
            },
            grid: { color: '#EAE0D3' }
          },
          x: {
            ticks: {
              color: '#5C5148',
              font: { family: 'Inter, sans-serif', size: 12 }
            },
            grid: { display: false }
          }
        }
      }
    });
  }
});
</script>
</body>
</html>
