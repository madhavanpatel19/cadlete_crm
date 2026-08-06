<?php
header('Content-Type: application/json');
if (!isset($con)) { include(__DIR__ . '/../../includes/db.php'); }

if (!isset($_GET['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing project ID']);
    exit;
}

$project_id = mysqli_real_escape_string($con, $_GET['project_id']);

$get_phases = "SELECT * FROM project_budget_phases WHERE project_id = '$project_id' ORDER BY id ASC";
$run_phases = mysqli_query($con, $get_phases);

$phases = [];
while ($row = mysqli_fetch_assoc($run_phases)) {
    $phase_name_esc = mysqli_real_escape_string($con, $row['phase_name']);
    $get_pmts = "SELECT * FROM project_phase_payments WHERE project_id = '$project_id' AND phase_name = '$phase_name_esc' ORDER BY id ASC";
    $run_pmts = mysqli_query($con, $get_pmts);
    $payments = [];
    if ($run_pmts && mysqli_num_rows($run_pmts) > 0) {
        while ($pmt = mysqli_fetch_assoc($run_pmts)) {
            $payments[] = [
                'amount' => (float)$pmt['amount'],
                'date' => $pmt['payment_date'],
                'method' => $pmt['payment_method'],
                'note' => $pmt['note']
            ];
        }
    } else if ((float)$row['received_amount'] > 0) {
        $payments[] = [
            'amount' => (float)$row['received_amount'],
            'date' => $row['received_date'] ?: date('Y-m-d'),
            'method' => $row['remark'] ?: 'Initial Payment',
            'note' => 'Initial payment'
        ];
    }

    $remark_val = (count($payments) > 1) ? 'Multiple' : $row['remark'];

    $phases[] = [
        'phase_name' => $row['phase_name'],
        'description' => $row['description'],
        'cost' => $row['cost'],
        'received_amount' => $row['received_amount'],
        'received_date' => $row['received_date'],
        'remark' => $remark_val,
        'payments' => $payments
    ];
}

$get_project = "SELECT currency FROM client_projects WHERE id = '$project_id'";
$run_project = mysqli_query($con, $get_project);
$project_data = mysqli_fetch_assoc($run_project);
$currency = !empty($project_data['currency']) ? $project_data['currency'] : 'INR';

echo json_encode([
    'success' => true, 
    'data' => $phases,
    'currency' => $currency
]);
?>
