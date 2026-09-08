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

// Currency symbols map
$currency_symbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'د.إ'
];

// Status filter
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, trim($_GET['status'])) : '';

// Base where clause: ONLY leads assigned to this employee & not deleted
$where_clause = " WHERE deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0 ";
if (!empty($status_filter)) {
    $where_clause .= " AND status='$status_filter' ";
}

// Counts for stat cards (strictly assigned to this employee)
$total_leads_count   = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0"));
$active_leads_count  = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='active' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0"));
$future_leads_count  = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='future' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0"));
$expired_leads_count = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='expired' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0"));

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$start_from = $offset;

$countSql = "SELECT COUNT(*) as total FROM leads $where_clause";
$countResult = mysqli_query($con, $countSql);
$totalRecords = $countResult ? (int)mysqli_fetch_assoc($countResult)['total'] : 0;
$totalPages = ceil($totalRecords / $limit);

// Fetch leads assigned to this employee
$get_leads = "SELECT * FROM leads $where_clause ORDER BY id DESC LIMIT $offset, $limit";
$run_leads = mysqli_query($con, $get_leads);
?>

<div class="page-wrapper premium-ui-enabled">

    <!-- Stat Cards Row -->
    <div class="stat-cards-row">
        <!-- Total Leads -->
        <div class="stat-card <?php echo empty($status_filter) ? 'active-filter' : ''; ?>" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads'">
            <div class="stat-card-icon sc-purple">
                <i class="fa fa-briefcase"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">TOTAL LEADS</div>
                <div class="stat-card-value"><?php echo $total_leads_count; ?></div>
            </div>
        </div>

        <!-- Current Leads (Active) -->
        <div class="stat-card <?php echo $status_filter === 'active' ? 'active-filter' : ''; ?>" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=active'">
            <div class="stat-card-icon sc-green">
                <i class="fa fa-folder-open"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">CURRENT LEADS</div>
                <div class="stat-card-value"><?php echo $active_leads_count; ?></div>
            </div>
        </div>

        <!-- Upcoming (Future) -->
        <div class="stat-card <?php echo $status_filter === 'future' ? 'active-filter' : ''; ?>" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=future'">
            <div class="stat-card-icon sc-orange">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">UPCOMING</div>
                <div class="stat-card-value"><?php echo $future_leads_count; ?></div>
            </div>
        </div>

        <!-- Lost Leads (Expired) -->
        <div class="stat-card <?php echo $status_filter === 'expired' ? 'active-filter' : ''; ?>" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=expired'">
            <div class="stat-card-icon sc-blue">
                <i class="fa fa-clock-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">LOST LEADS</div>
                <div class="stat-card-value"><?php echo $expired_leads_count; ?></div>
            </div>
        </div>
    </div>

    <!-- Assigned Leads Table Card -->
    <div class="premium-card" style="margin-top: 25px; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9;">
        <!-- Card Header Banner -->
        <div class="card-hdr" style="background: var(--p-bg-header); color: #fff; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-list-ul" style="font-size: 16px;"></i>
                <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">
                    ALL LEADS
                </h3>
            </div>
        </div>

        <!-- Table Responsive Container -->
        <div class="table-responsive">
            <table class="table-premium" style="width: 100%; border-collapse: collapse; margin-bottom: 0; table-layout: fixed;">
                <thead>
                    <tr style="background: #fcfdfe; border-bottom: 1.5px solid #f1f5f9;">
                        <th style="width: 12%; min-width: 90px; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px 20px;">ID</th>
                        <th style="width: 48%; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px 20px;">PROJECT NAME</th>
                        <th style="width: 25%; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px 20px;">STATUS</th>
                        <th style="width: 15%; min-width: 110px; text-align: center; font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; padding: 15px 20px;">MANAGE</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($run_leads && mysqli_num_rows($run_leads) > 0):
                        while ($row = mysqli_fetch_assoc($run_leads)):
                            $lead_id = (int)$row['id'];
                            $project_name = !empty($row['project_name']) ? $row['project_name'] : $row['client_name'];
                            $status = strtolower(trim($row['status'] ?? 'active'));

                            // Status badge styling (read-only, no change dropdown)
                            $badge_bg = '#ffeaeb';
                            $badge_color = '#dd2127';
                            $badge_border = '#fecdd3';
                            $badge_label = 'ACTIVE';

                            if ($status === 'active') {
                                $badge_bg = '#ffeaeb';
                                $badge_color = '#dd2127';
                                $badge_border = '#fecdd3';
                                $badge_label = 'ACTIVE';
                            } elseif ($status === 'future') {
                                $badge_bg = '#eff6ff';
                                $badge_color = '#2563eb';
                                $badge_border = '#bfdbfe';
                                $badge_label = 'FUTURE';
                            } elseif ($status === 'expired') {
                                $badge_bg = '#fef2f2';
                                $badge_color = '#dc2626';
                                $badge_border = '#fecaca';
                                $badge_label = 'EXPIRED';
                            } else {
                                $badge_label = strtoupper($status);
                            }
                    ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                <!-- Column 1: ID -->
                                <td style="text-align: center; padding: 16px 20px; font-weight: 700; color: #64748b;">
                                    <span class="id-badge-premium">#<?php echo str_pad($lead_id, 3, '0', STR_PAD_LEFT); ?></span>
                                </td>

                                <!-- Column 2: Project Name -->
                                <td style="text-align: center; padding: 16px 20px; word-break: break-word;">
                                    <div style="font-weight: 700; color: #1e293b; font-size: 14px;">
                                        <?php echo htmlspecialchars($project_name); ?>
                                    </div>
                                </td>

                                <!-- Column 3: Status (Read-only badge, no change dropdown) -->
                                <td style="text-align: center; padding: 16px 20px;">
                                    <span style="display: inline-block; padding: 6px 18px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.8px; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_border; ?>; user-select: none;">
                                        <?php echo $badge_label; ?>
                                    </span>
                                </td>

                                <!-- Column 4: Manage -->
                                <td style="text-align: center; padding: 16px 20px;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <button type="button" class="btn-icon-premium btn-icon-view" onclick="openLeadModal(<?php echo $lead_id; ?>)" title="View Lead Details">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 50px 20px; color: #94a3b8;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: #f8fafc; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; color: #cbd5e1; font-size: 24px;">
                                    <i class="fa fa-bullseye"></i>
                                </div>
                                <h4 style="font-weight: 700; color: #475569; margin-bottom: 5px;">No Assigned Leads Found</h4>
                                <p style="font-size: 13px; color: #94a3b8; margin: 0;">You currently have no leads assigned matching this filter.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 16px 24px; background: #fff; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 13px; color: #64748b;">
                    Showing <strong><?php echo ($totalRecords > 0 ? $start_from + 1 : 0); ?></strong> to <strong><?php echo min($start_from + $limit, $totalRecords); ?></strong> of <strong><?php echo $totalRecords; ?></strong> leads
                </div>
                <ul class="pagination pagination-sm" style="margin: 0;">
                    <?php
                    $url_prefix = "index.php?leads";
                    if (!empty($status_filter)) $url_prefix .= "&status=" . urlencode($status_filter);

                    if ($page > 1): ?>
                        <li><a href="<?php echo $url_prefix . '&page=' . ($page - 1); ?>">&laquo;</a></li>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="<?php echo $p == $page ? 'active' : ''; ?>">
                            <a href="<?php echo $url_prefix . '&page=' . $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li><a href="<?php echo $url_prefix . '&page=' . ($page + 1); ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== TRELLO-STYLE LEAD POPUP MODAL (IDENTICAL TO PHOTO) ===== -->
<div id="leadDetailOverlay">
    <div id="leadDetailModal">
        <!-- Top Navigation Bar -->
        <div class="tdm-top-bar">
            <div class="tdm-list-tag" id="lead-modal-assigned-pill" title="Assigned Employee">
                <i class="fa fa-user" style="color: #dd2127; margin-right: 5px;"></i>
                <span id="lead-modal-assigned-name">Assigned Employee</span>
            </div>
            <div class="tdm-top-actions">
                <button class="tdm-icon-btn" title="Lead Source">
                    <i class="fa fa-briefcase" style="color: #dd2127;"></i>
                    <span id="lead-modal-source" style="font-size: 12px; font-weight: 700;">General Task</span>
                </button>
                <button id="lead-modal-close-btn" onclick="closeLeadModal()" title="Close (Esc)">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>

        <!-- 2-Column Split Layout -->
        <div class="tdm-body">
            <!-- LEFT COLUMN: Title, Date, Priority & Description -->
            <div class="tdm-left">
                <!-- Title Header -->
                <div class="tdm-header" style="align-items: flex-start; gap: 12px; margin-bottom: 16px;">
                    <div id="lead-modal-title" style="font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.35; width: 100%;">
                        Lead Title
                    </div>
                </div>

                <!-- Meta Row: Follow-up Date & Status -->
                <div class="tdm-meta-row" style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
                    <div class="tdm-meta-group" style="display: flex; flex-direction: column; gap: 5px;">
                        <div class="tdm-meta-label" style="font-size: 10px; font-weight: 800; color: #94a3b8; letter-spacing: 0.8px;">DUE DATE</div>
                        <div id="lead-modal-date-display" style="padding: 7px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-weight: 700; color: #0f172a; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-calendar" style="color: #dd2127;"></i>
                            <span id="lead-modal-due-date">09/01/2026</span>
                        </div>
                    </div>

                    <div class="tdm-meta-group" style="display: flex; flex-direction: column; gap: 5px;">
                        <div class="tdm-meta-label" style="font-size: 10px; font-weight: 800; color: #94a3b8; letter-spacing: 0.8px;">STATUS</div>
                        <div id="lead-modal-status-badge" style="padding: 7px 16px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.8px; background: #ffeaeb; color: #dd2127; border: 1px solid #fecdd3; display: inline-block;">
                            ACTIVE
                        </div>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="tdm-section" style="display: flex; flex-direction: column; flex: 1; min-height: 0;">
                    <div class="tdm-section-title" style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-align-left" style="color: #dd2127;"></i>
                        <span>Description</span>
                    </div>
                    <div id="lead-modal-description" style="background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 14px 16px; flex: 1; overflow-y: auto; font-size: 13px; color: #334155; line-height: 1.55; white-space: pre-wrap; min-height: 80px;">
                        Add a more detailed description...
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Comments and activity -->
            <div class="tdm-right">
                <div class="tdm-section-title" style="font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 14px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-comments-o" style="color: #dd2127;"></i> Comments and activity
                </div>

                <!-- New Comment Box -->
                <div class="tdm-comment-add" style="display: flex; gap: 10px; margin-bottom: 16px;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px;">
                        <i class="fa fa-user"></i>
                    </div>
                    <div style="flex: 1;">
                        <textarea id="lead-comment-input" class="tdm-comment-textarea" rows="2" placeholder="Write a comment..." style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 13px; outline: none; font-family: inherit; resize: vertical;" onfocus="document.getElementById('lead-comment-actions').style.display='flex';"></textarea>

                        <div id="lead-comment-actions" style="display: none; margin-top: 8px; justify-content: space-between; align-items: center;">
                            <span style="font-size: 11px; color: #94a3b8;">Click Save to log note</span>
                            <button id="lead-comment-save-btn" onclick="submitLeadComment()" style="background: #dd2127; color: #fff; border: none; border-radius: 6px; padding: 6px 16px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                Save
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Stream -->
                <div id="lead-activity-stream" class="tdm-activity" style="flex: 1; overflow-y: auto; padding-right: 4px;">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Overlay and Trello Modal Styling */
    #leadDetailOverlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(15, 23, 42, 0.65);
        backdrop-filter: blur(4px);
        overflow-y: auto;
        padding: 40px 16px;
        font-family: inherit;
    }

    #leadDetailModal {
        background: #ffffff;
        border-radius: 16px;
        max-width: 920px;
        width: 100%;
        height: 580px;
        max-height: 88vh;
        margin: 0 auto;
        padding: 24px 28px 26px;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25);
        position: relative;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
        animation: tdSlideIn .2s ease;
    }

    @keyframes tdSlideIn {
        from {
            opacity: 0;
            transform: translateY(12px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .tdm-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 14px;
        flex-shrink: 0;
    }

    .tdm-list-tag {
        display: inline-flex;
        align-items: center;
        padding: 5px 12px;
        background: #f1f5f9;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 700;
        color: #334155;
    }

    .tdm-top-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tdm-icon-btn {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 5px 12px;
        color: #64748b;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    #lead-modal-close-btn {
        position: static !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        transform: none !important;
        flex-shrink: 0 !important;
        background: #dd2127 !important;
        color: #ffffff !important;
        border: none !important;
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        font-size: 14px !important;
        margin: 0 !important;
        padding: 0 !important;
        transition: background 0.2s ease, transform 0.2s ease !important;
    }

    #lead-modal-close-btn:hover {
        background: #b91c1c !important;
        transform: rotate(90deg) !important;
    }

    .tdm-body {
        display: flex;
        gap: 24px;
        align-items: stretch;
        border-top: 1px solid #f1f5f9;
        padding-top: 18px;
        flex: 1;
        min-height: 0;
        overflow: hidden;
    }

    .tdm-left {
        flex: 1.1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        padding-right: 6px;
    }

    .tdm-right {
        flex: 0.9;
        min-width: 0;
        background: #fafafa;
        border: 1px solid #f1f5f9;
        border-radius: 12px;
        padding: 16px 14px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-sizing: border-box;
    }
</style>

<?php
$auto_open_id = 0;
if (isset($_GET['open_lead'])) $auto_open_id = (int)$_GET['open_lead'];
elseif (isset($_GET['view_lead'])) $auto_open_id = (int)$_GET['view_lead'];
elseif (isset($_GET['lead_id'])) $auto_open_id = (int)$_GET['lead_id'];
?>

<script>
    let currentActiveLeadId = 0;
    const autoOpenLeadId = <?php echo $auto_open_id; ?>;

    $(document).ready(function() {
        if (autoOpenLeadId > 0) {
            openLeadModal(autoOpenLeadId);
        }
    });

    function openLeadModal(leadId) {
        currentActiveLeadId = leadId;
        $('#leadDetailOverlay').fadeIn(200);
        $('#lead-comment-input').val('');
        $('#lead-comment-actions').hide();
        $('#lead-activity-stream').html('<div style="text-align:center; padding:20px; color:#94a3b8;"><i class="fa fa-spinner fa-spin"></i> Loading details...</div>');

        $.ajax({
            url: 'ajax/ajax_lead_details.php',
            type: 'GET',
            data: {
                action: 'get_lead',
                lead_id: leadId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const lead = res.lead;
                    $('#lead-modal-assigned-name').text(lead.assigned_name || 'Assigned Employee');
                    $('#lead-modal-source').text(lead.lead_source || 'General Task');
                    $('#lead-modal-title').text(lead.project_name || lead.client_name);
                    $('#lead-modal-due-date').text(lead.display_followup_date || '--');

                    // Description (clean any legacy hardcoded 4 questions if description was only that)
                    let rawDesc = lead.description ? lead.description.trim() : '';
                    const legacyTemplate = "1. Can we do it or not?\n2. Complexity of the project on the scale from 1 to 5 (1 is easy, 5 is complex)\n3. Time required for the project\n4. Reply mail to client about any additional information/suggestions that I can directly forwarded to him";
                    
                    if (rawDesc.replace(/\r/g, '').trim() === legacyTemplate.replace(/\r/g, '').trim() || rawDesc.toLowerCase() === 'teste13') {
                        rawDesc = '';
                    }

                    if (rawDesc) {
                        $('#lead-modal-description').html(escapeHtml(rawDesc).replace(/\n/g, '<br>'));
                    } else {
                        $('#lead-modal-description').html('<span style="color: #94a3b8; font-style: italic; font-size: 13px;">No description provided.</span>');
                    }

                    // Status Badge
                    let stLabel = lead.status.toUpperCase();
                    let stBg = '#ffeaeb';
                    let stColor = '#dd2127';
                    let stBorder = '#fecdd3';

                    if (lead.status === 'future') {
                        stBg = '#eff6ff';
                        stColor = '#2563eb';
                        stBorder = '#bfdbfe';
                    } else if (lead.status === 'expired') {
                        stBg = '#fef2f2';
                        stColor = '#dc2626';
                        stBorder = '#fecaca';
                    }

                    $('#lead-modal-status-badge').css({
                        'background': stBg,
                        'color': stColor,
                        'border': '1px solid ' + stBorder
                    }).text(stLabel);

                    // Populate Comments Stream
                    renderLeadComments(res.followups);
                } else {
                    alert(res.message || 'Error loading lead details');
                    closeLeadModal();
                }
            },
            error: function() {
                alert('Failed to connect to server');
                closeLeadModal();
            }
        });
    }

    function closeLeadModal() {
        $('#leadDetailOverlay').fadeOut(180);
        currentActiveLeadId = 0;
    }

    // Close on backdrop click or ESC key
    $(document).on('click', '#leadDetailOverlay', function(e) {
        if (e.target.id === 'leadDetailOverlay') {
            closeLeadModal();
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#leadDetailOverlay').is(':visible')) {
            closeLeadModal();
        }
    });

    function renderLeadComments(followups) {
        const defaultQuestionsCard = `
            <div style="display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-start;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: #ffeaeb; color: #dd2127; border: 1px solid #fecdd3; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 11px; flex-shrink: 0; margin-top: 2px;">
                    <i class="fa fa-list-ol"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 5px;">
                        <strong style="color: #1e293b; font-weight: 700;">Default Task Questions</strong>
                        <small style="background: #f1f5f9; color: #64748b; font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 10px;">Checklist</small>
                    </div>
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 3px solid #dd2127; border-radius: 10px; padding: 12px 14px; font-size: 12.5px; color: #334155; line-height: 1.6; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Required Follow-up Questions:</div>
                        <div style="margin-bottom: 4px; display: flex; gap: 6px;"><span style="font-weight: 700; color: #dd2127;">1.</span> <span>Can we do it or not?</span></div>
                        <div style="margin-bottom: 4px; display: flex; gap: 6px;"><span style="font-weight: 700; color: #dd2127;">2.</span> <span>Complexity of the project on the scale from 1 to 5 (1 is easy, 5 is complex)</span></div>
                        <div style="margin-bottom: 4px; display: flex; gap: 6px;"><span style="font-weight: 700; color: #dd2127;">3.</span> <span>Time required for the project</span></div>
                        <div style="display: flex; gap: 6px;"><span style="font-weight: 700; color: #dd2127;">4.</span> <span>Reply mail to client about any additional information/suggestions that I can directly forwarded to him</span></div>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: #94a3b8; padding: 4px 0 12px 4px;">
                <span style="width: 22px; height: 22px; border-radius: 50%; background: #ffeaeb; color: #dd2127; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 11px;">+</span>
                <span>Task created</span>
            </div>
        `;

        let html = '';
        if (followups && followups.length > 0) {
            followups.forEach(function(item) {
                const authorName = item.author || 'Employee';
                const initial = authorName.charAt(0).toUpperCase();
                const timeAgo = item.time_ago || item.date;
                const remarkText = item.clean_remark || item.remark;

                html += `<div style="display: flex; gap: 10px; margin-bottom: 16px; align-items: flex-start;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #dd2127; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 11px; flex-shrink: 0; margin-top: 2px;">
                        ${escapeHtml(initial)}
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 5px;">
                            <strong style="color: #1e293b; font-weight: 700;">${escapeHtml(authorName)}</strong>
                            <small style="color: #94a3b8; font-size: 11.5px;">${escapeHtml(timeAgo)}</small>
                        </div>
                        <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #1e293b; line-height: 1.5; white-space: pre-wrap;">${escapeHtml(remarkText)}</div>
                    </div>
                </div>`;
            });
        }
        
        html += defaultQuestionsCard;
        $('#lead-activity-stream').html(html);
    }

    function submitLeadComment() {
        const remark = $('#lead-comment-input').val().trim();
        if (!remark || currentActiveLeadId <= 0) return;

        $('#lead-comment-save-btn').prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'ajax/ajax_lead_details.php',
            type: 'POST',
            data: {
                action: 'add_comment',
                lead_id: currentActiveLeadId,
                remark: remark
            },
            dataType: 'json',
            success: function(res) {
                $('#lead-comment-save-btn').prop('disabled', false).text('Save');
                if (res.status === 'success') {
                    $('#lead-comment-input').val('');
                    $('#lead-comment-actions').hide();
                    // Reload modal data
                    openLeadModal(currentActiveLeadId);
                } else {
                    alert(res.message || 'Could not save comment');
                }
            },
            error: function() {
                $('#lead-comment-save-btn').prop('disabled', false).text('Save');
                alert('Error saving comment');
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>