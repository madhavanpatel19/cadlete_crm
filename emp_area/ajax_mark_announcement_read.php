<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$ann_id = isset($_POST['announcement_id']) ? intval($_POST['announcement_id']) : 0;
$emp_id = intval($_SESSION['emp_id']);

if ($ann_id > 0) {
    $check_read = "SELECT id FROM announcement_read WHERE announcement_id='$ann_id' AND emp_id='$emp_id'";
    $run_check  = mysqli_query($con, $check_read);
    if ($run_check && mysqli_num_rows($run_check) == 0) {
        $insert_read = "INSERT INTO announcement_read (announcement_id, emp_id) VALUES ('$ann_id', '$emp_id')";
        if (mysqli_query($con, $insert_read)) {
            echo json_encode(['success' => true]);
            exit();
        }
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
}
