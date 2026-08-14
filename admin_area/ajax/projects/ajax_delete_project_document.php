<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('isSuperAdmin')) {
    require_once(__DIR__ . '/../../includes/admin_permissions.php');
}
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['doc_id'])) {
    $doc_id = mysqli_real_escape_string($con, $_POST['doc_id']);

    // Get file path first (only if not already soft-deleted)
    $get_path = "SELECT project_id, file_path, is_proposal FROM project_documents WHERE id = '$doc_id' AND deleted_at IS NULL";
    $run_path = mysqli_query($con, $get_path);
    if ($row = mysqli_fetch_assoc($run_path)) {
        if (!empty($row['is_proposal'])) {
            $proj_id = $row['project_id'];
            $current_admin_id = 0;
            if (isset($_SESSION['admin_email'])) {
                $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
                $r_adm = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email = '$email_esc' LIMIT 1");
                if ($r_adm && $row_a = mysqli_fetch_assoc($r_adm)) {
                    $current_admin_id = (int)$row_a['admin_id'];
                }
            }

            $res_p = mysqli_query($con, "SELECT assigned_admins FROM client_projects WHERE id = '$proj_id' LIMIT 1");
            $assigned_admins_list = [];
            if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
                if (!empty($row_p['assigned_admins'])) {
                    $assigned_admins_list = array_map('trim', explode(',', $row_p['assigned_admins']));
                }
            }

            $can_manage_proposal = isSuperAdmin() || ($current_admin_id > 0 && in_array((string)$current_admin_id, $assigned_admins_list, true));
            if (!$can_manage_proposal) {
                echo json_encode(['success' => false, 'message' => 'Permission denied: Only Super Admin or Assigned Admin can delete Project Proposal documents.']);
                exit;
            }
        }

        $path = $row['file_path'];
        $now  = date('Y-m-d H:i:s');

        $update = "UPDATE project_documents SET deleted_at = '$now' WHERE id = '$doc_id' AND deleted_at IS NULL";
        if (mysqli_query($con, $update)) {
            // Keep the physical file on disk (soft delete = no data loss)
            echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
