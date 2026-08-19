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

// Fetch project details from client_projects
$proj_q = mysqli_query($con, "SELECT * FROM client_projects WHERE id = '$project_id'");
$project = mysqli_fetch_assoc($proj_q);
if (!$project) {
    die("Project not found.");
}

// Fetch budget phases from project_budget_phases
$phases_q = mysqli_query($con, "SELECT * FROM project_budget_phases WHERE project_id = '$project_id' ORDER BY id ASC");
$phases = [];
$total_received = 0;

while ($row = mysqli_fetch_assoc($phases_q)) {
    $p_name_esc = mysqli_real_escape_string($con, $row['phase_name']);
    $get_pmts = mysqli_query($con, "SELECT * FROM project_phase_payments WHERE project_id = '$project_id' AND phase_name = '$p_name_esc' ORDER BY id ASC");
    $pmts = [];
    $pmt_sum = 0;
    if ($get_pmts && mysqli_num_rows($get_pmts) > 0) {
        while ($pmt = mysqli_fetch_assoc($get_pmts)) {
            $pmts[] = $pmt;
            $pmt_sum += (float)$pmt['amount'];
        }
    } else {
        $pmt_sum = (float)$row['received_amount'];
    }

    $row['received_amount'] = $pmt_sum;
    $row['payments'] = $pmts;
    $total_received += $pmt_sum;
    $phases[] = $row;
}

// Currency symbols
$currency_symbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'د.إ'
];
$currency = !empty($project['currency']) ? $project['currency'] : 'INR';
$sym      = $currency_symbols[$currency] ?? '₹';

// Totals
$total_cost     = (float)($project['budget'] ?? 0);
$total_pending  = $total_cost - $total_received;

$current_date = date("d F Y");
$clean_proj_name = preg_replace('/[^\w\s\-]/', '', $project['project_name'] ?? 'Project');
$pdf_doc_title = trim($clean_proj_name) . ' - Project Statement - ' . $current_date;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pdf_doc_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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

        /* ── PAGE WRAP ── */
        .page-wrap {
            width: 100%;
            margin: 20px auto;
            padding: 0;
            display: flex;
            justify-content: center;
        }

        /* ── A4 SHEET ── */
        .letter-sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10);
            padding: 0 0 110px;
        }

        /* ── HEADER: exact same as experience letter ── */
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

        /* ── TITLE ── */
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

        /* ── META ROW ── */
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

        /* ── SUMMARY BOXES ── */
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
            color: #0f172a;
        }

        .s-value.received {
            color: #16a34a;
        }

        .summary-box .s-bar {
            width: 28px;
            height: 4px;
            border-radius: 10px;
            margin-top: 8px;
        }

        /* ── TABLE ── */
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
        }

        .stmt-table thead tr {
            background: #1e293b;
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
        }

        .stmt-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .stmt-table tbody td {
            padding: 8px 7px;
            text-align: center;
            color: #334155;
            font-weight: 500;
            vertical-align: middle;
        }

        .stmt-table tbody td.phase-name {
            font-weight: 700;
            color: #0f172a;
            text-align: left;
            word-break: break-word;
            white-space: normal;
        }

        .stmt-table tbody td.amount {
            font-weight: 700;
            color: #0f172a;
        }

        .stmt-table tbody td.received-amt {
            font-weight: 800;
            color: #16a34a;
        }

        .no-phases {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
            font-size: 14px;
        }

        /* ── FOOTER: exact same as experience letter ── */
        .footer-bar {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
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

        /* ── RED CORNER: exact same as experience letter ── */
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

        /* ── ACTION BUTTONS ── */
        .actions {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            display: flex;
            gap: 10px;
        }

        .btn-print {
            background: #dd2127;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-weight: 700;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
        }

        .btn-back {
            background: #fff;
            border: 1px solid #ddd;
            color: #333;
            border-radius: 10px;
            padding: 12px 22px;
            font-weight: 700;
            font-size: 14px;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        @media print {
            body {
                background: #fff;
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
            }

            .actions {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <!-- Action Buttons -->
    <div class="actions">
        <button onclick="window.print()" class="btn-premium-add">
            <i class="fa fa-print"></i>Print / Save PDF
        </button>
        <a href="javascript:window.close();" class="btn-premium-cancel">Back</a>
    </div>

    <div class="page-wrap">
        <div class="letter-sheet">

            <!-- ── HEADER ── -->
            <div class="top-shape">
                <div class="black-bar"></div>
                <div class="red-bar"></div>
            </div>
            <div class="brand-row">
                <img src="../../images/Cadlete_logo%20Landscape.png" alt="CADLETE DESIGNS Logo">
            </div>

            <!-- ── TITLE ── -->
            <div class="title">Project Statement</div>
            <div class="title-underline">
                <div class="left"></div>
                <div class="right"></div>
            </div>

            <!-- ── META ── -->
            <div class="meta">
                <div>
                    <div class="project-name"><?php echo htmlspecialchars($project['project_name']); ?></div>
                    <div class="project-sub">Project Financial Statement</div>
                </div>
                <div class="date-right"><?php echo $current_date; ?></div>
            </div>

            <!-- ── SUMMARY ── -->
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="s-label">Total Estimated Cost</div>
                    <div class="s-value total"><?php echo $sym . ' ' . number_format($total_cost, 0); ?></div>
                    <div class="s-bar" style="background:#6366f1;"></div>
                </div>
                <div class="summary-box">
                    <div class="s-label">Collections Received</div>
                    <div class="s-value received"><?php echo $sym . ' ' . number_format($total_received, 0); ?></div>
                    <div class="s-bar" style="background:#22c55e;"></div>
                </div>
                <div class="summary-box">
                    <div class="s-label">Outstanding Balance</div>
                    <div class="s-value" style="color:<?php echo $total_pending > 0 ? '#ef4444' : '#16a34a'; ?>">
                        <?php echo ($total_pending < 0 ? '-' : '') . $sym . ' ' . number_format(abs($total_pending), 0); ?>
                    </div>
                    <div class="s-bar" style="background:<?php echo $total_pending > 0 ? '#ef4444' : '#22c55e'; ?>;"></div>
                </div>
            </div>

            <!-- ── PAYMENT TABLE ── -->
            <div class="content">
                <div class="section-label">Payment History</div>

                <?php if (empty($phases)): ?>
                    <div class="no-phases">No payment records found for this project.</div>
                <?php else: ?>
                    <table class="stmt-table">
                        <thead>
                            <tr>
                                <th style="text-align:center; width:4%;">#</th>
                                <th style="text-align:left; width:22%;">Phase</th>
                                <th style="text-align:left; width:18%;">Description</th>
                                <th style="width:12%;">Total Cost (<?php echo $sym; ?>)</th>
                                <th style="width:11%;">Received (<?php echo $sym; ?>)</th>
                                <th style="width:11%;">Pending (<?php echo $sym; ?>)</th>
                                <th style="width:11%;">Payment Method</th>
                                <th style="width:11%;">Received Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phases as $i => $phase):
                                $cost_val = (float)$phase['cost'];
                                $rec_val  = (float)$phase['received_amount'];
                                $pnd_val  = $cost_val - $rec_val;
                                $cost_fmt = number_format($cost_val, 0);
                                $rec_fmt  = number_format($rec_val, 0);
                                $pnd_fmt  = ($pnd_val < 0 ? '-' : '') . $sym . ' ' . number_format(abs($pnd_val), 0);
                                $date_fmt = (!empty($phase['received_date']) && $phase['received_date'] !== '0000-00-00')
                                    ? date("d M Y", strtotime($phase['received_date'])) : '—';

                                if (!empty($phase['payments'])) {
                                    if (count($phase['payments']) > 1) {
                                        $method = 'Multiple';
                                    } else {
                                        $method = htmlspecialchars($phase['payments'][0]['payment_method'] ?? $phase['remark'] ?? '—');
                                    }
                                } else {
                                    $method = !empty($phase['remark']) ? htmlspecialchars($phase['remark']) : '—';
                                }

                                $desc = !empty($phase['description']) ? htmlspecialchars($phase['description']) : '—';
                            ?>
                                <tr style="background: #ffffff; border-top: 1.5px solid #e2e8f0;">
                                    <td style="font-weight: 700; text-align: center; color: #475569;"><?php echo $i + 1; ?></td>
                                    <td class="phase-name" style="font-weight: 800; color: #0f172a; word-break: break-word; white-space: normal;"><?php echo htmlspecialchars($phase['phase_name']); ?></td>
                                    <td style="color: #64748b; font-size: 11px; text-align: left; word-break: break-word; white-space: normal;"><?php echo $desc; ?></td>
                                    <td class="amount" style="font-weight: 700;"><?php echo $sym . ' ' . $cost_fmt; ?></td>
                                    <td class="received-amt" style="font-weight: 800; color: #16a34a;"><?php echo $sym . ' ' . $rec_fmt; ?></td>
                                    <td style="font-weight: 800; color: <?php echo $pnd_val > 0 ? '#ef4444' : '#16a34a'; ?>; text-align: center;"><?php echo $pnd_fmt; ?></td>
                                    <td style="font-weight: 600; color: #475569; text-align: center;"><?php echo $method; ?></td>
                                    <td style="font-weight: 600; color: #475569; text-align: center;"><?php echo $date_fmt; ?></td>
                                </tr>
                                <?php if (!empty($phase['payments']) && count($phase['payments']) > 1): ?>
                                    <?php foreach ($phase['payments'] as $p_idx => $pmt):
                                        $p_amt_fmt = number_format((float)$pmt['amount'], 0);
                                        $p_date_fmt = (!empty($pmt['payment_date']) && $pmt['payment_date'] !== '0000-00-00')
                                            ? date("d M Y", strtotime($pmt['payment_date'])) : '—';
                                        $p_method = !empty($pmt['payment_method']) ? htmlspecialchars($pmt['payment_method']) : '—';
                                        $p_note   = !empty($pmt['note'])           ? htmlspecialchars($pmt['note'])           : '—';
                                    ?>
                                        <tr style="background: #f8fafc; font-size: 11px; color: #475569;">
                                            <td></td>
                                            <td style="padding-left: 12px; font-weight: 600; color: #dd2127; text-align: left; word-break: break-word; white-space: normal;">
                                                Payment #<?php echo $p_idx + 1; ?>
                                            </td>
                                            <td style="color: #64748b; font-size: 11px; text-align: left; word-break: break-word;"><?php echo $p_note; ?></td>
                                            <td style="text-align: center; color: #94a3b8;">—</td>
                                            <td class="received-amt" style="color: #16a34a; font-weight: 700; text-align: center;">
                                                <?php echo $sym . ' ' . $p_amt_fmt; ?>
                                            </td>
                                            <td style="text-align: center; color: #94a3b8;">—</td>
                                            <td style="font-weight: 500; color: #475569; text-align: center;"><?php echo $p_method; ?></td>
                                            <td style="font-weight: 500; color: #475569; text-align: center;"><?php echo $p_date_fmt; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ── FOOTER: exact same as experience letter ── -->
            <div class="footer-bar">
                <div class="footer-inner">
                    <div class="footer-col">
                        <div class="footer-item"><i class="fa fa-phone"></i>091 83202 11773
                        </div>
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>

</html>