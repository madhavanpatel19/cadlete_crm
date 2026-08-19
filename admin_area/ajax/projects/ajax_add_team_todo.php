<?php
session_start();
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0;

if ($is_emp && !$is_admin) {
    if ($emp_id != intval($_SESSION['emp_id'])) {
        echo json_encode(['success' => false, 'message' => 'Employees can only add tasks to their own column']);
        exit();
    }
}
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
    require_once __DIR__ . '/../../includes/notification_helper.php';
    $p_res = mysqli_query($con, "SELECT project_name FROM client_projects WHERE id = $project_id LIMIT 1");
    $p_row = mysqli_fetch_assoc($p_res);
    $p_name = $p_row['project_name'] ?? 'Project';

    $creator_name = 'Admin';
    $creator_emp_id = 0;
    $creator_admin_id = 0;
    if ($is_emp && isset($_SESSION['emp_id'])) {
        $creator_emp_id = intval($_SESSION['emp_id']);
        $emp = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM emp_list WHERE id={$creator_emp_id} LIMIT 1"));
        if ($emp) $creator_name = $emp['name'];
    } elseif ($is_admin) {
        $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $admin = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id, admin_name FROM admins WHERE admin_email='$ae' LIMIT 1"));
        if ($admin) {
            $creator_admin_id = intval($admin['admin_id']);
            $creator_name = $admin['admin_name'];
        }
    }

    $new_task_id = mysqli_insert_id($con);
    $url = "index.php?team_todo&project_id=$project_id&open_task_id=$new_task_id&emp_id=$emp_id";

    // 1. Notify assigned employee (if assigned to someone else)
    if ($emp_id !== $creator_emp_id) {
        addSystemNotification('employee', $emp_id, "New Task Assigned: $task_name", "$creator_name assigned task '$task_name' in $p_name.", $url, 'task_assigned');
    }

    // 2. Notify other assigned project members (excluding assigned employee & creator)
    notifyProjectMembers($project_id, "New Task in $p_name", "$creator_name added task '$task_name' in $p_name.", $url, 'task_assigned', $creator_emp_id > 0 ? $creator_emp_id : $emp_id);

    // 3. Notify Admins (excluding creator admin if posted by admin)
    notifyAllAdmins("New Task in $p_name", "$creator_name added task '$task_name' in $p_name.", $url, 'task_assigned', $creator_admin_id);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
}
