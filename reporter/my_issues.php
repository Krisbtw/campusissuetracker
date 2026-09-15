<?php
session_start();
require_once '../includes/auth_check.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
requireRole(['student','staff']);
$u=currentUser();
$sf=trim($_GET['status']??'');$pf=trim($_GET['priority']??'');$search=trim($_GET['search']??'');
$where=["i.reported_by=?"];$params=[$u['id']];
if($sf){$where[]="i.status=?";$params[]=$sf;}
if($pf){$where[]="i.priority=?";$params[]=$pf;}
if($search){
  $searchLower = '%' . mb_strtolower($search, 'UTF-8') . '%';
  $where[]="(LOWER(i.title) LIKE ? OR LOWER(i.location) LIKE ? OR LOWER(c.category_name) LIKE ? OR LOWER(i.description) LIKE ?)";
  $params[]=$searchLower;$params[]=$searchLower;$params[]=$searchLower;$params[]=$searchLower;
}
$sql="SELECT i.*,c.category_name FROM issues i LEFT JOIN categories c ON i.category_id=c.category_id WHERE ".implode(' AND ',$where)." ORDER BY i.created_at DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$issues=$stmt->fetchAll();
$pageTitle='My Issues';$pageSubtitle='Track all your submitted issues';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Issues – FixMyCampus</title>
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
        <h1 class="page-title"><?=htmlspecialchars($pageTitle)?></h1>
      </div>
      <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search title or location..." value="<?=htmlspecialchars($search)?>" class="filter-control" aria-label="Search">
        <select name="status" class="filter-control" aria-label="Filter by status">
          <option value="">All status</option>
          <?php foreach(['pending','in_progress','resolved','closed','rejected'] as $s):?><option value="<?=$s?>"<?=$sf===$s?' selected':''?>><?=ucwords(str_replace('_',' ',$s))?></option><?php endforeach;?>
        </select>
        <select name="priority" class="filter-control" aria-label="Filter by priority">
          <option value="">All priority</option>
          <?php foreach(['low','medium','high','critical'] as $p):?><option value="<?=$p?>"<?=$pf===$p?' selected':''?>><?=ucfirst($p)?></option><?php endforeach;?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="my_issues.php" class="btn btn-secondary">Clear</a>
        <a href="report_issue.php" class="btn btn-primary" style="margin-left:auto;">New issue</a>
      </form>
      <div class="panel">
        <div class="panel-header"><span>Issues <span class="issue-id">(<?=count($issues)?>)</span></span></div>
        <?php if(empty($issues)):?>
          <div class="empty-state"><p><?=$search||$sf||$pf?'No matching issues.':'You have not reported any issues.'?></p><?php if(!$search&&!$sf&&!$pf):?><a href="report_issue.php" class="btn btn-primary">Report an issue</a><?php endif;?></div>
        <?php else:?>
          <div class="table-scroll" role="region" aria-label="Issues table" tabindex="0"><table class="table-dark-custom">
            <thead><tr><th scope="col">#</th><th scope="col">Title</th><th scope="col">Category</th><th scope="col">Location</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach($issues as $iss):?>
            <tr>
              <td><span class="issue-id">#<?=$iss['issue_id']?></span></td>
              <td class="issue-title"><?=htmlspecialchars($iss['title'])?></td>
              <td class="text-muted"><?=htmlspecialchars($iss['category_name']??'—')?></td>
              <td class="text-muted"><?=htmlspecialchars($iss['location']??'—')?></td>
              <td><?=getStatusBadge($iss['status'])?></td>
              <td><span class="issue-id"><?=date('d M Y',strtotime($iss['created_at']))?></span></td>
              <td><a href="view_issue.php?id=<?=$iss['issue_id']?>" class="btn-sm-icon" title="View"><i class="bi bi-arrow-right"></i></a></td>
            </tr>
            <?php endforeach;?>
            </tbody>
          </table></div>
        <?php endif;?>
      </div>
    </main>
  </div>
</div>
</body></html>
