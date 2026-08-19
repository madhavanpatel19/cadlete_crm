<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) include(__DIR__ . '/../../includes/db.php');

header('Content-Type: application/json');

$is_admin = isset($_SESSION['admin_email']);
$is_emp = isset($_SESSION['emp_id']);

if (!$is_admin && !$is_emp) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = intval($_POST['task_id'] ?? 0);
$comment  = trim($_POST['comment'] ?? '');

if (!$task_id || empty($comment)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$esc = mysqli_real_escape_string($con, $comment);
$admin_id_val = "NULL";
$emp_id_val = "NULL";
$author_name = "User";
$author_emp_id = 0;
$author_admin_id = 0;

$posted_by = trim($_POST['posted_by'] ?? '');

if ($is_emp && ($posted_by === 'employee' || !$is_admin)) {
    $author_emp_id = intval($_SESSION['emp_id']);
    $emp_id_val = $author_emp_id;
    $emp = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM emp_list WHERE id={$author_emp_id} LIMIT 1"));
    if ($emp) {
        $author_name = $emp['name'];
    }
} else if ($is_admin) {
    $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $admin = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id, admin_name FROM admins WHERE admin_email='$ae' LIMIT 1"));
    if ($admin) {
        $author_admin_id = intval($admin['admin_id']);
        $admin_id_val = $author_admin_id;
        $author_name = $admin['admin_name'];
    }
}

$attachment_path = "NULL";
$attachment_name_esc = "NULL";

if (isset($_FILES['comment_file']) && $_FILES['comment_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['comment_file']['tmp_name'];
    $orig_name = $_FILES['comment_file']['name'];
    $file_ext = pathinfo($orig_name, PATHINFO_EXTENSION);
    $new_file_name = 'comment_doc_' . time() . '_' . rand(100, 999) . '.' . $file_ext;

    $upload_dir = __DIR__ . '/../../uploads/todo_docs/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $target_file = $upload_dir . $new_file_name;
    if (move_uploaded_file($file_tmp, $target_file)) {
        $attachment_path = "'uploads/todo_docs/" . mysqli_real_escape_string($con, $new_file_name) . "'";
        $attachment_name_esc = "'" . mysqli_real_escape_string($con, $orig_name) . "'";
    }
}

$insert_q = "INSERT INTO project_todo_comments (task_id, admin_id, emp_id, comment, attachment, attachment_name, created_at) VALUES ($task_id, $admin_id_val, $emp_id_val, '$esc', $attachment_path, $attachment_name_esc, NOW())";
if (mysqli_query($con, $insert_q)) {
    $new_id = mysqli_insert_id($con);

    require_once __DIR__ . '/../../includes/notification_helper.php';
    $t_res = mysqli_query($con, "SELECT t.task_name, t.emp_id, t.project_id, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.id = $task_id LIMIT 1");
    if ($t_res && $t_row = mysqli_fetch_assoc($t_res)) {
        $task_name = $t_row['task_name'] ?? 'Task';
        $p_name = $t_row['project_name'] ?? 'Project';
        $pid = intval($t_row['project_id']);
        $task_emp_id = intval($t_row['emp_id']);
        $url = "index.php?team_todo&project_id=$pid&open_task_id=$task_id&emp_id=$task_emp_id";
        $comment_snippet = (strlen($comment) > 40) ? substr($comment, 0, 40) . '...' : $comment;

        notifyProjectTeamAndAdmins($pid, "New Comment: $task_name", "$author_name commented: \"$comment_snippet\" on '$task_name' in $p_name.", $url, 'comment_added', $author_emp_id, $author_admin_id);
    }

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'comment' => [
            'id' => $new_id,
            'comment' => $comment,
            'author_name' => $author_name,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
}
