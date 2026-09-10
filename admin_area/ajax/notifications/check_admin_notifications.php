<?php
/**
 * check_admin_notifications.php
 *
 * This file detects time-based events (birthdays, upcoming birthdays, new
 * leave requests) and inserts them into the system_notifications table so
 * they are picked up automatically by the 30-second smart-polling system.
 *
 * It is called ONCE after each page load (via DOMContentLoaded) from
 * admin_area/index.php. It does NOT need to be polled rapidly — it just
 * needs to run once per session / page navigation to seed the DB.
 *
 * De-duplication is done via the DB (checking existing notification rows),
 * NOT via session variables, so it works correctly across multiple tabs
 * and after session restarts.
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
require_once __DIR__ . '/../../includes/notification_helper.php';

// ── Security headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('X-Content-Type-Options: nosniff');

// Only allow AJAX requests (not direct URL access)
$is_xhr = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    !empty($_SERVER['HTTP_ACCEPT']) &&
    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
);
if (!$is_xhr) {
    if (ob_get_length()) ob_clean();
    http_response_code(403);
    echo json_encode(['status' => 'forbidden']);
    exit();
}

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['admin_email'])) {
    if (ob_get_length()) ob_clean();
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// Get current admin ID
$admin_id = intval($_SESSION['admin_id'] ?? 0);
if ($admin_id <= 0 && isset($_SESSION['admin_email'])) {
    $ae    = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $a_res = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email = '$ae' LIMIT 1");
    if ($a_res && $a_row = mysqli_fetch_assoc($a_res)) {
        $admin_id = intval($a_row['admin_id']);
        $_SESSION['admin_id'] = $admin_id;
    }
}
if ($admin_id <= 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['status' => 'none']);
    exit();
}

initNotificationTable();

$today_day_month    = date('m-d');
$tomorrow_day_month = date('m-d', strtotime('+1 day'));
$current_year       = intval(date('Y'));
$today_date         = date('Y-m-d');
$inserted           = 0;

// ─────────────────────────────────────────────────────────────────────────────
// 1. BIRTHDAY TODAY — Notify all admins (once per employee per year)
// ─────────────────────────────────────────────────────────────────────────────
$bday_q = mysqli_query($con,
    "SELECT id, name FROM emp_list
     WHERE DATE_FORMAT(dob, '%m-%d') = '$today_day_month'
       AND (last_birthday_wish_year IS NULL OR last_birthday_wish_year != '$current_year')"
);
if ($bday_q) {
    while ($emp = mysqli_fetch_assoc($bday_q)) {
        $emp_id   = intval($emp['id']);
        $emp_name = mysqli_real_escape_string($con, $emp['name']);

        // Mark as notified for this year so it won't fire again
        mysqli_query($con,
            "UPDATE emp_list SET last_birthday_wish_year = '$current_year' WHERE id = $emp_id"
        );

        // Check if this birthday notification was already inserted today for any admin
        $already = mysqli_query($con,
            "SELECT id FROM system_notifications
             WHERE type = 'birthday_today'
               AND recipient_type = 'all_admins'
               AND message LIKE '%#$emp_id%'
               AND DATE(created_at) = '$today_date'
             LIMIT 1"
        );
        if ($already && mysqli_num_rows($already) > 0) continue;

        // Insert into system_notifications for all admins
        $title   = "🎂 Birthday Today!";
        $message = "Today is {$emp['name']}'s birthday! Wish them well. #$emp_id";
        $url     = "index.php?employees";
        addSystemNotification('all_admins', 0, $title, $message, $url, 'birthday_today');
        $inserted++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. BIRTHDAY TOMORROW — Remind all admins (once per employee per year)
// ─────────────────────────────────────────────────────────────────────────────
$tomorrow_str = date('Y-m-d', strtotime('+1 day'));
$tmrw_q = mysqli_query($con,
    "SELECT id, name FROM emp_list
     WHERE DATE_FORMAT(dob, '%m-%d') = '$tomorrow_day_month'"
);
if ($tmrw_q) {
    while ($emp = mysqli_fetch_assoc($tmrw_q)) {
        $emp_id   = intval($emp['id']);

        // Check if already inserted today for this employee
        $already = mysqli_query($con,
            "SELECT id FROM system_notifications
             WHERE type = 'birthday_tomorrow'
               AND recipient_type = 'all_admins'
               AND message LIKE '%#$emp_id%'
               AND DATE(created_at) = '$today_date'
             LIMIT 1"
        );
        if ($already && mysqli_num_rows($already) > 0) continue;

        $title   = "📅 Upcoming Birthday";
        $message = "Tomorrow is {$emp['name']}'s birthday! Prepare the celebrations. #$emp_id";
        $url     = "index.php?employees";
        addSystemNotification('all_admins', 0, $title, $message, $url, 'birthday_tomorrow');
        $inserted++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. NEW LEAVE REQUESTS — Notify all admins for any pending unnotified leave
//    Uses the DB directly (not sessions) — picks up ALL unread leave apps
// ─────────────────────────────────────────────────────────────────────────────
$leave_q = mysqli_query($con,
    "SELECT l.id, l.reason, l.leave_from, l.leave_to, l.emp_id,
            COALESCE(lt.leave_name, 'Leave') AS leave_type,
            e.name AS emp_name
     FROM leave_applications l
     JOIN emp_list e ON l.emp_id = e.id
     LEFT JOIN leave_types lt ON lt.id = l.leave_type_id
     WHERE l.status = 'pending'
     ORDER BY l.id DESC
     LIMIT 20"
);
if ($leave_q) {
    while ($row = mysqli_fetch_assoc($leave_q)) {
        $leave_id   = intval($row['id']);
        $emp_name   = $row['emp_name'];
        $reason     = substr($row['reason'] ?? '', 0, 60);
        $leave_type = $row['leave_type'];
        $from_date  = !empty($row['leave_from']) ? date('d M', strtotime($row['leave_from'])) : '';
        $to_date    = !empty($row['leave_to'])   ? date('d M', strtotime($row['leave_to']))   : '';

        // Check if a notification for this specific leave_id already exists
        $already = mysqli_query($con,
            "SELECT id FROM system_notifications
             WHERE type = 'leave_request'
               AND recipient_type = 'all_admins'
               AND url LIKE '%leave_id=$leave_id%'
             LIMIT 1"
        );
        if ($already && mysqli_num_rows($already) > 0) continue;

        $title   = "📋 Leave Request: $emp_name";
        $message = "$emp_name applied for $leave_type ($from_date – $to_date). Reason: $reason";
        $url     = "index.php?view_leave_requests&leave_id=$leave_id";
        addSystemNotification('all_admins', 0, $title, $message, $url, 'leave_request');
        $inserted++;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. LEAVE STATUS UPDATES — Notify employee when admin approves/rejects
//    Checks leave_applications created in the last 7 days with approved/rejected
//    status that don't yet have a matching notification in the DB
// ─────────────────────────────────────────────────────────────────────────────
$leave_status_q = mysqli_query($con,
    "SELECT l.id, l.emp_id, l.status, l.leave_from, l.leave_to,
            COALESCE(lt.leave_name, 'Leave') AS leave_type,
            e.name AS emp_name
     FROM leave_applications l
     JOIN emp_list e ON l.emp_id = e.id
     LEFT JOIN leave_types lt ON lt.id = l.leave_type_id
     WHERE l.status IN ('approved', 'rejected')
       AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY l.id DESC
     LIMIT 20"
);
if ($leave_status_q) {
    while ($row = mysqli_fetch_assoc($leave_status_q)) {
        $leave_id   = intval($row['id']);
        $emp_id     = intval($row['emp_id']);
        $status     = $row['status'];
        $leave_type = $row['leave_type'];
        $from_date  = !empty($row['leave_from']) ? date('d M', strtotime($row['leave_from'])) : '';
        $to_date    = !empty($row['leave_to'])   ? date('d M', strtotime($row['leave_to']))   : '';

        $notif_type = ($status === 'approved') ? 'leave_approved' : 'leave_rejected';
        $icon_emoji = ($status === 'approved') ? '✅' : '❌';
        $status_label = ucfirst($status);

        // Check if already notified for this specific leave status
        $already = mysqli_query($con,
            "SELECT id FROM system_notifications
             WHERE type = '$notif_type'
               AND recipient_type = 'employee'
               AND recipient_id = $emp_id
               AND url LIKE '%leave_id=$leave_id%'
             LIMIT 1"
        );
        if ($already && mysqli_num_rows($already) > 0) continue;

        $title   = "$icon_emoji Leave $status_label";
        $message = "Your $leave_type request ($from_date – $to_date) has been $status_label.";
        $url     = "index.php?my_leaves&leave_id=$leave_id";
        addSystemNotification('employee', $emp_id, $title, $message, $url, $notif_type);
        $inserted++;
    }
}

if (ob_get_length()) ob_clean();
echo json_encode([
    'status'   => 'ok',
    'inserted' => $inserted,
]);