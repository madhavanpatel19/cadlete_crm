<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
$emp_id = $_SESSION['emp_id'];

if ($task_id > 0) {
    // 1. Fetch task & project info before updating status
    $t_q = mysqli_query($con, "SELECT t.*, p.project_name FROM project_team_todos t LEFT JOIN client_projects p ON t.project_id = p.id WHERE t.id = $task_id AND t.emp_id = '$emp_id'");
    
    if ($t_row = mysqli_fetch_assoc($t_q)) {
        $task_name = $t_row['task_name'];
        $proj_name = !empty($t_row['project_name']) ? trim($t_row['project_name']) : '';
        
        $entry_text = !empty($proj_name) ? "Completed Task [$proj_name]: $task_name" : "Completed Task: $task_name";

        // 2. Update status to 1 (Completed)
        $query = "UPDATE project_team_todos SET status = 1 WHERE id = $task_id AND emp_id = '$emp_id'";
        if (mysqli_query($con, $query)) {
            $today = date('Y-m-d');
            
            // 3. Check for today's attendance record
            $att_q = mysqli_query($con, "SELECT id, remarks FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today'");
            
            if (mysqli_num_rows($att_q) > 0) {
                $att_row = mysqli_fetch_assoc($att_q);
                $curr_remarks = trim($att_row['remarks'] ?? '');
                
                // Avoid duplicating the same task line
                if (strpos($curr_remarks, $task_name) === false) {
                    if (!empty($curr_remarks)) {
                        $new_remarks = $curr_remarks . "\n- " . $entry_text;
                    } else {
                        $new_remarks = "- " . $entry_text;
                    }
                    $safe_remarks = mysqli_real_escape_string($con, $new_remarks);
                    mysqli_query($con, "UPDATE attendance SET remarks = '$safe_remarks' WHERE id = " . $att_row['id']);
                }
            } else {
                // Insert a new attendance record for today containing this work detail
                $new_remarks = "- " . $entry_text;
                $safe_remarks = mysqli_real_escape_string($con, $new_remarks);
                mysqli_query($con, "INSERT INTO attendance (emp_id, attendance_date, remarks) VALUES ('$emp_id', '$today', '$safe_remarks')");
            }
            
            // Fetch updated remarks to return to frontend
            $updated_att = mysqli_query($con, "SELECT remarks FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today'");
            $u_row = mysqli_fetch_assoc($updated_att);
            $latest_remarks = $u_row['remarks'] ?? '';

            echo json_encode(['success' => true, 'work_details' => $latest_remarks]);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($con)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
}
