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

// Force check if explicitly requested
unset($_SESSION['last_time_based_notif_check']);
$inserted = checkTimeBasedSystemNotifications($con);

if (ob_get_length()) ob_clean();
echo json_encode([
    'status'   => 'ok',
    'inserted' => $inserted,
]);