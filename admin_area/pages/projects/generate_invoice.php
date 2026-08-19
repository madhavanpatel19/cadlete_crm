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

// Fetch project + client details
$proj_q = mysqli_query($con, "
    SELECT cp.*, c.name AS client_name, c.email AS email, c.mobile AS mobile,
           c.country AS client_country, c.company_name
    FROM client_projects cp
    LEFT JOIN clients c ON cp.client_id = c.id
    WHERE cp.id = '$project_id'
");
$project = mysqli_fetch_assoc($proj_q);
if (!$project) {
    die("Project not found.");
}

$phases_q = mysqli_query($con, "SELECT * FROM project_budget_phases WHERE project_id = '$project_id' ORDER BY id ASC");
$phases = [];
$grand_total = 0;

while ($row = mysqli_fetch_assoc($phases_q)) {
    $p_name_esc = mysqli_real_escape_string($con, $row['phase_name']);
    $get_pmts = mysqli_query($con, "SELECT * FROM project_phase_payments WHERE project_id = '$project_id' AND phase_name = '$p_name_esc' ORDER BY id ASC");
    $pmt_sum = 0;
    if ($get_pmts && mysqli_num_rows($get_pmts) > 0) {
        while ($pmt = mysqli_fetch_assoc($get_pmts)) {
            $pmt_sum += (float)$pmt['amount'];
        }
    } else {
        $pmt_sum = (float)$row['received_amount'];
    }

    if ($pmt_sum > 0) {
        $row['received_amount'] = $pmt_sum;
        $grand_total += $pmt_sum;
        $phases[] = $row;
    }
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

// Totals — only from received phases
$grand_total    = array_sum(array_column($phases, 'received_amount'));
$total_cost     = (float)($project['budget'] ?? 0);

// Invoice number: INV + project_id padded
$invoice_no     = 'INV-' . str_pad($project_id, 4, '0', STR_PAD_LEFT);
$invoice_date   = date("d/m/Y");
$current_date   = date("d F Y");
$clean_proj_name = preg_replace('/[^\w\s\-]/', '', $project['project_name'] ?? 'Project');
$pdf_doc_title = trim($clean_proj_name) . ' - Invoice - ' . $current_date;
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
            --blue: #0a2d6e;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            margin: 0;
            background: #ececec;
            font-family: 'Montserrat', Arial, sans-serif;
            color: #333;
            font-size: 12px;
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
        .invoice-sheet {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            padding: 0;
        }

        /* ── HEADER ── */
        .inv-header {
            text-align: center;
            padding: 18px 20px 10px;
            border-bottom: 2px solid #000;
        }

        .inv-header .company-name {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 1px;
            color: #000;
            text-transform: uppercase;
            margin: 0 0 5px;
        }

        .inv-header .company-addr {
            font-size: 9.5px;
            color: #333;
            font-weight: 500;
            margin: 0;
        }

        /* ── DEBIT / INVOICE ROW ── */
        .inv-type-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 12px;
            background: #f0f0f0;
            border-bottom: 1px solid #999;
        }

        .inv-type-row .debit-label {
            font-size: 10px;
            font-weight: 700;
            color: #000;
        }

        .inv-type-row .inv-title {
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 6px;
            color: #000;
            text-transform: uppercase;
        }

        .inv-type-row .original-label {
            font-size: 10px;
            font-weight: 700;
            color: #000;
        }

        /* ── INVOICE META ── */
        .inv-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 12px 5px;
            border-bottom: 1px solid #ccc;
        }

        .inv-meta-row .inv-no {
            font-size: 10px;
            font-weight: 700;
            color: #000;
        }

        .inv-meta-row .inv-date {
            font-size: 10px;
            font-weight: 700;
            color: #000;
            text-align: right;
        }

        /* ── BUYER / CONSIGNEE BLOCK ── */
        .party-row {
            display: flex;
            border-bottom: 1px solid #ccc;
        }

        .party-block {
            flex: 1;
            padding: 8px 12px;
        }

        .party-block:first-child {
            border-right: 1px solid #ccc;
        }

        .party-label {
            font-size: 9px;
            font-weight: 800;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .party-name {
            font-size: 13px;
            font-weight: 900;
            color: #000;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .party-detail {
            font-size: 9.5px;
            color: #333;
            font-weight: 500;
            margin-bottom: 3px;
        }

        .party-detail strong {
            font-weight: 700;
            color: #000;
        }

        /* ── TABLE ── */
        .inv-table-wrap {
            padding: 0;
        }

        .inv-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inv-table thead tr {
            background: #f0f0f0;
        }

        .inv-table thead th {
            padding: 7px 8px;
            border: 1px solid #bbb;
            font-size: 9.5px;
            font-weight: 800;
            color: #000;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .inv-table thead th.th-particulars {
            text-align: left;
            width: 35%;
        }

        .inv-table tbody td {
            padding: 7px 8px;
            border: 1px solid #ccc;
            font-size: 10px;
            color: #333;
            vertical-align: top;
            text-align: center;
        }

        .inv-table tbody td.td-sr {
            font-weight: 700;
            color: #000;
            width: 30px;
            text-align: center;
        }

        .inv-table tbody td.td-particulars {
            text-align: left;
            font-weight: 600;
            color: #000;
        }

        .inv-table tbody td.td-particulars .phase-title {
            font-weight: 800;
            color: #000;
            font-size: 10.5px;
        }

        .inv-table tbody td.td-particulars .phase-desc {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
        }

        .inv-table tbody td.td-particulars .phase-method {
            font-size: 9px;
            color: #0a2d6e;
            margin-top: 2px;
            font-weight: 600;
        }

        .inv-table tbody td.td-particulars .phase-date {
            font-size: 9px;
            color: #555;
            margin-top: 1px;
        }

        .inv-table tbody td.td-amount {
            font-weight: 800;
            color: #000;
            font-size: 10.5px;
            white-space: nowrap;
        }

        .inv-table tbody td.td-qty {
            font-weight: 600;
            color: #333;
        }

        .inv-table tbody td.td-rate {
            font-weight: 600;
            color: #333;
            white-space: nowrap;
        }

        /* Sub-total / Grand total rows */
        .total-row td {
            border: 1px solid #bbb;
            padding: 6px 8px;
            font-weight: 700;
            font-size: 10px;
        }

        .total-row .total-label {
            text-align: right;
            color: #000;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .total-row .total-val {
            text-align: center;
            font-weight: 900;
            color: #000;
            font-size: 11px;
            white-space: nowrap;
        }

        /* Grand total row */
        .grand-row td {
            border: 1px solid #888;
            padding: 8px;
            font-weight: 900;
            font-size: 11px;
        }

        .grand-row .grand-label {
            text-align: right;
            color: #000;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f0f0f0;
        }

        .grand-row .grand-val {
            text-align: center;
            font-size: 14px;
            font-weight: 900;
            color: #000;
            background: #f0f0f0;
            white-space: nowrap;
        }

        /* ── BANK + FOOTER ── */
        .bottom-section {
            display: flex;
            border-top: 1px solid #ccc;
            min-height: 80px;
        }

        .bank-block {
            flex: 1.5;
            padding: 10px 12px;
            border-right: 1px solid #ccc;
        }

        .bank-block .bank-label {
            font-size: 9px;
            font-weight: 800;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .bank-block .bank-name {
            font-size: 10.5px;
            font-weight: 800;
            color: #000;
            margin-bottom: 3px;
        }

        .bank-block .bank-detail {
            font-size: 9.5px;
            color: #333;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .bank-block .gstin-block {
            margin-top: 10px;
        }

        .bank-block .gstin-detail {
            font-size: 9.5px;
            font-weight: 600;
            color: #000;
            margin-bottom: 2px;
        }

        /* .sign-block {
            flex: 1;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .sign-block .for-label {
            font-size: 10px;
            font-weight: 800;
            color: #000;
            text-align: right;
        }

        .sign-block .auth-label {
            font-size: 9px;
            font-weight: 600;
            color: #555;
            text-align: right;
            margin-top: 45px;
        }
 */
        .sign-block {
            text-align: center;
            margin-top: 20px;
            margin: 20px;
        }

        .for-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .sign-logo {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sign-logo img {
            max-width: 130px;
            max-height: 60px;
            object-fit: contain;
        }

        .auth-label {
            font-size: 13px;
            margin-top: 5px;
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 4px;
        }

        /* ── TERMS ── */
        .terms-row {
            border-top: 1px solid #ccc;
            padding: 6px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .terms-row .terms-label {
            font-size: 9px;
            font-weight: 700;
            color: #000;
        }

        /* ── SUMMARY BOXES ── */
        .summary-strip {
            display: flex;
            border-bottom: 1px solid #ccc;
            background: #fafafa;
        }

        .summary-item {
            flex: 1;
            padding: 8px 12px;
            text-align: center;
            border-right: 1px solid #ddd;
        }

        .summary-item:last-child {
            border-right: none;
        }

        .summary-item .si-label {
            font-size: 8.5px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 3px;
        }

        .summary-item .si-val {
            font-size: 14px;
            font-weight: 900;
            color: #000;
        }

        .si-val.green {
            color: #16a34a;
        }

        .si-val.red {
            color: #dc2626;
        }

        /* ── ACTIONS ── */
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

        .no-phases-msg {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
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

            .invoice-sheet {
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
            <i class="fa fa-print"></i> Print / Save PDF
        </button>
        <a href="javascript:window.close();" class="btn-premium-cancel">Back</a>
    </div>

    <div class="page-wrap">
        <div class="invoice-sheet">

            <!-- ── HEADER ── -->
            <div class="inv-header">
                <div class="company-name">CADLETE DESIGNS</div>
                <div class="company-addr">
                    A-106, Sun South Street, near Safal Parisar 1, South Bopal, Bopal, Ahmedabad, Gujarat 380058 &nbsp;|&nbsp;
                    Phone: +91 83202 11773 &nbsp;|&nbsp; Email: info@cadletedesigns.com
                </div>
            </div>

            <!-- ── TAX INVOICE ROW ── -->
            <div class="inv-type-row">
                <span class="debit-label">DEBIT</span>
                <span class="inv-title">T A X &nbsp; I N V O I C E</span>
                <span class="original-label">ORIGINAL FOR</span>
            </div>

            <!-- ── INVOICE NO + DATE ── -->
            <div class="inv-meta-row">
                <div class="inv-no">
                    Invoice No: &nbsp;<strong><?php echo $invoice_no; ?></strong>
                </div>
                <div class="inv-date">
                    Invoice: &nbsp;<strong><?php echo $invoice_date; ?></strong>
                </div>
            </div>

            <!-- ── BUYER / CONSIGNEE ── -->
            <div class="party-row">
                <!-- Buyer -->
                <div class="party-block">
                    <div class="party-label">Details Of Buyer :</div>
                    <div class="party-name"><?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></div>
                    <div class="party-detail">
                        <strong>Project :</strong> <?php echo htmlspecialchars($project['project_name'] ?? ''); ?>
                    </div>
                    <div class="party-detail">
                        <strong>Country :</strong> <?php echo htmlspecialchars($project['client_country'] ?? ''); ?>
                    </div>
                    <div class="party-detail">
                        <strong>Contact :</strong> <?php echo htmlspecialchars($project['mobile'] ?? ''); ?>
                    </div>
                    <!-- <div class="party-detail">
                        <strong>Email:</strong> <?php echo htmlspecialchars($project['email'] ?? ''); ?>
                    </div> -->
                </div>

                <!-- Consignee -->
                <div class="party-block">
                    <!-- <div class="party-label">Details Of Consignee (if any) :</div>
                    <div class="party-detail" style="margin-top: 12px;">
                        <strong>Project :</strong> <?php echo htmlspecialchars($project['project_name'] ?? ''); ?>
                    </div>
                    <div class="party-detail" style="margin-top: 6px;">
                        <strong>State :</strong> &nbsp;&nbsp; <strong>Code :</strong>
                    </div>
                    <div class="party-detail"><strong>GSTIN No:</strong></div>
                    <div class="party-detail"><strong>PAN No:</strong></div> -->
                </div>
            </div>

            <!-- ── SUMMARY STRIP ── -->
            <!-- <div class="summary-strip">
                <div class="summary-item">
                    <div class="si-label">Total Project Budget</div>
                    <div class="si-val"><?php echo $sym . ' ' . number_format($total_cost, 0); ?></div>
                </div>
                <div class="summary-item">
                    <div class="si-label">Total Amount Received</div>
                    <div class="si-val green"><?php echo $sym . ' ' . number_format($grand_total, 0); ?></div>
                </div>
                <div class="summary-item">
                    <div class="si-label">Outstanding Balance</div>
                    <?php $outstanding = $total_cost - $grand_total; ?>
                    <div class="si-val <?php echo $outstanding > 0 ? 'red' : 'green'; ?>">
                        <?php echo $sym . ' ' . number_format(abs($outstanding), 0); ?>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="si-label">Phases Received</div>
                    <div class="si-val"><?php echo count($phases); ?></div>
                </div>
            </div> -->

            <!-- ── ITEMS TABLE ── -->
            <div class="inv-table-wrap">
                <?php if (empty($phases)): ?>
                    <div class="no-phases-msg">
                        ⚠️ No received payments found for this project. Payments will appear here once marked as received.
                    </div>
                <?php else: ?>
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th style="width: 28px;">Sr.</th>
                                <th class="th-particulars">Particulars</th>
                                <th style="width: 70px;">HSN Code</th>
                                <th style="width: 90px;">Payment Date</th>
                                <th style="width: 100px;">Payment Method</th>
                                <th style="width: 120px;">Amount (<?php echo $currency; ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $running_total = 0;
                            foreach ($phases as $i => $phase):
                                $rec_fmt  = number_format((float)$phase['received_amount'], 2);
                                $running_total += (float)$phase['received_amount'];
                                $date_fmt = (!empty($phase['received_date']) && $phase['received_date'] !== '0000-00-00')
                                    ? date("d M Y", strtotime($phase['received_date'])) : '—';
                                $method   = !empty($phase['remark'])      ? htmlspecialchars($phase['remark'])      : '—';
                                $desc     = !empty($phase['description'])  ? htmlspecialchars($phase['description']) : '';
                            ?>
                                <tr>
                                    <td class="td-sr"><?php echo $i + 1; ?></td>
                                    <td class="td-particulars">
                                        <div class="phase-title"><?php echo htmlspecialchars($phase['phase_name']); ?></div>
                                        <?php if ($desc): ?>
                                            <div class="phase-desc"><?php echo $desc; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>—</td>
                                    <td style="font-size: 9.5px; font-weight: 600;"><?php echo $date_fmt; ?></td>
                                    <td style="font-size: 9.5px; font-weight: 600;"><?php echo $method; ?></td>
                                    <td class="td-amount"><?php echo $sym . ' ' . $rec_fmt; ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Empty filler rows for professional look -->
                            <?php
                            $min_rows = 8;
                            $filled   = count($phases);
                            for ($r = $filled; $r < $min_rows; $r++):
                            ?>
                                <tr style="height: 28px;">
                                    <td class="td-sr">&nbsp;</td>
                                    <td class="td-particulars">&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td class="td-amount">&nbsp;</td>
                                </tr>
                            <?php endfor; ?>

                            <!-- Sub Total -->
                            <!-- <tr class="total-row">
                                <td colspan="5" class="total-label" style="background: #f9f9f9;">Sub Total</td>
                                <td class="total-val"><?php echo $sym . ' ' . number_format($grand_total, 2); ?></td>
                            </tr> -->
                        </tbody>
                    </table>

                    <!-- Grand Total -->
                    <table class="inv-table" style="margin-top: -1px;">
                        <tbody>
                            <tr class="grand-row">
                                <td colspan="5" class="grand-label" style="width: 71%;">Grand Total</td>
                                <td class="grand-val" style="width: 16.5%;"><?php echo $sym . ' ' . number_format($grand_total, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- ── BOTTOM: BANK + SIGNATURE ── -->
            <div class="bottom-section">
                <div class="bank-block">
                    <div class="bank-label">Bank Details:</div>
                    <div class="bank-name">KOTAK MAHINDRA BANK</div>
                    <div class="bank-detail">A/C No: 3945244898 &nbsp; IFSC CODE: KKBK0002791</div>
                    <div class="bank-detail">BRANCH: SHYAM BUILDING 80 FEET ROAD RT</div>

                    <div class="gstin-block">
                        <div class="gstin-detail">Company's GSTIN No : 24BPFPR8426E1ZT</div>
                        <div class="gstin-detail">Company's PAN No &nbsp;&nbsp; : BPFPE 8426 E</div>
                    </div>
                </div>
                <div class="sign-block">
                    <div class="for-label">For, CADLETE DESIGNS</div>
                    <div class="sign-logo">
                        <img src="../../images/logo_sign.png" alt="CADLETE DESIGNS sign">
                    </div>
                    <div class="auth-label">Authorised Signatory</div>
                </div>
            </div>

            <!-- ── TERMS ── -->
            <div class="terms-row">
                <div class="terms-label">Terms: Payment received as per phase-wise agreement.</div>
                <div class="terms-label" style="color: #555; font-style: italic; font-size: 8.5px;">
                    Generated on: <?php echo $current_date; ?> &nbsp;|&nbsp; This is a computer generated invoice.
                </div>
            </div>

        </div>
    </div>

</body>

</html>