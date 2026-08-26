<?php
// session_start();
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
} // emp_area/includes/db.php
$errorFields = [];
$successMessage = "";

// Only allow access if logged in as employee
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}

$emp_id = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_leave'])) {
    $leave_from = mysqli_real_escape_string($con, $_POST['leave_from']);
    $leave_to = mysqli_real_escape_string($con, $_POST['leave_to']);
    $reason = mysqli_real_escape_string($con, $_POST['reason']);
    $leave_type_id = isset($_POST['leave_type_id']) ? intval($_POST['leave_type_id']) : -1;

    if (empty($leave_from)) $errorFields[] = 'leave_from';
    if (empty($leave_to)) $errorFields[] = 'leave_to';
    if (empty($reason)) $errorFields[] = 'reason';
    if ($leave_type_id < 0) $errorFields[] = 'leave_type_id';

    if (empty($errorFields)) {
        $from_ts = strtotime($leave_from);
        $to_ts   = strtotime($leave_to);

        // 1. Date Range Order Check
        if ($to_ts < $from_ts) {
            $_SESSION['leave_error'] = "Error: 'To Date' cannot be earlier than 'From Date'.";
        } else {
            $requested_days = (int)(($to_ts - $from_ts) / 86400) + 1;

            // 2. Check Overlapping Pending or Approved Leaves
            $overlap_q = mysqli_query($con, "SELECT id FROM leave_applications WHERE emp_id = '$emp_id' AND status IN ('approved', 'pending') AND (leave_from <= '$leave_to' AND leave_to >= '$leave_from') LIMIT 1");
            if ($overlap_q && mysqli_num_rows($overlap_q) > 0) {
                $_SESSION['leave_error'] = "Error: You already have a pending or approved leave application for dates overlapping with this period.";
            } else {
                // 3. Calculate Remaining Leaves for Selected Type
                $remaining_leaves = 0;
                $leave_type_name  = "Extra Leaves";

                if ($leave_type_id == 0) {
                    // Extra Leaves
                    $leave_type_name = "Extra Leaves";
                    $extra_assigned = 0;
                    $chk_ex = mysqli_query($con, "SELECT extra_leaves FROM emp_list WHERE id = '$emp_id' LIMIT 1");
                    if ($chk_ex && $ex_r = mysqli_fetch_assoc($chk_ex)) {
                        $extra_assigned = intval($ex_r['extra_leaves'] ?? 0);
                    }
                    $ex_used = 0;
                    $ex_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND (leave_type_id = 0 OR leave_type_id IS NULL) AND status IN ('approved', 'pending')");
                    if ($ex_q) {
                        while ($ex_r = mysqli_fetch_assoc($ex_q)) {
                            $ef = strtotime($ex_r['leave_from']);
                            $et = strtotime($ex_r['leave_to']);
                            if ($ef && $et && $et >= $ef) {
                                $ex_used += (int)(($et - $ef) / 86400) + 1;
                            }
                        }
                    }
                    $remaining_leaves = max(0, $extra_assigned - $ex_used);
                } else {
                    // Regular Leave Type
                    $lt_q = mysqli_query($con, "SELECT leave_name, num_of_leave FROM leave_types WHERE id = '$leave_type_id' AND deleted_at IS NULL LIMIT 1");
                    if ($lt_q && $lt_r = mysqli_fetch_assoc($lt_q)) {
                        $leave_type_name = $lt_r['leave_name'];
                        $allowed = intval($lt_r['num_of_leave']);
                        $type_used = 0;
                        $t_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND leave_type_id = '$leave_type_id' AND status IN ('approved', 'pending')");
                        if ($t_q) {
                            while ($t_r = mysqli_fetch_assoc($t_q)) {
                                $tf = strtotime($t_r['leave_from']);
                                $tt = strtotime($t_r['leave_to']);
                                if ($tf && $tt && $tt >= $tf) {
                                    $type_used += (int)(($tt - $tf) / 86400) + 1;
                                }
                            }
                        }
                        $remaining_leaves = max(0, $allowed - $type_used);
                    }
                }

                // 4. Validate Requested Days vs Remaining Quota
                if ($requested_days > $remaining_leaves) {
                    $_SESSION['leave_error'] = "Error: You applied for $requested_days day(s) of $leave_type_name, but you only have $remaining_leaves day(s) remaining for $leave_type_name.";
                } else {
                    // Insert Leave Application
                    $insert = "INSERT INTO leave_applications (emp_id, leave_type_id, leave_from, leave_to, reason, status) VALUES ('$emp_id', '$leave_type_id', '$leave_from', '$leave_to', '$reason', 'pending')";
                    if (mysqli_query($con, $insert)) {
                        $_SESSION['leave_success'] = "Leave application submitted successfully!";

                        // Send notification to admins
                        if (file_exists(__DIR__ . '/../../../admin_area/includes/notification_helper.php')) {
                            include_once(__DIR__ . '/../../../admin_area/includes/notification_helper.php');
                            if (function_exists('notifyAllAdmins')) {
                                $notif_title = "New Leave Request: " . $emp_name;
                                $notif_msg = $emp_name . " applied for " . $leave_type_name . " (" . date('d M Y', strtotime($leave_from)) . " to " . date('d M Y', strtotime($leave_to)) . ", " . $requested_days . " days). Reason: " . $reason;
                                $notif_url = "index.php?view_leave_requests";
                                notifyAllAdmins($notif_title, $notif_msg, $notif_url, 'warning');
                            }
                        }
                    } else {
                        $_SESSION['leave_error'] = "Error: " . mysqli_error($con);
                    }
                }
            }
        }
    } else {
        $_SESSION['leave_error'] = "Please fill in all required fields.";
    }

    // Redirect to prevent form resubmission using JavaScript
    $redirect_url = isset($_GET['leave_application']) ? 'index.php?leave_application' : $_SERVER['PHP_SELF'];
    echo "<script>window.open('$redirect_url','_self');</script>";
    exit();
}

// Retrieve messages from session if they exist
if (isset($_SESSION['leave_success'])) {
    $successMessage = $_SESSION['leave_success'];
    unset($_SESSION['leave_success']);
}
if (isset($_SESSION['leave_error'])) {
    $successMessage = $_SESSION['leave_error'];
    unset($_SESSION['leave_error']);
}

$is_partial = isset($_GET['partial']);

// Fetch Previous Leave Applications
$query = "SELECT la.*, lt.leave_name FROM leave_applications la LEFT JOIN leave_types lt ON la.leave_type_id = lt.id WHERE la.emp_id = '$emp_id' ORDER BY la.created_at DESC";
$result = mysqli_query($con, $query);
?>

<?php if (!$is_partial) : ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Leave Application</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../../../admin_area/css/bootstrap.min.css">
        <link href="../../../admin_area/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="../../../admin_area/css/style.css" rel="stylesheet">
        <style>
            body {
                background: #f4f7f6;
                padding-top: 20px;
            }

            .page-header {
                border-bottom: 1px solid #eee;
                margin-bottom: 20px;
            }

            .premium-stat-card {
                background: #fff;
                padding: 12px 18px;
                border-radius: 16px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                display: flex;
                align-items: center;
                gap: 15px;
                min-width: 140px;
                transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid #f1f5f9;
            }

            .premium-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                border-color: #e2e8f0;
            }
        </style>
    </head>

    <body>
        <div class="container">
        <?php endif; ?>

        <div class="premium-ui-enabled">
            <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; padding: 0 0 15px 0; margin-top: -5px;">
                <h1 style="margin: 0;"></h1>
                <button class="btn-premium-add" data-toggle="modal" data-target="#applyLeaveModal">
                    <i class="fa fa-plus"></i> Apply Leave
                </button>
            </div>

            <!-- Horizontal Scrollable Stats -->
            <div class="stats-scroll-wrapper" style="margin-bottom: 30px; position: relative;">
                <div class="stats-scroll-container" style="display: flex; gap: 15px; overflow-x: auto; padding: 5px 5px 15px 5px; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                        .stats-scroll-container::-webkit-scrollbar {
                            display: none;
                        }
                    </style>

                    <?php
                    // Check for extra_leaves assigned to this employee
                    $extra_leaves = 0;
                    $check_extra_q = mysqli_query($con, "SELECT extra_leaves FROM emp_list WHERE id = '$emp_id' LIMIT 1");
                    if ($check_extra_q && $extra_row = mysqli_fetch_assoc($check_extra_q)) {
                        $extra_leaves = intval($extra_row['extra_leaves'] ?? 0);
                    }

                    // Calculate used Extra Leaves (approved + pending)
                    $extra_used = 0;
                    $ex_apps_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND (leave_type_id = 0 OR leave_type_id IS NULL) AND status IN ('approved', 'pending')");
                    if ($ex_apps_q) {
                        while ($ex_app = mysqli_fetch_assoc($ex_apps_q)) {
                            $ef = strtotime($ex_app['leave_from']);
                            $et = strtotime($ex_app['leave_to']);
                            if ($ef && $et && $et >= $ef) {
                                $extra_used += (int)(($et - $ef) / 86400) + 1;
                            }
                        }
                    }
                    $extra_remaining = max(0, $extra_leaves - $extra_used);

                    // Calculate Overall Totals
                    $total_allowed = 0;
                    $total_allowed_q = mysqli_query($con, "SELECT SUM(num_of_leave) as total FROM leave_types WHERE deleted_at IS NULL");
                    if ($total_allowed_q) {
                        $total_allowed = (mysqli_fetch_assoc($total_allowed_q)['total'] ?: 0) + $extra_leaves;
                    } else {
                        $total_allowed = $extra_leaves;
                    }

                    // Total used days across all leave types (approved + pending)
                    $total_used = 0;
                    $all_apps_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND status IN ('approved', 'pending')");
                    if ($all_apps_q) {
                        while ($app = mysqli_fetch_assoc($all_apps_q)) {
                            $af = strtotime($app['leave_from']);
                            $at = strtotime($app['leave_to']);
                            if ($af && $at && $at >= $af) {
                                $total_used += (int)(($at - $af) / 86400) + 1;
                            }
                        }
                    }
                    $total_remaining = max(0, $total_allowed - $total_used);
                    ?>

                    <!-- 1. TOTAL LEAVE BOX -->
                    <div class="premium-stat-card" style="border-left: 4px solid #1e293b; min-width: 180px; flex-shrink: 0; background: #fff; border-radius: 20px; box-shadow: 0 8px 30px -5px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 15px; padding: 15px 20px; border: 1px solid #f1f5f9;">
                        <div style="flex: 1;">
                            <div style="font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Total Leave Summary</div>
                            <div style="display: flex; align-items: baseline; gap: 4px;">
                                <span style="font-size: 26px; font-weight: 950; color: #0f172a; line-height: 1;"><?php echo $total_remaining; ?></span>
                                <span style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Left</span>
                            </div>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(15, 23, 42, 0.05); color: #1e293b; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                            <i class="fa fa-pie-chart"></i>
                        </div>
                    </div>

                    <?php
                    $lt_sum = mysqli_query($con, "SELECT * FROM leave_types WHERE deleted_at IS NULL ORDER BY leave_name ASC");
                    $colors = ['#6366f1', '#10b981', '#3b82f6', '#f59e0b', '#ef4444'];
                    $bg_colors = ['rgba(99, 102, 241, 0.08)', 'rgba(16, 185, 129, 0.08)', 'rgba(59, 130, 246, 0.08)', 'rgba(245, 158, 11, 0.08)', 'rgba(239, 68, 68, 0.08)'];
                    $icons = ['fa-calendar-o', 'fa-heartbeat', 'fa-umbrella', 'fa-plane', 'fa-medkit'];
                    $c_idx = 0;

                    if ($lt_sum) {
                        while ($lt = mysqli_fetch_assoc($lt_sum)) {
                            $lt_id = $lt['id'];
                            $color = $colors[$c_idx % count($colors)];
                            $bg = $bg_colors[$c_idx % count($bg_colors)];
                            $icon = $icons[$c_idx % count($icons)];
                            $c_idx++;

                            $used = 0;
                            $type_apps_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND leave_type_id = '$lt_id' AND status IN ('approved', 'pending')");
                            if ($type_apps_q) {
                                while ($tapp = mysqli_fetch_assoc($type_apps_q)) {
                                    $tf = strtotime($tapp['leave_from']);
                                    $tt = strtotime($tapp['leave_to']);
                                    if ($tf && $tt && $tt >= $tf) {
                                        $used += (int)(($tt - $tf) / 86400) + 1;
                                    }
                                }
                            }
                            $total_lt = intval($lt['num_of_leave']);
                            $remaining_lt = max(0, $total_lt - $used);
                    ?>
                            <div class="premium-stat-card" style="border-left: 3.5px solid <?php echo $color; ?>; min-width: 170px; flex-shrink: 0; background: #fff; border-radius: 18px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 12px; padding: 12px 18px; border: 1px solid #f8fafc;">
                                <div style="flex: 1;">
                                    <div style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px;">
                                        <?php echo htmlspecialchars($lt['leave_name']); ?> <span style="opacity: 0.5; font-size: 8px;">(<?php echo $total_lt; ?>)</span>
                                    </div>
                                    <div style="display: flex; align-items: baseline; gap: 3px;">
                                        <span style="font-size: 22px; font-weight: 900; color: #0f172a; line-height: 1;"><?php echo $remaining_lt; ?></span>
                                        <span style="font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Left</span>
                                    </div>
                                </div>
                                <div style="width: 34px; height: 34px; border-radius: 10px; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                                    <i class="fa <?php echo $icon; ?>"></i>
                                </div>
                            </div>
                    <?php
                        }
                    }

                    if ($extra_leaves > 0) :
                    ?>
                        <div class="premium-stat-card" style="border-left: 3.5px solid #dd2127; min-width: 170px; flex-shrink: 0; background: #fff5f5; border-radius: 18px; box-shadow: 0 4px 20px -2px rgba(221,33,39,0.08); display: flex; align-items: center; gap: 12px; padding: 12px 18px; border: 1px solid #fca5a5;">
                            <div style="flex: 1;">
                                <div style="font-size: 9px; font-weight: 800; color: #991b1b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px;">
                                    Extra Leaves <span style="opacity: 0.7; font-size: 8px;">(+<?php echo $extra_leaves; ?>)</span>
                                </div>
                                <div style="display: flex; align-items: baseline; gap: 3px;">
                                    <span style="font-size: 22px; font-weight: 900; color: #7f1d1d; line-height: 1;"><?php echo $extra_remaining; ?></span>
                                    <span style="font-size: 10px; font-weight: 700; color: #991b1b; text-transform: uppercase;">Left</span>
                                </div>
                            </div>
                            <div style="width: 34px; height: 34px; border-radius: 10px; background: #fee2e2; color: #dd2127; display: flex; align-items: center; justify-content: center; font-size: 15px;">
                                <i class="fa fa-star"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($successMessage) : ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert <?php echo strpos($successMessage, 'Error') === false ? 'alert-success' : 'alert-danger'; ?> alert-dismissable">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-12">
                    <!-- Apply Leave Modal -->
                    <div class="modal fade" id="applyLeaveModal" tabindex="-1" role="dialog" aria-labelledby="applyLeaveModalLabel">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                                <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                                    <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                                        <div style="width: 36px; height: 36px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fa fa-plus" style="color: #fff; font-size: 14px;"></i>
                                        </div>
                                        <div>
                                            <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Apply for New Leave</h5>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </div>
                                <div class="modal-body" style="padding: 25px;">
                                    <form class="form-horizontal" method="POST" onsubmit="return validateLeaveForm();">
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Leave Type <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <select name="leave_type_id" id="leave_type_id_select" class="p-input-premium" required>
                                                    <option value="" data-remaining="0">Select Leave Type...</option>
                                                    <?php
                                                    $lt_query = mysqli_query($con, "SELECT * FROM leave_types WHERE deleted_at IS NULL ORDER BY leave_name ASC");
                                                    while ($lt = mysqli_fetch_assoc($lt_query)) {
                                                        $lt_id = $lt['id'];
                                                        $used_t = 0;
                                                        $t_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND leave_type_id = '$lt_id' AND status IN ('approved', 'pending')");
                                                        if ($t_q) {
                                                            while ($tr = mysqli_fetch_assoc($t_q)) {
                                                                $tf = strtotime($tr['leave_from']);
                                                                $tt = strtotime($tr['leave_to']);
                                                                if ($tf && $tt && $tt >= $tf) {
                                                                    $used_t += (int)(($tt - $tf) / 86400) + 1;
                                                                }
                                                            }
                                                        }
                                                        $rem_t = max(0, (int)$lt['num_of_leave'] - $used_t);
                                                        echo "<option value='" . $lt['id'] . "' data-remaining='" . $rem_t . "'>" . htmlspecialchars($lt['leave_name']) . " (" . $rem_t . " Left / " . $lt['num_of_leave'] . " Days/Yr)</option>";
                                                    }
                                                    if ($extra_leaves > 0) {
                                                        echo "<option value='0' data-remaining='" . $extra_remaining . "'>Extra Leaves (" . $extra_remaining . " Left / " . $extra_leaves . " Days/Yr)</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">From Date <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <input type="date" name="leave_from" id="leave_from_input" class="p-input-premium" required>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">To Date <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <input type="date" name="leave_to" id="leave_to_input" class="p-input-premium" required>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Reason for Leave <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <textarea name="reason" class="p-input-premium" style="height: 120px; resize: none;" placeholder="Please provide a brief reason for your leave request..." required></textarea>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-top: 30px; margin-bottom: 0;">
                                            <div class="col-md-12" style="display: flex; gap: 10px; justify-content: flex-end;">
                                                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                                                <button type="submit" name="apply_leave" class="btn-premium-add">
                                                    <i class="fa fa-paper-plane"></i> Submit Application
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        function validateLeaveForm() {
                            var typeSelect = document.querySelector('#applyLeaveModal select[name="leave_type_id"]');
                            var fromInput = document.querySelector('#applyLeaveModal input[name="leave_from"]');
                            var toInput = document.querySelector('#applyLeaveModal input[name="leave_to"]');
                            var reasonInput = document.querySelector('#applyLeaveModal textarea[name="reason"]');

                            if (!typeSelect.value) {
                                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Please select a leave type.', 'error');
                                else alert('Please select a leave type.');
                                return false;
                            }

                            if (!fromInput.value || !toInput.value) {
                                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Please select both From and To dates.', 'error');
                                else alert('Please select both From and To dates.');
                                return false;
                            }

                            var fromDate = new Date(fromInput.value);
                            var toDate = new Date(toInput.value);

                            if (toDate < fromDate) {
                                if (typeof Swal !== 'undefined') Swal.fire('Error', "'To Date' cannot be earlier than 'From Date'.", 'error');
                                else alert("'To Date' cannot be earlier than 'From Date'.");
                                return false;
                            }

                            // Calculate total days requested (inclusive)
                            var diffTime = Math.abs(toDate - fromDate);
                            var requestedDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                            // Check remaining balance from select option data-remaining attribute
                            var selectedOption = typeSelect.options[typeSelect.selectedIndex];
                            var remaining = parseInt(selectedOption.getAttribute('data-remaining') || '0', 10);

                            if (requestedDays > remaining) {
                                var typeName = selectedOption.text.split('(')[0].trim();
                                var msg = 'You are applying for ' + requestedDays + ' day(s) of ' + typeName + ', but you only have ' + remaining + ' day(s) remaining.';
                                if (typeof Swal !== 'undefined') {
                                    Swal.fire({ icon: 'error', title: 'Leave Limit Exceeded', text: msg });
                                } else {
                                    alert(msg);
                                }
                                return false;
                            }

                            return true;
                        }
                    </script>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="premium-card" style="border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 25px -5px rgba(0,0,0,0.08); background: #fff;">
                        <div class="card-hdr" style="background: var(--p-bg-header); padding: 18px 25px; display: flex; align-items: center; gap: 12px; border: none;">
                            <i class="fa fa-list-ul" style="font-size: 16px; color: #fff;"></i>
                            <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">History of Requests</h3>
                        </div>
                        <div class="table-responsive">
                            <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #fcfdfe; border-bottom: 1.5px solid #f1f5f9;">
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: center;">Applied On</th>
                                        <th style="text-align: center;">Leave Type</th>
                                        <th style="text-align: center;">From Date</th>
                                        <th style="text-align: center;">To Date</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0) : $i = 1; ?>
                                        <?php while ($row = mysqli_fetch_assoc($result)) :
                                            $st = strtolower($row['status']);
                                            $badge_style = 'background: #f1f5f9; color: #64748b;';
                                            if ($st == 'approved') $badge_style = 'background: #ecfdf5; color: #059669;';
                                            elseif ($st == 'rejected') $badge_style = 'background: #fef2f2; color: #dc2626;';
                                            elseif ($st == 'pending') $badge_style = 'background: #eff6ff; color: #2563eb;';
                                        ?>
                                            <tr>
                                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $i++; ?></td>
                                                <td style="text-align: center; font-weight: 500; color: #64748b; font-size: 13px;"><?php echo date('d-m-Y', strtotime($row['created_at'])); ?></td>
                                                <td style="text-align: center; padding: 12px;">
                                                    <span style="background: #ffeaeb; color: #dc2626; font-weight: 700; padding: 4px 12px; border-radius: 8px; font-size: 11px; display: inline-block;">
                                                        <?php echo !empty($row['leave_name']) ? htmlspecialchars($row['leave_name']) : 'Extra Leaves'; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center; font-weight: 600; color: #1e293b;"><?php echo date('d-m-Y', strtotime($row['leave_from'])); ?></td>
                                                <td style="text-align: center; font-weight: 600; color: #1e293b;"><?php echo date('d-m-Y', strtotime($row['leave_to'])); ?></td>
                                                <td style="text-align: center; padding: 15px;">
                                                    <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; <?php echo $badge_style; ?> display: inline-block; min-width: 90px;">
                                                        <?php echo ucfirst($st); ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 12px 15px; text-align: center; vertical-align: middle;">
                                                    <div style="max-width: 350px; margin: 0 auto; font-size: 13px; line-height: 1.5; color: #334155; max-height: 70px; overflow-y: auto; text-align: center; word-break: break-word;">
                                                        <?php echo htmlspecialchars(preg_replace('/\s+/', ' ', trim($row['reason']))); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                                                <i class="fa fa-folder-open-o" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                                                No leave requests found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Closing premium-ui-enabled div -->

        <?php if (!$is_partial) : ?>
        </div> <!-- Closing container div -->
    </body>

    </html>
<?php endif; ?>