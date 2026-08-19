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

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0;
$strict_project = isset($_POST['strict_project']) ? intval($_POST['strict_project']) : 0;

if ($emp_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

if ($strict_project == 1) {
    if ($project_id == 0) {
        $query = "SELECT t.id, t.project_id, t.emp_id, t.task_name, t.description, t.due_date, t.priority, t.status, t.completed_at, t.created_at, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE (t.project_id = 0 OR t.project_id IS NULL) AND t.emp_id = $emp_id AND t.deleted_at IS NULL ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.id DESC";
    } else {
        $query = "SELECT t.id, t.project_id, t.emp_id, t.task_name, t.description, t.due_date, t.priority, t.status, t.completed_at, t.created_at, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.project_id = $project_id AND t.emp_id = $emp_id AND t.deleted_at IS NULL ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.id DESC";
    }
} else {
    if ($project_id == 0) {
        $query = "SELECT t.id, t.project_id, t.emp_id, t.task_name, t.description, t.due_date, t.priority, t.status, t.completed_at, t.created_at, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.emp_id = $emp_id AND t.deleted_at IS NULL ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.id DESC";
    } else {
        $query = "SELECT t.id, t.project_id, t.emp_id, t.task_name, t.description, t.due_date, t.priority, t.status, t.completed_at, t.created_at, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.project_id = $project_id AND t.emp_id = $emp_id AND t.deleted_at IS NULL ORDER BY t.status ASC, CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.id DESC";
    }
}

$result = mysqli_query($con, $query);

if (!$result) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Query error: ' . mysqli_error($con)]);
    exit();
}

$tasks = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tasks[] = $row;
}

ob_end_clean();
echo json_encode(['success' => true, 'tasks' => $tasks]);
