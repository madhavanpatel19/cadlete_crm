<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $category = mysqli_real_escape_string($con, trim($_POST['category'] ?? 'project_images'));
    $asset_name = mysqli_real_escape_string($con, trim($_POST['asset_name'] ?? ''));
    $dimension_spec = mysqli_real_escape_string($con, trim($_POST['dimension_spec'] ?? ''));
    $link_url = mysqli_real_escape_string($con, trim($_POST['link_url'] ?? ''));

    if ($project_id <= 0 || empty($asset_name)) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid deliverable option (Project ID: ' . $project_id . ')']);
        exit;
    }

    $uploader = 'Admin';
    if (!empty($_SESSION['emp_name'])) {
        $uploader = $_SESSION['emp_name'];
    } elseif (!empty($_SESSION['admin_name'])) {
        $uploader = $_SESSION['admin_name'];
    }

    $allowed_exts = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico', 'heic',
        'mp4', 'mov', 'webm', 'mkv', 'avi', 'wmv', 'flv',
        'zip', 'rar', '7z', 'tar', 'gz',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
        'psd', 'ai', 'eps', 'cdr', 'xd', 'fig', 'sketch',
        'dwg', 'dxf', 'step', 'stp', 'iges', 'igs', 'stl', 'obj', '3ds', '3dm', 'sldprt', 'sldasm', 'max', 'blend'
    ];

    $target_dir = __DIR__ . "/../../uploads/project_media/";
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }

    $uploaded_count = 0;
    $errors = [];

    // 1. Handle Multiple/Single File Uploads
    $files_to_process = [];
    if (isset($_FILES['media_files'])) {
        if (is_array($_FILES['media_files']['name'])) {
            $count = count($_FILES['media_files']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['media_files']['error'][$i] === 0) {
                    $files_to_process[] = [
                        'name' => $_FILES['media_files']['name'][$i],
                        'tmp_name' => $_FILES['media_files']['tmp_name'][$i],
                        'size' => $_FILES['media_files']['size'][$i],
                        'error' => $_FILES['media_files']['error'][$i]
                    ];
                } else if ($_FILES['media_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Upload error code: " . $_FILES['media_files']['error'][$i];
                }
            }
        } else if ($_FILES['media_files']['error'] === 0) {
            $files_to_process[] = $_FILES['media_files'];
        }
    }

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === 0) {
        $files_to_process[] = $_FILES['media_file'];
    }

    foreach ($files_to_process as $file) {
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "File '$file_name': extension '.$file_ext' not allowed";
            continue;
        }

        if ($file_size > 104857600) { // 100MB
            $errors[] = "File '$file_name' exceeds 100MB limit";
            continue;
        }

        $new_file_name = "media_" . $project_id . "_" . time() . "_" . uniqid() . "." . $file_ext;
        $upload_path = $target_dir . $new_file_name;
        $db_path = "uploads/project_media/" . $new_file_name;

        if (move_uploaded_file($file_tmp, $upload_path)) {
            $file_type = in_array($file_ext, ['mp4', 'mov', 'webm', 'mkv', 'avi']) ? 'video' : 'image';
            $esc_orig = mysqli_real_escape_string($con, $file_name);
            
            $insert = "INSERT INTO project_media_assets (project_id, category, asset_name, dimension_spec, file_path, link_url, file_type, original_file_name, uploaded_by) 
                       VALUES ('$project_id', '$category', '$asset_name', '$dimension_spec', '$db_path', NULL, '$file_type', '$esc_orig', '$uploader')";
            if (mysqli_query($con, $insert)) {
                $uploaded_count++;
            } else {
                $errors[] = "Database insert failed: " . mysqli_error($con);
            }
        } else {
            $errors[] = "Failed to move file to: " . $upload_path;
        }
    }

    // 2. Handle External Link if provided
    if (!empty($link_url)) {
        if (!preg_match("~^(?:f|ht)tps?://~i", $link_url)) {
            $link_url = "https://" . $link_url;
        }
        $insert = "INSERT INTO project_media_assets (project_id, category, asset_name, dimension_spec, file_path, link_url, file_type, original_file_name, uploaded_by) 
                   VALUES ('$project_id', '$category', '$asset_name', '$dimension_spec', NULL, '$link_url', 'link', 'External Link', '$uploader')";
        if (mysqli_query($con, $insert)) {
            $uploaded_count++;
        } else {
            $errors[] = "Database insert link failed: " . mysqli_error($con);
        }
    }

    if ($uploaded_count > 0) {
        echo json_encode(['success' => true, 'message' => "Successfully uploaded $uploaded_count item(s)"]);
    } else {
        $err_msg = !empty($errors) ? implode(", ", $errors) : 'No files were uploaded. Please select valid files.';
        echo json_encode(['success' => false, 'message' => $err_msg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
