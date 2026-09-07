<?php
// =============================================================
// emp_area/pages/announcements/check_announcement.php
// API is turned off / commented out
// =============================================================
header('Content-Type: application/json');
echo json_encode(["status" => "none"]);
exit();

/*
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit();
}

$emp_id = $_SESSION['emp_id'];

$query = "SELECT id, title, message FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY id DESC LIMIT 1";
$run   = mysqli_query($con, $query);

if ($run && mysqli_num_rows($run) > 0) {
    $row      = mysqli_fetch_array($run);
    $latest_id = $row['id'];
    $title    = $row['title'];
    $message  = $row['message'];

    if (!isset($_SESSION['last_emp_announcement_id'])) {
        $_SESSION['last_emp_announcement_id'] = $latest_id;
        echo json_encode(["status" => "none"]);
        exit();
    }

    if ($latest_id > $_SESSION['last_emp_announcement_id']) {
        $_SESSION['last_emp_announcement_id'] = $latest_id;
        echo json_encode([
            "status"  => "new",
            "title"   => $title,
            "message" => substr($message, 0, 100) . "..."
        ]);
    } else {
        echo json_encode(["status" => "none"]);
    }
} else {
    echo json_encode(["status" => "none"]);
}
*/