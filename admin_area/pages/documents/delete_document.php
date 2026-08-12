<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

if (isset($_POST['doc_id'])) {
    $doc_id = intval($_POST['doc_id']);

    $res = mysqli_query($con, "SELECT file_name FROM employee_documents WHERE id = $doc_id AND deleted_at IS NULL");
    if ($res && mysqli_num_rows($res) > 0) {
        $row  = mysqli_fetch_assoc($res);
        $file = "../../uploads/" . $row['file_name'];

        $now    = date('Y-m-d H:i:s');
        $deleted = mysqli_query($con, "UPDATE employee_documents SET deleted_at = '$now' WHERE id = $doc_id AND deleted_at IS NULL");

        if ($deleted) {
            // Optionally remove physical file — comment out if you want to keep files too
            if (file_exists($file)) unlink($file);
        }

        echo json_encode([
            "status" => $deleted ? "success" : "error"
        ]);
    } else {
        echo json_encode(["status" => "error"]);
    }
} else {
    echo json_encode(["status" => "error"]);
}
