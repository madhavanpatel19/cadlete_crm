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

// SOP counts – ensure tables exist and get totals
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_items` (`id` INT(11) AUTO_INCREMENT PRIMARY KEY, `category` VARCHAR(100) NOT NULL, `item_text` TEXT NOT NULL, `sort_order` INT(11) DEFAULT 0, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_checklist` (`id` INT(11) AUTO_INCREMENT PRIMARY KEY, `project_id` INT(11) NOT NULL, `sop_item_id` INT(11) NOT NULL, `is_checked` TINYINT(1) DEFAULT 0, `checked_by` VARCHAR(255) DEFAULT NULL, `checked_at` DATETIME DEFAULT NULL, UNIQUE KEY `unique_project_sop` (`project_id`, `sop_item_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$emp_sop_total_res = mysqli_query($con, "SELECT COUNT(*) as t FROM project_sop_items");
$emp_sop_total = $emp_sop_total_res ? (int)mysqli_fetch_assoc($emp_sop_total_res)['t'] : 0;

?>

<style>
    .timeline-visual-wrapper {
        position: relative;
        padding-left: 20px;
    }

    .timeline-vertical-line {
        position: absolute;
        left: 4px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
        z-index: 1;
    }

    .timeline-remark-item {
        position: relative;
        z-index: 2;
    }

    .timeline-dot {
        position: absolute;
        left: -32px;
        top: 15px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #cbd5e1;
        z-index: 3;
    }

    .remark-content-box {
        background: #fff;
        padding: 20px 25px;
        border-radius: 18px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        position: relative;
        transition: 0.3s;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        display: block;
    }

    .remark-content-box:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border-color: #eef2f6;
    }

    /* Speech bubble tail */
    .remark-content-box::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 15px;
        width: 15px;
        height: 15px;
        background: #fff;
        border-left: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        transform: rotate(45deg);
    }

    .remark-time-premium {
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .remark-text-premium {
        font-size: 14px;
        color: #334155;
        line-height: 1.6;
        word-break: break-word;
    }

    .id-badge-premium {
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 800;
        color: #94a3b8;
        font-size: 13px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 8px;
        display: inline-block;
    }

    .repo-tab {
        padding: 16px 0;
        font-weight: 700;
        font-size: 13px;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .repo-tab:hover {
        color: #0f172a;
    }

    .repo-tab.active {
        color: #dd2127;
    }

    .repo-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 3px;
        background: #dd2127;
        border-radius: 3px 3px 0 0;
    }

    .repo-tab i {
        font-size: 15px;
    }
</style>

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
                                <th style="text-align:center;">Expenses</th>
                                <th style="text-align:center;">SOP</th>
                                <th style="text-align:center;">FILES</th>
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
                                            <span class="id-badge-premium">#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></span>
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
                                        <td style="text-align: center;">
                                            <button type="button" onclick="openExpenseModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['project_name']); ?>')"
                                                style="font-weight: 800; color: #dd2127; font-size: 12px; cursor: pointer; background: #fff1f2; padding: 6px 14px; border-radius: 10px; border: 1px solid #fecdd3; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 1px 3px rgba(221, 33, 39, 0.06);" title="View Project Expenses">
                                                <span id="proj_exp_badge_<?php echo $row['id']; ?>">Expenses</span>
                                            </button>
                                        </td>
                                        <!-- SOP Checklist Column -->
                                        <?php
                                        $emp_sop_done_res = mysqli_query($con, "SELECT COUNT(*) as d FROM project_sop_checklist WHERE project_id=" . (int)$row['id'] . " AND is_checked=1");
                                        $emp_sop_done = $emp_sop_done_res ? (int)mysqli_fetch_assoc($emp_sop_done_res)['d'] : 0;
                                        $e_sop_color = ($emp_sop_done == $emp_sop_total && $emp_sop_total > 0) ? '#16a34a' : ($emp_sop_done > 0 ? '#7c3aed' : '#94a3b8');
                                        $e_sop_bg    = ($emp_sop_done == $emp_sop_total && $emp_sop_total > 0) ? '#f0fdf4' : ($emp_sop_done > 0 ? '#f5f3ff' : '#f8fafc');
                                        $e_sop_border = ($emp_sop_done == $emp_sop_total && $emp_sop_total > 0) ? '#bbf7d0' : ($emp_sop_done > 0 ? '#ede9fe' : '#e2e8f0');
                                        ?>
                                        <td style="text-align: center;">
                                            <button type="button"
                                                id="sop_badge_<?php echo $row['id']; ?>"
                                                onclick="openSopModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['project_name']); ?>')"
                                                style="font-weight: 800; color: <?php echo $e_sop_color; ?>; font-size: 13px; cursor: pointer; background: <?php echo $e_sop_bg; ?>; padding: 6px 14px; border-radius: 10px; border: 1px solid <?php echo $e_sop_border; ?>; display: inline-flex; align-items: center; gap: 7px; transition: 0.2s; box-shadow: 0 1px 3px rgba(124,58,237,0.07); min-width: 70px; justify-content: center;"
                                                title="Project SOP Checklist">
                                                <i class="fa fa-check-square-o" style="font-size: 13px;"></i>
                                                <span><?php echo $emp_sop_done; ?>/<?php echo $emp_sop_total; ?></span>
                                            </button>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-icon-premium btn-icon-folder" onclick="viewDocs(<?php echo $row['id']; ?>, 'documents')" title="Artifact Repository" style="background: #ffeaeb; color: #dd2127; border: 1px solid #ffeaeb; border-radius: 8px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s;">
                                                    <i class="fa fa-folder-open"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td style="text-align: center; padding: 15px;">
                                            <span style="padding: 6px 14px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: #ffeaeb; color: #dd2127; display: inline-block; min-width: 90px;">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; padding: 15px;">
                                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                                <button type="button" class="btn-icon-premium btn-icon-sm btn-icon-history toggle-detail-btn" title="View Activity Timeline">
                                                    <i class="fa fa-history"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr class="project-detail-row" style="display: none; background: #fff;">
                                        <td colspan="8" style="padding: 0; border: none;">
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
                                                                            $poster_badge_bg = $is_sys ? '#f1f5f9' : '#ffeaeb';
                                                                            $poster_badge_color = $is_sys ? '#64748b' : '#dd2127';
                                                                            $poster_icon = $is_sys ? 'fa-cog' : 'fa-user';
                                                                    ?>
                                                                            <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; width: 100%;">
                                                                                <div class="timeline-dot" style="left: 0;"></div>
                                                                                <div class="remark-content-box" style="padding-left: 20px;">
                                                                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                                                                        <span style="font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px; background: <?php echo $poster_badge_bg; ?>; color: <?php echo $poster_badge_color; ?>; display: inline-flex; align-items: center; gap: 5px;">
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
                                    <td colspan="9" style="text-align: center; padding: 60px 40px; color: #94a3b8;">
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

                        <?php if ($page > 1): ?>
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $page - 1; ?>" class="page-link"><i class="fa fa-chevron-left"></i> Prev</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $page + 1; ?>" class="page-link">Next <i class="fa fa-chevron-right"></i></a>
                        <?php endif; ?>
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
                    remark: remarkText,
                    user_type: 'employee'
                },
                dataType: 'json',
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i> Post Update');
                    if (response.success) {
                        const posterName = response.posted_by || '<?php echo htmlspecialchars($_SESSION['emp_name'] ?? "Employee"); ?>';
                        const newRemark = $(`
                        <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; display: none; width: 100%;">
                            <div class="timeline-dot" style="left: 0;"></div>
                            <div class="remark-content-box" style="padding-left: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px; background: #ffeaeb; color: #dd2127; display: inline-flex; align-items: center; gap: 5px;">
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

        // Expense Form Submission
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

        // Resource Link Form Submission
        $('#add-link-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();

            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_link.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        toggleAddResourceForm('link');
                        $('#add-link-form-unified')[0].reset();
                        refreshRepoContent();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Success', 'Link saved to repository', 'success');
                        } else {
                            alert('Link saved to repository');
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        });

        // Resource Document Form Submission
        $('#add-document-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_document.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.prop('disabled', false).html('Add Document');
                    if (response.success) {
                        toggleAddResourceForm('document');
                        $('#add-document-form-unified')[0].reset();
                        $('#file-name-label-unified').text('Choose file...');
                        refreshRepoContent();
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Success', 'Document archived', 'success');
                        } else {
                            alert('Document archived');
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        });

        $('#project_doc_input_unified').change(function() {
            const fileName = $(this).val().split('\\').pop();
            if (fileName) $('#file-name-label-unified').text(fileName).css('color', '#4f46e5');
        });
    });

    // Global helper functions attached to window
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
                            <td style="text-align:center; font-weight:600; color:#475569; font-size:13px; padding:12px 14px;">${sym} ${exp.formatted_cost}</td>
                            <td style="text-align:center; font-weight:700; color:#dd2127; font-size:13px; padding:12px 14px;">${sym} ${exp.formatted_total}</td>
                            <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${ordHtml}</td>
                            <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${exp.paid_by ? htmlEscapeExp(exp.paid_by) : '-'}</td>
                            <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${invHtml}</td>
                            <td style="text-align:center; font-size:12px; padding:12px 14px;">${attHtml}</td>
                            <td style="text-align:center; padding:12px 14px;">
                                <button type="button" class="btn-delete-expense-item" data-id="${exp.id}" data-project-id="${projectId}" onclick="deleteProjectExpense(${exp.id}, ${projectId})" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:14px; padding:4px 8px;" title="Delete Expense"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>`;
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

    let currentProjectIdRepo = 0;
    let currentRepoTab = 'documents';

    const mediaDefinitions = {
        project_images: [{
                name: '3D CAD Images and Line Drawings',
                spec: 'From Drive'
            },
            {
                name: 'Final Product',
                spec: 'High Resolution'
            },
            {
                name: 'Photorealistic Renders from AI',
                spec: 'AI Renders'
            },
            {
                name: 'Work in Progress Images + Team photo with Final Product',
                spec: 'WIP + Team Photo'
            }
        ],
        social_media: [{
                name: 'Case Study Image/Portfolio Image',
                spec: '3840x2560'
            },
            {
                name: 'Cover Page Image for Instagram',
                spec: '4:5 Ratio'
            },
            {
                name: 'Carousel Images for Insta/Linkedin',
                spec: '4:5 Ratio'
            },
            {
                name: 'Square Portfolio Image',
                spec: '1:1 Ratio'
            },
            {
                name: 'Horizontal Portfolio Image',
                spec: '3403x1914'
            },
            {
                name: 'Cover Image for Portfolio',
                spec: '1280x769'
            },
            {
                name: 'Product Reel - 1 (Detailed)',
                spec: 'Instagram Reel'
            },
            {
                name: 'Product Reel - 1 (Very Short)',
                spec: 'Instagram Reel'
            }
        ]
    };

    window.populateMediaAssetSelect = function(category, selectedName = '') {
        const select = $('#media_asset_select');
        select.empty();
        const list = mediaDefinitions[category] || [];
        list.forEach(function(item) {
            const isSelected = (selectedName && selectedName.trim().toLowerCase() === item.name.trim().toLowerCase()) ? 'selected' : '';
            select.append(`<option value="${item.name}" data-spec="${item.spec}" ${isSelected}>${item.name} (${item.spec})</option>`);
        });
        syncMediaSpecFromOption(select[0]);
    };

    window.syncMediaSpecFromOption = function(selectEl) {
        const selectedOpt = $(selectEl).find('option:selected');
        const spec = selectedOpt.data('spec') || '';
        $('#media_dimension_spec_unified').val(spec);
    };

    window.switchRepoTab = function(tabName) {
        currentRepoTab = tabName;
        $('.repo-tab').removeClass('active');
        $(`#tab-${tabName}`).addClass('active');

        $('#btn-add-artifact').toggle(tabName === 'documents');
        $('#btn-add-link').toggle(tabName === 'links');
        $('#btn-add-project-image').toggle(tabName === 'project_images');
        $('#btn-add-social-post').toggle(tabName === 'social_media');

        $('#resource-forms-container').hide();
        refreshRepoContent();
    };

    window.toggleAddResourceForm = function(type) {
        const container = $('#resource-forms-container');
        const formDoc = $('#add-document-form-unified');
        const formLink = $('#add-link-form-unified');
        const formMedia = $('#add-media-form-unified');

        if (container.is(':visible')) {
            container.slideUp(300);
        } else {
            $('.resource-form').hide();
            if (type === 'link' || currentRepoTab === 'links') {
                formLink.show();
            } else if (type === 'project_images' || type === 'social_media' || currentRepoTab === 'project_images' || currentRepoTab === 'social_media') {
                const cat = (type === 'project_images' || type === 'social_media') ? type : currentRepoTab;
                $('#media_project_id_unified').val(currentProjectIdRepo);
                $('#media_category_unified').val(cat);
                populateMediaAssetSelect(cat);
                formMedia.show();
            } else {
                formDoc.show();
            }
            container.slideDown(300);
        }
    };

    window.openMediaUploadModal = function(category, slotName = '', spec = '') {
        currentRepoTab = category;
        $('.repo-tab').removeClass('active');
        $(`#tab-${category}`).addClass('active');

        $('#media_project_id_unified').val(currentProjectIdRepo);
        $('#media_category_unified').val(category);
        populateMediaAssetSelect(category, slotName);

        if (spec) {
            $('#media_dimension_spec_unified').val(spec);
        }

        $('.resource-form').hide();
        $('#add-media-form-unified').show();
        $('#resource-forms-container').slideDown(300);

        $('#btn-add-artifact').hide();
        $('#btn-add-link').hide();
        $('#btn-add-project-image').toggle(category === 'project_images');
        $('#btn-add-social-post').toggle(category === 'social_media');
    };

    function refreshRepoContent() {
        $('#docs-list-container').html('<div class="spinner-premium" style="margin: 30px auto;"></div>');
        let url = '';
        let data = {
            project_id: currentProjectIdRepo,
            user_type: 'employee'
        };

        if (currentRepoTab === 'documents') {
            url = '../admin_area/ajax/projects/ajax_view_project_documents.php';
        } else if (currentRepoTab === 'links') {
            url = '../admin_area/ajax/projects/ajax_view_project_links.php';
        } else if (currentRepoTab === 'project_images' || currentRepoTab === 'social_media') {
            url = '../admin_area/ajax/projects/ajax_view_project_media.php';
            data.category = currentRepoTab;
        }

        $.ajax({
            url: url,
            method: 'GET',
            data: data,
            success: function(response) {
                $('#docs-list-container').html(response);
            },
            error: function() {
                $('#docs-list-container').html('<div style="text-align:center; padding:30px; color:#ef4444;">Failed to load resources.</div>');
            }
        });
    }

    $(document).ready(function() {
        // Document Submission
        $('#add-document-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_document.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.prop('disabled', false).html('Add Document');
                    if (response.success) {
                        $('#resource-forms-container').slideUp(300);
                        $('#add-document-form-unified')[0].reset();
                        $('#file-name-label-unified').text('Choose file...');
                        refreshRepoContent();
                        showPremiumAlert('Document archived');
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        });

        // Link Submission
        $('#add-link-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_link.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        $('#resource-forms-container').slideUp(300);
                        $('#add-link-form-unified')[0].reset();
                        refreshRepoContent();
                        showPremiumAlert('Link saved to repository');
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        });

        // Media Asset Submission
        $('#add-media-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: '../admin_area/ajax/projects/ajax_add_project_media.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        $('#resource-forms-container').slideUp(300);
                        $('#add-media-form-unified')[0].reset();
                        $('#media-file-name-label').text('Choose file...');
                        refreshRepoContent();
                        showPremiumAlert(response.message || 'Media asset saved');
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                },
                error: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Failed to save media asset', 'error');
                    } else {
                        alert('Failed to save media asset');
                    }
                }
            });
        });
    });

    window.viewDocs = function(id, initialTab = 'documents') {
        currentProjectIdRepo = id;
        $('#doc_project_id_unified').val(id);
        $('#link_project_id_unified').val(id);
        $('#media_project_id_unified').val(id);
        switchRepoTab(initialTab);
        $('#viewDocumentsModal').modal('show');
    };

    window.deleteDoc = function(docId, projectId) {
        const doDeleteDoc = function() {
            const url = currentRepoTab === 'documents' ? '../admin_area/ajax/projects/ajax_delete_project_document.php' : '../admin_area/ajax/projects/ajax_delete_project_link.php';
            const data = currentRepoTab === 'documents' ? {
                doc_id: docId
            } : {
                link_id: docId
            };

            $.ajax({
                url: url,
                method: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        refreshRepoContent();
                        showPremiumAlert('Resource removed');
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Confirm Removal',
                text: "This resource will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    doDeleteDoc();
                }
            });
        } else {
            if (confirm('Are you sure you want to delete this resource?')) {
                doDeleteDoc();
            }
        }
    };

    window.deleteMediaAsset = function(assetId) {
        const doDeleteMedia = function() {
            $.ajax({
                url: '../admin_area/ajax/projects/ajax_delete_project_media.php',
                method: 'POST',
                data: {
                    asset_id: assetId
                },
                success: function(response) {
                    if (response.success) {
                        refreshRepoContent();
                        showPremiumAlert('Media asset removed');
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', response.message, 'error');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    }
                }
            });
        };

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Confirm Removal',
                text: "This media asset will be removed from this slot.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    doDeleteMedia();
                }
            });
        } else {
            if (confirm('Are you sure you want to remove this media asset?')) {
                doDeleteMedia();
            }
        }
    };

    window.handleDirectMediaUpload = function(inputEl, category, assetName, spec) {
        if (!inputEl.files || inputEl.files.length === 0) return;

        const files = inputEl.files;
        const formData = new FormData();
        formData.append('project_id', currentProjectIdRepo);
        formData.append('category', category);
        formData.append('asset_name', assetName);
        formData.append('dimension_spec', spec);

        for (let i = 0; i < files.length; i++) {
            formData.append('media_files[]', files[i]);
        }

        $('#docs-list-container').prepend('<div id="media-uploading-bar" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; padding:10px 16px; border-radius:10px; margin-bottom:15px; font-weight:700; font-size:13px; display:flex; align-items:center; gap:8px;"><i class="fa fa-spinner fa-spin"></i> Uploading ' + files.length + ' file(s)...</div>');

        $.ajax({
            url: '../admin_area/ajax/projects/ajax_add_project_media.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $('#media-uploading-bar').remove();
                if (res.success) {
                    refreshRepoContent();
                    showPremiumAlert(res.message || 'Files uploaded successfully');
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Upload Error', res.message || 'Could not upload file.', 'error');
                    } else {
                        alert('Error: ' + res.message);
                    }
                }
            },
            error: function(xhr) {
                $('#media-uploading-bar').remove();
                let errMsg = 'Upload failed. Please check file format and try again.';
                if (xhr.responseText) {
                    try {
                        const parsed = JSON.parse(xhr.responseText);
                        if (parsed.message) errMsg = parsed.message;
                    } catch (e) {}
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Upload Error', errMsg, 'error');
                } else {
                    alert(errMsg);
                }
            }
        });

        inputEl.value = '';
    };

    window.promptDriveLinkForSlot = function(category, assetName, spec) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Add Drive / External Link',
                text: 'Paste link for ' + assetName,
                input: 'url',
                inputPlaceholder: 'https://drive.google.com/...',
                showCancelButton: true,
                confirmButtonText: 'Save Link',
                confirmButtonColor: '#dd2127',
                cancelButtonColor: '#64748b'
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const linkUrl = result.value.trim();
                    $.ajax({
                        url: '../admin_area/ajax/projects/ajax_add_project_media.php',
                        method: 'POST',
                        data: {
                            project_id: currentProjectIdRepo,
                            category: category,
                            asset_name: assetName,
                            dimension_spec: spec,
                            link_url: linkUrl
                        },
                        success: function(res) {
                            if (res.success) {
                                refreshRepoContent();
                                showPremiumAlert('Link attached successfully');
                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }
                        }
                    });
                }
            });
        } else {
            const linkUrl = prompt('Enter Drive or external URL for ' + assetName + ':');
            if (linkUrl) {
                $.ajax({
                    url: '../admin_area/ajax/projects/ajax_add_project_media.php',
                    method: 'POST',
                    data: {
                        project_id: currentProjectIdRepo,
                        category: category,
                        asset_name: assetName,
                        dimension_spec: spec,
                        link_url: linkUrl
                    },
                    success: function(res) {
                        if (res.success) {
                            refreshRepoContent();
                            showPremiumAlert('Link attached successfully');
                        } else {
                            alert(res.message);
                        }
                    }
                });
            }
        }
    };

    function showPremiumAlert(message) {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.position = 'fixed';
            container.style.bottom = '20px';
            container.style.right = '20px';
            container.style.zIndex = '999999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.background = '#1e293b';
        toast.style.color = '#fff';
        toast.style.padding = '16px 24px';
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.gap = '12px';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '600';
        toast.style.transform = 'translateY(100px) scale(0.9)';
        toast.style.opacity = '0';
        toast.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';

        toast.innerHTML = `
            <div style="width: 24px; height: 24px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fa fa-check" style="font-size: 12px;"></i>
            </div>
            ${message}
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateY(0) scale(1)';
            toast.style.opacity = '1';
        }, 50);

        setTimeout(() => {
            toast.style.transform = 'translateY(50px) scale(0.9)';
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 400);
        }, 3000);
    }
</script>

<!-- View Documents Modal (Unified Repository) -->
<div id="viewDocumentsModal" class="modal fade" role="dialog" style="z-index: 1055;">
    <div class="modal-dialog" style="margin: 40px auto; width: 92%; max-width: 960px;">
        <div class="modal-content premium-modal-content" style="border: none; border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden;">
            <div class="modal-header" style="background: #FFEAEB; color: #000; padding: 25px 30px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: #dd2127; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff;">
                            <i class="fa fa-folder-open"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 20px; letter-spacing: -0.5px; margin: 0;">Project Resource Hub</h4>
                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Centralized Project Assets</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px; margin-right: 40px;">
                        <button type="button" id="btn-add-artifact" class="btn-premium-add-inline" onclick="toggleAddResourceForm('document')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(221, 33, 39, 0.2);">
                            <i class="fa fa-upload"></i>
                            <span class="btn-text">Add Document</span>
                        </button>
                        <button type="button" id="btn-add-link" class="btn-premium-add-inline" onclick="toggleAddResourceForm('link')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: none; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(221, 33, 39, 0.2);">
                            <i class="fa fa-globe"></i> <span class="btn-text">Add Link</span>
                        </button>
                        <button type="button" id="btn-add-project-image" class="btn-premium-add-inline" onclick="toggleAddResourceForm('project_images')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: none; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(221, 33, 39, 0.2);">
                            <i class="fa fa-camera"></i> <span class="btn-text">Upload Image</span>
                        </button>
                        <button type="button" id="btn-add-social-post" class="btn-premium-add-inline" onclick="toggleAddResourceForm('social_media')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: none; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(221, 33, 39, 0.2);">
                            <i class="fa fa-share-alt"></i> <span class="btn-text">Upload Post / Reel</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-body" style="padding: 0; background: #fff;">
                <!-- Tab Navigation -->
                <div style="background: #f1f5f9; padding: 0 25px; display: flex; gap: 20px; border-bottom: 1px solid #e2e8f0; overflow-x: auto;">
                    <div class="repo-tab active" onclick="switchRepoTab('documents')" id="tab-documents">
                        <i class="fa fa-files-o"></i> Documents
                    </div>
                    <div class="repo-tab" onclick="switchRepoTab('links')" id="tab-links">
                        <i class="fa fa-link"></i> External Links
                    </div>
                    <div class="repo-tab" onclick="switchRepoTab('project_images')" id="tab-project_images">
                        <i class="fa fa-picture-o"></i> Project Images
                    </div>
                    <div class="repo-tab" onclick="switchRepoTab('social_media')" id="tab-social_media">
                        <i class="fa fa-share-alt"></i> Social Media Posts
                    </div>
                </div>

                <!-- Inline Resource Forms -->
                <div id="resource-forms-container" style="display: none; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 25px 30px; animation: slideDown 0.3s ease-out;">
                    <!-- Document Form -->
                    <form id="add-document-form-unified" method="POST" enctype="multipart/form-data" class="resource-form">
                        <input type="hidden" name="project_id" id="doc_project_id_unified">
                        <div class="row">
                            <div class="col-md-7">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Artifact Name</label>
                                    <input type="text" name="document_name" class="p-input-premium" placeholder="e.g. Design Spec" required style="height: 45px;">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Select File</label>
                                    <div class="file-upload-wrapper-premium-mini" style="position: relative; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 10px; text-align: center; background: #fff; transition: 0.3s;">
                                        <input type="file" name="project_doc" id="project_doc_input_unified" required style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;">
                                        <span id="file-name-label-unified" style="color: #64748b; font-weight: 600; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;">Choose file...</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="is_proposal" value="0">
                        </div>
                        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn-premium-cancel" onclick="toggleAddResourceForm()">Discard</button>
                            <button type="submit" class="btn-premium-add">Add Document</button>
                        </div>
                    </form>

                    <!-- Link Form -->
                    <form id="add-link-form-unified" method="POST" class="resource-form" style="display: none;">
                        <input type="hidden" name="project_id" id="link_project_id_unified">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Link Title</label>
                                    <input type="text" name="link_name" class="p-input-premium" placeholder="e.g. Figma Design" required style="height: 45px;">
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">URL (https://...)</label>
                                    <input type="url" name="link_url" class="p-input-premium" placeholder="https://www.figma.com/file/..." required style="height: 45px;">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn-premium-cancel" onclick="toggleAddResourceForm()">Discard</button>
                            <button type="submit" class="btn-premium-add">Add Link</button>
                        </div>
                    </form>

                    <!-- Media / Post Upload Form -->
                    <form id="add-media-form-unified" method="POST" enctype="multipart/form-data" class="resource-form" style="display: none;">
                        <input type="hidden" name="project_id" id="media_project_id_unified">
                        <input type="hidden" name="category" id="media_category_unified" value="project_images">
                        <input type="hidden" name="dimension_spec" id="media_dimension_spec_unified" value="">

                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 6px; display: block; font-size: 11px; text-transform: uppercase;">Select Deliverable Option <span style="color: #ef4444;">*</span></label>
                                    <select name="asset_name" id="media_asset_select" class="p-input-premium" required style="height: 45px; width: 100%; font-weight: 600;" onchange="syncMediaSpecFromOption(this)">
                                        <!-- Populated dynamically via JS -->
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 6px; display: block; font-size: 11px; text-transform: uppercase;">Choose File(s)</label>
                                    <div class="file-upload-wrapper-premium-mini" style="position: relative; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 10px; text-align: center; background: #fff; transition: 0.3s;">
                                        <input type="file" name="media_files[]" id="media_file_input_unified" multiple style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;" onchange="$('#media-file-name-label').text(this.files.length > 1 ? this.files.length + ' files selected' : (this.files[0] ? this.files[0].name : 'Choose file...'));">
                                        <span id="media-file-name-label" style="color: #64748b; font-weight: 600; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;">Choose file...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 6px; display: block; font-size: 11px; text-transform: uppercase;">OR Drive / Cloud Link</label>
                                    <input type="url" name="link_url" id="media_link_url_input" class="p-input-premium" placeholder="https://drive.google.com/..." style="height: 45px; font-size: 12px;">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 15px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn-premium-cancel" onclick="toggleAddResourceForm()">Discard</button>
                            <button type="submit" class="btn-premium-add">Upload Asset</button>
                        </div>
                    </form>
                </div>

                <div id="docs-list-container" style="max-height: 550px; overflow-y: auto; padding: 25px 30px;">
                    <!-- Documents will be loaded here -->
                    <div class="spinner-premium" style="margin: 50px auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                                </tr>
                            </thead>
                            <tbody id="project_expenses_table_body">
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #64748b;">Loading expenses...</td>
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

<!-- ===================== PROJECT SOP CHECKLIST MODAL (EMP) ===================== -->
<div class="modal fade" id="projectSopModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document" style="max-width: 700px; width: 96%; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 24px; border: none; overflow: hidden; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.35);">
            <div class="modal-header" style="background: #FFEAEB; color: #000; padding: 22px 28px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: #dd2127; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                            <i class="fa fa-check-square-o"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 20px; letter-spacing: -0.5px; margin: 0; color: #0f172a;">Project SOP Checklist</h4>
                            <p id="sop_modal_project_name" style="margin: 4px 0 0 0; font-size: 12px; color: #dd2127; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;"></p>
                        </div>
                    </div>
                    <div style="margin-left: auto; margin-right: 45px; text-align: center;">
                        <div id="sop_progress_ring_wrap" style="position: relative; width: 64px; height: 64px; margin: 0 auto;">
                            <svg width="64" height="64" style="transform: rotate(-90deg);">
                                <circle cx="32" cy="32" r="26" fill="none" stroke="#fecdd3" stroke-width="6" />
                                <circle id="sop_ring_fill" cx="32" cy="32" r="26" fill="none" stroke="#dd2127" stroke-width="6"
                                    stroke-dasharray="163.4" stroke-dashoffset="163.4"
                                    style="transition: stroke-dashoffset 0.6s ease; stroke-linecap: round;" />
                            </svg>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 13px; font-weight: 900; color: #dd2127;" id="sop_pct_label">0%</div>
                        </div>
                        <div id="sop_counter_label" style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 4px; white-space: nowrap;">0 / 0 done</div>
                    </div>
                </div>
            </div>
            <div class="modal-body" style="padding: 0; background: #fff; max-height: 65vh; overflow-y: auto;">
                <div id="sop_checklist_body" style="padding: 24px 28px;">
                    <div style="text-align: center; padding: 50px 0; color: #94a3b8;">
                        <i class="fa fa-spinner fa-spin" style="font-size: 28px;"></i>
                        <p style="margin-top: 12px; font-weight: 600;">Loading checklist...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 28px; background: #f8fafc; border: none;">
                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .sop-section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 12px;
        margin-bottom: 12px;
        margin-top: 8px;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .sop-item-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 6px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        transition: 0.25s;
        cursor: pointer;
    }

    .sop-item-row:hover {
        background: #f5f3ff;
        border-color: #ede9fe;
    }

    .sop-item-row.sop-checked {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .sop-item-row.sop-checked .sop-item-text {
        text-decoration: line-through;
        color: #94a3b8;
    }

    .sop-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid #cbd5e1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 1px;
        transition: 0.2s;
    }

    .sop-checked .sop-checkbox {
        background: #16a34a;
        border-color: #16a34a;
    }

    .sop-item-text {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        line-height: 1.5;
        flex: 1;
    }

    .sop-checked-by {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 2px;
    }
</style>

<script>
    var SOP_CAT_CONFIG_EMP = {
        'SETUP': {
            color: '#1d4ed8',
            bg: '#eff6ff',
            border: '#bfdbfe',
            icon: 'fa-cog'
        },
        'EXECUTION': {
            color: '#b45309',
            bg: '#fff7ed',
            border: '#fed7aa',
            icon: 'fa-wrench'
        },
        'COMPLETION': {
            color: '#065f46',
            bg: '#ecfdf5',
            border: '#a7f3d0',
            icon: 'fa-flag'
        },
        'MARKETING': {
            color: '#9d174d',
            bg: '#fdf2f8',
            border: '#f9a8d4',
            icon: 'fa-bullhorn'
        },
    };

    window.openSopModal = function(projectId, projectName) {
        $('#sop_modal_project_name').text(projectName);
        $('#sop_checklist_body').html('<div style="text-align:center;padding:50px 0;color:#94a3b8;"><i class="fa fa-spinner fa-spin" style="font-size:28px;"></i><p style="margin-top:12px;font-weight:600;">Loading checklist...</p></div>');
        $('#projectSopModal').modal('show');
        $.ajax({
            url: '../admin_area/ajax/projects/ajax_get_project_sop.php',
            method: 'GET',
            data: {
                project_id: projectId
            },
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    $('#sop_checklist_body').html('<p style="color:red;padding:20px;">Error loading checklist.</p>');
                    return;
                }
                _empRenderSop(res);
            },
            error: function() {
                $('#sop_checklist_body').html('<p style="color:red;padding:20px;">Network error.</p>');
            }
        });
    };

    function _empUpdateProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        var offset = 163.4 - (pct / 100) * 163.4;
        $('#sop_ring_fill').attr('stroke-dashoffset', offset);
        var col = (done === total && total > 0) ? '#16a34a' : '#7c3aed';
        $('#sop_ring_fill').attr('stroke', col);
        $('#sop_pct_label').css('color', col).text(pct + '%');
        $('#sop_counter_label').text(done + ' / ' + total + ' done');
    }

    function _empRenderSop(res) {
        _empUpdateProgress(res.completed, res.total);
        var badgeEl = $('#sop_badge_' + res.project_id);
        if (badgeEl.length) {
            badgeEl.find('span').text(res.completed + '/' + res.total);
            var c = (res.completed === res.total && res.total > 0) ? '#16a34a' : (res.completed > 0 ? '#7c3aed' : '#94a3b8');
            var bg = (res.completed === res.total && res.total > 0) ? '#f0fdf4' : (res.completed > 0 ? '#f5f3ff' : '#f8fafc');
            var br = (res.completed === res.total && res.total > 0) ? '#bbf7d0' : (res.completed > 0 ? '#ede9fe' : '#e2e8f0');
            badgeEl.css({
                color: c,
                background: bg,
                'border-color': br
            });
        }
        var html = '';
        var catOrder = ['SETUP', 'EXECUTION', 'COMPLETION', 'MARKETING'];
        catOrder.forEach(function(cat) {
            if (!res.categories[cat] || res.categories[cat].length === 0) return;
            var cfg = SOP_CAT_CONFIG_EMP[cat] || {
                color: '#475569',
                bg: '#f8fafc',
                border: '#e2e8f0',
                icon: 'fa-list'
            };
            var done = res.categories[cat].filter(function(i) {
                return i.is_checked;
            }).length;
            html += '<div class="sop-section-header" style="background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';color:' + cfg.color + ';">' +
                '<i class="fa ' + cfg.icon + '"></i><span>' + cat + '</span>' +
                '<span style="margin-left:auto;font-size:10px;opacity:0.8;">' + done + '/' + res.categories[cat].length + '</span></div>';
            res.categories[cat].forEach(function(item) {
                var checked = item.is_checked ? 'sop-checked' : '';
                var chkIcon = item.is_checked ? '<i class="fa fa-check" style="color:#fff;font-size:11px;"></i>' : '';
                var byText = item.is_checked && item.checked_by ? '<div class="sop-checked-by"><i class="fa fa-user"></i> ' + $('<div>').text(item.checked_by).html() + '</div>' : '';
                html += '<div class="sop-item-row ' + checked + '" data-item-id="' + item.id + '" onclick="_empToggleSop(' + res.project_id + ',' + item.id + ',this)">' +
                    '<div class="sop-checkbox">' + chkIcon + '</div>' +
                    '<div style="flex:1;"><div class="sop-item-text">' + $('<div>').text(item.text).html() + '</div>' + byText + '</div></div>';
            });
            html += '<div style="height:8px;"></div>';
        });
        $('#sop_checklist_body').html(html);
    }

    window._empToggleSop = function(projectId, itemId, el) {
        var $row = $(el);
        var isNow = !$row.hasClass('sop-checked') ? 1 : 0;
        if (isNow) {
            $row.addClass('sop-checked');
            $row.find('.sop-checkbox').html('<i class="fa fa-check" style="color:#fff;font-size:11px;"></i>');
        } else {
            $row.removeClass('sop-checked').find('.sop-checkbox').html('');
            $row.find('.sop-checked-by').remove();
        }
        $.ajax({
            url: '../admin_area/ajax/projects/ajax_toggle_sop_item.php',
            method: 'POST',
            data: {
                project_id: projectId,
                sop_item_id: itemId,
                is_checked: isNow,
                portal: 'employee'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    if (isNow && res.checked_by) {
                        $row.find('.sop-checked-by').remove();
                        $row.find('.flex-1, div[style*="flex:1"]').first().append(
                            '<div class="sop-checked-by"><i class="fa fa-user"></i> ' + $('<div>').text(res.checked_by).html() + '</div>'
                        );
                    }
                    _empUpdateProgress(res.completed, res.total);
                    var $sec = $row.prevAll('.sop-section-header').first();
                    if ($sec.length) {
                        var $items = $sec.nextUntil('.sop-section-header').filter('.sop-item-row');
                        $sec.find('span').last().text($items.filter('.sop-checked').length + '/' + $items.length);
                    }
                    var badgeEl = $('#sop_badge_' + projectId);
                    if (badgeEl.length) {
                        badgeEl.find('span').text(res.completed + '/' + res.total);
                        var c = (res.completed === res.total && res.total > 0) ? '#16a34a' : (res.completed > 0 ? '#7c3aed' : '#94a3b8');
                        var bg = (res.completed === res.total && res.total > 0) ? '#f0fdf4' : (res.completed > 0 ? '#f5f3ff' : '#f8fafc');
                        var br = (res.completed === res.total && res.total > 0) ? '#bbf7d0' : (res.completed > 0 ? '#ede9fe' : '#e2e8f0');
                        badgeEl.css({
                            color: c,
                            background: bg,
                            'border-color': br
                        });
                    }

                    // ── Auto-Completed: update status badge + toast ──
                    if (res.auto_completed) {
                        // Find the table row containing this project's SOP badge and update its status span
                        $('tbody tr').each(function() {
                            if ($(this).find('#sop_badge_' + projectId).length > 0) {
                                $(this).find('td span').each(function() {
                                    var t = $(this).text().trim().toLowerCase();
                                    if (t === 'active' || t === 'pending' || t === 'completed') {
                                        $(this).text('Completed').css({
                                            'background': '#ecfdf5',
                                            'color': '#059669',
                                            'border': '1px solid #a7f3d0'
                                        });
                                    }
                                });
                            }
                        });

                        // Show SweetAlert celebration toast
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: '🎉 SOP Complete!',
                                html: '<b>All checklist items done!</b><br>Project has been automatically set to <b>Completed</b>.',
                                timer: 4000,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                position: 'top-end',
                                toast: true
                            });
                        }

                        // Refresh employee notification bell
                        if (typeof loadUserNotifications === 'function') {
                            setTimeout(function() {
                                loadUserNotifications();
                            }, 800);
                        }
                    }
                }
            }
        });
    };
</script>