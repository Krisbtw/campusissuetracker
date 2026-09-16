<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u = currentUser();

$id = intval($_GET['id'] ?? 0);
$msg = $err = '';

// Fetch issue
$stmt = $pdo->prepare("SELECT i.*,c.category_name,rb.full_name AS reporter_name,rb.email AS reporter_email,ab.full_name AS assigned_name FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users rb ON i.reported_by=rb.user_id LEFT JOIN users ab ON i.assigned_to=ab.user_id WHERE i.issue_id=?");
$stmt->execute([$id]);
$issue = $stmt->fetch();
if (!$issue) { header('Location: issues.php'); exit(); }

// Handle POST (assign, status update, reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action       = $_POST['action'] ?? '';
    $old_status   = $issue['status'];
    $remarks      = trim($_POST['remarks'] ?? '');

    if ($action === 'assign') {
        $assignee = intval($_POST['assigned_to'] ?? 0);
        if ($assignee > 0) {
            $pdo->prepare("UPDATE issues SET assigned_to=?,status='in_progress',admin_remark=?,updated_at=NOW() WHERE issue_id=?")->execute([$assignee,$remarks,$id]);
            logStatusChange($pdo,$id,$u['id'],$old_status,'in_progress','Assigned to staff. '.$remarks);
            // Notify assignee
            $aName = $pdo->prepare("SELECT full_name FROM users WHERE user_id=?"); $aName->execute([$assignee]); $aRow=$aName->fetch();
            sendNotification($pdo,$assignee,$id,"Issue #{$id} has been assigned to you: {$issue['title']}. Priority: ".strtoupper($issue['priority']),'warning');
            // Notify reporter
            sendNotification($pdo,$issue['reported_by'],$id,"Your issue #{$id} is now In Progress. Assigned to ".(isset($aRow)?$aRow['full_name']:'a technician').".","info");
            
            // Mass propagate assignment to clustered child reports
            $rootParentId = !empty($issue['is_parent']) ? $id : (!empty($issue['parent_id']) ? $issue['parent_id'] : 0);
            if ($rootParentId > 0) {
                $cStmt = $pdo->prepare("SELECT issue_id, reported_by, status FROM issues WHERE (parent_id = ? OR issue_id = ?) AND issue_id != ?");
                $cStmt->execute([$rootParentId, $rootParentId, $id]);
                foreach ($cStmt->fetchAll() as $ch) {
                    $pdo->prepare("UPDATE issues SET assigned_to=?,status='in_progress',admin_remark=?,updated_at=NOW() WHERE issue_id=?")->execute([$assignee, $remarks, $ch['issue_id']]);
                    logStatusChange($pdo, $ch['issue_id'], $u['id'], $ch['status'], 'in_progress', "Assigned staff via Parent Incident #{$rootParentId}. {$remarks}");
                    sendNotification($pdo, $ch['reported_by'], $ch['issue_id'], "Your report #{$ch['issue_id']} (linked to Parent Incident #{$rootParentId}) is now In Progress.", 'info');
                }
            }

            $msg = 'Issue assigned successfully and mass-propagated to cluster.';
            // re-fetch
            $stmt->execute([$id]); $issue = $stmt->fetch();
        } else { $err = 'Please select a staff member.'; }

    } elseif ($action === 'status') {
        $new_status = $_POST['new_status'] ?? '';
        $valid = ['pending','in_progress','resolved','closed','rejected'];
        if (in_array($new_status,$valid)) {
            $pdo->prepare("UPDATE issues SET status=?,admin_remark=?,updated_at=NOW() WHERE issue_id=?")->execute([$new_status,$remarks,$id]);
            logStatusChange($pdo,$id,$u['id'],$old_status,$new_status,$remarks);
            // Notify reporter
            $notifType = $new_status==='resolved'?'success':($new_status==='rejected'?'danger':'info');
            sendNotification($pdo,$issue['reported_by'],$id,"Your issue #{$id} status changed to '".ucwords(str_replace('_',' ',$new_status))."'. ".($remarks?"Remark: $remarks":''),$notifType);
            
            // Mass propagate status update across all clustered child/parent reports
            $rootParentId = !empty($issue['is_parent']) ? $id : (!empty($issue['parent_id']) ? $issue['parent_id'] : 0);
            if ($rootParentId > 0) {
                $cStmt = $pdo->prepare("SELECT issue_id, reported_by, status FROM issues WHERE (parent_id = ? OR issue_id = ?) AND issue_id != ?");
                $cStmt->execute([$rootParentId, $rootParentId, $id]);
                foreach ($cStmt->fetchAll() as $ch) {
                    $pdo->prepare("UPDATE issues SET status=?,admin_remark=?,updated_at=NOW() WHERE issue_id=?")->execute([$new_status, $remarks, $ch['issue_id']]);
                    logStatusChange($pdo, $ch['issue_id'], $u['id'], $ch['status'], $new_status, "Status sync from Parent Incident #{$rootParentId}. {$remarks}");
                    sendNotification($pdo, $ch['reported_by'], $ch['issue_id'], "Your report #{$ch['issue_id']} (linked to Parent Incident #{$rootParentId}) status changed to '".ucwords(str_replace('_',' ',$new_status))."'. ".($remarks?"Remark: $remarks":''), $notifType);
                }
            }

            $msg = 'Status updated to ' . ucfirst($new_status) . ' and mass-propagated across cluster.';
            $stmt->execute([$id]); $issue = $stmt->fetch();
        } else { $err = 'Invalid status.'; }
    }
}

// Get maintenance staff & cluster roster
$maintStaff = $pdo->query("SELECT user_id,full_name,department FROM users WHERE role='maintenance' ORDER BY full_name")->fetchAll();
$images = $pdo->prepare("SELECT * FROM issue_images WHERE issue_id=?"); $images->execute([$id]); $images=$images->fetchAll();
$history = $pdo->prepare("SELECT sh.*,u.full_name FROM status_history sh LEFT JOIN users u ON sh.changed_by=u.user_id WHERE sh.issue_id=? ORDER BY sh.changed_at ASC"); $history->execute([$id]); $history=$history->fetchAll();
$statusColors = ['pending'=>'var(--status-pending)','in_progress'=>'var(--status-progress)','resolved'=>'var(--status-resolved)','closed'=>'var(--status-closed)','rejected'=>'var(--status-rejected)'];

// Roster Query for Duplicate Incident Cluster
$rootParentId = !empty($issue['is_parent']) ? $id : (!empty($issue['parent_id']) ? $issue['parent_id'] : 0);
$roster = [];
if ($rootParentId > 0) {
    $rStmt = $pdo->prepare("SELECT i.issue_id, i.created_at, i.status, i.parent_id, i.is_parent, u.full_name, u.email, u.phone, u.department FROM issues i LEFT JOIN users u ON i.reported_by = u.user_id WHERE i.issue_id = ? OR i.parent_id = ? ORDER BY (CASE WHEN i.issue_id = ? THEN 1 ELSE 0 END) DESC, i.created_at ASC");
    $rStmt->execute([$rootParentId, $rootParentId, $rootParentId]);
    $roster = $rStmt->fetchAll();
}

$pageTitle = 'Issue #'.$id.' – Admin View'; $pageSubtitle = htmlspecialchars($issue['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Issue #<?= $id ?> Admin – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include '../includes/topbar.php'; ?>
    <main class="page-content">
      <div style="margin-bottom:14px;"><a href="issues.php" class="muted-note"><i class="bi bi-arrow-left me-1"></i>Back to issues</a></div>
      <?php if($msg): ?><div class="alert-banner alert-success" role="status"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="alert-banner alert-danger" role="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>

      <div class="detail-grid">

        <!-- Left: Details -->
        <div style="display:flex;flex-direction:column;gap:16px;">
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
              <div>
                <span style="font-weight:600;"><?= htmlspecialchars($issue['title']) ?></span>
                <?php if(!empty($issue['is_parent']) || (!empty($issue['affected_count']) && $issue['affected_count']>1)): ?>
                  <span class="badge badge-amber"><?= $issue['affected_count'] ?> affected · Incident #FM<?= $issue['issue_id'] ?></span>
                <?php elseif(!empty($issue['parent_id'])): ?>
                  <span class="badge badge-blue">Merged → #FM<?= $issue['parent_id'] ?></span>
                <?php endif; ?>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <?= getSlaBadge($issue['created_at'], $issue['priority'], $issue['status']) ?>
                <?= getPriorityBadge($issue['priority']) ?>
                <?= getStatusBadge($issue['status']) ?>
              </div>
            </div>
            <div class="panel-body">
              <div class="three-col-grid">
                <div class="meta-tile"><div class="meta-label">Category</div><div class="meta-value"><?= htmlspecialchars($issue['category_name']??'N/A') ?></div></div>
                <div class="meta-tile"><div class="meta-label">Location</div><div class="meta-value"><?= htmlspecialchars($issue['location']) ?></div></div>
                <div class="meta-tile"><div class="meta-label">Reported by</div><div class="meta-value"><?= htmlspecialchars($issue['reporter_name']) ?></div></div>
              </div>
              <div style="margin-bottom:16px;">
                <div class="meta-label" style="margin-bottom:8px;">Description</div>
                <div style="line-height:1.75;"><?= nl2br(htmlspecialchars($issue['description'])) ?></div>
              </div>
              <?php if($issue['admin_remark']): ?>
              <div class="meta-tile">
                <div class="meta-label" style="margin-bottom:5px;">Admin remark</div>
                <div class="muted-note"><?= nl2br(htmlspecialchars($issue['admin_remark'])) ?></div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Affected Reporter Roster Panel -->
          <?php if (!empty($roster)): ?>
          <div class="panel">
            <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;">
              <span>Affected reporter roster (<?= count($roster) ?>)</span>
              <span class="badge badge-amber">Incident #FM<?= $rootParentId ?></span>
            </div>
            <div class="panel-body">
              <p class="muted-note" style="margin-bottom:12px;">The following reporters are linked to this parent incident:</p>
              <div class="table-scroll" role="region" aria-label="Reporter roster table" tabindex="0"><table class="table-custom">
                <thead>
                  <tr>
                    <th scope="col">Reporter</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Ticket #</th>
                    <th scope="col">Reported</th>
                    <th scope="col">Role in cluster</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($roster as $rep): ?>
                  <tr>
                    <td class="issue-title"><?= htmlspecialchars($rep['full_name']) ?><div class="muted-note"><?= htmlspecialchars($rep['department'] ?? 'Student') ?></div></td>
                    <td class="text-muted"><?= htmlspecialchars($rep['email']) ?><?php if(!empty($rep['phone'])): ?><br><?= htmlspecialchars($rep['phone']) ?><?php endif; ?></td>
                    <td><a href="view_issue.php?id=<?= $rep['issue_id'] ?>" class="issue-id">#<?= $rep['issue_id'] ?></a></td>
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
                  <div class="evidence-item-card" id="imgCard_<?= $img['image_id'] ?>">
                    <a href="<?= htmlspecialchars($webPath) ?>" target="_blank" class="evidence-link">
                      <img src="<?= htmlspecialchars($webPath) ?>" alt="Issue Evidence" class="evidence-image" onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Image+Not+Found';" />
                    </a>
                    <button type="button" class="evidence-delete-btn" onclick="deleteIssueImage(<?= $img['image_id'] ?>)" title="Delete this photo evidence" aria-label="Delete image">
                      <i class="bi bi-trash3-fill"></i>
                    </button>
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

          <!-- History -->
          <div class="panel">
            <div class="panel-header">Status timeline</div>
            <div class="panel-body">
              <?php if(empty($history)): ?><p class="muted-note">No changes yet.</p>
              <?php else: ?><div class="timeline">
                <?php foreach($history as $h): $col=$statusColors[$h['new_status']]??'var(--status-closed)'; ?>
                <div class="timeline-item">
                  <div class="timeline-dot" style="background:<?= $col ?>;"></div>
                  <div class="timeline-content">
                    <div class="t-title"><?php if($h['old_status']): ?><?= getStatusBadge($h['old_status']) ?> <i class="bi bi-arrow-right mx-1" style="font-size:.875rem;"></i><?php endif; ?><?= getStatusBadge($h['new_status']) ?></div>
                    <div class="t-meta">By <b><?= htmlspecialchars($h['full_name']) ?></b> &bull; <?= date('d M Y, h:i A',strtotime($h['changed_at'])) ?></div>
                    <?php if($h['remarks']): ?><div class="t-remark">"<?= htmlspecialchars($h['remarks']) ?>"</div><?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div><?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Right: Actions -->
        <div style="display:flex;flex-direction:column;gap:14px;">
          <!-- Assign -->
          <?php if(!in_array($issue['status'],['resolved','closed'])): ?>
          <div class="panel">
            <div class="panel-header">Assign issue</div>
            <div class="panel-body">
              <form method="POST">
                <input type="hidden" name="action" value="assign">
                <div class="field-group">
                  <label class="form-label">Assign to staff</label>
                  <select name="assigned_to" required class="form-control" aria-label="Assign to staff">
                    <option value="">-- Select staff --</option>
                    <?php foreach($maintStaff as $ms): ?>
                      <option value="<?= $ms['user_id'] ?>" <?= $issue['assigned_to']==$ms['user_id']?'selected':'' ?>>
                        <?= htmlspecialchars($ms['full_name']) ?> (<?= htmlspecialchars($ms['department']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field-group" style="margin-bottom:0">
                  <label class="form-label">Remarks (optional)</label>
                  <textarea name="remarks" rows="2" placeholder="Instructions for staff…" class="form-control" aria-label="remarks"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-full" style="margin-top:14px;">Assign &amp; set in progress</button>
              </form>
            </div>
          </div>
          <?php endif; ?>

          <!-- Change Status -->
          <div class="panel">
            <div class="panel-header">Update status</div>
            <div class="panel-body">
              <form method="POST">
                <input type="hidden" name="action" value="status">
                <div class="field-group">
                  <label class="form-label">New status</label>
                  <select name="new_status" class="form-control" aria-label="Set issue status">
                    <?php foreach(['pending','in_progress','resolved','closed','rejected'] as $s): ?>
                      <option value="<?= $s ?>" <?= $issue['status']===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field-group" style="margin-bottom:0">
                  <label class="form-label">Remark / reason *</label>
                  <textarea name="remarks" rows="2" placeholder="Reason for status change…" required class="form-control" aria-label="remarks"></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-full" style="margin-top:14px;">Update status</button>
              </form>
            </div>
          </div>

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

          <!-- Info card -->
          <div class="panel">
            <div class="panel-header">Issue info</div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:10px;">
              <div><span class="meta-label">Issue ID</span><div class="meta-value">#<?= $id ?></div></div>
              <div><span class="meta-label">Submitted</span><div class="meta-value"><?= date('d M Y',strtotime($issue['created_at'])) ?></div></div>
              <div><span class="meta-label">Reporter</span><div class="meta-value"><?= htmlspecialchars($issue['reporter_name']) ?></div></div>
              <div><span class="meta-label">Email</span><div class="meta-value"><?= htmlspecialchars($issue['reporter_email']) ?></div></div>
              <div><span class="meta-label">Assigned</span><div class="meta-value"><?= $issue['assigned_name'] ? htmlspecialchars($issue['assigned_name']) : 'Unassigned' ?></div></div>
              <div><span class="meta-label">Updated</span><div class="meta-value"><?= timeAgo($issue['updated_at']??$issue['created_at']) ?></div></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>
<script>
async function deleteIssueImage(imageId) {
  if (!confirm('Are you sure you want to delete this photo evidence?')) return;
  try {
    const res = await fetch('../api/delete_issue_image.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ image_id: imageId })
    });
    const data = await res.json();
    if (data.success) {
      const card = document.getElementById('imgCard_' + imageId);
      if (card) {
        card.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.8)';
        setTimeout(() => card.remove(), 200);
      }
    } else {
      alert(data.error || 'Failed to delete image.');
    }
  } catch (err) {
    alert('Network error while deleting image.');
  }
}
</script>
</body>
</html>
