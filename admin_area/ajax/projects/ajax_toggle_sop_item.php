<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
require_once __DIR__ . '/../../includes/notification_helper.php';

header('Content-Type: application/json');

$project_id  = isset($_POST['project_id'])  ? (int)$_POST['project_id']  : 0;
$sop_item_id = isset($_POST['sop_item_id']) ? (int)$_POST['sop_item_id'] : 0;
$is_checked  = isset($_POST['is_checked'])  ? (int)$_POST['is_checked']  : 0;

if ($project_id <= 0 || $sop_item_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// ── Determine who is checking (Emp vs Admin) ─────────────────────────────────
$req_portal = isset($_POST['portal']) ? trim($_POST['portal']) : '';
$referer    = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

$is_emp_portal   = ($req_portal === 'employee' || strpos($referer, '/emp_area/') !== false);
$is_admin_portal = ($req_portal === 'admin' || strpos($referer, '/admin_area/') !== false);

$checked_by = '';
$is_emp_action = false;

// 1. Check Employee portal / session first if request came from emp_area
if ($is_emp_portal || (empty($_SESSION['admin_email']) && !empty($_SESSION['emp_id']))) {
    $is_emp_action = true;
    if (!empty($_SESSION['emp_name'])) {
        $checked_by = $_SESSION['emp_name'];
    } elseif (!empty($_SESSION['emp_id'])) {
        $emp_id_val = (int)$_SESSION['emp_id'];
        $r_emp = mysqli_query($con, "SELECT name FROM emp_list WHERE id=$emp_id_val LIMIT 1");
        if ($r_emp && $row_emp = mysqli_fetch_assoc($r_emp)) {
            $checked_by = !empty($row_emp['name']) ? $row_emp['name'] : 'Employee';
        }
    }
}

// 2. Check Admin portal / session
if (empty($checked_by) && ($is_admin_portal || !empty($_SESSION['admin_email']))) {
    if (!empty($_SESSION['admin_name'])) {
        $checked_by = $_SESSION['admin_name'];
    }
    if (empty($checked_by) && !empty($_SESSION['admin_email'])) {
        $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $r_adm = mysqli_query($con, "SELECT admin_name FROM admins WHERE admin_email='$email_esc' LIMIT 1");
        if ($r_adm && $row_adm = mysqli_fetch_assoc($r_adm)) {
            if (!empty($row_adm['admin_name'])) {
                $checked_by = $row_adm['admin_name'];
            }
        }
    }
    if (empty($checked_by) && !empty($_SESSION['admin_email'])) {
        $checked_by = $_SESSION['admin_email'];
    }
}

// 3. Fallbacks
if (empty($checked_by)) {
    if (!empty($_SESSION['emp_name'])) {
        $checked_by = $_SESSION['emp_name'];
    } elseif (!empty($_SESSION['admin_name'])) {
        $checked_by = $_SESSION['admin_name'];
    } else {
        $checked_by = 'User';
    }
}

$checked_at = date('Y-m-d H:i:s');

// ── Ensure tables exist ─────────────────────────────────────────────────────
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_checklist` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT(11) NOT NULL,
    `sop_item_id` INT(11) NOT NULL,
    `is_checked` TINYINT(1) DEFAULT 0,
    `checked_by` VARCHAR(255) DEFAULT NULL,
    `checked_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `unique_project_sop` (`project_id`, `sop_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Toggle the SOP item ─────────────────────────────────────────────────────
if ($is_checked) {
    $sql = "INSERT INTO project_sop_checklist (project_id, sop_item_id, is_checked, checked_by, checked_at)
            VALUES ($project_id, $sop_item_id, 1, '" . mysqli_real_escape_string($con, $checked_by) . "', '$checked_at')
            ON DUPLICATE KEY UPDATE is_checked=1, checked_by='" . mysqli_real_escape_string($con, $checked_by) . "', checked_at='$checked_at'";
} else {
    $sql = "INSERT INTO project_sop_checklist (project_id, sop_item_id, is_checked, checked_by, checked_at)
            VALUES ($project_id, $sop_item_id, 0, NULL, NULL)
            ON DUPLICATE KEY UPDATE is_checked=0, checked_by=NULL, checked_at=NULL";
}

if (!mysqli_query($con, $sql)) {
    echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
    exit;
}

// ── Get updated totals ──────────────────────────────────────────────────────
$total_q = mysqli_query($con, "SELECT COUNT(*) as t FROM project_sop_items");
$total = (int)mysqli_fetch_assoc($total_q)['t'];

$done_q = mysqli_query($con, "SELECT COUNT(*) as d FROM project_sop_checklist WHERE project_id=$project_id AND is_checked=1");
$done = (int)mysqli_fetch_assoc($done_q)['d'];

$auto_completed = false;

// ── Auto-complete project if ALL SOP items are checked ──────────────────────
if ($total > 0 && $done >= $total) {
    // Fetch current project status and name
    $proj_res = mysqli_query($con, "SELECT status, project_name FROM client_projects WHERE id=$project_id LIMIT 1");
    if ($proj_res && $proj_row = mysqli_fetch_assoc($proj_res)) {
        $current_status = $proj_row['status'];
        $project_name   = $proj_row['project_name'];

        if (strtolower($current_status) !== 'completed') {
            // ── 1. Update project status to Completed ───────────────────────
            mysqli_query($con, "UPDATE client_projects SET status='Completed' WHERE id=$project_id");

            // ── 2. Log system remark in project timeline ────────────────────
            $remark_text = "System: All " . $total . " SOP checklist items completed. Project status automatically changed to Completed.";
            $r_esc = mysqli_real_escape_string($con, $remark_text);
            mysqli_query($con, "INSERT INTO client_project_remarks (project_id, remark, posted_by, created_at)
                                VALUES ($project_id, '$r_esc', 'System', NOW())");

            // ── 3. Send notification to all project admins ──────────────────
            $notif_title   = "✅ Project SOP 100% Complete!";
            $notif_message = "All $total SOP checklist items for project \"$project_name\" have been completed by $checked_by. Status auto-changed to Completed.";
            $notif_url     = "index.php?projects";

            notifyProjectAdmins(
                $project_id,
                $notif_title,
                $notif_message,
                $notif_url,
                'success',
                0
            );

            $auto_completed = true;
        }
    }
}

echo json_encode([
    'success'        => true,
    'total'          => $total,
    'completed'      => $done,
    'auto_completed' => $auto_completed,
    'checked_by'     => $is_checked ? $checked_by : null,
]);
