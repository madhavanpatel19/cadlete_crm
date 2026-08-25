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
$leave_type_id = isset($_POST['leave_type_id']) ? intval($_POST['leave_type_id']) : 0;
$leave_from = isset($_POST['leave_from']) ? trim($_POST['leave_from']) : '';
$leave_to = isset($_POST['leave_to']) ? trim($_POST['leave_to']) : '';
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
$status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : 'approved';

if ($emp_id <= 0 || empty($leave_from) || empty($leave_to) || $leave_type_id < 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required leave fields.']);
    exit;
}

$reason_esc = mysqli_real_escape_string($con, $reason);
$status_esc = mysqli_real_escape_string($con, $status);

$insert = "INSERT INTO leave_applications (emp_id, leave_type_id, leave_from, leave_to, reason, status) 
           VALUES ($emp_id, $leave_type_id, '$leave_from', '$leave_to', '$reason_esc', '$status_esc')";

if (mysqli_query($con, $insert)) {
    $app_id = mysqli_insert_id($con);

    if ($status_esc === 'approved') {
        $start_date = new DateTime($leave_from);
        $end_date = new DateTime($leave_to);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $check = mysqli_query($con, "SELECT id, check_in_time FROM attendance WHERE emp_id = $emp_id AND attendance_date = '$current_date'");
            if (mysqli_num_rows($check) > 0) {
                $existing_att = mysqli_fetch_assoc($check);
                if (!empty($existing_att['check_in_time'])) {
                    continue;
                }
                mysqli_query($con, "UPDATE attendance SET status = 'leave', remarks = 'Leave: $reason_esc' WHERE emp_id = $emp_id AND attendance_date = '$current_date'");
            } else {
                mysqli_query($con, "INSERT INTO attendance (emp_id, attendance_date, status, remarks) VALUES ($emp_id, '$current_date', 'leave', 'Leave: $reason_esc')");
            }
        }
    }

    // Send system notification to employee
    if (file_exists(__DIR__ . '/../../includes/notification_helper.php')) {
        include_once(__DIR__ . '/../../includes/notification_helper.php');
        if (function_exists('addSystemNotification')) {
            $notif_title = "New Leave Record Added";
            $notif_msg = "Admin added a leave record (" . date('d M Y', strtotime($leave_from)) . " to " . date('d M Y', strtotime($leave_to)) . ") for you.";
            $notif_url = "index.php?leave_application";
            addSystemNotification('employee', intval($emp_id), $notif_title, $notif_msg, $notif_url, 'info');
        }
    }

    echo json_encode([
        'status' => 'success',
        'app_id' => $app_id,
        'message' => 'Leave record added successfully!'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
}
?>
