<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (isset($_POST['client_id'])) {
    $client_id = intval($_POST['client_id']);

    $get_projects = "SELECT * FROM client_projects WHERE client_id = $client_id AND deleted_at IS NULL ORDER BY id DESC";
    $run_projects = mysqli_query($con, $get_projects);

    if (!$run_projects) {
        die('<div class="alert alert-danger" style="margin: 20px; border-radius: 12px; border: none; background: #fee2e2; color: #991b1b; font-weight: 600;">
                <i class="fa fa-exclamation-triangle"></i> Database Error: ' . mysqli_error($con) . '
            </div>');
    }

    if (mysqli_num_rows($run_projects) > 0) {
        while ($p = mysqli_fetch_assoc($run_projects)) {
            $project_id = $p['id'];
            $project_date = !empty($p['project_date']) ? date('M d, Y', strtotime($p['project_date'])) : 'NA';
            $budget = floatval($p['budget']);
?>
            <tr class="toggle-project-detail" style="cursor: pointer; transition: 0.3s;">
                <td style="text-align: center;">
                    <span class="id-badge-premium">#<?php echo str_pad($project_id, 3, '0', STR_PAD_LEFT); ?></span>
                </td>
                <td>
                    <div style="font-weight: 800; color: #0f172a; font-size: 15px; letter-spacing: -0.3px;"><?php echo htmlspecialchars($p['project_name']); ?></div>
                </td>
                <td style="color: #64748b; font-size: 13px; font-weight: 700; text-align: center;">
                    <i class="fa fa-calendar-o" style="margin-right: 5px;"></i> <?php echo $project_date; ?>
                </td>
                <td style="text-align: center;">
                    <span style="font-weight: 900; color: #011d33; font-size: 16px; letter-spacing: -0.5px;">₹ <?php echo number_format($budget, 0); ?></span>
                </td>
                <td style="text-align: center;">
                    <?php
                    $status = !empty($p['status']) ? $p['status'] : 'Active';
                    $color_map = [
                        'Active' => '#16a34a',
                        'Pending' => '#ca8a04',
                        'Completed' => '#2563eb'
                    ];
                    $bg_map = [
                        'Active' => '#f0fdf4',
                        'Pending' => '#fefce8',
                        'Completed' => '#eff6ff'
                    ];
                    $border_map = [
                        'Active' => '#dcfce7',
                        'Pending' => '#fef9c3',
                        'Completed' => '#dbeafe'
                    ];
                    $current_color = isset($color_map[$status]) ? $color_map[$status] : '#475569';
                    $current_bg = isset($bg_map[$status]) ? $bg_map[$status] : '#f8fafc';
                    $current_border = isset($border_map[$status]) ? $border_map[$status] : '#e2e8f0';
                    ?>
                    <select class="project-status-select" data-project-id="<?php echo $project_id; ?>"
                        style="background: <?php echo $current_bg; ?>; color: <?php echo $current_color; ?>; border: 1px solid <?php echo $current_border; ?>; font-size: 10px; font-weight: 800; text-transform: uppercase; padding: 6px 12px; border-radius: 20px; letter-spacing: 0.5px; cursor: pointer; outline: none; transition: 0.3s; width: 100%; max-width: 120px;">
                        <option value="Active" <?php if ($status == 'Active') echo 'selected'; ?>>Active</option>
                        <option value="Pending" <?php if ($status == 'Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Completed" <?php if ($status == 'Completed') echo 'selected'; ?>>Completed</option>
                    </select>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <button class="btn-icon-premium" onclick="window.location.href='index.php?team_todo&project_id=<?php echo $project_id; ?>'"
                            style="background: #fffbeb; border-color: #fef3c7;" title="Team To-Do">
                            <i class="fa fa-list-alt" style="color: #f59e0b;"></i>
                        </button>
                        <button class="btn-icon-premium btn-toggle-history" style="background: #f5f3ff; border-color: #ede9fe;" title="View History">
                            <i class="fa fa-history history-toggle-icon" style="color: #7c3aed;"></i>
                        </button>
                        <button class="btn-icon-premium" onclick="deleteProject(<?php echo $project_id; ?>, '<?php echo addslashes($p['project_name']); ?>')"
                            style="background: #fef2f2; border-color: #fee2e2;" title="Delete Project">
                            <i class="fa fa-trash-o" style="color: #ef4444;"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <tr class="project-detail-row" style="display: none; background: #fff;">
                <td colspan="6" style="padding: 0; border: none;">
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
}
?>