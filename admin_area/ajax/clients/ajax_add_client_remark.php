<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
if (!isset($con)) { include(__DIR__ . '/../../includes/db.php'); }

$response = ['success' => false];

if (isset($_POST['project_id']) && isset($_POST['remark'])) {
    $project_id = intval($_POST['project_id']);
    $remark = mysqli_real_escape_string($con, $_POST['remark']);

    // Ensure posted_by column exists
    try {
        @mysqli_query($con, "ALTER TABLE client_project_remarks ADD COLUMN posted_by VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}

    // Determine who is posting the update
    $posted_by = 'System';
    if (!empty($_SESSION['emp_name'])) {
        $posted_by = $_SESSION['emp_name'];
    } elseif (!empty($_SESSION['admin_name'])) {
        $posted_by = $_SESSION['admin_name'];
    } elseif (!empty($_SESSION['admin_email'])) {
        $posted_by = 'Admin';
    }

    $safe_posted_by = mysqli_real_escape_string($con, $posted_by);

    if (!empty($remark)) {
        $insert = "INSERT INTO client_project_remarks (project_id, remark, posted_by) VALUES ($project_id, '$remark', '$safe_posted_by')";
        if (mysqli_query($con, $insert)) {
            $response['success'] = true;
            $response['posted_by'] = $posted_by;
        }
    }
}

echo json_encode($response);
?>
