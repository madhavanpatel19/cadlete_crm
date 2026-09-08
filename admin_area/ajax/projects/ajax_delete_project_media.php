<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_id = (int)($_POST['asset_id'] ?? 0);

    if ($asset_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid asset ID']);
        exit;
    }

    $del = "UPDATE project_media_assets SET deleted_at = CURRENT_TIMESTAMP WHERE id = '$asset_id'";
    if (mysqli_query($con, $del)) {
        echo json_encode(['success' => true, 'message' => 'Asset removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
