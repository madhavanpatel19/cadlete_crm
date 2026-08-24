<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

$raw_id = null;
if (isset($_POST['industry_id']) && $_POST['industry_id'] !== '') {
    $raw_id = $_POST['industry_id'];
} elseif (isset($_GET['industry_id']) && $_GET['industry_id'] !== '') {
    $raw_id = $_GET['industry_id'];
} elseif (isset($_REQUEST['industry_id']) && $_REQUEST['industry_id'] !== '') {
    $raw_id = $_REQUEST['industry_id'];
} elseif (isset($_POST['id']) && $_POST['id'] !== '') {
    $raw_id = $_POST['id'];
} elseif (isset($_GET['id']) && $_GET['id'] !== '') {
    $raw_id = $_GET['id'];
} elseif (isset($_REQUEST['id']) && $_REQUEST['id'] !== '') {
    $raw_id = $_REQUEST['id'];
}

if ($raw_id !== null && trim($raw_id) !== '') {
    $industry_id = intval($raw_id);
    if ($industry_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Industry ID provided.']);
        exit;
    }
    $now = date('Y-m-d H:i:s');

    $update = mysqli_query($con, "UPDATE client_industries SET deleted_at = '$now' WHERE id = $industry_id");

    if ($update) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Industry ID missing.']);
}
