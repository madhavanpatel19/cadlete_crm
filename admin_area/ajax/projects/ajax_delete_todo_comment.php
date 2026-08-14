<?php
session_start();
if (!isset($con)) include(__DIR__ . '/../../includes/db.php');
header('Content-Type: application/json');
if (!isset($_SESSION['admin_email'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit(); }

$comment_id = intval($_POST['comment_id'] ?? 0);
if (!$comment_id) { echo json_encode(['success'=>false,'message'=>'Invalid']); exit(); }

$ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
$admin = mysqli_fetch_assoc(mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email='$ae' LIMIT 1"));
$aid = $admin['admin_id'] ?? 0;
$now = date('Y-m-d H:i:s');

mysqli_query($con, "UPDATE project_todo_comments SET deleted_at='$now' WHERE id=$comment_id AND admin_id=$aid AND deleted_at IS NULL");
echo json_encode(['success'=> mysqli_affected_rows($con) > 0]);
