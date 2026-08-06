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
    $get_path = "SELECT file_path, is_proposal FROM project_documents WHERE id = '$doc_id' AND deleted_at IS NULL";
    $run_path = mysqli_query($con, $get_path);
    if ($row = mysqli_fetch_assoc($run_path)) {
        if (!empty($row['is_proposal']) && !isSuperAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Permission denied: Only Super Admin can delete Project Proposal documents.']);
            exit;
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
