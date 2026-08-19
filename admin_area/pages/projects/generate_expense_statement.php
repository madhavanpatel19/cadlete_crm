<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Require project_id
if (!isset($_GET['project_id'])) {
    die("Project ID is required.");
}

$project_id = (int) $_GET['project_id'];

// Fetch project details
$proj_q = mysqli_query($con, "SELECT * FROM client_projects WHERE id = '$project_id'");
$project = mysqli_fetch_assoc($proj_q);
if (!$project) {
    die("Project not found.");
}

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

// Fetch expenses
$exp_q = mysqli_query($con, "SELECT * FROM project_expenses WHERE project_id = '$project_id' ORDER BY expense_date ASC, id ASC");
$expenses = [];
$total_sum = 0;
$total_qty = 0;

if ($exp_q) {
    while ($r = mysqli_fetch_assoc($exp_q)) {
        $cost = floatval($r['cost']);
        $qty = intval($r['qty']);
        $total = floatval($r['total_cost']);
        if ($total <= 0 && $cost > 0) {
            $total = $cost * ($qty > 0 ? $qty : 1);
        }
        $total_sum += $total;
        $total_qty += ($qty > 0 ? $qty : 1);

        $expenses[] = [
            'id' => intval($r['id']),
            'item_name' => $r['item_name'],
            'qty' => $qty > 0 ? $qty : 1,
            'cost' => $cost,
            'total_cost' => $total,
            'expense_date' => (!empty($r['expense_date']) && $r['expense_date'] !== '0000-00-00') ? date('d M Y', strtotime($r['expense_date'])) : '—',
            'ordered_from' => !empty($r['ordered_from']) ? $r['ordered_from'] : '—',
            'paid_by' => !empty($r['paid_by']) ? $r['paid_by'] : '—',
            'invoice_no' => !empty($r['invoice_no']) ? $r['invoice_no'] : '—'
        ];
    }
}

$sym = '₹';
$current_date = date("d F Y");
$clean_proj_name = preg_replace('/[^\w\s\-]/', '', $project['project_name'] ?? 'Project');
$pdf_doc_title = trim($clean_proj_name) . ' - Expense Statement - ' . $current_date;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pdf_doc_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="../../css/style.css" rel="stylesheet">
    <style>
        :root {
            --red: #e31e24;
            --dark: #222;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            background: #ececec;
            font-family: 'Montserrat', sans-serif;
            color: #333;
        }

        @page {
            size: A4;
            margin: 0;
        }

        .page-wrap {
            width: 100%;
            margin: 20px auto;
            padding: 0;
            display: flex;
            justify-content: center;
        }

        .letter-sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10);
            padding: 0 0 110px;
        }

        /* HEADER */
        .top-shape {
            height: 80px;
            position: relative;
            background: transparent;
        }

        .top-shape .black-bar {
            width: 65%;
            height: 45px;
            background: #222;
            clip-path: polygon(0 0, 100% 0, 92% 100%, 0 100%);
        }

        .top-shape .red-bar {
            width: 50%;
            height: 12px;
            background: var(--red);
            margin-top: 12px;
            clip-path: polygon(0 0, 100% 0, 96% 100%, 0 100%);
        }

        .brand-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 0 25px;
            margin-top: -65px;
        }

        .brand-row img {
            height: 85px;
            object-fit: contain;
        }

        /* TITLE */
        .title {
            text-align: center;
            color: var(--red);
            font-weight: 800;
            font-size: 22px;
            letter-spacing: .5px;
            margin: 8px 0 6px;
            text-transform: uppercase;
        }

        .title-underline {
            display: flex;
            align-items: center;
            margin: 0 25px 24px;
            height: 6px;
        }

        .title-underline .left {
            width: 44%;
            height: 4px;
            background: #121212;
        }

        .title-underline .right {
            width: 56%;
            height: 4px;
            background: var(--red);
        }

        /* META ROW */
        .meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0 25px;
            margin-bottom: 20px;
            gap: 20px;
        }

        .meta .project-name {
            font-size: 18px;
            font-weight: 800;
            color: #1e293b;
        }

        .meta .project-sub {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-top: 3px;
        }

        .meta .date-right {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            text-align: right;
            white-space: nowrap;
        }

        /* SUMMARY BOXES */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            padding: 0 25px;
            margin-bottom: 22px;
        }

        .summary-box {
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px 18px;
            background: #f8fafc;
        }

        .summary-box .s-label {
            font-size: 9px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 6px;
        }

        .summary-box .s-value {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.5px;
        }

        .s-value.total {
            color: #ef4444;
        }

        .s-value.count {
            color: #0f172a;
        }

        .s-value.date {
            color: #6366f1;
        }

        .summary-box .s-bar {
            width: 28px;
            height: 4px;
            border-radius: 10px;
            margin-top: 8px;
        }

        /* TABLE */
        .content {
            padding: 0 25px;
        }

        .section-label {
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 10px;
        }

        .stmt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            table-layout: fixed;
            page-break-inside: auto;
        }

        .stmt-table thead {
            display: table-header-group;
        }

        .stmt-table thead tr {
            background: #52525b;
        }

        .stmt-table thead th {
            padding: 10px 6px;
            color: #fff;
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            border: none;
            white-space: normal;
            word-wrap: break-word;
            vertical-align: middle;
            line-height: 1.25;
        }

        .stmt-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .stmt-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .stmt-table tbody td {
            padding: 9px 7px;
            text-align: center;
            color: #334155;
            font-weight: 500;
            vertical-align: middle;
        }

        .stmt-table tbody td.item-name {
            font-weight: 700;
            color: #0f172a;
            text-align: left;
            word-break: break-word;
        }

        .stmt-table tbody td.amount {
            font-weight: 800;
            color: #1e293b;
        }

        .stmt-table tfoot {
            display: table-footer-group;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .stmt-table tfoot tr {
            background: #ffedeb;
            border-top: 2px solid #fecdd3;
        }

        .stmt-table tfoot td {
            padding: 12px 8px;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .no-records {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
        }

        /* FOOTER CONTAINER */
        .footer-container {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            z-index: 20;
        }

        .footer-bar {
            position: relative;
            background: #d1d1d1;
            padding: 15px 52px;
        }

        .footer-inner {
            display: flex;
            justify-content: flex-start;
            gap: 40px;
            flex-wrap: nowrap;
            font-size: 13px;
            color: #222;
            font-weight: 600;
            padding-right: 160px;
        }

        .footer-col {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .footer-item i {
            color: #222;
            width: 18px;
            text-align: center;
            font-size: 16px;
        }

        .corner-red {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 180px;
            height: 80px;
            background: var(--red);
            clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
            z-index: 10;
        }

        /* ACTION BUTTONS */
        .actions {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            display: flex;
            gap: 10px;
        }

        @media print {

            html,
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }

            .page-wrap {
                margin: 0;
                padding: 0;
                width: 100%;
                display: block;
            }

            .letter-sheet {
                box-shadow: none;
                width: 210mm;
                min-height: 297mm;
                margin: 0 auto;
                position: relative;
                padding: 0 0 110px;
            }

            .footer-container {
                position: absolute;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                z-index: 20;
            }

            .actions {
                display: none !important;
            }

            .stmt-table {
                page-break-inside: auto;
            }

            .stmt-table thead {
                display: table-header-group;
            }

            .stmt-table tbody tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .stmt-table tfoot {
                display: table-footer-group;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <!-- Action Buttons -->
    <div class="actions">
        <button onclick="window.print()" class="btn-premium-add">
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
        <a href="javascript:window.close();" class="btn-premium-cancel">Back</a>
    </div>

    <div class="page-wrap">
        <div class="letter-sheet">

            <!-- HEADER -->
            <div class="top-shape">
                <div class="black-bar"></div>
                <div class="red-bar"></div>
            </div>
            <div class="brand-row">
                <img src="../../images/Cadlete_logo%20Landscape.png" alt="CADLETE DESIGNS Logo">
            </div>

            <!-- TITLE -->
            <div class="title">Project Expense Statement</div>
            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>

            <!-- META -->
            <div class="meta">
                <div>
                    <div class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></div>
                    <div class="project-sub">Project Expense Statement</div>
                </div>
                <div class="date-right"><?php echo $current_date; ?></div>
            </div>



            <!-- TABLE CONTENT -->
            <div class="content">
                <div class="section-label">Expense History</div>

                <?php if (empty($expenses)): ?>
                    <div class="no-records">No expense records found for this project.</div>
                <?php else: ?>
                    <table class="stmt-table">
                        <thead>
                            <tr>
                                <th style="text-align:center; width:5%;">#</th>
                                <th style="text-align:center; width:25%;">Item Name</th>
                                <th style="text-align:center; width:7%;">Qty</th>
                                <th style="text-align:center; width:13%;">Cost (<?php echo $sym; ?>)</th>
                                <th style="text-align:center; width:15%;">Total Cost (<?php echo $sym; ?>)</th>
                                <th style="text-align:center; width:12%;">Date</th>
                                <th style="text-align:center; width:13%;">Ordered From</th>
                                <th style="text-align:center; width:10%;">Paid By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $i => $exp): ?>
                                <tr>
                                    <td style="font-weight: 700; text-align: center; color: #475569;"><?php echo $i + 1; ?></td>
                                    <td class="item-name"><?php echo htmlspecialchars($exp['item_name']); ?></td>
                                    <td style="text-align: center; font-weight: 600;"><?php echo $exp['qty']; ?></td>
                                    <td style="text-align: center; font-weight: 600; color: #475569;"><?php echo number_format($exp['cost'], 2); ?></td>
                                    <td class="amount" style="text-align: center; font-weight: 800; color: #0f172a;"><?php echo number_format($exp['total_cost'], 2); ?></td>
                                    <td style="text-align: center; color: #64748b; font-weight: 600;"><?php echo $exp['expense_date']; ?></td>
                                    <td style="text-align: center; color: #334155; font-weight: 500;"><?php echo htmlspecialchars($exp['ordered_from']); ?></td>
                                    <td style="text-align: center; color: #334155; font-weight: 500;"><?php echo htmlspecialchars($exp['paid_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4" style="text-align: center; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">TOTAL EXPENSES:</td>
                                <td style="text-align: center; font-size: 14px; font-weight: 900; color: #ef4444;"><?php echo $sym . ' ' . number_format($total_sum, 2); ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>

            <!-- FOOTER CONTAINER -->
            <div class="footer-container">
                <div class="footer-bar">
                    <div class="footer-inner">
                        <div class="footer-col">
                            <div class="footer-item"><i class="fa fa-phone"></i>091 83202 11773</div>
                            <div class="footer-item"><i class="fa fa-envelope"></i> info@cadletedesigns.com</div>
                        </div>
                        <div class="footer-col">
                            <div class="footer-item"><i class="fa-solid fa-location-dot"></i> A-106, Sun South Street, Ahmedabad</div>
                            <div class="footer-item"><i class="fa fa-globe"></i> www.cadletedesigns.com</div>
                        </div>
                    </div>
                </div>
                <div class="corner-red"></div>
            </div>

        </div>
    </div>

</body>

</html>