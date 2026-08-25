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

$app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;

if ($app_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid leave application ID.']);
    exit;
}

// Fetch leave details to clear attendance records if approved
$get_leave = mysqli_query($con, "SELECT * FROM leave_applications WHERE id = $app_id LIMIT 1");
if ($get_leave && mysqli_num_rows($get_leave) > 0) {
    $leave_row = mysqli_fetch_assoc($get_leave);
    $emp_id = intval($leave_row['emp_id']);
    $from = $leave_row['leave_from'];
    $to = $leave_row['leave_to'];
    $status = strtolower(trim($leave_row['status'] ?? ''));

    if ($status === 'approved' && !empty($from) && !empty($to)) {
        $start_date = new DateTime($from);
        $end_date = new DateTime($to);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            mysqli_query($con, "DELETE FROM attendance WHERE emp_id = $emp_id AND attendance_date = '$current_date' AND status = 'leave' AND (check_in_time IS NULL OR check_in_time = '')");
        }
    }
}

$delete = mysqli_query($con, "DELETE FROM leave_applications WHERE id = $app_id");

if ($delete) {
    echo json_encode(['status' => 'success', 'message' => 'Leave application deleted successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
}
?>
