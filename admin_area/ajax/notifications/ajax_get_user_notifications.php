<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
require_once __DIR__ . '/../../includes/notification_helper.php';

header('Content-Type: application/json');

$portal = isset($_GET['portal']) ? $_GET['portal'] : (isset($_POST['portal']) ? $_POST['portal'] : '');

$is_admin = isset($_SESSION['admin_email']);
$is_emp   = isset($_SESSION['emp_id']);

$fetch_as_emp = false;
if ($portal === 'employee') {
    $fetch_as_emp = true;
} elseif ($portal === 'admin') {
    $fetch_as_emp = false;
} elseif ($is_emp && !$is_admin) {
    $fetch_as_emp = true;
}

$where = "";
if ($fetch_as_emp && $is_emp) {
    $emp_id = intval($_SESSION['emp_id']);
    $where = "(recipient_type = 'all_employees' OR (recipient_type = 'employee' AND recipient_id = $emp_id))";
} else if ($is_admin) {
    $admin_id = intval($_SESSION['admin_id'] ?? 0);
    $is_super = false;
    if (isset($_SESSION['admin_email'])) {
        $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $a_res = mysqli_query($con, "SELECT admin_id, is_super_admin, admin_job FROM admins WHERE admin_email = '$ae' LIMIT 1");
        if ($a_res && $a_row = mysqli_fetch_assoc($a_res)) {
            $admin_id = intval($a_row['admin_id']);
            $_SESSION['admin_id'] = $admin_id;
            if (intval($a_row['is_super_admin'] ?? 0) === 1 || strcasecmp(trim($a_row['admin_job'] ?? ''), 'Super Admin') === 0) {
                $is_super = true;
            }
        }
    }

    if ($is_super) {
        $where = "(recipient_type = 'all_admins' OR recipient_type = 'admin')";
    } else {
        $where = "(recipient_type = 'admin' AND recipient_id = $admin_id)";
    }
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Unread count
$count_res = mysqli_query($con, "SELECT COUNT(*) as unread FROM system_notifications WHERE $where AND is_read = 0");
$unread_count = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $unread_count = intval($row['unread']);
}

// Fetch 15 most recent unread notifications
$list_res = mysqli_query($con, "SELECT * FROM system_notifications WHERE $where AND is_read = 0 ORDER BY id DESC LIMIT 15");
$notifications = [];
if ($list_res) {
    while ($r = mysqli_fetch_assoc($list_res)) {
        $created = strtotime($r['created_at']);
        $diff = time() - $created;
        if ($diff < 60) $time_ago = 'just now';
        elseif ($diff < 3600) $time_ago = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400) $time_ago = floor($diff / 3600) . 'h ago';
        else $time_ago = date('d M', $created);

        $r['time_ago'] = $time_ago;
        $notifications[] = $r;
    }
}

if (ob_get_length()) ob_clean();
echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);
