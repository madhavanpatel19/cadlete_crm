<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_email'])) {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

$today = date('Y-m-d');
$response = [];

$q = "SELECT a.*, e.name, e.employee_image 
      FROM attendance a 
      JOIN emp_list e ON a.emp_id = e.id 
      WHERE a.attendance_date = '$today'
      ORDER BY a.check_in_time DESC";
$res = mysqli_query($con, $q);

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $has_in = !empty($row['check_in_time']) && $row['check_in_time'] !== '00:00:00';
        $st_txt = 'Not Checked In';
        $badge_bg = '#f1f5f9';
        $badge_color = '#64748b';

        if ($has_in) {
            if ((int)$row['is_working'] === 1) {
                $badge_bg = '#dbeafe';
                $badge_color = '#2563eb';
                $st_txt = 'Working';
            } elseif (!empty($row['check_out_time']) && $row['check_out_time'] !== '00:00:00') {
                $badge_bg = '#dcfce7';
                $badge_color = '#16a34a';
                $st_txt = 'Completed';
            } elseif (strtolower($row['status']) === 'late') {
                $badge_bg = '#ffeaeb';
                $badge_color = '#dd2127';
                $st_txt = 'Late';
            } elseif (strtolower($row['status']) === 'absent') {
                $badge_bg = '#fee2e2';
                $badge_color = '#dc2626';
                $st_txt = 'Absent';
            } elseif (strtolower($row['status']) === 'leave') {
                $badge_bg = '#fef3c7';
                $badge_color = '#d97706';
                $st_txt = 'Leave';
            } else {
                $badge_bg = '#fef3c7';
                $badge_color = '#d97706';
                $st_txt = 'Present';
            }
        }

        $check_in_fmt = (!empty($row['check_in_time']) && $row['check_in_time'] !== '00:00:00') ? date('h:i A', strtotime($row['check_in_time'])) : '-';
        $check_out_fmt = (!empty($row['check_out_time']) && $row['check_out_time'] !== '00:00:00') ? date('h:i A', strtotime($row['check_out_time'])) : '-';

        $has_img = !empty($row['employee_image']) && file_exists(__DIR__ . '/../../uploads/' . $row['employee_image']);
        $e_img = $has_img ? 'uploads/' . $row['employee_image'] : '';
        $first_letter = strtoupper(substr(trim($row['name']), 0, 1));

        $response[$row['emp_id']] = [
            'emp_id' => (int)$row['emp_id'],
            'name' => htmlspecialchars($row['name']),
            'has_img' => $has_img,
            'image' => $e_img,
            'first_letter' => $first_letter,
            'is_working' => (int)$row['is_working'],
            'last_resume' => $row['last_resume_time'] ? date('Y-m-d\TH:i:s', strtotime($row['last_resume_time'])) : '',
            'check_in' => $row['check_in_time'] ? date('Y-m-d\TH:i:s', strtotime($today . ' ' . $row['check_in_time'])) : '',
            'check_in_fmt' => $check_in_fmt,
            'check_out_fmt' => $check_out_fmt,
            'total_secs' => (int)$row['total_duration_secs'],
            'status' => $st_txt,
            'badge_bg' => $badge_bg,
            'badge_color' => $badge_color
        ];
    }
}

echo json_encode($response);
?>
