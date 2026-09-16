<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
require_once '../includes/stepper_helper.php';
requireRole('maintenance');
$u = currentUser();

$id = intval($_GET['id'] ?? 0);
$msg = $err = '';

$stmt = $pdo->prepare("SELECT i.*,c.category_name,rb.full_name AS reporter_name,rb.email AS reporter_email FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users rb ON i.reported_by=rb.user_id WHERE i.issue_id=? AND i.assigned_to=?");
$stmt->execute([$id,$u['id']]);
$issue = $stmt->fetch();
if (!$issue) { header('Location: my_assignments.php'); exit(); }

if (!empty($issue['parent_id'])) {
    // If this issue was merged into a parent incident, redirect staff to the parent incident
    $pCheck = $pdo->prepare("SELECT issue_id FROM issues WHERE issue_id = ? AND assigned_to = ?");
    $pCheck->execute([$issue['parent_id'], $u['id']]);
    if ($pCheck->fetch()) {
        header('Location: update_issue.php?id=' . $issue['parent_id']);
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['new_status'] ?? '';
    $remarks    = trim($_POST['remarks'] ?? '');
    $valid = ['in_progress','resolved'];
    if (!in_array($new_status,$valid)) { $err = 'Invalid status.'; }
    elseif (empty($remarks)) { $err = 'Please provide a remark.'; }
    else {
        $old_status = $issue['status'];

        // Handle Resolution Proof Photo Upload
        $resolution_img = null;
        if (!empty($_FILES['resolution_image']['tmp_name']) && $_FILES['resolution_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['resolution_image']['tmp_name'];
            $allowed = ['image/jpeg','image/png','image/jpg','image/webp'];
            $mime = mime_content_type($tmp);
            if (in_array($mime, $allowed)) {
                $cUrl = uploadToCloudinary($tmp);
                if ($cUrl) {
                    $resolution_img = $cUrl;
                } else {
                    if (!file_exists(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0777, true); }
                    $ext = pathinfo($_FILES['resolution_image']['name'], PATHINFO_EXTENSION);
                    $newName = 'resolved_' . $id . '_' . uniqid() . '.' . strtolower($ext);
                    if (move_uploaded_file($tmp, UPLOAD_DIR . $newName)) {
                        $resolution_img = $newName;
                    }
                }
            }
        }

        if ($resolution_img) {
            $pdo->prepare("UPDATE issues SET status=?, resolution_image=?, updated_at=NOW() WHERE issue_id=?")->execute([$new_status, $resolution_img, $id]);
        } else {
            $pdo->prepare("UPDATE issues SET status=?, updated_at=NOW() WHERE issue_id=?")->execute([$new_status, $id]);
        }

        logStatusChange($pdo,$id,$u['id'],$old_status,$new_status,$remarks);
        $type = $new_status==='resolved'?'success':'info';
        sendNotification($pdo,$issue['reported_by'],$id,"Your issue #{$id} ({$issue['title']}) status is now '".ucwords(str_replace('_',' ',$new_status))."'. Note: {$remarks}",$type);
        // Also notify admin
        $admins=$pdo->query("SELECT user_id FROM users WHERE role='admin'")->fetchAll();
        foreach($admins as $admin) { sendNotification($pdo,$admin['user_id'],$id,"Issue #{$id} marked as '{$new_status}' by {$u['name']}. Remark: {$remarks}",'info'); }
        
        // Mass propagate status update to clustered reports
        $rootParentId = !empty($issue['is_parent']) ? $id : (!empty($issue['parent_id']) ? $issue['parent_id'] : 0);
        if ($rootParentId > 0) {
            $cStmt = $pdo->prepare("SELECT issue_id, reported_by, status FROM issues WHERE (parent_id = ? OR issue_id = ?) AND issue_id != ?");
            $cStmt->execute([$rootParentId, $rootParentId, $id]);
            foreach ($cStmt->fetchAll() as $ch) {
                if ($resolution_img) {
                    $pdo->prepare("UPDATE issues SET status=?, resolution_image=?, updated_at=NOW() WHERE issue_id=?")->execute([$new_status, $resolution_img, $ch['issue_id']]);
                } else {
                    $pdo->prepare("UPDATE issues SET status=?, updated_at=NOW() WHERE issue_id=?")->execute([$new_status, $ch['issue_id']]);
                }
                logStatusChange($pdo, $ch['issue_id'], $u['id'], $ch['status'], $new_status, "Status sync from Parent Incident #{$rootParentId}. {$remarks}");
                sendNotification($pdo, $ch['reported_by'], $ch['issue_id'], "Your report #{$ch['issue_id']} (linked to Parent Incident #{$rootParentId}) status changed to '".ucwords(str_replace('_',' ',$new_status))."'. Note: {$remarks}", $type);
            }
        }

        $msg = 'Status updated to ' . ucwords(str_replace('_',' ',$new_status)) . ' and synced across incident cluster.';
        $stmt->execute([$id,$u['id']]); $issue=$stmt->fetch();
    }
}

$rootParentId = !empty($issue['is_parent']) ? $id : (!empty($issue['parent_id']) ? $issue['parent_id'] : 0);

// Load images for this issue and any clustered child reports
if ($rootParentId > 0) {
    $imgStmt = $pdo->prepare("SELECT im.*, i.issue_id FROM issue_images im JOIN issues i ON im.issue_id = i.issue_id WHERE im.issue_id = ? OR im.issue_id IN (SELECT issue_id FROM issues WHERE parent_id = ?) ORDER BY (CASE WHEN im.issue_id = ? THEN 1 ELSE 0 END) DESC, im.image_id ASC");
    $imgStmt->execute([$rootParentId, $rootParentId, $rootParentId]);
    $images = $imgStmt->fetchAll();
} else {
    $imgStmt = $pdo->prepare("SELECT *, issue_id FROM issue_images WHERE issue_id=?");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

$history = $pdo->prepare("SELECT sh.*,u.full_name FROM status_history sh LEFT JOIN users u ON sh.changed_by=u.user_id WHERE sh.issue_id=? ORDER BY sh.changed_at ASC"); $history->execute([$id]); $history=$history->fetchAll();
$statusColors=['pending'=>'var(--status-pending)','in_progress'=>'var(--status-progress)','resolved'=>'var(--status-resolved)','closed'=>'var(--status-closed)','rejected'=>'var(--status-rejected)'];

// Roster Query for Duplicate Incident Cluster
$roster = [];
if ($rootParentId > 0) {
    $rStmt = $pdo->prepare("SELECT i.issue_id, i.created_at, i.status, i.reopen_count, i.parent_id, i.is_parent, u.full_name, u.email, u.phone, u.department FROM issues i LEFT JOIN users u ON i.reported_by = u.user_id WHERE i.issue_id = ? OR i.parent_id = ? ORDER BY (CASE WHEN i.issue_id = ? THEN 1 ELSE 0 END) DESC, i.created_at ASC");
    $rStmt->execute([$rootParentId, $rootParentId, $rootParentId]);
    $roster = $rStmt->fetchAll();
}

$pageTitle='Update Issue #'.$id; $pageSubtitle=htmlspecialchars($issue['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Update Issue – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/animations.css"></head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div style="margin-bottom:14px;"><a href="my_assignments.php" class="muted-note"><i class="bi bi-arrow-left me-1"></i>Back to assignments</a></div>
      <?php if($msg): ?><div class="alert-banner alert-success" role="status"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <!-- Interactive Progress Stepper -->
      <?= renderIssueStepper($issue, $history) ?>

      <div class="detail-grid">
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-weight:600;"><?= htmlspecialchars($issue['title']) ?></span>
                <?php if(!empty($issue['is_parent']) || (!empty($issue['affected_count']) && $issue['affected_count']>1)): ?>
                  <span class="badge badge-amber" style="margin-left:6px;"><i class="bi bi-collection me-1"></i>Cluster (<?= $issue['affected_count'] ?> reports) · Incident #FM<?= $issue['issue_id'] ?></span>
                <?php elseif(!empty($issue['parent_id'])): ?>
                  <span class="badge badge-blue" style="margin-left:6px;">Merged → #FM<?= $issue['parent_id'] ?></span>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:8px;"><?= getPriorityBadge($issue['priority']) ?><?= getStatusBadge($issue['status']) ?></div>
            </div>
            <div class="panel-body">
              <div class="two-col-grid">
                <div class="meta-tile"><div class="meta-label">Category</div><div class="meta-value"><?= htmlspecialchars($issue['category_name']??'N/A') ?></div></div>
                <div class="meta-tile"><div class="meta-label">Location</div><div class="meta-value"><?= htmlspecialchars($issue['location']) ?></div></div>
              </div>
              <div style="margin-bottom:16px;"><div class="meta-label" style="margin-bottom:8px;">Description</div><div style="line-height:1.75;"><?= nl2br(htmlspecialchars($issue['description'])) ?></div></div>
              <?php if($issue['admin_remark']): ?><div class="meta-tile"><div class="meta-label" style="margin-bottom:5px;">Admin instruction</div><div class="muted-note"><?= nl2br(htmlspecialchars($issue['admin_remark'])) ?></div></div><?php endif; ?>
            </div>
          </div>

          <!-- Affected Reporter Roster Panel -->
          <?php if (!empty($roster) && count($roster) > 1): ?>
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
              <span><i class="bi bi-people me-1"></i>Affected reporters (<?= count($roster) ?>)</span>
              <span class="badge badge-amber">Incident #FM<?= $rootParentId ?></span>
            </div>
            <div class="panel-body">
              <p class="muted-note" style="margin-bottom:12px;">The following student reporters submitted duplicate reports merged into this incident:</p>
              <div class="table-scroll" role="region" aria-label="Reporter roster table" tabindex="0"><table class="table-dark-custom">
                <thead>
                  <tr>
                    <th scope="col">Reporter</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Ticket #</th>
                    <th scope="col">Status</th>
                    <th scope="col">Reported</th>
                    <th scope="col">Role in cluster</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($roster as $rep): ?>
                  <tr>
                    <td class="issue-title"><?= htmlspecialchars($rep['full_name'] ?? 'Unknown') ?><div class="muted-note"><?= htmlspecialchars($rep['department'] ?? 'Student') ?></div></td>
                    <td class="text-muted"><?= htmlspecialchars($rep['email'] ?? '—') ?><?php if(!empty($rep['phone'])): ?><br><?= htmlspecialchars($rep['phone']) ?><?php endif; ?></td>
                    <td><span class="issue-id">#<?= $rep['issue_id'] ?></span></td>
                    <td>
                      <?= getStatusBadge($rep['status']) ?>
                      <?php if(!empty($rep['reopen_count']) && $rep['reopen_count'] > 0): ?>
                        <span class="badge badge-amber" style="font-size:10px;margin-left:4px;" title="Re-opened by reporter"><i class="bi bi-arrow-counterclockwise"></i> Reopened</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= date('d M Y, h:i A', strtotime($rep['created_at'])) ?></td>
                    <td>
                      <?php if ($rep['issue_id'] == $rootParentId): ?>
                        <span class="badge badge-amber">Primary incident</span>
                      <?php else: ?>
                        <span class="badge badge-blue">Merged duplicate</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table></div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Images -->
          <?php if(!empty($images)): ?>
          <div class="panel">
            <div class="panel-header">Images (<?= count($images) ?>)</div>
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
                  <div style="position:relative;display:inline-block;">
                    <a href="<?= htmlspecialchars($webPath) ?>" target="_blank" class="evidence-link">
                      <img src="<?= htmlspecialchars($webPath) ?>" alt="Issue Evidence" class="evidence-image" onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Image+Not+Found';" />
                    </a>
                    <?php if(!empty($img['issue_id']) && $img['issue_id'] != $id): ?>
                      <span class="badge badge-neutral" style="position:absolute;bottom:6px;left:6px;font-size:10px;background:rgba(0,0,0,0.75);color:#fff;backdrop-filter:blur(4px);">#FM<?= $img['issue_id'] ?></span>
                    <?php endif; ?>
                  </div>
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
          <!-- Before & After Proof of Work Evidence -->
          <?php if(!empty($issue['resolution_image'])): 
            $beforeImg = !empty($images[0]['image_path']) ? $images[0]['image_path'] : '';
            $afterImg = $issue['resolution_image'];
            
            $webBefore = '';
            if ($beforeImg) {
              if (filter_var($beforeImg, FILTER_VALIDATE_URL) || strpos($beforeImg, 'http') === 0) {
                $webBefore = $beforeImg;
              } elseif (strpos($beforeImg, 'uploads/') === 0) {
                $webBefore = '../' . $beforeImg;
              } else {
                $webBefore = '../uploads/issues/' . ltrim($beforeImg, '/');
              }
            }
            
            $webAfter = '';
            if (filter_var($afterImg, FILTER_VALIDATE_URL) || strpos($afterImg, 'http') === 0) {
              $webAfter = $afterImg;
            } elseif (strpos($afterImg, 'uploads/') === 0) {
              $webAfter = '../' . $afterImg;
            } else {
              $webAfter = '../uploads/issues/' . ltrim($afterImg, '/');
            }
          ?>
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;gap:8px;">
              <i class="bi bi-images" style="color:var(--emerald);"></i>
              <span>Proof of Work: Before &amp; After Photo Comparison</span>
            </div>
            <div class="panel-body">
              <div class="before-after-grid">
                <div class="before-after-card">
                  <span class="evidence-tag before">Before Repair</span>
                  <?php if ($webBefore): ?>
                    <a href="<?= htmlspecialchars($webBefore) ?>" target="_blank">
                      <img src="<?= htmlspecialchars($webBefore) ?>" alt="Before Repair">
                    </a>
                  <?php else: ?>
                    <div style="height:200px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:13px;">No initial photo attached</div>
                  <?php endif; ?>
                </div>
                <div class="before-after-card">
                  <span class="evidence-tag after">After (Resolved)</span>
                  <a href="<?= htmlspecialchars($webAfter) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($webAfter) ?>" alt="After Repair Completed">
                  </a>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="panel"><div class="panel-header">Status timeline</div><div class="panel-body">
            <?php if(empty($history)): ?><p class="muted-note">No changes yet.</p>
            <?php else: ?><div class="timeline"><?php foreach($history as $h): $col=$statusColors[$h['new_status']]??'var(--status-closed)'; ?><div class="timeline-item"><div class="timeline-dot" style="background:<?= $col ?>;"></div><div class="timeline-content"><div class="t-title"><?php if($h['old_status']): ?><?= getStatusBadge($h['old_status']) ?> <i class="bi bi-arrow-right mx-1" style="font-size:.875rem;"></i><?php endif; ?><?= getStatusBadge($h['new_status']) ?></div><div class="t-meta">By <b><?= htmlspecialchars($h['full_name']) ?></b> &bull; <?= date('d M Y, h:i A',strtotime($h['changed_at'])) ?></div><?php if($h['remarks']): ?><div class="t-remark">"<?= htmlspecialchars($h['remarks']) ?>"</div><?php endif; ?></div></div><?php endforeach; ?></div>
            <?php endif; ?>
          </div></div>
        </div>

        <!-- Right: Update Form -->
        <div style="display:flex;flex-direction:column;gap:14px;">
          <?php if(!in_array($issue['status'],['resolved','closed','rejected'])): ?>
          <div class="panel">
            <div class="panel-header">Update status</div>
            <div class="panel-body">
              <form method="POST" enctype="multipart/form-data">
                <div class="field-group">
                  <label class="form-label">Set status to</label>
                  <select name="new_status" class="form-control" aria-label="Set issue status">
                    <option value="in_progress" <?= $issue['status']==='in_progress'?'selected':'' ?>>In progress</option>
                    <option value="resolved">Mark as resolved</option>
                  </select>
                </div>
                <div class="field-group">
                  <label class="form-label">Proof of work / Resolution photo</label>
                  <input type="file" name="resolution_image" accept="image/jpeg,image/png,image/webp" class="form-control">
                  <div class="muted-note" style="margin-top:4px;">Attach photo showing fixed equipment or cleaned area.</div>
                </div>
                <div class="field-group" style="margin-bottom:0">
                  <label class="form-label">Work notes / remark *</label>
                  <textarea name="remarks" rows="4" placeholder="Describe the work done, parts replaced, or reason if not yet complete…" required class="form-control" aria-label="remarks"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-full" style="margin-top:14px;">Submit update</button>
              </form>
            </div>
          </div>
          <?php else: ?>
          <div class="panel"><div class="panel-body" style="text-align:center;">
            <div style="font-weight:600;margin-bottom:6px;">Issue <?= ucfirst($issue['status']) ?></div>
            <div class="muted-note">This issue has been closed. No further action required.</div>
          </div></div>
          <?php endif; ?>
          <!-- Issue Status Card -->
          <div class="panel">
            <div class="panel-header">Issue status</div>
            <div class="panel-body">
              <div style="text-align:center;padding:20px 0;">
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
                <div class="muted-note" style="margin-top:12px;">Last updated: <?= timeAgo($issue['updated_at'] ?? $issue['created_at']) ?></div>
              </div>
            </div>
          </div>

          <div class="panel"><div class="panel-header">Issue details</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:10px;">
              <div><span class="meta-label">Issue ID</span><div class="meta-value">#<?= $id ?></div></div>
              <div><span class="meta-label">Reporter</span><div class="meta-value"><?= htmlspecialchars($issue['reporter_name']) ?></div></div>
              <div><span class="meta-label">Email</span><div class="meta-value"><?= htmlspecialchars($issue['reporter_email']) ?></div></div>
              <div><span class="meta-label">Submitted</span><div class="meta-value"><?= date('d M Y',strtotime($issue['created_at'])) ?></div></div>
              <div><span class="meta-label">Last updated</span><div class="meta-value"><?= timeAgo($issue['updated_at']??$issue['created_at']) ?></div></div>
              <?php if (!empty($issue['is_parent']) || (!empty($issue['affected_count']) && $issue['affected_count']>1)): ?>
                <div><span class="meta-label">Incident Cluster</span><div class="meta-value"><span class="badge badge-amber"><i class="bi bi-collection me-1"></i><?= $issue['affected_count'] ?> Reports Linked</span></div></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
</body>
</html>
