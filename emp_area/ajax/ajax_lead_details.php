<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../includes/db.php');
}
/** @var mysqli $con */

header('Content-Type: application/json');

if (!isset($_SESSION['emp_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$emp_id = (int)$_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'] ?? 'Employee';
$action = $_REQUEST['action'] ?? '';

$currency_symbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'د.إ'
];

function leadTimeAgo(string $datetime = ''): string
{
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M, h:i A', $timestamp);
}

if ($action === 'get_lead') {
    $lead_id = (int)($_GET['lead_id'] ?? 0);
    if ($lead_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Lead ID']);
        exit;
    }

    // Verify lead belongs to this employee
    $get_lead = "SELECT * FROM leads WHERE id = '$lead_id' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0";
    $run_lead = mysqli_query($con, $get_lead);

    if (!$run_lead || mysqli_num_rows($run_lead) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Lead not found or access denied']);
        exit;
    }

    $row = mysqli_fetch_assoc($run_lead);

    // Fetch assigned employee names
    $assigned_names = [];
    $emp_ids_str = !empty($row['assigned_employees']) ? $row['assigned_employees'] : '';
    if (!empty($emp_ids_str)) {
        $emp_ids_arr = array_filter(array_map('intval', explode(',', $emp_ids_str)));
        if (!empty($emp_ids_arr)) {
            $ids_impl = implode(',', $emp_ids_arr);
            $r_emps = mysqli_query($con, "SELECT name FROM emp_list WHERE id IN ($ids_impl)");
            if ($r_emps) {
                while ($e_row = mysqli_fetch_assoc($r_emps)) {
                    $assigned_names[] = $e_row['name'];
                }
            }
        }
    }
    if (empty($assigned_names)) {
        $assigned_names[] = $emp_name;
    }

    // Format phone digits for WhatsApp link
    $clean_phone = !empty($row['phone']) ? preg_replace('/[^0-9]/', '', $row['phone']) : '';
    $curr_sym = isset($row['currency']) && isset($currency_symbols[$row['currency']]) ? $currency_symbols[$row['currency']] : '₹';

    // Fetch followups history
    $followups = [];
    $get_f = "SELECT * FROM lead_followups WHERE lead_id = '$lead_id' ORDER BY followup_date DESC, id DESC";
    $run_f = mysqli_query($con, $get_f);
    if ($run_f) {
        while ($f_row = mysqli_fetch_assoc($run_f)) {
            $raw_remark = $f_row['remark'] ?? '';
            $author = $emp_name;
            $clean_remark = $raw_remark;

            if (preg_match('/^(.*?)\s*\(by\s+([^)]+)\)$/s', $raw_remark, $matches)) {
                $clean_remark = trim($matches[1]);
                $author = trim($matches[2]);
            }

            $followups[] = [
                'id' => (int)$f_row['id'],
                'date' => !empty($f_row['followup_date']) ? date('d-m-Y', strtotime($f_row['followup_date'])) : '',
                'method' => $f_row['followup_method'] ?? 'General',
                'type' => $f_row['followup_type'] ?? 'Note',
                'author' => $author,
                'clean_remark' => $clean_remark,
                'remark' => $raw_remark,
                'time_ago' => leadTimeAgo($f_row['created_at'] ?? '')
            ];
        }
    }

    // Fetch Question Answers
    $question_answers = [
        1 => [],
        2 => [],
        3 => [],
        4 => []
    ];
    $get_qa = "SELECT * FROM lead_question_answers WHERE lead_id = '$lead_id' ORDER BY id ASC";
    $run_qa = mysqli_query($con, $get_qa);
    if ($run_qa) {
        while ($qa_row = mysqli_fetch_assoc($run_qa)) {
            $qnum = (int)$qa_row['question_num'];
            if (!isset($question_answers[$qnum])) {
                $question_answers[$qnum] = [];
            }
            $question_answers[$qnum][] = [
                'id' => (int)$qa_row['id'],
                'emp_id' => (int)$qa_row['emp_id'],
                'emp_name' => $qa_row['emp_name'] ?: 'Employee',
                'question_num' => $qnum,
                'question_text' => $qa_row['question_text'] ?? '',
                'answer' => $qa_row['answer'] ?? '',
                'created_at' => $qa_row['created_at'] ?? '',
                'time_ago' => leadTimeAgo($qa_row['created_at'] ?? ''),
                'can_delete' => ($emp_id === (int)$qa_row['emp_id'])
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'current_user' => [
            'emp_id' => $emp_id,
            'emp_name' => $emp_name
        ],
        'lead' => [
            'id' => (int)$row['id'],
            'project_name' => !empty($row['project_name']) ? $row['project_name'] : $row['client_name'],
            'client_name' => $row['client_name'] ?? '',
            'company_name' => $row['company_name'] ?? '',
            'phone' => $row['phone'] ?? '',
            'clean_phone' => $clean_phone,
            'email' => $row['email'] ?? '',
            'budget' => !empty($row['budget']) ? $curr_sym . ' ' . $row['budget'] : '-',
            'lead_source' => !empty($row['lead_source']) ? $row['lead_source'] : 'General Task',
            'status' => strtolower($row['status'] ?? 'active'),
            'followup_date' => !empty($row['followup_date']) ? date('Y-m-d', strtotime($row['followup_date'])) : date('Y-m-d'),
            'display_followup_date' => !empty($row['followup_date']) ? date('d/m/Y', strtotime($row['followup_date'])) : '--',
            'description' => $row['description'] ?? '',
            'remark' => $row['remark'] ?? '',
            'assigned_name' => implode(', ', $assigned_names)
        ],
        'followups' => $followups,
        'question_answers' => $question_answers
    ]);
    exit;
}

if ($action === 'add_comment') {
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');
    $next_date = !empty($_POST['next_date']) ? mysqli_real_escape_string($con, $_POST['next_date']) : null;

    if ($lead_id <= 0 || empty($remark)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a comment']);
        exit;
    }

    // Verify access
    $check_access = mysqli_query($con, "SELECT id FROM leads WHERE id = '$lead_id' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0");
    if (!$check_access || mysqli_num_rows($check_access) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }

    $escaped_remark = mysqli_real_escape_string($con, $remark . " (by " . $emp_name . ")");
    $today = date('Y-m-d');
    $method = 'Note';
    $type = 'Comment';

    $sql = "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
            VALUES ('$lead_id', '$today', '$method', '$type', '$escaped_remark')";

    if (mysqli_query($con, $sql)) {
        if (!empty($next_date)) {
            mysqli_query($con, "UPDATE leads SET followup_date = '$next_date' WHERE id = '$lead_id'");
        }
        echo json_encode([
            'status' => 'success',
            'message' => 'Comment added successfully',
            'comment' => [
                'date' => date('d-m-Y'),
                'method' => $method,
                'type' => $type,
                'remark' => $remark . " (by " . $emp_name . ")",
                'time_ago' => date('h:i A, d M Y')
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($action === 'add_question_answer') {
    $lead_id = (int)($_POST['lead_id'] ?? 0);
    $question_num = (int)($_POST['question_num'] ?? 0);
    $question_text = trim($_POST['question_text'] ?? '');
    $answer = trim($_POST['answer'] ?? '');

    if ($lead_id <= 0 || $question_num <= 0 || empty($answer)) {
        echo json_encode(['status' => 'error', 'message' => 'Please provide an answer']);
        exit;
    }

    // Verify access
    $check_access = mysqli_query($con, "SELECT id FROM leads WHERE id = '$lead_id' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0");
    if (!$check_access || mysqli_num_rows($check_access) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied']);
        exit;
    }

    $esc_qtext = mysqli_real_escape_string($con, $question_text);
    $esc_ans = mysqli_real_escape_string($con, $answer);
    $esc_emp_name = mysqli_real_escape_string($con, $emp_name);

    $sql = "INSERT INTO lead_question_answers (lead_id, emp_id, emp_name, question_num, question_text, answer) 
            VALUES ('$lead_id', '$emp_id', '$esc_emp_name', '$question_num', '$esc_qtext', '$esc_ans')";

    if (mysqli_query($con, $sql)) {
        $ans_id = mysqli_insert_id($con);
        // Also log into lead_followups for activity stream visibility
        $followup_remark = mysqli_real_escape_string($con, "[Q$question_num: $question_text]\n$answer (by $emp_name)");
        $today = date('Y-m-d');
        mysqli_query($con, "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                            VALUES ('$lead_id', '$today', 'Follow-up Q&A', 'Question Response', '$followup_remark')");

        echo json_encode([
            'status' => 'success',
            'message' => 'Answer saved successfully',
            'answer' => [
                'id' => $ans_id,
                'emp_id' => $emp_id,
                'emp_name' => $emp_name,
                'question_num' => $question_num,
                'question_text' => $question_text,
                'answer' => $answer,
                'time_ago' => 'just now',
                'can_delete' => true
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
        echo json_encode(['status' => 'error', 'message' => 'Invalid answer ID']);
        exit;
    }

    $del_sql = "DELETE FROM lead_question_answers WHERE id = '$answer_id' AND lead_id = '$lead_id' AND (emp_id = '$emp_id' OR '$emp_id' = 0)";
    if (mysqli_query($con, $del_sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Answer removed']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
