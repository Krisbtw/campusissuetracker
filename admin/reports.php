<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u = currentUser();

// === Stats ===
$totalIssues    = $pdo->query("SELECT COUNT(*) FROM issues")->fetchColumn();
$resolvedIssues = $pdo->query("SELECT COUNT(*) FROM issues WHERE status IN ('resolved','closed')")->fetchColumn();
$pendingIssues  = $pdo->query("SELECT COUNT(*) FROM issues WHERE status='pending'")->fetchColumn();
$totalUsers     = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('student','staff')")->fetchColumn();
$resolutionRate = $totalIssues > 0 ? round(($resolvedIssues / $totalIssues) * 100) : 0;

// Issues by category
$catData = $pdo->query("SELECT c.category_name, COUNT(i.issue_id) AS cnt FROM categories c LEFT JOIN issues i ON i.category_id=c.category_id GROUP BY c.category_id ORDER BY cnt DESC")->fetchAll();

// Issues by status
$statusData = $pdo->query("SELECT status, COUNT(*) AS cnt FROM issues GROUP BY status")->fetchAll();

// Issues by priority
$priData = $pdo->query("SELECT priority, COUNT(*) AS cnt FROM issues GROUP BY priority ORDER BY CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")->fetchAll();

// Monthly issues (last 6 rolling months)
$isPgsql = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$dateFmt = $isPgsql ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')";
$sixMonthsAgo = date('Y-m-d 00:00:00', strtotime('-6 months'));
$stmt = $pdo->prepare("
    SELECT {$dateFmt} AS month_key, 
           COUNT(*) AS total 
    FROM issues 
    WHERE created_at >= ?
    GROUP BY {$dateFmt} 
    ORDER BY month_key ASC
");
$stmt->execute([$sixMonthsAgo]);
$db_monthly = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthlyLabels = [];
$monthlyCounts = [];
for ($i = 5; $i >= 0; $i--) {
    $time = strtotime("first day of -$i month");
    $key = date('Y-m', $time);
    $label = date('M Y', $time); // e.g. "Mar 2026"
    $monthlyLabels[] = $label;
    $monthlyCounts[] = isset($db_monthly[$key]) ? (int)$db_monthly[$key] : 0;
}

// Top reporters
$topReporters = $pdo->query("SELECT u.full_name, u.role, COUNT(i.issue_id) AS cnt FROM users u LEFT JOIN issues i ON i.reported_by=u.user_id GROUP BY u.user_id, u.full_name, u.role ORDER BY cnt DESC LIMIT 5")->fetchAll();

$pageTitle='Reports & Analytics'; $pageSubtitle='Issue statistics and system overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Reports – FixMyCampus Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">

      <div class="page-head">
        <h1 class="page-title"><?=htmlspecialchars($pageTitle)?></h1>
        <p class="page-sub"><?=htmlspecialchars($pageSubtitle)?></p>
      </div>

      <!-- Summary Stats -->
      <div class="summary-grid">
        <div class="stat-card"><div class="stat-value"><?= $totalIssues ?></div><div class="stat-label">Total issues</div></div>
        <div class="stat-card"><div class="stat-value"><?= $resolvedIssues ?></div><div class="stat-label">Resolved</div></div>
        <div class="stat-card"><div class="stat-value"><?= $pendingIssues ?></div><div class="stat-label">Pending</div></div>
        <div class="stat-card"><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Active users</div></div>
        <div class="stat-card"><div class="stat-value"><?= $resolutionRate ?>%</div><div class="stat-label">Resolution rate</div></div>
      </div>

      <div class="two-col-grid">

        <!-- Issues by Category -->
        <div class="panel">
          <div class="panel-header">Issues by category</div>
          <div class="panel-body">
            <?php $maxCat = max(array_column($catData,'cnt') ?: [1]);
            foreach($catData as $cd): $pct = $maxCat>0?round(($cd['cnt']/$maxCat)*100):0; ?>
            <div style="margin-bottom:14px;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px;">
                <span style="font-weight:600;"><?= htmlspecialchars($cd['category_name']) ?></span>
                <span class="muted-note"><?= $cd['cnt'] ?> issue<?= $cd['cnt']!=1?'s':'' ?></span>
              </div>
              <div class="mini-bar-track"><div class="mini-bar-fill" style="width:<?= $pct ?>%;"></div></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Issues by Status + Priority -->
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="panel">
            <div class="panel-header">By status</div>
            <div class="panel-body">
              <?php
              $statusColors2 = ['pending'=>'var(--status-pending)','in_progress'=>'var(--status-progress)','resolved'=>'var(--status-resolved)','closed'=>'var(--status-closed)','rejected'=>'var(--status-rejected)'];
              $maxStat = max(array_column($statusData,'cnt') ?: [1]);
              foreach($statusData as $sd): $pct=round(($sd['cnt']/$maxStat)*100); $col=$statusColors2[$sd['status']]??'var(--status-closed)'; ?>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div class="muted-note" style="width:80px;text-align:right;"><?= ucwords(str_replace('_',' ',$sd['status'])) ?></div>
                <div class="mini-bar-track" style="flex:1;"><div class="mini-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
                <div class="chart-count" style="width:24px;font-weight:600;color:<?= $col ?>;"><?= $sd['cnt'] ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="panel">
            <div class="panel-header">By priority</div>
            <div class="panel-body">
              <?php
              $priColors = ['low'=>'var(--status-closed)','medium'=>'var(--status-pending)','high'=>'var(--amber)','critical'=>'var(--rose)'];
              $maxPri = max(array_column($priData,'cnt') ?: [1]);
              foreach($priData as $pd): $pct=round(($pd['cnt']/$maxPri)*100); $col=$priColors[$pd['priority']]??'var(--status-closed)'; ?>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                <div class="muted-note" style="width:60px;text-align:right;"><?= ucfirst($pd['priority']) ?></div>
                <div class="mini-bar-track" style="flex:1;"><div class="mini-bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div></div>
                <div class="chart-count" style="width:24px;font-weight:600;color:<?= $col ?>;"><?= $pd['cnt'] ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Reporters + Monthly Trend -->
      <div class="two-col-grid">
        <div class="panel">
          <div class="panel-header">Top reporters</div>
          <div class="table-scroll" role="region" aria-label="Top reporters table" tabindex="0"><table class="table-dark-custom">
            <thead><tr><th scope="col">#</th><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Issues reported</th></tr></thead>
            <tbody>
            <?php foreach($topReporters as $idx=>$tr): ?>
            <tr>
              <td class="text-muted"><?= $idx+1 ?></td>
              <td class="issue-title"><?= htmlspecialchars($tr['full_name']) ?></td>
              <td class="text-muted" style="text-transform:capitalize;"><?= $tr['role'] ?></td>
              <td class="issue-id"><?= $tr['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
        <div class="panel">
          <div class="panel-header">Monthly trend (last 6 months)</div>
          <div class="panel-body">
            <div style="position:relative;height:210px;width:100%;">
              <canvas id="monthlyTrendChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
  const ctx = document.getElementById('monthlyTrendChart');
  if (ctx) {
    const monthLabels = <?= json_encode($monthlyLabels) ?>;
    const monthCounts = <?= json_encode($monthlyCounts) ?>;
    
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: monthLabels,
        datasets: [{
          label: 'Issues reported',
          data: monthCounts,
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
            grid: {
              display: false
            }
          }
        }
      }
    });
  }
});
</script>
</body>
</html>
