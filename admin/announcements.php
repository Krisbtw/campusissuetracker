<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u = currentUser();

$msg = $err = '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->execute([$delId]);
        $msg = 'Announcement deleted successfully.';
    } catch (Exception $e) {
        $err = 'Failed to delete announcement: ' . $e->getMessage();
    }
}

// Handle Toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle']) && isset($_GET['status'])) {
    $toggleId = intval($_GET['toggle']);
    $newStatus = intval($_GET['status']) ? 1 : 0;
    try {
        $stmt = $pdo->prepare("UPDATE announcements SET is_active = ? WHERE announcement_id = ?");
        $stmt->execute([$newStatus, $toggleId]);
        $msg = 'Announcement status updated.';
    } catch (Exception $e) {
        $err = 'Failed to update status: ' . $e->getMessage();
    }
}

// Handle New Announcement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $urgency = $_POST['urgency'] ?? 'info';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $duration = $_POST['duration'] ?? 'never';
    $expiresAt = null;

    if ($duration === '12h') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+12 hours'));
    } elseif ($duration === '24h') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    } elseif ($duration === '2d') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+2 days'));
    } elseif ($duration === '3d') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));
    } elseif ($duration === '7d') {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    } elseif ($duration === 'custom' && !empty($_POST['custom_expires_at'])) {
        $parsedTime = strtotime($_POST['custom_expires_at']);
        if ($parsedTime && $parsedTime > time()) {
            $expiresAt = date('Y-m-d H:i:s', $parsedTime);
        } else {
            $err = 'Custom expiration date and time must be in the future.';
        }
    }

    if (empty($err)) {
        if (empty($title) || empty($message)) {
            $err = 'Please fill in both announcement title and message.';
        } elseif (!in_array($urgency, ['info', 'warning', 'critical'])) {
            $err = 'Invalid urgency level selected.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO announcements (title, message, urgency, is_active, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $message, $urgency, $isActive, $expiresAt, $u['id']]);
                $msg = 'Campus broadcast announcement published successfully!';
            } catch (Exception $e) {
                $err = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all announcements
try {
    $announcements = $pdo->query("
        SELECT a.*, u.full_name as creator_name
        FROM announcements a
        LEFT JOIN users u ON a.created_by = u.user_id
        ORDER BY a.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $announcements = [];
}

$pageTitle = 'Broadcast Announcements';
$pageSubtitle = 'Send campus-wide alerts and maintenance notices';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Campus Announcements – FixMyCampus Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/animations.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title"><i class="bi bi-megaphone-fill me-2" style="color:var(--burg);"></i>Broadcast Announcements</h1>
        <p class="page-sub">Publish notices, water/power shutdowns, and urgent facility alerts directly to student and staff dashboards</p>
      </div>

      <?php if($msg): ?><div class="alert-banner alert-success" role="status"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <div class="content-grid report-grid" style="grid-template-columns: 360px 1fr; gap: 20px; align-items: start;">
        <!-- New Announcement Form -->
        <div class="panel spotlight-card" style="box-shadow: 0 10px 30px -4px rgba(0, 0, 0, 0.08), 0 4px 12px -2px rgba(0, 0, 0, 0.04);">
          <div class="panel-header" style="display:flex;align-items:center;gap:8px;">
            <i class="bi bi-plus-circle-fill" style="color:var(--burg);"></i>
            <span>Create New Announcement</span>
          </div>
          <div class="panel-body">
            <form method="POST" action="announcements.php">
              <div class="field-group">
                <label class="form-label" for="title">Announcement Title *</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Scheduled Power Outage - Block B" required maxlength="150" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
              </div>

              <div class="field-group">
                <label class="form-label" for="urgency">Urgency Level *</label>
                <select id="urgency" name="urgency" class="form-control" required>
                  <option value="info" <?= (($_POST['urgency'] ?? '') === 'info') ? 'selected' : '' ?>> Info — General update or reminder</option>
                  <option value="warning" <?= (($_POST['urgency'] ?? 'warning') === 'warning') ? 'selected' : '' ?>> Warning — Service interruption / maintenance</option>
                  <option value="critical" <?= (($_POST['urgency'] ?? '') === 'critical') ? 'selected' : '' ?>> Critical — Urgent safety notice / closure</option>
                </select>
              </div>

              <div class="field-group">
                <label class="form-label" for="duration">Broadcast Duration / Expiry *</label>
                <select id="duration" name="duration" class="form-control" onchange="toggleCustomExpiry(this.value)" required>
                  <option value="never" selected>No expiration (Until manually deactivated)</option>
                  <option value="12h">12 Hours</option>
                  <option value="24h">24 Hours (1 Day)</option>
                  <option value="2d">2 Days</option>
                  <option value="3d">3 Days</option>
                  <option value="7d">7 Days (1 Week)</option>
                  <option value="custom">Custom end date &amp; time...</option>
                </select>
              </div>

              <div class="field-group" id="customExpiryGroup" style="display:none;">
                <label class="form-label" for="custom_expires_at">Custom Expiration Date &amp; Time *</label>
                <input type="datetime-local" id="custom_expires_at" name="custom_expires_at" class="form-control" min="<?= date('Y-m-d\TH:i') ?>">
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Notice will automatically expire and disappear from dashboards at this time.</div>
              </div>

              <div class="field-group">
                <label class="form-label" for="message">Broadcast Message *</label>
                <textarea id="message" name="message" class="form-control" rows="4" placeholder="Detail the outage, affected buildings, expected restoration time..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
              </div>

              <div class="field-group" style="margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:10px;">
                  <input type="checkbox" id="is_active" name="is_active" value="1" checked style="width:18px;height:18px;accent-color:var(--burg);cursor:pointer;">
                  <label for="is_active" style="cursor:pointer;font-size:14px;color:var(--text-primary);font-weight:500;">
                    Broadcast immediately (Active banner)
                  </label>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;margin-left:28px;">Active broadcasts are visible to students and staff across the campus portal.</div>
              </div>

              <button type="submit" class="btn btn-primary w-full" style="justify-content:center;gap:8px;">
                <i class="bi bi-send-fill"></i>
                <span>Publish Broadcast</span>
              </button>
            </form>
          </div>
        </div>

        <!-- Announcements List -->
        <div class="panel">
          <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:8px;">
              <i class="bi bi-collection-fill" style="color:var(--text-muted);"></i>
              <?php 
                $now = time();
                $activeCount = count(array_filter($announcements, function($a) use ($now) { 
                    $expired = !empty($a['expires_at']) && strtotime($a['expires_at']) <= $now;
                    return !empty($a['is_active']) && !$expired; 
                })); 
              ?>
              <span>Broadcasts (<strong style="color:var(--emerald);"><?= $activeCount ?> Active</strong>, <?= count($announcements) ?> Total)</span>
            </div>
          </div>
          <div class="panel-body">
            <?php if (empty($announcements)): ?>
              <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
                <i class="bi bi-megaphone" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:10px;"></i>
                <p>No broadcast announcements yet. Create one using the form on the left!</p>
              </div>
            <?php else: ?>
              <div class="table-scroll">
                <table class="table-dark-custom">
                  <thead>
                    <tr>
                      <th>Status</th>
                      <th>Urgency</th>
                      <th>Announcement</th>
                      <th>Duration / Expiry</th>
                      <th>Posted</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($announcements as $a): 
                      $isExpired = (!empty($a['expires_at']) && strtotime($a['expires_at']) <= time());
                    ?>
                      <tr>
                        <td>
                          <?php if ($isExpired): ?>
                            <span class="badge badge-neutral" style="display:inline-flex;align-items:center;gap:4px;" title="Automatically expired">
                              <i class="bi bi-clock-history"></i> Expired
                            </span>
                          <?php elseif ($a['is_active']): ?>
                            <span class="badge badge-emerald" style="display:inline-flex;align-items:center;gap:4px;">
                              <span class="pulse-dot" style="background:#10b981;width:6px;height:6px;"></span> Active
                            </span>
                          <?php else: ?>
                            <span class="badge badge-neutral">Inactive</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php
                            $uBadge = 'badge-blue';
                            if ($a['urgency'] === 'warning') $uBadge = 'badge-amber';
                            if ($a['urgency'] === 'critical') $uBadge = 'badge-rose';
                          ?>
                          <span class="badge <?= $uBadge ?>"><?= ucfirst($a['urgency']) ?></span>
                        </td>
                        <td style="max-width:300px;">
                          <div style="font-weight:600;color:var(--text-primary);margin-bottom:2px;"><?= htmlspecialchars($a['title']) ?></div>
                          <div class="muted-note" style="font-size:12px;line-height:1.4;"><?= nl2br(htmlspecialchars($a['message'])) ?></div>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                          <?php if (!empty($a['expires_at'])): ?>
                            <?php if ($isExpired): ?>
                              <span style="color:var(--rose);font-weight:500;"><i class="bi bi-clock-history me-1"></i>Expired on:</span><br>
                              <?= date('M d, Y g:i A', strtotime($a['expires_at'])) ?>
                            <?php else: ?>
                              <span style="color:var(--emerald);font-weight:600;"><i class="bi bi-hourglass-split me-1"></i>Expires:</span><br>
                              <?= date('M d, Y g:i A', strtotime($a['expires_at'])) ?>
                              <?php 
                                $diffHours = round((strtotime($a['expires_at']) - time()) / 3600);
                                if ($diffHours <= 1) {
                                    $diffMins = max(1, round((strtotime($a['expires_at']) - time()) / 60));
                                    echo "<br><span style='font-size:11px;color:var(--amber);'>({$diffMins} mins left)</span>";
                                } elseif ($diffHours < 24) {
                                    echo "<br><span style='font-size:11px;color:var(--emerald);'>({$diffHours} hrs left)</span>";
                                } else {
                                    $days = ceil($diffHours / 24);
                                    echo "<br><span style='font-size:11px;color:var(--emerald);'>({$days} days left)</span>";
                                }
                              ?>
                            <?php endif; ?>
                          <?php else: ?>
                            <span style="color:var(--text-muted);">No expiration<br><span style="font-size:11px;opacity:0.7;">(Manual deactivate)</span></span>
                          <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;">
                          <?= date('M d, Y', strtotime($a['created_at'])) ?><br>
                          <span style="font-size:11px;opacity:0.7;">by <?= htmlspecialchars($a['creator_name'] ?? 'Admin') ?></span>
                        </td>
                        <td style="text-align:right;white-space:nowrap;">
                          <?php $aid = $a['announcement_id'] ?? $a['id'] ?? 0; ?>
                          <?php if ($a['is_active'] && !$isExpired): ?>
                            <a href="announcements.php?toggle=<?= $aid ?>&status=0" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" title="Hide from dashboards">
                              <i class="bi bi-eye-slash me-1"></i>Deactivate
                            </a>
                          <?php else: ?>
                            <a href="announcements.php?toggle=<?= $aid ?>&status=1" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;" title="Show on dashboards">
                              <i class="bi bi-eye me-1"></i>Activate
                            </a>
                          <?php endif; ?>
                          <a href="announcements.php?delete=<?= $aid ?>" class="btn btn-secondary" style="padding:4px 8px;font-size:12px;color:var(--rose);margin-left:4px;" onclick="return confirm('Are you sure you want to permanently delete this broadcast?');" title="Delete">
                            <i class="bi bi-trash3"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
function toggleCustomExpiry(val) {
  const group = document.getElementById('customExpiryGroup');
  const input = document.getElementById('custom_expires_at');
  if (val === 'custom') {
    group.style.display = 'block';
    input.required = true;
    input.focus();
  } else {
    group.style.display = 'none';
    input.required = false;
  }
}
</script>
</body>
</html>
