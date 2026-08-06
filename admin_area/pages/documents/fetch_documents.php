<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
$id = intval($_GET['id']);
$result = mysqli_query($con, "SELECT * FROM employee_documents WHERE emp_id='$id' AND deleted_at IS NULL");
if ($result && mysqli_num_rows($result) > 0) {
    while ($doc = mysqli_fetch_assoc($result)) {
        $file = htmlspecialchars($doc['file_name']);
        $fileUrl = (strpos($doc['file_name'], 'uploads/') === 0) ? $doc['file_name'] : 'uploads/' . $doc['file_name'];
        echo "<div class='doc-item'>
                <a href='$fileUrl' target='_blank'>$file</a>
                <button class='delete-doc' onclick='deleteDocument({$doc['id']}, $id)'>Delete</button>
              </div>";
    }
} else {
    echo "<p>No documents uploaded yet.</p>";
}
?>
