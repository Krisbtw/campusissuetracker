<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

requireRole(['admin']);

$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category_id = intval($_GET['category_id'] ?? 0);
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$where = ['1=1'];
$params = [];

if (!empty($status)) {
    $where[] = 'i.status = ?';
    $params[] = $status;
}
if (!empty($priority)) {
    $where[] = 'i.priority = ?';
    $params[] = $priority;
}
if ($category_id > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $category_id;
}
if (!empty($from_date)) {
    $where[] = 'i.created_at >= ?';
    $params[] = $from_date . ' 00:00:00';
}
if (!empty($to_date)) {
    $where[] = 'i.created_at <= ?';
    $params[] = $to_date . ' 23:59:59';
}

$sql = "SELECT 
            i.issue_id,
            i.title,
            c.category_name,
            i.location,
            i.priority,
            i.status,
            u_rep.name AS reporter_name,
            u_rep.email AS reporter_email,
            u_tech.name AS technician_name,
            i.rating,
            i.feedback,
            i.created_at,
            i.updated_at
        FROM issues i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN users u_rep ON i.reported_by = u_rep.user_id
        LEFT JOIN users u_tech ON i.assigned_to = u_tech.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.issue_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'campus_issues_report_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, [
    'Issue ID',
    'Title',
    'Category',
    'Location',
    'Priority',
    'Status',
    'Reported By',
    'Reporter Email',
    'Assigned Technician',
    'Student Rating (1-5)',
    'Student Feedback',
    'Date Submitted',
    'Last Updated'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        '#' . $row['issue_id'],
        $row['title'],
        $row['category_name'] ?? 'Uncategorized',
        $row['location'],
        strtoupper($row['priority']),
        ucfirst(str_replace('_', ' ', $row['status'])),
        $row['reporter_name'] ?? 'Unknown',
        $row['reporter_email'] ?? '',
        $row['technician_name'] ?? 'Unassigned',
        $row['rating'] ? $row['rating'] . ' / 5' : 'N/A',
        $row['feedback'] ?? '',
        $row['created_at'],
        $row['updated_at']
    ]);
}

fclose($output);
exit;
