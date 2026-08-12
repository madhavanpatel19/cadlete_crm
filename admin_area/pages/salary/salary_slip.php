<?php
// ---- Salary Slip fragment (include from index.php) ----
// Assumes: $con (mysqli connection) and session already started in index.php
// Also assumes Bootstrap + Font Awesome loaded in main layout

// ------------------ HELPERS ------------------ //
function format_money($n)
{
    return number_format((float)$n, 2);
}

function format_money_with_symbol($n, $currency_symbol)
{
    return '<span class="currency-symbol">' . $currency_symbol . '</span>' . format_money($n);
}

// Convert number to words (supports up to billions, with paise/decimal part)
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

// Currency symbol (HTML entity keeps it ASCII-safe)
$currency_symbol = '&#8377;';

// ------------------ FETCH EMPLOYEES ------------------ //
$employees = array();
$res = mysqli_query(
    $con,
    "SELECT id, name, COALESCE(salary, '') AS salary, join_date
     FROM emp_list
     ORDER BY name ASC"
);
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $employees[] = $r;
    }
}

// ------------------ SALARY SAVE HANDLER ------------------ //
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salary_amounts'])) {
    if (!function_exists('canAdminAccess') || !canAdminAccess('salary_update')) {
        echo "<script>Swal.fire('Error', 'You do not have permission to update salary.', 'error');</script>";
        exit();
    }
    $emp_id_save = (int)$_POST['emp_id'];
    $month_save  = mysqli_real_escape_string($con, $_POST['month']);
    $basic    = (float)$_POST['basic'];
    $hra_val  = (float)$_POST['hra'];
    $pf_val   = (float)$_POST['pf'];
    $tax_val  = (float)$_POST['tax'];
    $allow    = (float)$_POST['other_allow'];
    $ded      = (float)$_POST['other_ded'];

    $gross_pay = $basic + $hra_val + $allow;
    $total_ded = $pf_val + $tax_val + $ded;
    $net_pay   = $gross_pay - $total_ded;

    // Do not allow salary entry for months before employee joining month.
    $join_q = mysqli_query($con, "SELECT join_date FROM emp_list WHERE id = $emp_id_save LIMIT 1");
    $join_r = ($join_q && mysqli_num_rows($join_q)) ? mysqli_fetch_assoc($join_q) : null;
    if ($join_r && !empty($join_r['join_date']) && $join_r['join_date'] !== '0000-00-00') {
        $join_month_save = date('Y-m', strtotime($join_r['join_date']));
        if ($month_save < $join_month_save) {
            echo "<script>Swal.fire('Notification', 'Cannot save salary before employee joining month.', 'info'); window.location.href='index.php?salary_slip=1&emp_id=$emp_id_save&month=" . urlencode($join_month_save) . "';</script>";
            exit();
        }
    }

    // 1. Update the history table (Primary storage for month-specific data)
    $history_q = "INSERT INTO emp_salary_history 
        (emp_id, month, basic_salary, hra, pf, tax, allowance, deductions, gross_pay, total_deductions, net_pay)
        VALUES ($emp_id_save, '$month_save', $basic, $hra_val, $pf_val, $tax_val, $allow, $ded, $gross_pay, $total_ded, $net_pay)
        ON DUPLICATE KEY UPDATE 
        basic_salary = $basic, 
        hra = $hra_val, 
        pf = $pf_val, 
        tax = $tax_val, 
        allowance = $allow, 
        deductions = $ded,
        gross_pay = $gross_pay,
        total_deductions = $total_ded,
        net_pay = $net_pay";

    mysqli_query($con, $history_q);

    echo "<script>Swal.fire({title: 'Notification', text: 'Salary amounts updated successfully for $month_save!', icon: 'success'});</script>";
    // Refresh to show updated values
    echo "<script>window.location.href='index.php?salary_slip=1&emp_id=$emp_id_save&month=" . urlencode($month_save) . "&view=1';</script>";
    exit();
}

// ------------------ GET FILTER VALUES ------------------ //
$selected_emp   = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;
$selected_month = isset($_GET['month']) && $_GET['month'] !== ''
    ? $_GET['month']           // format Y-m from input type="month"
    : date('Y-m'); // default to current month
$view_mode      = isset($_GET['view']) && $_GET['view'] == '1';
$download_mode  = isset($_GET['download']) && $_GET['download'] == '1';
$view_all_mode  = isset($_GET['view_all']) && $_GET['view_all'] == '1'; // show all employees for current month
$print_all_mode = isset($_GET['print_all']) && $_GET['print_all'] == '1'; // render and print all slips
// Optional return parameter (e.g. return=dashboard) to redirect back after viewing
$return_to      = isset($_GET['return']) ? $_GET['return'] : '';

// ------------------ FETCH SELECTED EMPLOYEE ------------------ //
$employee    = null;
$base_salary = 0.00;
$designation = '';
$department  = '';
$join_month = null;
$is_before_joining_month = false;

// Initial defaults for components
$db_basic = null;
$db_hra   = null;
$db_pf    = null;
$db_tax   = null;
$db_allow = null;
$db_ded   = null;

if ($selected_emp) {
    // Join with history table for selected month
    $q = mysqli_query(
        $con,
        "SELECT e.*, 
                h.basic_salary AS h_basic, 
                h.hra AS h_hra, 
                h.pf AS h_pf, 
                h.tax AS h_tax, 
                h.allowance AS h_allow, 
                h.deductions AS h_ded
         FROM emp_list e
         LEFT JOIN emp_salary_history h ON e.id = h.emp_id AND h.month = '" . mysqli_real_escape_string($con, $selected_month) . "'
         WHERE e.id = '" . (int)$selected_emp . "' LIMIT 1"
    );
    if ($q && mysqli_num_rows($q)) {
        $employee    = mysqli_fetch_assoc($q);
        $base_salary = $employee['salary'] !== '' ? (float)$employee['salary'] : 0.00;
        $designation = isset($employee['designation']) ? $employee['designation'] : '';
        $department  = isset($employee['department']) ? $employee['department'] : '';
        if (!empty($employee['join_date']) && $employee['join_date'] !== '0000-00-00') {
            $join_month = date('Y-m', strtotime($employee['join_date']));
            $is_before_joining_month = ($selected_month < $join_month);
        }

        // Prioritize history values if they exist
        $db_basic = ($employee['h_basic'] !== null) ? $employee['h_basic'] : $employee['basic_salary'];
        $db_hra   = ($employee['h_hra']   !== null) ? $employee['h_hra']   : $employee['hra'];
        $db_pf    = ($employee['h_pf']    !== null) ? $employee['h_pf']    : null; // No default PF in emp_list
        $db_tax   = ($employee['h_tax']   !== null) ? $employee['h_tax']   : null; // No default Tax in emp_list
        $db_allow = ($employee['h_allow'] !== null) ? $employee['h_allow'] : $employee['allowance'];
        $db_ded   = ($employee['h_ded']   !== null) ? $employee['h_ded']   : $employee['deductions'];
    }
}

// ------------------ SALARY CALCULATION ------------------ //
// Use DB values if present, otherwise calculate defaults
$base_salary_val = ($db_basic !== null && $db_basic > 0) ? (float)$db_basic : (($base_salary <= 0) ? 30000.00 : (float)$base_salary);
$hra   = ($db_hra   !== null) ? (float)$db_hra   : round($base_salary_val * 0.20, 2);
$pf    = ($db_pf    !== null) ? (float)$db_pf    : 0.00;
$tax   = ($db_tax   !== null) ? (float)$db_tax   : 0.00;
$other_allow = ($db_allow !== null) ? (float)$db_allow : 0.00;
$other_ded   = ($db_ded   !== null) ? (float)$db_ded   : 0.00;

// Allow temporary GET overrides if needed (optional, keeping for flexibility)
if (isset($_GET['basic'])) $base_salary_val = (float)$_GET['basic'];
if (isset($_GET['hra']))   $hra = (float)$_GET['hra'];
if (isset($_GET['pf']))    $pf = (float)$_GET['pf'];
if (isset($_GET['tax']))   $tax = (float)$_GET['tax'];
if (isset($_GET['other_allow'])) $other_allow = (float)$_GET['other_allow'];
if (isset($_GET['other_ded']))   $other_ded = (float)$_GET['other_ded'];

$gross            = $base_salary_val + $hra + $other_allow;
$total_deductions = $pf + $tax + $other_ded;
$net              = $gross - $total_deductions;

// ------------------ BASIC STYLES ------------------ //
echo '<link href="css/salary-slip.css" rel="stylesheet">';

// Wrapper classes (flag print-all so CSS can adjust print rules)
$slip_wrap_classes = 'salary-slip-wrap';
if ($print_all_mode) {
    $slip_wrap_classes .= ' print-all-mode';
}
?>

<style>
    .table-premium th,
    .table-premium td {
        text-align: center !important;
        vertical-align: middle !important;
    }
</style>

<div class="<?php echo $slip_wrap_classes; ?>">
    <div class="slip-controls" style="margin-bottom:18px;">
        <?php if (!$view_mode && !$view_all_mode): ?>
            <div class="page-wrapper premium-ui-enabled">
                <div class="page-header-premium">
                    <h1></h1>
                </div>

                <div class="premium-card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden; margin-bottom: 25px; max-width: 600px;">
                    <div class="card-hdr" style="padding: 20px 30px; background: var(--p-bg-header); display: flex; align-items: center; gap: 12px;">
                        <i class="fa fa-user-circle" style="font-size: 18px; color: #333;"></i>
                        <h3 style="margin: 0; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Salary Slip</h3>
                    </div>

                    <div style="padding: 40px 30px; background: #fff;">
                        <form method="GET" class="form-horizontal">
                            <input type="hidden" name="salary_slip" value="1">

                            <div style="display: flex; flex-direction: column; gap: 25px; max-width: 600px;">
                                <div class="form-group" style="margin: 0; display: flex; align-items: center; flex-wrap: wrap;">
                                    <label for="emp_id" class="col-sm-3" style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.02em;">Employee</label>
                                    <div class="col-sm-9">
                                        <select name="emp_id" id="emp_id" class="p-input-premium" required style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 20px top 50%; background-size: 12px 20px;">
                                            <option value="">-- Choose Employee --</option>
                                            <?php foreach ($employees as $e): ?>
                                                <option
                                                    value="<?php echo (int)$e['id']; ?>"
                                                    data-join-month="<?php echo (!empty($e['join_date']) && $e['join_date'] !== '0000-00-00') ? htmlspecialchars(date('Y-m', strtotime($e['join_date']))) : ''; ?>"
                                                    <?php echo ($selected_emp == $e['id']) ? "selected" : ""; ?>>
                                                    <?php echo htmlspecialchars($e['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" style="margin: 0; display: flex; align-items: center; flex-wrap: wrap;">
                                    <label for="month" class="col-sm-3" style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.02em;">Month</label>
                                    <div class="col-sm-9">
                                        <input type="month" id="month" name="month" class="p-input-premium" value="<?php echo $selected_month ? htmlspecialchars($selected_month) : ''; ?>" max="<?php echo date('Y-m'); ?>" required>
                                    </div>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <div class="col-sm-3"></div>
                                    <div class="col-sm-9" style="display: flex; gap: 12px;">
                                        <a href="index.php?dashboard" class="btn" style="flex: 1; padding: 12px; border-radius: 10px; background: #f1f5f9; color: #475569; font-weight: 700; border: none; text-align: center; text-decoration: none; font-size: 13px;">
                                            <i class="fa fa-arrow-left" style="margin-right: 5px;"></i> Back
                                        </a>
                                        <button type="submit" class="btn-premium-add">
                                            <i class="fa fa-search" style="margin-right: 8px;"></i> View Slip
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // ------------------ ALL EMPLOYEES SALARY SLIPS VIEW (for current month) ------------------ //
    if ($view_all_mode):
        $current_month = $selected_month ? $selected_month : date('Y-m');
        $current_month_label = date('F, Y', strtotime($current_month . '-01'));

        // If print_all_mode is requested, render full slips for each employee and trigger print
        if (isset($print_all_mode) && $print_all_mode):
            // Fetch all employees and their history for this month
            $all_emp_q = mysqli_query(
                $con,
                "SELECT e.id, e.name, e.salary, e.basic_salary, e.hra, e.allowance, e.deductions,
                        h.basic_salary AS h_basic, h.hra AS h_hra, h.pf AS h_pf, h.tax AS h_tax, 
                        h.allowance AS h_allow, h.deductions AS h_ded
                 FROM emp_list e
                 LEFT JOIN emp_salary_history h ON e.id = h.emp_id AND h.month = '$current_month'
                 WHERE e.join_date IS NULL OR e.join_date = '0000-00-00' OR DATE_FORMAT(e.join_date, '%Y-%m') <= '$current_month'
                 ORDER BY e.name ASC"
            );
            while ($emp = mysqli_fetch_assoc($all_emp_q)):
                $emp_id_local = (int)$emp['id'];
                $emp_name_local = $emp['name'];
                $emp_salary_raw = (isset($emp['salary']) && $emp['salary'] !== '') ? (float)$emp['salary'] : 0.00;

                // Prioritize history values
                $base_val_local = ($emp['h_basic'] !== null) ? (float)$emp['h_basic'] : (($emp['basic_salary'] !== null && $emp['basic_salary'] > 0) ? (float)$emp['basic_salary'] : (($emp_salary_raw <= 0) ? 30000.00 : $emp_salary_raw));
                $hra_local = ($emp['h_hra'] !== null) ? (float)$emp['h_hra'] : (($emp['hra'] !== null) ? (float)$emp['hra'] : round($base_val_local * 0.20, 2));
                $pf_local  = ($emp['h_pf']  !== null) ? (float)$emp['h_pf']  : 0.00;
                $tax_local = ($emp['h_tax'] !== null) ? (float)$emp['h_tax'] : 0.00;
                $other_allow_local = ($emp['h_allow'] !== null) ? (float)$emp['h_allow'] : (($emp['allowance'] !== null) ? (float)$emp['allowance'] : 0.00);
                $other_ded_local   = ($emp['h_ded']   !== null) ? (float)$emp['h_ded']   : (($emp['deductions'] !== null) ? (float)$emp['deductions'] : 0.00);

                $gross_local = $base_val_local + $hra_local + $other_allow_local;
                $total_deductions_local = $pf_local + $tax_local + $other_ded_local;
                $net_local = $gross_local - $total_deductions_local;
    ?>
                <div class="salary-slip card" style="margin:14px auto; padding:18px; max-width:820px;">
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
                            <div class="slip-id">Slip No: <strong><?php echo sprintf('%05d', $emp_id_local); ?></strong></div>
                            <p><strong>Period:</strong> <?php echo htmlspecialchars($current_month_label); ?></p>
                        </div>
                    </div>

                    <div class="employee-info clearfix">
                        <div class="emp-left">
                            <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($emp_name_local); ?></p>
                            <p><strong>Employee ID:</strong> <?php echo $emp_id_local; ?></p>
                        </div>
                        <div class="emp-right">
                            <p><strong>Pay Date:</strong> <?php echo date('t M Y', strtotime($current_month . '-01')); ?></p>
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
                                    <td class="amt"><?php echo format_money_with_symbol($base_val_local, $currency_symbol); ?></td>
                                    <td>Provident Fund (PF)</td>
                                    <td class="amt"><?php echo format_money_with_symbol($pf_local, $currency_symbol); ?></td>
                                </tr>
                                <tr>
                                    <td>House Rent Allowance (HRA)</td>
                                    <td class="amt"><?php echo format_money_with_symbol($hra_local, $currency_symbol); ?></td>
                                    <td>Leave Without Pay</td>
                                    <td class="amt"><?php echo format_money_with_symbol($tax_local, $currency_symbol); ?></td>
                                </tr>
                                <tr>
                                    <td>Other Allowances</td>
                                    <td class="amt"><?php echo format_money_with_symbol($other_allow_local, $currency_symbol); ?></td>
                                    <td>Other Deductions</td>
                                    <td class="amt"><?php echo format_money_with_symbol($other_ded_local, $currency_symbol); ?></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="totals">
                                    <td><strong>Gross Pay</strong></td>
                                    <td class="amt"><strong><?php echo format_money_with_symbol($gross_local, $currency_symbol); ?></strong></td>
                                    <td><strong>Total Deductions</strong></td>
                                    <td class="amt"><strong><?php echo format_money_with_symbol($total_deductions_local, $currency_symbol); ?></strong></td>
                                </tr>
                                <tr class="net">
                                    <td class="net-label"><strong>Net Pay</strong></td>
                                    <td class="net-amt"><strong><?php echo format_money_with_symbol($net_local, $currency_symbol); ?></strong></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td colspan="4" style="padding-top:10px; font-size:12px; font-style:italic;">
                                        <strong>Amount in words:</strong> <?php echo htmlspecialchars(number_to_words($net_local)); ?> Only
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
                                <img src="images/logo_sign.png" alt="Signature" class="sign-image" style="height: 73px; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: -5px; z-index: 1;">
                                <p style="margin: 0; border-top: 1px solid #444; padding-top: 5px; min-width: 150px; display: inline-block; font-weight: 600;">Authorized Signatory</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            endwhile; // employees
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // delay slightly to ensure stylesheets/fonts finish loading
                    // increased timeout to allow CSS/fonts to load before print
                    setTimeout(function() {
                        try {
                            window.focus();
                        } catch (e) {}
                        window.print();
                    }, 800);
                });
            </script>
        <?php
        else:
            // normal table view with link to invoke print-all
            $current_month = $selected_month ? $selected_month : date('Y-m');
            $current_month_label = date('F, Y', strtotime($current_month . '-01'));
            $emp_slips = array();
            $list_q = mysqli_query(
                $con,
                "SELECT e.id, e.name, e.salary, e.basic_salary, e.hra, e.allowance, e.deductions, h.net_pay
                 FROM emp_list e
                 LEFT JOIN emp_salary_history h ON e.id = h.emp_id AND h.month = '$current_month'
                 WHERE e.join_date IS NULL OR e.join_date = '0000-00-00' OR DATE_FORMAT(e.join_date, '%Y-%m') <= '$current_month'
                 ORDER BY e.name ASC"
            );
            while ($emp = mysqli_fetch_assoc($list_q)) {
                if ($emp['net_pay'] !== null) {
                    $net_pay_final = (float)$emp['net_pay'];
                } else {
                    // Calculate fallback net pay using same logic as slips
                    $emp_salary_raw = (isset($emp['salary']) && $emp['salary'] !== '') ? (float)$emp['salary'] : 0.00;
                    $b_val = ($emp['basic_salary'] !== null && $emp['basic_salary'] > 0) ? (float)$emp['basic_salary'] : (($emp_salary_raw <= 0) ? 30000.00 : $emp_salary_raw);

                    $h_val = ($emp['hra'] !== null) ? (float)$emp['hra'] : round($b_val * 0.20, 2);
                    $p_val = 0.00;
                    $t_val = 0.00;
                    $a_val = ($emp['allowance'] !== null) ? (float)$emp['allowance'] : 0.00;
                    $d_val = ($emp['deductions'] !== null) ? (float)$emp['deductions'] : 0.00;

                    $net_pay_final = ($b_val + $h_val + $a_val) - ($p_val + $t_val + $d_val);
                }

                $emp_slips[] = array(
                    'id' => $emp['id'],
                    'name' => $emp['name'],
                    'salary' => $net_pay_final,
                    'is_custom' => ($emp['net_pay'] !== null)
                );
            }
        ?>
            <div class="premium-card" style="margin-top: 20px;">
                <div class="card-hdr" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 25px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-money"></i>
                        <h3 style="margin: 0; font-size: 16px;">Salary Slips - <?php echo htmlspecialchars($current_month_label); ?></h3>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <a class="btn btn-sm btn-primary" href="?salary_slip=1&view_all=1&print_all=1&month=<?php echo htmlspecialchars($current_month); ?>" style="border-radius: 8px; font-weight: 700;">
                            <i class="fa fa-print"></i> Print All
                        </a>
                        <a href="index.php?dashboard" class="btn btn-sm btn-default" style="border-radius: 8px; font-weight: 700;">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 60px;">ID</th>
                                <th>Employee Name</th>
                                <th class="text-center" style="text-align: center !important;">Net Salary</th>
                                <th class="text-center" style="text-align: center !important;">Source</th>
                                <th class="text-center" style="text-align: center !important;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emp_slips as $slip): ?>
                                <tr>
                                    <td class="text-center" style="font-weight: 700; color: #64748b;"><?php echo (int)$slip['id']; ?></td>
                                    <td>
                                        <span style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($slip['name']); ?></span>
                                    </td>
                                    <td class="text-center" style="text-align: center !important; font-weight: 800; color: #0f172a; font-family: 'Poppins', sans-serif;">
                                        <?php echo $currency_symbol . ' ' . format_money($slip['salary']); ?>
                                    </td>
                                    <td class="text-center" style="text-align: center !important;">
                                        <?php if ($slip['is_custom']): ?>
                                            <span class="p-badge p-badge-info" style="font-size: 10px;">Adjusted</span>
                                        <?php else: ?>
                                            <span class="p-badge p-badge-default" style="font-size: 10px; opacity: 0.6;">Standard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center" style="text-align: center !important;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <a class="btn-icon-premium btn-icon-view" href="?salary_slip=1&emp_id=<?php echo (int)$slip['id']; ?>&month=<?php echo htmlspecialchars($current_month); ?>&view=1<?php echo ($return_to == 'dashboard') ? '&return=dashboard' : '&return=view_all'; ?>" title="View">
                                                <i class="fa fa-list-alt"></i>
                                            </a>
                                            <a class="btn-icon-premium btn-icon-download" href="?salary_slip=1&emp_id=<?php echo (int)$slip['id']; ?>&month=<?php echo htmlspecialchars($current_month); ?>&view=1&download=1<?php echo ($return_to == 'dashboard') ? '&return=dashboard' : '&return=view_all'; ?>" title="Download">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php
        endif; // print_all_mode
    endif; // view_all_mode

    // ------------------ RECORDS TABLE (selected month only) ------------------ //
    if ($selected_emp && $selected_month && !$view_mode && $employee && !$is_before_joining_month): ?>
        <?php
        $months = array();
        $current_month_dt = DateTime::createFromFormat('Y-m', $selected_month);
        if (!$current_month_dt) {
            $current_month_dt = new DateTime();
        }
        $months = array($current_month_dt);
        ?>
        <div class="records-table" style="margin-top:12px;">
            <table class="table table-bordered table-striped slip-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Period (Month - Year)</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($months as $m_dt): ?>
                        <?php
                        $m_str   = $m_dt->format('Y-m');
                        $m_label = $m_dt->format('F, Y');
                        ?>
                        <tr>
                            <td><?php echo (int)$employee['id']; ?></td>
                            <td><?php echo htmlspecialchars($employee['name']); ?></td>
                            <td><?php echo htmlspecialchars($m_label); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a class="btn-icon-premium btn-icon-view"
                                        href="?salary_slip=1&emp_id=<?php echo (int)$selected_emp; ?>&month=<?php echo htmlspecialchars($m_str); ?>&view=1<?php echo ($return_to == 'dashboard') ? '&return=dashboard' : ''; ?>" title="View">
                                        <i class="fa fa-list-alt"></i>
                                    </a>
                                    <a class="btn-icon-premium btn-icon-download"
                                        href="?salary_slip=1&emp_id=<?php echo (int)$selected_emp; ?>&month=<?php echo htmlspecialchars($m_str); ?>&view=1&download=1<?php echo ($return_to == 'dashboard') ? '&return=dashboard' : ''; ?>" title="Download">
                                        <i class="fa fa-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($selected_emp && $selected_month && !$view_mode && $employee && $is_before_joining_month): ?>
        <div class="alert alert-warning" style="margin-top: 12px;">
            Salary slip is available from <strong><?php echo htmlspecialchars(date('F, Y', strtotime($join_month . '-01'))); ?></strong>.
            Selected month is before joining date.
        </div>
    <?php endif; ?>

    <?php
    // ------------------ SALARY SLIP VIEW ------------------ //
    if ($view_mode && $employee && $selected_month && !$is_before_joining_month): ?>
        <div id="slip" class="salary-slip card" style="margin:10px auto; padding:18px; max-width:820px;">
            <div class="slip-top-decor"></div>

            <?php
            // Build back URL:
            // - if user requested return to dashboard, go there
            // - if user requested return to view_all, go back to the listing for the same month
            // - otherwise default to the selection/records page for this employee/month
            if ($return_to === 'dashboard') {
                $back_url = 'index.php?dashboard';
            } elseif ($return_to === 'view_all') {
                $back_url = 'index.php?salary_slip=1&view_all=1&month=' . urlencode($selected_month);
            } else {
                $back_url = 'index.php?salary_slip=1&emp_id=' . (int)$selected_emp . '&month=' . urlencode($selected_month);
            }
            ?>

            <!-- HEADER -->
            <div class="slip-header">
                <div class="company-left">
                    <img src="images/Cadlete_Black_logo_favicon.png"
                        alt="CADLETE DESIGNS Logo"
                        class="company-logo"
                        style="max-height: 80px;"
                        onerror="this.style.display='none'">
                    <div class="company-center">
                        <h3 class="company-name">CADLETE DESIGNS</h3>
                        <div class="company-address">
                            A-106, Sun South Street, Ahmedabad
                        </div>
                        <div class="company-meta-small">
                            Phone: 091 83202 11773 &nbsp;|&nbsp; Email: info@cadletedesigns.com
                        </div>
                    </div>
                </div>
                <div class="slip-meta">
                    <h4>Salary Slip</h4>
                    <div class="slip-id">Slip No: <strong><?php echo sprintf('%05d', $selected_emp); ?></strong></div>
                    <p><strong>Period:</strong> <?php echo date('F, Y', strtotime($selected_month . '-01')); ?></p>
                </div>
            </div>

            <!-- EMPLOYEE INFO -->
            <div class="employee-info clearfix">
                <div class="emp-left">
                    <p><strong>Employee Name:</strong> <?php echo htmlspecialchars($employee['name']); ?></p>
                    <p><strong>Employee ID:</strong> <?php echo (int)$selected_emp; ?></p>
                </div>
                <div class="emp-right">
                    <p><strong>Pay Date:</strong> <?php echo date('t M Y', strtotime($selected_month . '-01')); ?></p>
                </div>
            </div>

            <!-- EARNINGS / DEDUCTIONS TABLE -->
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
                            <td></td>
                            <td></td>
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
                        <img src="images/logo_sign.png" alt="Signature" class="sign-image" style="height: 73px; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: -5px; z-index: 1;">
                        <p style="margin: 0; border-top: 1px solid #444; padding-top: 5px; min-width: 150px; display: inline-block; font-weight: 600;">Authorized Signatory</p>
                    </div>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="slip-actions" style="margin-top:12px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; gap:8px;">

                    <a href="<?php echo $back_url; ?>" class="btn-premium-cancel"><i class="fa fa-arrow-left"></i> Back</a>
                    <?php if (function_exists('canAdminAccess') && canAdminAccess('salary_update')): ?>
                        <button class="btn-premium-add" data-toggle="modal" data-target="#amountModal"><i class="fa fa-edit"></i> Change Amount</button>
                    <?php endif; ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <button id="printBtn" class="btn-premium-cancel">
                        <i class="fa fa-print"></i> Print
                    </button>
                    <button id="downloadBtn" class="btn-premium-add">
                        <i class="fa fa-download"></i> Save as PDF
                    </button>
                </div>
            </div>
        </div>

        <?php if ($download_mode): ?>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    window.print();
                });
            </script>
        <?php endif; ?>
    <?php elseif ($view_mode && $employee && $selected_month && $is_before_joining_month): ?>
        <div class="alert alert-warning" style="margin-top:18px;">
            Cannot generate salary slip before joining month.
            Please select month from <strong><?php echo htmlspecialchars(date('F, Y', strtotime($join_month . '-01'))); ?></strong> onward.
        </div>
    <?php endif; ?>
</div>

<!-- ================= AMOUNT EDIT MODAL ================= -->
<?php if (function_exists('canAdminAccess') && canAdminAccess('salary_update')): ?>
    <div class="modal fade" id="amountModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">

                <form method="POST">
                    <input type="hidden" name="emp_id" value="<?php echo (int)$selected_emp; ?>">
                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">

                    <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                        <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                        <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                            <div style="background: #c70039; color:white;width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa fa-edit" style="font-size: 14px;"></i>
                            </div>
                            Edit Salary Amounts
                        </h4>
                    </div>

                    <div class="modal-body" style="padding: 30px; background: #fff;">
                        <div class="row">
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Basic Salary (₹)</label>
                                <input type="number" step="0.01" name="basic" value="<?php echo $base_salary_val; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">House Rent Allowance (HRA) (₹)</label>
                                <input type="number" step="0.01" name="hra" value="<?php echo $hra; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Provident Fund (PF) (₹)</label>
                                <input type="number" step="0.01" name="pf" value="<?php echo $pf; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Leave Without Pay (₹)</label>
                                <input type="number" step="0.01" name="tax" value="<?php echo $tax; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Other Allowances (₹)</label>
                                <input type="number" step="0.01" name="other_allow" value="<?php echo $other_allow; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                            <div class="col-md-6" style="margin-bottom: 20px;">
                                <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Other Deductions (₹)</label>
                                <input type="number" step="0.01" name="other_ded" value="<?php echo $other_ded; ?>" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer" style="padding: 20px 30px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 20px 20px; text-align: right;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_salary_amounts" class="btn-premium-add"><i class="fa fa-save"></i> Save & Apply</button>
                    </div>

                </form>

            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    (function() {
        var empSelect = document.getElementById('emp_id');
        var monthInput = document.getElementById('month');
        if (!empSelect || !monthInput) return;

        function applyMonthLimits() {
            var selectedOption = empSelect.options[empSelect.selectedIndex];
            var joinMonth = selectedOption ? selectedOption.getAttribute('data-join-month') : '';
            var currentMonth = '<?php echo date('Y-m'); ?>';

            // Always block future months.
            monthInput.max = currentMonth;

            // Block months before employee joining month when available.
            if (joinMonth) {
                monthInput.min = joinMonth;
                if (!monthInput.value || monthInput.value < joinMonth) {
                    monthInput.value = joinMonth;
                }
            } else {
                monthInput.removeAttribute('min');
            }

            // Keep selected month within allowed range.
            if (monthInput.value && monthInput.value > currentMonth) {
                monthInput.value = currentMonth;
            }
        }

        empSelect.addEventListener('change', applyMonthLimits);
        applyMonthLimits();
    })();

    document.addEventListener("DOMContentLoaded", function() {
        var printBtn = document.getElementById("printBtn");
        var downloadBtn = document.getElementById("downloadBtn");

        if (printBtn) {
            printBtn.addEventListener("click", function(e) {
                e.preventDefault();
                window.print();
            });
        }
        if (downloadBtn) {
            downloadBtn.addEventListener("click", function(e) {
                e.preventDefault();
                window.print(); // Use browser "Save as PDF"
            });
        }
    });

    // Function to print individual slip from all-employees view
    function printSlip(empId, month) {
        window.location.href = "?salary_slip=1&emp_id=" + empId + "&month=" + month + "&view=1&download=1";
    }
</script>