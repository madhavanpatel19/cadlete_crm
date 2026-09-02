<?php
// Get date range from GET/POST
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : (isset($_POST['report_type']) ? $_POST['report_type'] : 'custom');
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : (isset($_POST['from_date']) ? $_POST['from_date'] : '');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : (isset($_POST['to_date']) ? $_POST['to_date'] : '');
$single_date = isset($_GET['single_date']) ? $_GET['single_date'] : (isset($_POST['single_date']) ? $_POST['single_date'] : '');
$export_pdf = isset($_POST['export_pdf']) ? 1 : 0;

// Handle report type auto-date calculation
if ($report_type === 'daily' && !$single_date) {
    $single_date = date('Y-m-d');
}

// Fetch all employees
function get_all_employees(mysqli $con): array
{
    $arr = array();
    $q = "SELECT id, name FROM emp_list ORDER BY name ASC";
    $r = mysqli_query($con, $q);
    while ($row = mysqli_fetch_assoc($r)) {
        $arr[] = $row;
    }
    return $arr;
}

// Fetch attendance for date range
function get_attendance_range(mysqli $con, string $from_date, string $to_date): array
{
    $ret = array();
    $from = mysqli_real_escape_string($con, $from_date);
    $to = mysqli_real_escape_string($con, $to_date);
    $q = "SELECT * FROM attendance 
          WHERE attendance_date >= '$from' AND attendance_date <= '$to'
          ORDER BY emp_id, attendance_date";
    $r = mysqli_query($con, $q);
    while ($rec = mysqli_fetch_assoc($r)) {
        $emp_id = (int)$rec['emp_id'];
        if (!isset($ret[$emp_id])) {
            $ret[$emp_id] = array();
        }
        $ret[$emp_id][$rec['attendance_date']] = $rec;
    }
    return $ret;
}

// Count attendance status for a date range
function count_status_in_range(mysqli $con, int $emp_id, string $from_date, string $to_date, string $status): int
{
    $emp_id = (int)$emp_id;
    $from = mysqli_real_escape_string($con, $from_date);
    $to = mysqli_real_escape_string($con, $to_date);
    $st = mysqli_real_escape_string($con, $status);
    $q = "SELECT COUNT(*) as cnt FROM attendance 
          WHERE emp_id='$emp_id' 
            AND attendance_date >= '$from' 
            AND attendance_date <= '$to' 
            AND status='$st'";
    $r = mysqli_query($con, $q);
    $row = mysqli_fetch_assoc($r);
    return (int)$row['cnt'];
}

$employees = get_all_employees($con);
$report_data = array();
$message = null;

if ($report_type === 'daily' && $single_date) {
    // Single date report
    $from_date = $single_date;
    $to_date = $single_date;
    $report_data = get_attendance_range($con, $from_date, $to_date);

    if ($export_pdf) {
        generate_attendance_pdf($con, $employees, $report_data, $from_date, $to_date, 'daily');
        exit;
    }
} elseif ($report_type === 'custom' && $from_date && $to_date) {
    // Custom range reports
    // Validate dates
    if (strtotime($from_date) > strtotime($to_date)) {
        $message = "Error: 'From Date' must be before 'To Date'.";
    } else {
        $report_data = get_attendance_range($con, $from_date, $to_date);

        if ($export_pdf) {
            generate_attendance_pdf($con, $employees, $report_data, $from_date, $to_date, $report_type);
            exit;
        }
    }
}

function generate_attendance_pdf(mysqli $con, array $employees, array $report_data, string $from_date, string $to_date, string $report_type = 'range'): void
{
    // Generate downloadable HTML file as PDF alternative
    $from_label = date('d-m-y', strtotime($from_date));
    $to_label = date('d-m-y', strtotime($to_date));

    if ($report_type === 'daily') {
        $filename = 'Attendance_Report_' . $from_label . '.html';
        $period_text = 'Date: ' . htmlspecialchars($from_label);
        // include check-in and check-out times for daily reports
        $table_headers = '<th>Employee ID</th><th>Employee Name</th><th>Check In</th><th>Check Out</th><th>Duration</th><th>Status</th><th>Performance</th><th>Remarks</th><th>Created At</th>';
    } else {
        $filename = 'Attendance_Report_' . $from_label . '_to_' . $to_label . '.html';
        $period_text = 'Period: <strong>' . htmlspecialchars($from_label) . ' to ' . htmlspecialchars($to_label) . '</strong>';
        $table_headers = '<th>Employee ID</th><th>Employee Name</th><th>Present</th><th>Absent</th><th>Leave</th><th>Total Days</th><th>Created At</th>';
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<title>Attendance Report</title>';
    echo '<style>';
    echo 'html, body { margin: 0; padding: 0; }';
    echo '@page { size: A4; margin: 15mm; }';
    echo 'body { font-family: "Arial", sans-serif; line-height: 1.4; color: #333; background: #fff; }';
    echo '.report-container { max-width: 210mm; height: 297mm; margin: 0 auto; padding: 15mm; background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); }';
    echo '.report-header { text-align: center; margin-bottom: 20px; border-bottom: 3px solid #2c3e50; padding-bottom: 12px; }';
    echo '.report-header h1 { margin: 0 0 8px 0; color: #2c3e50; font-size: 22px; font-weight: bold; letter-spacing: 1px; }';
    echo '.report-header .company-info { font-size: 11px; color: #555; margin: 4px 0; }';
    echo '.report-header p { margin: 3px 0; color: #666; font-size: 12px; }';
    echo '.report-table { width: 100%; border-collapse: collapse; margin-top: 15px; }';
    echo '.report-table thead { background: #34495e; color: #fff; }';
    echo '.report-table thead tr th { padding: 10px 6px; text-align: center; font-weight: bold; font-size: 11px; border: 1px solid #2c3e50; }';
    echo '.report-table tbody tr td { padding: 8px 6px; border: 1px solid #bdc3c7; font-size: 11px; text-align: center; word-wrap: break-word; word-break: break-word; white-space: normal; }';
    // echo '.report-table tbody tr td:nth-child(2) { text-align: left; }';
    // // echo '.report-table tbody tr td:nth-child(4) { text-align: left; }';
    echo '.report-table tbody tr:nth-child(odd) { background: #ecf0f1; }';
    echo '.report-table tbody tr:nth-child(even) { background: #fff; }';
    echo '.status-present { color: #27ae60; font-weight: bold; }';
    echo '.status-absent { color: #e74c3c; font-weight: bold; }';
    echo '.status-leave { color: #f39c12; font-weight: bold; }';
    echo '.status-holiday { color: #3498db; font-weight: bold; }';
    echo '.report-table tfoot { background: #95a5a6; color: #fff; font-weight: bold; }';
    echo '.report-table tfoot tr td { padding: 10px 6px; border: 1px solid #7f8c8d; font-size: 11px; text-align: center; }';
    // echo '.report-table tfoot tr td:nth-child(1) { text-align: left; }';
    // echo '.report-table tfoot tr td:nth-child(2) { text-align: left; }';
    echo '.signature-area { margin-top: 30px; display: flex; justify-content: space-between; }';
    echo '.signature-box { text-align: center; width: 30%; }';
    echo '.signature-line { border-top: 1px solid #333; margin-top: 35px; font-size: 10px; }';
    echo '@media print { body { margin: 0; padding: 0; } .report-container { max-width: 100%; height: auto; padding: 0; box-shadow: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    echo '<div class="report-container">';

    // Header
    echo '<div class="report-header">';
    echo '<h1>ATTENDANCE REPORT</h1>';
    echo '<div class="company-info">Cadlete</div>';
    echo '<p>' . $period_text . '</p>';
    echo '<p>Generated on: ' . date('d-m-y H:i:s') . '</p>';
    echo '</div>';

    // Report Table
    echo '<table class="report-table">';
    echo '<thead>';
    echo '<tr>';
    echo $table_headers;
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $total_present = 0;
    $total_absent = 0;
    $total_leave = 0;

    if ($report_type === 'daily') {
        // Daily report with remarks + times
        foreach ($employees as $emp) {
            $emp_id = (int)$emp['id'];
            $status = 'No Record';
            $remarks = '-';
            $check_in = '-';
            $check_out = '-';
            $duration_out = '-';
            $perfout = '-';
            $created_at = '-';

            // Check if there's attendance record for this employee on this date
            if (isset($report_data[$emp_id][$from_date])) {
                $att = $report_data[$emp_id][$from_date];
                $status = ucfirst($att['status']);
                $remarks = !empty($att['remarks']) ? nl2br(htmlspecialchars($att['remarks'])) : '-';
                $check_in = !empty($att['check_in_time']) ? $att['check_in_time'] : '-';
                $check_out = !empty($att['check_out_time']) ? $att['check_out_time'] : '-';

                // Format duration
                if (!empty($att['total_duration_secs'])) {
                    $secs = (int)$att['total_duration_secs'];
                    $h = floor($secs / 3600);
                    $m = floor(($secs % 3600) / 60);
                    $duration_out = "{$h}h {$m}m";
                }

                $created_at = !empty($att['created_at']) ? date('d-m-y H:i:s', strtotime($att['created_at'])) : '-';

                $perfout = isset($att['performance']) && $att['performance'] !== null ? $att['performance'] : '-';
                if ($att['status'] === 'present') {
                    $status = '<span class="status-present">' . $status . '</span>';
                } elseif ($att['status'] === 'absent') {
                    $status = '<span class="status-absent">' . $status . '</span>';
                } elseif ($att['status'] === 'leave') {
                    $status = '<span class="status-leave">' . $status . '</span>';
                } elseif ($att['status'] === 'holiday') {
                    $status = '<span class="status-holiday">' . $status . '</span>';
                }
            } else {
                $day_name = date('D', strtotime($from_date));
                if ($day_name === 'Sat' || $day_name === 'Sun') {
                    $status = '<span class="status-holiday">Holiday</span>';
                }
            }

            echo '<tr>';
            echo '<td>' . format_emp_id($emp_id) . '</td>';
            echo '<td>' . htmlspecialchars($emp['name']) . '</td>';
            echo '<td>' . htmlspecialchars($check_in) . '</td>';
            echo '<td>' . htmlspecialchars($check_out) . '</td>';
            echo '<td>' . htmlspecialchars($duration_out) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $perfout . '</td>';
            echo '<td style="max-width: 250px; text-align: left;">' . $remarks . '</td>';
            echo '<td>' . $created_at . '</td>';
            echo '</tr>';
        }
    } else {
        // Custom range report
        $total_present = 0;
        $total_absent = 0;
        $total_leave = 0;

        foreach ($employees as $emp) {
            $emp_id = (int)$emp['id'];
            $present = count_status_in_range($con, $emp_id, $from_date, $to_date, 'present');
            $absent = count_status_in_range($con, $emp_id, $from_date, $to_date, 'absent');
            $leave = count_status_in_range($con, $emp_id, $from_date, $to_date, 'leave');
            $total_days = $present + $absent + $leave;

            $total_present += $present;
            $total_absent += $absent;
            $total_leave += $leave;

            // Find the earliest created_at for this employee in the range
            $created_at = '-';
            if (isset($report_data[$emp_id])) {
                $min_created_at = null;
                foreach ($report_data[$emp_id] as $att_row) {
                    if (!empty($att_row['created_at'])) {
                        $row_created = strtotime($att_row['created_at']);
                        if ($min_created_at === null || $row_created < $min_created_at) {
                            $min_created_at = $row_created;
                        }
                    }
                }
                if ($min_created_at !== null) {
                    $created_at = date('Y-m-d H:i:s', $min_created_at);
                }
            }

            echo '<tr>';
            echo '<td>' . format_emp_id($emp_id) . '</td>';
            echo '<td>' . htmlspecialchars($emp['name']) . '</td>';
            echo '<td class="status-present">' . $present . '</td>';
            echo '<td class="status-absent">' . $absent . '</td>';
            echo '<td class="status-leave">' . $leave . '</td>';
            echo '<td>' . $total_days . '</td>';
            echo '<td>' . $created_at . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    if ($report_type === "custom") {
        echo '<tfoot>';
        echo '<tr>';
        echo '<td colspan="2">TOTAL</td>';
        echo '<td class="status-present">' . $total_present . '</td>';
        echo '<td class="status-absent">' . $total_absent . '</td>';
        echo '<td class="status-leave">' . $total_leave . '</td>';
        echo '<td>' . ($total_present + $total_absent + $total_leave) . '</td>';
        echo '<td></td>';
        echo '</tr>';
        echo '</tfoot>';
    }
    echo '</table>';

    // Signature Area
    echo '<div class="signature-area">';
    echo '<div class="signature-box">';
    echo '<div class="signature-line">Employee</div>';
    echo '</div>';
    echo '<div class="signature-box">';
    echo '<div class="signature-line">HR Manager</div>';
    echo '</div>';
    echo '<div class="signature-box">';
    echo '<div class="signature-line">Admin</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // close report-container
    echo '</body>';
    echo '</html>';
}

?>
<style>
    .report-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .alert {
        margin-top: 15px;
    }

    .report-preview {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        margin-top: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    /* Report Type Header - Classy Black & White */
    .report-header-section {
        background: #ffffff;
        /* white */
        padding: 22px 26px;
        border-radius: 10px;
        margin-bottom: 24px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.06);
    }

    .report-header-section h4 {
        color: #222222;
        /* dark */
        margin: 0 0 12px 0;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.4px;
    }

    .report-type-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .type-card {
        background: #ffffff;
        /* white card */
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 8px;
        padding: 16px;
        cursor: pointer;
        transition: all 0.22s ease;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .type-card:hover {
        background: #fbfbfb;
        border-color: rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
    }

    .type-card input[type="radio"] {
        cursor: pointer;
        width: 18px;
        height: 18px;
        accent-color: #222222;
        flex: 0 0 18px;
    }

    .type-card label {
        margin: 0;
        color: #222222;
        /* dark label */
        cursor: pointer;
        flex: 1;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        font-size: 15px;
    }

    .type-card i {
        font-size: 18px;
        color: #666666;
    }

    /* Modal Styling */
    .modal-content {
        border: none;
        border-radius: 12px;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.12);
    }

    /* smaller modal dialogs for compact look */
    #dailyDateModal .modal-dialog,
    #customDateModal .modal-dialog {
        max-width: 520px;
        /* narrower */
    }

    .modal-header {
        background: #ffffff;
        /* white header */
        color: #222222;
        border: none;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
        border-radius: 12px 12px 0 0;
        padding: 18px 22px;
    }

    .modal-header .close {
        color: #666666;
        opacity: 1;
        font-size: 26px;
    }

    .modal-header .close:hover {
        color: #222222;
    }

    .modal-header h5 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    .modal-body {
        padding: 35px;
    }

    .modal-body .form-group {
        margin-bottom: 30px;
    }

    .modal-body label {
        font-weight: 700;
        color: #2c3e50;
        margin-bottom: 12px;
        display: block;
        font-size: 15px;
        letter-spacing: 0.5px;
    }

    .modal-body input[type="date"] {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .modal-body input[type="date"]:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1);
    }

    .modal-footer {
        border-top: 1px solid #e0e0e0;
        padding: 20px 30px;
        background: #f8f9fa;
        border-radius: 0 0 15px 15px;
    }

    .modal-footer .btn {
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 700;
        transition: all 0.3s ease;
        border: none;
    }

    .modal-footer .btn-secondary {
        background: #95a5a6;
        color: #fff;
    }

    .modal-footer .btn-secondary:hover {
        background: #7f8c8d;
        transform: translateY(-2px);
    }

    .modal-footer .btn-primary {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        color: #fff;
    }

    .modal-footer .btn-primary:hover {
        background: linear-gradient(135deg, #2980b9 0%, #1a5fa0 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
    }

    @media (max-width: 768px) {
        .report-type-cards {
            grid-template-columns: 1fr;
        }

        .report-header-section {
            padding: 20px;
        }

        .type-card {
            padding: 15px;
        }
    }
</style>
<div class="report-container">
    <h2 style="margin-bottom: 30px; color: #2c3e50; font-weight: 700;"><i class="fa fa-file-text"></i> Attendance Report</h2>

    <!-- Report Type Selection Header -->
    <div class="report-header-section">
        <h4><i class="fa fa-cogs" style="margin-right: 10px;"></i>Select Report Type:</h4>
        <div class="report-type-cards">
            <div class="type-card" onclick="selectReportType('daily')">
                <input type="radio" name="quick_report" value="daily" class="quick-report-btn" id="daily-radio" <?php echo $report_type === 'daily' ? 'checked' : ''; ?>>
                <label for="daily-radio">
                    <i class="fa fa-calendar"></i>
                    <span>Daily Report</span>
                </label>
            </div>
            <div class="type-card" onclick="selectReportType('custom')">
                <input type="radio" name="quick_report" value="custom" class="quick-report-btn" id="custom-radio" <?php echo $report_type === 'custom' ? 'checked' : ''; ?>>
                <label for="custom-radio">
                    <i class="fa fa-sliders"></i>
                    <span>Custom Range</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Daily Report Modal -->
    <div class="modal fade" id="dailyDateModal" tabindex="-1" role="dialog" aria-labelledby="dailyDateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dailyDateModalLabel">
                        <i class="fa fa-calendar"></i> Select Date for Daily Report
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="GET" id="dailyForm" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modalSingleDate"><i class="fa fa-calendar"></i> Select Date:</label>
                            <input type="date" id="modalSingleDate" name="single_date" value="<?php echo htmlspecialchars($single_date); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check"></i> Generate Report
                        </button>
                    </div>
                    <input type="hidden" name="report_type" value="daily">
                    <input type="hidden" name="attendance_report" value="1">
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Range Modal -->
    <div class="modal fade" id="customDateModal" tabindex="-1" role="dialog" aria-labelledby="customDateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="customDateModalLabel">
                        <i class="fa fa-sliders"></i> Select Date Range for Custom Report
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="GET" id="customForm" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modalFromDate"><i class="fa fa-calendar"></i> From Date:</label>
                            <input type="date" id="modalFromDate" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="modalToDate"><i class="fa fa-calendar"></i> To Date:</label>
                            <input type="date" id="modalToDate" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-check"></i> Generate Report
                        </button>
                    </div>
                    <input type="hidden" name="report_type" value="custom">
                    <input type="hidden" name="attendance_report" value="1">
                </form>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Report Display -->
    <?php if (($report_type === 'daily' && $single_date && !$message) || ($report_type === 'custom' && $from_date && $to_date && !$message)): ?>
        <?php if (count($employees) > 0): ?>
            <div class="report-preview">
                <div class="custom-page-header" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #e0e0e0; gap: 10px;">
                    <form method="POST" style="margin: 0;">
                        <?php if ($report_type === 'daily'): ?>
                            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                            <input type="hidden" name="single_date" value="<?php echo htmlspecialchars($single_date); ?>">
                        <?php else: ?>
                            <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
                            <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                            <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                        <?php endif; ?>
                        <button type="submit" name="export_pdf" class="btn btn-success" style="padding: 10px 20px; width: auto;">
                            <i class="fa fa-download"></i> Download Report
                        </button>
                    </form>
                    <div class="header-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="javascript:void(0)" onclick="$('#<?php echo $report_type === 'daily' ? 'dailyDateModal' : 'customDateModal'; ?>').modal('show')" class="btn btn-info" style="padding: 10px 20px; flex: 1; text-align: center; white-space: nowrap;">
                            <i class="fa fa-edit"></i> Change Date
                        </a>
                        <a href="index.php?attendance" class="btn btn-default" style="padding: 10px 20px; flex: 1; text-align: center; white-space: nowrap;">
                            <i class="fa fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" style="margin-top: 15px;">
                        <thead>
                            <tr style="background: #f5f5f5;">
                                <th style="white-space: nowrap;">Employee ID</th>
                                <th style="white-space: nowrap;">Employee Name</th>
                                <?php if ($report_type === 'daily'): ?>
                                    <th style="white-space: nowrap;">Check In</th>
                                    <th style="white-space: nowrap;">Check Out</th>
                                    <th style="white-space: nowrap;">Duration</th>
                                    <th style="text-align:center; white-space: nowrap;">Status</th>
                                    <th style="width: 35%;">Remarks</th>
                                <?php else: ?>
                                    <th style="text-align:center; white-space: nowrap;">Present</th>
                                    <th style="text-align:center; white-space: nowrap;">Absent</th>
                                    <th style="text-align:center; white-space: nowrap;">Leave</th>
                                    <th style="text-align:center; white-space: nowrap;">Total Days</th>
                                <?php endif; ?>
                                <th style="white-space: nowrap;">Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_present = 0;
                            $total_absent = 0;
                            $total_leave = 0;

                            if ($report_type === 'daily'):
                                // Daily report with remarks and times
                                foreach ($employees as $emp) {
                                    $emp_id = (int)$emp['id'];
                                    $status = 'No Record';
                                    $remarks = '-';
                                    $status_class = 'label-default';
                                    $check_in = '-';
                                    $check_out = '-';
                                    $total_duration_secs = '-';
                                    $created_at = '-';

                                    // Check if there's attendance record for this employee on this date
                                    if (isset($report_data[$emp_id][$single_date])) {
                                        $att = $report_data[$emp_id][$single_date];
                                        $status = ucfirst($att['status']);
                                        $remarks = !empty($att['remarks']) ? nl2br(htmlspecialchars($att['remarks'])) : '-';
                                        $check_in = !empty($att['check_in_time']) ? $att['check_in_time'] : '-';
                                        $check_out = !empty($att['check_out_time']) ? $att['check_out_time'] : '-';

                                        // Format duration
                                        if (!empty($att['total_duration_secs'])) {
                                            $secs = (int)$att['total_duration_secs'];
                                            $h = floor($secs / 3600);
                                            $m = floor(($secs % 3600) / 60);
                                            $total_duration_secs = "{$h}h {$m}m";
                                        } else {
                                            $total_duration_secs = '-';
                                        }

                                        $created_at = !empty($att['created_at']) ? date('d-m-y H:i:s', strtotime($att['created_at'])) : '-';

                                        if ($att['status'] === 'present') {
                                            $status_class = 'label-success';
                                        } elseif ($att['status'] === 'absent') {
                                            $status_class = 'label-danger';
                                        } elseif ($att['status'] === 'leave') {
                                            $status_class = 'label-warning';
                                        } elseif ($att['status'] === 'holiday') {
                                            $status_class = 'label-info';
                                        }
                                    } else {
                                        $day_name = date('D', strtotime($single_date));
                                        if ($day_name === 'Sat' || $day_name === 'Sun') {
                                            $status = 'Holiday';
                                            $status_class = 'label-info';
                                        }
                                    }

                                    echo '<tr>';
                                    echo '<td style="white-space: nowrap;">' . format_emp_id($emp_id) . '</td>';
                                    echo '<td style="white-space: nowrap;">' . htmlspecialchars($emp['name']) . '</td>';
                                    echo '<td style="white-space: nowrap;">' . htmlspecialchars($check_in) . '</td>';
                                    echo '<td style="white-space: nowrap;">' . htmlspecialchars($check_out) . '</td>';
                                    echo '<td style="white-space: nowrap;">' . htmlspecialchars($total_duration_secs) . '</td>';
                                    echo '<td style="text-align:center; white-space: nowrap;"><span class="label ' . $status_class . '">' . $status . '</span></td>';
                                    echo '<td style="word-wrap: break-word; word-break: break-word; white-space: normal;">' . $remarks . '</td>';
                                    echo '<td style="white-space: nowrap;">' . $created_at . '</td>';
                                    echo '</tr>';
                                }
                            else:
                                // Custom range report
                                foreach ($employees as $emp) {
                                    $emp_id = (int)$emp['id'];
                                    $present = count_status_in_range($con, $emp_id, $from_date, $to_date, 'present');
                                    $absent = count_status_in_range($con, $emp_id, $from_date, $to_date, 'absent');
                                    $leave = count_status_in_range($con, $emp_id, $from_date, $to_date, 'leave');
                                    $total_days = $present + $absent + $leave;

                                    $total_present += $present;
                                    $total_absent += $absent;
                                    $total_leave += $leave;

                                    // Find the earliest created_at for this employee in the range
                                    $created_at = '-';
                                    if (isset($report_data[$emp_id])) {
                                        $min_created_at = null;
                                        foreach ($report_data[$emp_id] as $att_row) {
                                            if (!empty($att_row['created_at'])) {
                                                $row_created = strtotime($att_row['created_at']);
                                                if ($min_created_at === null || $row_created < $min_created_at) {
                                                    $min_created_at = $row_created;
                                                }
                                            }
                                        }
                                        if ($min_created_at !== null) {
                                            $created_at = date('d-m-y H:i:s', $min_created_at);
                                        }
                                    }

                                    echo '<tr>';
                                    echo '<td style="white-space: nowrap;">' . format_emp_id($emp_id) . '</td>';
                                    echo '<td style="white-space: nowrap;">' . htmlspecialchars($emp['name']) . '</td>';
                                    echo '<td style="text-align:center; color:#27ae60; font-weight:bold; white-space: nowrap;">' . $present . '</td>';
                                    echo '<td style="text-align:center; color:#e74c3c; font-weight:bold; white-space: nowrap;">' . $absent . '</td>';
                                    echo '<td style="text-align:center; color:#f39c12; font-weight:bold; white-space: nowrap;">' . $leave . '</td>';
                                    echo '<td style="text-align:center; white-space: nowrap;">' . $total_days . '</td>';
                                    echo '<td style="white-space: nowrap;">' . $created_at . '</td>';
                                    echo '</tr>';
                                }
                            endif;
                            ?>
                        </tbody>
                        <?php if ($report_type === 'custom'): ?>
                            <tfoot>
                                <tr style="background: #f0f0f0; font-weight: bold;">
                                    <td colspan="2">TOTAL</td>
                                    <td style="text-align:center; color:#27ae60;"><?php echo $total_present; ?></td>
                                    <td style="text-align:center; color:#e74c3c;"><?php echo $total_absent; ?></td>
                                    <td style="text-align:center; color:#f39c12;"><?php echo $total_leave; ?></td>
                                    <td style="text-align:center;"><?php echo $total_present + $total_absent + $total_leave; ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> No employees found in the directory.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
    function selectReportType(type) {
        if (type === 'daily') {
            $('#dailyDateModal').modal('show');
        } else if (type === 'custom') {
            $('#customDateModal').modal('show');
        }
    }

    // Auto-trigger modal on page load if report_type is set
    $(document).ready(function() {
        var reportType = '<?php echo htmlspecialchars($report_type); ?>';
        if (reportType === 'daily' && !$('#modalSingleDate').val()) {
            // Only show modal if we don't have report data yet
            if ($('.report-preview').length === 0) {
                $('#dailyDateModal').modal('show');
            }
        } else if (reportType === 'custom' && (!$('#modalFromDate').val() || !$('#modalToDate').val())) {
            // Only show modal if we don't have report data yet
            if ($('.report-preview').length === 0) {
                $('#customDateModal').modal('show');
            }
        }

        // Weekend blocking for date inputs
        $('#modalSingleDate, #modalFromDate, #modalToDate').on('change', function() {
            if (this.value) {
                var date = new Date(this.value);
                var day = date.getDay(); // 0 is Sun, 6 is Sat
                if (day === 0 || day === 6) {
                    alert("Selected date is a " + (day === 0 ? "Sunday" : "Saturday") + ", which is already a holiday. Please select a working day.");
                    this.value = "";
                }
            }
        });
    });
</script>