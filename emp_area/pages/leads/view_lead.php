<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit;
}

$emp_id = (int)$_SESSION['emp_id'];

$currency_symbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'د.إ'
];

$view_id = null;
$row_lead = [];
$run_followups = null;

if (isset($_GET['view_lead'])) {
    $view_id = (int)$_GET['view_lead'];
    // Verify lead exists, is not deleted, and is assigned to this employee
    $get_lead = "SELECT * FROM leads WHERE id = '$view_id' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0";
    $run_lead = mysqli_query($con, $get_lead);
    $row_lead = mysqli_fetch_assoc($run_lead);

    if (!$row_lead) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Access Denied',
                    text: 'Lead not found or you are not assigned to this lead.',
                    icon: 'error',
                    confirmButtonColor: '#dd2127'
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
        exit;
    }

    $client_name = $row_lead['client_name'];

    // Handle Employee Question Answer Submission
    if (isset($_POST['submit_lead_question_answer'])) {
        $q_num = (int)$_POST['question_num'];
        $q_text = mysqli_real_escape_string($con, $_POST['question_text'] ?? '');
        $ans = trim($_POST['answer'] ?? '');
        $emp_name = $_SESSION['emp_name'] ?? 'Employee';

        if (!empty($ans) && $q_num > 0) {
            $esc_ans = mysqli_real_escape_string($con, $ans);
            $esc_name = mysqli_real_escape_string($con, $emp_name);

            $ins_q = "INSERT INTO lead_question_answers (lead_id, emp_id, emp_name, question_num, question_text, answer) 
                      VALUES ('$view_id', '$emp_id', '$esc_name', '$q_num', '$q_text', '$esc_ans')";
            if (mysqli_query($con, $ins_q)) {
                $f_remark = mysqli_real_escape_string($con, "[Q$q_num: $q_text]\n$ans (by $emp_name)");
                $today = date('Y-m-d');
                mysqli_query($con, "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                                    VALUES ('$view_id', '$today', 'Follow-up Q&A', 'Question Response', '$f_remark')");
                echo "<script>window.location.href='index.php?view_lead=$view_id&tab=questions';</script>";
                exit;
            }
        }
    }

    // Handle Delete Question Answer (by author)
    if (isset($_GET['delete_qa_id'])) {
        $del_id = (int)$_GET['delete_qa_id'];
        if ($del_id > 0) {
            mysqli_query($con, "DELETE FROM lead_question_answers WHERE id = '$del_id' AND lead_id = '$view_id' AND emp_id = '$emp_id'");
            echo "<script>window.location.href='index.php?view_lead=$view_id&tab=questions';</script>";
            exit;
        }
    }

    // Fetch Question Answers
    $qa_list = [1 => [], 2 => [], 3 => [], 4 => []];
    $get_qa = mysqli_query($con, "SELECT * FROM lead_question_answers WHERE lead_id = '$view_id' ORDER BY id ASC");
    $answered_count = 0;
    if ($get_qa) {
        while ($qa_row = mysqli_fetch_assoc($get_qa)) {
            $qnum = (int)$qa_row['question_num'];
            if (!isset($qa_list[$qnum])) {
                $qa_list[$qnum] = [];
            }
            $qa_list[$qnum][] = $qa_row;
        }
    }
    for ($i = 1; $i <= 4; $i++) {
        if (!empty($qa_list[$i])) $answered_count++;
    }

    // Handle quick follow-up log by employee
    if (isset($_POST['add_emp_followup'])) {
        $method = mysqli_real_escape_string($con, $_POST['followup_method']);
        $type = mysqli_real_escape_string($con, $_POST['followup_type']);
        $f_date = mysqli_real_escape_string($con, $_POST['followup_date']);
        $f_remark = mysqli_real_escape_string($con, $_POST['remark']);
        $next_date = !empty($_POST['next_followup_date']) ? mysqli_real_escape_string($con, $_POST['next_followup_date']) : null;

        $emp_name = $_SESSION['emp_name'] ?? 'Employee';
        $full_remark = $f_remark . " (Logged by $emp_name)";

        $insert_f = "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                     VALUES ('$view_id', '$f_date', '$method', '$type', '$full_remark')";
        if (mysqli_query($con, $insert_f)) {
            if (!empty($next_date)) {
                mysqli_query($con, "UPDATE leads SET followup_date = '$next_date' WHERE id = '$view_id'");
            }
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Follow-up saved successfully.',
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.href = 'index.php?view_lead=$view_id&tab=timeline';
                    });
                });
            </script>";
            exit;
        }
    }

    // Get Follow-up History
    $get_followups = "SELECT * FROM lead_followups WHERE lead_id = '$view_id' ORDER BY followup_date DESC, id DESC";
    $run_followups = mysqli_query($con, $get_followups);
}

$active_tab = isset($_GET['tab']) && $_GET['tab'] === 'questions' ? 'questions' : 'timeline';

$default_questions_data = [
    1 => [
        'title' => 'Can we do it or not?',
        'placeholder' => 'Write answer (e.g. Yes we can do it, scope details)...',
        'chips' => ['Yes, we can do it', 'No, out of scope', 'Need more details from client']
    ],
    2 => [
        'title' => 'Complexity of the project on the scale from 1 to 5 (1 is easy, 5 is complex)',
        'placeholder' => 'Rate complexity 1 to 5 with explanation...',
        'chips' => ['1 - Very Easy', '2 - Easy', '3 - Moderate', '4 - Complex', '5 - Very Complex']
    ],
    3 => [
        'title' => 'Time required for the project',
        'placeholder' => 'Estimated time required (e.g. 2-3 days, 1 week)...',
        'chips' => ['1-2 Days', '3-5 Days', '1-2 Weeks', '3-4 Weeks', '1+ Month']
    ],
    4 => [
        'title' => 'Reply mail to client about any additional information/suggestions that I can directly forwarded to him',
        'placeholder' => 'Write draft reply email or additional suggestions for the client...',
        'chips' => ['All details clear, ready to proceed', 'Need client confirmation on requirements', 'Drafted email reply provided']
    ]
];
?>

<div class="page-wrapper premium-ui-enabled">
    <!-- Top Action Header: Back button ONLY (no edit, no delete) -->
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
        </div>
        <div class="header-actions-premium">
            <a href="index.php?leads" class="btn-premium-add" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                <i class="fa fa-arrow-left"></i> Back to Leads
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Lead Information Card -->
        <div class="col-md-5">
            <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                <div class="card-hdr" style="background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-info-circle" style="color: #dd2127; font-size: 16px;"></i>
                    <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Lead Information</h3>
                </div>
                <div style="padding: 24px;">
                    <!-- Client Header Box -->
                    <div style="background: #f8fafc; padding: 20px; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #1e293b; font-weight: 800; font-size: 20px;">
                            <?php echo htmlspecialchars($row_lead['client_name']); ?>
                        </h2>
                        <p style="color: #64748b; margin-top: 6px; font-weight: 500; font-size: 13px;">
                            <i class="fa fa-building-o"></i> <?php echo !empty($row_lead['company_name']) ? htmlspecialchars($row_lead['company_name']) : 'Individual Client'; ?>
                        </p>
                        <!-- Quick Contact Buttons -->
                        <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($row_lead['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($row_lead['phone']); ?>" class="btn btn-default btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fa fa-phone"></i> Call
                                </a>
                                <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $row_lead['phone']); ?>" target="_blank" class="btn btn-success btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #16a34a; border-color: #16a34a;">
                                    <i class="fa fa-whatsapp"></i> WhatsApp
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($row_lead['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($row_lead['email']); ?>" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #4f46e5; border-color: #4f46e5;">
                                    <i class="fa fa-envelope"></i> Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Details Table -->
                    <table style="width: 100%; font-size: 13px;">
                        <tr>
                            <th style="color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Project</th>
                            <td style="font-weight: 700; color: #1e293b; padding: 10px 0;"><?php echo !empty($row_lead['project_name']) ? htmlspecialchars($row_lead['project_name']) : 'N/A'; ?></td>
                        </tr>
                        <?php if (!empty($row_lead['lead_source'])): ?>
                            <tr>
                                <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Lead Source</th>
                                <td style="border-top: 1px solid #f1f5f9; font-weight: 600; color: #334155; padding: 10px 0;">
                                    <span style="background: #f1f5f9; padding: 3px 10px; border-radius: 6px; font-size: 12px;"><?php echo htmlspecialchars($row_lead['lead_source']); ?></span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Assigned To</th>
                            <td style="border-top: 1px solid #f1f5f9; padding: 10px 0;">
                                <span style="background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; border-radius: 12px; padding: 3px 10px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa fa-user"></i> <?php echo !empty($emp_name) ? htmlspecialchars($emp_name) : 'Assigned Team'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Budget</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 700; color: #1e293b; padding: 10px 0;">
                                <?php echo !empty($row_lead['budget']) ? (isset($currency_symbols[$row_lead['currency']]) ? $currency_symbols[$row_lead['currency']] : '₹') . ' ' . htmlspecialchars($row_lead['budget']) : 'N/A'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Status</th>
                            <td style="border-top: 1px solid #f1f5f9; padding: 10px 0;">
                                <?php
                                $st = strtolower($row_lead['status'] ?? 'active');
                                $st_bg = '#ffeaeb';
                                $st_color = '#dd2127';
                                $st_border = '#fecdd3';
                                $st_label = 'ACTIVE';

                                if ($st === 'active') {
                                    $st_bg = '#ffeaeb';
                                    $st_color = '#dd2127';
                                    $st_border = '#fecdd3';
                                    $st_label = 'ACTIVE';
                                } elseif ($st === 'future') {
                                    $st_bg = '#eff6ff';
                                    $st_color = '#2563eb';
                                    $st_border = '#bfdbfe';
                                    $st_label = 'FUTURE';
                                } elseif ($st === 'expired') {
                                    $st_bg = '#fef2f2';
                                    $st_color = '#dc2626';
                                    $st_border = '#fecaca';
                                    $st_label = 'EXPIRED';
                                }
                                ?>
                                <span style="display: inline-block; padding: 4px 14px; border-radius: 14px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; background: <?php echo $st_bg; ?>; color: <?php echo $st_color; ?>; border: 1px solid <?php echo $st_border; ?>;">
                                    <?php echo $st_label; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Next Follow-up</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 700; color: #4f46e5; padding: 10px 0;">
                                <?php echo !empty($row_lead['followup_date']) ? date('d-m-Y', strtotime($row_lead['followup_date'])) : 'Not Scheduled'; ?>
                            </td>
                        </tr>
                    </table>

                    <?php
                    $raw_emp_desc = $row_lead['description'] ?? '';
                    $is_emp_default = (stripos($raw_emp_desc, 'Can we do it or not') !== false && stripos($raw_emp_desc, 'Complexity of the project') !== false);
                    $clean_emp_desc = (!$is_emp_default && !empty(trim($raw_emp_desc))) ? trim($raw_emp_desc) : '';
                    if (!empty($clean_emp_desc)):
                    ?>
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                            <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Description</label>
                            <p style="color: #475569; line-height: 1.6; margin-top: 5px; font-size: 13px;"><?php echo nl2br(htmlspecialchars($clean_emp_desc)); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($row_lead['remark'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                            <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Remark</label>
                            <p style="color: #475569; line-height: 1.6; margin-top: 5px; font-size: 13px;"><?php echo nl2br(htmlspecialchars($row_lead['remark'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Follow-up Timeline & Questions Card -->
        <div class="col-md-7">
            <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                <div class="card-hdr" style="background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <!-- Card Tabs -->
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-sm emp-tab-btn <?php echo $active_tab === 'timeline' ? 'active' : ''; ?>" onclick="switchEmpTab('timeline')" id="btn-emp-timeline">
                            <i class="fa fa-history"></i> Activity & Notes
                            <span class="badge"><?php echo $run_followups ? mysqli_num_rows($run_followups) : 0; ?></span>
                        </button>
                        <button type="button" class="btn btn-sm emp-tab-btn <?php echo $active_tab === 'questions' ? 'active' : ''; ?>" onclick="switchEmpTab('questions')" id="btn-emp-questions">
                            <i class="fa fa-list-ol"></i> Required Questions
                            <span class="badge"><?php echo $answered_count; ?>/4</span>
                        </button>
                    </div>

                    <button type="button" class="btn btn-sm" style="background: #dd2127; color: #fff; border-radius: 8px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;" data-toggle="modal" data-target="#empFollowupModal">
                        <i class="fa fa-plus"></i> Add Note
                    </button>
                </div>

                <div style="padding: 22px;">
                    <!-- TAB 1: Follow-up Timeline -->
                    <div id="emp-tab-pane-timeline" style="display: <?php echo $active_tab === 'timeline' ? 'block' : 'none'; ?>; max-height: 560px; overflow-y: auto; overflow-x: hidden; padding-right: 6px;">
                        <?php if ($run_followups && mysqli_num_rows($run_followups) > 0): ?>
                            <div class="emp-timeline">
                                <?php while ($f_row = mysqli_fetch_assoc($run_followups)):
                                    $method = strtolower($f_row['followup_method'] ?? 'other');
                                    $icon = 'fa-comment';
                                    if ($method === 'phone') $icon = 'fa-phone';
                                    elseif ($method === 'whatsapp') $icon = 'fa-whatsapp';
                                    elseif ($method === 'email') $icon = 'fa-envelope';
                                    elseif ($method === 'meeting') $icon = 'fa-users';
                                    elseif ($method === 'follow-up q&a' || $method === 'q&a') $icon = 'fa-list-ol';
                                ?>
                                    <div class="emp-timeline-item">
                                        <div class="emp-timeline-icon">
                                            <i class="fa <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="emp-timeline-body">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                                <h4 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 14px;">
                                                    <?php echo htmlspecialchars($f_row['followup_method']); ?> - <?php echo htmlspecialchars($f_row['followup_type']); ?>
                                                </h4>
                                                <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">
                                                    <?php echo date('d-m-Y', strtotime($f_row['followup_date'])); ?>
                                                </span>
                                            </div>
                                            <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.5;">
                                                <?php echo nl2br(htmlspecialchars($f_row['remark'])); ?>
                                            </p>
                                            <div style="margin-top: 10px; display: flex; align-items: center; gap: 5px; font-size: 11px; color: #94a3b8;">
                                                <i class="fa fa-clock-o"></i> Logged at <?php echo date('h:i A, d M Y', strtotime($f_row['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 50px 20px; background: #f8fafc; border-radius: 14px; border: 2px dashed #e2e8f0;">
                                <div style="width: 60px; height: 60px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); color: #cbd5e1; font-size: 26px;">
                                    <i class="fa fa-history"></i>
                                </div>
                                <h4 style="color: #64748b; font-weight: 700; font-size: 15px; margin-bottom: 5px;">No Activity History Yet</h4>
                                <p style="color: #94a3b8; font-size: 12.5px; max-width: 280px; margin: 0 auto;">Click "+ Add Note" above to record any client discussion or interaction.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- TAB 2: 4 Individual Question Cards -->
                    <div id="emp-tab-pane-questions" style="display: <?php echo $active_tab === 'questions' ? 'block' : 'none'; ?>; max-height: 560px; overflow-y: auto; overflow-x: hidden; padding-right: 6px;">
                        <div style="display: flex; flex-direction: column; gap: 16px;">
                            <?php foreach ($default_questions_data as $q_num => $q_info): ?>
                                <?php
                                $answers = $qa_list[$q_num] ?? [];
                                ?>
                                <div class="view-q-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                                    <!-- Header -->
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="background: #ffeaeb; color: #dd2127; border: 1px solid #fecdd3; font-weight: 800; font-size: 11.5px; padding: 3px 8px; border-radius: 6px; flex-shrink: 0;">Q<?php echo $q_num; ?></span>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 13.5px; line-height: 1.4;">
                                                <?php echo htmlspecialchars($q_info['title']); ?>
                                            </div>
                                        </div>
                                        <span id="emp-q-count-tag-<?php echo $q_num; ?>" class="badge" style="background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;">
                                            <?php echo count($answers); ?> <?php echo count($answers) === 1 ? 'answer' : 'answers'; ?>
                                        </span>
                                    </div>

                                    <!-- Answers Scroll Box (Shown only if answers exist) -->
                                    <div id="emp-q-answers-list-<?php echo $q_num; ?>" class="q-answers-scroll-box" style="max-height: 200px; overflow-y: auto; overflow-x: hidden; padding: 2px 0; margin-top: 10px; margin-bottom: 6px; display: <?php echo !empty($answers) ? 'flex' : 'none'; ?>; flex-direction: column; gap: 8px; border: none; background: transparent;">
                                        <?php if (!empty($answers)): ?>
                                            <?php foreach ($answers as $ans):
                                                $a_name = !empty($ans['emp_name']) ? htmlspecialchars($ans['emp_name']) : 'Employee';
                                                $a_init = strtoupper(substr($a_name, 0, 1));
                                                $a_date = !empty($ans['created_at']) ? date('d M Y, h:i A', strtotime($ans['created_at'])) : '';
                                                $can_del = ($emp_id === (int)$ans['emp_id']);
                                            ?>
                                                <div class="qa-ans-card-item" id="emp-ans-item-<?php echo $ans['id']; ?>" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                        <div style="display: flex; align-items: center; gap: 7px;">
                                                            <div style="width: 22px; height: 22px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; flex-shrink: 0;">
                                                                <?php echo $a_init; ?>
                                                            </div>
                                                            <strong style="color: #1e293b; font-size: 12.5px;"><?php echo $a_name; ?></strong>
                                                        </div>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <span style="color: #94a3b8; font-size: 11px;"><?php echo $a_date; ?></span>
                                                            <?php if ($can_del): ?>
                                                                <button type="button" onclick="deleteEmpQA(<?php echo $ans['id']; ?>, <?php echo $q_num; ?>)" title="Delete Answer" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 5px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#dc2626'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#dc2626';">
                                                                    <i class="fa fa-trash-o"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div style="color: #334155; font-size: 12.5px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; padding-left: 29px;"><?php echo htmlspecialchars($ans['answer']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Answer Input Form -->
                                    <div style="display: flex; gap: 8px; align-items: flex-end; margin-top: 12px;">
                                        <textarea id="emp-q-input-<?php echo $q_num; ?>" class="view-q-textarea" placeholder="<?php echo htmlspecialchars($q_info['placeholder']); ?>" rows="1"></textarea>
                                        <button type="button" id="emp-q-save-btn-<?php echo $q_num; ?>" onclick="saveEmpQA(<?php echo $q_num; ?>, '<?php echo addslashes($q_info['title']); ?>')" style="background: #dd2127; color: #fff; border: none; border-radius: 8px; padding: 0 18px; font-size: 13px; font-weight: 700; cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; height: 38px; transition: background 0.15s ease;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dd2127'">
                                            <i class="fa fa-paper-plane"></i> Save
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Follow-up Note for Employee -->
<div id="empFollowupModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <form method="POST">
                <div class="modal-header" style="background: #ffeaeb; color: #1e293b; padding: 22px 25px; border-bottom: 1px solid #f1f5f9; position: relative;">
                    <button type="button" data-dismiss="modal" style="position: absolute; right: 20px; top: 20px; background: #dd2127; color: #fff; border: none; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        <i class="fa fa-times"></i>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: #dd2127; color: white; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-history" style="font-size: 16px;"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 18px; margin: 0; color: #0f172a;">Add Follow-up Note</h4>
                            <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">Record interaction for <strong><?php echo htmlspecialchars($client_name); ?></strong></p>
                        </div>
                    </div>
                </div>
                <div class="modal-body" style="padding: 25px; background: #fff;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Interaction Date</label>
                                <input type="date" name="followup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Method</label>
                                <select name="followup_method" class="form-control" style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                                    <option value="Phone">Phone</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Email">Email</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Follow-up Type</label>
                        <select name="followup_type" class="form-control" style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                            <option value="General Discussion">General Discussion</option>
                            <option value="Client Requirement Update">Client Requirement Update</option>
                            <option value="Quotation Discussion">Quotation Discussion</option>
                            <option value="Follow-up Call">Follow-up Call</option>
                            <option value="Urgent Update">Urgent Update</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Remark / Discussion</label>
                        <textarea name="remark" class="form-control" rows="3" style="border-radius: 8px; border-color: #cbd5e1; padding: 12px;" placeholder="What did you discuss with the client..." required></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 15px; padding: 14px; background: #f0fdf4; border-radius: 10px; border: 1px solid #dcfce7;">
                        <label style="font-weight: 700; color: #166534; margin-bottom: 6px; display: block; font-size: 13px;">Next Follow-up Date (Optional)</label>
                        <input type="date" name="next_followup_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" style="height: 40px; border-radius: 8px; border-color: #bbf7d0;">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Cancel</button>
                    <button type="submit" name="add_emp_followup" class="btn" style="background: #dd2127; color: #fff; border-radius: 8px; font-weight: 700; padding: 7px 20px;">
                        <i class="fa fa-save"></i> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .emp-timeline {
        position: relative;
        padding-left: 52px;
    }

    .emp-timeline::before {
        content: '';
        position: absolute;
        left: 17px;
        top: 10px;
        bottom: 0;
        width: 2px;
        background: #f1f5f9;
    }

    .emp-timeline-item {
        position: relative;
        margin-bottom: 25px;
    }

    .emp-timeline-icon {
        position: absolute;
        left: -52px;
        width: 36px;
        height: 36px;
        background: #fff;
        border: 2px solid #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dd2127;
        z-index: 2;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
    }

    .emp-timeline-body {
        background: #fff;
        padding: 18px 20px;
        border-radius: 14px;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .emp-timeline-item:hover .emp-timeline-body {
        border-color: #e2e8f0;
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.04);
        transform: translateX(4px);
    }

    /* Tab Buttons Styling */
    .emp-tab-btn {
        background: #f1f5f9 !important;
        color: #64748b !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        font-weight: 700 !important;
        font-size: 12.5px !important;
        padding: 6px 14px !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        transition: all 0.15s ease !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .emp-tab-btn:hover {
        background: #e2e8f0 !important;
        color: #1e293b !important;
        border-color: #cbd5e1 !important;
    }

    .emp-tab-btn.active {
        background: #dd2127 !important;
        color: #ffffff !important;
        border-color: #dd2127 !important;
        box-shadow: 0 2px 6px rgba(221, 33, 39, 0.25) !important;
    }

    .emp-tab-btn .badge {
        background: rgba(0, 0, 0, 0.08) !important;
        color: inherit !important;
        font-size: 10.5px !important;
        padding: 2px 7px !important;
        border-radius: 10px !important;
        font-weight: 800 !important;
    }

    .emp-tab-btn.active .badge {
        background: rgba(255, 255, 255, 0.25) !important;
        color: #ffffff !important;
    }

    /* Remove default black focus ring across all controls */
    button:focus,
    .btn:focus,
    .btn:active:focus,
    .btn.active:focus,
    input:focus,
    textarea:focus,
    select:focus {
        outline: none !important;
        box-shadow: none !important;
    }

    /* Chips */
    .view-chip-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .view-chip-btn:hover {
        background: #fee2e2;
        border-color: #fca5a5;
        color: #b91c1c;
    }

    /* Question Textarea */
    .view-q-textarea {
        flex: 1;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 13px;
        outline: none;
        resize: vertical;
        min-height: 38px;
        font-family: inherit;
        box-sizing: border-box;
        background: #ffffff;
        color: #1e293b;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .view-q-textarea:focus {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px rgba(221, 33, 39, 0.08) !important;
    }

    .view-q-textarea::placeholder {
        color: #94a3b8;
        font-size: 12.5px;
    }

    /* Custom Scrollbars */
    .q-answers-scroll-box,
    #emp-tab-pane-questions,
    #emp-tab-pane-timeline {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }

    .q-answers-scroll-box::-webkit-scrollbar,
    #emp-tab-pane-questions::-webkit-scrollbar,
    #emp-tab-pane-timeline::-webkit-scrollbar {
        width: 6px;
        height: 6px;
    }

    .q-answers-scroll-box::-webkit-scrollbar-track,
    #emp-tab-pane-questions::-webkit-scrollbar-track,
    #emp-tab-pane-timeline::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 4px;
    }

    .q-answers-scroll-box::-webkit-scrollbar-thumb,
    #emp-tab-pane-questions::-webkit-scrollbar-thumb,
    #emp-tab-pane-timeline::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .q-answers-scroll-box::-webkit-scrollbar-thumb:hover,
    #emp-tab-pane-questions::-webkit-scrollbar-thumb:hover,
    #emp-tab-pane-timeline::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Force SweetAlert confirmation popups above all modals */
    .swal2-container {
        z-index: 9999999 !important;
    }

    .swal2-container.swal2-backdrop-show {
        background: rgba(15, 23, 42, 0.55) !important;
        backdrop-filter: blur(4px) !important;
        -webkit-backdrop-filter: blur(4px) !important;
    }
</style>

<script>
    const currentLeadViewId = <?php echo (int)$view_id; ?>;

    function switchEmpTab(tabName) {
        if (tabName === 'questions') {
            $('#btn-emp-questions').addClass('active');
            $('#btn-emp-timeline').removeClass('active');
            $('#emp-tab-pane-questions').show();
            $('#emp-tab-pane-timeline').hide();
        } else {
            $('#btn-emp-timeline').addClass('active');
            $('#btn-emp-questions').removeClass('active');
            $('#emp-tab-pane-timeline').show();
            $('#emp-tab-pane-questions').hide();
        }
    }

    function insertEmpChip(qNum, text) {
        const inputEl = $('#emp-q-input-' + qNum);
        const currentVal = inputEl.val().trim();
        if (!currentVal) {
            inputEl.val(text);
        } else {
            inputEl.val(currentVal + ' - ' + text);
        }
        inputEl.focus();
    }

    function saveEmpQA(qNum, qTitle) {
        const inputEl = $('#emp-q-input-' + qNum);
        const answerText = inputEl.val().trim();

        if (!answerText) {
            inputEl.focus();
            return;
        }

        const saveBtn = $('#emp-q-save-btn-' + qNum);
        saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'ajax/ajax_lead_details.php',
            type: 'POST',
            data: {
                action: 'add_question_answer',
                lead_id: currentLeadViewId,
                question_num: qNum,
                question_text: qTitle,
                answer: answerText
            },
            dataType: 'json',
            success: function(res) {
                saveBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Save');
                if (res.status === 'success') {
                    inputEl.val('');
                    const ans = res.answer;
                    const container = $('#emp-q-answers-list-' + qNum);
                    container.find('.qa-empty-msg').remove();

                    const initial = ans.emp_name ? ans.emp_name.charAt(0).toUpperCase() : 'E';
                    const newHtml = `
                        <div class="qa-ans-card-item" id="emp-ans-item-${ans.id}" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); animation: fadeIn 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <div style="display: flex; align-items: center; gap: 7px;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; flex-shrink: 0;">
                                        ${escapeHtml(initial)}
                                    </div>
                                    <strong style="color: #1e293b; font-size: 12.5px;">${escapeHtml(ans.emp_name)}</strong>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="color: #94a3b8; font-size: 11px;">just now</span>
                                    <button type="button" onclick="deleteEmpQA(${ans.id}, ${qNum})" title="Delete Answer" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 5px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#dc2626'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#dc2626';">
                                        <i class="fa fa-trash-o"></i>
                                    </button>
                                </div>
                            </div>
                            <div style="color: #334155; font-size: 12.5px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; padding-left: 29px;">${escapeHtml(ans.answer)}</div>
                        </div>
                    `;
                    container.css('display', 'flex').show().append(newHtml);
                    container.animate({
                        scrollTop: container[0].scrollHeight
                    }, 200);
                    updateEmpQACount();
                    showPremiumAlert('Answer saved');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'Error saving answer',
                        confirmButtonColor: '#dd2127'
                    });
                }
            },
            error: function(xhr, status, error) {
                saveBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Save');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Connection error while saving answer',
                    confirmButtonColor: '#dd2127'
                });
            }
        });
    }

    function deleteEmpQA(ansId, qNum) {
        Swal.fire({
            title: 'Delete Answer?',
            text: 'Are you sure you want to delete this answer?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dd2127',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/ajax_lead_details.php',
                    type: 'POST',
                    data: {
                        action: 'delete_question_answer',
                        answer_id: ansId,
                        lead_id: currentLeadViewId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            const item = $('#emp-ans-item-' + ansId);
                            const container = $('#emp-q-answers-list-' + qNum);
                            item.fadeOut(180, function() {
                                $(this).remove();
                                if (container.find('.qa-ans-card-item').length === 0) {
                                    container.hide();
                                }
                                updateEmpQACount();
                            });
                            showPremiumAlert('Answer deleted');
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message || 'Error deleting answer',
                                confirmButtonColor: '#dd2127'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Connection error while deleting answer',
                            confirmButtonColor: '#dd2127'
                        });
                    }
                });
            }
        });
    }

    function showPremiumAlert(message, type = 'success') {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.position = 'fixed';
            container.style.bottom = '24px';
            container.style.right = '24px';
            container.style.zIndex = '999999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.background = '#1e293b';
        toast.style.color = '#fff';
        toast.style.padding = '14px 20px';
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 25px -5px rgba(0,0,0,0.25)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.gap = '12px';
        toast.style.fontSize = '13.5px';
        toast.style.fontWeight = '600';
        toast.style.transform = 'translateY(50px) scale(0.95)';
        toast.style.opacity = '0';
        toast.style.transition = 'all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
        toast.style.border = '1px solid rgba(255,255,255,0.1)';

        const iconBg = type === 'error' ? '#ef4444' : '#10b981';
        const iconClass = type === 'error' ? 'fa-times' : 'fa-check';

        toast.innerHTML = `
            <div style="width: 26px; height: 26px; background: ${iconBg}; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                <i class="fa ${iconClass}" style="font-size: 13px; color: #fff;"></i>
            </div>
            <div>${message}</div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateY(0) scale(1)';
            toast.style.opacity = '1';
        }, 10);

        setTimeout(() => {
            toast.style.transform = 'translateY(20px) scale(0.95)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 350);
        }, 3000);
    }

    function updateEmpQACount() {
        let answeredTotal = 0;
        for (let i = 1; i <= 4; i++) {
            const ansCount = $('#emp-q-answers-list-' + i).find('.qa-ans-card-item').length;
            if (ansCount > 0) {
                answeredTotal++;
            }
            $('#emp-q-count-tag-' + i).text(ansCount + (ansCount === 1 ? ' answer' : ' answers'));
        }
        $('#btn-emp-questions .badge').text(answeredTotal + '/4');
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>