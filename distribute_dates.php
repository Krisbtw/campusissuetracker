<?php
/**
 * FixMyCampus - Issue Dates Distribution Script
 * Updates existing issues #1 through #7 and inserts additional tickets to enrich monthly trend charts.
 */
session_start();
require_once __DIR__ . '/config/db.php';

// 1. Fetch or create category mappings to ensure valid category_id references
$categoryList = [
    'HVAC / Cooling'         => ['HVAC / Cooling', 'bi-fan'],
    'IT / Network Issue'     => ['IT / Network Issue', 'bi-wifi'],
    'Infrastructure Damage' => ['Furniture Damage', 'bi-shop'],
    'Cleanliness / Waste'   => ['Cleanliness', 'bi-trash3-fill'],
    'Electrical Fault'       => ['Electrical Fault', 'bi-lightning-charge']
];

$catMap = [];
foreach ($categoryList as $key => $info) {
    $chk = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
    $chk->execute([$info[0]]);
    $res = $chk->fetch();
    if ($res) {
        $catMap[$key] = $res['category_id'];
    } else {
        $pdo->prepare("INSERT INTO categories (category_name, icon, description) VALUES (?, ?, ?)")
            ->execute([$info[0], $info[1], $info[0] . ' issues']);
        $catMap[$key] = $pdo->lastInsertId();
    }
}

// Ensure default student user for reported_by field
$studentId = $pdo->query("SELECT user_id FROM users WHERE role = 'student' LIMIT 1")->fetchColumn() ?: 1;

// 1. Update existing issues #1 through #7 with spread-out timestamps
$updates = [
    1 => '2026-03-14 10:20:00',
    2 => '2026-03-28 14:15:00',
    3 => '2026-04-10 09:30:00',
    4 => '2026-05-05 11:45:00',
    5 => '2026-06-18 16:00:00',
    6 => '2026-07-02 12:10:00',
    7 => '2026-07-22 15:30:00',
];

foreach ($updates as $id => $date) {
    $stmt = $pdo->prepare("UPDATE issues SET created_at = ?, updated_at = ? WHERE issue_id = ?");
    $stmt->execute([$date, $date, $id]);
}

// 2. Insert additional records to enrich monthly data
$extra_issues = [
    ['AC leaking water in Lab 1', 'HVAC / Cooling', 'High', 'Resolved', 'Block B - Lab 1', '2026-03-20 10:00:00'],
    ['Projector not turning on', 'IT / Network Issue', 'Medium', 'Resolved', 'FE Comp 1', '2026-04-15 14:00:00'],
    ['Broken door handle in C6', 'Infrastructure Damage', 'Low', 'Resolved', 'C6 Classroom', '2026-05-12 09:00:00'],
    ['Loose ceiling panel in corridor', 'Infrastructure Damage', 'High', 'In Progress', 'C3 Second Floor', '2026-06-04 11:30:00'],
    ['Water cooler not working', 'Cleanliness / Waste', 'Medium', 'Resolved', 'Canteen Area', '2026-06-25 15:00:00'],
    ['Ethernet wall port broken', 'IT / Network Issue', 'Low', 'Resolved', 'FE Comp 2', '2026-07-11 13:20:00'],
    ['Flickering tube light', 'Electrical Fault', 'Medium', 'Pending', 'Block A - 2nd Floor', '2026-08-08 10:15:00']
];

$insertStmt = $pdo->prepare("
    INSERT INTO issues (title, description, category_id, priority, status, location, reported_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($extra_issues as $issue) {
    $catId = $catMap[$issue[1]] ?? 1;
    $prio  = strtolower($issue[2]);
    $stat  = strtolower(str_replace(' ', '_', $issue[3]));
    $insertStmt->execute([
        $issue[0],
        "Reported: " . $issue[0] . " at " . $issue[4],
        $catId,
        $prio,
        $stat,
        $issue[4],
        $studentId,
        $issue[5],
        $issue[5]
    ]);
}

echo "<div style='font-family:sans-serif; padding:20px;'>";
echo "<h2 style='color:green;'>✓ Successfully updated and distributed issue dates from Mar 2026 to Aug 2026!</h2>";
echo "<p>Return to <a href='admin/dashboard.php'>Admin Dashboard</a> to view the updated trends.</p>";
echo "</div>";
