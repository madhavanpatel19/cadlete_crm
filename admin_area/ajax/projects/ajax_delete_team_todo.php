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
if (!canAdminAccess('project_assign_task') && !canAdminAccess('todo_delete') && !canAdminAccess('project_view') && !canAdminAccess('todo_view')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

if ($task_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$now   = date('Y-m-d H:i:s');
$query = "UPDATE project_team_todos SET deleted_at = '$now' WHERE id = $task_id AND deleted_at IS NULL";
if (mysqli_query($con, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
