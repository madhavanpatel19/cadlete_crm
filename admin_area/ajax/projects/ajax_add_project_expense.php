<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;
$cost = isset($_POST['cost']) ? floatval($_POST['cost']) : 0.00;
$expense_date = isset($_POST['expense_date']) && !empty($_POST['expense_date']) ? trim($_POST['expense_date']) : date('Y-m-d');
$ordered_from = isset($_POST['ordered_from']) ? trim($_POST['ordered_from']) : '';
$ordered_from_url = isset($_POST['ordered_from_url']) ? trim($_POST['ordered_from_url']) : '';
$paid_by = isset($_POST['paid_by']) ? trim($_POST['paid_by']) : '';
$invoice_no = isset($_POST['invoice_no']) ? trim($_POST['invoice_no']) : '';

if ($project_id <= 0 || empty($item_name)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Project ID and Item Name are required.']);
    exit();
}

$qty = $qty > 0 ? $qty : 1;
$total_cost = $qty * $cost;

$upload_dir = __DIR__ . '/../../uploads/expense_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$invoice_file = '';
if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
    $fn = $_FILES['invoice_file']['name'];
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    $new_fn = 'inv_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($_FILES['invoice_file']['tmp_name'], $upload_dir . $new_fn)) {
        $invoice_file = 'uploads/expense_files/' . $new_fn;
    }
}

$attachment = '';
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $afn = $_FILES['attachment']['name'];
    $aext = strtolower(pathinfo($afn, PATHINFO_EXTENSION));
    $new_afn = 'att_' . time() . '_' . rand(100, 999) . '.' . $aext;
    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_afn)) {
        $attachment = 'uploads/expense_files/' . $new_afn;
    }
}

$item_name_esc = mysqli_real_escape_string($con, $item_name);
$exp_date_esc = mysqli_real_escape_string($con, $expense_date);
$ordered_from_esc = mysqli_real_escape_string($con, $ordered_from);
$ordered_url_esc = mysqli_real_escape_string($con, $ordered_from_url);
$paid_by_esc = mysqli_real_escape_string($con, $paid_by);
$invoice_no_esc = mysqli_real_escape_string($con, $invoice_no);
$invoice_file_esc = mysqli_real_escape_string($con, $invoice_file);
$attachment_esc = mysqli_real_escape_string($con, $attachment);

$sql = "INSERT INTO project_expenses 
        (project_id, item_name, qty, cost, total_cost, expense_date, ordered_from, ordered_from_url, paid_by, invoice_no, invoice_file, attachment, created_at) 
        VALUES 
        ($project_id, '$item_name_esc', $qty, $cost, $total_cost, '$exp_date_esc', '$ordered_from_esc', '$ordered_url_esc', '$paid_by_esc', '$invoice_no_esc', '$invoice_file_esc', '$attachment_esc', NOW())";

if (mysqli_query($con, $sql)) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => 'Expense added successfully.']);
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
}
