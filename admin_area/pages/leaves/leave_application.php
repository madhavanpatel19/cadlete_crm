<?php
// session_start();
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
$errorFields = [];
$successMessage = "";

// Only allow access if logged in as employee
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/emp-login.php');
    exit();
}

$emp_id = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_leave'])) {
    $leave_from = mysqli_real_escape_string($con, $_POST['leave_from']);
    $leave_to = mysqli_real_escape_string($con, $_POST['leave_to']);
    $reason = mysqli_real_escape_string($con, $_POST['reason']);

    if (empty($leave_from)) $errorFields[] = 'leave_from';
    if (empty($leave_to)) $errorFields[] = 'leave_to';
    if (empty($reason)) $errorFields[] = 'reason';

    if (empty($errorFields)) {
        $leave_type_id = intval($_POST['leave_type_id']);
        $insert = "INSERT INTO leave_applications (emp_id, leave_type_id, leave_from, leave_to, reason, status) VALUES ('$emp_id', '$leave_type_id', '$leave_from', '$leave_to', '$reason', 'pending')";
        if (mysqli_query($con, $insert)) {
            $_SESSION['leave_success'] = "Leave application submitted successfully!";
        } else {
            $_SESSION['leave_error'] = "Error: " . mysqli_error($con);
        }
    } else {
        $_SESSION['leave_error'] = "Please fill in all required fields.";
    }

    // Redirect to prevent form resubmission using JavaScript since HTML might already be sent
    $redirect_url = $_SERVER['PHP_SELF'];
    if (isset($_GET['leave_application'])) {
        $redirect_url = "emp_index.php?leave_application";
    }
    echo "<script>window.open('$redirect_url','_self');</script>";
    exit();
}

// Retrieve messages from session if they exist
if (isset($_SESSION['leave_success'])) {
    $successMessage = $_SESSION['leave_success'];
    unset($_SESSION['leave_success']);
}
if (isset($_SESSION['leave_error'])) {
    $successMessage = $_SESSION['leave_error']; // Reusing the same variable for display logic below
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
        <title>Cadlete - Leave Application</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../../css/bootstrap.min.css">
        <link href="../../font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="../../css/style.css" rel="stylesheet">
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
            <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; margin-bottom: 15px;">
                <h1 style="font-size: 24px; font-weight: 800; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 12px;">
                    <i class="fa fa-calendar-check-o" style="color: #1e293b;"></i> Leave Application
                </h1>
                <button class="btn-premium-add" data-toggle="modal" data-target="#applyLeaveModal">
                    <i class="fa fa-plus"></i> Apply Leave
                </button>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div style="background: #f8fafc; border-radius: 14px; padding: 10px 20px; margin-bottom: 25px; display: flex; align-items: center; gap: 8px; border: 1px solid #f1f5f9; width: fit-content;">
                        <i class="fa fa-dashboard" style="color: #94a3b8; font-size: 12px;"></i>
                        <span style="color: #94a3b8; font-size: 13px;">Dashboard</span>
                        <span style="color: #cbd5e1; font-size: 12px;">/</span>
                        <span style="color: #1e293b; font-size: 13px; font-weight: 700;">Leave Application</span>
                    </div>
                </div>
            </div>

            <!-- Horizontal Scrollable Stats -->
            <div class="stats-scroll-wrapper" style="margin-bottom: 30px; position: relative;">
                <div style="display: flex; gap: 15px; overflow-x: auto; padding: 5px 5px 20px 5px; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                        .stats-scroll-wrapper div::-webkit-scrollbar {
                            display: none;
                        }
                    </style>

                    <?php
                    // Calculate Overall Totals
                    $total_allowed = 0;
                    $total_used = 0;

                    $total_allowed_q = mysqli_query($con, "SELECT SUM(num_of_leave) as total FROM leave_types WHERE deleted_at IS NULL");
                    if ($total_allowed_q) {
                        $total_allowed = mysqli_fetch_assoc($total_allowed_q)['total'] ?: 0;
                    }

                    // Count actual leave days from attendance (not raw date range),
                    // so days the employee worked within a leave period are not deducted.
                    $apps_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND status = 'approved'");
                    $total_used = 0;
                    if ($apps_q) {
                        while ($app = mysqli_fetch_assoc($apps_q)) {
                            $f = mysqli_real_escape_string($con, $app['leave_from']);
                            $t = mysqli_real_escape_string($con, $app['leave_to']);
                            $cnt_q = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM attendance WHERE emp_id = '$emp_id' AND attendance_date BETWEEN '$f' AND '$t' AND status = 'leave'");
                            if ($cnt_q) $total_used += (int)mysqli_fetch_assoc($cnt_q)['cnt'];
                        }
                    }
                    $total_remaining = $total_allowed - $total_used;
                    ?>

                    <!-- 1. TOTAL LEAVE BOX (DEFAULT) -->
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
                    $lt_sum = mysqli_query($con, "SELECT * FROM leave_types WHERE deleted_at IS NULL");
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

                            // Count actual leave days from attendance for this leave type
                            $used = 0;
                            $type_apps_q = mysqli_query($con, "SELECT leave_from, leave_to FROM leave_applications WHERE emp_id = '$emp_id' AND leave_type_id = '$lt_id' AND status = 'approved'");
                            if ($type_apps_q) {
                                while ($tapp = mysqli_fetch_assoc($type_apps_q)) {
                                    $tf = mysqli_real_escape_string($con, $tapp['leave_from']);
                                    $tt = mysqli_real_escape_string($con, $tapp['leave_to']);
                                    $tcnt_q = mysqli_query($con, "SELECT COUNT(*) AS cnt FROM attendance WHERE emp_id = '$emp_id' AND attendance_date BETWEEN '$tf' AND '$tt' AND status = 'leave'");
                                    if ($tcnt_q) $used += (int)mysqli_fetch_assoc($tcnt_q)['cnt'];
                                }
                            }
                            $total = $lt['num_of_leave'];
                            $remaining = $total - $used;
                    ?>
                            <div class="premium-stat-card" style="border-left: 3.5px solid <?php echo $color; ?>; min-width: 170px; flex-shrink: 0; background: #fff; border-radius: 18px; box-shadow: 0 4px 20px -2px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 12px; padding: 12px 18px; border: 1px solid #f8fafc;">
                                <div style="flex: 1;">
                                    <div style="font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px;">
                                        <?php echo htmlspecialchars($lt['leave_name']); ?> <span style="opacity: 0.5; font-size: 8px;">(<?php echo $total; ?>)</span>
                                    </div>
                                    <div style="display: flex; align-items: baseline; gap: 3px;">
                                        <span style="font-size: 22px; font-weight: 900; color: #0f172a; line-height: 1;"><?php echo $remaining; ?></span>
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
                    ?>
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
                    <!-- <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fa fa-paper-plane fa-fw"></i> Apply New Leave</h3>
            </div>
            <div class="panel-body">
                <div style="margin-bottom: 20px; font-weight: bold; color: #555;">
                    Employee: <?php echo htmlspecialchars($emp_name); ?> (ID: <?php echo htmlspecialchars($emp_id); ?>)
                </div>
                
                 Modal trigger only, form moved to modal below
            </div>
        </div> -->

                    <!-- Apply Leave Modal -->
                    <div class="modal fade" id="applyLeaveModal" tabindex="-1" role="dialog" aria-labelledby="applyLeaveModalLabel">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                                <div class="modal-header" style="background: #1f2937; color: #fff; padding: 20px 25px;">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;"><span aria-hidden="true">&times;</span></button>
                                    <h4 class="modal-title" id="applyLeaveModalLabel" style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 15px;">
                                        <i class="fa fa-calendar-plus-o"></i> Apply for New Leave
                                    </h4>
                                </div>
                                <div class="modal-body" style="padding: 25px;">
                                    <form class="form-horizontal" method="POST" onsubmit="return validateLeaveForm();">
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Leave Type <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <select name="leave_type_id" class="p-input-premium" required>
                                                    <option value="">Select Leave Type...</option>
                                                    <?php
                                                    $lt_query = mysqli_query($con, "SELECT * FROM leave_types WHERE deleted_at IS NULL ORDER BY leave_name ASC");
                                                    while ($lt = mysqli_fetch_assoc($lt_query)) {
                                                        echo "<option value='" . $lt['id'] . "'>" . $lt['leave_name'] . " (" . $lt['num_of_leave'] . " Days/Yr)</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">From Date <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <input type="date" name="leave_from" class="p-input-premium" required>
                                            </div>
                                        </div>
                                        <div class="form-group" style="margin-bottom: 20px;">
                                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">To Date <span class="text-danger">*</span></label>
                                            <div class="col-md-8">
                                                <input type="date" name="leave_to" class="p-input-premium" required>
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
                                                <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-weight: 600;">Cancel</button>
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
                            var from = document.querySelector('#applyLeaveModal input[name="leave_from"]');
                            var to = document.querySelector('#applyLeaveModal input[name="leave_to"]');
                            var reason = document.querySelector('#applyLeaveModal textarea[name="reason"]');
                            var valid = true;
                            [from, to, reason].forEach(function(field) {
                                if (!field.value) {
                                    field.parentElement.classList.add('has-error');
                                    valid = false;
                                } else {
                                    // Specific check for weekend dates
                                    if (field.name === 'leave_from' || field.name === 'leave_to') {
                                        var date = new Date(field.value);
                                        var day = date.getDay(); // 0 is Sun, 6 is Sat
                                        if (day === 0 || day === 6) {
                                            Swal.fire('Notification', "Selected date is a " + (day === 0 ? "Sunday" : "Saturday", 'info') + ", which is already a holiday. Please select a working day.");
                                            field.value = ""; // Reset the field
                                            valid = false;
                                        }
                                    }
                                    field.parentElement.classList.remove('has-error');
                                }
                            });
                            return valid;
                        }
                    </script>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="premium-card" style="border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 25px -5px rgba(0,0,0,0.08); background: #fff;">
                        <div class="card-hdr" style="background: #1e293b; color: #fff; padding: 18px 25px; display: flex; align-items: center; gap: 12px; border: none;">
                            <i class="fa fa-list-ul" style="font-size: 16px; color: #fff;"></i>
                            <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">History of Requests</h3>
                        </div>
                        <div style="overflow-x: auto;">
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
                                                <td style="text-align: center; font-weight: 500; color: #64748b; font-size: 13px;"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                                <td style="text-align: center; padding: 12px;">
                                                    <span style="background: #ffeaeb; color: #dc2626; font-weight: 700; padding: 4px 12px; border-radius: 8px; font-size: 11px; display: inline-block;">
                                                        <?php echo !empty($row['leave_name']) ? htmlspecialchars($row['leave_name']) : 'General Leave'; ?>
                                                    </span>
                                                </td>
                                                <td style="text-align: center; font-weight: 600; color: #1e293b;"><?php echo date('d M Y', strtotime($row['leave_from'])); ?></td>
                                                <td style="text-align: center; font-weight: 600; color: #1e293b;"><?php echo date('d M Y', strtotime($row['leave_to'])); ?></td>
                                                <td style="text-align: center; padding: 15px;">
                                                    <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; <?php echo $badge_style; ?> display: inline-block; min-width: 90px;">
                                                        <?php echo ucfirst($st); ?>
                                                    </span>
                                                </td>
                                                <td class="p-cell-wrap">
                                                    <div style="font-size: 13px; line-height: 1.6;">
                                                        <?php echo htmlspecialchars($row['reason']); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
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