<?php
/**
 * FixMyCampus - Database Seeder (March 2026 - August 2026 Multi-Month Verification)
 * Populates realistic campus issue data spread evenly across past 6 months for chart analytics & trend verification.
 * 
 * Usage: Access via browser at http://localhost/fixmycampus/seed.php
 */
session_start();
require_once __DIR__ . '/config/db.php';

// Security / Access Check
$secretKey = 'secret_seed_123';
$passedKey = $_GET['key'] ?? '';
$isCli = (php_sapi_name() === 'cli');

if (!$isCli && !empty($secretKey) && !empty($passedKey) && $passedKey !== $secretKey) {
    die('<div style="font-family:sans-serif;padding:30px;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;max-width:500px;margin:50px auto;text-align:center;">
        <h2>Access Denied</h2>
        <p>Invalid security key provided. Please pass <code>?key=secret_seed_123</code> in the URL.</p>
    </div>');
}

// Clear old mock data to ensure clean distribution
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE status_history;");
    $pdo->exec("TRUNCATE TABLE notifications;");
    $pdo->exec("TRUNCATE TABLE issue_images;");
    $pdo->exec("DELETE FROM issues;");
    $pdo->exec("ALTER TABLE issues AUTO_INCREMENT = 1;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
} catch (Exception $e) {
    $pdo->exec("DELETE FROM status_history;");
    $pdo->exec("DELETE FROM notifications;");
    $pdo->exec("DELETE FROM issue_images;");
    $pdo->exec("DELETE FROM issues;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}

// 1. Ensure Essential Categories Exist
$defaultCategories = [
    ['Electrical Fault', 'bi-lightning-charge', 'Power failures, flickering lights, damaged sockets, or open wiring.'],
    ['HVAC / Cooling', 'bi-fan', 'AC unit breakdowns, excessive noise, water leaks, or inadequate cooling.'],
    ['Furniture Damage', 'bi-shop', 'Broken desks, cracked chairs, damaged whiteboards, or unhinged doors.'],
    ['IT / Network Issue', 'bi-wifi', 'Campus WiFi downtime, dead Ethernet ports, computer lab PC errors, or projector glitches.'],
    ['Plumbing', 'bi-droplet-fill', 'Leaking taps, clogged washroom drains, broken flush valves, or water shortages.'],
    ['Cleanliness', 'bi-trash3-fill', 'Overflowing dustbins, unclean washrooms, uncleaned hallways, or spillages.'],
    ['Safety & Security', 'bi-shield-lock-fill', 'Broken door locks, malfunctioning CCTV cameras, exposed wiring hazards, or damaged handrails.']
];

foreach ($defaultCategories as $cat) {
    $chk = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
    $chk->execute([$cat[0]]);
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO categories (category_name, icon, description) VALUES (?, ?, ?)")->execute($cat);
    }
}

// Fetch Category Map (Name => ID)
$catRows = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll();
$catMap = [];
foreach ($catRows as $cr) {
    $catMap[$cr['category_name']] = $cr['category_id'];
}

// 2. Ensure Users Exist (Admin, Staff, Students)
$pwdHash = '$2y$10$Z35bH6yk4CnQDdqqNoVNwe28rZv5xa206HpLeWUc0qaLYqsGdVxn.'; // bcrypt for 'password123'
$defaultUsers = [
    ['Admin User', 'admin@fixmycampus.com', $pwdHash, 'admin', 'IT Services', '9876543210'],
    ['John Student', 'student@fixmycampus.com', $pwdHash, 'student', 'Computer Science', '9876543211'],
    ['Jane Staff', 'staff@fixmycampus.com', $pwdHash, 'staff', 'Library', '9876543212'],
    ['Mike Maintenance', 'maintenance@fixmycampus.com', $pwdHash, 'maintenance', 'Maintenance Dept', '9876543213'],
    ['Sarah Techie', 'tech@fixmycampus.com', $pwdHash, 'maintenance', 'IT Department', '9876543214'],
    ['Maintenance Tech 1', 'staff1@fixmycampus.com', $pwdHash, 'maintenance', 'Facilities', '9876543211'],
    ['Maintenance Tech 2', 'staff2@fixmycampus.com', $pwdHash, 'maintenance', 'Electrical Dept', '9876543212'],
    ['Rahul Sharma', 'student1@fixmycampus.com', $pwdHash, 'student', 'Computer Science', '9876543213'],
    ['Ananya Roy', 'student2@fixmycampus.com', $pwdHash, 'student', 'Electronics Dept', '9876543214'],
    ['Priya Nair', 'student3@fixmycampus.com', $pwdHash, 'student', 'Mechanical Dept', '9876543215']
];

foreach ($defaultUsers as $u) {
    $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $chk->execute([$u[1]]);
    $existing = $chk->fetch();
    if (!$existing) {
        $pdo->prepare("INSERT INTO users (full_name, email, password, role, department, phone) VALUES (?, ?, ?, ?, ?, ?)")->execute($u);
    } else {
        $pdo->prepare("UPDATE users SET password = ?, role = ?, full_name = ? WHERE user_id = ?")->execute([$u[2], $u[3], $u[0], $existing['user_id']]);
    }
}

$students = $pdo->query("SELECT user_id FROM users WHERE role = 'student'")->fetchAll(PDO::FETCH_COLUMN);
$staff    = $pdo->query("SELECT user_id FROM users WHERE role IN ('maintenance','staff','admin')")->fetchAll(PDO::FETCH_COLUMN);
$adminId  = $staff[0] ?? 1;

if (empty($students)) $students = [1];
if (empty($staff)) $staff = [1];

$categoryImages = [
    'Electrical Fault'   => ['https://images.unsplash.com/photo-1544724569-5f546fd6f2b5?w=600'],
    'HVAC / Cooling'     => ['https://images.unsplash.com/photo-1621905251189-08b45d6a269e?w=600'],
    'Furniture Damage'   => ['https://images.unsplash.com/photo-1580481072645-022f9a6d1290?w=600'],
    'IT / Network Issue' => ['https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600'],
    'Plumbing'           => ['https://images.unsplash.com/photo-1585704032915-c3400ca199e7?w=600'],
    'Cleanliness'        => ['https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=600'],
    'Safety & Security'  => ['https://images.unsplash.com/photo-1557597774-9d273605dfa9?w=600']
];

// 31 Explicit Records across March 2026 to August 2026
$issuesToSeed = [
    // MARCH 2026 (4 Issues)
    ['title' => 'Projector HDMI port broken in Auditorium 1', 'cat' => 'IT / Network Issue', 'loc' => 'Auditorium Block', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-03-15 10:00:00'],
    ['title' => 'Water leakage near Block A washroom', 'cat' => 'Plumbing', 'loc' => 'Block A - 2nd Floor', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-03-15 10:00:00'],
    ['title' => 'Main entrance AC blowing warm air', 'cat' => 'HVAC / Cooling', 'loc' => 'Admin Block', 'prio' => 'critical', 'stat' => 'resolved', 'created' => '2026-03-15 10:00:00'],
    ['title' => 'Broken bench legs in Classroom C3', 'cat' => 'Furniture Damage', 'loc' => 'Classroom C3', 'prio' => 'low', 'stat' => 'resolved', 'created' => '2026-03-15 10:00:00'],

    // APRIL 2026 (5 Issues)
    ['title' => 'Uncleaned trash bins near Canteen patio', 'cat' => 'Cleanliness', 'loc' => 'Student Canteen Area', 'prio' => 'low', 'stat' => 'resolved', 'created' => '2026-04-12 14:30:00'],
    ['title' => 'Ceiling fan screeching noise in Electrical Lab 2', 'cat' => 'Electrical Fault', 'loc' => 'Block B - Electrical Lab 2', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-04-12 14:30:00'],
    ['title' => 'CCTV camera angle misaligned near Library exit', 'cat' => 'Safety & Security', 'loc' => 'Central Library', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-04-12 14:30:00'],
    ['title' => 'WiFi Access Point 4 dropping connections', 'cat' => 'IT / Network Issue', 'loc' => 'Hostel Block 2', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-04-12 14:30:00'],
    ['title' => 'Flush valve leaking in Boys Washroom', 'cat' => 'Plumbing', 'loc' => 'Block C - 1st Floor', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-04-12 14:30:00'],

    // MAY 2026 (6 Issues)
    ['title' => 'Door lock latch jammed in Seminar Hall 2', 'cat' => 'Safety & Security', 'loc' => 'Block B - Seminar Hall 2', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],
    ['title' => 'AC unit leaking water in Staff Room 102', 'cat' => 'HVAC / Cooling', 'loc' => 'Staff Room 102', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],
    ['title' => 'Short circuit in Lab 4 computer plug points', 'cat' => 'Electrical Fault', 'loc' => 'Block A - Lab 4', 'prio' => 'critical', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],
    ['title' => 'Broken podium desk in Lecture Hall 1', 'cat' => 'Furniture Damage', 'loc' => 'Lecture Hall 1', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],
    ['title' => 'Main hall water dispenser tap broken', 'cat' => 'Plumbing', 'loc' => 'Main Hall - Ground Floor', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],
    ['title' => 'Ethernet port non-responsive in Research Lab', 'cat' => 'IT / Network Issue', 'loc' => 'Research Lab Block D', 'prio' => 'low', 'stat' => 'resolved', 'created' => '2026-05-18 11:20:00'],

    // JUNE 2026 (5 Issues)
    ['title' => 'Dustbin overflow in Corridor B', 'cat' => 'Cleanliness', 'loc' => 'Block B - Corridor', 'prio' => 'low', 'stat' => 'resolved', 'created' => '2026-06-22 09:15:00'],
    ['title' => 'Exposed wire joint near Staircase 3', 'cat' => 'Electrical Fault', 'loc' => 'Staircase 3 - Block A', 'prio' => 'critical', 'stat' => 'resolved', 'created' => '2026-06-22 09:15:00'],
    ['title' => 'Window glass pane cracked in Classroom C5', 'cat' => 'Safety & Security', 'loc' => 'Classroom C5', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-06-22 09:15:00'],
    ['title' => 'Library AC 2 thermostat failure', 'cat' => 'HVAC / Cooling', 'loc' => 'Central Library', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-06-22 09:15:00'],
    ['title' => 'Hostel 3 RO water filter leaking', 'cat' => 'Plumbing', 'loc' => 'Hostel Block 3', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-06-22 09:15:00'],

    // JULY 2026 (7 Issues)
    ['title' => 'Main Gate light sensor not responding', 'cat' => 'Electrical Fault', 'loc' => 'Main Gate Entrance', 'prio' => 'medium', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Washroom pipe burst near Sports Complex', 'cat' => 'Plumbing', 'loc' => 'Sports Complex', 'prio' => 'critical', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Central Server Room AC cooling failure', 'cat' => 'HVAC / Cooling', 'loc' => 'Server Room - IT Dept', 'prio' => 'critical', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Campus Wi-Fi AP 12 offline', 'cat' => 'IT / Network Issue', 'loc' => 'Block C - 2nd Floor', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Whiteboard frame loose in Classroom B2', 'cat' => 'Furniture Damage', 'loc' => 'Classroom B2', 'prio' => 'low', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Fire extinguisher pressure gauge expired', 'cat' => 'Safety & Security', 'loc' => 'Chemistry Lab', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],
    ['title' => 'Stairwell light fitting dangling', 'cat' => 'Electrical Fault', 'loc' => 'Block C - Stairwell', 'prio' => 'high', 'stat' => 'resolved', 'created' => '2026-07-10 16:45:00'],

    // AUGUST 2026 (4 Issues + Clusters)
    ['title' => 'AC unit stopped cooling in Computer Lab 3', 'cat' => 'HVAC / Cooling', 'loc' => 'Block A – Computer Lab 3', 'prio' => 'critical', 'stat' => 'in_progress', 'created' => '2026-08-05 13:10:00'],
    ['title' => 'Flickering LED tube light near Board Room', 'cat' => 'Electrical Fault', 'loc' => 'Block A - 3rd Floor Corridor', 'prio' => 'medium', 'stat' => 'in_progress', 'created' => '2026-08-05 13:10:00'],
    ['title' => 'Broken whiteboard frame in Classroom C8', 'cat' => 'Furniture Damage', 'loc' => 'Classroom C8', 'prio' => 'low', 'stat' => 'pending', 'created' => '2026-08-05 13:10:00'],
    ['title' => 'Main Router offline in Mechanical Workshop', 'cat' => 'IT / Network Issue', 'loc' => 'Mechanical Workshop', 'prio' => 'critical', 'stat' => 'pending', 'created' => '2026-08-05 13:10:00'],
    
    // CLUSTER PARENTS
    ['title' => 'Tubelight flickering violently in Classroom C8', 'cat' => 'Electrical Fault', 'loc' => 'Classroom C8', 'prio' => 'high', 'stat' => 'in_progress', 'created' => '2026-08-12 10:00:00', 'is_cluster_parent' => true],
    ['title' => 'Library 2nd Floor WiFi completely unreachable', 'cat' => 'IT / Network Issue', 'loc' => 'Central Library - 2nd Floor', 'prio' => 'critical', 'stat' => 'pending', 'created' => '2026-08-13 11:30:00', 'is_cluster_parent' => true]
];

$seededIssues = 0;
$seededImages = 0;
$seededClusters = 0;
$parentMap = [];

foreach ($issuesToSeed as $item) {
    $catId = $catMap[$item['cat']] ?? 1;
    $reporterId = $students[array_rand($students)];
    $assigneeId = ($item['stat'] !== 'pending') ? $staff[array_rand($staff)] : null;
    
    $createdTime = $item['created'];
    if ($item['stat'] === 'resolved') {
        $updatedTime = date('Y-m-d H:i:s', strtotime($createdTime . " +2 days"));
    } else {
        $updatedTime = date('Y-m-d H:i:s', strtotime($createdTime . " +4 hours"));
    }

    $adminRemark = ($item['stat'] === 'resolved') ? "Inspection completed and repair verified." : null;
    $isParentFlag = !empty($item['is_cluster_parent']) ? 1 : 0;
    $affectedCount = $isParentFlag ? 3 : 1;

    $stmt = $pdo->prepare("INSERT INTO issues (title, description, category_id, location, priority, status, reported_by, assigned_to, is_parent, affected_count, admin_remark, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $item['title'],
        "Detailed campus issue report for " . strtolower($item['title']) . " at location " . $item['loc'] . ".",
        $catId,
        $item['loc'],
        $item['prio'],
        $item['stat'],
        $reporterId,
        $assigneeId,
        $isParentFlag,
        $affectedCount,
        $adminRemark,
        $createdTime,
        $updatedTime
    ]);
    
    $issueId = $pdo->lastInsertId();
    $seededIssues++;

    if ($isParentFlag) {
        $parentMap[$item['loc']] = $issueId;
        $seededClusters++;
    }

    if (!empty($categoryImages[$item['cat']])) {
        $imgUrl = $categoryImages[$item['cat']][0];
        $pdo->prepare("INSERT INTO issue_images (issue_id, image_path, uploaded_at) VALUES (?, ?, ?)")->execute([$issueId, $imgUrl, $createdTime]);
        $seededImages++;
    }

    $pdo->prepare("INSERT INTO status_history (issue_id, changed_by, old_status, new_status, remarks, changed_at) VALUES (?, ?, ?, ?, ?, ?)")->execute([
        $issueId, $reporterId, '', 'pending', 'Initial report submitted', $createdTime
    ]);

    if ($item['stat'] !== 'pending') {
        $pdo->prepare("INSERT INTO status_history (issue_id, changed_by, old_status, new_status, remarks, changed_at) VALUES (?, ?, ?, ?, ?, ?)")->execute([
            $issueId, $adminId, 'pending', $item['stat'], $adminRemark ?: 'Status updated', $updatedTime
        ]);
    }
}

// Child Duplicate Clusters
$childDuplicates = [
    ['title' => 'Tube light not working and buzzing in C8 class', 'cat' => 'Electrical Fault', 'loc' => 'Classroom C8', 'prio' => 'high', 'parent_loc' => 'Classroom C8', 'created' => '2026-08-12 11:00:00'],
    ['title' => 'No light in C8 lecture hall', 'cat' => 'Electrical Fault', 'loc' => 'Classroom C8', 'prio' => 'medium', 'parent_loc' => 'Classroom C8', 'created' => '2026-08-12 12:15:00'],
    ['title' => 'No Internet access on 2nd Floor Library WiFi', 'cat' => 'IT / Network Issue', 'loc' => 'Central Library - 2nd Floor', 'prio' => 'critical', 'parent_loc' => 'Central Library - 2nd Floor', 'created' => '2026-08-13 14:00:00']
];

foreach ($childDuplicates as $child) {
    $parentIssueId = $parentMap[$child['parent_loc']] ?? null;
    if ($parentIssueId) {
        $catId = $catMap[$child['cat']] ?? 1;
        $reporterId = $students[array_rand($students)];
        $createdTime = $child['created'];

        $stmt = $pdo->prepare("INSERT INTO issues (title, description, category_id, location, priority, status, reported_by, parent_id, is_parent, affected_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?)");
        $stmt->execute([
            $child['title'],
            "Duplicate student report linked to Parent Incident #{$parentIssueId}.",
            $catId,
            $child['loc'],
            $child['prio'],
            'in_progress',
            $reporterId,
            $parentIssueId,
            $createdTime,
            $createdTime
        ]);

        $childId = $pdo->lastInsertId();
        $seededIssues++;
    }
}

// Fetch Verification Debug Table Data
$isPgsql = (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql');
$dateFmt = $isPgsql ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')";
$debugRows = $pdo->query("SELECT issue_id, title, created_at, {$dateFmt} AS month_key FROM issues ORDER BY created_at ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Database Seeder – FixMyCampus</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background:#f8f6f0;min-height:100vh;padding:30px;font-family:sans-serif;">
  <div style="background:#ffffff;border:1px solid #cbbba8;border-radius:16px;max-width:850px;margin:0 auto;padding:28px;box-shadow:0 10px 30px rgba(60,21,21,0.08);">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;border-bottom:1px solid rgba(74,14,23,0.12);padding-bottom:16px;">
      <div style="width:44px;height:44px;border-radius:12px;background:#3c1515;color:#cbbba8;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
        <i class="bi bi-calendar-check-fill"></i>
      </div>
      <div>
        <h2 style="font-size:1.25rem;font-weight:700;color:#3c1515;margin:0;">Multi-Month Seeder Verification Complete</h2>
        <div style="font-size:0.8rem;color:#8a7575;">Exact dates seeded across March 2026 – August 2026</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
      <div style="background:#fcfbfa;border:1px solid #e8e2d8;border-radius:10px;padding:12px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:#3c1515;"><?= $seededIssues ?></div>
        <div style="font-size:0.75rem;color:#8a7575;">Total Issues</div>
      </div>
      <div style="background:#fcfbfa;border:1px solid #e8e2d8;border-radius:10px;padding:12px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:#b45309;"><?= count($catMap) ?></div>
        <div style="font-size:0.75rem;color:#8a7575;">Categories</div>
      </div>
      <div style="background:#fcfbfa;border:1px solid #e8e2d8;border-radius:10px;padding:12px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:#059669;"><?= $seededImages ?></div>
        <div style="font-size:0.75rem;color:#8a7575;">Photos</div>
      </div>
      <div style="background:#fcfbfa;border:1px solid #e8e2d8;border-radius:10px;padding:12px;text-align:center;">
        <div style="font-size:1.4rem;font-weight:800;color:#7c3aed;"><?= $seededClusters ?></div>
        <div style="font-size:0.75rem;color:#8a7575;">Clusters</div>
      </div>
    </div>

    <!-- Quick Links -->
    <div style="display:flex;gap:10px;margin-bottom:20px;">
      <a href="admin/dashboard.php" style="background:#3c1515;color:#ffffff;padding:8px 16px;border-radius:8px;font-size:0.8rem;font-weight:600;text-decoration:none;">
        <i class="bi bi-speedometer2" style="color:#cbbba8;"></i> Admin Dashboard
      </a>
      <a href="admin/reports.php" style="background:#059669;color:#ffffff;padding:8px 16px;border-radius:8px;font-size:0.8rem;font-weight:600;text-decoration:none;">
        <i class="bi bi-bar-chart-line"></i> View Monthly Reports
      </a>
    </div>

    <!-- Verification Debug Table -->
    <h3 style="font-size:0.95rem;font-weight:700;color:#3c1515;margin-bottom:10px;"><i class="bi bi-bug me-1.5"></i>Seeded Dates Verification Table</h3>
    <div style="max-height:280px;overflow-y:auto;border:1px solid #e8e2d8;border-radius:8px;">
      <table style="width:100%;font-size:0.78rem;border-collapse:collapse;text-align:left;">
        <thead style="background:#f8f6f0;position:sticky;top:0;border-bottom:1px solid #e8e2d8;">
          <tr>
            <th style="padding:8px 12px;color:#3c1515;">ID</th>
            <th style="padding:8px 12px;color:#3c1515;">Title</th>
            <th style="padding:8px 12px;color:#3c1515;">created_at Timestamp</th>
            <th style="padding:8px 12px;color:#3c1515;">Month Key (%Y-%m)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($debugRows as $row): ?>
          <tr style="border-bottom:1px solid #f0eae1;">
            <td style="padding:6px 12px;font-weight:600;color:#3c1515;">#<?= $row['issue_id'] ?></td>
            <td style="padding:6px 12px;color:#4a3333;"><?= htmlspecialchars($row['title']) ?></td>
            <td style="padding:6px 12px;font-family:monospace;color:#2563eb;"><?= $row['created_at'] ?></td>
            <td style="padding:6px 12px;font-weight:700;color:#059669;"><?= $row['month_key'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
