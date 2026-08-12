<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit();
}

/* ==============================
   FETCH EMPLOYEE LIST
============================== */
$empList = [];
$empQ = mysqli_query($con, "SELECT id, name FROM emp_list WHERE deleted_at IS NULL ORDER BY name ASC");
while ($erow = mysqli_fetch_assoc($empQ)) $empList[] = $erow;

/* ==============================
   HANDLE FILTERS SAFELY
============================== */
$filter_emp    = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_from   = isset($_GET['from']) ? trim($_GET['from']) : '';
$filter_to     = isset($_GET['to']) ? trim($_GET['to']) : '';

$where = [];
if (!empty($filter_emp)) $where[] = "a.emp_id = $filter_emp";
if (!empty($filter_status) && in_array($filter_status, ['present', 'absent', 'leave'])) {
    $st_esc = mysqli_real_escape_string($con, $filter_status);
    $where[] = "a.status = '$st_esc'";
}
if (!empty($filter_from)) {
    $from_db = date('Y-m-d', strtotime($filter_from));
    $where[] = "DATE(a.attendance_date) >= '" . mysqli_real_escape_string($con, $from_db) . "'";
}
if (!empty($filter_to)) {
    $to_db = date('Y-m-d', strtotime($filter_to));
    $where[] = "DATE(a.attendance_date) <= '" . mysqli_real_escape_string($con, $to_db) . "'";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

/* ==============================
   PAGINATION SETUP
============================== */
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total records
$countSql = "SELECT COUNT(*) as total 
             FROM attendance a 
             LEFT JOIN emp_list e ON a.emp_id = e.id 
             $whereSql";
$countResult = mysqli_query($con, $countSql);
$totalRecords = 0;
if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRecords = $countRow['total'];
}
$totalPages = ceil($totalRecords / $limit);

$sql = "SELECT a.*, e.name AS emp_name, e.employee_image,
        (SELECT action_time FROM attendance_logs WHERE att_id = a.id AND action = 'check_out' ORDER BY action_time DESC LIMIT 1) as log_checkout 
        FROM attendance a 
        LEFT JOIN emp_list e ON a.emp_id = e.id 
        $whereSql
        ORDER BY a.attendance_date DESC, a.id DESC
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($con, $sql);
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <div class="header-actions">
            <!-- Optional: Filter Toggle or Export Button -->
            <button class="btn-premium-add" onclick="window.filter()">
                <i class="fa fa-filter"></i>filter
            </button>
            <button class="btn-premium-add" onclick="openWorkGallery()">
                <i class="fa fa-photo"></i>Images
            </button>
            <button class="btn-premium-add" onclick="window.print()">
                <i class="fa fa-print"></i>Print
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div id="filter-section" class="premium-card filter-card" style="display: <?php echo (!empty($filter_emp) || !empty($filter_status) || !empty($filter_from) || !empty($filter_to)) ? 'block' : 'none'; ?>; ">
        <div class="card-hdr">
            <i class="fa fa-sliders"></i>
            <h3>Filter By</h3>
            <button class="btn-close-filter" onclick="window.filter()" style="margin-left: auto; background: none; border: none; color: #94a3b8; cursor: pointer;">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <form method="GET" action="index.php" style="padding: 20px;">
            <input type="hidden" name="worksheettable" value="">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Employee</label>
                    <select name="emp_id" class="p-input-premium">
                        <option value="">All Employees</option>
                        <?php foreach ($empList as $e): ?>
                            <option value="<?php echo $e['id']; ?>" <?php if ($filter_emp == $e['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($e['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="p-input-premium">
                        <option value="">All Statuses</option>
                        <option value="present" <?php if ($filter_status == 'present') echo 'selected'; ?>>Present</option>
                        <option value="absent" <?php if ($filter_status == 'absent') echo 'selected'; ?>>Absent</option>
                        <option value="leave" <?php if ($filter_status == 'leave') echo 'selected'; ?>>Leave</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="from" class="p-input-premium" value="<?php echo $filter_from; ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="to" class="p-input-premium" value="<?php echo $filter_to; ?>">
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end;">
                <a href="index.php?worksheettable" class="btn-clear-filter">
                    <i class="fa fa-refresh"></i>Clear
                </a>
                <button type="submit" class="btn-premium-add">
                    <i class="fa fa-check"></i>Search
                </button>
            </div>
        </form>
    </div>

    <div class="premium-card">
        <div class="card-hdr">
            <i class="fa fa-table"></i>
            <h3>Work Details</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;"></th>
                        <th style="width: 60px; text-align: center;">#</th>
                        <th>Team Member</th>
                        <th>Time Logged</th>
                        <th style="text-align: center;">Status</th>
                        <!-- <th style="text-align: center;">Activity %</th> -->
                        <th>Tasks Done</th>
                        <th>Saved At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): $i = $offset + 1; ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $st = $row['status'];
                            $badge_class = 'p-badge-secondary';
                            if ($st == 'present') $badge_class = 'p-badge-success';
                            elseif ($st == 'absent') $badge_class = 'p-badge-danger';
                            elseif ($st == 'leave') $badge_class = 'p-badge-primary';

                            $img = !empty($row['employee_image']) ? 'uploads/' . $row['employee_image'] : '../admin_area/admin_images/default.png';
                            $att_id = $row['id'];
                        ?>
                            <tr class="ws-emp-row" data-att-id="<?php echo $att_id; ?>" style="cursor:pointer; transition: background 0.2s;">
                                <td style="text-align: center; color: #94a3b8; font-size: 12px; width:50px;">
                                    <i class="fa fa-chevron-right log-expand-icon" style="transition: transform 0.3s;"></i>
                                </td>
                                <td style="text-align: center; color: var(--p-secondary); font-weight: 700;"><?php echo $i++; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?php echo $img; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                                        <div style="font-weight: 700; color: var(--p-text);"><?php echo htmlspecialchars($row['emp_name']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--p-text);"><?php echo date('d-m-Y', strtotime($row['attendance_date'])); ?></div>
                                    <div style="font-size: 11px; color: var(--p-secondary);">
                                        <?php
                                        $display_out = $row['check_out_time'];
                                        if ((empty($display_out) || $display_out == '00:00:00') && !empty($row['log_checkout'])) {
                                            $display_out = date('H:i:s', strtotime($row['log_checkout']));
                                        }
                                        ?>
                                        <i class="fa fa-clock-o"></i> <?php echo (!empty($row['check_in_time']) && $row['check_in_time'] != '00:00:00') ? $row['check_in_time'] : '--:--'; ?> - <?php echo (!empty($display_out) && $display_out != '00:00:00') ? $display_out : '--:--'; ?>
                                    </div>
                                </td>
                                <td style="text-align: center; vertical-align: middle;">
                                    <span class="p-badge <?php echo $badge_class; ?>" style="display: inline-flex; justify-content: center; min-width: 80px; white-space: nowrap;">
                                        <?php echo ucfirst($st); ?>
                                    </span>
                                </td>
                                <!-- <td style="text-align: center;">
                                    <?php if (isset($row['performance'])): ?>
                                        <div style="font-weight: 800; color: #4f46e5;"><?php echo $row['performance']; ?>%</div>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1;">-</span>
                                    <?php endif; ?>
                                </td> -->
                                <td>
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
                                        $emp_name = htmlspecialchars($row['emp_name'], ENT_QUOTES, 'UTF-8');
                                        $date = htmlspecialchars(date('d-m-Y', strtotime($row['attendance_date'])), ENT_QUOTES, 'UTF-8');
                                        $emp_img = htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
                                        $remark_js = htmlspecialchars(json_encode(nl2br(htmlspecialchars($row['remarks'] ?: '-'))), ENT_QUOTES, 'UTF-8');
                                        echo '<button type="button" class="btn btn-sm" style="border-radius: 6px; padding: 4px 12px; font-weight: 600; background: #fff; color: #1e293b; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.05);" onclick="openRowGallery(\'' . $json_photos . '\', \'' . $emp_name . '\', \'' . $date . '\', \'' . $emp_img . '\', ' . $remark_js . '); event.stopPropagation();"><i class="fa fa-eye" style="color: #dd2127; margin-right: 4px;"></i> View Details</button>';
                                    } else {
                                        echo '<span style="color: #cbd5e1;">-</span>';
                                    }
                                    ?>
                                </td>
                                <td style="font-size: 11px; color: var(--p-secondary);"><?php echo date('d-m-Y H:i', strtotime($row['created_at'])); ?></td>
                            </tr>
                            <!-- Log Detail Row -->
                            <tr class="log-detail-row" id="log-row-<?php echo $att_id; ?>" style="display: none;">
                                <td colspan="8" style="padding: 0 !important; border-top: none;">
                                    <div class="log-detail-panel" id="log-panel-<?php echo $att_id; ?>">
                                        <div class="log-panel-loading" id="log-loading-<?php echo $att_id; ?>">
                                            <i class="fa fa-spinner fa-spin"></i> Loading session details...
                                        </div>
                                        <div class="log-panel-content" id="log-content-<?php echo $att_id; ?>" style="display:none;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 100px 20px; text-align: center;">
                                <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fa fa-folder-open-o" style="font-size: 24px; color: #94a3b8;"></i>
                                </div>
                                <h3 style="color: #64748b; font-weight: 600; font-size: 16px;">No reports found.</h3>
                                <p style="color: #64748b; font-size: 13px;">No logs match your search filters.</p>
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
                // Display up to 5 page numbers
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

<div id="workGalleryModal" class="modal fade" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog modal-lg" style="margin-top: 40px; max-width: 1150px; width: 95%;">
        <div class="modal-content premium-modal-content-v2" style="border: none; border-radius: 32px; box-shadow: 0 40px 100px -20px rgba(111, 50, 50, 0.4); overflow: hidden;">
            <div class="modal-header" style="background: #ffeaeb; color:black; padding: 25px 35px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; padding-right: 40px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 18px;">
                        <div style="width: 48px; height: 48px; background: #dd2127; color:white;border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 8px 16px rgba(185, 81, 81, 0.3);">
                            <i class="fa fa-th-large"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 20px; margin: 0; letter-spacing: -0.5px;">Work Submission Archive</h4>
                            <p style="margin: 4px 0 0 0; font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Live Visual Insights</p>
                        </div>
                    </div>

                    <!-- Filters Inside Modal -->
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <!-- Employee Filter -->
                        <div class="modal-header-filter" style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 6px 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                            <i class="fa fa-user-circle" style="color: #dd2127; font-size: 15px;"></i>
                            <select id="modal_emp_filter" onchange="openWorkGallery(this.value, $('#modal_date_filter').val())" class="modal-select-premium" style="border: none; background: transparent; font-weight: 700; font-size: 13px; color: #1e293b; outline: none; cursor: pointer;">
                                <option value="">All Employees</option>
                                <?php foreach ($empList as $e): ?>
                                    <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Filter -->
                        <div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 6px 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                            <i class="fa fa-calendar" style="color: #dd2127; font-size: 15px;"></i>
                            <input type="date" id="modal_date_filter" onchange="openWorkGallery($('#modal_emp_filter').val(), this.value)" style="border: none; background: transparent; font-weight: 700; font-size: 13px; color: #1e293b; outline: none; cursor: pointer;" title="Filter photos by date">
                        </div>

                        <!-- Reset Date Filter -->
                        <button type="button" onclick="$('#modal_date_filter').val(''); openWorkGallery($('#modal_emp_filter').val(), '');" style="background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 7px 14px; font-size: 12px; font-weight: 700; color: #475569; cursor: pointer; transition: 0.2s;" title="Show all dates">
                            <i class="fa fa-refresh" style="color: #dd2127;"></i> All Dates
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-body" style="padding: 0; background: #fff; min-height: 450px; max-height: 78vh; overflow-y: auto;">
                <div id="gallery-content-container">
                    <!-- Gallery Content -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div id="imagePreviewModal" class="modal fade" role="dialog" style="z-index: 999999;">
    <div class="modal-dialog modal-lg" style="margin-top: 40px; max-width: 900px;">
        <div class="modal-content premium-modal-content-v2" style="border: none; border-radius: 32px; box-shadow: 0 40px 100px -20px rgba(111, 50, 50, 0.4); overflow: hidden; background: #fff;">
            <div class="modal-header" style="background: #ffeaeb; color:black; padding: 25px 35px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal">
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
    .p-badge {
        padding: 4px 12px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .p-badge-success {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }

    .p-badge-primary {
        background: rgba(79, 70, 229, 0.05);
        color: #dd2127;
    }

    .p-badge-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .p-badge-secondary {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Ensure table row height is consistent even with long remarks */
    .table-premium td {
        vertical-align: middle !important;
        padding: 12px 15px !important;
    }

    /* Pagination Styles */
    .pagination-premium {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding: 20px;
        gap: 8px;
        border-top: 1px solid #f1f5f9;
    }

    .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none !important;
        transition: all 0.2s;
        gap: 6px;
    }

    .page-link:hover:not(.disabled) {
        background: #dd2127;
        color: #FFF;
        border-color: #dd212d;
        text-decoration: none !important;
    }

    .page-link.active {
        background: #ffeaeb;
        color: #dd2127;
        border-color: #dd2127;
        text-decoration: none !important;
    }

    .page-link.disabled {
        opacity: 0.5;
        pointer-events: none;
        background: #f8fafc;
    }

    .page-link:focus,
    .page-link:active,
    .page-link:focus-visible {
        outline: none !important;
        box-shadow: none !important;
        text-decoration: none !important;
        -webkit-tap-highlight-color: transparent;
    }

    /* Filter Styles */
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .filter-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 8px;
    }

    .btn-clear-filter {
        background: #f1f5f9;
        color: #dd2127;
        padding: 10px 25px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none !important;
    }

    .btn-clear-filter:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .p-input-premium {
        width: 100%;
        padding: 10px 15px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background-color: #fff;
        color: #1e293b;
        font-weight: 500;
        outline: none;
        transition: all 0.2s;
    }

    .p-input-premium:focus {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
    }

    /* Premium Modal V2 */
    .premium-modal-content-v2 {
        animation: modalReveal 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        backdrop-filter: blur(25px);
    }

    @keyframes modalReveal {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(30px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    /* Enhance Modal Backdrop */
    .modal-backdrop.in {
        opacity: 0.7 !important;
        background-color: #0f172a !important;
        backdrop-filter: blur(8px);
    }

    /* Advanced Pulse Loader */
    .premium-loader-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 100px 0;
    }

    .premium-pulse-loader {
        width: 60px;
        height: 60px;
        background: #6366f1;
        border-radius: 20px;
        animation: pulseAndRotate 2s infinite ease-in-out;
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4);
    }

    @keyframes pulseAndRotate {
        0% {
            transform: scale(0.8) rotate(0deg);
            border-radius: 20px;
        }

        50% {
            transform: scale(1.2) rotate(180deg);
            border-radius: 50%;
            box-shadow: 0 0 0 20px rgba(99, 102, 241, 0);
        }

        100% {
            transform: scale(0.8) rotate(360deg);
            border-radius: 20px;
        }
    }

    .loader-text-premium {
        margin-top: 25px;
        font-weight: 800;
        color: #1e293b;
        letter-spacing: 1px;
        text-transform: uppercase;
        font-size: 12px;
        animation: fadeInOut 1.5s infinite;
    }

    @keyframes fadeInOut {

        0%,
        100% {
            opacity: 0.4;
        }

        50% {
            opacity: 1;
        }
    }

    /* ── Log detail panel styles ── */
    .ws-emp-row:hover {
        background: #fef9ff !important;
    }

    .ws-emp-row.row-open {
        background: #fff5f5 !important;
    }

    .ws-emp-row.row-open .log-expand-icon {
        transform: rotate(90deg);
        color: #dd2127;
    }

    .log-detail-row td {
        background: #fafbff;
        border-bottom: 2px solid #e2e8f0;
    }

    .log-detail-panel {
        padding: 20px 24px 24px;
        animation: logSlideDown 0.3s ease-out;
    }

    @keyframes logSlideDown {
        from {
            opacity: 0;
            transform: translateY(-8px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .log-panel-loading {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 14px;
    }

    .log-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 0;
    }

    .log-info-card {
        background: #fff;
        border: 1.5px solid #f1f5f9;
        border-radius: 14px;
        padding: 16px 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        transition: box-shadow 0.2s;
    }

    .log-info-card:hover {
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }

    .log-info-icon {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .lic-red {
        background: #ffe4e6;
        color: #e11d48;
    }

    .lic-blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .lic-green {
        background: #d1fae5;
        color: #059669;
    }

    .lic-purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .lic-amber {
        background: #fef3c7;
        color: #d97706;
    }

    .log-info-body h6 {
        margin: 0 0 2px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }

    .log-info-body p {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #0f172a;
        word-break: break-all;
    }

    .log-live-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #dcfce7;
        color: #16a34a;
        font-size: 10px;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 20px;
        letter-spacing: 0.05em;
        margin-left: 6px;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .log-live-dot {
        width: 7px;
        height: 7px;
        background: #22c55e;
        border-radius: 50%;
        animation: livePulse 1.2s infinite;
        display: inline-block;
    }

    @keyframes livePulse {

        0%,
        100% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.6);
            opacity: 0.5;
        }
    }

    .log-section-title {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .log-section-title::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #f1f5f9;
    }
</style>

<script>
    window.filter = function() {
        const section = document.getElementById('filter-section');
        if (section.style.display === 'none') {
            section.style.display = 'block';
            section.style.animation = 'slideDown 0.3s ease-out forwards';
        } else {
            section.style.display = 'none';
        }
    };

    window.openWorkGallery = function(forceEmpId = null, forceDate = null) {
        const modal = $('#workGalleryModal');
        const container = $('#gallery-content-container');

        let empId = forceEmpId !== null ? forceEmpId : ($('#modal_emp_filter').val() || $('select[name="emp_id"]').val() || '');
        let dateVal = forceDate !== null ? forceDate : ($('#modal_date_filter').val() || '');

        const status = $('select[name="status"]').val();
        const from = $('input[name="from"]').val();
        const to = $('input[name="to"]').val();

        if ($('#modal_emp_filter').length > 0 && forceEmpId === null) {
            $('#modal_emp_filter').val(empId);
        }
        if ($('#modal_date_filter').length > 0 && forceDate === null) {
            $('#modal_date_filter').val(dateVal);
        }

        container.html(`
            <div class="premium-loader-wrapper">
                <div class="premium-pulse-loader"></div>
                <div class="loader-text-premium">Curating Gallery...</div>
            </div>
        `);

        if (!modal.is(':visible')) {
            modal.modal('show');
        }

        $.ajax({
            url: 'ajax/gallery/ajax_view_work_gallery.php',
            method: 'GET',
            data: {
                emp_id: empId,
                date: dateVal,
                status: status,
                from: from,
                to: to
            },
            success: function(response) {
                container.hide().html(response).fadeIn(400);
            },
            error: function() {
                container.html('<div style="padding: 100px; text-align: center; color: #ef4444; font-weight: 700;"><i class="fa fa-exclamation-triangle"></i> ARCHIVE TEMPORARILY OFFLINE</div>');
            }
        });
    };

    // Expandable Log Row Handler
    var logLoaded = {};

    $(document).on('click', '.ws-emp-row', function(e) {
        if ($(e.target).closest('a, button, .work-photo-item-mini').length) return;

        var $row = $(this);
        var attId = $row.data('att-id');
        var $detail = $('#log-row-' + attId);
        var $content = $('#log-content-' + attId);
        var $loading = $('#log-loading-' + attId);

        if ($detail.is(':visible')) {
            $detail.hide();
            $row.removeClass('row-open');
            return;
        }

        // Close any other open rows
        $('.log-detail-row:visible').hide();
        $('.ws-emp-row.row-open').removeClass('row-open');

        $row.addClass('row-open');
        $detail.show();

        if (logLoaded[attId]) return;

        $loading.show();
        $content.hide();

        $.ajax({
            url: 'ajax/worksheet/ajax_get_emp_log.php',
            method: 'GET',
            data: {
                att_id: attId
            },
            dataType: 'json',
            success: function(d) {
                $loading.hide();
                if (!d.success) {
                    $content.html('<div style="color:#ef4444;font-weight:600;padding:20px;"><i class="fa fa-exclamation-triangle"></i> ' + (d.message || 'Failed to load') + '</div>').show();
                    return;
                }

                // Determine status color & label
                var isLeave = (d.status === 'leave');
                var isAbsent = (d.status === 'absent');
                var statusColor, statusLabel;
                if (isLeave) {
                    statusColor = '#2563eb';
                    statusLabel = '📋 On Leave';
                } else if (isAbsent) {
                    statusColor = '#ef4444';
                    statusLabel = '✗ Absent';
                } else if (d.is_live) {
                    statusColor = '#10b981';
                    statusLabel = '<span class="log-live-dot" style="margin-right:5px;"></span>Active';
                } else if (!d.check_out_time) {
                    statusColor = '#f59e0b';
                    statusLabel = '⏸ Paused';
                } else {
                    statusColor = '#64748b';
                    statusLabel = '✓ Completed';
                }

                var cinDisplay = d.check_in_time || '--';
                var coutDisplay = d.check_out_time ? d.check_out_time :
                    (d.check_in_time && !isLeave && !isAbsent) ? 'Still working' : '--';

                var summaryBar = `
                    <div class="seg-summary-bar">
                        <div class="seg-summary-item">
                            <span class="seg-sum-label">Total Duration</span>
                            <span class="seg-sum-val" id="live-dur-${attId}" style="color:#e11d48;">${d.duration_fmt}</span>
                        </div>
                        <div class="seg-summary-item">
                            <span class="seg-sum-label">Check In</span>
                            <span class="seg-sum-val">${cinDisplay}</span>
                        </div>
                        <div class="seg-summary-item">
                            <span class="seg-sum-label">Check Out</span>
                            <span class="seg-sum-val">${coutDisplay}</span>
                        </div>
                        <div class="seg-summary-item">
                            <span class="seg-sum-label">Status</span>
                            <span class="seg-sum-val" style="color:${statusColor};">${statusLabel}</span>
                        </div>
                        <div class="seg-summary-item">
                            <span class="seg-sum-label">Segments</span>
                            <span class="seg-sum-val">${d.segments ? d.segments.length : 0}</span>
                        </div>
                    </div>`;

                var segHtml = '';
                if (d.segments && d.segments.length > 0) {
                    var rowsHtml = '';
                    d.segments.forEach(function(seg, idx) {
                        var isLiveSeg = seg.end_action === 'live';
                        var endLabel = isLiveSeg ? '<span class="log-live-badge"><span class="log-live-dot"></span>Running</span>' : seg.end_time;
                        var actionIcon = seg.end_action === 'pause' ? '<i class="fa fa-pause" style="color:#f59e0b;"></i> Paused' :
                            seg.end_action === 'check_out' ? '<i class="fa fa-sign-out" style="color:#64748b;"></i> Checked Out' :
                            '<span class="log-live-badge"><span class="log-live-dot"></span>Live</span>';
                        var durId = isLiveSeg ? 'live-seg-dur-' + attId : '';
                        rowsHtml += `
                            <tr class="seg-row">
                                <td class="seg-num">${seg.seg_num}</td>
                                <td>
                                    <span class="seg-time-badge seg-start">
                                        <i class="fa fa-play"></i> ${seg.start_time}
                                    </span>
                                </td>
                                <td>
                                    <span class="seg-time-badge ${isLiveSeg ? 'seg-live' : 'seg-end'}">
                                        ${isLiveSeg ? '<i class="fa fa-circle" style="animation:livePulse 1.2s infinite;"></i>' : '<i class="fa fa-stop"></i>'} ${endLabel}
                                    </span>
                                </td>
                                <td class="seg-dur ${isLiveSeg ? 'seg-dur-live' : ''}" id="${durId}">
                                    ${isLiveSeg ? '<span class="log-live-badge"><span class="log-live-dot"></span></span> ' : ''}${seg.duration}
                                </td>
                                <td class="seg-action">${actionIcon}</td>
                                <td class="seg-ip">
                                    <div class="seg-ip-row">
                                        <i class="fa fa-sign-in" style="color:#10b981;font-size:10px;"></i>
                                        <span>${seg.start_ip !== '-' ? seg.start_ip : '<span style=\'color:#94a3b8\'>—</span>'}</span>
                                    </div>
                                    ${seg.end_action !== 'live' ? `<div class="seg-ip-row" style="margin-top:3px;">
                                        <i class="fa fa-${seg.end_action === 'pause' ? 'pause' : 'sign-out'}" style="color:#f59e0b;font-size:10px;"></i>
                                        <span>${seg.end_ip !== '-' ? seg.end_ip : '<span style=\'color:#94a3b8\'>—</span>'}</span>
                                    </div>` : ''}
                                </td>
                                <td class="seg-loc">
                                    <div class="seg-ip-row">
                                        <i class="fa fa-map-marker" style="color:#10b981;font-size:10px;"></i>
                                        <span>${seg.start_loc !== '-' ? seg.start_loc : '<span style=\'color:#94a3b8\'>—</span>'}</span>
                                    </div>
                                    ${seg.end_action !== 'live' ? `<div class="seg-ip-row" style="margin-top:3px;">
                                        <i class="fa fa-map-marker" style="color:#f59e0b;font-size:10px;"></i>
                                        <span>${seg.end_loc !== '-' ? seg.end_loc : '<span style=\'color:#94a3b8\'>—</span>'}</span>
                                    </div>` : ''}
                                </td>
                            </tr>`;
                    });

                    segHtml = `
                        <div class="log-section-title" style="margin-top:16px;"><i class="fa fa-list-ul"></i> Working Segments</div>
                        <div class="seg-table-wrap">
                            <table class="seg-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Duration</th>
                                        <th>Event</th>
                                        <th><i class="fa fa-globe"></i> IP Address</th>
                                        <th><i class="fa fa-map-marker"></i> Location</th>
                                    </tr>
                                </thead>
                                <tbody>${rowsHtml}</tbody>
                            </table>
                        </div>`;
                } else {
                    segHtml = `<div style="text-align:center;padding:20px 0;color:#94a3b8;font-size:13px;font-weight:600;">
                        <i class="fa fa-info-circle" style="margin-right:6px;"></i>
                        No detailed log yet. Logs are recorded from the next check-in onwards.
                    </div>`;
                }

                $content.html(summaryBar + segHtml).show();
                logLoaded[attId] = true;

                // Live counters
                if (d.is_live) {
                    var totalSecs = parseInt(d.duration_secs);
                    var lastSeg = d.segments && d.segments.length > 0 ? d.segments[d.segments.length - 1] : null;
                    var segSecs = lastSeg && lastSeg.end_action === 'live' ? parseInt(lastSeg.duration_secs) : 0;

                    setInterval(function() {
                        totalSecs++;
                        segSecs++;

                        function fmt(s) {
                            var h = Math.floor(s / 3600),
                                m = Math.floor((s % 3600) / 60),
                                sc = s % 60;
                            return String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm ' + String(sc).padStart(2, '0') + 's';
                        }
                        $('#live-dur-' + attId).text(fmt(totalSecs));
                        $('#live-seg-dur-' + attId).html('<span class="log-live-badge"><span class="log-live-dot"></span></span> ' + fmt(segSecs));
                    }, 1000);
                }
            },
            error: function() {
                $loading.hide();
                $content.html('<div style="color:#ef4444;font-weight:600;padding:20px;"><i class="fa fa-wifi"></i> Could not load log data.</div>').show();
            }
        });
    });

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
            html += '<div class="work-gallery-item" onclick="window.open(\'' + url + '\')">';
            html += '<img src="' + url + '" loading="lazy">';
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
    };
</script>

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
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.25s ease;
        border: 1.5px solid #e2e8f0;
    }

    .work-gallery-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.15);
        border-color: #6366f1;
    }

    .work-gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .work-gallery-item:hover img {
        transform: scale(1.15);
        filter: brightness(0.7);
    }

    .item-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(100, 33, 33, 0) 50%, rgba(196, 116, 116, 0.8) 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 20px;
        opacity: 0;
        transition: 0.4s ease;
    }

    .work-gallery-item:hover .item-overlay {
        opacity: 1;
    }

    .item-info {
        transform: translateY(15px);
        transition: 0.5s cubic-bezier(0.23, 1, 0.32, 1);
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
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        opacity: 0;
        transition: 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .work-gallery-item:hover .item-overlay i {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }

    /* ── Summary bar ── */
    .seg-summary-bar {
        display: flex;
        gap: 0;
        background: #fff;
        border: 1.5px solid #f1f5f9;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 16px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .seg-summary-item {
        flex: 1;
        padding: 14px 18px;
        border-right: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .seg-summary-item:last-child {
        border-right: none;
    }

    .seg-sum-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
    }

    .seg-sum-val {
        font-size: 15px;
        font-weight: 800;
        color: #0f172a;
    }

    /* ── Segment table ── */
    .seg-table-wrap {
        overflow-x: auto;
        border-radius: 12px;
        border: 1.5px solid #f1f5f9;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    }

    .seg-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .seg-table thead tr {
        background: #f8fafc;
    }

    .seg-table th {
        padding: 11px 14px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        border-bottom: 1.5px solid #f1f5f9;
        text-align: left;
        white-space: nowrap;
    }

    .seg-row {
        border-bottom: 1px solid #f8fafc;
        transition: background 0.15s;
    }

    .seg-row:last-child {
        border-bottom: none;
    }

    .seg-row:hover {
        background: #fafbff;
    }

    .seg-row td {
        padding: 12px 14px;
        vertical-align: middle !important;
    }

    .seg-num {
        width: 36px;
        text-align: center;
        font-weight: 800;
        color: #cbd5e1;
        font-size: 12px;
    }

    .seg-time-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-weight: 700;
        font-size: 13px;
        padding: 4px 10px;
        border-radius: 8px;
        white-space: nowrap;
    }

    .seg-start {
        background: #dcfce7;
        color: #15803d;
    }

    .seg-end {
        background: #f1f5f9;
        color: #475569;
    }

    .seg-live {
        background: #fef3c7;
        color: #d97706;
    }

    .seg-dur {
        font-weight: 800;
        color: #1e293b;
        white-space: nowrap;
    }

    .seg-dur-live {
        color: #e11d48;
    }

    .seg-action {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        white-space: nowrap;
    }

    .seg-ip,
    .seg-loc {
        font-size: 11px;
        color: #475569;
        font-weight: 600;
    }

    .seg-ip-row {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media print {

        /* Hide UI components not needed in the report */
        .modern-topbar,
        .modern-sidebar,
        #sidebar,
        .page-header-premium,
        #filter-section,
        .pagination-premium,
        .btn-premium-add,
        .card-hdr button,
        .card-hdr i {
            display: none !important;
        }

        /* Reset the wrapper and body layout to utilize the full page width */
        #page-wrapper,
        body,
        html {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .page-wrapper {
            padding: 0 !important;
        }

        .premium-card {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* For the detailed activity table header */
        .card-hdr h3 {
            display: block !important;
            text-align: center;
            font-size: 20px;
            margin-bottom: 20px;
            width: 100%;
        }
    }
</style>