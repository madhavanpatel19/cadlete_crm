<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($con)) {
    include(__DIR__ . "/../../includes/db.php");
}

if (isset($_POST['ajax_delete_announcement']) || isset($_GET['ajax_delete_announcement'])) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    if (!isset($_SESSION['admin_email'])) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }

    $delete_id = isset($_POST['ajax_delete_announcement'])
        ? $_POST['ajax_delete_announcement']
        : $_GET['ajax_delete_announcement'];

    $delete_id    = intval($delete_id);
    $now          = date('Y-m-d H:i:s');
    $delete_success = mysqli_query($con, "UPDATE announcements SET deleted_at = '$now' WHERE id = $delete_id AND deleted_at IS NULL");

    echo json_encode([
        "status" => ($delete_success && mysqli_affected_rows($con) > 0) ? "success" : "error"
    ]);
    exit;
}

if (isset($_POST['ajax_toggle_status'])) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    if (!isset($_SESSION['admin_email'])) {
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit;
    }
    $toggle_id = mysqli_real_escape_string($con, $_POST['ajax_toggle_status']);
    $new_status = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    if ($new_status == 1) {
        $check_expire = mysqli_query($con, "SELECT end_date FROM announcements WHERE id='$toggle_id'");
        if ($check_expire && $row_exp = mysqli_fetch_assoc($check_expire)) {
            if (!empty($row_exp['end_date']) && strtotime($row_exp['end_date']) <= time()) {
                // If activating an expired announcement, clear the end_date so it stays active
                $toggle_query = "UPDATE announcements SET is_active='1', end_date=NULL WHERE id='$toggle_id'";
                $toggle_success = mysqli_query($con, $toggle_query);

                echo json_encode([
                    "status" => $toggle_success ? "success" : "error",
                    "is_active" => 1
                ]);
                exit;
            }
        }
    }

    $toggle_query = "UPDATE announcements SET is_active='$new_status' WHERE id='$toggle_id'";
    $toggle_success = mysqli_query($con, $toggle_query);

    echo json_encode([
        "status" => $toggle_success ? "success" : "error",
        "is_active" => $new_status
    ]);
    exit;
}

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
} else {
    $announcement_error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_announcement'])) {
        $announcement_title = trim($_POST['announcement_title']);
        $announcement_message = trim($_POST['announcement_message']);
        $publish_date = !empty($_POST['publish_date']) ? date('Y-m-d H:i:s', strtotime($_POST['publish_date'])) : date('Y-m-d H:i:s');
        $end_date_val = !empty($_POST['end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['end_date'])) : null;

        if ($announcement_title === '' || $announcement_message === '') {
            $announcement_error = 'Title and message are required.';
        } else {
            $announcement_title = mysqli_real_escape_string($con, $announcement_title);
            $announcement_message = mysqli_real_escape_string($con, $announcement_message);
            $publish_date = mysqli_real_escape_string($con, $publish_date);
            $end_date_sql = $end_date_val ? "'" . mysqli_real_escape_string($con, $end_date_val) . "'" : "NULL";

            $insert = "INSERT INTO announcements (title, message, publish_date, end_date, created_at)
                       VALUES ('$announcement_title', '$announcement_message', '$publish_date', $end_date_sql, NOW())";

            $run = mysqli_query($con, $insert);

            if ($run) {
                $_SESSION['announcement_flash'] = [
                    'type' => 'success',
                    'message' => 'Announcement posted successfully!'
                ];

                echo "<script>window.location.replace('index.php?announcement');</script>";
                exit;
            }

            $announcement_error = 'Unable to save the announcement. Please try again.';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_announcement'])) {
        $edit_id = intval($_POST['edit_id']);
        $announcement_title = trim($_POST['announcement_title']);
        $announcement_message = trim($_POST['announcement_message']);
        $publish_date = !empty($_POST['publish_date']) ? date('Y-m-d H:i:s', strtotime($_POST['publish_date'])) : date('Y-m-d H:i:s');
        $end_date_val = !empty($_POST['end_date']) ? date('Y-m-d H:i:s', strtotime($_POST['end_date'])) : null;

        if ($announcement_title === '' || $announcement_message === '') {
            $announcement_error = 'Title and message are required.';
        } else {
            $announcement_title = mysqli_real_escape_string($con, $announcement_title);
            $announcement_message = mysqli_real_escape_string($con, $announcement_message);
            $publish_date = mysqli_real_escape_string($con, $publish_date);
            $end_date_sql = $end_date_val ? "'" . mysqli_real_escape_string($con, $end_date_val) . "'" : "NULL";

            $update = "UPDATE announcements SET title='$announcement_title', message='$announcement_message', publish_date='$publish_date', end_date=$end_date_sql WHERE id='$edit_id'";
            $run = mysqli_query($con, $update);

            if ($run) {
                $_SESSION['announcement_flash'] = [
                    'type' => 'success',
                    'message' => 'Announcement updated successfully!'
                ];

                echo "<script>window.location.replace('index.php?announcement');</script>";
                exit;
            }

            $announcement_error = 'Unable to update the announcement. Please try again.';
        }
    }

    $announcement_flash = null;
    if (isset($_SESSION['announcement_flash'])) {
        $announcement_flash = $_SESSION['announcement_flash'];
        unset($_SESSION['announcement_flash']);
    }
?>

    <div class="page-wrapper premium-ui-enabled">
        <div class="page-header-premium">
            <h1></h1>
            <?php if (canAdminAccess('announcement_insert')): ?>
                <button class="btn-premium-add" data-toggle="modal" data-target="#addWorksheetModal" type="button">
                    <i class="fa fa-plus-circle"></i> Add Announcement
                </button>
            <?php endif; ?>
        </div>

        <style>
            .table-premium th,
            .table-premium td {
                vertical-align: middle !important;
                text-align: center !important;
            }

            .table-premium th:first-child,
            .table-premium td:first-child {
                text-align: left !important;
                padding-left: 24px !important;
            }

            .table-premium th:nth-child(2),
            .table-premium td:nth-child(2),
            .table-premium th:nth-child(3),
            .table-premium td:nth-child(3),
            .table-premium th:nth-child(4),
            .table-premium td:nth-child(4) {
                text-align: center !important;
            }

            .premium-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 16px 24px;
                border-radius: 16px;
                display: flex;
                align-items: center;
                gap: 12px;
                z-index: 9999;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                color: white;
                font-weight: 600;
                transform: translateX(120%);
                transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            }

            .premium-notification.active {
                transform: translateX(0);
            }

            .notification-success {
                background: rgba(16, 185, 129, 0.9);
            }

            .notification-error {
                background: rgba(239, 68, 68, 0.9);
            }

            .premium-confirm-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(8px);
                z-index: 9999;
                justify-content: center;
                align-items: center;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .premium-confirm-overlay.active {
                display: flex;
                opacity: 1;
            }

            .premium-confirm-modal {
                background: #fff;
                width: 100%;
                max-width: 400px;
                border-radius: 20px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                overflow: hidden;
                transform: scale(0.9);
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
                padding: 30px;
                text-align: center;
            }

            .premium-confirm-overlay.active .premium-confirm-modal {
                transform: scale(1);
            }

            .confirm-icon-box {
                width: 60px;
                height: 60px;
                background: #fee2e2;
                color: #ef4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                margin: 0 auto 20px auto;
                animation: pulseDanger 2s infinite;
            }

            @keyframes pulseDanger {
                0% {
                    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
                }

                70% {
                    box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
                }

                100% {
                    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                }
            }

            .confirm-btn-cancel {
                flex: 1;
                padding: 12px;
                border-radius: 12px;
                background: #f1f5f9;
                color: #64748b;
                border: none;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s;
            }

            .confirm-btn-delete {
                flex: 1;
                padding: 12px;
                border-radius: 12px;
                background: #ef4444;
                color: #fff;
                border: none;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s;
                box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
            }

            .confirm-btn-delete:hover {
                background: #dc2626;
                transform: translateY(-1px);
            }

            /* Premium Action Button Styles */
            .p-btn-action {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 1px solid transparent;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
                background: #fff;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            }

            .p-btn-delete {
                color: #ef4444;
                border-color: #fee2e2;
                background: #fef2f2;
            }

            .p-btn-delete:hover {
                background: #ef4444;
                color: #fff;
                border-color: #ef4444;
                transform: translateY(-2px);
                box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2), 0 2px 4px -1px rgba(239, 68, 68, 0.1);
            }

            /* New List Layout Styles */
            .announcement-list-container {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                overflow: hidden;
                margin-bottom: 30px;
            }

            .announcement-header-row {
                display: grid;
                grid-template-columns: 2.5fr 1fr 1fr 120px;
                padding: 16px 24px;
                background: #f8fafc;
                border-bottom: 1.5px solid #e2e8f0;
                font-size: 13px;
                font-weight: 700;
                color: #475569;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .announcement-item-row {
                display: grid;
                grid-template-columns: 2.5fr 1fr 1fr 120px;
                padding: 20px 24px;
                align-items: center;
                border-bottom: 1px solid #f1f5f9;
                background: #fff;
                transition: background-color 0.2s;
            }

            .announcement-item-row:hover {
                background: #f8fafc;
            }

            .announcement-item-row:last-child {
                border-bottom: none;
            }

            .announcement-left {
                display: flex;
                align-items: center;
                gap: 16px;
                padding-right: 20px;
            }

            .announcement-icon-box {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                flex-shrink: 0;
            }

            .announcement-info h4 {
                margin: 0 0 6px 0;
                font-size: 15px;
                font-weight: 700;
                color: #1e293b;
            }

            .announcement-info p {
                margin: 0;
                font-size: 13px;
                color: #64748b;
                display: -webkit-box;
                -webkit-line-clamp: 1;
                line-clamp: 1;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .announcement-date-col {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .announcement-date-val {
                font-size: 13px;
                color: #475569;
                font-weight: 500;
            }

            .announcement-date-author {
                font-size: 12px;
                color: #94a3b8;
            }

            .announcement-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                background: #ecfdf5;
                color: #10b981;
                width: fit-content;
            }

            .announcement-status-badge.badge-scheduled {
                background: #fffbeb;
                color: #f59e0b;
            }

            .status-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #10b981;
            }

            .status-dot.dot-scheduled {
                background: #f59e0b;
            }

            .announcement-actions {
                display: flex;
                gap: 8px;
            }

            .action-btn {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                background: #fff;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
            }

            .action-btn:hover {
                border-color: #cbd5e1;
                color: #1e293b;
                background: #f8fafc;
            }

            .action-btn.active-state {
                color: #10b981;
            }

            .action-btn.inactive-state {
                color: #94a3b8;
            }

            .announcement-pagination {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 24px;
                background: #fff;
                border-top: 1px solid #f1f5f9;
            }

            .pagination-info {
                font-size: 13px;
                color: #64748b;
            }

            .pagination-controls {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .page-btn {
                width: 32px;
                height: 32px;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
                background: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                color: #475569;
                cursor: pointer;
                transition: all 0.2s;
            }

            .page-btn.active {
                border-color: #3b82f6;
                color: #3b82f6;
                background: #eff6ff;
            }

            .page-btn:hover:not(.active) {
                background: #f8fafc;
            }

            .per-page-select {
                padding: 6px 12px;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
                font-size: 13px;
                color: #475569;
                outline: none;
                cursor: pointer;
                background: #fff;
                margin-left: 8px;
            }

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
            }

            .page-link:hover:not(.disabled) {
                background: #DD2127;
                color: #FFEAEB;
                border-color: #DD2127;
            }

            .page-link.active {
                background: #FFEAEB;
                color: #DD2127;
                border-color: #DD2127;
                text-decoration: none !important;
            }

            .page-link.disabled {
                opacity: 0.5;
                pointer-events: none;
                background: #f8fafc;
            }
        </style>

        <div class="announcement-list-container">
            <div class="card-hdr" style="padding: 20px 24px; background:var(--p-bg-header);color:white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-bullhorn"></i>
                <h3 style="margin: 0; font-size: 16px; font-weight: 700;">Announcements</h3>
            </div>

            <div class="table-responsive">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th style="padding-left: 24px;">Title</th>
                            <th style="text-align: center;">Posted On</th>
                            <th style="text-align: center;">Status</th>
                            <th style="text-align: center;">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        /* ==============================
                           PAGINATION SETUP & QUERIES
                        ============================== */
                        $limit = 5; // Number of records per page
                        $page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
                        $offset = ($page - 1) * $limit;

                        // Count total records
                        $countSql = "SELECT COUNT(*) as total FROM announcements WHERE deleted_at IS NULL";
                        $countResult = mysqli_query($con, $countSql);
                        $totalRecords = 0;
                        if ($countResult) {
                            $countRow = mysqli_fetch_assoc($countResult);
                            $totalRecords = $countRow['total'];
                        }
                        $totalPages = ceil($totalRecords / $limit);

                        $i = $offset; // Adjust numbering
                        // Auto deactivate expired announcements
                        mysqli_query($con, "UPDATE announcements SET is_active = '0' WHERE end_date IS NOT NULL AND end_date <= NOW() AND is_active = '1' AND deleted_at IS NULL");

                        $get_announcements = "SELECT * FROM announcements WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT $offset, $limit";
                        $run_announcements = mysqli_query($con, $get_announcements);

                        $icon_classes = [
                            ['bg' => '#eff6ff', 'color' => '#3b82f6', 'icon' => 'fa-bullhorn'],
                            ['bg' => '#ecfdf5', 'color' => '#10b981', 'icon' => 'fa-rocket'],
                            ['bg' => '#fff7ed', 'color' => '#f97316', 'icon' => 'fa-calendar-o'],
                            ['bg' => '#f5f3ff', 'color' => '#8b5cf6', 'icon' => 'fa-graduation-cap'],
                            ['bg' => '#fdf2f8', 'color' => '#ec4899', 'icon' => 'fa-gift'],
                            ['bg' => '#f0fdf4', 'color' => '#22c55e', 'icon' => 'fa-shield']
                        ];

                        if (mysqli_num_rows($run_announcements) > 0) {
                            while ($row_announcements = mysqli_fetch_array($run_announcements)) {
                                $announcement_id = $row_announcements['id'];
                                $announcement_title = $row_announcements['title'];
                                $announcement_message = $row_announcements['message'];
                                $announcement_date = !empty($row_announcements['publish_date']) ? $row_announcements['publish_date'] : $row_announcements['created_at'];
                                $is_active = isset($row_announcements['is_active']) ? $row_announcements['is_active'] : 1;
                                $end_date = !empty($row_announcements['end_date']) ? $row_announcements['end_date'] : null;
                                $i++;

                                $icon_data = $icon_classes[$i % 6];

                                $is_scheduled = strtotime($announcement_date) > time();
                                $is_expired = $end_date && strtotime($end_date) <= time();

                                if ($is_expired) {
                                    $badge_class = '';
                                    $badge_text = 'Expired';
                                    $dot_class = '';
                                    $is_grey = true;
                                    $toggle_is_active = 0;
                                } else if ($is_active == 0) {
                                    $badge_class = '';
                                    $badge_text = 'Inactive';
                                    $dot_class = '';
                                    $is_grey = true;
                                    $toggle_is_active = 0;
                                } else {
                                    $badge_class = $is_scheduled ? 'badge-scheduled' : '';
                                    $badge_text = $is_scheduled ? 'Scheduled' : 'Published';
                                    $dot_class = $is_scheduled ? 'dot-scheduled' : '';
                                    $is_grey = false;
                                    $toggle_is_active = 1;
                                }
                        ?>
                                <tr data-announcement-row="<?php echo $announcement_id; ?>">
                                    <td style="padding-left: 24px; vertical-align: middle;">
                                        <div class="announcement-left" style="display: flex; align-items: center; gap: 16px;">
                                            <div class="announcement-icon-box" style="background: <?php echo $icon_data['bg']; ?>; color: <?php echo $icon_data['color']; ?>;">
                                                <i class="fa <?php echo $icon_data['icon']; ?>"></i>
                                            </div>
                                            <div class="announcement-info">
                                                <h4><?php echo htmlspecialchars($announcement_title); ?></h4>
                                                <p><?php echo htmlspecialchars($announcement_message); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div class="announcement-date-col" style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                                            <div class="announcement-date-val"><?php echo date('d M Y, h:i A', strtotime($announcement_date)); ?></div>
                                            <div class="announcement-date-author">by Admin</div>
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div style="display: flex; justify-content: center; align-items: center;">
                                            <div class="announcement-status-badge <?php echo $badge_class; ?>" style="<?php echo $is_grey ? 'background: #f1f5f9; color: #64748b;' : ''; ?>">
                                                <span class="status-dot <?php echo $dot_class; ?>" style="<?php echo $is_grey ? 'background: #94a3b8;' : ''; ?>"></span> <?php echo $badge_text; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: middle;">
                                        <div class="announcement-actions" style="display: flex; justify-content: center; align-items: center; gap: 8px;">
                                            <?php if (canAdminAccess('announcement_update')): ?>
                                                <button type="button" class="btn-icon-premium" style="color: <?php echo $toggle_is_active == 1 ? '#10b981' : '#94a3b8'; ?>;" title="<?php echo $toggle_is_active == 1 ? 'Set Inactive' : 'Set Active'; ?>" onclick="toggleStatus(<?php echo $announcement_id; ?>, <?php echo $toggle_is_active == 1 ? 0 : 1; ?>)">
                                                    <i class="fa <?php echo $toggle_is_active == 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?>" style="font-size: 16px;"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php
                                            $edit_data = json_encode([
                                                "id" => $announcement_id,
                                                "title" => $announcement_title,
                                                "message" => $announcement_message,
                                                "publish_date" => date("Y-m-d\TH:i", strtotime($announcement_date)),
                                                "end_date" => !empty($row_announcements['end_date']) ? date("Y-m-d\TH:i", strtotime($row_announcements['end_date'])) : ""
                                            ]);
                                            $safe_edit_data = htmlspecialchars($edit_data, ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <?php if (canAdminAccess('announcement_update')): ?>
                                                <button type="button" class="btn-icon-premium btn-icon-edit" onclick="openEditModal(<?php echo $safe_edit_data; ?>)" title="Edit Announcement">
                                                    <i class="fa fa-pencil"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (canAdminAccess('announcement_delete')): ?>
                                                <button type="button" class="btn-icon-premium btn-icon-delete" title="Delete Announcement" onclick="showDeleteConfirm(<?php echo $announcement_id; ?>)">
                                                    <i class="fa fa-trash-o"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 60px 20px;">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                        <div style="width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                                            <i class="fa fa-folder-open-o" style="font-size: 28px; color: #cbd5e1;"></i>
                                        </div>
                                        <div style="font-size: 15px; font-weight: 700; color: #64748b; margin-bottom: 4px;">No announcements.</div>
                                        <div style="font-size: 13px; color: #94a3b8;">No notices to show right now.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

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

    <div class="modal fade" id="addWorksheetModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;">
                <form method="post" action="index.php?announcement">
                    <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                        <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                        <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; text-align: left !important; flex: 1;">
                            <div style="background: #dd2127; color:white;width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa fa-bullhorn" style="font-size: 14px;"></i>
                            </div>
                            New Notice
                        </h4>
                    </div>

                    <div class="modal-body" style="padding: 30px 25px; background: #fff;">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Announcement Title</label>
                            <input type="text" name="announcement_title" class="form-control" required placeholder="Enter a concise title..." style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Publish Date & Time (Optional)</label>
                            <input type="datetime-local" name="publish_date" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">End Date & Time (Optional)</label>
                            <input type="datetime-local" name="end_date" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Detailed Message</label>
                            <textarea name="announcement_message" class="form-control" rows="6" required placeholder="Type the announcement content here..." style="background: #f8fafc; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 15px 20px; width: 100%; color: #0f172a; font-size: 14px; font-weight: 600; outline: none; transition: all 0.3s; resize: none;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; padding: 20px 25px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" name="submit_announcement" class="btn-premium-add">
                            <i class="fa fa-paper-plane"></i> Post Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editWorksheetModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;">
                <form method="post" action="index.php?announcement">
                    <input type="hidden" name="edit_id" id="edit_announcement_id">
                    <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                        <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                        <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; text-align: left !important; flex: 1;">
                            <div style="background: #dd2127; color:white;width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa fa-bullhorn" style="font-size: 14px;"></i>
                            </div>
                            Edit Announcement
                        </h4>
                    </div>

                    <div class="modal-body" style="padding: 30px 25px; background: #fff;">
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Announcement Title</label>
                            <input type="text" name="announcement_title" id="edit_announcement_title" class="form-control" required placeholder="Enter a concise title..." style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Publish Date & Time (Optional)</label>
                            <input type="datetime-local" name="publish_date" id="edit_publish_date" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">End Date & Time (Optional)</label>
                            <input type="datetime-local" name="end_date" id="edit_end_date" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label style="font-weight: 700; color: #475569; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Detailed Message</label>
                            <textarea name="announcement_message" id="edit_announcement_message" class="form-control" rows="6" required placeholder="Type the announcement content here..." style="background: #f8fafc; border-radius: 14px; border: 1.5px solid #e2e8f0; padding: 15px 20px; width: 100%; color: #0f172a; font-size: 14px; font-weight: 600; outline: none; transition: all 0.3s; resize: none;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; padding: 20px 25px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" name="update_announcement" class="btn-premium-add">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="premium-confirm-overlay" id="deleteConfirmOverlay">
        <div class="premium-confirm-modal">
            <div class="confirm-icon-box">
                <i class="fa fa-trash"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-weight: 700; color: #0f172a;">Delete Announcement?</h3>
            <p style="margin: 0 0 25px 0; font-size: 14px; color: #64748b;">This action will permanently remove this announcement. This cannot be undone.</p>
            <div style="display: flex; gap: 12px;">
                <button class="confirm-btn-cancel" onclick="closeDeleteConfirm()" type="button">Cancel</button>
                <button class="confirm-btn-delete" id="confirmDeleteBtn" type="button">Delete Now</button>
            </div>
        </div>
    </div>

    <script>
        window.announcementFlash = <?php echo json_encode($announcement_flash); ?>;
        window.announcementFormError = <?php echo json_encode($announcement_error); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            window.showPremiumAlert = function(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `premium-notification notification-${type}`;

                const icon = type === 'success' ?
                    'fa-check-circle' :
                    'fa-exclamation-circle';

                toast.innerHTML = `<i class="fa ${icon}"></i> <span>${message}</span>`;
                document.body.appendChild(toast);

                setTimeout(() => toast.classList.add('active'), 10);

                setTimeout(() => {
                    toast.classList.remove('active');
                    setTimeout(() => toast.remove(), 400);
                }, 3500);
            };

            let currentDeleteId = null;

            const deleteOverlay = document.getElementById('deleteConfirmOverlay');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

            window.openEditModal = function(data) {
                document.getElementById('edit_announcement_id').value = data.id;
                document.getElementById('edit_announcement_title').value = data.title;
                document.getElementById('edit_announcement_message').value = data.message;
                document.getElementById('edit_publish_date').value = data.publish_date || '';
                document.getElementById('edit_end_date').value = data.end_date || '';

                $('#editWorksheetModal').modal('show');
            };

            window.toggleStatus = function(id, newStatus) {
                fetch('pages/announcements/announcement.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: `ajax_toggle_status=${encodeURIComponent(id)}&is_active=${encodeURIComponent(newStatus)}`
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.status === 'success') {
                            showPremiumAlert(newStatus == 1 ? 'Announcement marked as Active' : 'Announcement marked as Inactive');
                            setTimeout(() => location.reload(), 500);
                        } else {
                            throw new Error('Toggle failed');
                        }
                    })
                    .catch(() => showPremiumAlert('Error updating status', 'error'));
            };

            window.showDeleteConfirm = function(id) {
                currentDeleteId = id;
                deleteOverlay.classList.add('active');
            };

            window.closeDeleteConfirm = function() {
                deleteOverlay.classList.remove('active');
                currentDeleteId = null;
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = 'Delete Now';
            };

            deleteOverlay.addEventListener('click', function(e) {
                if (e.target === deleteOverlay) {
                    closeDeleteConfirm();
                }
            });

            confirmDeleteBtn.addEventListener('click', function() {
                if (!currentDeleteId) {
                    return;
                }

                confirmDeleteBtn.disabled = true;
                confirmDeleteBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';

                fetch('pages/announcements/announcement.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: `ajax_delete_announcement=${encodeURIComponent(currentDeleteId)}`
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.status === 'success') {
                            const row = document.querySelector(`[data-announcement-row="${currentDeleteId}"]`);

                            if (row) {
                                row.remove();
                            }

                            closeDeleteConfirm();
                            showPremiumAlert('Announcement deleted successfully');
                            return;
                        }

                        throw new Error('Delete failed');
                    })
                    .catch(() => {
                        showPremiumAlert('Error deleting announcement', 'error');
                        confirmDeleteBtn.disabled = false;
                        confirmDeleteBtn.innerHTML = 'Delete Now';
                    });
            });

            if (window.announcementFlash && window.announcementFlash.message) {
                showPremiumAlert(window.announcementFlash.message, window.announcementFlash.type || 'success');
            }

            if (window.announcementFormError) {
                showPremiumAlert(window.announcementFormError, 'error');
                $('#addWorksheetModal').modal('show');
            }
        });
    </script>

<?php } ?>