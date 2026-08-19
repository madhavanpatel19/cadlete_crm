<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

// Auto-create table if missing
$create_table = "CREATE TABLE IF NOT EXISTS project_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    qty INT DEFAULT 1,
    cost DECIMAL(15,2) DEFAULT 0.00,
    total_cost DECIMAL(15,2) DEFAULT 0.00,
    expense_date DATE DEFAULT NULL,
    ordered_from VARCHAR(255) DEFAULT NULL,
    ordered_from_url VARCHAR(500) DEFAULT NULL,
    paid_by VARCHAR(255) DEFAULT NULL,
    invoice_no VARCHAR(255) DEFAULT NULL,
    invoice_file VARCHAR(255) DEFAULT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
@mysqli_query($con, $create_table);

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($project_id <= 0) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid Project ID']);
    exit();
}

// Fetch project info
$p_q = mysqli_query($con, "SELECT project_name, currency FROM client_projects WHERE id = $project_id LIMIT 1");
$project_name = 'Project';
$currency = 'INR';
if ($p_q && $pr = mysqli_fetch_assoc($p_q)) {
    $project_name = $pr['project_name'];
    $currency = !empty($pr['currency']) ? $pr['currency'] : 'INR';
}

$symbols = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'د.إ'];
$sym = '₹';

$get_exp = mysqli_query($con, "SELECT * FROM project_expenses WHERE project_id = $project_id ORDER BY expense_date DESC, id DESC");
$expenses = [];
$total_sum = 0;

if ($get_exp) {
    while ($r = mysqli_fetch_assoc($get_exp)) {
        $cost = floatval($r['cost']);
        $qty = intval($r['qty']);
        $total = floatval($r['total_cost']);
        if ($total <= 0 && $cost > 0) {
            $total = $cost * ($qty > 0 ? $qty : 1);
        }
        $total_sum += $total;

        $formatted_date = !empty($r['expense_date']) ? date('d M Y', strtotime($r['expense_date'])) : '-';

        $expenses[] = [
            'id' => intval($r['id']),
            'item_name' => $r['item_name'],
            'qty' => $qty,
            'cost' => $cost,
            'total_cost' => $total,
            'expense_date' => $formatted_date,
            'raw_date' => $r['expense_date'],
            'ordered_from' => $r['ordered_from'] ?? '',
            'ordered_from_url' => $r['ordered_from_url'] ?? '',
            'paid_by' => $r['paid_by'] ?? '',
            'invoice_no' => $r['invoice_no'] ?? '',
            'invoice_file' => $r['invoice_file'] ?? '',
            'attachment' => $r['attachment'] ?? ''
        ];
    }
}

if (ob_get_length()) ob_clean();
echo json_encode([
    'success' => true,
    'project_id' => $project_id,
    'project_name' => $project_name,
    'currency' => $currency,
    'symbol' => $sym,
    'expenses' => $expenses,
    'total_sum' => $total_sum,
    'formatted_total_sum' => number_format($total_sum, 2)
]);
