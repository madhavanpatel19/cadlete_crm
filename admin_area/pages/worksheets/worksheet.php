<?php
// session_start();
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
$errorFields = [];
$successMessage = "";

// Only allow access if logged in
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/emp-login.php');
    exit();
}

$emp_id = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

// Partial rendering logic for dashboard integration
$is_partial = isset($_GET['partial']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $progress_input = isset($_POST['task_progress']) ? trim($_POST['task_progress']) : (isset($_POST['task']) ? trim($_POST['task']) : '');
    $planning_input = isset($_POST['task_planning']) ? trim($_POST['task_planning']) : '';
    $issues_input   = isset($_POST['task_issues']) ? trim($_POST['task_issues']) : '';
    $help_input     = isset($_POST['task_help']) ? trim($_POST['task_help']) : '';

    if (empty($progress_input)) {
        $errorFields[] = 'task_progress';
    }
    $required = ['date', 'start_time', 'end_time'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errorFields[] = $field;
        }
    }

    if (empty($errorFields)) {
        $attendance_date = mysqli_real_escape_string($con, $_POST['date']);
        $today_date = date('Y-m-d');

        if ($attendance_date !== $today_date) {
            $errorFields[] = 'date';
            $successMessage = "Error: You can only submit worksheet for the current date.";
        } else {
            $check_in_time = mysqli_real_escape_string($con, $_POST['start_time']);
            $check_out_time = mysqli_real_escape_string($con, $_POST['end_time']);

            $parts = [];
            $parts[] = "Today’s Progress:\n" . $progress_input;
            if (!empty($planning_input)) $parts[] = "Planning for Tomorrow:\n" . $planning_input;
            if (!empty($issues_input))   $parts[] = "Issues:\n" . $issues_input;
            if (!empty($help_input))     $parts[] = "Need any Help?:\n" . $help_input;

            $remarks = mysqli_real_escape_string($con, implode("\n\n", $parts));

            // Check if record exists for this emp/date
            $check = mysqli_query($con, "SELECT id FROM attendance WHERE emp_id='$emp_id' AND attendance_date='$attendance_date'");
            if (mysqli_num_rows($check) > 0) {
                // Update
                $update = "UPDATE attendance SET check_in_time='$check_in_time', check_out_time='$check_out_time', remarks='$remarks' WHERE emp_id='$emp_id' AND attendance_date='$attendance_date'";
                if (mysqli_query($con, $update)) {
                    $successMessage = "Worksheet updated successfully!";
                }
            } else {
                // Insert
                $insert = "INSERT INTO attendance (emp_id, attendance_date, check_in_time, check_out_time, status, remarks) VALUES ('$emp_id', '$attendance_date', '$check_in_time', '$check_out_time', 'present', '$remarks')";
                if (mysqli_query($con, $insert)) {
                    $successMessage = "Worksheet submitted successfully!";
                }
            }
        }
    }
}

// Fetch recent worksheet history
$history_query = "SELECT * FROM attendance WHERE emp_id = '$emp_id' ORDER BY attendance_date DESC LIMIT 10";
$history_result = mysqli_query($con, $history_query);

// Fetch today's record for auto-filling worksheet
$today_date = date('Y-m-d');
$today_q = "SELECT check_in_time, check_out_time, remarks FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today_date'";
$today_res = mysqli_query($con, $today_q);
$today_att = mysqli_fetch_assoc($today_res);
$prefill_in = ($today_att && $today_att['check_in_time']) ? date('H:i', strtotime($today_att['check_in_time'])) : '';
$prefill_out = ($today_att && $today_att['check_out_time']) ? date('H:i', strtotime($today_att['check_out_time'])) : date('H:i');
$prefill_task = ($today_att && !empty($today_att['remarks'])) ? $today_att['remarks'] : '';

if (!function_exists('parse_work_details')) {
    function parse_work_details(string $text = '')
    {
        $text = trim($text ?? '');
        $res = ['progress' => '', 'planning' => '', 'issues' => '', 'help' => ''];
        if (empty($text)) {
            return $res;
        }

        $headers_pattern = "/(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?)/iu";

        if (preg_match($headers_pattern, $text)) {
            $clean = function ($s) {
                $s = preg_replace("/^Today[’']s Progress:\s*/iu", "", $s);
                $s = preg_replace("/^Planning for Tomorrow:\s*/iu", "", $s);
                $s = preg_replace("/^Issues:\s*/iu", "", $s);
                $s = preg_replace("/^Need any Help\??:\s*/iu", "", $s);
                $s = preg_replace("/\n{2,}/", "\n", $s);
                return trim($s);
            };

            if (preg_match("/Today[’']s Progress:\s*(.*?)(?=(?:Planning for Tomorrow:|Issues:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['progress'] = $clean($m[1]);
            }
            if (preg_match("/Planning for Tomorrow:\s*(.*?)(?=(?:Today[’']s Progress:|Issues:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['planning'] = $clean($m[1]);
            }
            if (preg_match("/Issues:\s*(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['issues'] = $clean($m[1]);
            }
            if (preg_match("/Need any Help\?:?\s*(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:)|$)/isu", $text, $m)) {
                $res['help'] = $clean($m[1]);
            }

            if (empty($res['progress'])) {
                if (preg_match("/^(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?))/isu", $text, $m)) {
                    $res['progress'] = $clean($m[1]);
                }
            }
        } else {
            $res['progress'] = $text;
        }
        return $res;
    }
}
$parsed_task = parse_work_details($prefill_task);
?>

<?php if (!$is_partial) : ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Cadlete - Worksheet</title>
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

            /* Page Header Styling */
            .page-header {
                border-bottom: 1px solid #eee;
                margin-bottom: 20px;
            }
        </style>
    </head>

    <body>
        <div class="container">
        <?php endif; ?>

        <div class="premium-ui-enabled">
            <div class="page-header-premium">
                <h1><i class="fa fa-pencil-square-o"></i> Daily Worksheet</h1>
                <div class="header-actions">
                    <button class="btn-premium-add" data-toggle="modal" data-target="#addWorksheetModal">
                        <i class="fa fa-plus"></i> Add Worksheet
                    </button>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <ol class="breadcrumb" style="background: #f1f5f9; border-radius: 12px; padding: 12px 20px; margin-bottom: 25px;">
                        <li><a href="index.php?dashboard" style="color: #64748b; text-decoration: none;"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li class="active" style="color: #1e293b; font-weight: 600;">Worksheet</li>
                    </ol>
                </div>
            </div>

            <?php if ($successMessage) : ?>
                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert <?php echo strpos($successMessage, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?> alert-dismissable">
                            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                            <i class="fa <?php echo strpos($successMessage, 'Error') !== false ? 'fa-exclamation-triangle' : 'fa-check'; ?>"></i> <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-12">
                    <div class="premium-card">
                        <div class="card-hdr">
                            <i class="fa fa-history"></i>
                            <h3>Recent Submissions</h3>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="table-premium">
                                <thead>
                                    <tr>
                                        <th style="width: 60px; text-align: center;">#</th>
                                        <th>Date</th>
                                        <th style="text-align: center;">Check-in</th>
                                        <th style="text-align: center;">Check-out</th>
                                        <th style="text-align: center;">Duration</th>
                                        <th style="text-align: center;">Status</th>
                                        <th class="p-cell-wrap">Task Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($history_result) > 0) : $i = 1; ?>
                                        <?php while ($row = mysqli_fetch_assoc($history_result)) :
                                            $st = $row['status'] ?: 'present';
                                            $badge_class = 'p-badge-secondary';
                                            if ($st == 'present') $badge_class = 'p-badge-success';
                                            elseif ($st == 'absent') $badge_class = 'p-badge-danger';
                                            elseif ($st == 'leave') $badge_class = 'p-badge-primary';
                                        ?>
                                            <tr>
                                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $i++; ?></td>
                                                <td style="font-weight: 600; color: #1e293b;"><?php echo date('d-m-Y', strtotime($row['attendance_date'])); ?></td>
                                                <td style="text-align: center; color: #64748b; font-size: 13px;"><?php echo $row['check_in_time'] ?: '--:--'; ?></td>
                                                <td style="text-align: center; color: #64748b; font-size: 13px;"><?php echo $row['check_out_time'] ?: '--:--'; ?></td>
                                                <td style="text-align: center; color: #3b82f6; font-weight: 700;">
                                                    <?php
                                                    if ($row['total_duration_secs'] > 0) {
                                                        $h = floor($row['total_duration_secs'] / 3600);
                                                        $m = floor(($row['total_duration_secs'] % 3600) / 60);
                                                        echo "{$h}h {$m}m";
                                                    } else {
                                                        echo "-";
                                                    }
                                                    ?>
                                                </td>
                                                <td style="text-align: center;">
                                                    <span class="p-badge <?php echo $badge_class; ?>" style="display: inline-flex; justify-content: center; min-width: 80px;">
                                                        <?php echo ucfirst($st); ?>
                                                    </span>
                                                </td>
                                                <td class="p-cell-wrap">
                                                    <div style="font-size: 13px; line-height: 1.6;">
                                                        <?php echo nl2br(htmlspecialchars($row['remarks'])); ?>

                                                        <?php
                                                        if (!empty($row['work_photos'])) {
                                                            $photos = json_decode($row['work_photos'], true);
                                                            if (!empty($photos)) {
                                                                echo '<div style="display: flex; gap: 6px; margin-top: 10px; flex-wrap: wrap;">';
                                                                foreach ($photos as $p) {
                                                                    echo '<div class="work-photo-item" onclick="window.open(\'' . htmlspecialchars($p) . '\')" title="Click to view full image">
                                                                        <img src="' . htmlspecialchars($p) . '">
                                                                        <div class="work-photo-overlay"><i class="fa fa-search-plus"></i></div>
                                                                      </div>';
                                                                }
                                                                echo '</div>';
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">
                                                <i class="fa fa-folder-open-o" style="font-size: 32px; display: block; margin-bottom: 10px;"></i>
                                                No recent worksheet records found.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Worksheet Modal -->
            <div class="modal fade" id="addWorksheetModal" tabindex="-1" role="dialog" aria-labelledby="addWorksheetModalLabel">
                <div class="modal-dialog" role="document">
                    <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                        <div class="modal-header" style="background: #1f2937; color: #fff; padding: 20px 25px;">
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8;"><span aria-hidden="true">&times;</span></button>
                            <h4 class="modal-title" id="addWorksheetModalLabel" style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; font-size: 15px;">
                                <i class="fa fa-plus-circle"></i> Add New Worksheet
                            </h4>
                        </div>
                        <div class="modal-body" style="padding: 25px;">
                            <form class="form-horizontal" method="POST" onsubmit="return validateWorksheetForm();">
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Date</label>
                                    <div class="col-md-8">
                                        <input type="date" name="date" class="p-input-premium" style="background: #f8fafc;" value="<?php echo date('Y-m-d'); ?>" readonly>
                                        <small style="color: #94a3b8; font-size: 11px; margin-top: 5px; display: block;">Worksheet can only be filled for today.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Check-in Time <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input type="time" name="start_time" id="ws_start_time" class="p-input-premium" style="background: #f8fafc;" value="<?php echo $prefill_in; ?>" readonly required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Check-out Time <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input type="time" name="end_time" id="ws_end_time" class="p-input-premium" style="background: #f8fafc;" value="<?php echo $prefill_out; ?>" readonly required>
                                        <small style="color: #3b82f6; font-size: 11px; margin-top: 5px; display: block;"><i class="fa fa-info-circle"></i> Times are automatically fetched from your real-time Check-In/Out.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Today’s Progress <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <textarea name="task_progress" class="p-input-premium" style="height: 75px; resize: none;" placeholder="What did you accomplish today?" required><?php echo htmlspecialchars($parsed_task['progress']); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Planning for Tomorrow</label>
                                    <div class="col-md-8">
                                        <textarea name="task_planning" class="p-input-premium" style="height: 60px; resize: none;" placeholder="What will you work on tomorrow?"><?php echo htmlspecialchars($parsed_task['planning']); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Issues</label>
                                    <div class="col-md-8">
                                        <textarea name="task_issues" class="p-input-premium" style="height: 60px; resize: none;" placeholder="Any blockers or challenges faced today?"><?php echo htmlspecialchars($parsed_task['issues']); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Need any Help?</label>
                                    <div class="col-md-8">
                                        <textarea name="task_help" class="p-input-premium" style="height: 60px; resize: none;" placeholder="Do you need any assistance?"><?php echo htmlspecialchars($parsed_task['help']); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 30px; margin-bottom: 0;">
                                    <div class="col-md-12" style="display: flex; gap: 10px; justify-content: flex-end;">
                                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn-premium-add">
                                            <i class="fa fa-paper-plane"></i> Submit Worksheet
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Closing premium-ui-enabled div -->
        <script>
            function validateWorksheetForm() {
                var date = document.querySelector('input[name="date"]');
                var start = document.querySelector('input[name="start_time"]');
                var end = document.querySelector('input[name="end_time"]');
                var task = document.querySelector('textarea[name="task_progress"]');
                var valid = true;
                [date, start, end, task].forEach(function(field) {
                    if (field && !field.value) {
                        field.parentElement.classList.add('has-error');
                        valid = false;
                    } else if (field) {
                        // Weekend blocking for Worksheet
                        if (field.name === 'date') {
                            var dateVal = new Date(field.value);
                            var day = dateVal.getDay();
                            if (day === 0 || day === 6) {
                                Swal.fire('Notification', "Selected date is a " + (day === 0 ? "Sunday" : "Saturday", 'info') + ", which is already a holiday. Please select a working day.");
                                field.value = "";
                                valid = false;
                            }
                        }
                        field.parentElement.classList.remove('has-error');
                    }
                });
                return valid;
            }

            // Direct listener for date input
            document.addEventListener('DOMContentLoaded', function() {
                var worksheetDate = document.querySelector('input[name="date"]');
                if (worksheetDate) {
                    worksheetDate.addEventListener('change', function() {
                        if (this.value) {
                            var dateVal = new Date(this.value);
                            var day = dateVal.getDay();
                            if (day === 0 || day === 6) {
                                Swal.fire('Notification', "Selected date is a " + (day === 0 ? "Sunday" : "Saturday", 'info') + ", which is already a holiday. Please select a working day.");
                                this.value = "";
                            }
                        }
                    });
                }

                // Auto-fill end time when opening modal ONLY if it's currently empty
                $('.btn-premium-add').on('click', function() {
                    var endTimeField = $('#ws_end_time');
                    if (!endTimeField.val()) {
                        var now = new Date();
                        var h = String(now.getHours()).padStart(2, '0');
                        var m = String(now.getMinutes()).padStart(2, '0');
                        endTimeField.val(h + ":" + m);
                    }
                });
            });
        </script>

        <?php if (!$is_partial) : ?>
        </div>
    </body>

    </html>
<?php endif; ?>