<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Only allow access if logged in as employee
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}

// Render the modern Project-wise Kanban Todo board
include(__DIR__ . '/../../../admin_area/pages/projects/global_team_todos.php');
