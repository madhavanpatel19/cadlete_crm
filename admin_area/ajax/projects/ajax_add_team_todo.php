<?php
session_start();
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!canAdminAccess('project_assign_task') && !canAdminAccess('todo_insert')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0;
$task_name = isset($_POST['task_name']) ? mysqli_real_escape_string($con, $_POST['task_name']) : '';
$due_date = !empty($_POST['due_date']) ? mysqli_real_escape_string($con, $_POST['due_date']) : null;
$priority = isset($_POST['priority']) ? mysqli_real_escape_string($con, $_POST['priority']) : 'Medium';

if ($emp_id == 0 || empty($task_name)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$date_val = $due_date ? "'$due_date'" : "NULL";

$query = "INSERT INTO project_team_todos (project_id, emp_id, task_name, due_date, priority) VALUES ($project_id, $emp_id, '$task_name', $date_val, '$priority')";
if (mysqli_query($con, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
