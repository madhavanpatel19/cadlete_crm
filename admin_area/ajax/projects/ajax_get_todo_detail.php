<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) include(__DIR__ . '/../../includes/db.php');

$is_admin = isset($_SESSION['admin_email']);
$is_emp = isset($_SESSION['emp_id']);

if (!$is_admin && !$is_emp) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$task_id = intval($_POST['task_id'] ?? 0);
if (!$task_id) {
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid task']);
    exit();
}

$task = mysqli_fetch_assoc(mysqli_query($con, "SELECT t.*, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.id = $task_id AND t.deleted_at IS NULL LIMIT 1"));
if (!$task) {
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Task not found']);
    exit();
}

// Find all related task IDs with the same task_name in the same project (common task siblings)
$task_name_esc = mysqli_real_escape_string($con, $task['task_name']);
$project_id_esc = intval($task['project_id']);

$sibling_ids = [$task_id]; // always include current task

if ($project_id_esc > 0) {
    $sr = mysqli_query($con, "SELECT id FROM project_team_todos WHERE project_id = $project_id_esc AND task_name = '$task_name_esc' AND deleted_at IS NULL");
    if ($sr) {
        while ($srow = mysqli_fetch_assoc($sr)) {
            $sibling_ids[] = intval($srow['id']);
        }
        $sibling_ids = array_unique($sibling_ids);
    }
}

$ids_in = implode(',', $sibling_ids);

// Auto-migrate tables/columns if not existing
@mysqli_query($con, "CREATE TABLE IF NOT EXISTS project_todo_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size VARCHAR(50) DEFAULT NULL,
    uploaded_by_admin INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$check_col = @mysqli_query($con, "SHOW COLUMNS FROM project_todo_comments LIKE 'attachment'");
if ($check_col && mysqli_num_rows($check_col) == 0) {
    @mysqli_query($con, "ALTER TABLE project_todo_comments ADD COLUMN attachment VARCHAR(255) DEFAULT NULL, ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL");
}

// Fetch description attachments
$ar = mysqli_query($con, "SELECT * FROM project_todo_attachments WHERE task_id IN ($ids_in) AND deleted_at IS NULL ORDER BY id DESC");
$attachments = [];
if ($ar) {
    while ($row = mysqli_fetch_assoc($ar)) {
        $attachments[] = $row;
    }
}

// Fetch combined comments from all related tasks, including author info
$cr = mysqli_query($con, "
    SELECT c.*, 
           a.admin_name,
           ea.name as comment_author_emp_name,
           e.name as task_emp_name,
           t.emp_id as task_emp_id
    FROM project_todo_comments c
    LEFT JOIN admins a ON c.admin_id = a.admin_id
    LEFT JOIN emp_list ea ON c.emp_id = ea.id
    LEFT JOIN project_team_todos t ON c.task_id = t.id
    LEFT JOIN emp_list e ON t.emp_id = e.id
    WHERE c.task_id IN ($ids_in) AND c.deleted_at IS NULL
    ORDER BY c.created_at DESC, c.id DESC
");

$comments = [];
while ($row = mysqli_fetch_assoc($cr)) {
    if (!empty($row['comment_author_emp_name'])) {
        // Posted by Employee
        $row['author_name'] = $row['comment_author_emp_name'];
        $row['is_emp_author'] = true;
        $row['emp_tag_name'] = '';
    } else {
        // Posted by Admin
        $row['author_name'] = !empty($row['admin_name']) ? $row['admin_name'] : 'Admin';
        $row['is_emp_author'] = false;
        $row['emp_tag_name'] = !empty($row['task_emp_name']) ? $row['task_emp_name'] : '';
    }
    $comments[] = $row;
}

if (ob_get_length()) ob_end_clean();
echo json_encode(['success' => true, 'task' => $task, 'attachments' => $attachments, 'comments' => $comments, 'is_admin' => $is_admin]);
