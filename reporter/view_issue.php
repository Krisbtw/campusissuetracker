<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole(['student','staff']);
$u = currentUser();

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*,c.category_name,rb.full_name AS reporter_name,rb.email AS reporter_email,
    ab.full_name AS assigned_name FROM issues i
    LEFT JOIN categories c ON i.category_id=c.category_id
    LEFT JOIN users rb ON i.reported_by=rb.user_id
    LEFT JOIN users ab ON i.assigned_to=ab.user_id
    WHERE i.issue_id=? AND i.reported_by=?");
$stmt->execute([$id, $u['id']]);
$issue = $stmt->fetch();

if (!$issue) {
    header('Location: my_issues.php?error=Issue not found');
    exit();
}

$images = $pdo->prepare("SELECT * FROM issue_images WHERE issue_id=?");
$images->execute([$id]);
$images = $images->fetchAll();

$history = $pdo->prepare("SELECT sh.*, u.full_name FROM status_history sh LEFT JOIN users u ON sh.changed_by=u.user_id WHERE sh.issue_id=? ORDER BY sh.changed_at ASC");
$history->execute([$id]);
$history = $history->fetchAll();

$statusColors = ['pending'=>'var(--status-pending)','in_progress'=>'var(--status-progress)','resolved'=>'var(--status-resolved)','closed'=>'var(--status-closed)','rejected'=>'var(--status-rejected)'];
$pageTitle = 'Issue #' . $id;
$pageSubtitle = htmlspecialchars($issue['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Issue #<?= $id ?> – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div style="margin-bottom:16px;">
        <a href="my_issues.php" class="muted-note"><i class="bi bi-arrow-left me-1"></i>Back to my issues</a>
      </div>

      <?php if(isset($_SESSION['flash_success'])): ?>
        <div class="alert-banner alert-success" role="status" style="margin-bottom:14px;"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); ?>
      <?php endif; ?>
      <?php if(isset($_SESSION['flash_error'])): ?>
        <div class="alert-banner alert-danger" role="alert" style="margin-bottom:14px;"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); ?>
      <?php endif; ?>

      <div class="detail-grid">

        <!-- Left: Issue Details -->
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-size:1.125rem;font-weight:600;"><?= htmlspecialchars($issue['title']) ?></span>
                <?php if(!empty($issue['is_parent']) || (!empty($issue['affected_count']) && $issue['affected_count']>1)): ?>
                  <span class="badge badge-neutral"><?= $issue['affected_count'] ?> affected · Incident #FM<?= $issue['issue_id'] ?></span>
                <?php elseif(!empty($issue['parent_id'])): ?>
                  <span class="badge badge-neutral">Merged → #FM<?= $issue['parent_id'] ?></span>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:8px;"><?= getPriorityBadge($issue['priority']) ?> <?= getStatusBadge($issue['status']) ?></div>
            </div>
            <div class="panel-body">
              <?php if(!empty($issue['parent_id'])): ?>
              <div class="meta-tile" style="margin-bottom:14px;">
                <span class="muted-note">Your report has been automatically linked to <b>Parent Incident #FM<?= $issue['parent_id'] ?></b>. Updates and status changes to the main incident will automatically sync to your report.</span>
              </div>
              <?php endif; ?>
              <div class="two-col-grid">
                <div class="meta-tile">
                  <div class="meta-label">Category</div>
                  <div class="meta-value"><?= htmlspecialchars($issue['category_name'] ?? 'N/A') ?></div>
                </div>
                <div class="meta-tile">
                  <div class="meta-label">Location</div>
                  <div class="meta-value"><?= htmlspecialchars($issue['location']) ?></div>
                </div>
                <div class="meta-tile">
                  <div class="meta-label">Reported on</div>
                  <div class="meta-value"><?= date('d M Y, h:i A', strtotime($issue['created_at'])) ?></div>
                </div>
                <div class="meta-tile">
                  <div class="meta-label">Assigned to</div>
                  <div class="meta-value"><?= $issue['assigned_name'] ? htmlspecialchars($issue['assigned_name']) : 'Not yet assigned' ?></div>
                </div>
              </div>
              <div style="margin-bottom:18px;">
                <div class="meta-label" style="margin-bottom:8px;">Description</div>
                <div style="line-height:1.75;"><?= nl2br(htmlspecialchars($issue['description'])) ?></div>
              </div>
              <?php if($issue['admin_remark']): ?>
              <div class="meta-tile">
                <div class="meta-label" style="margin-bottom:6px;">Admin remark</div>
                <div class="muted-note"><?= nl2br(htmlspecialchars($issue['admin_remark'])) ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Images -->
          <?php if(!empty($images)): ?>
          <div class="panel">
            <div class="panel-header">Uploaded images (<?= count($images) ?>)</div>
            <div class="panel-body">
              <div class="image-grid">
                <?php 
                $validImgCount = 0;
                foreach($images as $img):
                  $raw_path = trim($img['image_path'] ?? '');
                  if (empty($raw_path)) continue;
                  if (filter_var($raw_path, FILTER_VALIDATE_URL) || strpos($raw_path, 'http://') === 0 || strpos($raw_path, 'https://') === 0) {
                      $webPath = $raw_path;
                  } elseif (strpos($raw_path, '../') === 0) {
                      $webPath = $raw_path;
                  } elseif (strpos($raw_path, 'uploads/') === 0) {
                      $webPath = '../' . $raw_path;
                  } else {
                      $webPath = '../uploads/issues/' . ltrim($raw_path, '/');
                  }
                  $validImgCount++;
                ?>
                  <a href="<?= htmlspecialchars($webPath) ?>" target="_blank" class="evidence-link">
                    <img src="<?= htmlspecialchars($webPath) ?>" alt="Issue Evidence" class="evidence-image" onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Image+Not+Found';" />
                  </a>
                <?php 
                endforeach;
                if ($validImgCount === 0):
                ?>
                  <p class="muted-note">No image preview available</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Status Timeline -->
          <div class="panel">
            <div class="panel-header">Status history</div>
            <div class="panel-body">
              <?php if(empty($history)): ?>
                <p class="muted-note">No history yet.</p>
              <?php else: ?>
              <div class="timeline">
                <?php foreach($history as $h):
                  $col = $statusColors[$h['new_status']] ?? 'var(--status-closed)';
                ?>
                <div class="timeline-item">
                  <div class="timeline-dot" style="background:<?= $col ?>;"></div>
                  <div class="timeline-content">
                    <div class="t-title">
                      <?php if($h['old_status']): ?>
                        <?= getStatusBadge($h['old_status']) ?> <i class="bi bi-arrow-right mx-1" style="font-size:.875rem;"></i>
                      <?php endif; ?>
                      <?= getStatusBadge($h['new_status']) ?>
                    </div>
                    <div class="t-meta">
                      By <b><?= htmlspecialchars($h['full_name']) ?></b> &bull; <?= date('d M Y, h:i A', strtotime($h['changed_at'])) ?>
                    </div>
                    <?php if($h['remarks']): ?>
                      <div class="t-remark">"<?= htmlspecialchars($h['remarks']) ?>"</div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Right Sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="panel">
            <div class="panel-header">Issue status</div>
            <div class="panel-body">
              <div style="text-align:center;padding:4px 0;">
                <?php 
                $st = $issue['status'] ?? 'pending';
                $statusIconMap = [
                  'pending'     => 'bi-hourglass-split',
                  'in_progress' => 'bi-wrench',
                  'resolved'    => 'bi-check-circle-fill',
                  'closed'      => 'bi-lock-fill',
                  'rejected'    => 'bi-x-circle-fill'
                ];
                $iconClass = $statusIconMap[$st] ?? 'bi-info-circle-fill';
                ?>
                <div class="status-badge-circle status-<?= htmlspecialchars($st) ?>">
                  <i class="bi <?= $iconClass ?> status-badge-icon"></i>
                </div>
                <?= getStatusBadge($issue['status']) ?>
                <?php if(!empty($issue['reopen_count']) && $issue['reopen_count'] > 0): ?>
                  <div style="margin-top:8px;">
                    <span class="badge badge-rose">Re-opened (x<?= $issue['reopen_count'] ?>)</span>
                  </div>
                <?php endif; ?>
                <div class="muted-note" style="margin-top:12px;">Last updated: <?= timeAgo($issue['updated_at'] ?? $issue['created_at']) ?></div>

                <?php if($issue['status'] === 'resolved'): ?>
                  <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border);">
                    <button type="button" onclick="openReopenModal()" class="btn btn-secondary w-full">
                      <i class="bi bi-exclamation-triangle-fill"></i> Reopen issue
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="panel">
            <div class="panel-header">Reporter</div>
            <div class="panel-body">
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="user-avatar-sm"><?= strtoupper(substr($issue['reporter_name'],0,1)) ?></div>
                <div>
                  <div style="font-weight:600;"><?= htmlspecialchars($issue['reporter_name']) ?></div>
                  <div class="muted-note"><?= htmlspecialchars($issue['reporter_email']) ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- Re-Open Issue Modal -->
<div id="reopenModal" style="display:none;position:fixed;inset:0;background:rgba(43,13,13,.35);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div class="modal-card" role="dialog" aria-label="Reopen issue">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid var(--border);">
      <span style="font-weight:600;">Reopen issue #<?= $issue['issue_id'] ?></span>
      <button type="button" onclick="closeReopenModal()" class="btn-sm-icon" aria-label="Close reopen form">&times;</button>
    </div>
    <form action="reopen_issue.php" method="POST">
      <input type="hidden" name="issue_id" value="<?= $issue['issue_id'] ?>">
      <div class="field-group">
        <label class="form-label" for="reopenReason">Reason for reopening *</label>
        <textarea id="reopenReason" name="reason" rows="3" required placeholder="Describe why the problem is not resolved (e.g. 'Light is still flickering', 'AC is still leaking water')…" class="form-control"></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="closeReopenModal()" class="btn btn-secondary">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill"></i> Submit reopen request</button>
      </div>
    </form>
  </div>
</div>
<script>
function openReopenModal() { document.getElementById('reopenModal').style.display = 'flex'; }
function closeReopenModal() { document.getElementById('reopenModal').style.display = 'none'; }
</script>
</body>
</html>
