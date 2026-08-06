<?php
if (session_status() == PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($con) || !$con) {
    include(__DIR__ . '/../../includes/db.php'); // emp_area/includes/db.php
}

if (!isset($_SESSION['emp_id'])) {

    // If AJAX request
    if (isset($_GET['ajax'])) {
        echo "SessionExpired";
        exit();
    }

    header("Location: ../../pages/auth/login.php");
    exit();
}

// ------------------ HELPERS ------------------ //
function format_money($n)
{
    return number_format((float)$n, 2);
}

function format_money_with_symbol($n, $currency_symbol)
{
    return '<span class="currency-symbol">' . $currency_symbol . '</span>' . format_money($n);
}

// Convert number to words
function number_to_words($number)
{
    $no = floor($number);
    $decimal = round(($number - $no) * 100);
    $words = array(
        '0' => 'Zero',
        '1' => 'One',
        '2' => 'Two',
        '3' => 'Three',
        '4' => 'Four',
        '5' => 'Five',
        '6' => 'Six',
        '7' => 'Seven',
        '8' => 'Eight',
        '9' => 'Nine',
        '10' => 'Ten',
        '11' => 'Eleven',
        '12' => 'Twelve',
        '13' => 'Thirteen',
        '14' => 'Fourteen',
        '15' => 'Fifteen',
        '16' => 'Sixteen',
        '17' => 'Seventeen',
        '18' => 'Eighteen',
        '19' => 'Nineteen',
        '20' => 'Twenty',
        '30' => 'Thirty',
        '40' => 'Forty',
        '50' => 'Fifty',
        '60' => 'Sixty',
        '70' => 'Seventy',
        '80' => 'Eighty',
        '90' => 'Ninety'
    );

    if ($no == 0) {
        $result = 'Zero';
    } else {
        $result = '';
        $units = array('', 'Thousand', 'Million', 'Billion');
        $i = 0;
        while ($no > 0) {
            $chunk = $no % 1000;
            if ($chunk) {
                $hundreds = floor($chunk / 100);
                $remainder = $chunk % 100;
                $str = '';
                if ($hundreds) {
                    $str .= $words[$hundreds] . ' Hundred';
                    if ($remainder) $str .= ' and ';
                }
                if ($remainder) {
                    if ($remainder < 21) {
                        $str .= $words[$remainder];
                    } else {
                        $tens = floor($remainder / 10) * 10;
                        $ones = $remainder % 10;
                        $str .= $words[$tens];
                        if ($ones) $str .= ' ' . $words[$ones];
                    }
                }
                if (!empty($units[$i])) $str .= ' ' . $units[$i];
                $result = trim($str . ' ' . $result);
            }
            $no = floor($no / 1000);
            $i++;
        }
    }
    if ($decimal > 0) {
        $result .= ' and ' . $decimal . '/100';
    }
    return $result;
}

$emp_id = (int)$_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

// Security: Force emp_id to the session value to prevent viewing others
$_GET['emp_id'] = $emp_id;

// Get selected month
$selected_month = isset($_GET['month']) && $_GET['month'] !== '' ? $_GET['month'] : date('Y-m');
$view_mode = isset($_GET['view']) && $_GET['view'] == '1';

// Fetch salary slip for this employee and month
$month_q = mysqli_real_escape_string($con, $selected_month);
$q = mysqli_query($con, "SELECT e.*, 
                h.basic_salary AS h_basic, 
                h.hra AS h_hra, 
                h.pf AS h_pf, 
                h.tax AS h_tax, 
                h.allowance AS h_allow, 
                h.deductions AS h_ded
         FROM emp_list e
         LEFT JOIN emp_salary_history h ON e.id = h.emp_id AND h.month = '$month_q'
         WHERE e.id = " . (int)$emp_id . " LIMIT 1");
$employee = ($q && mysqli_num_rows($q)) ? mysqli_fetch_assoc($q) : null;

$base_salary = $employee && $employee['salary'] !== '' ? (float)$employee['salary'] : 0.00;

// Salary calculation components (Prioritize history)
$base_salary_val = ($employee && $employee['h_basic'] !== null && $employee['h_basic'] > 0) ? (float)$employee['h_basic'] : (($employee && $employee['basic_salary'] !== null && $employee['basic_salary'] > 0) ? (float)$employee['basic_salary'] : (($base_salary <= 0) ? 30000.00 : (float)$base_salary));

$hra = ($employee && $employee['h_hra'] !== null) ? (float)$employee['h_hra'] : (($employee && $employee['hra'] !== null) ? (float)$employee['hra'] : round($base_salary_val * 0.20, 2));

$pf = ($employee && $employee['h_pf'] !== null) ? (float)$employee['h_pf'] : 0.00;

$tax = ($employee && $employee['h_tax'] !== null) ? (float)$employee['h_tax'] : 0.00;

$other_allow = ($employee && $employee['h_allow'] !== null) ? (float)$employee['h_allow'] : (($employee && $employee['allowance'] !== null) ? (float)$employee['allowance'] : 0.00);

$other_ded = ($employee && $employee['h_ded'] !== null) ? (float)$employee['h_ded'] : (($employee && $employee['deductions'] !== null) ? (float)$employee['deductions'] : 0.00);

$gross = $base_salary_val + $hra + $other_allow;
$total_deductions = $pf + $tax + $other_ded;
$net = $gross - $total_deductions;

$currency_symbol = '&#8377;';

// Handle AJAX request for viewing a slip
if (isset($_GET['ajax']) && isset($_GET['view']) && $employee) {
    ob_start();
?>
    <div id="slip-content" class="salary-slip card" style="margin:10px auto; padding:18px; max-width:820px;">
        <div class="slip-top-decor"></div>
        <div class="slip-header">
            <div class="company-left">
                <img src="../admin_area/images/cadlete_Black_logo_favicon.png" alt="CADLETE DESIGNS Logo" class="company-logo" style="max-height: 80px;" onerror="this.style.display='none'">
                <div class="company-center">
                    <h3 class="company-name">CADLETE DESIGNS</h3>
                    <div class="company-address">A-106, Sun South Street, Ahmedabad</div>
                    <div class="company-meta-small">Phone: 091 83202 11773 &nbsp;|&nbsp; Email: info@cadletedesigns.com</div>
                </div>
            </div>
            <div class="slip-meta">
                <h4>Salary Slip</h4>
                <div class="slip-id">Slip No: <strong><?php echo sprintf('%05d', $emp_id); ?></strong></div>
                <p><strong>Period:</strong> <?php echo date('F, Y', strtotime($selected_month . '-01')); ?></p>
            </div>
        </div>

        <div class="employee-info clearfix">
            <div class="emp-left">
                <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($emp_name); ?></p>
                <p><strong>Employee ID:</strong> <?php echo (int)$emp_id; ?></p>
            </div>
            <div class="emp-right">
                <p><strong>Pay Date:</strong> <?php echo date('t M Y', strtotime($selected_month . '-01')); ?></p>
            </div>
        </div>

        <div class="slip-tables">
            <table class="earn-ded-table">
                <thead>
                    <tr>
                        <th style="width:26%;">Earnings</th>
                        <th class="amt" style="width:24%;">Amount (<?php echo $currency_symbol; ?>)</th>
                        <th style="width:26%;">Deductions</th>
                        <th class="amt" style="width:24%;">Amount (<?php echo $currency_symbol; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic Salary</td>
                        <td class="amt"><?php echo format_money_with_symbol($base_salary_val, $currency_symbol); ?></td>
                        <td>Provident Fund (PF)</td>
                        <td class="amt"><?php echo format_money_with_symbol($pf, $currency_symbol); ?></td>
                    </tr>
                    <tr>
                        <td>House Rent Allowance (HRA)</td>
                        <td class="amt"><?php echo format_money_with_symbol($hra, $currency_symbol); ?></td>
                        <td>Leave Without Pay</td>
                        <td class="amt"><?php echo format_money_with_symbol($tax, $currency_symbol); ?></td>
                    </tr>
                    <tr>
                        <td>Other Allowances</td>
                        <td class="amt"><?php echo format_money_with_symbol($other_allow, $currency_symbol); ?></td>
                        <td>Other Deductions</td>
                        <td class="amt"><?php echo format_money_with_symbol($other_ded, $currency_symbol); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="totals">
                        <td><strong>Gross Pay</strong></td>
                        <td class="amt"><strong><?php echo format_money_with_symbol($gross, $currency_symbol); ?></strong></td>
                        <td><strong>Total Deductions</strong></td>
                        <td class="amt"><strong><?php echo format_money_with_symbol($total_deductions, $currency_symbol); ?></strong></td>
                    </tr>
                    <tr class="net">
                        <td class="net-label"><strong>Net Pay</strong></td>
                        <td class="net-amt"><strong><?php echo format_money_with_symbol($net, $currency_symbol); ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding-top:10px; font-size:12px; font-style:italic;">
                            <strong>Amount in words:</strong> <?php echo htmlspecialchars(number_to_words($net)); ?> Only
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="slip-signature clearfix" style="border-top: none; display: flex; align-items: flex-end; justify-content: space-between; margin-top: 20px;">
            <div class="sign-left" style="width: 60%; text-align: left; float: none;">
                <p style="font-size: 11px; color: #666; border-top: none; margin: 0; padding: 0; margin-bottom: 5px; display: block; font-weight: normal;">This is a system generated payslip.</p>
            </div>
            <div class="sign-right" style="width: 40%; float: none; text-align: right;">
                <div style="display: inline-block; text-align: center; position: relative; margin-top: 50px;">
                    <img src="../admin_area/images/logo_sign.png" alt="Signature" class="sign-image" style="height: 73px; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: -5px; z-index: 1;">
                    <p style="margin: 0; border-top: 1px solid #444; padding-top: 5px; min-width: 150px; display: inline-block; font-weight: 600;">Authorized Signatory</p>
                </div>
            </div>
        </div>
    </div>
<?php
    $content = ob_get_clean();
    echo $content;
    exit();
}

// UI
?>
<link href="../admin_area/css/salary-slip.css" rel="stylesheet">
<style>
    @media print {

        /* Hide common dashboard elements */
        #wrapper #sidebar-wrapper,
        #wrapper .emp-sidebar,
        #wrapper .navbar,
        .page-header-premium,
        .breadcrumb,
        .premium-card,
        .modal-header,
        .modal-footer,
        .close {
            display: none !important;
        }

        /* Ensure modal is visible and positioned at top left */
        #salarySlipModal {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 9999 !important;
            background: #fff !important;
        }

        /* Ensure all parents of the modal body are visible */
        #wrapper,
        #page-wrapper,
        .container-fluid,
        .premium-ui-enabled,
        #salarySlipModal,
        #salarySlipModal .modal-dialog,
        #salarySlipModal .modal-content,
        #salarySlipModalBody,
        #salarySlipModalBody * {
            visibility: visible !important;
            display: block !important;
            background: transparent !important;
            box-shadow: none !important;
            border: none !important;
        }

        /* Specifically format the salary slip for the print page */
        .salary-slip {
            margin: 0 auto !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: none !important;
        }

        /* Print only the content, no background colors from parents */
        body {
            background: #fff !important;
        }

        /* Avoid colors being skipped by some browsers */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="premium-ui-enabled">


    <div class="row">
        <div class="col-lg-12">
            <div class="premium-card">
                <div class="card-hdr">
                    <i class="fa fa-list"></i>
                    <h3>Monthly Salary Record</h3>
                </div>
                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">#</th>
                                <th style="width: 180px;">Employee</th>
                                <th style="width: 130px;">Month</th>
                                <th style="width: 140px; text-align: center;">Net Salary</th>
                                <th style="width: 100px; text-align: center;">Status</th>
                                <th style="width: 80px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Show last 12 months, but not before join date
                            $join_month = ($employee && !empty($employee['join_date']) && $employee['join_date'] !== '0000-00-00')
                                ? date('Y-m', strtotime($employee['join_date']))
                                : '1970-01';

                            $idx = 1;
                            for ($i = 0; $i < 12; $i++) {
                                $ts = strtotime("-{$i} month");
                                $m_val = date('Y-m', $ts);

                                // Stop if we go before join date
                                if ($m_val < $join_month) break;

                                $m_label = date('F, Y', $ts);
                                $is_current = ($m_val === date('Y-m'));
                                $current_day = (int)date('d');

                                // Fetch history for this row to show correct amount in table
                                $row_q = mysqli_query($con, "SELECT net_pay FROM emp_salary_history WHERE emp_id = '" . (int)$emp_id . "' AND month = '$m_val'");
                                $row_data = mysqli_fetch_assoc($row_q);

                                if ($row_data && $row_data['net_pay'] !== null) {
                                    $row_salary = (float)$row_data['net_pay'];
                                } else {
                                    // Calculate default row salary if no history using profile fields
                                    $row_base = ($employee && $employee['basic_salary'] !== null && $employee['basic_salary'] > 0) ? (float)$employee['basic_salary'] : (($base_salary <= 0) ? 30000.00 : $base_salary);

                                    $row_hra = ($employee && $employee['hra'] !== null) ? (float)$employee['hra'] : round($row_base * 0.20, 2);
                                    $row_pf = 0.00;
                                    $row_tax = 0.00;
                                    $row_allow = ($employee && $employee['allowance'] !== null) ? (float)$employee['allowance'] : 0.00;
                                    $row_ded = ($employee && $employee['deductions'] !== null) ? (float)$employee['deductions'] : 0.00;

                                    $row_salary = ($row_base + $row_hra + $row_allow) - ($row_pf + $row_tax + $row_ded);
                                }

                                // Logic: Disable view for current month until end of month (e.g., after 25th)
                                // unless a specific history record exists (admin manually saved it)
                                $can_view = true;
                                if ($is_current && $current_day < 25) {
                                    if (!$row_data) {
                                        $can_view = false;
                                    }
                                }
                            ?>
                                <tr>
                                    <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $idx++; ?></td>
                                    <td>
                                        <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($emp_name); ?></div>
                                        <div style="font-size: 11px; color: #64748b;">ID: <?php echo (int)$emp_id; ?></div>
                                    </td>
                                    <td style="font-weight: 600; color: #4b5563;"><?php echo htmlspecialchars($m_label); ?></td>
                                    <td style="text-align: center; font-weight: 800; color: #4b5563; font-size: 15px;">
                                        <?php echo format_money_with_symbol($row_salary, $currency_symbol); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($can_view): ?>
                                            <span class="p-badge p-badge-success" style="display: inline-flex; justify-content: center; min-width: 80px;">PAID</span>
                                        <?php else: ?>
                                            <span class="p-badge p-badge-primary" style="display: inline-flex; justify-content: center; min-width: 80px;">LOCKED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($can_view): ?>
                                            <div style="display: flex; justify-content: center;">
                                                <button type="button"
                                                    class="btn-icon-premium btn-icon-view view-slip-btn"
                                                    data-month="<?php echo $m_val; ?>"
                                                    title="View Salary Slip">
                                                    <i class="fa fa-eye"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <i class="fa fa-lock" style="color: #cbd5e1;" title="Available at month end"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="salarySlipModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="modal-header" style="background: var(--p-bg-header);color:#fff; padding: 20px 25px;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity: 0.8;">&times;</button>
                    <h4 class="modal-title" style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 15px;">
                        <i class="fa fa-file-text-o"></i> Salary Slip Detail
                    </h4>
                </div>
                <div class="modal-body" id="salarySlipModalBody" style="background: #f8fafc; padding: 30px;">
                    <div style="text-align:center; padding:60px;">
                        <i class="fa fa-spinner fa-spin fa-3x" style="color: #4f46e5; margin-bottom: 15px;"></i>
                        <br>
                        <span style="color: #64748b; font-weight: 600;">Generating Secure Slip View...</span>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 20px 25px; background: #fff; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-premium-cancel" data-dismiss="modal">Close</button>
                    <button type="button" class="btn-premium-add" id="modalDownloadBtn">
                        <i class="fa fa-download"></i> Save as PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="../admin_area/js/jquery.min.js"></script>
    <script src="../admin_area/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {

            var currentMonthLabel = '';

            $('.view-slip-btn').click(function() {

                var month = $(this).data('month');
                currentMonthLabel = $(this).closest('tr').find('td:nth-child(3)').text().trim();

                $('#salarySlipModalBody').html(
                    '<div style="text-align:center;padding:60px;"><i class="fa fa-spinner fa-spin fa-3x" style="color: #4f46e5; margin-bottom: 15px;"></i><br><span style="color: #64748b; font-weight: 600;">Generating Secure Slip View...</span></div>'
                );

                $('#salarySlipModal').modal('show');

                $.ajax({
                    url: 'pages/salary/emp_salary_slip.php',
                    type: 'GET',
                    data: {
                        month: month,
                        view: 1,
                        ajax: 1
                    },
                    success: function(data) {

                        if (data.trim() === "SessionExpired") {
                            window.location = "emp-login.php";
                            return;
                        }

                        $('#salarySlipModalBody').html(data);

                    },
                    error: function() {
                        $('#salarySlipModalBody').html(
                            '<div style="text-align:center;color:red;">Error loading salary slip.</div>'
                        );
                    }
                });

            });

            // Direct PDF Download Logic
            $('#modalDownloadBtn').click(function() {
                var element = document.getElementById('slip-content');
                if (!element) {
                    Swal.fire('Notification', "Slip content not loaded yet!", 'info');
                    return;
                }

                var opt = {
                    margin: [10, 10],
                    filename: 'Salary_Slip_<?php echo str_replace(" ", "_", $emp_name); ?>_' + currentMonthLabel.replace(/, /g, '_') + '.pdf',
                    image: {
                        type: 'jpeg',
                        quality: 0.98
                    },
                    html2canvas: {
                        scale: 2,
                        useCORS: true,
                        logging: false
                    },
                    jsPDF: {
                        unit: 'mm',
                        format: 'a4',
                        orientation: 'portrait'
                    }
                };

                // Show loading state on button
                var $btn = $(this);
                var originalHtml = $btn.html();
                $btn.html('<i class="fa fa-spinner fa-spin"></i> Generating...').prop('disabled', true);

                html2pdf().set(opt).from(element).save().then(function() {
                    $btn.html(originalHtml).prop('disabled', false);
                });
            });

        });
    </script>
</div>