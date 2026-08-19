<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

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

$notif_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$mark_all = isset($_POST['mark_all']) && ($_POST['mark_all'] === 'true' || $_POST['mark_all'] === '1');

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
        $where = "(recipient_type = 'all_admins' OR (recipient_type = 'admin' AND (recipient_id = $admin_id OR recipient_id = 0)))";
    }
} else {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($mark_all) {
    mysqli_query($con, "UPDATE system_notifications SET is_read = 1 WHERE $where");
} else if ($notif_id > 0) {
    mysqli_query($con, "UPDATE system_notifications SET is_read = 1 WHERE id = $notif_id");
}

if (ob_get_length()) ob_clean();
echo json_encode(['success' => true]);
