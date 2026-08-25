<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

// Auto-migrate attendance status column from ENUM to VARCHAR(20) if needed
$checkAttCol = @mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'status'");
if ($checkAttCol && $attCol = mysqli_fetch_assoc($checkAttCol)) {
    if (strpos(strtolower($attCol['Type']), 'enum') !== false) {
        @mysqli_query($con, "ALTER TABLE attendance MODIFY COLUMN status VARCHAR(20) DEFAULT 'present'");
        @mysqli_query($con, "UPDATE attendance SET status = 'leave' WHERE (remarks LIKE 'Leave:%' OR remarks LIKE '%leave%') AND (check_in_time IS NULL OR check_in_time = '')");
    }
}

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;
$new_status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';

if ($app_id <= 0 || !in_array($new_status, ['approved', 'pending', 'rejected'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
    exit;
}

// Fetch existing leave application details
$get_leave = mysqli_query($con, "SELECT * FROM leave_applications WHERE id = $app_id LIMIT 1");
if (!$get_leave || mysqli_num_rows($get_leave) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Leave application not found.']);
    exit;
}

$leave_row = mysqli_fetch_assoc($get_leave);
$emp_id = intval($leave_row['emp_id']);
$from = $leave_row['leave_from'];
$to = $leave_row['leave_to'];
$reason = mysqli_real_escape_string($con, $leave_row['reason'] ?? '');

$update = mysqli_query($con, "UPDATE leave_applications SET status = '$new_status' WHERE id = $app_id");

if ($update) {
    // Attendance synchronization logic
    if ($new_status === 'approved') {
        $start_date = new DateTime($from);
        $end_date = new DateTime($to);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            $check = mysqli_query($con, "SELECT id, check_in_time FROM attendance WHERE emp_id = $emp_id AND attendance_date = '$current_date'");
            if (mysqli_num_rows($check) > 0) {
                $existing_att = mysqli_fetch_assoc($check);
                if (!empty($existing_att['check_in_time'])) {
                    continue; // Skip if employee checked in
                }
                mysqli_query($con, "UPDATE attendance SET status = 'leave', remarks = 'Leave: $reason' WHERE emp_id = $emp_id AND attendance_date = '$current_date'");
            } else {
                mysqli_query($con, "INSERT INTO attendance (emp_id, attendance_date, status, remarks) VALUES ($emp_id, '$current_date', 'leave', 'Leave: $reason')");
            }
        }
    } else {
        // Remove leave attendance records if changed from approved to pending/rejected
        $start_date = new DateTime($from);
        $end_date = new DateTime($to);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));

        foreach ($period as $date) {
            $current_date = $date->format('Y-m-d');
            mysqli_query($con, "DELETE FROM attendance WHERE emp_id = $emp_id AND attendance_date = '$current_date' AND status = 'leave' AND (check_in_time IS NULL OR check_in_time = '')");
        }
    }

    // Send notification to employee
    if (file_exists(__DIR__ . '/../../includes/notification_helper.php')) {
        include_once(__DIR__ . '/../../includes/notification_helper.php');
        if (function_exists('addSystemNotification')) {
            $notif_title = "Leave Request " . ucfirst($new_status);
            $notif_msg = "Your leave request (" . date('d M Y', strtotime($from)) . " to " . date('d M Y', strtotime($to)) . ") has been " . $new_status . ".";
            $notif_url = "index.php?leave_application";
            $notif_type = ($new_status === 'approved' ? 'success' : 'danger');
            addSystemNotification('employee', intval($emp_id), $notif_title, $notif_msg, $notif_url, $notif_type);
        }
    }

    echo json_encode([
        'status' => 'success',
        'app_id' => $app_id,
        'new_status' => $new_status,
        'message' => 'Leave application status updated to ' . ucfirst($new_status)
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
}
