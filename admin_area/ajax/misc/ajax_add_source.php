<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}

// Auto-migration: ensure deleted_at column exists in lead_sources
$check_del_col = @mysqli_query($con, "SHOW COLUMNS FROM lead_sources LIKE 'deleted_at'");
if ($check_del_col && mysqli_num_rows($check_del_col) == 0) {
    @mysqli_query($con, "ALTER TABLE lead_sources ADD COLUMN deleted_at DATETIME DEFAULT NULL");
}

if (isset($_POST['source_name'])) {
    $source_name = trim(mysqli_real_escape_string($con, $_POST['source_name']));

    if (!empty($source_name)) {
        // Check if source already exists (case-insensitive check)
        $check = mysqli_query($con, "SELECT id, source_name, deleted_at FROM lead_sources WHERE LOWER(TRIM(source_name)) = LOWER('$source_name') LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            if (!empty($row['deleted_at'])) {
                // Restore soft-deleted source
                mysqli_query($con, "UPDATE lead_sources SET deleted_at = NULL WHERE id = " . intval($row['id']));
            }
            echo json_encode([
                'status' => 'success',
                'id' => intval($row['id']),
                'name' => $row['source_name'],
                'already_existed' => true,
                'message' => 'Source selected successfully'
            ]);
            exit;
        }

        $insert = mysqli_query($con, "INSERT INTO lead_sources (source_name) VALUES ('$source_name')");
        if ($insert) {
            $new_id = mysqli_insert_id($con);
            echo json_encode([
                'status' => 'success',
                'id' => $new_id,
                'name' => $source_name,
                'already_existed' => false,
                'message' => 'Source created and selected'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Source name cannot be empty']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>
