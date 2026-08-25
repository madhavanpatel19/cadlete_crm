<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$emp_id = isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0;
$extra_leaves = isset($_POST['extra_leaves']) ? intval($_POST['extra_leaves']) : 0;

if ($emp_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid inputs provided.']);
    exit;
}

if ($extra_leaves < 0) {
    $extra_leaves = 0;
}

// Auto-migration check: extra_leaves column in emp_list
$checkCol = @mysqli_query($con, "SHOW COLUMNS FROM emp_list LIKE 'extra_leaves'");
if ($checkCol && mysqli_num_rows($checkCol) == 0) {
    @mysqli_query($con, "ALTER TABLE emp_list ADD COLUMN extra_leaves INT(11) DEFAULT 0");
}

$update = mysqli_query($con, "UPDATE emp_list SET extra_leaves = $extra_leaves WHERE id = $emp_id");

if ($update) {
    echo json_encode([
        'status' => 'success',
        'emp_id' => $emp_id,
        'extra_leaves' => $extra_leaves,
        'message' => 'Extra leave quota updated successfully!'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
}
?>
