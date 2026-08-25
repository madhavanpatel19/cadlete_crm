<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

// Auto-migration check: extra_leaves column in emp_list
$checkColExtra = @mysqli_query($con, "SHOW COLUMNS FROM emp_list LIKE 'extra_leaves'");
if ($checkColExtra && mysqli_num_rows($checkColExtra) == 0) {
    @mysqli_query($con, "ALTER TABLE emp_list ADD COLUMN extra_leaves INT(11) DEFAULT 0");
}

$emp_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : (isset($_POST['emp_id']) ? intval($_POST['emp_id']) : 0);

if ($emp_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Employee ID.']);
    exit;
}

// Fetch employee details
$emp_q = mysqli_query($con, "SELECT id, name, extra_leaves, department, designation FROM emp_list WHERE id = $emp_id LIMIT 1");
if (!$emp_q || mysqli_num_rows($emp_q) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    exit;
}
$emp_data = mysqli_fetch_assoc($emp_q);

// Calculate default system policy sum from leave_types table
$policy_sum = 0;
$lt_sum_q = @mysqli_query($con, "SELECT SUM(num_of_leave) as total FROM leave_types WHERE deleted_at IS NULL");
if ($lt_sum_q && $row = mysqli_fetch_assoc($lt_sum_q)) {
    if ($row['total'] !== null && $row['total'] !== '') {
        $policy_sum = intval($row['total']);
    }
}

$extra_leaves = intval($emp_data['extra_leaves'] ?? 0);
if ($extra_leaves < 0) {
    $extra_leaves = 0;
}

$allowed_leaves = $policy_sum + $extra_leaves;

// Fetch leave applications for this employee first to calculate per-type usage
$applications = [];
$used_leaves = 0;
$used_by_type = [];

$app_q = mysqli_query($con, "SELECT la.*, lt.leave_name 
                             FROM leave_applications la 
                             LEFT JOIN leave_types lt ON la.leave_type_id = lt.id 
                             WHERE la.emp_id = $emp_id 
                             ORDER BY la.leave_from DESC, la.id DESC");

if ($app_q) {
    while ($app = mysqli_fetch_assoc($app_q)) {
        $from = strtotime($app['leave_from']);
        $to = strtotime($app['leave_to']);
        $days = 1;
        if ($from && $to && $to >= $from) {
            $days = round(($to - $from) / (60 * 60 * 24)) + 1;
        }

        $status = strtolower(trim($app['status'] ?? 'pending'));
        if ($status === 'approved') {
            $used_leaves += $days;
            $lt_id = intval($app['leave_type_id']);
            if (!isset($used_by_type[$lt_id])) {
                $used_by_type[$lt_id] = 0;
            }
            $used_by_type[$lt_id] += $days;
        }

        $applications[] = [
            'id' => intval($app['id']),
            'leave_type_id' => intval($app['leave_type_id']),
            'leave_name' => !empty($app['leave_name']) ? $app['leave_name'] : 'Extra Leaves',
            'leave_from' => $app['leave_from'],
            'leave_to' => $app['leave_to'],
            'days' => $days,
            'reason' => $app['reason'] ?? '',
            'status' => $status,
            'created_at' => $app['created_at'] ?? ''
        ];
    }
}

// Fetch leave types with used breakdown
$leave_types = [];
$lt_q = mysqli_query($con, "SELECT id, leave_name, num_of_leave FROM leave_types WHERE deleted_at IS NULL ORDER BY leave_name ASC");
if ($lt_q) {
    while ($lt_row = mysqli_fetch_assoc($lt_q)) {
        $l_id = intval($lt_row['id']);
        $leave_types[] = [
            'id' => $l_id,
            'leave_name' => $lt_row['leave_name'],
            'num_of_leave' => intval($lt_row['num_of_leave']),
            'used_leave' => isset($used_by_type[$l_id]) ? intval($used_by_type[$l_id]) : 0
        ];
    }
}

$remaining_leaves = max(0, $allowed_leaves - $used_leaves);

echo json_encode([
    'status' => 'success',
    'emp' => [
        'id' => intval($emp_data['id']),
        'name' => $emp_data['name'],
        'department' => $emp_data['department'],
        'designation' => $emp_data['designation'],
        'policy_sum' => $policy_sum,
        'extra_leaves' => $extra_leaves,
        'extra_leaves_used' => isset($used_by_type[0]) ? intval($used_by_type[0]) : 0,
        'allowed_leaves' => $allowed_leaves,
        'used_leaves' => $used_leaves,
        'remaining_leaves' => $remaining_leaves
    ],
    'leave_types' => $leave_types,
    'applications' => $applications
]);
?>
