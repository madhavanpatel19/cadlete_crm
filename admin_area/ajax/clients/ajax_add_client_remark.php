<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

$response = ['success' => false];

if (isset($_POST['project_id']) && isset($_POST['remark'])) {
    $project_id = intval($_POST['project_id']);
    $remark = mysqli_real_escape_string($con, trim($_POST['remark']));
    $req_user_type = isset($_POST['user_type']) ? trim($_POST['user_type']) : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

    // Ensure posted_by column exists
    try {
        @mysqli_query($con, "ALTER TABLE client_project_remarks ADD COLUMN posted_by VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
    }

    $posted_by = '';

    // Check if explicitly coming from Employee Portal or Admin Portal
    $is_emp_portal = ($req_user_type === 'employee' || strpos($referer, '/emp_area/') !== false);
    $is_admin_portal = ($req_user_type === 'admin' || strpos($referer, '/admin_area/') !== false);

    if ($is_emp_portal) {
        // Must use Employee name
        if (!empty($_SESSION['emp_name'])) {
            $posted_by = $_SESSION['emp_name'];
        } elseif (!empty($_SESSION['emp_id'])) {
            $emp_id_val = (int)$_SESSION['emp_id'];
            $r_emp = mysqli_query($con, "SELECT name FROM emp_list WHERE id=$emp_id_val LIMIT 1");
            if ($r_emp && $row_emp = mysqli_fetch_assoc($r_emp)) {
                $posted_by = !empty($row_emp['name']) ? $row_emp['name'] : 'Employee';
            }
        }
    }

    if (empty($posted_by) && ($is_admin_portal || !empty($_SESSION['admin_email']))) {
        // Admin Portal or Admin session
        if (!empty($_SESSION['admin_email'])) {
            $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
            $r_adm = mysqli_query($con, "SELECT admin_name FROM admins WHERE admin_email='$email_esc' LIMIT 1");
            if ($r_adm && $row_adm = mysqli_fetch_assoc($r_adm)) {
                if (!empty($row_adm['admin_name'])) {
                    $posted_by = $row_adm['admin_name'];
                }
            }
            if (empty($posted_by) && !empty($_SESSION['admin_name'])) {
                $posted_by = $_SESSION['admin_name'];
            }
        }
        if (empty($posted_by)) {
            $posted_by = !empty($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
        }
    }

    // Ultimate fallback if still empty
    if (empty($posted_by)) {
        if (!empty($_SESSION['emp_name'])) {
            $posted_by = $_SESSION['emp_name'];
        } else {
            $posted_by = 'System';
        }
    }

    $safe_posted_by = mysqli_real_escape_string($con, $posted_by);

    if (!empty($remark)) {
        $insert = "INSERT INTO client_project_remarks (project_id, remark, posted_by) VALUES ($project_id, '$remark', '$safe_posted_by')";
        if (mysqli_query($con, $insert)) {
            $response['success'] = true;
            $response['posted_by'] = $posted_by;
        }
    }
}

echo json_encode($response);
