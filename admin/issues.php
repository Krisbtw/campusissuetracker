<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole('admin');
$u=currentUser();
$msg=$err='';
$sf=trim($_GET['status']??'');$pf=trim($_GET['priority']??'');$search=trim($_GET['search']??'');
$where=['i.parent_id IS NULL'];$params=[];
if($sf){$where[]="i.status=?";$params[]=$sf;}
if($pf){$where[]="i.priority=?";$params[]=$pf;}
if($search){
  $searchLower = '%' . mb_strtolower($search, 'UTF-8') . '%';
  $where[]="(LOWER(i.title) LIKE ? OR LOWER(i.location) LIKE ? OR LOWER(u.full_name) LIKE ? OR LOWER(c.category_name) LIKE ? OR LOWER(i.description) LIKE ?)";
  $params[]=$searchLower;$params[]=$searchLower;$params[]=$searchLower;$params[]=$searchLower;$params[]=$searchLower;
}
$sql="SELECT i.*,c.category_name,u.full_name AS reporter FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id LEFT JOIN users u ON i.reported_by=u.user_id WHERE ".implode(' AND ',$where)." ORDER BY CASE i.priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END, i.created_at DESC";

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if($action==='merge_selected'){
    $selected=$_POST['selected_issues']??[];
    if(is_array($selected)&&count($selected)>=2){
      $selected=array_map('intval',$selected);
      sort($selected);
      $parent_id=$selected[0];
      $child_ids=array_slice($selected,1);

      $pdo->prepare("UPDATE issues SET is_parent=1 WHERE issue_id=?")->execute([$parent_id]);
      
      // Inherit parent's assigned technician and status to child issues
      $pInfoStmt = $pdo->prepare("SELECT assigned_to, status FROM issues WHERE issue_id = ?");
      $pInfoStmt->execute([$parent_id]);
      $pInfo = $pInfoStmt->fetch();
      $parentAssigned = $pInfo['assigned_to'] ?? null;
      $parentStatus = $pInfo['status'] ?? 'pending';

      foreach($child_ids as $cid){
        $pdo->prepare("UPDATE issues SET parent_id=?, assigned_to=?, status=? WHERE issue_id=?")->execute([$parent_id, $parentAssigned, $parentStatus, $cid]);
      }
      $pdo->prepare("UPDATE issues SET affected_count = (SELECT COUNT(*) + 1 FROM issues WHERE parent_id = ?) WHERE issue_id = ?")->execute([$parent_id,$parent_id]);
      logStatusChange($pdo,$parent_id,$u['id'],'','',"Merged ".count($child_ids)." duplicate report(s) into this incident.");

      // Notify Administrators only
      $admins = $pdo->query("SELECT user_id FROM users WHERE role='admin'")->fetchAll();
      foreach($admins as $admin){
        sendNotification($pdo, $admin['user_id'], $parent_id, "Merged issue(s) #" . implode(', #', $child_ids) . " into Parent Incident #{$parent_id}.", 'info');
      }
      $msg="Successfully merged ".count($child_ids)." issue(s) into Parent Incident #{$parent_id}.";
    } else {
      $err="Please select at least 2 issues to perform a merge.";
    }
  }
}

$stmt=$pdo->prepare($sql);$stmt->execute($params);$issues=$stmt->fetchAll();
$pageTitle='All Issues';$pageSubtitle='Manage and assign campus issues';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>All Issues – FixMyCampus Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
  <?php include '../includes/sidebar.php';?>
  <div class="main-content">
    <?php include '../includes/topbar.php';?>
    <main class="page-content">
      <div class="page-head">
        <h1 class="page-title">Issues</h1>
      </div>
      <?php if($msg):?><div class="alert-banner alert-success" role="status"><?=htmlspecialchars($msg)?></div><?php endif;?>
      <?php if($err):?><div class="alert-banner alert-danger" role="alert"><?=htmlspecialchars($err)?></div><?php endif;?>

      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search title, location, reporter..." value="<?=htmlspecialchars($search)?>" class="filter-control" aria-label="Search">
        <select name="status" class="filter-control" aria-label="Filter by status">
          <option value="">All status</option>
          <?php foreach(['pending','in_progress','resolved','closed','rejected'] as $s):?><option value="<?=$s?>"<?=$sf===$s?' selected':''?>><?=ucwords(str_replace('_',' ',$s))?></option><?php endforeach;?>
        </select>
        <select name="priority" class="filter-control" aria-label="Filter by priority">
          <option value="">All priority</option>
          <?php foreach(['low','medium','high','critical'] as $p):?><option value="<?=$p?>"<?=$pf===$p?' selected':''?>><?=ucfirst($p)?></option><?php endforeach;?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="issues.php" class="btn btn-secondary">Clear</a>
      </form>

      <form method="POST">
        <input type="hidden" name="action" value="merge_selected">
        <div class="cluster-toolbar">
          <div>
            <div style="font-size:14px;font-weight:600;">Incident clustering</div>
            <div class="muted-note">Select 2 or more issues to merge into one parent incident.</div>
          </div>
          <button type="submit" class="btn btn-primary">Merge selected</button>
        </div>

        <div class="panel">
          <div class="panel-header"><span>All issues <span class="issue-id">(<?=count($issues)?>)</span></span></div>
          <?php if(empty($issues)):?>
            <div class="empty-state"><p>No issues match these filters.</p></div>
          <?php else:?>
            <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
              <thead>
                <tr>
                  <th scope="col" style="width:36px;text-align:center;"><input type="checkbox" onclick="toggleSelectAll(this)" title="Select all"></th>
                  <th scope="col">#</th>
                  <th scope="col">Title</th>
                  <th scope="col">Reporter</th>
                  <th scope="col">Category</th>
                  <th scope="col">Location</th>
                  <th scope="col">Status</th>
                  <th scope="col">Date</th>
                  <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach($issues as $iss):?>
              <tr>
                <td style="text-align:center;"><input type="checkbox" name="selected_issues[]" value="<?=$iss['issue_id']?>" class="issue-cb" aria-label="Select issue for merging"></td>
                <td><span class="issue-id">#<?=$iss['issue_id']?></span></td>
                <td class="issue-title">
                  <div><?=htmlspecialchars($iss['title'])?></div>
                  <div style="margin-top:4px;display:flex;flex-wrap:wrap;gap:4px;">
                    <?=getSlaBadge($iss['created_at'], $iss['priority'], $iss['status'])?>
                    <?php if(!empty($iss['reopen_count']) && $iss['reopen_count']>0):?>
                      <span class="badge badge-rose">Reopened ×<?=$iss['reopen_count']?></span>
                    <?php endif;?>
                    <?php if(!empty($iss['is_parent']) || !empty($iss['affected_count']) && $iss['affected_count']>1):?>
                      <span class="badge badge-amber"><?=$iss['affected_count']??1?> affected · #FM<?=$iss['issue_id']?></span>
                    <?php elseif(!empty($iss['parent_id'])):?>
                      <span class="badge badge-neutral">Merged → #FM<?=$iss['parent_id']?></span>
                    <?php endif;?>
                  </div>
                </td>
                <td class="text-muted"><?=htmlspecialchars($iss['reporter'])?></td>
                <td class="text-muted"><?=htmlspecialchars($iss['category_name']??'—')?></td>
                <td class="text-muted"><?=htmlspecialchars($iss['location'])?></td>
                <td><?=getStatusBadge($iss['status'])?></td>
                <td><span class="issue-id"><?=date('d M Y',strtotime($iss['created_at']))?></span></td>
                <td><a href="view_issue.php?id=<?=$iss['issue_id']?>" class="btn-sm-icon" title="View"><i class="bi bi-arrow-right"></i></a></td>
              </tr>
              <?php endforeach;?>
              </tbody>
            </table></div>
          <?php endif;?>
        </div>
      </form>
    </main>
  </div>
</div>
<script>
function toggleSelectAll(master) {
  const checkboxes = document.querySelectorAll('.issue-cb');
  checkboxes.forEach(cb => cb.checked = master.checked);
}
</script>
</body></html>
