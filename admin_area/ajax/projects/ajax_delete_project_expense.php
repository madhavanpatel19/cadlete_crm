<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

$expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
if ($expense_id <= 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid Expense ID']);
    exit();
}

$del_q = "DELETE FROM project_expenses WHERE id = $expense_id";
if (mysqli_query($con, $del_q)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => 'Expense deleted successfully.']);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
}
