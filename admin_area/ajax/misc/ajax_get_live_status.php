<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (session_status() == PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($_SESSION['admin_email'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$today = date('Y-m-d');
$response = [];

$q = "SELECT emp_id, is_working, last_resume_time, check_in_time, check_out_time, total_duration_secs, status 
      FROM attendance 
      WHERE attendance_date = '$today'";
$res = mysqli_query($con, $q);

while ($row = mysqli_fetch_assoc($res)) {
    $has_in = !empty($row['check_in_time']) && $row['check_in_time'] !== '00:00:00';
    $st_txt = 'Not Checked In';
    if ($has_in) {
        if ((int)$row['is_working'] === 1) {
            $st_txt = 'Working';
        } elseif (!empty($row['check_out_time']) && $row['check_out_time'] !== '00:00:00') {
            $st_txt = 'Completed';
        } else {
            $st_txt = ucfirst($row['status']);
        }
    }
    $response[$row['emp_id']] = [
        'is_working' => (int)$row['is_working'],
        'last_resume' => $row['last_resume_time'] ? date('Y-m-d\TH:i:s', strtotime($row['last_resume_time'])) : '',
        'check_in' => $row['check_in_time'] ? date('Y-m-d\TH:i:s', strtotime($today . ' ' . $row['check_in_time'])) : '',
        'total_secs' => (int)$row['total_duration_secs'],
        'status' => $st_txt
    ];
}

echo json_encode($response);
