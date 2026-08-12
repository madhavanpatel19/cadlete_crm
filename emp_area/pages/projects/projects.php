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
$query = "SELECT cp.*, c.name as client_name 
          FROM client_projects cp 
          LEFT JOIN clients c ON cp.client_id = c.id 
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
                                <th style="text-align:center;">Client</th>
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
                                            <div style="font-weight: 600; color: #64748b; font-size: 13px;">
                                                <i class="fa fa-user" style="margin-right: 5px; opacity: 0.6;"></i>
                                                <?php echo htmlspecialchars($row['client_name'] ?? 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php echo !empty($row['project_date']) ? date('d M Y', strtotime($row['project_date'])) : '--'; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php echo !empty($row['deadline']) ? date('d M Y', strtotime($row['deadline'])) : '--'; ?>
                                        </td>
                                        <td style="text-align: center; padding: 15px; align-items: center;">
                                            <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #ffeaeb; color: #dd2127; display: inline-block; min-width: 90px;">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; padding: 15px; align-items: center;">
                                            <button type="button" class="btn-icon-premium btn-icon-sm btn-icon-warning toggle-detail-btn" title="View Timeline & Post Progress Update">
                                                <i class="fa fa-list-alt"></i>
                                            </button>
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
                                                                                            <i class="fa fa-clock-o"></i> <?php echo date('d M Y • h:i A', strtotime($r['created_at'])); ?>
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
    });
</script>