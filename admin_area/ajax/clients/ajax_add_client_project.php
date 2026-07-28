<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}
if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!canAdminAccess('project_insert')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$response = ['success' => false, 'message' => 'Invalid request'];

if (isset($_POST['client_id']) && isset($_POST['project_name'])) {
    $client_id = intval($_POST['client_id']);
    $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
    $project_date = mysqli_real_escape_string($con, $_POST['project_date'] ?: date('Y-m-d'));
    $budget = floatval($_POST['budget'] ?: 0);
    $currency = mysqli_real_escape_string($con, $_POST['currency'] ?: 'INR');
    $project_manager = mysqli_real_escape_string($con, $_POST['project_manager']);
    $status = mysqli_real_escape_string($con, $_POST['status'] ?: 'Pending');
    $assigned_employees = isset($_POST['assigned_employees']) ? implode(',', $_POST['assigned_employees']) : '';

    $insert_project = "INSERT INTO client_projects (client_id, project_name,assigned_employees,project_date, budget, currency, project_manager, status) VALUES ($client_id, '$project_name', '$assigned_employees', '$project_date', $budget, '$currency', '$project_manager', '$status')";

    if (mysqli_query($con, $insert_project)) {
        $project_id = mysqli_insert_id($con);

        if (!empty($remark)) {
            $insert_remark = "INSERT INTO client_project_remarks (project_id, remark) VALUES ($project_id, '$remark')";
            mysqli_query($con, $insert_remark);
        }

        $response['success'] = true;
        $response['message'] = 'Project added successfully';
    } else {
        $response['message'] = 'Database error: ' . mysqli_error($con);
    }
}

echo json_encode($response);
