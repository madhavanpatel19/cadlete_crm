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

$attachment_id = intval($_POST['attachment_id'] ?? 0);
if (!$attachment_id) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid attachment ID']);
    exit();
}

$now = date('Y-m-d H:i:s');
$q = "UPDATE project_todo_attachments SET deleted_at = '$now' WHERE id = $attachment_id AND deleted_at IS NULL";
if (mysqli_query($con, $q)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true]);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
}
