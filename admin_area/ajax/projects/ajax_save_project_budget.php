<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}
if (!canAdminAccess('budget_insert') && !canAdminAccess('budget_update') && !canAdminAccess('budget_delete')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied: budget access required']);
    exit();
}

if (!isset($_POST['project_id']) || !isset($_POST['phases'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$project_id = mysqli_real_escape_string($con, $_POST['project_id']);
$phases = json_decode($_POST['phases'], true);

// Start transaction
mysqli_begin_transaction($con);

try {
    // Delete existing phases for this project
    mysqli_query($con, "DELETE FROM project_budget_phases WHERE project_id = '$project_id'");

    $total_cost = 0;

    if (!empty($phases)) {
        foreach ($phases as $phase) {
            $name = mysqli_real_escape_string($con, $phase['phase_name']);
            $desc = mysqli_real_escape_string($con, $phase['description']);
            $cost = floatval($phase['cost']);
            $received = floatval($phase['received_amount']);
            $date = !empty($phase['received_date']) ? "'" . mysqli_real_escape_string($con, $phase['received_date']) . "'" : "NULL";
            $remark = mysqli_real_escape_string($con, $phase['remark']);

            $total_cost += $cost;

            $insert = "INSERT INTO project_budget_phases (project_id, phase_name, description, cost, received_amount, received_date, remark) 
                       VALUES ('$project_id', '$name', '$desc', '$cost', '$received', $date, '$remark')";

            if (!mysqli_query($con, $insert)) {
                throw new Exception("Error inserting phase: " . mysqli_error($con));
            }
        }
    }

    $currency = isset($_POST['currency']) ? mysqli_real_escape_string($con, $_POST['currency']) : 'INR';

    // Update currency on main project (preserve overall project budget)
    $update_project = "UPDATE client_projects SET currency = '$currency' WHERE id = '$project_id'";
    mysqli_query($con, $update_project);

    mysqli_commit($con);
    echo json_encode(['success' => true, 'message' => 'Budget saved successfully']);
} catch (Exception $e) {
    mysqli_rollback($con);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
