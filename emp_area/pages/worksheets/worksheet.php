<?php
// =============================================================
// emp_area/pages/worksheets/worksheet.php
// Employee worksheet – supports partial render (via index.php)
// Moved from: admin_area/pages/worksheets/worksheet.php
// Paths updated:
//   - db.php include → emp_area/includes/db.php
//   - auth redirect → emp_area/pages/auth/login.php
//   - CSS/assets   → admin_area (up 3 levels from here)
// =============================================================
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
$errorFields    = [];
$successMessage = "";

if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}

$emp_id   = $_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];

$emp_q = mysqli_query($con, "SELECT employee_image FROM emp_list WHERE id='$emp_id'");
$emp_data = mysqli_fetch_assoc($emp_q);
$emp_img_path = !empty($emp_data['employee_image']) ? '../admin_area/uploads/' . $emp_data['employee_image'] : '../admin_area/admin_images/default.png';

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
        $today_date      = date('Y-m-d');

        if ($attendance_date !== $today_date) {
            $errorFields[]  = 'date';
            $successMessage = "Error: You can only submit worksheet for the current date.";
        } else {
            $check_in_time  = mysqli_real_escape_string($con, $_POST['start_time']);
            $check_out_time = mysqli_real_escape_string($con, $_POST['end_time']);

            $parts = [];
            $parts[] = "Today’s Progress:\n" . $progress_input;
            if (!empty($planning_input)) $parts[] = "Planning for Tomorrow:\n" . $planning_input;
            if (!empty($issues_input))   $parts[] = "Issues:\n" . $issues_input;
            if (!empty($help_input))     $parts[] = "Need any Help?:\n" . $help_input;

            $remarks = mysqli_real_escape_string($con, implode("\n\n", $parts));

            $check = mysqli_query($con, "SELECT id, work_photos FROM attendance WHERE emp_id='$emp_id' AND attendance_date='$attendance_date'");
            if (mysqli_num_rows($check) > 0) {
                $row_att = mysqli_fetch_assoc($check);
                $existing_photos = !empty($row_att['work_photos']) ? json_decode($row_att['work_photos'], true) : [];
                if (!is_array($existing_photos)) $existing_photos = [];

                $uploaded_photos = $existing_photos;
                if (isset($_FILES['work_photos'])) {
                    $upload_dir = __DIR__ . '/../../../admin_area/uploads/';
                    foreach ($_FILES['work_photos']['name'] as $key => $name) {
                        if ($_FILES['work_photos']['error'][$key] == 0) {
                            $tmp_name = $_FILES['work_photos']['tmp_name'][$key];
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
                            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                                $uploaded_photos[] = 'uploads/' . $new_name;
                            }
                        }
                    }
                }
                $photos_json = empty($uploaded_photos) ? '' : json_encode($uploaded_photos);

                $status = 'present';
                if (!empty($check_in_time)) {
                    $tstamp = strtotime('1970-01-01 ' . $check_in_time);
                    $late_cutoff = strtotime('1970-01-01 10:15:00');
                    if ($tstamp !== false && $tstamp > $late_cutoff) {
                        $status = 'late';
                    }
                }

                $update = "UPDATE attendance SET check_in_time='$check_in_time', check_out_time='$check_out_time', status='$status', remarks='$remarks', work_photos='$photos_json' WHERE emp_id='$emp_id' AND attendance_date='$attendance_date'";
                if (mysqli_query($con, $update)) {
                    $successMessage = "Worksheet updated successfully!";
                }
            } else {
                $uploaded_photos = [];
                if (isset($_FILES['work_photos'])) {
                    $upload_dir = __DIR__ . '/../../../admin_area/uploads/';
                    foreach ($_FILES['work_photos']['name'] as $key => $name) {
                        if ($_FILES['work_photos']['error'][$key] == 0) {
                            $tmp_name = $_FILES['work_photos']['tmp_name'][$key];
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
                            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                                $uploaded_photos[] = 'uploads/' . $new_name;
                            }
                        }
                    }
                }
                $photos_json = empty($uploaded_photos) ? '' : json_encode($uploaded_photos);

                $status = 'present';
                if (!empty($check_in_time)) {
                    $tstamp = strtotime('1970-01-01 ' . $check_in_time);
                    $late_cutoff = strtotime('1970-01-01 10:15:00');
                    if ($tstamp !== false && $tstamp > $late_cutoff) {
                        $status = 'late';
                    }
                }

                $insert = "INSERT INTO attendance (emp_id, attendance_date, check_in_time, check_out_time, status, remarks, work_photos) VALUES ('$emp_id', '$attendance_date', '$check_in_time', '$check_out_time', '$status', '$remarks', '$photos_json')";
                if (mysqli_query($con, $insert)) {
                    $successMessage = "Worksheet submitted successfully!";
                }
            }
        }
    }
}

$history_query  = "SELECT a.*, 
                   (SELECT action_time FROM attendance_logs WHERE att_id = a.id AND action = 'check_out' ORDER BY action_time DESC LIMIT 1) as log_checkout 
                   FROM attendance a 
                   WHERE a.emp_id = '$emp_id' 
                   ORDER BY a.attendance_date DESC LIMIT 10";
$history_result = mysqli_query($con, $history_query);

$today_date  = date('Y-m-d');
$today_q     = "SELECT check_in_time, check_out_time, remarks, work_photos FROM attendance WHERE emp_id = '$emp_id' AND attendance_date = '$today_date'";
$today_res   = mysqli_query($con, $today_q);
$today_att   = mysqli_fetch_assoc($today_res);
$prefill_in  = ($today_att && $today_att['check_in_time'])  ? date('H:i', strtotime($today_att['check_in_time']))  : '';
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

// ── Auto-fill today's completed To-Do tasks into Today's Progress ──────────
// Build authoritative deduplicated list from DB (single source of truth)
$ws_today = date('Y-m-d');
$todos_today_q = mysqli_query($con, "SELECT t.task_name, p.project_name
    FROM project_team_todos t
    LEFT JOIN client_projects p ON t.project_id = p.id
    WHERE t.emp_id = '$emp_id'
      AND t.status = 1
      AND DATE(COALESCE(t.completed_at, t.due_date, t.created_at)) = '$ws_today'
      AND t.deleted_at IS NULL
    ORDER BY t.id ASC");
if ($todos_today_q && mysqli_num_rows($todos_today_q) > 0) {
    $db_entries   = [];
    while ($ct = mysqli_fetch_assoc($todos_today_q)) {
        $t_name = trim($ct['task_name']);
        $p_name = !empty($ct['project_name']) ? trim($ct['project_name']) : '';
        $entry  = $p_name ? "Completed Task [$p_name]: $t_name" : "Completed Task: $t_name";
        $db_entries[] = '- ' . $entry;
    }
    // Keep any custom (non-Completed Task) lines the employee wrote
    $custom_lines = [];
    foreach (explode("\n", $parsed_task['progress']) as $line) {
        $line = trim($line);
        if ($line !== '' && stripos($line, 'Completed Task') === false) {
            $custom_lines[] = $line;
        }
    }
    $all_lines = array_merge($db_entries, $custom_lines);
    $parsed_task['progress'] = implode("\n", $all_lines);
}

$prefill_photos = ($today_att && !empty($today_att['work_photos'])) ? json_decode($today_att['work_photos'], true) : [];
if (!is_array($prefill_photos)) $prefill_photos = [];
?>
<?php if (!$is_partial) : ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Cadlete - Worksheet</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../../../admin_area/css/bootstrap.min.css">
        <link href="../../../admin_area/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link href="../../../admin_area/css/style.css" rel="stylesheet">
    <?php endif; ?>
    <style>
        body {
            background: #f4f7f6;
            padding-top: 20px;
        }

        .page-header {
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .work-photo-item {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }

        .work-photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .work-photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: 0.3s ease;
            color: #fff;
        }

        .work-photo-item:hover .work-photo-overlay {
            opacity: 1;
        }

        .p-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .p-badge-success {
            background: rgba(16, 185, 129, 0.1) !important;
            color: #10b981 !important;
        }

        .p-badge-primary {
            background: rgba(79, 70, 229, 0.05) !important;
            color: #dd2127 !important;
        }

        .p-badge-danger {
            background: rgba(239, 68, 68, 0.1) !important;
            color: #ef4444 !important;
        }

        .p-badge-warning {
            background: rgba(245, 158, 11, 0.1) !important;
            color: #f59e0b !important;
        }

        .p-badge-secondary {
            background: #f1f5f9 !important;
            color: #64748b !important;
        }
    </style>
    <?php if (!$is_partial) : ?>
    </head>

    <body>
        <div class="container">
        <?php endif; ?>

        <div class="premium-ui-enabled">
            <div class="page-header-premium">
                <h1></h1>
                <div class="header-actions">
                    <button class="btn-premium-add" data-toggle="modal" data-target="#addWorksheetModal">
                        <i class="fa fa-plus"></i> Add Worksheet
                    </button>
                </div>
            </div>

            <!-- <div class="row">
            <div class="col-lg-12">
                <ol class="breadcrumb" style="background: #f1f5f9; border-radius: 12px; padding: 12px 20px; margin-bottom: 25px;">
                    <li><a href="../../index.php?dashboard" style="color: #64748b; text-decoration: none;"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li class="active" style="color: #1e293b; font-weight: 600;">Worksheet</li>
                </ol>
            </div>
        </div> -->

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
                        <div class="table-responsive">
                            <table class="table-premium">
                                <thead>
                                    <tr>
                                        <th style="width: 60px; text-align: center;">#</th>
                                        <th style="text-align: center;">Date</th>
                                        <th style="text-align: center;">Check-in</th>
                                        <th style="text-align: center;">Check-out</th>
                                        <th style="text-align: center;">Duration</th>
                                        <th style="text-align: center;">Status</th>
                                        <th style="text-align: center;">Work Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($history_result) > 0) : $i = 1; ?>
                                        <?php while ($row = mysqli_fetch_assoc($history_result)) :
                                            $st = $row['status'] ?: 'present';
                                            $check_in = $row['check_in_time'];
                                            if ($st === 'present' && !empty($check_in) && $check_in != '00:00:00') {
                                                $tstamp = strtotime('1970-01-01 ' . $check_in);
                                                $late_cutoff = strtotime('1970-01-01 10:15:00');
                                                if ($tstamp !== false && $tstamp > $late_cutoff) {
                                                    $st = 'late';
                                                }
                                            }
                                            $badge_class = 'p-badge-secondary';
                                            if ($st == 'present') $badge_class = 'p-badge-success';
                                            elseif ($st == 'absent') $badge_class = 'p-badge-danger';
                                            elseif ($st == 'leave')  $badge_class = 'p-badge-primary';
                                            elseif ($st == 'late')   $badge_class = 'p-badge-warning';
                                        ?>
                                            <tr>
                                                <td style="text-align: center; font-weight: 700; color: #64748b;"><?php echo $i++; ?></td>
                                                <td style="font-weight: 600; color: #1e293b; text-align: center;"><?php echo date('d-m-Y', strtotime($row['attendance_date'])); ?></td>
                                                <td style="text-align: center; color: #64748b; font-size: 13px;"><?php echo $row['check_in_time'] ?: '--:--'; ?></td>
                                                <td style="text-align: center; color: #64748b; font-size: 13px;">
                                                    <?php
                                                    $display_out = $row['check_out_time'];
                                                    if ((empty($display_out) || $display_out == '00:00:00') && !empty($row['log_checkout'])) {
                                                        $display_out = date('H:i:s', strtotime($row['log_checkout']));
                                                    }
                                                    echo $display_out ?: '--:--';
                                                    ?>
                                                </td>
                                                <td style="text-align: center; color: #dd2127; font-weight: 700;">
                                                    <?php
                                                    $active_secs = $row['total_duration_secs'];
                                                    if ($row['attendance_date'] == date('Y-m-d') && $row['is_working']) {
                                                        $startTime = (!empty($row['total_duration_secs']) && $row['total_duration_secs'] > 0) ? $row['last_resume_time'] : ($row['attendance_date'] . ' ' . $row['check_in_time']);
                                                        if (!empty($startTime)) {
                                                            $active_secs += time() - strtotime($startTime);
                                                        }
                                                    }

                                                    if ($active_secs > 0) {
                                                        $h = floor($active_secs / 3600);
                                                        $m = floor(($active_secs % 3600) / 60);
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
                                                <td style="text-align: center;">
                                                    <?php
                                                    $photos = [];
                                                    if (!empty($row['work_photos'])) {
                                                        $decoded = json_decode($row['work_photos'], true);
                                                        if (is_array($decoded)) {
                                                            $photos = $decoded;
                                                        }
                                                    }

                                                    if (!empty($photos) || !empty(trim($row['remarks'] ?? ''))) {
                                                        $json_photos = htmlspecialchars(json_encode($photos), ENT_QUOTES, 'UTF-8');
                                                        $emp_name_js = htmlspecialchars($emp_name, ENT_QUOTES, 'UTF-8');
                                                        $date_js = htmlspecialchars(date('d-m-Y', strtotime($row['attendance_date'])), ENT_QUOTES, 'UTF-8');
                                                        $emp_img_js = htmlspecialchars($emp_img_path, ENT_QUOTES, 'UTF-8');
                                                        $remark_js = htmlspecialchars(json_encode(nl2br(htmlspecialchars($row['remarks'] ?? ''))), ENT_QUOTES, 'UTF-8');
                                                        echo '<button type="button" class="btn btn-sm" style="border-radius: 6px; padding: 4px 12px; font-weight: 600; background: #fff; color: #1e293b; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="openRowGallery(\'' . $json_photos . '\', \'' . $emp_name_js . '\', \'' . $date_js . '\', \'' . $emp_img_js . '\', ' . $remark_js . '); event.stopPropagation();"><i class="fa fa-eye" style="color: #dd2127; margin-right: 4px;"></i> View Details</button>';
                                                    } else {
                                                        echo '<span style="color: #cbd5e1;">-</span>';
                                                    }
                                                    ?>
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
                        <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background:#ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                            <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                                <div style="width: 36px; height: 36px; background: #dd2127; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fa fa-pencil-square-o" style="color: #fff; font-size: 14px;"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Add New Worksheet</h5>
                                </div>
                            </div>
                            <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                        <div class="modal-body" style="padding: 25px;">
                            <form class="form-horizontal" method="POST" enctype="multipart/form-data" onsubmit="return validateWorksheetForm();">
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
                                        <small style="color: #dd2127; font-size: 11px; margin-top: 5px; display: block;"><i class="fa fa-info-circle"></i> Times are automatically fetched from your real-time Check-In/Out.</small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Today’s Progress <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <textarea name="task_progress" class="p-input-premium" style="height: 85px; resize: none;" placeholder="What did you accomplish today?"><?php echo htmlspecialchars($parsed_task['progress']); ?></textarea>
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
                                <div class="form-group">
                                    <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Work Photos <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                            <?php
                                            for ($id = 1; $id <= 4; $id++):
                                                $photo_src = '';
                                                $has_prefill = isset($prefill_photos[$id - 1]) && !empty($prefill_photos[$id - 1]);
                                                if ($has_prefill) {
                                                    $p_url = $prefill_photos[$id - 1];
                                                    $photo_src = (strpos($p_url, 'http') === 0 || strpos($p_url, '/') === 0) ? $p_url : '../admin_area/' . $p_url;
                                                }
                                            ?>
                                                <div id="box_<?php echo $id; ?>" onclick="document.getElementById('work_photo_<?php echo $id; ?>').click()"
                                                    style="width:70px;height:70px;border:2px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;background:#f8fafc;">
                                                    <i class="fa fa-plus" style="color:#94a3b8;font-size:18px; <?php echo $has_prefill ? 'display:none;' : ''; ?>"></i>
                                                    <input type="file" name="work_photos[]" id="work_photo_<?php echo $id; ?>" style="display:none;" accept="image/*" onchange="previewWorkPhoto(this,<?php echo $id; ?>)">
                                                    <img id="preview_<?php echo $id; ?>" src="<?php echo htmlspecialchars($photo_src); ?>" style="<?php echo $has_prefill ? 'display:block;' : 'display:none;'; ?>width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
                                                </div>
                                            <?php endfor; ?>
                                        </div>
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
        </div><!-- Closing premium-ui-enabled div -->

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
                        if (field.name === 'date') {
                            var dateVal = new Date(field.value);
                            var day = dateVal.getDay();
                            if (day === 0 || day === 6) {
                                Swal.fire('Notification', "Selected date is a " + (day === 0 ? "Sunday" : "Saturday") + ", which is a holiday.");
                                field.value = "";
                                valid = false;
                            }
                        }
                        field.parentElement.classList.remove('has-error');
                    }
                });

                var hasPhoto = false;
                for (var i = 1; i <= 4; i++) {
                    var fi = document.getElementById('work_photo_' + i);
                    var prev = document.getElementById('preview_' + i);
                    if ((fi && fi.files && fi.files.length > 0) || (prev && prev.src && prev.style.display !== 'none' && prev.src !== '' && !prev.src.endsWith('/'))) {
                        hasPhoto = true;
                    }
                }
                if (!hasPhoto) {
                    Swal.fire('Notification', 'Please upload at least 1 work photo.', 'info');
                    return false;
                }

                return valid;
            }

            function previewWorkPhoto(input, id) {
                if (input.files && input.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('preview_' + id).src = e.target.result;
                        document.getElementById('preview_' + id).style.display = 'block';
                        document.getElementById('box_' + id).querySelector('.fa-plus').style.display = 'none';
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
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

        <script>
            window.openRowGallery = function(photosJson, empName, date, empImg, remarkHtml) {
                var photos = JSON.parse(photosJson);
                var html = '';
                if (remarkHtml && remarkHtml !== '-') {
                    var formattedRemark = remarkHtml
                        .replace(/(Today[’']s Progress:)/gi, '<strong style="color:#0f172a; display:block; margin-top:6px; margin-bottom:2px; font-weight:700;"><i class="fa fa-tasks" style="color:#dd2127; margin-right:5px;"></i>$1</strong>')
                        .replace(/(Planning for Tomorrow:)/gi, '<strong style="color:#0f172a; display:block; margin-top:10px; margin-bottom:2px; font-weight:700;"><i class="fa fa-calendar-check-o" style="color:#2563eb; margin-right:5px;"></i>$1</strong>')
                        .replace(/(Issues:)/gi, '<strong style="color:#0f172a; display:block; margin-top:10px; margin-bottom:2px; font-weight:700;"><i class="fa fa-exclamation-triangle" style="color:#eab308; margin-right:5px;"></i>$1</strong>')
                        .replace(/(Need any Help\s*\?:?)/gi, '<strong style="color:#0f172a; display:block; margin-top:10px; margin-bottom:2px; font-weight:700;"><i class="fa fa-question-circle" style="color:#8b5cf6; margin-right:5px;"></i>$1</strong>');
                    html += '<div style="background: #fff; padding: 15px 20px; border-radius: 12px; text-align: left; margin-bottom: 20px; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.02); font-size: 14px; color: #475569; width: 100%;"><h5 style="margin-top:0; font-size:13px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px;">Remark</h5>' + formattedRemark + '</div>';
                }
                html += '<div class="work-gallery-grid" style="width: 100%;">';
                photos.forEach(function(url) {
                    var fullUrl = '../admin_area/' + url;
                    html += '<div class="work-gallery-item" onclick="window.open(\'' + fullUrl + '\')">';
                    html += '<img src="' + fullUrl + '" loading="lazy">';
                    html += '<div class="item-overlay">';
                    html += '<div class="item-info">';
                    html += '<div class="item-emp"><img src="' + empImg + '" class="emp-mini-img"><span>' + empName + '</span></div>';
                    html += '<div class="item-date">' + date + '</div>';
                    html += '</div>';
                    html += '<i class="fa fa-search-plus"></i>';
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                $('#previewModalImageContainer').html(html);
                $('#imagePreviewModal').modal('show');
            }
        </script>

        <!-- Image Preview Modal -->
        <div id="imagePreviewModal" class="modal fade" role="dialog" style="z-index: 999999;">
            <div class="modal-dialog modal-lg" style="margin-top: 40px; max-width: 900px;">
                <div class="modal-content premium-modal-content-v2" style="border: none; border-radius: 32px; box-shadow: 0 40px 100px -20px rgba(111, 50, 50, 0.4); overflow: hidden; background: #fff;">
                    <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                        <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 50px; height: 50px; border-radius: 16px; background: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                                <i class="fa fa-picture-o" style="font-size: 24px; color: #f43f5e;"></i>
                            </div>
                            <div>
                                <h4 class="modal-title" style="font-weight: 800; font-size: 20px; margin: 0; letter-spacing: -0.5px;">Work Details</h4>
                                <div style="font-size: 13px; color: #64748b; margin-top: 4px; font-weight: 500;">View remarks and work photos</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body" style="padding: 30px; text-align: center; background: #f8fafc; min-height: 400px; max-height: 75vh; overflow-y: auto;">
                        <div id="previewModalImageContainer" style="display: flex; flex-direction: column; gap: 20px; align-items: center;"></div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .work-gallery-grid {
                display: grid;
                grid-template-columns: repeat(8, minmax(0, 1fr));
                gap: 10px;
                padding: 15px;
            }

            @media (max-width: 1100px) {
                .work-gallery-grid {
                    grid-template-columns: repeat(6, minmax(0, 1fr));
                }
            }

            @media (max-width: 768px) {
                .work-gallery-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            @media (max-width: 480px) {
                .work-gallery-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            .work-gallery-item {
                position: relative;
                aspect-ratio: 1;
                border-radius: 12px;
                overflow: hidden;
                cursor: pointer;
                border: 1.5px solid #e2e8f0;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .work-gallery-item>img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.4s;
            }

            .work-gallery-item:hover>img {
                transform: scale(1.05);
            }

            .item-overlay {
                position: absolute;
                inset: 0;
                background: linear-gradient(to top, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.1));
                opacity: 0;
                transition: opacity 0.3s;
                display: flex;
                flex-direction: column;
                justify-content: flex-end;
                padding: 15px;
            }

            .work-gallery-item:hover .item-overlay {
                opacity: 1;
            }

            .item-info {
                transform: translateY(10px);
                transition: transform 0.3s;
                text-align: left;
            }

            .work-gallery-item:hover .item-info {
                transform: translateY(0);
            }

            .item-emp {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 800;
                font-size: 13px;
                color: #fff;
            }

            .emp-mini-img {
                width: 22px !important;
                height: 22px !important;
                border-radius: 6px !important;
                border: 1.5px solid rgba(255, 255, 255, 0.4) !important;
            }

            .item-date {
                font-size: 10px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.6);
                margin-top: 3px;
            }

            .item-overlay i {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.5);
                color: #fff;
                font-size: 24px;
                background: #dd2127;
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                opacity: 0;
                transition: 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            }

            .work-gallery-item:hover .item-overlay i {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        </style>

        <?php if (!$is_partial) : ?>
        </div>
    </body>

    </html>
<?php endif; ?>