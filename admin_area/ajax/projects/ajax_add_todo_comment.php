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

$posted_by = trim($_POST['posted_by'] ?? '');

if ($posted_by === 'employee' || ($is_emp && !$is_admin)) {
    if ($is_emp) {
        $emp_id_val = intval($_SESSION['emp_id']);
    } else {
        $t = mysqli_fetch_assoc(mysqli_query($con, "SELECT emp_id FROM project_team_todos WHERE id = $task_id LIMIT 1"));
        if ($t && !empty($t['emp_id'])) {
            $emp_id_val = intval($t['emp_id']);
        }
    }
    if ($emp_id_val !== "NULL" && $emp_id_val > 0) {
        $emp = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM emp_list WHERE id={$emp_id_val} LIMIT 1"));
        if ($emp) {
            $author_name = $emp['name'];
        }
    }
} else if ($is_admin) {
    $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $admin = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id, admin_name FROM admins WHERE admin_email='$ae' LIMIT 1"));
    if ($admin) {
        $admin_id_val = intval($admin['admin_id']);
        $author_name = $admin['admin_name'];
    }
}

$insert_q = "INSERT INTO project_todo_comments (task_id, admin_id, emp_id, comment, created_at) VALUES ($task_id, $admin_id_val, $emp_id_val, '$esc', NOW())";
if (mysqli_query($con, $insert_q)) {
    $new_id = mysqli_insert_id($con);
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
