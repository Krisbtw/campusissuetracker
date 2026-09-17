<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in.']);
    exit();
}

$u = currentUser();
$input = json_decode(file_get_contents('php://input'), true);
$imageId = intval($input['image_id'] ?? $_POST['image_id'] ?? 0);

if ($imageId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid image ID.']);
    exit();
}

try {
    // Find image and related issue
    $stmt = $pdo->prepare("SELECT ii.*, i.reported_by, i.status FROM issue_images ii JOIN issues i ON ii.issue_id = i.issue_id WHERE ii.image_id = ?");
    $stmt->execute([$imageId]);
    $img = $stmt->fetch();

    if (!$img) {
        echo json_encode(['success' => false, 'error' => 'Image not found.']);
        exit();
    }

    // Authorization: Admin can delete any; Reporter can delete their own image if issue is not closed
    $isAdmin = ($u['role'] === 'admin');
    $isReporter = ($img['reported_by'] == $u['id']);

    if (!$isAdmin && !$isReporter) {
        echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this image.']);
        exit();
    }

    // Delete local file if present
    $path = $img['image_path'] ?? '';
    if (!empty($path) && !filter_var($path, FILTER_VALIDATE_URL) && strpos($path, 'http://') !== 0 && strpos($path, 'https://') !== 0) {
        $localFile = UPLOAD_DIR . ltrim(basename($path), '/');
        if (file_exists($localFile)) {
            @unlink($localFile);
        }
    }

    // Delete from DB
    $delStmt = $pdo->prepare("DELETE FROM issue_images WHERE image_id = ?");
    $delStmt->execute([$imageId]);

    echo json_encode(['success' => true, 'message' => 'Image deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete image: ' . $e->getMessage()]);
}
