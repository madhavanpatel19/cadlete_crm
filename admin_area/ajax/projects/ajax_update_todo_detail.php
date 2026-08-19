<?php
session_start();
if (!isset($con)) include(__DIR__ . '/../../includes/db.php');
if (!function_exists('canAdminAccess')) require_once __DIR__ . '/../../includes/admin_permissions.php';
header('Content-Type: application/json');

$is_admin = isset($_SESSION['admin_email']);
$is_emp   = isset($_SESSION['emp_id']);

if (!$is_admin && !$is_emp) { 
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); 
    exit(); 
}

if ($is_admin && !canAdminAccess('project_assign_task') && !canAdminAccess('todo_update') && !canAdminAccess('project_view') && !canAdminAccess('todo_view')) { 
    echo json_encode(['success' => false, 'message' => 'Permission denied']); 
    exit(); 
}

$task_id = intval($_POST['task_id'] ?? 0);
if (!$task_id) { 
    echo json_encode(['success' => false, 'message' => 'Invalid task']); 
    exit(); 
}

$fields = [];
if (isset($_POST['task_name']) && trim($_POST['task_name']) !== '') {
    $fields[] = "task_name='" . mysqli_real_escape_string($con, trim($_POST['task_name'])) . "'";
}
if (isset($_POST['description'])) {
    $fields[] = "description='" . mysqli_real_escape_string($con, $_POST['description']) . "'";
}
if (isset($_POST['due_date'])) {
    $fields[] = "due_date=" . (!empty($_POST['due_date']) ? "'" . mysqli_real_escape_string($con, $_POST['due_date']) . "'" : "NULL");
}
if (isset($_POST['priority'])) {
    $fields[] = "priority='" . mysqli_real_escape_string($con, $_POST['priority']) . "'";
}

if (empty($fields)) { 
    echo json_encode(['success' => false, 'message' => 'No fields to update']); 
    exit(); 
}

$res = mysqli_query($con, "UPDATE project_team_todos SET " . implode(',', $fields) . " WHERE id = $task_id AND deleted_at IS NULL");
if ($res) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
