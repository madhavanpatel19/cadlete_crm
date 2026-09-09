    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
    global $con;

    if (!isset($_SESSION['admin_email'])) {
        echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
        exit;
    }

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
        $view_id = mysqli_real_escape_string($con, $_GET['view_lead']);
        $get_lead = "SELECT * FROM leads WHERE id = '$view_id'";
        $run_lead = mysqli_query($con, $get_lead);
        $row_lead = mysqli_fetch_array($run_lead);

        if (!$row_lead) {
            echo "<style>
            body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
                overflow: hidden !important;
            }
            .swal2-backdrop-show {
                backdrop-filter: blur(5px) !important;
                background: rgba(15, 23, 42, 0.6) !important;
            }
        </style>";
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Lead not found!',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
            exit;
        }

        $client_name = $row_lead['client_name'];

        // Handle Admin Question Answer Submission
        if (isset($_POST['submit_lead_question_answer'])) {
            $q_num = (int)$_POST['question_num'];
            $q_text = mysqli_real_escape_string($con, $_POST['question_text'] ?? '');
            $ans = trim($_POST['answer'] ?? '');
            $admin_name = $_SESSION['admin_name'] ?? 'Admin';
            $admin_id = (int)($_SESSION['admin_id'] ?? 0);

            if (!empty($ans) && $q_num > 0) {
                $esc_ans = mysqli_real_escape_string($con, $ans);
                $esc_name = mysqli_real_escape_string($con, $admin_name);

                $ins_q = "INSERT INTO lead_question_answers (lead_id, emp_id, emp_name, question_num, question_text, answer) 
                      VALUES ('$view_id', '$admin_id', '$esc_name', '$q_num', '$q_text', '$esc_ans')";
                if (mysqli_query($con, $ins_q)) {
                    $f_remark = mysqli_real_escape_string($con, "[Q$q_num: $q_text]\n$ans (by $admin_name)");
                    $today = date('Y-m-d');
                    mysqli_query($con, "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                                    VALUES ('$view_id', '$today', 'Follow-up Q&A', 'Question Response', '$f_remark')");
                    echo "<script>window.location.href='index.php?view_lead=$view_id&tab=questions';</script>";
                    exit;
                }
            }
        }

        // Handle Delete Question Answer
        if (isset($_GET['delete_qa_id'])) {
            $del_id = (int)$_GET['delete_qa_id'];
            if ($del_id > 0) {
                mysqli_query($con, "DELETE FROM lead_question_answers WHERE id = '$del_id' AND lead_id = '$view_id'");
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

        // Get Follow-up History
        $get_followups = "SELECT * FROM lead_followups WHERE lead_id = '$view_id' ORDER BY followup_date DESC, id DESC";
        $run_followups = mysqli_query($con, $get_followups);
    }
    include("leads_logic.php");

    $active_tab = isset($_GET['tab']) && $_GET['tab'] === 'questions' ? 'questions' : 'timeline';

    $default_questions_data = [
        1 => [
            'title' => 'Can we do it or not?',
            'placeholder' => 'Write answer (e.g. Yes we can do it, scope details)...',
            // 'chips' => ['Yes, we can do it', 'No, out of scope', 'Need more details from client']
        ],
        2 => [
            'title' => 'Complexity of the project on the scale from 1 to 5 (1 is easy, 5 is complex)',
            'placeholder' => 'Rate complexity 1 to 5 with explanation...',
            // 'chips' => ['1 - Very Easy', '2 - Easy', '3 - Moderate', '4 - Complex', '5 - Very Complex']
        ],
        3 => [
            'title' => 'Time required for the project',
            'placeholder' => 'Estimated time required (e.g. 2-3 days, 1 week)...',
            // 'chips' => ['1-2 Days', '3-5 Days', '1-2 Weeks', '3-4 Weeks', '1+ Month']
        ],
        4 => [
            'title' => 'Reply mail to client about any additional information/suggestions that I can directly forwarded to him',
            'placeholder' => 'Write draft reply email or additional suggestions for the client...',
            // 'chips' => ['All details clear, ready to proceed', 'Need client confirmation on requirements', 'Drafted email reply provided']
        ]
    ];
    ?>

    <div class="page-wrapper premium-ui-enabled">
        <div class="page-header-premium">
            <div>

            </div>
            <div class="header-actions-premium">
                <a href="index.php?leads" class="btn-premium-add">
                    <i class="fa fa-arrow-left"></i> Back
                </a>
                <?php if (canAdminAccess('lead_update')): ?>
                    <a href="index.php?edit_lead=<?php echo $view_id; ?>" class="btn-premium-add">
                        <i class="fa fa-pencil"></i> Edit
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Lead Details -->
            <div class="col-md-5">
                <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                    <div class="card-hdr" style="background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 16px 20px; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-info-circle" style="color: #dd2127; font-size: 16px;"></i>
                        <h3 style="margin: 0; color: #1e293b; font-size: 15px; font-weight: 700;">Lead Information</h3>
                    </div>
                    <div style="padding: 20px;">
                        <div style="background: #f8fafc; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                            <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo htmlspecialchars($row_lead['client_name']); ?></h2>
                            <p style="color: #64748b; margin-top: 5px; font-weight: 500;">
                                <i class="fa fa-building"></i> <?php echo !empty($row_lead['company_name']) ? htmlspecialchars($row_lead['company_name']) : 'Individual Client'; ?>
                            </p>
                            <div style="margin-top: 15px; display: flex; gap: 10px;">
                                <a href="tel:<?php echo htmlspecialchars($row_lead['phone']); ?>" class="btn btn-default btn-sm" style="border-radius: 10px;">
                                    <i class="fa fa-phone"></i> Call
                                </a>
                                <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $row_lead['phone']); ?>" target="_blank" class="btn btn-success btn-sm" style="border-radius: 10px;">
                                    <i class="fa fa-whatsapp"></i> WhatsApp
                                </a>
                                <?php if (!empty($row_lead['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($row_lead['email']); ?>" class="btn btn-primary btn-sm" style="border-radius: 10px">
                                        <i class="fa fa-envelope"></i> Email
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <table class="table table-clean">
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Project</th>
                                <td style="border:none; font-weight: 700; color: #1e293b;"><?php echo !empty($row_lead['project_name']) ? htmlspecialchars($row_lead['project_name']) : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Budget</th>
                                <td style="border:none; font-weight: 700; color: #1e293b;"><?php echo !empty($row_lead['budget']) ? (isset($row_lead['currency']) && isset($currency_symbols[$row_lead['currency']]) ? $currency_symbols[$row_lead['currency']] : (isset($row_lead['currency']) ? $row_lead['currency'] : 'INR')) . ' ' . htmlspecialchars($row_lead['budget']) : 'N/A'; ?></td>
                            </tr>
                            <?php if (canAdminAccess('project_source_view')): ?>
                                <tr>
                                    <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Lead Source</th>
                                    <td style="border:none; font-weight: 600;"><?php echo htmlspecialchars($row_lead['lead_source']); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Current Status</th>
                                <td style="border:none;">
                                    <?php
                                    $status = $row_lead['status'];
                                    $badge_class = 'p-badge-success';
                                    if ($status == 'future') $badge_class = 'p-badge-primary';
                                    if ($status == 'expired') $badge_class = 'p-badge-danger';
                                    ?>
                                    <span class="p-badge <?php echo $badge_class; ?>" style="text-transform:capitalize;"><?php echo $status; ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Next Follow-up</th>
                                <td style="border:none; font-weight: 700; color: #dd2127;"><?php echo !empty($row_lead['followup_date']) ? date('d-m-Y', strtotime($row_lead['followup_date'])) : 'Not Scheduled'; ?></td>
                            </tr>
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Assigned Team</th>
                                <td style="border:none;">
                                    <?php
                                    $v_emp_names = [];
                                    $v_emp_ids = !empty($row_lead['assigned_employees']) ? array_filter(array_map('intval', explode(',', $row_lead['assigned_employees']))) : [];
                                    if (!empty($v_emp_ids)) {
                                        $v_emp_impl = implode(',', $v_emp_ids);
                                        $r_emps = mysqli_query($con, "SELECT name FROM emp_list WHERE id IN ($v_emp_impl)");
                                        if ($r_emps) {
                                            while ($e_r = mysqli_fetch_assoc($r_emps)) {
                                                $v_emp_names[] = htmlspecialchars($e_r['name']);
                                            }
                                        }
                                    }
                                    $v_adm_names = [];
                                    $v_adm_ids = !empty($row_lead['assigned_admins']) ? array_filter(array_map('intval', explode(',', $row_lead['assigned_admins']))) : [];
                                    if (!empty($v_adm_ids)) {
                                        $v_adm_impl = implode(',', $v_adm_ids);
                                        $r_adms = mysqli_query($con, "SELECT admin_name FROM admins WHERE admin_id IN ($v_adm_impl)");
                                        if ($r_adms) {
                                            while ($a_r = mysqli_fetch_assoc($r_adms)) {
                                                $v_adm_names[] = htmlspecialchars($a_r['admin_name']);
                                            }
                                        }
                                    }

                                    if (!empty($v_emp_names) || !empty($v_adm_names)) {
                                        echo '<div style="display:flex; flex-wrap:wrap; gap:5px;">';
                                        foreach ($v_emp_names as $en) {
                                            echo '<span class="label label-info" style="background:#ffeaeb; color:#dd2127; border:1px solid #ffc7cb; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:600;"><i class="fa fa-user" style="margin-right:4px;"></i>' . $en . '</span>';
                                        }
                                        foreach ($v_adm_names as $an) {
                                            echo '<span class="label label-warning" style="background:#ffeaeb; color:#dd2127; border:1px solid #ffc7cb; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:600;"><i class="fa fa-user-secret" style="margin-right:4px;"></i>' . $an . '</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<span style="color:#94a3b8;">Unassigned</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>

                        <?php
                        $raw_desc = $row_lead['description'] ?? '';
                        $is_default_template = (stripos($raw_desc, 'Can we do it or not') !== false && stripos($raw_desc, 'Complexity of the project') !== false);
                        $clean_desc = (!$is_default_template && !empty(trim($raw_desc))) ? trim($raw_desc) : '';
                        ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                            <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Description</label>
                            <p style="color: #475569; line-height: 1.6; margin-top: 5px;"><?php echo !empty($clean_desc) ? nl2br(htmlspecialchars($clean_desc)) : '<span style="color: #94a3b8; font-style: italic; font-size: 13px;">No description provided.</span>'; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Questions Set & Follow-up Timeline -->
            <div class="col-md-7">
                <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                    <div class="card-hdr" style="background: #ffffff; border-bottom: 1px solid #f1f5f9; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <!-- Card Tabs -->
                        <div style="display: flex; gap: 8px;">
                            <button type="button" class="btn btn-sm view-tab-btn <?php echo $active_tab === 'timeline' ? 'active' : ''; ?>" onclick="switchViewTab('timeline')" id="btn-view-timeline">
                                <i class="fa fa-history"></i> Activity & Notes
                                <span class="badge"><?php echo mysqli_num_rows($run_followups); ?></span>
                            </button>
                            <button type="button" class="btn btn-sm view-tab-btn <?php echo $active_tab === 'questions' ? 'active' : ''; ?>" onclick="switchViewTab('questions')" id="btn-view-questions">
                                <i class="fa fa-list-ol"></i> Required Questions
                                <span class="badge"><?php echo $answered_count; ?>/4</span>
                            </button>
                        </div>

                        <?php if (canAdminAccess('lead_update')): ?>
                            <button onclick="openFollowupModal(<?php echo $view_id; ?>, '<?php echo htmlspecialchars($client_name); ?>')" class="btn-premium-add">
                                <i class="fa fa-plus"></i> Add Follow-up
                            </button>
                        <?php endif; ?>
                    </div>

                    <div style="padding: 22px;">
                        <!-- TAB 1: Follow-up Timeline -->
                        <div id="view-tab-pane-timeline" style="display: <?php echo $active_tab === 'timeline' ? 'block' : 'none'; ?>; max-height: 560px; overflow-y: auto; overflow-x: hidden; padding-right: 6px;">
                            <?php if (mysqli_num_rows($run_followups) > 0): ?>
                                <div class="timeline-premium">
                                    <?php while ($f_row = mysqli_fetch_array($run_followups)): ?>
                                        <div class="timeline-item-premium">
                                            <div class="timeline-icon-premium">
                                                <?php
                                                $method = strtolower($f_row['followup_method']);
                                                $icon = 'fa-comment';
                                                if ($method == 'phone') $icon = 'fa-phone';
                                                if ($method == 'whatsapp') $icon = 'fa-whatsapp';
                                                if ($method == 'email') $icon = 'fa-envelope';
                                                if ($method == 'follow-up q&a' || $method == 'q&a') $icon = 'fa-list-ol';
                                                ?>
                                                <i class="fa <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="timeline-body-premium">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <h4 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 14px;">
                                                        <?php echo htmlspecialchars($f_row['followup_method']); ?> - <?php echo htmlspecialchars($f_row['followup_type']); ?>
                                                    </h4>
                                                    <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">
                                                        <?php echo date('d-m-Y', strtotime($f_row['followup_date'])); ?>
                                                    </span>
                                                </div>
                                                <p style="margin: 8px 0 0 0; color: #475569; font-size: 13px; line-height: 1.5;">
                                                    <?php echo nl2br(htmlspecialchars($f_row['remark'])); ?>
                                                </p>
                                                <div style="margin-top: 10px; display: flex; align-items: center; gap: 5px; font-size: 11px; color: #94a3b8;">
                                                    <i class="fa fa-clock-o"></i> Logged at <?php echo date('h:i A', strtotime($f_row['created_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center" style="padding: 40px 20px; background: #f8fafc; border-radius: 16px; border: 2px dashed #e2e8f0;">
                                    <div style="width: 60px; height: 60px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                                        <i class="fa fa-calendar-times-o" style="font-size: 24px; color: #cbd5e1;"></i>
                                    </div>
                                    <h4 style="color: #64748b; font-weight: 700; font-size: 15px; margin: 0 0 4px 0;">No History Yet</h4>
                                    <p style="color: #94a3b8; font-size: 12.5px; max-width: 250px; margin: 0 auto;">Start logging notes or follow-ups to track your interactions with this lead.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: 4 Individual Question Cards -->
                        <div id="view-tab-pane-questions" style="display: <?php echo $active_tab === 'questions' ? 'block' : 'none'; ?>; max-height: 560px; overflow-y: auto; overflow-x: hidden; padding-right: 6px;">
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
                                            <span id="admin-q-count-tag-<?php echo $q_num; ?>" class="badge" style="background: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 6px;">
                                                <?php echo count($answers); ?> <?php echo count($answers) === 1 ? 'answer' : 'answers'; ?>
                                            </span>
                                        </div>
                                        <!-- Answers Scroll Box (Shown only if answers exist) -->
                                        <div id="admin-q-answers-list-<?php echo $q_num; ?>" class="q-answers-scroll-box" style="max-height: 200px; overflow-y: auto; overflow-x: hidden; padding: 2px 0; margin-top: 10px; margin-bottom: 6px; display: <?php echo !empty($answers) ? 'flex' : 'none'; ?>; flex-direction: column; gap: 8px; border: none; background: transparent;">
                                            <?php if (!empty($answers)): ?>
                                                <?php foreach ($answers as $ans):
                                                    $a_name = !empty($ans['emp_name']) ? htmlspecialchars($ans['emp_name']) : 'User';
                                                    $a_init = strtoupper(substr($a_name, 0, 1));
                                                    $a_date = !empty($ans['created_at']) ? date('d M Y, h:i A', strtotime($ans['created_at'])) : '';
                                                ?>
                                                    <div class="qa-ans-card-item" id="admin-ans-item-<?php echo $ans['id']; ?>" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                                            <div style="display: flex; align-items: center; gap: 7px;">
                                                                <div style="width: 22px; height: 22px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; flex-shrink: 0;">
                                                                    <?php echo $a_init; ?>
                                                                </div>
                                                                <strong style="color: #1e293b; font-size: 12.5px;"><?php echo $a_name; ?></strong>
                                                            </div>
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <span style="color: #94a3b8; font-size: 11px;"><?php echo $a_date; ?></span>
                                                                <button type="button" onclick="deleteAdminQA(<?php echo $ans['id']; ?>, <?php echo $q_num; ?>)" title="Delete Answer" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 5px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#dc2626'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#dc2626';">
                                                                    <i class="fa fa-trash-o"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div style="color: #334155; font-size: 12.5px; line-height: 1.45; white-space: pre-wrap; word-break: break-word; padding-left: 29px;"><?php echo htmlspecialchars($ans['answer']); ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Answer Input Form -->
                                        <div style="display: flex; gap: 8px; align-items: flex-end; margin-top: 12px;">
                                            <textarea id="admin-q-input-<?php echo $q_num; ?>" class="view-q-textarea" placeholder="<?php echo htmlspecialchars($q_info['placeholder']); ?>" rows="1"></textarea>
                                            <button type="button" id="admin-q-save-btn-<?php echo $q_num; ?>" onclick="saveAdminQA(<?php echo $q_num; ?>, '<?php echo addslashes($q_info['title']); ?>')" style="background: #dd2127; color: #fff; border: none; border-radius: 8px; padding: 0 18px; font-size: 13px; font-weight: 700; cursor: pointer; flex-shrink: 0; display: inline-flex; align-items: center; gap: 6px; height: 38px; transition: background 0.15s ease;" onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dd2127'">
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

    <?php include("leads_modal.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .table-clean th {
            padding: 12px 0 !important;
            width: 130px;
        }

        .table-clean td {
            padding: 12px 0 !important;
        }

        /* Tab Buttons Styling */
        .view-tab-btn {
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

        .view-tab-btn:hover {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
        }

        .view-tab-btn.active {
            background: #dd2127 !important;
            color: #ffffff !important;
            border-color: #dd2127 !important;
            box-shadow: 0 2px 6px rgba(221, 33, 39, 0.25) !important;
        }

        .view-tab-btn .badge {
            background: rgba(0, 0, 0, 0.08) !important;
            color: inherit !important;
            font-size: 10.5px !important;
            padding: 2px 7px !important;
            border-radius: 10px !important;
            font-weight: 800 !important;
        }

        .view-tab-btn.active .badge {
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
        #view-tab-pane-questions,
        #view-tab-pane-timeline {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f8fafc;
        }

        .q-answers-scroll-box::-webkit-scrollbar,
        #view-tab-pane-questions::-webkit-scrollbar,
        #view-tab-pane-timeline::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .q-answers-scroll-box::-webkit-scrollbar-track,
        #view-tab-pane-questions::-webkit-scrollbar-track,
        #view-tab-pane-timeline::-webkit-scrollbar-track {
            background: #f8fafc;
            border-radius: 4px;
        }

        .q-answers-scroll-box::-webkit-scrollbar-thumb,
        #view-tab-pane-questions::-webkit-scrollbar-thumb,
        #view-tab-pane-timeline::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .q-answers-scroll-box::-webkit-scrollbar-thumb:hover,
        #view-tab-pane-questions::-webkit-scrollbar-thumb:hover,
        #view-tab-pane-timeline::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Timeline Styles */
        .timeline-premium {
            position: relative;
            padding-left: 52px;
        }

        .timeline-premium::before {
            content: '';
            position: absolute;
            left: 17px;
            top: 10px;
            bottom: 0;
            width: 2px;
            background: #f1f5f9;
        }

        .timeline-item-premium {
            position: relative;
            margin-bottom: 24px;
        }

        .timeline-icon-premium {
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
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .timeline-body-premium {
            background: #fff;
            padding: 16px 18px;
            border-radius: 14px;
            border: 1px solid #f1f5f9;
            transition: all 0.3s;
        }

        .timeline-item-premium:hover .timeline-body-premium {
            border-color: #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            transform: translateX(4px);
        }

        .timeline-item-premium:hover .timeline-icon-premium {
            border-color: #dd2127;
            background: #dd2127;
            color: #fff;
        }

        .p-badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
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

        function switchViewTab(tabName) {
            if (tabName === 'questions') {
                $('#btn-view-questions').addClass('active');
                $('#btn-view-timeline').removeClass('active');
                $('#view-tab-pane-questions').show();
                $('#view-tab-pane-timeline').hide();
            } else {
                $('#btn-view-timeline').addClass('active');
                $('#btn-view-questions').removeClass('active');
                $('#view-tab-pane-timeline').show();
                $('#view-tab-pane-questions').hide();
            }
        }

        function insertAdminChip(qNum, text) {
            const inputEl = $('#admin-q-input-' + qNum);
            const currentVal = inputEl.val().trim();
            if (!currentVal) {
                inputEl.val(text);
            } else {
                inputEl.val(currentVal + ' - ' + text);
            }
            inputEl.focus();
        }

        function saveAdminQA(qNum, qTitle) {
            const inputEl = $('#admin-q-input-' + qNum);
            const answerText = inputEl.val().trim();

            if (!answerText) {
                inputEl.focus();
                return;
            }

            const saveBtn = $('#admin-q-save-btn-' + qNum);
            saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/leads/ajax_lead_questions.php',
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
                        const container = $('#admin-q-answers-list-' + qNum);
                        container.find('.qa-empty-msg').remove();

                        const newHtml = `
                        <div class="qa-ans-card-item" id="admin-ans-item-${ans.id}" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.02); animation: fadeIn 0.2s ease;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <div style="display: flex; align-items: center; gap: 7px;">
                                    <div style="width: 22px; height: 22px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 10px; flex-shrink: 0;">
                                        ${escapeHtml(ans.initial || 'A')}
                                    </div>
                                    <strong style="color: #1e293b; font-size: 12.5px;">${escapeHtml(ans.emp_name)}</strong>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="color: #94a3b8; font-size: 11px;">${escapeHtml(ans.date_str || 'just now')}</span>
                                    <button type="button" onclick="deleteAdminQA(${ans.id}, ${qNum})" title="Delete Answer" style="background: #fee2e2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 5px; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; cursor: pointer; transition: all 0.15s ease;" onmouseover="this.style.background='#dc2626'; this.style.color='#fff';" onmouseout="this.style.background='#fee2e2'; this.style.color='#dc2626';">
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
                        updateAdminQACount();
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

        function deleteAdminQA(ansId, qNum) {
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
                        url: 'ajax/leads/ajax_lead_questions.php',
                        type: 'POST',
                        data: {
                            action: 'delete_question_answer',
                            answer_id: ansId,
                            lead_id: currentLeadViewId
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 'success') {
                                const item = $('#admin-ans-item-' + ansId);
                                const container = $('#admin-q-answers-list-' + qNum);
                                item.fadeOut(180, function() {
                                    $(this).remove();
                                    if (container.find('.qa-ans-card-item').length === 0) {
                                        container.hide();
                                    }
                                    updateAdminQACount();
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

        function updateAdminQACount() {
            let answeredTotal = 0;
            for (let i = 1; i <= 4; i++) {
                const ansCount = $('#admin-q-answers-list-' + i).find('.qa-ans-card-item').length;
                if (ansCount > 0) {
                    answeredTotal++;
                }
                $('#admin-q-count-tag-' + i).text(ansCount + (ansCount === 1 ? ' answer' : ' answers'));
            }
            $('#btn-view-questions .badge').text(answeredTotal + '/4');
        }

        function openFollowupModal(leadId, clientName) {
            document.getElementById('modalLeadId').value = leadId;
            document.getElementById('modalClientName').innerText = clientName;
            $('#followupModal').modal('show');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }
    </script>