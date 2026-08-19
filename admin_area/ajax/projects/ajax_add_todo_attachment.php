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
if (!$task_id) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

if (!isset($_FILES['attachment_file']) || $_FILES['attachment_file']['error'] !== UPLOAD_ERR_OK) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'No file uploaded or file upload error']);
    exit();
}

$orig_name = $_FILES['attachment_file']['name'];
$file_tmp  = $_FILES['attachment_file']['tmp_name'];
$file_size_bytes = $_FILES['attachment_file']['size'];

// Format size
if ($file_size_bytes >= 1048576) {
    $size_str = round($file_size_bytes / 1048576, 1) . ' MB';
} else if ($file_size_bytes >= 1024) {
    $size_str = round($file_size_bytes / 1024, 1) . ' KB';
} else {
    $size_str = $file_size_bytes . ' B';
}

$file_ext = pathinfo($orig_name, PATHINFO_EXTENSION);
$new_file_name = 'task_desc_doc_' . time() . '_' . rand(100, 999) . '.' . $file_ext;

$upload_dir = __DIR__ . '/../../uploads/todo_docs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$target_file = $upload_dir . $new_file_name;

if (move_uploaded_file($file_tmp, $target_file)) {
    $file_path = 'uploads/todo_docs/' . $new_file_name;
    $orig_esc  = mysqli_real_escape_string($con, $orig_name);
    $path_esc  = mysqli_real_escape_string($con, $file_path);
    $size_esc  = mysqli_real_escape_string($con, $size_str);

    $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $admin = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email='$ae' LIMIT 1"));
    $admin_id_val = $admin ? intval($admin['admin_id']) : 'NULL';

    $insert_q = "INSERT INTO project_todo_attachments (task_id, file_name, file_path, file_size, uploaded_by_admin, created_at) VALUES ($task_id, '$orig_esc', '$path_esc', '$size_esc', $admin_id_val, NOW())";

    if (mysqli_query($con, $insert_q)) {
        $new_id = mysqli_insert_id($con);
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'attachment' => [
                'id' => $new_id,
                'file_name' => $orig_name,
                'file_path' => $file_path,
                'file_size' => $size_str,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit();
    } else {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        exit();
    }
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit();
}
