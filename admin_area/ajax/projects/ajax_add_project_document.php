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
    $is_proposal = (isset($_POST['is_proposal']) && $_POST['is_proposal'] == '1' && isSuperAdmin()) ? 1 : 0;

    if (isset($_FILES['project_doc']) && $_FILES['project_doc']['error'] == 0) {
        $file_name = $_FILES['project_doc']['name'];
        $file_tmp = $_FILES['project_doc']['tmp_name'];
        $file_size = $_FILES['project_doc']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'txt', 'xls', 'xlsx');

        if (in_array($file_ext, $allowed)) {
            if ($file_size <= 10485760) { // 10MB
                $new_file_name = "project_" . $project_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
                $db_path = "project_docs/" . $new_file_name;
                $upload_path = "../../" . $db_path;

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
