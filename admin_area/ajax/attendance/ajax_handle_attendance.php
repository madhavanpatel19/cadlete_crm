<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit();
}

$emp_id       = $_SESSION['emp_id'];
$action       = isset($_POST['action']) ? $_POST['action'] : '';
$today        = date('Y-m-d');
$current_time = date('H:i:s');
$now_dt       = date('Y-m-d H:i:s');
$formatted_time = date('h:i A', strtotime($current_time));

if (empty($_POST) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Uploaded files are too large. Please reduce the size of your photos.']);
    exit();
}

// ── Ensure ip_address / location columns exist ────────────────
try {
    @mysqli_query($con, "ALTER TABLE attendance ADD COLUMN ip_address VARCHAR(50) DEFAULT NULL");
} catch (Exception $e) {
}
try {
    @mysqli_query($con, "ALTER TABLE attendance ADD COLUMN location VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
}
try {
    @mysqli_query($con, "ALTER TABLE attendance ADD COLUMN remarks TEXT DEFAULT NULL");
} catch (Exception $e) {
}
try {
    @mysqli_query($con, "ALTER TABLE attendance ADD COLUMN work_photos TEXT DEFAULT NULL");
} catch (Exception $e) {
}

// ── Ensure attendance_logs table exists ───────────────────────
try {
    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS attendance_logs (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        att_id      INT NOT NULL,
        emp_id      INT NOT NULL,
        action      VARCHAR(20) NOT NULL,
        action_time DATETIME NOT NULL,
        ip_address  VARCHAR(50) DEFAULT NULL,
        location    VARCHAR(255) DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (att_id)
    )");
} catch (Exception $e) {
}

// ── Helper: get real visitor IP ───────────────────────────────
function getVisitorIP()
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return 'Unknown';
}

// ── Helper: geolocate IP (free, no API key) ───────────────────
function geolocateIP($ip)
{
    if (in_array($ip, ['127.0.0.1', '::1']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return 'Local Network';
    }
    $url  = 'http://ip-api.com/json/' . $ip . '?fields=city,regionName,country,status';
    $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if ($data && $data['status'] === 'success') {
            $parts = array_filter([$data['city'] ?? '', $data['regionName'] ?? '', $data['country'] ?? '']);
            return implode(', ', $parts);
        }
    }
    return 'Unknown';
}

// ── Helper: insert action into attendance_logs ────────────────
function logAttendanceAction($con, $att_id, $emp_id, $action, $action_time, $ip, $location)
{
    $safe_ip  = mysqli_real_escape_string($con, $ip);
    $safe_loc = mysqli_real_escape_string($con, $location);
    $safe_dt  = mysqli_real_escape_string($con, $action_time);
    $safe_act = mysqli_real_escape_string($con, $action);
    mysqli_query($con, "INSERT INTO attendance_logs (att_id, emp_id, action, action_time, ip_address, location)
                        VALUES ('$att_id', '$emp_id', '$safe_act', '$safe_dt', '$safe_ip', '$safe_loc')");
}

// ═══════════════════════════════════════════════════════════════
//  CHECK IN
// ═══════════════════════════════════════════════════════════════
if ($action == 'check_in') {
    $check_q   = "SELECT * FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today'";
    $check_res = mysqli_query($con, $check_q);

    $visitor_ip  = getVisitorIP();
    $visitor_loc = geolocateIP($visitor_ip);
    $safe_ip     = mysqli_real_escape_string($con, $visitor_ip);
    $safe_loc    = mysqli_real_escape_string($con, $visitor_loc);

    $late_cutoff = strtotime('1970-01-01 10:15:00');
    $tstamp = strtotime('1970-01-01 ' . $current_time);
    $status = 'present';
    if ($tstamp !== false && $tstamp > $late_cutoff) {
        $status = 'late';
    }

    if (mysqli_num_rows($check_res) > 0) {
        $row = mysqli_fetch_assoc($check_res);
        if (!empty($row['check_in_time'])) {
            echo json_encode(['status' => 'info', 'message' => 'Already checked in.', 'time' => date('h:i A', strtotime($row['check_in_time']))]);
            exit();
        }
        $update_q = "UPDATE attendance SET 
                     check_in_time = '$current_time', 
                     last_resume_time = '$now_dt', 
                     is_working = 1, 
                     total_duration_secs = 0, 
                     status = '$status',
                     ip_address = '$safe_ip',
                     location = '$safe_loc'
                     WHERE id = " . $row['id'];
        if (mysqli_query($con, $update_q)) {
            logAttendanceAction($con, $row['id'], $emp_id, 'check_in', $now_dt, $visitor_ip, $visitor_loc);
            echo json_encode(['status' => 'success', 'message' => 'Checked in!', 'time' => $formatted_time]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . mysqli_error($con)]);
        }
    } else {
        $insert_q = "INSERT INTO attendance (emp_id, attendance_date, check_in_time, last_resume_time, is_working, total_duration_secs, status, ip_address, location) 
                     VALUES ('$emp_id', '$today', '$current_time', '$now_dt', 1, 0, '$status', '$safe_ip', '$safe_loc')";
        if (mysqli_query($con, $insert_q)) {
            $att_id = mysqli_insert_id($con);
            logAttendanceAction($con, $att_id, $emp_id, 'check_in', $now_dt, $visitor_ip, $visitor_loc);
            echo json_encode(['status' => 'success', 'message' => 'Checked in!', 'time' => $formatted_time]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . mysqli_error($con)]);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PAUSE
    // ═══════════════════════════════════════════════════════════════
} elseif ($action == 'pause') {
    $check_q   = "SELECT * FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today' AND is_working = 1";
    $check_res = mysqli_query($con, $check_q);

    if (mysqli_num_rows($check_res) > 0) {
        $row         = mysqli_fetch_assoc($check_res);
        $last_resume = $row['last_resume_time'];
        $diff        = strtotime($now_dt) - strtotime($last_resume);

        $visitor_ip  = getVisitorIP();
        $visitor_loc = geolocateIP($visitor_ip);

        $update_q = "UPDATE attendance SET 
                     total_duration_secs = total_duration_secs + $diff, 
                     is_working = 0 
                     WHERE id = " . $row['id'];
        if (mysqli_query($con, $update_q)) {
            logAttendanceAction($con, $row['id'], $emp_id, 'pause', $now_dt, $visitor_ip, $visitor_loc);
            echo json_encode(['status' => 'success', 'message' => 'Timer paused.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not currently working.']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  RESUME
    // ═══════════════════════════════════════════════════════════════
} elseif ($action == 'resume') {
    $check_q   = "SELECT * FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today' AND is_working = 0 AND check_in_time IS NOT NULL AND check_out_time IS NULL";
    $check_res = mysqli_query($con, $check_q);

    if (mysqli_num_rows($check_res) > 0) {
        $row = mysqli_fetch_assoc($check_res);

        $visitor_ip  = getVisitorIP();
        $visitor_loc = geolocateIP($visitor_ip);
        $safe_ip     = mysqli_real_escape_string($con, $visitor_ip);
        $safe_loc    = mysqli_real_escape_string($con, $visitor_loc);

        $update_q = "UPDATE attendance SET last_resume_time = '$now_dt', is_working = 1 WHERE id = " . $row['id'];
        if (mysqli_query($con, $update_q)) {
            logAttendanceAction($con, $row['id'], $emp_id, 'resume', $now_dt, $visitor_ip, $visitor_loc);
            echo json_encode(['status' => 'success', 'message' => 'Timer resumed.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cannot resume. Check if you are already checked out.']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  CHECK OUT
    // ═══════════════════════════════════════════════════════════════
} elseif ($action == 'check_out') {
    $check_q   = "SELECT * FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today'";
    $check_res = mysqli_query($con, $check_q);

    if (mysqli_num_rows($check_res) > 0) {
        $row = mysqli_fetch_assoc($check_res);
        if (!empty($row['check_out_time'])) {
            echo json_encode(['status' => 'info', 'message' => 'Already checked out.', 'time' => date('h:i A', strtotime($row['check_out_time']))]);
            exit();
        }

        $work_details = isset($_POST['work_details']) ? mysqli_real_escape_string($con, $_POST['work_details']) : '';
        $manual_in    = isset($_POST['check_in_time'])  ? $_POST['check_in_time']  : '';
        $manual_out   = isset($_POST['check_out_time']) ? $_POST['check_out_time'] : '';

        $visitor_ip  = getVisitorIP();
        $visitor_loc = geolocateIP($visitor_ip);

        // Handle Work Photos Upload
        $existing_photos = !empty($row['work_photos']) ? json_decode($row['work_photos'], true) : [];
        if (!is_array($existing_photos)) $existing_photos = [];

        $uploaded_photos = $existing_photos;
        if (isset($_FILES['work_photos'])) {
            $files      = $_FILES['work_photos'];
            $upload_dir = '../../work_photos/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] == 0) {
                    $tmp_name = $files['tmp_name'][$i];
                    $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $new_name = $emp_id . '_' . $today . '_' . time() . '_' . $i . '.' . $ext;
                    $target   = $upload_dir . $new_name;
                    if (move_uploaded_file($tmp_name, $target)) $uploaded_photos[] = 'work_photos/' . $new_name;
                }
            }
        }
        $photos_json = !empty($uploaded_photos) ? mysqli_real_escape_string($con, json_encode(array_values(array_unique($uploaded_photos)))) : '';

        if (!empty($manual_in) && !empty($manual_out)) {
            $last_resume      = !empty($row['last_resume_time']) ? $row['last_resume_time'] : ($today . ' ' . $row['check_in_time']);
            $manual_out_dt    = $today . ' ' . $manual_out;
            $segment_duration = max(0, strtotime($manual_out_dt) - strtotime($last_resume));
            $base_timer       = ($row['is_working'] == 1) ? (int)$row['total_duration_secs'] : 0;
            $new_duration     = $base_timer + $segment_duration;

            $status = 'present';
            $tstamp = strtotime('1970-01-01 ' . $manual_in);
            $late_cutoff = strtotime('1970-01-01 10:15:00');
            if ($tstamp !== false && $tstamp > $late_cutoff) {
                $status = 'late';
            }

            $update_q = "UPDATE attendance SET 
                         check_in_time = '$manual_in',
                         check_out_time = '$manual_out', 
                         is_working = 0,
                         total_duration_secs = '$new_duration',
                         status = '$status',
                         remarks = '$work_details',
                         work_photos = '$photos_json'
                         WHERE id = " . $row['id'];
            $checkout_dt = $today . ' ' . $manual_out;
        } else {
            $duration_update = '';
            if ($row['is_working'] == 1) {
                $last_resume     = $row['last_resume_time'];
                $diff            = strtotime($now_dt) - strtotime($last_resume);
                $duration_update = ", total_duration_secs = total_duration_secs + $diff";
            }
            $update_q = "UPDATE attendance SET 
                         check_out_time = '$current_time', 
                         is_working = 0,
                         remarks = '$work_details',
                         work_photos = '$photos_json'
                         $duration_update 
                         WHERE id = " . $row['id'];
            $checkout_dt = $now_dt;
        }

        if (mysqli_query($con, $update_q)) {
            logAttendanceAction($con, $row['id'], $emp_id, 'check_out', $checkout_dt, $visitor_ip, $visitor_loc);
            echo json_encode(['status' => 'success', 'message' => 'Checked out!', 'time' => $formatted_time]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Record not found.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action or request too large (files exceeded limit).']);
}
