<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Check admin session and load admin row for sidebar
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit;
}

if (!isset($admin_id)) {
    $admin_session = $_SESSION['admin_email'];
    $run_a = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email='" . mysqli_real_escape_string($con, $admin_session) . "' LIMIT 1");
    if ($run_a && $row_a = mysqli_fetch_assoc($run_a)) {
        $admin_id = $row_a['admin_id'];
    } else {
        $admin_id = 0;
    }
}

if (!function_exists('isSuperAdmin')) {
    include(__DIR__ . '/../../includes/admin_permissions.php');
}
if (function_exists('requireAdminPermission')) {
    requireAdminPermission('attendance_view');
}

// ------------ INPUTS (GET) ------------

// Get current year and month (for monthly view)
$current_year  = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// Get selected employee (for monthly view)
$selected_emp_id = isset($_GET['emp_id']) ? (int)$_GET['emp_id'] : 0;

// Daily attendance mode?
$is_daily       = isset($_GET['daily']) && $_GET['daily'] == 1;
$selected_date  = isset($_GET['date']) ? $_GET['date'] : '';

// Normalise month
if ($current_month < 1)  $current_month = 1;
if ($current_month > 12) $current_month = 12;

// Days in current month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

// ------------ ATTENDANCE TABLE CHECK ------------

$check_table   = mysqli_query($con, "SHOW TABLES LIKE 'attendance'");
$table_exists  = mysqli_num_rows($check_table) > 0;

if (!$table_exists) {
    // Create attendance table if it doesn't exist
    $create_table = "CREATE TABLE `attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `emp_id` INT NOT NULL,
        `attendance_date` DATE NOT NULL,
        `check_in_time` TIME NULL,
        `check_out_time` TIME NULL,
        `status` ENUM('present', 'absent', 'late') DEFAULT 'present',
        `remarks` VARCHAR(255),
        `performance` INT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `emp_date` (`emp_id`, `attendance_date`),
        FOREIGN KEY (`emp_id`) REFERENCES `emp_list` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";
    mysqli_query($con, $create_table);
} else {
    // Migrate old 'leave' to 'absent' and update ENUM
    $check_enum = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'status'");
    if ($check_enum) {
        $row_enum = mysqli_fetch_assoc($check_enum);
        if (strpos($row_enum['Type'], 'late') === false) {
            mysqli_query($con, "UPDATE attendance SET status='absent' WHERE status='leave'");
            mysqli_query($con, "ALTER TABLE `attendance` MODIFY `status` ENUM('present', 'absent', 'late') DEFAULT 'present'");
        }
    }
    // Ensure check_in_time column exists
    $col_check = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'check_in_time'");
    if ($col_check && mysqli_num_rows($col_check) === 0) {
        mysqli_query($con, "ALTER TABLE `attendance` ADD `check_in_time` TIME NULL AFTER `attendance_date`");
    }

    // Ensure check_out_time column exists
    $col_checkout = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'check_out_time'");
    if ($col_checkout && mysqli_num_rows($col_checkout) === 0) {
        mysqli_query($con, "ALTER TABLE `attendance` ADD `check_out_time` TIME NULL AFTER `check_in_time`");
    }

    // Ensure performance column exists
    $col_perf = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'performance'");
    if ($col_perf && mysqli_num_rows($col_perf) === 0) {
        mysqli_query($con, "ALTER TABLE `attendance` ADD `performance` INT DEFAULT NULL AFTER `remarks`");
    }

    // Ensure total_duration_secs column exists
    $col_duration = mysqli_query($con, "SHOW COLUMNS FROM attendance LIKE 'total_duration_secs'");
    if ($col_duration && mysqli_num_rows($col_duration) === 0) {
        mysqli_query($con, "ALTER TABLE `attendance` ADD `total_duration_secs` INT DEFAULT 0 AFTER `performance`");
    }
}

// ------------ HELPER FUNCTIONS ------------

function get_employees($con)
{
    $arr = array();
    $q = "SELECT id, name FROM emp_list ORDER BY name ASC";
    $r = mysqli_query($con, $q);
    while ($row = mysqli_fetch_assoc($r)) {
        $arr[] = $row;
    }
    return $arr;
}

function get_employee($con, $emp_id)
{
    $emp_id = (int)$emp_id;
    $q = "SELECT id, name FROM emp_list WHERE id='$emp_id' LIMIT 1";
    $r = mysqli_query($con, $q);
    return mysqli_num_rows($r) ? mysqli_fetch_assoc($r) : null;
}

function get_attendance_month($con, $emp_id, $month, $year)
{
    $ret   = array();
    $emp_id = (int)$emp_id;
    $month  = (int)$month;
    $year   = (int)$year;
    $q = "SELECT * FROM attendance 
          WHERE emp_id='$emp_id' 
            AND MONTH(attendance_date)='$month' 
            AND YEAR(attendance_date)='$year'";
    $r = mysqli_query($con, $q);
    while ($rec = mysqli_fetch_assoc($r)) {
        $ret[$rec['attendance_date']] = $rec;
    }
    return $ret;
}

function get_daily_attendance($con, $date)
{
    $ret  = array();
    $date = mysqli_real_escape_string($con, $date);
    $q = "SELECT * FROM attendance WHERE attendance_date='$date'";
    $r = mysqli_query($con, $q);
    while ($rec = mysqli_fetch_assoc($r)) {
        $ret[(int)$rec['emp_id']] = $rec;
    }
    return $ret;
}

// Normalize check-in time to 24-hour format (HH:MM:SS) to avoid AM/PM misreads
function normalize_checkin_time($raw)
{
    if ($raw === null) return null;
    $raw = trim($raw);
    if ($raw === '') return null;

    $formats = array('H:i:s', 'H:i', 'g:i a', 'g:i A', 'g:i:s a', 'g:i:s A', 'h:i A', 'h:i a', 'h:i:s A', 'h:i:s a');
    foreach ($formats as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $raw);
        if ($dt instanceof DateTime) {
            return $dt->format('H:i:s');
        }
    }

    // Fallback: basic HH:MM
    if (preg_match('/^(\\d{1,2}):(\\d{2})$/', $raw, $m)) {
        return sprintf('%02d:%02d:00', $m[1], $m[2]);
    }

    return null;
}

function save_attendance_record($con, $emp_id, $attendance_date, $status, $remarks = '', $check_in_time = null, $check_out_time = null, $performance = null)
{
    global $db;
    $eid  = (int)$emp_id;
    $date = mysqli_real_escape_string($con, $attendance_date);
    $st   = mysqli_real_escape_string($con, $status);

    $check = mysqli_query($con, "SELECT id FROM attendance WHERE emp_id='$eid' AND attendance_date='$date'");
    $exists = mysqli_num_rows($check) > 0;

    if ($st === 'delete' || $st === 'clear') {
        return true; // Deletion no longer supported
    }

    $rm_raw   = $remarks;
    $normalized_checkin = normalize_checkin_time($check_in_time);
    $normalized_checkout = normalize_checkin_time($check_out_time);
    $time_in = $normalized_checkin ? mysqli_real_escape_string($con, $normalized_checkin) : null;
    $time_out = $normalized_checkout ? mysqli_real_escape_string($con, $normalized_checkout) : null;
    $perf_val = is_numeric($performance) ? (int)$performance : null;

    // Auto-flag late if after 10:00:00 AM when marked present
    $late_cutoff = strtotime('1970-01-01 10:15:00');
    if ($time_in && ($status === 'present' || $status === 'late')) {
        $tstamp = strtotime('1970-01-01 ' . $normalized_checkin);
        if ($tstamp !== false && $tstamp > $late_cutoff) {
            $st = 'late';
            if (stripos($rm_raw, 'late') === false) {
                $rm_raw = ($rm_raw ? $rm_raw . ' | ' : '') . 'Late check-in';
            }
        } else {
            $st = 'present';
        }
    }

    $rm   = mysqli_real_escape_string($con, $rm_raw);

    $duration_secs = 0;
    if ($time_in && $time_out) {
        $in_t = strtotime("1970-01-01 $time_in");
        $out_t = strtotime("1970-01-01 $time_out");
        if ($out_t > $in_t) {
            $duration_secs = $out_t - $in_t;
        }
    }

    $row_id = null;
    if ($exists) {
        if (!canAdminAccess('attendance_insert')) return false;

        $existing = mysqli_fetch_assoc($check);
        $row_id = (int)$existing['id'];
        $update = "UPDATE attendance 
                   SET status='$st', remarks='$rm', check_in_time " . ($time_in !== null ? "='$time_in'" : "=NULL") . ", 
                       check_out_time " . ($time_out !== null ? "='$time_out'" : "=NULL") . ", total_duration_secs='$duration_secs'";
        if ($perf_val !== null) {
            $update .= ", performance='$perf_val'";
        }
        $update .= " 
                   WHERE emp_id='$eid' AND attendance_date='$date'";
        $ok = mysqli_query($con, $update);
    } else {
        if (!canAdminAccess('attendance_insert')) return false;

        $insert = "INSERT INTO attendance (emp_id, attendance_date, check_in_time, check_out_time, status, remarks, total_duration_secs";
        if ($perf_val !== null) {
            $insert .= ", performance";
        }
        $insert .= ", created_at";
        $insert .= ") 
                   VALUES ('$eid', '$date', " . ($time_in !== null ? "'$time_in'" : "NULL") . ", " . ($time_out !== null ? "'$time_out'" : "NULL") . ", '$st', '$rm', '$duration_secs'";
        if ($perf_val !== null) {
            $insert .= ", '$perf_val'";
        }
        $insert .= ", NOW())";
        $ok = mysqli_query($con, $insert);
        if ($ok) {
            $row_id = (int)mysqli_insert_id($con);
        }
    }



    return $ok;
}

function save_daily_attendance_batch($con, $date, $emp_ids, $statuses, $remarks_arr, $checkins_arr, $checkouts_arr = array(), $perf_arr = array())
{
    if (!$date || !is_array($emp_ids)) return false;
    foreach ($emp_ids as $idx => $e) {
        $eid = (int)$e;
        $st  = isset($statuses[$idx]) ? $statuses[$idx] : 'absent';
        $rm  = isset($remarks_arr[$idx]) ? $remarks_arr[$idx] : '';
        $ci  = isset($checkins_arr[$idx]) ? $checkins_arr[$idx] : null;
        $co  = isset($checkouts_arr[$idx]) ? $checkouts_arr[$idx] : null;
        $pf  = isset($perf_arr[$idx]) ? $perf_arr[$idx] : null;
        save_attendance_record($con, $eid, $date, $st, $rm, $ci, $co, $pf);
    }
    return true;
}

// ------------ FORM HANDLERS ------------

$message = null;

// Single record from modal (monthly view)
if (isset($_POST['save_attendance'])) {
    $emp_id          = isset($_POST['emp_id']) ? (int)$_POST['emp_id'] : 0;
    $attendance_date = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : '';
    $status          = isset($_POST['status']) ? $_POST['status'] : '';
    $remarks         = isset($_POST['remarks']) ? $_POST['remarks'] : '';
    $check_in_time   = isset($_POST['check_in_time']) ? $_POST['check_in_time'] : '';
    $check_out_time  = isset($_POST['check_out_time']) ? $_POST['check_out_time'] : '';
    $performance     = isset($_POST['performance']) ? $_POST['performance'] : null;

    if ($emp_id && $attendance_date && $status) {
        // Require check-in time only for presents
        if ($status === 'present' && empty($check_in_time)) {
            $message = "Check-in time is required for Present status.";
        } else {
            save_attendance_record($con, $emp_id, $attendance_date, $status, $remarks, $check_in_time ?: null, $check_out_time ?: null, $performance);
            $message = "Attendance updated successfully!";
        }
    } else {
        $message = "Invalid attendance data.";
    }
}

// Daily batch save
if (isset($_POST['save_daily_attendance'])) {
    $date        = isset($_POST['attendance_date']) ? $_POST['attendance_date'] : '';
    $emp_ids     = isset($_POST['emp_id']) ? $_POST['emp_id'] : array();
    $statuses    = isset($_POST['status']) ? $_POST['status'] : array();
    $remarks_arr = isset($_POST['remarks_arr']) ? $_POST['remarks_arr'] : array();
    $checkins_arr = isset($_POST['check_in_time_arr']) ? $_POST['check_in_time_arr'] : array();
    $checkouts_arr = isset($_POST['check_out_time_arr']) ? $_POST['check_out_time_arr'] : array();
    $perf_arr      = isset($_POST['performance_arr']) ? $_POST['performance_arr'] : array();

    if ($date && is_array($emp_ids)) {
        $validation_error = false;

        // Clean up and Auto-fill logic
        foreach ($emp_ids as $idx => $e) {
            $st = isset($statuses[$idx]) ? $statuses[$idx] : 'absent';
            $rm = isset($remarks_arr[$idx]) ? trim($remarks_arr[$idx]) : '';
            $ci = isset($checkins_arr[$idx]) ? trim($checkins_arr[$idx]) : '';

            // (Removed mandatory remarks for leave)

            // 2. Smart Auto-fill: If Present but no time, default to 10:00
            if ($st === 'present' && empty($ci)) {
                $checkins_arr[$idx] = '10:00';
            }
        }

        if (!$validation_error) {
            save_daily_attendance_batch($con, $date, $emp_ids, $statuses, $remarks_arr, $checkins_arr, $checkouts_arr, $perf_arr);
            $message = "Daily attendance saved successfully!";

            // Standardize redirect format to Y-m-d
            $iso_date = date('Y-m-d', strtotime($date));
            $redir = 'index.php?attendance&daily=1&date=' . urlencode($iso_date);
            if ($selected_emp_id > 0) {
                $redir .= '&emp_id=' . $selected_emp_id;
            }
            echo "<script>window.open('" . $redir . "', '_self')</script>";
            exit;
        }
    }
}

// ------------ LOAD DATA FOR VIEW ------------

$employees_array = get_employees($con);
$employee_data   = null;
$attendance_data = array();

if ($selected_emp_id > 0) {
    $employee_data = get_employee($con, $selected_emp_id);
    if ($employee_data) {
        $attendance_data = get_attendance_month($con, $selected_emp_id, $current_month, $current_year);
    }
}

$daily_attendance = array();
if ($is_daily && $selected_date) {
    $daily_attendance = get_daily_attendance($con, $selected_date);
}

// Calculate average daily performance for the month
$avg_daily_performance = null;
$daily_perf_count = 0;
if ($selected_emp_id > 0) {
    $avgQuery = mysqli_query($con, "SELECT AVG(performance) as avg_perf, COUNT(performance) as perf_count FROM attendance 
                                    WHERE emp_id='$selected_emp_id' AND MONTH(attendance_date)='$current_month' 
                                    AND YEAR(attendance_date)='$current_year' AND performance IS NOT NULL");
    if ($avgQuery && $avgResult = mysqli_fetch_assoc($avgQuery)) {
        if ((int)$avgResult['perf_count'] > 0) {
            $avg_daily_performance = round((float)$avgResult['avg_perf'], 2);
            $daily_perf_count = (int)$avgResult['perf_count'];
        }
    }
}

// Decide which screen to show
$showSelectionScreen = (!$is_daily && $selected_emp_id == 0);
$showDataScreen      = ($is_daily && $selected_date) || ($selected_emp_id > 0);
?>
<link href="css/attendance.css" rel="stylesheet">
<!-- INITIAL SELECTION SCREEN -->
<div id="selectionScreen" style="display: <?php echo $showSelectionScreen ? 'block' : 'none'; ?>;">
    <!-- <div class="custom-page-header">
        <h1><i class="fa fa-calendar-check-o"></i> Attendance</h1>
        <div class="header-actions">
            <a href="index.php?attendance_report" class="btn-premium-add" style="text-decoration: none;">
                <i class="fa fa-file-text-o"></i> View Reports
            </a>
        </div>
    </div> -->

    <div class="premium-card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden; margin-bottom: 25px; max-width: 450px;">
        <div class="card-hdr" style="padding: 20px 24px; background:var(--p-bg-header);color:white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
            <i class="fa fa-search"></i>
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Find Attendance</h3>
        </div>

        <div style="padding: 35px 30px; background: #fff;">
            <?php if ($message) : ?>
                <div class="alert alert-info" style="border-radius: 10px; margin-bottom: 25px; font-weight: 600; border: none; background: #f0f9ff; color: #DD2127;">
                    <i class="fa fa-info-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form id="selectionForm" method="GET">
                <div style="display: flex; flex-direction: column; gap: 25px; max-width: 400px;">
                    <!-- Mode Toggle -->
                    <div class="selection-control">
                        <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px; display: block;">View By</label>
                        <div class="p-radio-group">
                            <label class="p-radio-item">
                                <input type="radio" name="mode" value="monthly" checked>
                                <span>Month</span>
                            </label>
                            <label class="p-radio-item">
                                <input type="radio" name="mode" value="daily">
                                <span>Day</span>
                            </label>
                        </div>
                    </div>

                    <!-- Employee Selector -->
                    <div class="selection-control" id="empControl">
                        <label for="empSelectInitial" style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; display: block;">Employee</label>
                        <select id="empSelectInitial" name="emp_id" class="p-input-premium" style="appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right%2015px%20top%2050%25; background-size: 12px%20auto;">
                            <option value="">--Select Employee--</option>
                            <?php foreach ($employees_array as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Month Picker -->
                    <div class="selection-control" id="monthControl">
                        <label for="monthSelectInitial" style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: block;">Month</label>
                        <input type="month" id="monthSelectInitial" name="month_date" class="p-input-premium">
                    </div>

                    <!-- Date Picker (Hidden by default) -->
                    <div class="selection-control" id="dayControl" style="display:none;">
                        <label for="daySelectInitial" style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; display: block;">Date</label>
                        <input type="date" id="daySelectInitial" name="day_date" class="p-input-premium">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-premium-add" style="justify-content: center;">
                        Show Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DATA SCREEN (MONTHLY or DAILY) -->
<div id="dataScreen" style="display: <?php echo $showDataScreen ? 'block' : 'none'; ?>;">
    <div class="custom-page-header">
        <h1><i class="fa fa-table"></i> Attendance Sheet</h1>
        <div class="header-actions">
            <button type="button" class="btn-premium-add" onclick="changeSelection()">
                <i class="fa fa-arrow-left"></i> Back
            </button>
        </div>
    </div>

    <div class="premium-card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden; margin-bottom: 25px;">
        <!-- Monthly navigation only for monthly mode -->
        <?php if (!$is_daily && $selected_emp_id > 0): ?>
            <div class="sheet-controls" style="padding: 15px 24px; background: #fafafa; border-bottom: 1px solid #e2e8f0;">
                <div class="control-group">
                    <span class="month-year" style="font-weight: 700; color: #1e293b;">
                        <?php echo date('F Y', mktime(0, 0, 0, $current_month, 1, $current_year)); ?>
                    </span>
                    <div class="nav-buttons">
                        <?php
                        $prev_month = $current_month - 1;
                        $prev_year  = $current_year;
                        if ($prev_month < 1) {
                            $prev_month = 12;
                            $prev_year--;
                        }
                        $next_month = $current_month + 1;
                        $next_year  = $current_year;
                        if ($next_month > 12) {
                            $next_month = 1;
                            $next_year++;
                        }
                        $emp_param = "&emp_id=" . $selected_emp_id;
                        ?>
                        <a href="index.php?attendance&month=<?php echo $prev_month; ?>&year=<?php echo $prev_year . $emp_param; ?>" title="Previous Month">
                            <i class="fa fa-chevron-left"></i>
                        </a>
                        <a href="index.php?attendance&month=<?php echo date('m'); ?>&year=<?php echo date('Y') . $emp_param; ?>" title="Current Month">
                            <i class="fa fa-calendar"></i>
                        </a>
                        <a href="index.php?attendance&month=<?php echo $next_month; ?>&year=<?php echo $next_year . $emp_param; ?>" title="Next Month">
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($is_daily) :
            $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
            $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
        ?>
            <!-- Daily Nav -->
            <div style="padding: 15px 24px; background: #fafafa; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-clock-o" style="color: #64748b; font-size: 18px;"></i>
                    <h3 style="margin: 0; font-size: 15px; color: #1e293b; font-weight: 600;">Daily Entries: <span style="color: #dd2127;"><?php echo date('d M Y', strtotime($selected_date)); ?></span></h3>
                </div>
                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 13px; font-weight: 600; color: #64748b; margin: 0;">Jump to Date:</label>
                        <input type="date" class="p-input-premium" value="<?php echo $selected_date; ?>" style="width: 160px; padding: 6px 12px; height: auto;" onchange="
                            if(this.value) {
                                if (new Date(this.value) > new Date()) {
                                    alert('Cannot select future date!');
                                    this.value = '<?php echo $selected_date; ?>';
                                    return;
                                }
                                window.location.href = 'index.php?attendance&daily=1&date=' + encodeURIComponent(this.value);
                            }
                        ">
                    </div>
                    <div class="nav-buttons" style="display: flex; gap: 5px;">
                        <?php $emp_q = $selected_emp_id > 0 ? '&emp_id=' . $selected_emp_id : ''; ?>
                        <a href="index.php?attendance&daily=1&date=<?php echo $prev_date . $emp_q; ?>" title="Previous Day" style="padding: 6px 12px; background: #e2e8f0; color: #475569; border-radius: 6px; text-decoration: none; transition: 0.2s;">
                            <i class="fa fa-chevron-left"></i>
                        </a>
                        <a href="index.php?attendance&daily=1&date=<?php echo date('Y-m-d') . $emp_q; ?>" title="Today" style="padding: 6px 12px; background: #e2e8f0; color: #475569; border-radius: 6px; text-decoration: none; transition: 0.2s;">
                            <i class="fa fa-calendar"></i>
                        </a>
                        <a href="index.php?attendance&daily=1&date=<?php echo $next_date . $emp_q; ?>" title="Next Day" style="padding: 6px 12px; background: #e2e8f0; color: #475569; border-radius: 6px; text-decoration: none; transition: 0.2s;">
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert" style="margin: 15px 24px;">
                <i class="fa fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- DAILY VIEW -->
        <?php if ($is_daily && $selected_date): ?>
            <form method="POST">
                <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <div class="attendance-table-wrapper">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th class="hidden-xs">#</th>
                                <th class="hidden-xs">Employee ID</th>
                                <th>Employee Name</th>
                                <th style="min-width:120px;">Status</th>
                                <th style="white-space: nowrap;">Check-in Time</th>
                                <th style="white-space: nowrap;">Check-out Time</th>
                                <!-- <th style="white-space: nowrap;">Performance</th>
                                <th style="width: 30%;">Remarks</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $loop_employees = $employees_array;
                            if ($selected_emp_id > 0) {
                                // restrict to selected employee if exists and reindex
                                $loop_employees = array_values(array_filter($employees_array, function ($e) use ($selected_emp_id) {
                                    return (int)$e['id'] === $selected_emp_id;
                                }));
                            }
                            foreach ($loop_employees as $i => $emp):
                                $eid         = (int)$emp['id'];
                                // If POST (error), use submitted values, else use DB
                                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_id'][$i])) {
                                    $pref_status = isset($_POST['status'][$i]) ? $_POST['status'][$i] : '';
                                    $pref_remarks = isset($_POST['remarks_arr'][$i]) ? htmlspecialchars($_POST['remarks_arr'][$i]) : '';
                                    $pref_checkin = isset($_POST['check_in_time_arr'][$i]) ? $_POST['check_in_time_arr'][$i] : '';
                                    $pref_checkout = isset($_POST['check_out_time_arr'][$i]) ? $_POST['check_out_time_arr'][$i] : '';
                                    $pref_performance = isset($_POST['performance_arr'][$i]) ? $_POST['performance_arr'][$i] : '';

                                    if ($pref_status === 'present' && $pref_checkin) {
                                        $tstamp = strtotime('1970-01-01 ' . $pref_checkin);
                                        $late_cutoff = strtotime('1970-01-01 10:15:00');
                                        if ($tstamp !== false && $tstamp > $late_cutoff) {
                                            $pref_status = 'late';
                                        }
                                    }
                                } else {
                                    $pref        = isset($daily_attendance[$eid]) ? $daily_attendance[$eid] : null;
                                    $pref_status = $pref ? $pref['status'] : '';
                                    $pref_remarks = $pref ? htmlspecialchars($pref['remarks'] ?? '') : '';
                                    $pref_checkin = $pref ? htmlspecialchars($pref['check_in_time'] ?? '') : '10:00';
                                    $pref_checkout = $pref ? htmlspecialchars($pref['check_out_time'] ?? '') : '';
                                    $pref_performance = $pref ? htmlspecialchars($pref['performance'] ?? '') : '';

                                    if ($pref_status === 'present' && $pref_checkin) {
                                        $tstamp = strtotime('1970-01-01 ' . $pref_checkin);
                                        $late_cutoff = strtotime('1970-01-01 10:15:00');
                                        if ($tstamp !== false && $tstamp > $late_cutoff) {
                                            $pref_status = 'late';
                                        }
                                    }
                                }
                            ?>
                                <tr>
                                    <td class="hidden-xs"><?php echo $i + 1; ?></td>
                                    <td class="hidden-xs">
                                        <?php echo $eid; ?>
                                        <input type="hidden" name="emp_id[]" value="<?php echo $eid; ?>">
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <?php if (canAdminAccess('attendance_insert')): ?>
                                            <div class="status-options">
                                                <label class="status-btn<?php echo ($pref_status === 'present' || $pref_status === 'late' || $pref_status == '') ? ($pref_status === 'late' ? ' active-late' : ' active') : ''; ?>">
                                                    <input type="radio" name="status[<?php echo $i; ?>]" value="present" <?php echo ($pref_status === 'present' || $pref_status === 'late' || $pref_status == '') ? 'checked' : ''; ?> onclick="updateStatusUI(this)">P
                                                </label>
                                                <label class="status-btn<?php echo ($pref_status === 'absent') ? ' active' : ''; ?>">
                                                    <input type="radio" name="status[<?php echo $i; ?>]" value="absent" <?php echo ($pref_status === 'absent') ? 'checked' : ''; ?> onclick="updateStatusUI(this)">A
                                                </label>
                                            </div>
                                        <?php else: ?>
                                            <span style="font-weight: bold; text-transform: uppercase;">
                                                <?php echo htmlspecialchars($pref_status ? $pref_status : '-'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (canAdminAccess('attendance_insert')): ?>
                                            <input type="time" name="check_in_time_arr[]" value="<?php echo $pref_checkin; ?>" class="form-control input-sm" onchange="autoSetLateStatus(this, <?php echo $i; ?>)">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($pref_checkin ? date('h:i A', strtotime($pref_checkin)) : '-'); ?>
                                        <?php endif; ?>

                                        <?php
                                        $show_time_error = false;
                                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_daily_attendance'])) {
                                            if ($pref_status === 'present' && trim($pref_checkin) === '') {
                                                $show_time_error = true;
                                            }
                                        }
                                        if ($show_time_error): ?>
                                            <div class="inline-error" style="color:#d9534f; font-size:12px; margin-top:2px;">
                                                Check-in time is required for Present.
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (canAdminAccess('attendance_insert')): ?>
                                            <input type="time" name="check_out_time_arr[]" value="<?php echo $pref_checkout; ?>" class="form-control input-sm">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($pref_checkout ? date('h:i A', strtotime($pref_checkout)) : '-'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <!-- <td>
                                        <?php if (canAdminAccess('attendance_insert')): ?>
                                            <input type="number" name="performance_arr[]" value="<?php echo isset($pref_performance) ? htmlspecialchars($pref_performance) : ''; ?>" min="0" max="100" placeholder="0-100" class="form-control input-sm">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($pref_performance !== '' ? $pref_performance : '-'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (canAdminAccess('attendance_insert')): ?>
                                            <input type="text" name="remarks_arr[]" value="<?php echo $pref_remarks; ?>" placeholder="Optional remarks" class="form-control input-sm">
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($pref_remarks ? $pref_remarks : '-'); ?>
                                        <?php endif; ?>
                                    </td> -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (canAdminAccess('attendance_insert')): ?>
                    <div style="padding: 15px 24px; text-align:right; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                        <button type="submit" name="save_daily_attendance" class="btn-premium-add">
                            <i class="fa fa-save"></i> Save Attendance
                        </button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- MONTHLY VIEW -->
        <?php elseif ($selected_emp_id > 0 && $employee_data): ?>
            <div style="margin: 15px 24px; padding: 15px 20px; background: #f8fafc; border-left: 4px solid #4338ca; border-radius: 6px;">
                <h3 style="margin: 0; font-size: 15px; color: #1e293b;"><i class="fa fa-user" style="color: #64748b; margin-right: 5px;"></i> <?php echo htmlspecialchars($employee_data['name']); ?> <span style="font-size: 13px; font-weight: normal; color: #475569; margin-left: 10px;">(ID: <?php echo $selected_emp_id; ?>)</span></h3>
            </div>

            <div class="attendance-table-wrapper">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th style="white-space: nowrap; width: 80px;">Date</th>
                            <th style="white-space: nowrap; width: 60px;">Day</th>
                            <th style="white-space: nowrap; width: 100px;">Status</th>
                            <th style="white-space: nowrap; width: 100px;">Check-in</th>
                            <th style="white-space: nowrap; width: 100px;">Check-out</th>
                            <th style="white-space: nowrap; width: 100px;">Total Hours</th>
                            <!-- <th style="white-space: nowrap; width: 100px;">Performance</th>
                            <th style="white-space: nowrap; width: 160px;">Created At</th>
                            <th>Remarks</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $present_count = 0;
                        $absent_count  = 0;
                        $late_count    = 0;
                        $marked_days   = 0;
                        $today = date('Y-m-d');
                        $can_edit_today = false;
                        if (function_exists('canAdminAccess')) {
                            $can_edit_today = canAdminAccess('attendance_insert');
                        }

                        $total_secs_month = 0;
                        $total_display_minutes = 0;

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date     = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                            $day_name = date('D', strtotime($date));

                            $status       = '';
                            $status_class = 'unmarked';
                            $remarks      = '';
                            $perf         = '';
                            $display_duration = '-';

                            if (isset($attendance_data[$date])) {
                                $status       = ucfirst($attendance_data[$date]['status']);
                                $status_class = $attendance_data[$date]['status'];
                                $remarks      = nl2br(htmlspecialchars($attendance_data[$date]['remarks'] ?? ''));
                                $checkin      = htmlspecialchars($attendance_data[$date]['check_in_time'] ?? '');
                                $checkout     = htmlspecialchars($attendance_data[$date]['check_out_time'] ?? '');

                                if ($status_class === 'present' && $checkin) {
                                    $tstamp = strtotime('1970-01-01 ' . $checkin);
                                    $late_cutoff = strtotime('1970-01-01 10:15:00');
                                    if ($tstamp !== false && $tstamp > $late_cutoff) {
                                        $status = 'Late';
                                        $status_class = 'late';
                                    }
                                }

                                // Calculate formatted duration
                                $row_secs = isset($attendance_data[$date]['total_duration_secs']) ? (int)$attendance_data[$date]['total_duration_secs'] : 0;
                                if ($date === date('Y-m-d') && isset($attendance_data[$date]['is_working']) && $attendance_data[$date]['is_working'] == 1) {
                                    $last_res = $attendance_data[$date]['last_resume_time'];
                                    $db_tot_secs = (int)($attendance_data[$date]['total_duration_secs'] ?? 0);
                                    $start_time_calc = ($db_tot_secs > 0 && !empty($last_res)) ? $last_res : ($date . ' ' . $checkin);
                                    if (!empty($start_time_calc)) {
                                        $row_secs = $db_tot_secs + (time() - strtotime($start_time_calc));
                                    }
                                } elseif ($row_secs == 0 && $checkin && $checkout) {
                                    $in_t = strtotime("1970-01-01 $checkin");
                                    $out_t = strtotime("1970-01-01 $checkout");
                                    if ($out_t > $in_t) {
                                        $row_secs = $out_t - $in_t;
                                    }
                                }
                                $total_secs_month += $row_secs;

                                if ($row_secs > 0) {
                                    $rh = floor($row_secs / 3600);
                                    $rm = floor(($row_secs % 3600) / 60);
                                    $display_duration = "{$rh}h {$rm}m";
                                    $total_display_minutes += ($rh * 60) + $rm;
                                }

                                $created_at   = htmlspecialchars($attendance_data[$date]['created_at'] ?? '');
                                $perf         = htmlspecialchars($attendance_data[$date]['performance'] ?? '');
                                $marked_days++;

                                if ($status_class === 'late') $late_count++;
                                elseif ($attendance_data[$date]['status'] === 'present') $present_count++;
                                elseif ($attendance_data[$date]['status'] === 'absent')  $absent_count++;
                                elseif ($attendance_data[$date]['status'] === 'holiday') {
                                    // No count increase for now unless requested
                                }
                            } else {
                                if ($day_name === 'Sat' || $day_name === 'Sun') {
                                    $status = 'Holiday';
                                    $status_class = 'holiday';
                                } else {
                                    $status = '-';
                                }
                                $checkin = '';
                                $checkout = '';
                                $created_at = '';
                            }

                            echo '<tr>';
                            echo '<td style="white-space: nowrap;"><strong>' . date('d-m-y', strtotime($date)) . '</strong></td>';
                            echo '<td style="white-space: nowrap;">' . $day_name . '</td>';
                            // Lock editing if not permitted
                            if (!$can_edit_today) {
                                echo '<td class="date-cell ' . $status_class . '" title="Editing locked by admin permission" style="white-space: nowrap;">' . $status . '</td>';
                            } else {
                                echo '<td class="date-cell ' . $status_class . '" onclick="openModal(' . $selected_emp_id . ', \'' . $date . '\', \'' . $checkin . '\')" title="Click to mark attendance" style="white-space: nowrap;">' . $status . '</td>';
                            }
                            echo '<td style="white-space: nowrap;">' . ($checkin ? $checkin : '-') . '</td>';
                            echo '<td style="white-space: nowrap;">' . ($checkout ? $checkout : '-') . '</td>';
                            echo '<td style="white-space: nowrap; color: #3b82f6; font-weight: 600;">' . $display_duration . '</td>';
                            // echo '<td style="white-space: nowrap;">' . ($perf !== '' ? $perf : '-') . '</td>';
                            // echo '<td style="white-space: nowrap;">' . ($created_at ? date('d-m-y H:i:s', strtotime($created_at)) : '-') . '</td>';
                            // echo '<td class="remarks-cell" style="max-width: 250px; word-wrap: break-word; word-break: break-word; white-space: normal;">' . ($remarks ? $remarks : '-') . '</td>';
                            echo '</tr>';
                        }
                        ?>
                        <tr class="summary-row" style="background: #f1f5f9; font-weight: 700; border-top: 2px solid #cbd5e1;">
                            <td colspan="3" style="text-align: right; text-transform: uppercase; letter-spacing: 0.05em; color: #475569;">Monthly Summary</td>
                            <td colspan="2">
                                <span style="color: #10b981;">P: <?php echo $present_count; ?></span> |
                                <span style="color: #ef4444;">A: <?php echo $absent_count; ?></span> |
                                <span style="color: #e65100;">L: <?php echo $late_count; ?></span> |
                                <span style="color: #6366f1;">Total: <?php echo $marked_days; ?></span>
                            </td>

                            <td style="color: #3b82f6;">
                                <?php
                                if ($total_display_minutes > 0) {
                                    $th = floor($total_display_minutes / 60);
                                    $tm = $total_display_minutes % 60;
                                    echo "{$th}h {$tm}m";
                                } else {
                                    echo "-";
                                }
                                ?>
                            </td>

                            <!-- <td>
                                <?php if ($avg_daily_performance !== null): ?>
                                    <span style="background: #3b82f6; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                        <?php echo $avg_daily_performance; ?>%
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td colspan="2"></td> -->
                        </tr>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <div class="alert" style="margin: 15px 24px;">
                <i class="fa fa-info-circle"></i> Please go back and select employee or daily mode.
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
</div>

<!-- MODAL (Monthly mark attendance) -->
<div id="attendanceModal" class="modal">
    <div class="modal-dialog">
        <div class="modal-header">
            <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
            <h4>Mark Attendance</h4>
        </div>
        <form id="attendanceForm" method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Date:</label>
                    <div id="modalDate" style="padding: 6px 10px; background: #f9f9f9; border-radius: 3px;"></div>
                </div>
                <div class="form-group">
                    <label for="status">Status: <span style="color: #d9534f;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="">Select Status</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="check_in_time">Check-in Time: <span style="color: #d9534f;">*</span></label>
                    <input type="time" id="check_in_time" name="check_in_time" class="form-control">
                </div>
                <div class="form-group">
                    <label for="check_out_time">Check-out Time:</label>
                    <input type="time" id="check_out_time" name="check_out_time" class="form-control">
                </div>
                <!-- <div class="form-group">
                    <label for="performance">Performance (0-100):</label>
                    <input type="number" min="0" max="100" id="performance" name="performance" class="form-control" placeholder="Optional">
                </div>
                <div class="form-group">
                    <label for="remarks">Remarks: <span id="remarksRequired" style="color: #d9534f; display:none;">*</span></label>
                    <textarea id="remarks" name="remarks" class="form-control" placeholder="Optional remarks..."></textarea>
                </div> -->
                <input type="hidden" id="emp_id" name="emp_id">
                <input type="hidden" id="attendance_date" name="attendance_date">
                <input type="hidden" name="save_attendance" value="1">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-primary btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ---- AUTO LATE STATUS UI ----
    function updateStatusUI(radio) {
        let name = radio.name;
        let allRadios = document.querySelectorAll('input[name="' + name + '"]');
        allRadios.forEach(r => {
            if (r.checked) {
                if (r.value === 'present') {
                    let tr = r.closest('tr');
                    let timeInput = tr.querySelector('input[type="time"]');
                    let isLate = false;
                    if (timeInput && timeInput.value) {
                        let timeParts = timeInput.value.split(':');
                        let hours = parseInt(timeParts[0]);
                        let minutes = parseInt(timeParts[1]);
                        if (hours > 10 || (hours === 10 && minutes > 15)) {
                            isLate = true;
                        }
                    }
                    if (isLate) {
                        r.parentElement.classList.add('active-late');
                        r.parentElement.classList.remove('active');
                    } else {
                        r.parentElement.classList.add('active');
                        r.parentElement.classList.remove('active-late');
                    }
                } else {
                    r.parentElement.classList.add('active');
                    r.parentElement.classList.remove('active-late');
                }
            } else {
                r.parentElement.classList.remove('active');
                r.parentElement.classList.remove('active-late');
            }
        });
    }

    function autoSetLateStatus(input, index) {
        let pRadio = document.querySelector('input[name="status[' + index + ']"][value="present"]');
        let aRadio = document.querySelector('input[name="status[' + index + ']"][value="absent"]');

        // Only change it if they haven't explicitly marked it Absent
        if (aRadio && !aRadio.checked && pRadio) {
            pRadio.checked = true;
            updateStatusUI(pRadio);
        }
    }

    // ---- REMARKS VALIDATION (REMOVED LEAVE VALIDATION) ----
    function validateLeaveRemarks() {
        return true;
    }

    // Intercept daily attendance form submission
    document.addEventListener('DOMContentLoaded', function() {
        const dailyForm = document.querySelector('form');
        if (dailyForm && dailyForm.querySelector('input[name="save_daily_attendance"]')) {
            dailyForm.addEventListener('submit', function(e) {
                if (!validateLeaveRemarks()) {
                    e.preventDefault();
                    return false;
                }
            });
        }

        // Modal form validation
        const attendanceForm = document.getElementById('attendanceForm');
        if (attendanceForm) {
            // Insert inline error container if not present
            let inlineErr = document.createElement('div');
            inlineErr.id = 'modalLeaveError';
            inlineErr.style.display = 'none';
            const lastGroup = attendanceForm.querySelector('.form-group:last-child') || attendanceForm.querySelector('.modal-body') || attendanceForm;
            if (lastGroup) {
                lastGroup.appendChild(inlineErr);
            }

            attendanceForm.addEventListener('submit', function(e) {
                const status = document.getElementById('status').value;
                const remarks = document.getElementById('remarks').value.trim();
                const checkIn = document.getElementById('check_in_time').value;
                const errDiv = document.getElementById('modalLeaveError');

                if (status === 'present' && !checkIn) {
                    e.preventDefault();
                    errDiv.textContent = 'Check-in time is required for Present status.';
                    errDiv.style.display = 'block';
                    document.getElementById('check_in_time').focus();
                    return false;
                }
                errDiv.style.display = 'none';
            });
        }
    });

    // ---- SELECTION SCREEN ----
    document.addEventListener('DOMContentLoaded', function() {
        const selectionForm = document.getElementById('selectionForm');
        const modeRadios = document.querySelectorAll('input[name="mode"]');
        const empControl = document.getElementById('empControl');
        const monthControl = document.getElementById('monthControl');
        const dayControl = document.getElementById('dayControl');
        const empSelect = document.getElementById('empSelectInitial');
        const monthInput = document.getElementById('monthSelectInitial');
        const dayInput = document.getElementById('daySelectInitial');

        function updateModeUI() {
            const mode = document.querySelector('input[name="mode"]:checked').value;
            if (mode === 'daily') {
                empControl.style.display = 'none';
                monthControl.style.display = 'none';
                dayControl.style.display = 'block';

                empSelect.removeAttribute('required');
                monthInput.removeAttribute('required');
                dayInput.setAttribute('required', 'required');
            } else {
                empControl.style.display = 'block';
                monthControl.style.display = 'block';
                dayControl.style.display = 'none';

                empSelect.setAttribute('required', 'required');
                monthInput.setAttribute('required', 'required');
                dayInput.removeAttribute('required');
            }
        }

        modeRadios.forEach(function(r) {
            r.addEventListener('change', updateModeUI);
        });
        updateModeUI();

        // Default month: current month
        if (monthInput) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            monthInput.value = year + '-' + month;
        }

        // Default date: today
        if (dayInput) {
            const t = new Date();
            const y = t.getFullYear();
            const m = String(t.getMonth() + 1).padStart(2, '0');
            const d = String(t.getDate()).padStart(2, '0');
            dayInput.value = y + '-' + m + '-' + d;
        }

        if (selectionForm) {
            selectionForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const mode = document.querySelector('input[name="mode"]:checked').value;
                if (mode === 'daily') {
                    const dayVal = dayInput.value;
                    if (!dayVal) {
                        alert('Please select a date for daily attendance.');
                        return;
                    }
                    window.location.href = 'index.php?attendance&daily=1&date=' + encodeURIComponent(dayVal);
                } else {
                    const empId = empSelect.value;
                    const monthValue = monthInput.value;
                    if (!empId) {
                        alert('Please select an employee for monthly report.');
                        return;
                    }
                    if (!monthValue) {
                        alert('Please select month.');
                        return;
                    }
                    const parts = monthValue.split('-');
                    const year = parts[0];
                    const month = parts[1];
                    window.location.href = 'index.php?attendance&emp_id=' + empId + '&month=' + month + '&year=' + year;
                }
            });
        }
    });

    function changeSelection() {
        window.location.href = 'index.php?attendance';
    }

    // ---- MODAL (monthly mark) ----
    function openModal(empId, date, checkIn = '', checkOut = '', remarks = '', perf = '') {
        document.getElementById('emp_id').value = empId;
        document.getElementById('attendance_date').value = date;
        document.getElementById('modalDate').textContent = new Date(date).toLocaleDateString('en-GB', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        document.getElementById('status').value = '';
        document.getElementById('remarks').value = remarks || '';
        document.getElementById('check_in_time').value = checkIn || '10:00';
        document.getElementById('check_out_time').value = checkOut || '';
        document.getElementById('performance').value = perf || '';
        document.getElementById('attendanceModal').style.display = 'block';
        updateRemarksRequirement();
    }

    function closeModal() {
        document.getElementById('attendanceModal').style.display = 'none';
    }

    // Show/hide remarks required indicator based on status
    function updateRemarksRequirement() {
        const status = document.getElementById('status').value;
        const remarksRequired = document.getElementById('remarksRequired');
        const remarksTextarea = document.getElementById('remarks');

        if (status === 'leave') {
            remarksRequired.style.display = 'inline';
            remarksTextarea.placeholder = 'Required - Please provide reason for leave...';
        } else {
            remarksRequired.style.display = 'none';
            remarksTextarea.placeholder = 'Optional remarks...';
        }
    }

    // Attach status change listener
    document.addEventListener('DOMContentLoaded', function() {
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            statusSelect.addEventListener('change', updateRemarksRequirement);
        }
    });

    window.onclick = function(event) {
        const modal = document.getElementById('attendanceModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };

    // ---- DAILY STATUS BUTTON ACTIVE STATE ----
    (function() {
        function syncStatusButtons() {
            document.querySelectorAll('.status-options').forEach(function(group) {
                const radios = group.querySelectorAll('input[type=radio]');
                const checkInput = group.parentElement.parentElement.querySelector('input[name^="check_in_time_arr"]');
                radios.forEach(function(r) {
                    const lbl = r.closest('label');
                    if (!lbl) return;
                    if (r.checked) lbl.classList.add('active');
                    else lbl.classList.remove('active');
                    r.addEventListener('change', function() {
                        radios.forEach(function(rr) {
                            const l2 = rr.closest('label');
                            if (!l2) return;
                            l2.classList.remove('active');
                        });
                        const l = this.closest('label');
                        if (l) l.classList.add('active');

                        // Toggle check-in required only for present
                        if (checkInput) {
                            if (this.value === 'present') {
                                checkInput.setAttribute('required', 'required');
                            } else {
                                checkInput.removeAttribute('required');
                            }
                        }
                    });
                });

                // initial state
                if (checkInput) {
                    const checked = group.querySelector('input[type=radio]:checked');
                    if (checked && checked.value === 'present') {
                        checkInput.setAttribute('required', 'required');
                    } else {
                        checkInput.removeAttribute('required');
                    }
                }
            });
        }

        function attachLabelClickers() {
            document.querySelectorAll('.status-btn').forEach(function(lbl) {
                lbl.addEventListener('click', function() {
                    const input = lbl.querySelector('input[type=radio]');
                    if (input) {
                        input.checked = true;
                        const ev = new Event('change', {
                            bubbles: true
                        });
                        input.dispatchEvent(ev);
                    }
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                syncStatusButtons();
                attachLabelClickers();
            });
        } else {
            syncStatusButtons();
            attachLabelClickers();
        }
    })();
</script>