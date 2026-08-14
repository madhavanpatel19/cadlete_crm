<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}

header('Content-Type: application/json');

$is_admin = isset($_SESSION['admin_email']);
$is_emp = isset($_SESSION['emp_id']);

if (!$is_admin && !$is_emp) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$status = isset($_POST['status']) ? intval($_POST['status']) : 0;

if ($task_id == 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// If employee, find the task or sibling task belonging to logged in employee
if ($is_emp && !$is_admin) {
    $emp_id_val = intval($_SESSION['emp_id']);
    $chk = mysqli_query($con, "SELECT id FROM project_team_todos WHERE id = $task_id AND emp_id = $emp_id_val AND deleted_at IS NULL");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        $t_info = mysqli_fetch_assoc(mysqli_query($con, "SELECT project_id, task_name FROM project_team_todos WHERE id = $task_id LIMIT 1"));
        if ($t_info && !empty($t_info['task_name'])) {
            $t_name_esc = mysqli_real_escape_string($con, $t_info['task_name']);
            $p_id_esc = intval($t_info['project_id']);
            $match_task = mysqli_fetch_assoc(mysqli_query($con, "SELECT id FROM project_team_todos WHERE project_id = $p_id_esc AND task_name = '$t_name_esc' AND emp_id = $emp_id_val AND deleted_at IS NULL LIMIT 1"));
            if ($match_task) {
                $task_id = intval($match_task['id']);
            }
        }
    }
}

$now = date('Y-m-d H:i:s');
$comp_val = ($status == 1) ? "'$now'" : "NULL";

$query = "UPDATE project_team_todos SET status = $status, completed_at = $comp_val WHERE id = $task_id AND deleted_at IS NULL";
if (mysqli_query($con, $query)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'task_id' => $task_id, 'status' => $status]);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
