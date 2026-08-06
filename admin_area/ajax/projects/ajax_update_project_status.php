<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($con)) { include(__DIR__ . '/../../includes/db.php'); }
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}
if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!canAdminAccess('project_update')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_POST['project_id']) && isset($_POST['status'])) {
    $project_id = intval($_POST['project_id']);
    $status = mysqli_real_escape_string($con, $_POST['status']);

    $update_status = "UPDATE client_projects SET status = '$status' WHERE id = $project_id";
    
    if (mysqli_query($con, $update_status)) {
        $response['success'] = true;
        $response['message'] = 'Status updated to ' . $status;

        // Automatically log a system remark for status change
        $system_remark = "System: Project status updated to $status";
        $insert_remark = "INSERT INTO client_project_remarks (project_id, remark, posted_by) VALUES ($project_id, '$system_remark', 'System')";
        mysqli_query($con, $insert_remark);
    } else {
        $response['message'] = 'Database error: ' . mysqli_error($con);
    }
}

echo json_encode($response);
?>
