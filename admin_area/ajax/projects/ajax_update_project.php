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
if (!canAdminAccess('project_update')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_POST['project_id']) && isset($_POST['project_name'])) {
    $project_id = intval($_POST['project_id']);
    $client_id = intval($_POST['client_id']);
    $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
    $project_date = mysqli_real_escape_string($con, $_POST['project_date']);
    $budget = floatval($_POST['budget']);
    $currency = mysqli_real_escape_string($con, $_POST['currency'] ?: 'INR');
    $status = mysqli_real_escape_string($con, $_POST['status']);

    $update_project = "UPDATE client_projects SET 
                        client_id = $client_id,
                        project_name = '$project_name',
                        project_date = '$project_date',
                        budget = $budget,
                        currency = '$currency',
                        status = '$status'
                       WHERE id = $project_id";
    
    if (mysqli_query($con, $update_project)) {
        $response['success'] = true;
        $response['message'] = 'Project updated successfully';
        
        // Log update
        $system_remark = "System: Project details updated (Name: $project_name, Budget: $budget, Status: $status)";
        $insert_remark = "INSERT INTO client_project_remarks (project_id, remark) VALUES ($project_id, '$system_remark')";
        mysqli_query($con, $insert_remark);
    } else {
        $response['message'] = 'Database error: ' . mysqli_error($con);
    }
}

echo json_encode($response);
?>
