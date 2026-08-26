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

$from_ts = strtotime($leave_from);
$to_ts   = strtotime($leave_to);

if ($to_ts < $from_ts) {
    echo json_encode(['status' => 'error', 'message' => "'To Date' cannot be earlier than 'From Date'."]);
    exit;
}

$requested_days = (int)(($to_ts - $from_ts) / 86400) + 1;

// Check overlap
$overlap_q = mysqli_query($con, "SELECT id FROM leave_applications WHERE emp_id = $emp_id AND status IN ('approved', 'pending') AND (leave_from <= '$leave_to' AND leave_to >= '$leave_from') LIMIT 1");
if ($overlap_q && mysqli_num_rows($overlap_q) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Employee already has a pending or approved leave record for overlapping dates.']);
    exit;
}

// Check remaining balance
$remaining_leaves = 0;
$leave_type_name  = "Extra Leaves";

if ($leave_type_id == 0) {
    $extra_assigned = 0;
    $chk_ex = mysqli_query($con, "SELECT extra_leaves FROM emp_list WHERE id = $emp_id LIMIT 1");
    if ($chk_ex && $ex_r = mysqli_fetch_assoc($chk_ex)) {
        $extra_assigned = intval($ex_r['extra_leaves'] ?? 0);
    }
    $ex_used = 0;
    $ex_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = $emp_id AND (leave_type_id = 0 OR leave_type_id IS NULL) AND status IN ('approved', 'pending')");
    if ($ex_q) {
        while ($ex_r = mysqli_fetch_assoc($ex_q)) {
            $ef = strtotime($ex_r['leave_from']);
            $et = strtotime($ex_r['leave_to']);
            if ($ef && $et && $et >= $ef) {
                $ex_used += (int)(($et - $ef) / 86400) + 1;
            }
        }
    }
    $remaining_leaves = max(0, $extra_assigned - $ex_used);
} else {
    $lt_q = mysqli_query($con, "SELECT leave_name, num_of_leave FROM leave_types WHERE id = $leave_type_id AND deleted_at IS NULL LIMIT 1");
    if ($lt_q && $lt_r = mysqli_fetch_assoc($lt_q)) {
        $leave_type_name = $lt_r['leave_name'];
        $allowed = intval($lt_r['num_of_leave']);
        $type_used = 0;
        $t_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = $emp_id AND leave_type_id = $leave_type_id AND status IN ('approved', 'pending')");
        if ($t_q) {
            while ($t_r = mysqli_fetch_assoc($t_q)) {
                $tf = strtotime($t_r['leave_from']);
                $tt = strtotime($t_r['leave_to']);
                if ($tf && $tt && $tt >= $tf) {
                    $type_used += (int)(($tt - $tf) / 86400) + 1;
                }
            }
        }
        $remaining_leaves = max(0, $allowed - $type_used);
    }
}

if ($requested_days > $remaining_leaves) {
    echo json_encode(['status' => 'error', 'message' => "Requested $requested_days day(s) for $leave_type_name, but employee only has $remaining_leaves day(s) remaining."]);
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
