<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($con)) { include(__DIR__ . '/../../includes/db.php'); }
if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}
if (!canAdminAccess('project_delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_POST['project_id'])) {
    $project_id = intval($_POST['project_id']);
    $now        = date('Y-m-d H:i:s');

    // Soft delete project remarks
    mysqli_query($con, "UPDATE client_project_remarks SET deleted_at = '$now' WHERE project_id = $project_id AND deleted_at IS NULL");

    // Soft delete the project
    $update_project = "UPDATE client_projects SET deleted_at = '$now' WHERE id = $project_id AND deleted_at IS NULL";

    if (mysqli_query($con, $update_project)) {
        $response['success'] = true;
        $response['message'] = 'Project soft-deleted successfully';
    } else {
        $response['message'] = 'Database error: ' . mysqli_error($con);
    }
}

echo json_encode($response);
?>
