<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Only allow access if logged in as employee
if (!isset($_SESSION['emp_id']) || !isset($_SESSION['emp_name'])) {
    header('Location: ../../pages/auth/login.php');
    exit();
}

$emp_id = $_SESSION['emp_id'];

$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$where_clause = " WHERE FIND_IN_SET('$emp_id', cp.assigned_employees) > 0 ";
if ($status_filter) {
    $where_clause .= " AND cp.status='$status_filter' ";
}

$limit = 10;
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Total records for pagination
$countSql = "SELECT COUNT(*) as total FROM client_projects cp " . $where_clause;
$countResult = mysqli_query($con, $countSql);
$totalRecords = 0;
if ($countResult) {
    $row = mysqli_fetch_assoc($countResult);
    $totalRecords = $row['total'];
}
$totalPages = ceil($totalRecords / $limit);

// Fetch projects assigned to this employee
$query = "SELECT cp.* 
          FROM client_projects cp 
          $where_clause 
          ORDER BY cp.id DESC LIMIT $offset, $limit";
$result = mysqli_query($con, $query);

// Count projects for Cards (Assigned to this employee)
$total_projects = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE FIND_IN_SET('$emp_id', assigned_employees) > 0"));
$active_projects = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Active' AND FIND_IN_SET('$emp_id', assigned_employees) > 0"));
$pending_projects = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Pending' AND FIND_IN_SET('$emp_id', assigned_employees) > 0"));
$completed_projects = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Completed' AND FIND_IN_SET('$emp_id', assigned_employees) > 0"));

?>

<div class="premium-ui-enabled">
    <div class="stat-cards-row">
        <!-- Total Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects'">
            <div class="stat-card-icon sc-purple">
                <i class="fa fa-briefcase"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Total Projects</div>
                <div class="stat-card-value"><?php echo $total_projects; ?></div>
            </div>
        </div>

        <!-- Active Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Active'">
            <div class="stat-card-icon sc-green">
                <i class="fa fa-folder-open"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Active Projects</div>
                <div class="stat-card-value"><?php echo $active_projects; ?></div>
            </div>
        </div>

        <!-- Completed Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Completed'">
            <div class="stat-card-icon sc-orange">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Completed Projects</div>
                <div class="stat-card-value"><?php echo $completed_projects; ?></div>
            </div>
        </div>

        <!-- Pending Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Pending'">
            <div class="stat-card-icon sc-blue">
                <i class="fa fa-clock-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Pending Projects</div>
                <div class="stat-card-value"><?php echo $pending_projects; ?></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="premium-card" style="border: none; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 25px -5px rgba(0,0,0,0.08); background: #fff;">
                <div class="card-hdr" style="background: var(--p-bg-header); color: #fff; padding: 18px 25px; display: flex; align-items: center; gap: 12px; border: none;">
                    <i class="fa fa-list-ul" style="font-size: 16px; color: #fff;"></i>
                    <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">Project Assignments</h3>
                </div>
                <div class="table-responsive">
                    <table class="table-premium" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #fcfdfe; border-bottom: 1.5px solid #f1f5f9;">
                                <th style="text-align:center;">ID</th>
                                <th style="text-align:left;">Project Name</th>
                                <th style="text-align:center;">Start Date</th>
                                <th style="text-align:center;">Deadline</th>
                                <th style="text-align:center;">Status</th>
                                <th style="text-align:center;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0) : ?>
                                <?php while ($row = mysqli_fetch_assoc($result)) :
                                    $st = strtolower($row['status']);
                                    $badge_style = 'background: #f1f5f9; color: #64748b;';
                                    if ($st == 'completed') $badge_style = 'background: #ecfdf5; color: #059669;';
                                    elseif ($st == 'active' || $st == 'in progress') $badge_style = 'background: #eff6ff; color: #2563eb;';
                                    elseif ($st == 'pending') $badge_style = 'background: #fff7ed; color: #ea580c;';
                                    elseif ($st == 'cancelled') $badge_style = 'background: #fef2f2; color: #dc2626;';
                                ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="text-align:center; font-weight: 700; color: #64748b;">
                                            <span style="background:#f1f5f9; padding:4px 8px; border-radius:6px; font-size:12px;">#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td style="text-align:left;">
                                            <div style="font-weight: 700; color: #1e293b; font-size: 14px;">
                                                <?php echo htmlspecialchars($row['project_name']); ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php echo !empty($row['project_date']) ? date('d-m-Y', strtotime($row['project_date'])) : '--'; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php echo !empty($row['deadline']) ? date('d-m-Y', strtotime($row['deadline'])) : '--'; ?>
                                        </td>
                                        <td style="text-align: center; padding: 15px; align-items: center;">
                                            <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #ffeaeb; color: #dd2127; display: inline-block; min-width: 90px;">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; padding: 15px;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-icon-premium btn-icon-sm btn-icon-history toggle-detail-btn" title="View Activity Timeline">
                                                    <i class="fa fa-history"></i>
                                                </button>
                                                <button type="button" class="btn-icon-premium btn-icon-sm btn-icon-expense" onclick="openExpenseModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['project_name']); ?>')" title="Project Expenses" style="background: #ffeaeb; color: #dd2127; border: 1px solid #ffeaeb; border-radius: 8px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;">
                                                    <i class="fa fa-calculator"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="project-detail-row" style="display: none; background: #fff;">
                                        <td colspan="7" style="padding: 0; border: none;">
                                            <div style="padding: 35px 50px; border-top: 1px solid #f1f5f9; background: #fcfdfe;">
                                                <div class="row">
                                                    <div class="col-md-7">
                                                        <div class="timeline-container-premium" style="background: transparent; border: none; padding: 0; margin-bottom: 0;">
                                                            <div class="timeline-header-premium" style="margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
                                                                <div style="display: flex; align-items: center; gap: 10px; font-size: 11px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">
                                                                    <i class="fa fa-history" style="color: #dd2127; font-size: 14px;"></i>
                                                                    <span>Project Activity Timeline</span>
                                                                </div>
                                                                <a href="download_progress_report.php?project_id=<?php echo $row['id']; ?>" target="_blank" style="background: #ffeaeb; color: #dd2127; border: 1px solid #ffeaeb; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                                                    <i class="fa fa-download"></i> Download Progress Report
                                                                </a>
                                                            </div>
                                                            <div class="timeline-visual-wrapper" style="max-height: 250px; overflow-y: auto; overflow-x: hidden; padding-right: 15px; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                                                                <div class="timeline-vertical-line" style="left: 4px;"></div>
                                                                <div class="remarks-history-premium" style="position: relative; padding-left: 0;">
                                                                    <?php
                                                                    $p_id = (int)$row['id'];
                                                                    try {
                                                                        @mysqli_query($con, "ALTER TABLE client_project_remarks ADD COLUMN posted_by VARCHAR(255) DEFAULT NULL");
                                                                    } catch (Exception $e) {
                                                                    }

                                                                    $get_remarks = "SELECT * FROM client_project_remarks WHERE project_id = $p_id ORDER BY created_at DESC";
                                                                    $run_remarks = mysqli_query($con, $get_remarks);
                                                                    if ($run_remarks && mysqli_num_rows($run_remarks) > 0) {
                                                                        while ($r = mysqli_fetch_assoc($run_remarks)) {
                                                                            $poster = !empty($r['posted_by']) ? htmlspecialchars($r['posted_by']) : '';
                                                                            if (empty($poster)) {
                                                                                $poster = (strpos($r['remark'], 'System:') === 0) ? 'System' : 'Team Member';
                                                                            }
                                                                            $is_sys = (strtolower($poster) === 'system');
                                                                            $poster_badge_bg = $is_sys ? '#f1f5f9' : '#eff6ff';
                                                                            $poster_badge_color = $is_sys ? '#64748b' : '#2563eb';
                                                                            $poster_icon = $is_sys ? 'fa-cog' : 'fa-user';
                                                                    ?>
                                                                            <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; width: 100%;">
                                                                                <div class="timeline-dot" style="left: 0;"></div>
                                                                                <div class="remark-content-box" style="padding-left: 20px;">
                                                                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                                                                        <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: #ffeaeb; color:#dd2127;display: inline-flex; align-items: center; gap: 4px;">
                                                                                            <i class="fa <?php echo $poster_icon; ?>"></i> <?php echo $poster; ?>
                                                                                        </span>
                                                                                        <div class="remark-time-premium" style="margin: 0; font-size: 11px;">
                                                                                            <i class="fa fa-clock-o"></i> <?php echo date('d-m-Y • h:i A', strtotime($r['created_at'])); ?>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="remark-text-premium" style="font-size: 13px; color: #334155; font-weight: 600;"><?php echo nl2br(htmlspecialchars($r['remark'])); ?></div>
                                                                                </div>
                                                                            </div>
                                                                    <?php
                                                                        }
                                                                    } else {
                                                                        echo '<div class="no-remarks-placeholder" style="padding: 40px 0; text-align: center; color: #94a3b8;">
                                                                                <i class="fa fa-commenting-o" style="font-size: 32px; opacity: 0.4; margin-bottom: 10px; display: block;"></i>
                                                                                <p style="font-size: 13px; font-weight: 700;">No activity recorded yet.</p>
                                                                              </div>';
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <div class="remark-action-premium glass-card-premium" style="padding: 30px; border-radius: 24px; box-shadow: 0 10px 30px -10px rgba(0,0,0,0.08);">
                                                            <h4 style="font-size: 11px; font-weight: 950; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                                                <div style="width: 8px; height: 8px; background: #dd2127; border-radius: 50%;"></div>
                                                                Post Progress Update
                                                            </h4>
                                                            <div class="action-input-wrapper" style="display: flex; flex-direction: column; gap: 15px; width: 100%;">
                                                                <textarea class="remark-textarea p-input-premium" style="width: 100%; height: 120px; resize: none; font-size: 14px; box-sizing: border-box;" placeholder="What milestone was achieved today?"></textarea>
                                                                <button type="button" class="add-remark-btn" data-project-id="<?php echo $row['id']; ?>" style="width: 100%; height: 48px; font-size: 14px; background: #dd2127; color: #ffffff; border: none; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; font-weight: 700; gap: 8px; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.2); box-sizing: border-box;">
                                                                    <i class="fa fa-send"></i> Post Update
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px 40px; color: #94a3b8;">
                                        <i class="fa fa-folder-open-o" style="font-size: 42px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                                        <h4 style="color: #64748b; font-weight: 700; margin-bottom: 5px;">No Projects Assigned</h4>
                                        <p style="font-size: 13px; font-weight: 500;">You are currently not assigned to any projects.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination UI -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-premium">
                        <?php
                        $queryParams = $_GET;
                        unset($queryParams['page']);
                        $qString = http_build_query($queryParams);
                        $baseUrl = "index.php";
                        if (!empty($qString)) {
                            $baseUrl .= "?" . $qString . "&";
                        } else {
                            $baseUrl .= "?";
                        }
                        ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo max(1, $page - 1); ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fa fa-angle-left"></i> Prev</a>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $startPage + 4);
                        if ($endPage - $startPage < 4) {
                            $startPage = max(1, $endPage - 4);
                        }

                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $p; ?>" class="page-link <?php echo $page == $p ? 'active' : ''; ?>"><?php echo $p; ?></a>
                        <?php endfor; ?>

                        <a href="<?php echo $baseUrl; ?>page=<?php echo min($totalPages, $page + 1); ?>" class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next <i class="fa fa-angle-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Toggle project details timeline (Accordion style - only single option open at a time)
        $(document).on('click', '.toggle-detail-btn', function() {
            const $btn = $(this);
            const tr = $btn.closest('tr');
            const targetDetailRow = tr.next('.project-detail-row');
            const isOpen = targetDetailRow.is(':visible');

            // Close all other open project detail rows and deactivate their buttons
            $('.project-detail-row').not(targetDetailRow).slideUp(300);
            $('.toggle-detail-btn').not($btn).removeClass('active');

            if (isOpen) {
                targetDetailRow.slideUp(300);
                $btn.removeClass('active');
            } else {
                targetDetailRow.slideDown(300);
                $btn.addClass('active');
            }
        });

        // Post progress update remark
        $(document).on('click', '.add-remark-btn', function() {
            const btn = $(this);
            const projectId = btn.data('project-id');
            const detailRow = btn.closest('.project-detail-row');
            const remarkInput = detailRow.find('.remark-textarea');
            const remarkText = remarkInput.val().trim();
            const container = detailRow.find('.remarks-history-premium');

            if (!remarkText) {
                remarkInput.focus();
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Posting...');

            $.ajax({
                url: '../admin_area/ajax/clients/ajax_add_client_remark.php',
                type: 'POST',
                data: {
                    project_id: projectId,
                    remark: remarkText
                },
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i> Post Update');
                    if (response.success) {
                        const posterName = response.posted_by || '<?php echo htmlspecialchars($_SESSION['emp_name'] ?? "Employee"); ?>';
                        const newRemark = $(`
                        <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; display: none; width: 100%;">
                            <div class="timeline-dot" style="left: 0; background: #dd2127; border-color: #dd2127; box-shadow: 0 0 0 4px rgba(221, 33, 39, 0.1);"></div>
                            <div class="remark-content-box" style="border-left: 4px solid #dd2127; padding-left: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: #eff6ff; color: #dd2127; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-user"></i> ${posterName}
                                    </span>
                                    <div class="remark-time-premium" style="margin: 0; font-size: 11px;">
                                        <i class="fa fa-clock-o"></i> JUST NOW
                                    </div>
                                </div>
                                <div class="remark-text-premium" style="font-size: 13px; color: #334155; font-weight: 600;">${remarkText.replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>`);

                        container.find('.no-remarks-placeholder').remove();
                        container.prepend(newRemark);
                        newRemark.slideDown(400);
                        remarkInput.val('');
                    } else {
                        alert(response.message || 'Error posting update.');
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i> Post Update');
                    alert('Network error while posting update.');
                }
            });
        });

        let currentExpenseProjectId = null;

        window.openExpenseModal = function(projectId, projectName) {
            currentExpenseProjectId = projectId;
            $('#expense_modal_project_name').text('PROJECT: ' + projectName);
            $('#projectExpensesModal').modal('show');
            loadProjectExpenses(projectId);
        };

        window.toggleAddExpenseForm = function() {
            if (!currentExpenseProjectId) return;
            $('#popup_exp_project_id').val(currentExpenseProjectId);
            if ($('#add_expense_form_popup').length && $('#add_expense_form_popup')[0]) {
                $('#add_expense_form_popup')[0].reset();
            }
            $('#popup_att_file_name').text('Upload invoice or receipt');
            calcPopupExpTotal();
            $('#addExpenseFormModal').modal('show');
        };

        window.calcPopupExpTotal = function() {
            const qty = parseFloat($('#popup_exp_qty').val()) || 1;
            const cost = parseFloat($('#popup_exp_cost').val()) || 0;
            const total = qty * cost;
            $('#popup_exp_total_display').val(total.toFixed(2));
        };

        window.downloadExpenseStatement = function() {
            if (!currentExpenseProjectId) return;
            window.open('../admin_area/pages/projects/generate_expense_statement.php?project_id=' + currentExpenseProjectId, '_blank');
        };

        function loadProjectExpenses(projectId) {
            $('#project_expenses_table_body').html('<tr><td colspan="10" style="text-align:center; padding:30px; color:#64748b;"><i class="fa fa-spinner fa-spin"></i> Loading expenses...</td></tr>');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_get_project_expenses.php',
                method: 'GET',
                data: {
                    project_id: projectId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        const sym = '₹';
                        $('#project_expenses_grand_total').text(sym + ' ' + res.formatted_total_sum);

                        if (res.expenses.length === 0) {
                            $('#project_expenses_table_body').html('<tr><td colspan="10" style="text-align:center; padding:45px 20px; color:#94a3b8; font-weight:600; font-size:14px; background:#ffffff;">No expenses recorded for this project yet. Click + Add Expense to create one.</td></tr>');
                            return;
                        }

                        let html = '';
                        res.expenses.forEach(function(exp) {
                            let ordHtml = exp.ordered_from ? htmlEscapeExp(exp.ordered_from) : '-';
                            if (exp.ordered_from_url) {
                                ordHtml += ` <a href="${htmlEscapeExp(exp.ordered_from_url)}" target="_blank" style="color:#6366f1; font-size:11px;" title="Visit Website"><i class="fa fa-external-link"></i></a>`;
                            }

                            let invHtml = exp.invoice_no ? `Invoice #${htmlEscapeExp(exp.invoice_no)}` : '-';
                            if (exp.invoice_file) {
                                let invPath = exp.invoice_file;
                                if (invPath && !invPath.startsWith('http') && !invPath.startsWith('/')) {
                                    invPath = '../admin_area/' + invPath.replace(/^(\.\.\/)+/, '');
                                }
                                invHtml += ` <a href="${htmlEscapeExp(invPath)}" target="_blank" style="color:#6366f1; font-weight:600; margin-left:5px; font-size:12px;">View</a>`;
                            }

                            let attHtml = '-';
                            if (exp.attachment) {
                                let attPath = exp.attachment;
                                if (attPath && !attPath.startsWith('http') && !attPath.startsWith('/')) {
                                    attPath = '../admin_area/' + attPath.replace(/^(\.\.\/)+/, '');
                                }
                                attHtml = `<a href="${htmlEscapeExp(attPath)}" target="_blank" style="color:#6366f1; font-weight:600; font-size:12px;">View</a>`;
                            }

                            html += `
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="font-weight:700; color:#334155; font-size:13px; padding:12px 14px;">${htmlEscapeExp(exp.item_name)}</td>
                                <td style="text-align:center; font-weight:600; color:#475569; font-size:13px; padding:12px 14px;">${exp.qty}</td>
                                <td style="text-align:center; font-weight:700; color:#334155; font-size:13px; padding:12px 14px;">${sym}${parseFloat(exp.cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                <td style="text-align:center; font-weight:800; color:#1e293b; font-size:13px; padding:12px 14px;">${sym}${parseFloat(exp.total_cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                <td style="text-align:center; color:#64748b; font-size:12px; font-weight:600; padding:12px 14px;">${exp.expense_date}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${ordHtml}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${exp.paid_by ? htmlEscapeExp(exp.paid_by) : '-'}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${invHtml}</td>
                                <td style="text-align:center; font-size:12px; padding:12px 14px;">${attHtml}</td>
                                <td style="text-align:center; padding:12px 14px;">
                                    <button type="button" class="btn-delete-expense-item" data-id="${exp.id}" data-project-id="${projectId}" onclick="deleteProjectExpense(${exp.id}, ${projectId})" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:14px; padding:4px 8px;" title="Delete Expense"><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                        });
                        $('#project_expenses_table_body').html(html);
                    }
                }
            });
        }

        function htmlEscapeExp(str) {
            if (!str) return '';
            return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
        }

        $(document).on('submit', '#add_expense_form_popup', function(e) {
            e.preventDefault();
            const btn = $('#btn_submit_popup_expense');
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');

            const formData = new FormData(this);
            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_expense.php',
                method: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(res) {
                    btn.prop('disabled', false).html('<i class="fa fa-file-text"></i> Submit Expense');
                    if (res.success) {
                        $('#addExpenseFormModal').modal('hide');
                        loadProjectExpenses(currentExpenseProjectId);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Expense Added',
                                text: res.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message
                            });
                        } else {
                            alert(res.message);
                        }
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-file-text"></i> Submit Expense');
                    alert('Network error saving expense.');
                }
            });
        });

        window.deleteProjectExpense = function(expId, projectId) {
            if (!expId) return;

            function doDelete() {
                $.ajax({
                    url: '../admin_area/ajax/projects/ajax_delete_project_expense.php',
                    method: 'POST',
                    data: {
                        expense_id: expId
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res && res.success) {
                            loadProjectExpenses(projectId || currentExpenseProjectId);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted',
                                    text: res.message || 'Expense deleted successfully.',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        } else {
                            const msg = (res && res.message) ? res.message : 'Could not delete expense';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire('Error', msg, 'error');
                            } else {
                                alert(msg);
                            }
                        }
                    },
                    error: function() {
                        alert('Could not delete expense due to network error.');
                    }
                });
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete Expense?',
                    text: 'Are you sure you want to delete this expense entry?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        doDelete();
                    }
                });
            } else {
                if (confirm('Are you sure you want to delete this expense entry?')) {
                    doDelete();
                }
            }
        };
    });
</script>

<!-- Project Expenses Modal -->
<div class="modal fade" id="projectExpensesModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog" role="document" style="width: 92% !important; max-width: 1300px !important; margin: 30px auto !important;">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 14px; text-align: left;">
                    <div style="width: 42px; height: 42px; background: #dd2127; color: #ffffff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                        <i class="fa fa-calculator"></i>
                    </div>
                    <div style="text-align: left;">
                        <h4 class="modal-title" style="margin: 0; font-size: 20px; font-weight: 800; color: #1e293b; letter-spacing: -0.3px; text-align: left;">Project Expenses</h4>
                        <div id="expense_modal_project_name" style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; text-align: left;">PROJECT</div>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="padding: 30px; background: #f8fafc;">
                <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">

                    <!-- Subheader: Expense History + Add Expense Button -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-receipt" style="color: #dd2127;"></i> Expense History
                            </h3>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500;">Track all project purchases, expenses and attachments.</p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button" class="btn-premium-add" onclick="downloadExpenseStatement()">
                                <i class="fa fa-file-text-o"></i> Download Statement
                            </button>
                            <button type="button" class="btn-premium-add" onclick="toggleAddExpenseForm()">
                                <i class="fa fa-plus"></i> Add Expense
                            </button>
                        </div>
                    </div>

                    <!-- Expenses Table -->
                    <div style="overflow-x: auto; overflow-y: auto; max-height: 380px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff;">
                        <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
                            <thead style="background: #52525b; color: #ffffff; position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ITEM NAME</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">QTY</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">COST</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">TOTAL COST</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">DATE</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ORDERED FROM</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">PAID BY</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">INVOICE</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ATTACHMENT</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="project_expenses_table_body">
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #64748b;">Loading expenses...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total Expenses Footer -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 15px; border-top: 1.5px solid #f1f5f9;">
                        <div style="font-size: 14px; font-weight: 800; color: #475569; letter-spacing: 0.5px; text-transform: uppercase;">
                            TOTAL PROJECT EXPENSES:
                        </div>
                        <div id="project_expenses_grand_total" style="font-size: 24px; font-weight: 900; color: #ef4444; letter-spacing: -0.5px;">
                            ₹ 0.00
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dedicated Add Project Expense Sub-Modal -->
<div class="modal fade" id="addExpenseFormModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document" style="width: 580px !important; max-width: 95% !important; margin: 40px auto !important;">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);">
            <!-- Header -->
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="width: 44px; height: 44px; background: #dd2127; color: #ffffff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                        <i class="fa fa-calculator"></i>
                    </div>
                    <div>
                        <h4 class="modal-title" style="margin: 0; font-size: 19px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">Add Project Expense</h4>
                        <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500;">Add a new expense to this project.</p>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body" style="padding: 10px 28px 28px 28px; background: #ffffff;">
                <form id="add_expense_form_popup" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" id="popup_exp_project_id">

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-7">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Item Name</label>
                            <input type="text" name="item_name" class="form-control p-input-premium" placeholder="Enter item name" required style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                        <div class="col-md-5">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Qty</label>
                            <input type="number" name="qty" id="popup_exp_qty" value="1" min="1" class="form-control p-input-premium" required oninput="calcPopupExpTotal()" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Cost</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 12px; font-weight: 700; color: #64748b; font-size: 14px;" class="popup_exp_curr_sym">₹</span>
                                <input type="number" step="0.01" name="cost" id="popup_exp_cost" placeholder="0.00" class="form-control p-input-premium" required oninput="calcPopupExpTotal()" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding-left: 32px; font-size: 13.5px; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Total Cost</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 12px; font-weight: 700; color: #64748b; font-size: 14px;" class="popup_exp_curr_sym">₹</span>
                                <input type="text" id="popup_exp_total_display" value="0.00" class="form-control p-input-premium" readonly style="height: 44px; border-radius: 10px; border: 1px solid #e2e8f0; padding-left: 32px; font-size: 13.5px; font-weight: 800; background: #f8fafc; color: #1e293b; width: 100%;">
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Date</label>
                            <input type="date" name="expense_date" class="form-control p-input-premium" value="<?php echo date('Y-m-d'); ?>" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Ordered From</label>
                            <input type="text" name="ordered_from" class="form-control p-input-premium" placeholder="e.g. DigiKey / Robu / Mouser" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Order Link</label>
                            <div style="position: relative;">
                                <i class="fa fa-link" style="position: absolute; left: 14px; top: 14px; color: #94a3b8; font-size: 13px;"></i>
                                <input type="url" name="ordered_from_url" class="form-control p-input-premium" placeholder="https://" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding-left: 36px; font-size: 13.5px; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Paid By</label>
                            <input type="text" name="paid_by" class="form-control p-input-premium" value="<?php echo htmlspecialchars($_SESSION['emp_name'] ?? ''); ?>" placeholder="e.g. CADLETE / Employee" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Invoice Number</label>
                        <input type="text" name="invoice_no" class="form-control p-input-premium" placeholder="Enter invoice number" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Upload Attachment</label>
                        <div style="border: 2px dashed #cbd5e1; border-radius: 14px; padding: 24px; text-align: center; background: #f8fafc; cursor: pointer; transition: 0.3s; position: relative;" onclick="document.getElementById('popup_expense_attachment').click()">
                            <div style="width: 44px; height: 44px; background: #ffffff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #475569; font-size: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 8px;">
                                <i class="fa fa-cloud-upload"></i>
                            </div>
                            <div style="font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 3px;" id="popup_att_file_name">Upload invoice or receipt</div>
                            <div style="font-size: 12px; color: #94a3b8;">PDF, JPG, PNG</div>
                            <input type="file" name="attachment" id="popup_expense_attachment" style="display: none;" accept="image/*,.pdf" onchange="if(this.files[0]) $('#popup_att_file_name').text(this.files[0].name);">
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn_submit_popup_expense" class="btn-premium-add">
                            <i class="fa fa-file-text"></i> Submit Expense
                        </button>
                    </div>
            </div>
        </div>
    </div>
</div>