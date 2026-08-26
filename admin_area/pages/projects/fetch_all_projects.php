<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once(__DIR__ . '/../../includes/admin_permissions.php');
}

// Always read admin status fresh from DB (bypass session cache)
$current_admin_id_proj = 0;
$is_super_admin_proj = false;
if (isset($_SESSION['admin_email'])) {
    $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $r = mysqli_query($con, "SELECT admin_id, is_super_admin FROM admins WHERE admin_email='$email_esc' LIMIT 1");
    if ($r && $row_a = mysqli_fetch_assoc($r)) {
        $current_admin_id_proj = (int)$row_a['admin_id'];
        $is_super_admin_proj   = !empty($row_a['is_super_admin']);
    }
}

// If NOT super admin, restrict to projects assigned to this admin ONLY IF they have the restriction permission
$admin_project_filter = '';
if (!$is_super_admin_proj && $current_admin_id_proj > 0) {
    if (canAdminAccess('project_assigned_only') || canAdminAccess('subadmin_assigned_project_only')) {
        $admin_project_filter = " AND (FIND_IN_SET('$current_admin_id_proj', REPLACE(cp.assigned_admins, ' ', '')) > 0) ";
    }
}

$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$source_filter = isset($_GET['source']) ? mysqli_real_escape_string($con, $_GET['source']) : '';
$cost_filter   = isset($_GET['cost']) ? mysqli_real_escape_string($con, $_GET['cost']) : '';
$search_filter = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

$where_clause = " WHERE cp.deleted_at IS NULL AND c.deleted_at IS NULL $admin_project_filter ";
if ($status_filter !== "") {
    $where_clause .= " AND cp.status='$status_filter' ";
}
if ($source_filter !== "") {
    $where_clause .= " AND cp.source LIKE '%$source_filter%' ";
}
if ($search_filter !== "") {
    $search_clean = trim($search_filter);
    $search_clean = ltrim($search_clean, '#');

    $emp_id_matches = [];
    $get_matching_emps = mysqli_query($con, "SELECT id FROM emp_list WHERE name LIKE '%$search_clean%'");
    if ($get_matching_emps && mysqli_num_rows($get_matching_emps) > 0) {
        while ($e_row = mysqli_fetch_assoc($get_matching_emps)) {
            $emp_id_matches[] = (int)$e_row['id'];
        }
    }

    $emp_where = "";
    if (!empty($emp_id_matches)) {
        $emp_conditions = [];
        foreach ($emp_id_matches as $e_id) {
            $emp_conditions[] = "FIND_IN_SET('$e_id', REPLACE(cp.assigned_employees, ' ', '')) > 0";
        }
        $emp_where = " OR " . implode(" OR ", $emp_conditions);
    }

    $full_match = "(cp.project_name LIKE '%$search_clean%' OR c.name LIKE '%$search_clean%' OR cp.id LIKE '%$search_clean%' OR cp.source LIKE '%$search_clean%' OR cp.status LIKE '%$search_clean%' $emp_where)";

    $words = array_filter(explode(' ', $search_clean), function ($w) {
        return strlen(trim($w)) > 1;
    });

    if (count($words) > 1) {
        $word_clauses = [];
        foreach ($words as $w) {
            $w_esc = mysqli_real_escape_string($con, $w);
            $word_clauses[] = "(cp.project_name LIKE '%$w_esc%' OR c.name LIKE '%$w_esc%' OR cp.id LIKE '%$w_esc%' OR cp.source LIKE '%$w_esc%' OR cp.status LIKE '%$w_esc%')";
        }
        $all_words_clause = "(" . implode(" AND ", $word_clauses) . ")";
        $where_clause .= " AND ($full_match OR $all_words_clause) ";
    } else {
        $where_clause .= " AND $full_match ";
    }
}

$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$order_by = " ORDER BY cp.id DESC ";
if ($cost_filter === 'high_to_low') {
    $order_by = " ORDER BY CAST(cp.budget AS DECIMAL(15,2)) DESC, cp.id DESC ";
} elseif ($cost_filter === 'low_to_high') {
    $order_by = " ORDER BY CAST(cp.budget AS DECIMAL(15,2)) ASC, cp.id DESC ";
}

$get_projects = "SELECT cp.*, c.name as client_name, c.image FROM client_projects cp JOIN clients c ON cp.client_id = c.id $where_clause $order_by LIMIT $offset, $limit";
$run_projects = mysqli_query($con, $get_projects);

// Automatic Fallback: If current offset returned 0 rows but matching projects exist in DB, query from offset 0
if ($run_projects && mysqli_num_rows($run_projects) == 0 && ($search_filter !== "" || $status_filter !== "" || $source_filter !== "" || $cost_filter !== "" || $page > 1)) {
    $get_projects = "SELECT cp.*, c.name as client_name, c.image FROM client_projects cp JOIN clients c ON cp.client_id = c.id $where_clause $order_by LIMIT 0, $limit";
    $run_projects = mysqli_query($con, $get_projects);
}


if (!$run_projects) {
    die('<div class="alert alert-danger" style="margin: 20px; border-radius: 12px; border: none; background: #fee2e2; color: #991b1b; font-weight: 600;">
            <i class="fa fa-exclamation-triangle"></i> Database Error: ' . mysqli_error($con) . '
        </div>');
}

if (mysqli_num_rows($run_projects) > 0) {
    // Ensure SOP tables exist (auto-create)
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_items` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `category` VARCHAR(100) NOT NULL,
        `item_text` TEXT NOT NULL,
        `sort_order` INT(11) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_checklist` (
        `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT(11) NOT NULL,
        `sop_item_id` INT(11) NOT NULL,
        `is_checked` TINYINT(1) DEFAULT 0,
        `checked_by` VARCHAR(255) DEFAULT NULL,
        `checked_at` DATETIME DEFAULT NULL,
        UNIQUE KEY `unique_project_sop` (`project_id`, `sop_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Get total SOP items count once
    $sop_total_res = mysqli_query($con, "SELECT COUNT(*) as t FROM project_sop_items");
    $sop_total = $sop_total_res ? (int)mysqli_fetch_assoc($sop_total_res)['t'] : 0;

    while ($p = mysqli_fetch_assoc($run_projects)) {
        $project_id = $p['id'];
        $project_date = !empty($p['project_date']) ? date('M d, Y', strtotime($p['project_date'])) : 'NA';
        $source = isset($p['source']) ? $p['source'] : '';
        //employee names
        $empIds = explode(",", $p['assigned_employees']);
        $budget = floatval($p['budget']);
        $currency = !empty($p['currency']) ? $p['currency'] : 'INR';
        $symbols = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'د.إ'];
        $sym = isset($symbols[$currency]) ? $symbols[$currency] : '₹';
        // SOP count for this project
        $sop_done_res = mysqli_query($con, "SELECT COUNT(*) as d FROM project_sop_checklist WHERE project_id=$project_id AND is_checked=1");
        $sop_done = $sop_done_res ? (int)mysqli_fetch_assoc($sop_done_res)['d'] : 0;
?>
        <tr style="transition: 0.3s;">
            <td style="text-align: center;">
                <span class="id-badge-premium">#<?php echo str_pad($project_id, 3, '0', STR_PAD_LEFT); ?></span>
            </td>
            <td>
                <div style="display:flex; align-items:center; gap:12px;">
                    <?php if (!empty($p['project_image']) && file_exists('../../uploads/project_images/' . $p['project_image'])) { ?>
                        <img src="uploads/project_images/<?php echo htmlspecialchars($p['project_image']); ?>"
                            style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #e2e8f0;">
                    <?php } else if (!empty($p['image']) && file_exists('../../uploads/client_images/' . $p['image'])) { ?>
                        <img src="uploads/client_images/<?php echo htmlspecialchars($p['image']); ?>"
                            style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #e2e8f0;">
                    <?php } else { ?>
                        <div style="width:40px;height:40px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-weight:600;color:#64748b;">
                            <?php echo strtoupper(substr($p['project_name'], 0, 1)); ?>
                        </div>
                    <?php } ?>

                    <!-- Project Details -->
                    <div>
                        <div style="font-weight:800;color:#0f172a;font-size:15px;letter-spacing:-0.3px;">
                            <?php echo htmlspecialchars($p['project_name']); ?>
                        </div>
                        <div style="font-weight:600;color:#64748b;font-size:12px;margin-top:3px;">
                            <i class="fa fa-user"></i>
                            <?php echo htmlspecialchars($p['client_name']); ?>
                        </div>
                    </div>
                </div>
            </td>
            <td>
                <div class="employee-wrap">
                    <?php
                    $limit = 3;

                    $validEmpIds = array_filter($empIds, function ($id) {
                        return !empty(trim($id));
                    });

                    echo '<div class="employee-group">';

                    $i = 0;
                    foreach ($validEmpIds as $empId) {
                        $query = mysqli_query($con, "SELECT employee_image,name FROM emp_list WHERE id='" . intval($empId) . "'");
                        if ($query) {
                            $emp = mysqli_fetch_assoc($query);
                            if ($emp) {
                                $isHidden = $i >= $limit ? 'display: none;' : '';
                                $hiddenClass = $i >= $limit ? 'hidden-employee' : '';
                                $empName = htmlspecialchars($emp['name'] ?? '');

                                if (!empty($emp['employee_image'])) {
                                    echo '<span class="emp-avatar-item ' . $hiddenClass . '" data-tooltip="' . $empName . '" style="' . $isHidden . '">';
                                    echo '<img src="uploads/' . htmlspecialchars($emp['employee_image']) . '">';
                                    echo '</span>';
                                } else {
                                    $initial = strtoupper(substr($emp['name'] ?? 'U', 0, 1));
                                    echo '<span class="emp-avatar-item ' . $hiddenClass . '" data-tooltip="' . $empName . '" style="' . $isHidden . '">';
                                    echo '<div class="emp-initial">' . $initial . '</div>';
                                    echo '</span>';
                                }
                                $i++;
                            }
                        }
                    }

                    if ($i > $limit) {
                        echo '<span class="more emp-avatar-item" data-tooltip="Show all" onclick="this.parentElement.querySelectorAll(\'.hidden-employee\').forEach(el => el.style.display = \'inline-flex\'); this.style.display = \'none\';">+' . ($i - $limit) . '</span>';
                    }

                    echo '</div>';
                    ?>
                </div>
            </td>
            <?php if (canAdminAccess('project_source_view')): ?>
                <td style="text-align: center;">
                    <span style="font-size: 12px; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 6px; width: 90px; display: inline-block; white-space: normal; word-wrap: break-word;"><?php echo htmlspecialchars($source ?: '-'); ?></span>
                </td>
            <?php endif; ?>
            <td style="text-align: center;">
                <button type="button" onclick="openExpenseModal(<?php echo $project_id; ?>, '<?php echo addslashes($p['project_name']); ?>')"
                    style="font-weight: 800; color: #dd2127; font-size: 12px; cursor: pointer; background: #fff1f2; padding: 6px 14px; border-radius: 10px; border: 1px solid #fecdd3; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 1px 3px rgba(221, 33, 39, 0.06);" title="View Project Expenses">
                    <i class="fa fa-receipt" style="color: #dd2127;"></i>
                    <span id="proj_exp_badge_<?php echo $project_id; ?>">Expenses</span>
                </button>
            </td>
            <td style="text-align: center;">
                <?php if (canAdminAccess('budget_view')): ?>
                    <span class="budget-badge-trigger" onclick="openBudgetModal(<?php echo $project_id; ?>, '<?php echo addslashes($p['project_name']); ?>', <?php echo $budget; ?>, '<?php echo $currency; ?>')"
                        style="font-weight: 900; color: #16a34a; font-size: 13px; letter-spacing: -0.2px; cursor: pointer; background: #f0fdf4; padding: 7px 14px; border-radius: 12px; border: 1px solid #dcfce7; display: inline-flex; align-items: center; justify-content: center; min-width: 115px; transition: 0.2s; box-shadow: 0 2px 4px rgba(22, 163, 74, 0.05);">
                        <span style="opacity: 0.6; margin-right: 4px;"><?php echo $sym; ?></span> <?php echo number_format($budget, 0); ?>
                    </span>
                <?php else: ?>
                    <span style="font-weight: 700; color: #94a3b8; font-size: 12px;"><i class="fa fa-lock"></i></span>
                <?php endif; ?>
            </td>
            <!-- SOP Checklist Column -->
            <td style="text-align: center;">
                <?php
                $sop_pct = ($sop_total > 0) ? round(($sop_done / $sop_total) * 100) : 0;
                $sop_color = ($sop_done == $sop_total && $sop_total > 0) ? '#16a34a' : (($sop_done > 0) ? '#7c3aed' : '#94a3b8');
                $sop_bg    = ($sop_done == $sop_total && $sop_total > 0) ? '#f0fdf4' : (($sop_done > 0) ? '#f5f3ff' : '#f8fafc');
                $sop_border= ($sop_done == $sop_total && $sop_total > 0) ? '#bbf7d0' : (($sop_done > 0) ? '#ede9fe' : '#e2e8f0');
                ?>
                <button type="button"
                    id="sop_badge_<?php echo $project_id; ?>"
                    onclick="openSopModal(<?php echo $project_id; ?>, '<?php echo addslashes($p['project_name']); ?>')"
                    style="font-weight: 800; color: <?php echo $sop_color; ?>; font-size: 13px; cursor: pointer; background: <?php echo $sop_bg; ?>; padding: 6px 14px; border-radius: 10px; border: 1px solid <?php echo $sop_border; ?>; display: inline-flex; align-items: center; gap: 7px; transition: 0.2s; box-shadow: 0 1px 3px rgba(124,58,237,0.07); min-width: 70px; justify-content: center;"
                    title="Project SOP Checklist">
                    <i class="fa fa-check-square-o" style="font-size: 13px;"></i>
                    <span><?php echo $sop_done; ?>/<?php echo $sop_total; ?></span>
                </button>
            </td>
            <td style="text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <button class="btn-icon-premium btn-icon-folder" onclick="viewDocs(<?php echo $project_id; ?>, 'documents')" title="Artifact Repository">
                        <i class="fa fa-folder-open"></i>
                    </button>
                </div>
            </td>
            <td style="text-align: center;">
                <?php
                $status = !empty($p['status']) ? $p['status'] : 'Active';
                $color_map = ['Active' => '#16a34a', 'Pending' => '#ca8a04', 'Completed' => '#2563eb'];
                $bg_map = ['Active' => '#f0fdf4', 'Pending' => '#fefce8', 'Completed' => '#eff6ff'];
                $border_map = ['Active' => '#dcfce7', 'Pending' => '#fef9c3', 'Completed' => '#dbeafe'];
                $current_color = isset($color_map[$status]) ? $color_map[$status] : '#475569';
                $current_bg = isset($bg_map[$status]) ? $bg_map[$status] : '#f8fafc';
                $current_border = isset($border_map[$status]) ? $border_map[$status] : '#e2e8f0';
                ?>
                <select class="project-status-select" data-project-id="<?php echo $project_id; ?>"
                    style="appearance: none; -webkit-appearance: none; background: <?php echo $current_bg; ?> url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22<?php echo urlencode($current_color); ?>%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E') no-repeat right 12px center; color: <?php echo $current_color; ?>; border: 1px solid <?php echo $current_border; ?>; font-size: 10px; font-weight: 900; text-transform: uppercase; padding: 7px 32px 7px 15px; border-radius: 20px; letter-spacing: 0.8px; cursor: pointer; outline: none; transition: all 0.3s ease; width: auto; min-width: 125px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                    <option value="Active" <?php if ($status == 'Active') echo 'selected'; ?>>Active</option>
                    <option value="Pending" <?php if ($status == 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Completed" <?php if ($status == 'Completed') echo 'selected'; ?>>Completed</option>
                </select>
            </td>
            <td style="text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <?php if (canAdminAccess('project_assign_task')): ?>
                        <button class="btn-icon-premium btn-icon-sm btn-icon-warning" onclick="window.location.href='index.php?team_todo&project_id=<?php echo $project_id; ?>'" title="Team To-Do">
                            <i class="fa fa-list-alt"></i>
                        </button>
                    <?php endif; ?>
                    <button class="btn-icon-premium btn-icon-sm btn-icon-history btn-toggle-history" title="View History">
                        <i class="fa fa-history history-toggle-icon"></i>
                    </button>
                    <?php if (canAdminAccess('project_update')): ?>
                        <button class="btn-icon-premium btn-icon-sm btn-icon-edit" onclick="window.location.href='index.php?edit_project=<?php echo $project_id; ?>'" title="Edit Project">
                            <i class="fa fa-pencil"></i>
                        </button>
                    <?php endif; ?>
                    <?php if (canAdminAccess('project_delete')): ?>
                        <button class="btn-icon-premium btn-icon-sm btn-icon-delete" onclick="deleteProject(<?php echo $project_id; ?>, '<?php echo addslashes($p['project_name']); ?>')" title="Delete Project">
                            <i class="fa fa-trash-o"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr class="project-detail-row" style="display: none; background: #fff;">
            <td colspan="<?php echo canAdminAccess('project_source_view') ? '10' : '9'; ?>" style="padding: 0; border: none;">
                <div style="padding: 35px 50px; border-top: 1px solid #f1f5f9; background: #fcfdfe;">
                    <div class="row">
                        <div class="col-md-7">
                            <div class="timeline-container-premium" style="background: transparent; border: none; padding: 0; margin-bottom: 0;">
                                <div class="timeline-header-premium" style="margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 10px; font-size: 11px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">
                                        <i class="fa fa-history" style="color: #dd2127; font-size: 14px;"></i>
                                        <span>Project Activity Timeline</span>
                                    </div>
                                    <a href="download_progress_report.php?project_id=<?php echo $project_id; ?>" target="_blank" style="background: #ffeaeb; color: #dd2127; border: 1px solid #ffeaeb; border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                        <i class="fa fa-download"></i> Download Progress Report
                                    </a>
                                </div>
                                <div class="timeline-visual-wrapper" style="max-height: 250px; overflow-y: auto; overflow-x: hidden; padding-right: 15px; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                                    <div class="timeline-vertical-line" style="left: 4px;"></div>
                                    <div class="remarks-history-premium" style="position: relative; padding-left: 0;">
                                        <?php
                                        // Ensure posted_by column exists
                                        try {
                                            @mysqli_query($con, "ALTER TABLE client_project_remarks ADD COLUMN posted_by VARCHAR(255) DEFAULT NULL");
                                        } catch (Exception $e) {
                                        }

                                        $get_remarks = "SELECT * FROM client_project_remarks WHERE project_id = $project_id ORDER BY created_at DESC";
                                        $run_remarks = mysqli_query($con, $get_remarks);
                                        if (mysqli_num_rows($run_remarks) > 0) {
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
                                    <button type="button" class="add-remark-btn" data-project-id="<?php echo $project_id; ?>" style="width: 100%; height: 48px; font-size: 14px; background: #dd2127; color: #ffffff; border: none; border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.3s ease; font-weight: 700; gap: 8px; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.2); box-sizing: border-box;">
                                        <i class="fa fa-send"></i> Post Update
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
<?php
    }
}
?>