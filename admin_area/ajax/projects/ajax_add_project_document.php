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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
    $document_name = mysqli_real_escape_string($con, $_POST['document_name']);
    $current_admin_id = 0;
    if (isset($_SESSION['admin_email'])) {
        $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $r_adm = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email = '$email_esc' LIMIT 1");
        if ($r_adm && $row_a = mysqli_fetch_assoc($r_adm)) {
            $current_admin_id = (int)$row_a['admin_id'];
        }
    }

    $res_p = mysqli_query($con, "SELECT assigned_admins FROM client_projects WHERE id = '$project_id' LIMIT 1");
    $assigned_admins_list = [];
    if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
        if (!empty($row_p['assigned_admins'])) {
            $assigned_admins_list = array_map('trim', explode(',', $row_p['assigned_admins']));
        }
    }

    $can_manage_proposal = isSuperAdmin() || ($current_admin_id > 0 && in_array((string)$current_admin_id, $assigned_admins_list, true));
    $is_proposal = (isset($_POST['is_proposal']) && $_POST['is_proposal'] == '1' && $can_manage_proposal) ? 1 : 0;

    if (isset($_FILES['project_doc']) && $_FILES['project_doc']['error'] == 0) {
        $file_name = $_FILES['project_doc']['name'];
        $file_tmp = $_FILES['project_doc']['tmp_name'];
        $file_size = $_FILES['project_doc']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'txt', 'xls', 'xlsx');

        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 10485760) { // 10MB
                $new_file_name = "project_" . $project_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
                $target_dir = __DIR__ . "/../../uploads/project_documents/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $db_path = "uploads/project_documents/" . $new_file_name;
                $upload_path = $target_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $insert = "INSERT INTO project_documents (project_id, document_name, file_path, is_proposal) VALUES ('$project_id', '$document_name', '$db_path', '$is_proposal')";
                    if (mysqli_query($con, $insert)) {
                        echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
