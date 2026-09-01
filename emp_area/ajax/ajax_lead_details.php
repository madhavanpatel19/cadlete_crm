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

    echo json_encode([
        'status' => 'success',
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
        'followups' => $followups
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

echo json_encode(['status' => 'error', 'message' => 'Invalid Action']);
