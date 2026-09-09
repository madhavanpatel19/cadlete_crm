<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

/** @var mysqli $con */

if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

if ($action === 'add_question_answer') {
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    $question_num = (int)($_POST['question_num'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $answer = trim($_POST['answer'] ?? '');

    if ($lead_id <= 0 || $question_num <= 0 || empty($answer)) {
        echo json_encode(['status' => 'error', 'message' => 'Answer cannot be empty']);
        exit;
    }

    $esc_qtext = mysqli_real_escape_string($con, $question_text);
    $esc_ans = mysqli_real_escape_string($con, $answer);
    $esc_name = mysqli_real_escape_string($con, $admin_name);

    $sql = "INSERT INTO lead_question_answers (lead_id, emp_id, emp_name, question_num, question_text, answer) 
            VALUES ('$lead_id', '$admin_id', '$esc_name', '$question_num', '$esc_qtext', '$esc_ans')";

    if (mysqli_query($con, $sql)) {
        $ans_id = mysqli_insert_id($con);
        $formatted_date = date('d M Y, h:i A');

        // Log into lead_followups
        $followup_remark = mysqli_real_escape_string($con, "[Q$question_num: $question_text]\n$answer (by $admin_name)");
        $today = date('Y-m-d');
        mysqli_query($con, "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                            VALUES ('$lead_id', '$today', 'Follow-up Q&A', 'Question Response', '$followup_remark')");

        echo json_encode([
            'status' => 'success',
            'message' => 'Answer saved successfully',
            'answer' => [
                'id' => $ans_id,
                'emp_id' => $admin_id,
                'emp_name' => $admin_name,
                'question_num' => $question_num,
                'question_text' => $question_text,
                'answer' => $answer,
                'date_str' => $formatted_date,
                'initial' => strtoupper(substr($admin_name, 0, 1))
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($action === 'delete_question_answer') {
    $answer_id = (int)($_POST['answer_id'] ?? 0);
    $lead_id = (int)($_POST['lead_id'] ?? 0);

    if ($answer_id <= 0 || $lead_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        exit;
    }

    $del_sql = "DELETE FROM lead_question_answers WHERE id = '$answer_id' AND lead_id = '$lead_id'";
    if (mysqli_query($con, $del_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Answer deleted']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
