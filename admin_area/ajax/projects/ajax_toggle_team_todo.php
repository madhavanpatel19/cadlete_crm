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

$portal = isset($_POST['portal']) ? $_POST['portal'] : '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$is_emp_action = false;
if ($portal === 'employee' || strpos($referer, 'emp_area') !== false) {
    $is_emp_action = true;
} elseif ($portal === 'admin' || strpos($referer, 'admin_area') !== false) {
    $is_emp_action = false;
} elseif ($is_admin) {
    $is_emp_action = false;
} elseif ($is_emp) {
    $is_emp_action = true;
}

// If employee, find the task or sibling task belonging to logged in employee
if ($is_emp_action && isset($_SESSION['emp_id'])) {
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
    // Notify project admins when task is completed
    if ($status == 1) {
        require_once __DIR__ . '/../../includes/notification_helper.php';
        $t_info_q = mysqli_query($con, "SELECT t.task_name, t.emp_id, t.project_id, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.id = $task_id LIMIT 1");
        if ($t_info_q && $t_row = mysqli_fetch_assoc($t_info_q)) {
            $t_name = $t_row['task_name'] ?? 'Task';
            $p_name = $t_row['project_name'] ?? 'Project';
            $pid = intval($t_row['project_id']);
            $task_emp_id = intval($t_row['emp_id']);
            $actor_name = 'Employee';
            $exclude_admin_id = 0;

            if ($is_emp_action && isset($_SESSION['emp_id'])) {
                $e_id = intval($_SESSION['emp_id']);
                $emp_q = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM emp_list WHERE id = $e_id LIMIT 1"));
                if ($emp_q) $actor_name = $emp_q['name'];
            } elseif ($is_admin) {
                $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
                $a_q = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id, admin_name FROM admins WHERE admin_email = '$ae' LIMIT 1"));
                if ($a_q) {
                    $actor_name = $a_q['admin_name'];
                    $exclude_admin_id = intval($a_q['admin_id']);
                }
            }

            $url = "index.php?team_todo&project_id=$pid&open_task_id=$task_id&emp_id=$task_emp_id";
            notifyProjectAdmins($pid, "Task Completed: $t_name", "$actor_name completed task '$t_name' in $p_name.", $url, 'task_completed', $exclude_admin_id, $task_emp_id);
        }
    }

    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'task_id' => $task_id, 'status' => $status]);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
