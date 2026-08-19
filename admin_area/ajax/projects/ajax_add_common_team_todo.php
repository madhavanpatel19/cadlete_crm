<?php
ob_start();
session_start();
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../includes/admin_permissions.php';
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!canAdminAccess('project_assign_task') && !canAdminAccess('todo_insert') && !canAdminAccess('project_view') && !canAdminAccess('todo_view')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit();
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

// Accept emp_ids as array, JSON string, or comma-separated string
$emp_ids = [];
if (isset($_POST['emp_ids'])) {
    if (is_array($_POST['emp_ids'])) {
        $emp_ids = array_map('intval', $_POST['emp_ids']);
    } else {
        $decoded = json_decode($_POST['emp_ids'], true);
        if (is_array($decoded)) {
            $emp_ids = array_map('intval', $decoded);
        } else {
            $emp_ids = array_map('intval', explode(',', $_POST['emp_ids']));
        }
    }
}
$emp_ids = array_filter($emp_ids, function($id) { return $id > 0; });

$task_name = isset($_POST['task_name']) ? mysqli_real_escape_string($con, trim($_POST['task_name'])) : '';
$description = isset($_POST['description']) ? mysqli_real_escape_string($con, trim($_POST['description'])) : '';
$due_date = !empty($_POST['due_date']) ? mysqli_real_escape_string($con, $_POST['due_date']) : null;
$priority = isset($_POST['priority']) ? mysqli_real_escape_string($con, $_POST['priority']) : 'Medium';
$comment = isset($_POST['comment']) ? mysqli_real_escape_string($con, trim($_POST['comment'])) : '';

if ($project_id == 0 || empty($emp_ids) || empty($task_name)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parameters. Please provide task name and select at least one employee.']);
    exit();
}

// Fetch Admin info for comment posting
$ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
$admin_result = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email='$ae' LIMIT 1");
$admin = $admin_result ? mysqli_fetch_assoc($admin_result) : null;
$admin_id = $admin ? intval($admin['admin_id']) : 0;

$date_val = $due_date ? "'$due_date'" : "NULL";
$desc_val = !empty($description) ? "'$description'" : "NULL";

$created_count = 0;

foreach ($emp_ids as $emp_id) {
    $query = "INSERT INTO project_team_todos (project_id, emp_id, task_name, description, due_date, priority, created_at) 
              VALUES ($project_id, $emp_id, '$task_name', $desc_val, $date_val, '$priority', NOW())";

    if (mysqli_query($con, $query)) {
        $task_id = mysqli_insert_id($con);
        $created_count++;

        if (!empty($comment) && $admin_id > 0 && $task_id > 0) {
            mysqli_query($con, "INSERT INTO project_todo_comments (task_id, admin_id, comment, created_at) VALUES ($task_id, $admin_id, '$comment', NOW())");
        }
    }
}

if ($created_count > 0) {
    require_once __DIR__ . '/../../includes/notification_helper.php';
    $p_res = mysqli_query($con, "SELECT project_name FROM client_projects WHERE id = $project_id LIMIT 1");
    $p_row = mysqli_fetch_assoc($p_res);
    $p_name = $p_row['project_name'] ?? 'Project';
    $url = "index.php?team_todo&project_id=$project_id";

    foreach ($emp_ids as $eid) {
        $eid = intval($eid);
        if ($eid > 0) {
            addSystemNotification('employee', $eid, "New Task Assigned: $task_name", "Admin assigned common task '$task_name' in $p_name.", $url, 'task_assigned');
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'count' => $created_count,
        'message' => "Common task successfully assigned to $created_count employee(s)."
    ]);
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to create tasks. ' . mysqli_error($con)]);
}
