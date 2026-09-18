<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

ensureSession($_GET);
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$u = currentUser();

$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : (isset($_POST['category_id']) ? intval($_POST['category_id']) : 0);
$location = trim($_GET['location'] ?? ($_POST['location'] ?? ''));
$title = trim($_GET['title'] ?? ($_POST['title'] ?? ''));

if ($category_id <= 0 && mb_strlen($location) < 3 && mb_strlen($title) < 3) {
    echo json_encode(['success' => true, 'count' => 0, 'similar' => []]);
    exit;
}

try {
    // Look for active open/in-progress issues in the last 14 days
    $params = [];
    $where = ["status IN ('pending', 'in_progress', 'assigned')"];

    // Search by category and matching location/title words
    if ($category_id > 0 && mb_strlen($location) >= 3) {
        $where[] = "category_id = ?";
        $params[] = $category_id;

        // Clean location for pattern search
        $locClean = mb_strtolower(trim($location));
        $where[] = "(LOWER(location) LIKE ? OR LOWER(title) LIKE ?)";
        $params[] = '%' . $locClean . '%';
        $params[] = '%' . $locClean . '%';
    } elseif ($category_id > 0 && mb_strlen($title) >= 3) {
        $where[] = "category_id = ?";
        $params[] = $category_id;
        $titleClean = mb_strtolower(trim($title));
        $where[] = "LOWER(title) LIKE ?";
        $params[] = '%' . $titleClean . '%';
    } elseif (mb_strlen($location) >= 3) {
        $locClean = mb_strtolower(trim($location));
        $where[] = "LOWER(location) LIKE ?";
        $params[] = '%' . $locClean . '%';
    }

    $sql = "SELECT i.issue_id, i.title, i.location, i.status, i.priority, i.created_at, c.category_name, u.name as reporter_name 
            FROM issues i
            LEFT JOIN categories c ON i.category_id = c.category_id
            LEFT JOIN users u ON i.reported_by = u.user_id
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY i.created_at DESC 
            LIMIT 3";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count' => count($matches),
        'similar' => $matches
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
