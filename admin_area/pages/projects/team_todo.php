<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_employee_portal = (strpos($_SERVER['REQUEST_URI'] ?? '', 'emp_area') !== false) || (isset($_SESSION['emp_id']) && !isset($_SESSION['admin_email']));
if (strpos($_SERVER['REQUEST_URI'] ?? '', 'emp_area') !== false) {
    $is_employee_portal = true;
}
$is_admin_mode = isset($_SESSION['admin_email']) && !$is_employee_portal;

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id == 0) {
    echo "<script>window.location.href='index.php?projects';</script>";
    exit();
}

$get_project = "SELECT cp.*, c.name as client_name FROM client_projects cp JOIN clients c ON cp.client_id = c.id WHERE cp.id = $project_id";
$run_project = mysqli_query($con, $get_project);
$project = mysqli_fetch_assoc($run_project);

if (!$project) {
    echo "<script>window.location.href='index.php?projects';</script>";
    exit();
}

$assigned_employees = array_filter(explode(',', $project['assigned_employees']), function ($id) {
    return !empty(trim($id));
});

?>

<style>
    /* Board & Layout Styling */
    .todo-board {
        display: flex !important;
        flex-direction: row !important;
        gap: 24px !important;
        overflow-x: auto !important;
        padding-bottom: 24px !important;
        align-items: flex-start !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .todo-board::-webkit-scrollbar {
        height: 8px;
    }

    .todo-board::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .todo-board::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .todo-board::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .todo-column {
        min-width: 360px !important;
        max-width: 360px !important;
        flex: 0 0 360px !important;
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 16px !important;
        padding: 22px !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02) !important;
        box-sizing: border-box !important;
    }

    .todo-col-header {
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
        margin-bottom: 18px !important;
        padding-bottom: 12px !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .emp-avatar {
        width: 40px !important;
        height: 40px !important;
        border-radius: 50% !important;
        object-fit: cover !important;
        flex-shrink: 0 !important;
    }

    .emp-avatar-fallback {
        width: 40px !important;
        height: 40px !important;
        border-radius: 50% !important;
        background: #ffeaeb !important;
        color: #dd2127 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: 800 !important;
        font-size: 15px !important;
        flex-shrink: 0 !important;
    }

    .emp-name {
        font-weight: 800 !important;
        font-size: 15px !important;
        color: #0f172a !important;
    }

    .emp-role {
        font-weight: 600 !important;
        font-size: 12px !important;
        color: #64748b !important;
        margin-top: 2px !important;
    }

    .icon-btn {
        background: transparent !important;
        border: none !important;
        color: #94a3b8 !important;
        cursor: pointer !important;
        font-size: 16px !important;
        padding: 5px !important;
        transition: 0.2s !important;
    }

    .icon-btn:hover {
        color: #dd2127 !important;
    }

    .add-task-trigger {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
        color: #64748b !important;
        font-weight: 600 !important;
        font-size: 14px !important;
        cursor: pointer !important;
        padding: 10px 14px !important;
        background: #f8fafc !important;
        border: 1px dashed #cbd5e1 !important;
        border-radius: 10px !important;
        margin-bottom: 14px !important;
        transition: 0.2s !important;
    }

    .add-task-trigger:hover {
        color: #dd2127 !important;
        border-color: #fca5a5 !important;
        background: #ffeaeb !important;
    }

    .add-task-form {
        background: #fafafa !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px !important;
        padding: 14px !important;
        margin-bottom: 14px !important;
    }

    .task-input {
        width: 100% !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 9px 12px !important;
        font-size: 13.5px !important;
        font-weight: 500 !important;
        outline: none !important;
        transition: 0.2s !important;
        box-sizing: border-box !important;
    }

    .task-input:focus {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
    }

    .task-date-input,
    .task-priority-input {
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        padding: 6px 10px !important;
        font-size: 12px !important;
        font-weight: 600 !important;
        color: #475569 !important;
        outline: none !important;
    }

    .task-item {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 12px 0 !important;
        border-bottom: 1px solid #f1f5f9 !important;
        transition: 0.2s !important;
    }

    .task-item:last-child {
        border-bottom: none !important;
    }

    .task-checkbox {
        width: 20px !important;
        height: 20px !important;
        border-radius: 50% !important;
        border: 2px solid #cbd5e1 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        cursor: pointer !important;
        transition: 0.2s !important;
        flex-shrink: 0 !important;
    }

    .task-checkbox:hover {
        border-color: #dd2127 !important;
        background: #ffeaeb !important;
    }

    .task-checkbox i {
        display: none !important;
        color: #fff !important;
        font-size: 10px !important;
    }

    .task-item.completed .task-checkbox {
        background: #dd2127 !important;
        border-color: #dd2127 !important;
    }

    .task-item.completed .task-checkbox i {
        display: block !important;
    }

    .task-name {
        font-size: 14px !important;
        font-weight: 600 !important;
        color: #334155 !important;
        flex: 1 !important;
        transition: 0.2s !important;
    }

    .task-name-clickable {
        cursor: pointer !important;
    }

    .task-name-clickable:hover {
        color: #dd2127 !important;
        text-decoration: underline !important;
    }

    .task-item.completed .task-name {
        text-decoration: line-through !important;
        color: #94a3b8 !important;
    }

    .task-meta {
        display: flex !important;
        align-items: center !important;
        gap: 10px !important;
    }

    .date-badge {
        background: #ffeaeb !important;
        color: #dd2127 !important;
        padding: 3px 8px !important;
        border-radius: 6px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
    }

    .priority-flag {
        font-size: 11px !important;
    }

    .priority-High {
        color: #ef4444 !important;
    }

    .priority-Medium {
        color: #f59e0b !important;
    }

    .priority-Low {
        color: #22c55e !important;
    }

    /* Modal Overlay & Trello Container */
    #taskDetailOverlay {
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

    #taskDetailModal {
        background: #ffffff;
        border-radius: 16px;
        max-width: 900px;
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
        cursor: pointer;
        transition: 0.15s;
    }

    .tdm-list-tag:hover {
        background: #ffeaeb;
        color: #dd2127;
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
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: 0.15s;
    }

    .tdm-icon-btn:hover {
        background: #ffeaeb;
        color: #dd2127;
        border-color: #fca5a5;
    }

    #taskDetailModal .btn-modal-close,
    #commonTaskModal .btn-modal-close {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        transform: none !important;
        flex-shrink: 0 !important;
    }

    #taskDetailModal .btn-modal-close:hover,
    #commonTaskModal .btn-modal-close:hover {
        background: #ffeaeb !important;
        color: #dd2127 !important;
        transform: rotate(90deg) !important;
    }

    .tdm-header {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
        flex-shrink: 0;
    }

    .tdm-check {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        flex-shrink: 0;
        margin-top: 4px;
        transition: 0.2s;
        color: transparent;
        font-size: 12px;
    }

    .tdm-check:hover {
        border-color: #dd2127;
        background: #ffeaeb;
    }

    .tdm-check.td-completed {
        background: #dd2127;
        border-color: #dd2127;
        color: #fff;
    }

    .tdm-title-input {
        width: 100%;
        border: none;
        border-bottom: 2px solid transparent;
        outline: none;
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        resize: none;
        font-family: inherit;
        line-height: 1.35;
        padding: 2px 0;
        background: transparent;
        transition: border-bottom-color 0.2s;
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

    .tdm-meta-row {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
        flex-shrink: 0;
    }

    .tdm-meta-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .tdm-meta-label {
        font-size: 10px;
        font-weight: 800;
        color: #94a3b8;
        letter-spacing: 0.8px;
    }

    .tdm-date-input,
    .tdm-select {
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 7px 12px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        outline: none;
        cursor: pointer;
        background: #fff;
        transition: 0.15s;
    }

    .tdm-date-input:focus,
    .tdm-select:focus {
        border-color: #dd2127;
        box-shadow: 0 0 0 3px #ffeaeb;
    }

    .tdm-section {
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }

    .tdm-section-title {
        font-size: 14px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-shrink: 0;
    }

    .tdm-section-title i {
        color: #dd2127;
        font-size: 15px;
    }

    .tdm-desc {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 14px 16px;
        font-size: 13.5px;
        font-family: inherit;
        color: #334155;
        resize: none;
        outline: none;
        transition: 0.15s;
        line-height: 1.6;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        background: #fff;
    }

    .tdm-desc:focus,
    .tdm-desc.focused {
        border-color: #dd2127;
        box-shadow: 0 0 0 3px #ffeaeb;
    }

    .tdm-comment-add {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        margin-bottom: 16px;
        flex-shrink: 0;
    }

    .tdm-comment-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #dd2127;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 13px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .tdm-comment-textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 13px;
        font-family: inherit;
        color: #334155;
        resize: vertical;
        outline: none;
        transition: 0.15s;
        background: #fff;
    }

    .tdm-comment-textarea:focus,
    .tdm-comment-textarea.focused {
        border-color: #dd2127;
        box-shadow: 0 0 0 3px #ffeaeb;
    }

    .tdm-save-btn {
        background: #dd2127;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.15s;
    }

    .tdm-save-btn:hover {
        background: #b91c1c;
    }

    .tdm-activity {
        display: flex;
        flex-direction: column;
        gap: 14px;
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding-right: 4px;
    }

    .td-act-system {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        color: #64748b;
    }

    .td-act-dot {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #ffeaeb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #dd2127;
        flex-shrink: 0;
    }

    .td-act-item {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .td-act-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #dd2127;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 11px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .td-act-body {
        flex: 1;
        min-width: 0;
    }

    .td-act-header {
        font-size: 12.5px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .td-act-header small {
        font-weight: 400;
        color: #94a3b8;
        margin-left: 6px;
    }

    .td-act-comment {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 13px;
        color: #334155;
        line-height: 1.5;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    .td-comment-attachment-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 6px 10px;
        margin-top: 6px;
        max-width: 100%;
        box-sizing: border-box;
        transition: 0.15s;
    }

    .td-comment-attachment-pill:hover {
        background: #ffeaeb;
        border-color: #fca5a5;
    }

    .td-comment-attachment-link {
        color: #0f172a;
        font-weight: 600;
        font-size: 12px;
        text-decoration: none;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: calc(100% - 36px);
        display: inline-block;
    }

    .td-act-actions {
        margin-top: 4px;
        display: flex;
        gap: 8px;
    }

    .td-act-link {
        font-size: 11px;
        color: #94a3b8;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
    }

    .td-act-link:hover {
        color: #dd2127;
        text-decoration: underline;
    }

    @keyframes tdSlideIn {
        from {
            opacity: 0;
            transform: translateY(-12px) scale(0.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @media (max-width: 768px) {
        .tdm-body {
            flex-direction: column;
        }

        .tdm-right {
            width: 100%;
        }

        #taskDetailModal {
            padding: 20px 16px 24px;
        }
    }
</style>

<div class="page-wrapper premium-ui-enabled" style="background: #f8fafc; min-height: calc(100vh - 60px); padding: 20px;">
    <!-- Page Header -->
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 16px;">
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-list-alt" style="color: #dd2127;"></i> Team To-Do
            </h1>
            <div style="padding: 6px 14px; background: #ffeaeb; border: 1px solid #fca5a5; border-radius: 8px; font-size: 13px; font-weight: 700; color: #dd2127; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-building-o" style="color: #dd2127;"></i> <?php echo htmlspecialchars($project['project_name']); ?>
            </div>
        </div>
        <div class="header-actions-premium" style="display: flex; gap: 14px; align-items: center;">
            <?php if ($is_admin_mode) : ?>
                <button type="button" class="btn-premium-add" onclick="openCommonTaskModal()">
                    <i class="fa fa-users"></i> Add Common Task
                </button>
            <?php endif; ?>
            <?php
            $back_url = $is_admin_mode ? "index.php?view_projects&id=" . $project['client_id'] : "index.php?projects";
            ?>
            <a href="<?php echo $back_url; ?>" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Project
            </a>
        </div>
    </div>

    <!-- Kanban Board Columns -->
    <div class="todo-board">
        <?php
        $module_colors = [
            ['primary' => '#4f46e5', 'border' => '#6366f1', 'bg' => '#e0e7ff', 'color' => '#3730a3', 'badge_border' => '#c7d2fe'],
            ['primary' => '#059669', 'border' => '#10b981', 'bg' => '#d1fae5', 'color' => '#065f46', 'badge_border' => '#a7f3d0'],
            ['primary' => '#d97706', 'border' => '#f59e0b', 'bg' => '#fef3c7', 'color' => '#92400e', 'badge_border' => '#fde68a'],
            ['primary' => '#e11d48', 'border' => '#f43f5e', 'bg' => '#ffe4e6', 'color' => '#9f1239', 'badge_border' => '#fecdd3'],
            ['primary' => '#7c3aed', 'border' => '#8b5cf6', 'bg' => '#ede9fe', 'color' => '#5b21b6', 'badge_border' => '#ddd6fe'],
            ['primary' => '#0891b2', 'border' => '#06b6d4', 'bg' => '#cffaff', 'color' => '#155e75', 'badge_border' => '#a5f3fc'],
            ['primary' => '#db2777', 'border' => '#ec4899', 'bg' => '#fce7f3', 'color' => '#9d174d', 'badge_border' => '#fbcfe8'],
            ['primary' => '#2563eb', 'border' => '#3b82f6', 'bg' => '#dbeafe', 'color' => '#1e40af', 'badge_border' => '#bfdbfe'],
        ];

        if (empty($assigned_employees)) {
            echo '<div style="text-align: center; width: 100%; padding: 50px; color: #64748b; font-weight: 600;">No employees assigned to this project.</div>';
        } else {
            foreach ($assigned_employees as $emp_idx => $emp_id) {
                $emp_id = intval($emp_id);
                $get_emp = mysqli_query($con, "SELECT * FROM emp_list WHERE id = $emp_id");
                $emp = mysqli_fetch_assoc($get_emp);
                if (!$emp) continue;

                $emp_name = htmlspecialchars($emp['name']);
                $emp_job = htmlspecialchars(!empty($emp['department']) ? $emp['department'] : (!empty($emp['designation']) ? $emp['designation'] : 'Employee'));
                $emp_img = !empty($emp['employee_image']) ? 'uploads/' . htmlspecialchars($emp['employee_image']) : null;
                $c_theme = $module_colors[$emp_idx % count($module_colors)];
        ?>
                <div class="todo-column" data-emp-id="<?php echo $emp_id; ?>" style="border-left: 4px solid <?php echo $c_theme['border']; ?>;">
                    <div class="todo-col-header" style="border-left: none;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <?php if ($emp_img && file_exists('../../' . $emp_img)) { ?>
                                <img src="<?php echo $emp_img; ?>" class="emp-avatar" style="border: 2px solid <?php echo $c_theme['border']; ?>;">
                            <?php } else { ?>
                                <div class="emp-avatar-fallback" style="background: <?php echo $c_theme['bg']; ?>; color: <?php echo $c_theme['color']; ?>; border: 2px solid <?php echo $c_theme['badge_border']; ?>;"><?php echo strtoupper(substr($emp_name, 0, 1)); ?></div>
                            <?php } ?>
                            <div>
                                <div class="emp-name"><?php echo $emp_name; ?></div>
                                <div class="emp-role"><?php echo $emp_job; ?></div>
                            </div>
                        </div>
                        <button class="icon-btn"><i class="fa fa-ellipsis-v"></i></button>
                    </div>

                    <?php
                    $current_logged_emp_id = intval($_SESSION['emp_id'] ?? 0);
                    $can_add_task_here = $is_admin_mode || ($is_employee_portal && $emp_id == $current_logged_emp_id);
                    ?>
                    <?php if ($can_add_task_here) : ?>
                        <div class="add-task-trigger" onclick="showAddTask(<?php echo $emp_id; ?>)">
                            <i class="fa fa-plus-circle" style="font-size: 16px; color: #dd2127;"></i>
                            <span>Add a task</span>
                        </div>

                        <div class="add-task-form" id="add-form-<?php echo $emp_id; ?>" style="display: none;">
                            <input type="text" class="task-input" id="task-input-<?php echo $emp_id; ?>" placeholder="What needs to be done?">
                            <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                                <input type="date" class="task-date-input" id="task-date-<?php echo $emp_id; ?>">
                                <select class="task-priority-input" id="task-priority-<?php echo $emp_id; ?>">
                                    <option value="Low">Low Priority</option>
                                    <option value="Medium" selected>Medium Priority</option>
                                    <option value="High">High Priority</option>
                                </select>
                                <button class="btn-premium-add" onclick="saveTask(<?php echo $emp_id; ?>)">Add</button>
                                <button class="btn-premium-cancel" onclick="hideAddTask(<?php echo $emp_id; ?>)">Cancel</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="task-list" id="task-list-<?php echo $emp_id; ?>">
                        <div style="text-align: center; padding: 20px;"><i class="fa fa-spinner fa-spin" style="color: #cbd5e1;"></i></div>
                    </div>
                </div>
        <?php
            }
        }
        ?>
    </div>
</div>

<!-- ===== SAME-TO-SAME TRELLO CARD POPUP MODAL ===== -->
<div id="taskDetailOverlay">
    <div id="taskDetailModal">

        <!-- Top Navigation Bar: List Selector Tag (Left) & Actions/Close (Right) -->
        <div class="tdm-top-bar">
            <div class="tdm-list-tag">
                <span id="td-in-list">List Name</span> <i class="fa fa-angle-down" style="font-size: 11px; margin-left: 4px;"></i>
            </div>
            <div class="tdm-top-actions">
                <button class="tdm-icon-btn" title="Project"><i class="fa fa-briefcase"></i> <span id="td-project-name" style="font-size:12px; font-weight:600;">Project</span></button>
                <button class="btn-modal-close" onclick="closeTaskDetail()" title="Close (Esc)"><i class="fa fa-times"></i></button>
            </div>
        </div>

        <!-- 2-Column Split Layout (Matching Reference Photo EXACTLY) -->
        <div class="tdm-body">
 
            <!-- LEFT COLUMN (54% Width): Title, Pills & Description -->
            <div class="tdm-left">
                <!-- Title Row: Check Circle + Large Bold Title Input -->
                <div class="tdm-header">
                    <div id="td-check-circle" class="tdm-check" onclick="tdToggleStatus()" title="Toggle mark complete">
                        <i class="fa fa-check"></i>
                    </div>
                    <textarea id="td-title" class="tdm-title-input" rows="1" placeholder="Task title..."
                        onfocus="this.style.borderBottomColor='#dd2127'"
                        onblur="this.style.borderBottomColor='transparent'; saveTdField('task_name', this.value)"></textarea>
                </div>


                <!-- Meta Controls (Due Date & Priority Inputs) -->
                <div class="tdm-meta-row">
                    <div class="tdm-meta-group">
                        <div class="tdm-meta-label">DUE DATE</div>
                        <input type="date" id="td-due-date" class="tdm-date-input no-global-flatpickr"
                            onchange="saveTdField('due_date', this.value)">
                    </div>
                    <div class="tdm-meta-group">
                        <div class="tdm-meta-label">PRIORITY</div>
                        <select id="td-priority" class="tdm-select" onchange="saveTdField('priority', this.value)">
                            <option value="">— None</option>
                            <option value="Low">🟢 Low</option>
                            <option value="Medium">🟡 Medium</option>
                            <option value="High">🔴 High</option>
                        </select>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="tdm-section">
                    <div class="tdm-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                        <span><i class="fa fa-align-left"></i> Description</span>
                        <div id="td-desc-upload-btn-wrap" style="display: none;">
                            <label style="margin: 0; padding: 4px 10px; background: #ffeaeb; color: #dd2127; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.15s;" title="Upload Document for Description">
                                <i class="fa fa-paperclip"></i> Attach Document
                                <input type="file" id="td-desc-file-input" style="display: none;" onchange="uploadTdDescAttachment(this)">
                            </label>
                        </div>
                    </div>
                    <textarea id="td-description" class="tdm-desc" rows="3"
                        placeholder="Add a more detailed description..."
                        onfocus="this.classList.add('focused')"
                        onblur="this.classList.remove('focused'); saveTdField('description', this.value)"></textarea>

                    <!-- Description Attachments List -->
                    <div id="td-desc-attachments-container" style="margin-top: 10px; display: none;">
                        <div style="font-size: 11px; font-weight: 800; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 6px;">ATTACHED DOCUMENTS</div>
                        <div id="td-desc-attachments-list" style="display: flex; flex-direction: column; gap: 6px;"></div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN (46% Width): Comments and Activity Stream -->
            <div class="tdm-right">
                <div class="tdm-section-title" style="justify-content: space-between; margin-bottom: 16px;">
                    <span><i class="fa fa-comments-o"></i> Comments and activity</span>
                    <span style="font-size: 11px; font-weight: 600; color: #94a3b8; cursor: pointer;">Show details</span>
                </div>

                <!-- Add Comment Box -->
                <div class="tdm-comment-add">
                    <div class="tdm-comment-avatar">A</div>
                    <div style="flex:1;">
                        <textarea id="td-comment-input" class="tdm-comment-textarea" rows="2"
                            placeholder="Write a comment..."
                            onfocus="document.getElementById('td-comment-actions').style.display='flex'; this.classList.add('focused')"
                            onkeydown="if(event.ctrlKey && event.key==='Enter'){tdSubmitComment();}"></textarea>

                        <div id="td-comment-file-preview" style="display:none; font-size:11.5px; color:#dd2127; font-weight:600; margin-top:6px; background:#ffeaeb; padding:4px 8px; border-radius:6px; width:fit-content; align-items:center; gap:6px;">
                            <i class="fa fa-paperclip"></i> <span id="td-comment-file-name">file.pdf</span>
                            <i class="fa fa-times" onclick="clearTdCommentFile()" style="cursor:pointer; margin-left:4px;"></i>
                        </div>

                        <div id="td-comment-actions" style="display:none; margin-top:8px; justify-content:space-between; align-items:center;">
                            <label style="margin:0; font-size:12px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:5px; font-weight:600;" title="Attach Document to Comment">
                                <i class="fa fa-paperclip" style="color:#dd2127; font-size:14px;"></i> Attach File
                                <input type="file" id="td-comment-file" style="display:none;" onchange="handleTdCommentFileSelect(this)">
                            </label>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:11px; color:#94a3b8;">Ctrl + Enter</span>
                                <button id="td-comment-save" class="tdm-save-btn" onclick="tdSubmitComment()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Log & Comments Stream -->
                <div id="td-activity" class="tdm-activity"></div>
            </div>

        </div>
    </div>
</div>

<!-- ===== ADD COMMON TASK MODAL ===== -->
<div id="commonTaskOverlay" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); overflow-y: auto; padding: 40px 16px; font-family: inherit;">
    <div id="commonTaskModal" style="background: #ffffff; border-radius: 16px; max-width: 650px; width: 100%; margin: 0 auto; padding: 24px 28px; box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.25); position: relative; box-sizing: border-box; animation: tdSlideIn .2s ease;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: #ffeaeb; color: #dd2127; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa fa-users"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: #0f172a;">Add Common Task for All Employees</h3>
                    <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">Assign task, description, due date & initial comments across team members</div>
                </div>
            </div>
            <button type="button" class="btn-modal-close" onclick="closeCommonTaskModal()" title="Close"><i class="fa fa-times"></i></button>
        </div>

        <form id="commonTaskForm" onsubmit="submitCommonTask(event)">
            <!-- Task Name -->
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; letter-spacing: 0.5px;">TASK NAME / ACTIVITY TITLE <span style="color: #ef4444;">*</span></label>
                <input type="text" id="ct-task-name" placeholder="What needs to be done across team?" required style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; font-weight: 600; color: #0f172a; outline: none; transition: 0.2s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 3px #ffeaeb';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
            </div>

            <!-- Description -->
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; letter-spacing: 0.5px;">ACTIVITY DESCRIPTION / INSTRUCTIONS</label>
                <textarea id="ct-description" rows="3" placeholder="Add detailed instructions or task description for all assigned employees..." style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 13.5px; color: #334155; outline: none; transition: 0.2s; resize: vertical;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 3px #ffeaeb';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"></textarea>
            </div>

            <!-- Due Date & Priority -->
            <div style="display: flex; gap: 16px; margin-bottom: 16px;">
                <div style="flex: 1;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; letter-spacing: 0.5px;">DUE DATE</label>
                    <input type="date" id="ct-due-date" style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #334155; outline: none; background: #fff;">
                </div>
                <div style="flex: 1;">
                    <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; letter-spacing: 0.5px;">PRIORITY</label>
                    <select id="ct-priority" style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #334155; outline: none; background: #fff;">
                        <option value="Low">🟢 Low Priority</option>
                        <option value="Medium" selected>🟡 Medium Priority</option>
                        <option value="High">🔴 High Priority</option>
                    </select>
                </div>
            </div>

            <!-- Assign Employees Selection -->
            <div style="margin-bottom: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label style="font-size: 12px; font-weight: 800; color: #334155; letter-spacing: 0.5px; margin: 0;">ASSIGN TO EMPLOYEES</label>
                    <label style="font-size: 12px; font-weight: 700; color: #dd2127; cursor: pointer; display: flex; align-items: center; gap: 6px; user-select: none;">
                        <input type="checkbox" id="ct-select-all" checked onchange="toggleSelectAllEmployees(this.checked)" style="accent-color: #dd2127; width: 15px; height: 15px; cursor: pointer;"> Select All
                    </label>
                </div>
                <div id="ct-employee-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; max-height: 160px; overflow-y: auto; padding-right: 4px;">
                    <?php
                    if (!empty($assigned_employees)) {
                        foreach ($assigned_employees as $emp_id) {
                            $emp_id = intval($emp_id);
                            $get_e = mysqli_query($con, "SELECT id, name, designation FROM emp_list WHERE id = $emp_id");
                            if ($e_row = mysqli_fetch_assoc($get_e)) {
                                echo '<label style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: 0.15s; user-select: none;">';
                                echo '<input type="checkbox" class="ct-emp-checkbox" value="' . $e_row['id'] . '" checked style="accent-color: #dd2127; width: 16px; height: 16px; cursor: pointer;">';
                                echo '<div>';
                                echo '<div style="font-size: 13px; font-weight: 700; color: #0f172a;">' . htmlspecialchars($e_row['name']) . '</div>';
                                echo '<div style="font-size: 11px; color: #64748b;">' . htmlspecialchars($e_row['designation'] ?? 'Employee') . '</div>';
                                echo '</div>';
                                echo '</label>';
                            }
                        }
                    } else {
                        echo '<div style="font-size: 12px; color: #94a3b8;">No assigned employees found in project.</div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Initial Comment / Activity Stream Note -->
            <div style="margin-bottom: 22px;">
                <label style="display: block; font-size: 12px; font-weight: 800; color: #334155; margin-bottom: 6px; letter-spacing: 0.5px;">INITIAL COMMENT / ACTIVITY NOTE</label>
                <textarea id="ct-comment" rows="2" placeholder="Add an initial comment to all created tasks (e.g. Please update status before EOD)..." style="width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 13px; color: #334155; outline: none; transition: 0.2s; resize: vertical;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 3px #ffeaeb';" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';"></textarea>
            </div>

            <!-- Footer Actions -->
            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                <button type="button" onclick="closeCommonTaskModal()" class="btn-premium-cancel">Cancel</button>
                <button type="submit" id="ct-submit-btn" class="btn-premium-add"><i class="fa fa-plus-circle"></i> Create Common Task</button>
            </div>
        </form>
    </div>
</div>



<script>
    const projectId = <?php echo $project_id; ?>;
    const _isEmpPortal = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;
    const _ajaxBaseUrl = _isEmpPortal ? '../admin_area/ajax/projects/' : 'ajax/projects/';

    $(document).ready(function() {
        // Load tasks for all columns
        $('.todo-column').each(function() {
            const empId = $(this).data('emp-id');
            loadTasks(empId);
        });

        // Close modal when clicking outside overlay
        $(document).on('click', '#taskDetailOverlay', function(e) {
            if (e.target === this) closeTaskDetail();
        });
        $(document).on('click', '#commonTaskOverlay', function(e) {
            if (e.target === this) closeCommonTaskModal();
        });
    });

    function showAddTask(empId) {
        $(`#add-form-${empId}`).slideDown(200);
        $(`#task-input-${empId}`).focus();
    }

    function hideAddTask(empId) {
        $(`#add-form-${empId}`).slideUp(200);
        $(`#task-input-${empId}`).val('');
        $(`#task-date-${empId}`).val('');
    }

    function loadTasks(empId) {
        const list = $('#task-list-' + empId);
        list.html('<div style="text-align:center;padding:20px;"><i class="fa fa-spinner fa-spin" style="color:#dd2127;font-size:18px;"></i></div>');
        $.ajax({
            url: _ajaxBaseUrl + 'ajax_get_team_todos.php',
            method: 'POST',
            dataType: 'json',
            data: {
                project_id: projectId,
                emp_id: empId
            },
            success: function(res) {
                console.log('loadTasks response for emp ' + empId + ':', res);
                if (typeof res === 'string') {
                    try {
                        res = JSON.parse(res);
                    } catch (e) {}
                }
                if (res && res.success) {
                    renderTasks(empId, res.tasks);
                } else {
                    console.warn('loadTasks error:', res ? res.message : 'unknown');
                    renderTasks(empId, []);
                }
            },
            error: function(xhr, status, err) {
                console.error('loadTasks AJAX error:', status, err, xhr.responseText);
                renderTasks(empId, []);
            }
        });
    }

    function renderTasks(empId, tasks) {
        try {
            const list = $(`#task-list-${empId}`);
            list.empty();

            if (!tasks || !Array.isArray(tasks) || tasks.length === 0) {
                list.html('<div style="color: #94a3b8; font-size: 13px; text-align: center; padding: 15px 0;">No tasks yet</div>');
                return;
            }

            tasks.forEach(task => {
                if (!task) return;
                const isCompleted = task.status == 1;
                const itemClass = isCompleted ? 'task-item completed' : 'task-item';

                let dateBadge = '';
                if (task.due_date && task.due_date !== '0000-00-00' && task.due_date !== '0000-00-00 00:00:00') {
                    try {
                        const due = new Date(task.due_date.replace(/-/g, '/'));
                        if (!isNaN(due.getTime())) {
                            const today = new Date();
                            const tomorrow = new Date();
                            tomorrow.setDate(tomorrow.getDate() + 1);

                            let dateStr = due.toLocaleDateString('en-GB', {
                                day: 'numeric',
                                month: 'short'
                            });
                            if (due.toDateString() === today.toDateString()) {
                                dateStr = 'Today';
                            } else if (due.toDateString() === tomorrow.toDateString()) {
                                dateStr = 'Tomorrow';
                            }
                            dateBadge = `<div class="date-badge">${dateStr}</div>`;
                        }
                    } catch (de) {}
                }

                let priorityHtml = '';
                if (task.priority) {
                    priorityHtml = `<div class="priority-flag priority-${escapeHtml(task.priority)}"><i class="fa fa-flag"></i> ${escapeHtml(task.priority)}</div>`;
                }

                const safeName = escapeHtml(task.task_name || 'Untitled Task');

                const html = `
                    <div class="${itemClass}" data-task-id="${task.id}">
                        <div class="task-checkbox" onclick="toggleTask(${task.id}, ${empId}, ${isCompleted ? 0 : 1})">
                            <i class="fa fa-check"></i>
                        </div>
                        <div class="task-name task-name-clickable" onclick="openTaskDetail(${task.id}, ${empId})" title="Click to view details">${safeName}</div>
                        <div class="task-meta">
                            ${priorityHtml}
                            ${dateBadge}
                            <div class="dropdown">
                                <button class="icon-btn" data-toggle="dropdown"><i class="fa fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-right" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                                    <li><a href="#" onclick="deleteTask(${task.id}, ${empId}); return false;" style="color: #ef4444; font-weight: 600; padding: 10px 20px;"><i class="fa fa-trash-o" style="margin-right: 8px;"></i> Delete</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
                list.append(html);
            });
        } catch (e) {
            console.error("renderTasks error:", e);
            $(`#task-list-${empId}`).html('<div style="color: #94a3b8; font-size: 13px; text-align: center; padding: 15px 0;">No tasks yet</div>');
        }
    }

    function saveTask(empId) {
        const name = $(`#task-input-${empId}`).val().trim();
        const date = $(`#task-date-${empId}`).val();
        const priority = $(`#task-priority-${empId}`).val();

        if (!name) return;

        $.ajax({
            url: _ajaxBaseUrl + 'ajax_add_team_todo.php',
            method: 'POST',
            data: {
                project_id: projectId,
                emp_id: empId,
                task_name: name,
                due_date: date,
                priority: priority
            },
            success: function(res) {
                if (res && res.success) {
                    hideAddTask(empId);
                    loadTasks(empId);
                    if (typeof fetchLiveNotifications === 'function') fetchLiveNotifications();
                } else {
                    Swal.fire("Error", res ? res.message : "Could not add task.", "error");
                }
            }
        });
    }

    function toggleTask(taskId, empId, newStatus) {
        $.ajax({
            url: _ajaxBaseUrl + 'ajax_toggle_team_todo.php',
            method: 'POST',
            data: {
                task_id: taskId,
                status: newStatus
            },
            success: function(res) {
                if (res && res.success) {
                    loadTasks(empId);
                }
            }
        });
    }

    function deleteTask(taskId, empId) {
        Swal.fire({
            title: 'Delete Task?',
            text: "This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dd2127',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: _ajaxBaseUrl + 'ajax_delete_team_todo.php',
                    method: 'POST',
                    data: {
                        task_id: taskId
                    },
                    success: function(res) {
                        if (res && res.success) {
                            loadTasks(empId);
                        }
                    }
                });
            }
        });
    }

    /* ===== TASK DETAIL MODAL FUNCTIONS ===== */
    let _modalTaskId = null;
    let _modalEmpId = null;
    let _modalStatus = 0;

    function openTaskDetail(taskId, empId) {
        _modalTaskId = taskId;
        _modalEmpId = empId;

        // Reset fields
        $('#td-title').val('');
        $('#td-description').val('');
        $('#td-due-date').val('');
        $('#td-priority').val('');
        $('#td-activity').html('<div style="text-align:center;padding:20px;color:#94a3b8;"><i class="fa fa-circle-o-notch fa-spin"></i></div>');
        $('#td-check-circle').removeClass('td-completed');
        $('#td-status-label').text('Mark Complete');
        $('#td-in-list').text('');
        $('#td-desc-attachments-container').hide();
        $('#td-desc-attachments-list').empty();
        $('#td-desc-upload-btn-wrap').hide();
        clearTdCommentFile();

        $('#taskDetailOverlay').fadeIn(200);
        $('body').css('overflow', 'hidden');

        $.ajax({
            url: _ajaxBaseUrl + 'ajax_get_todo_detail.php',
            method: 'POST',
            data: {
                task_id: taskId
            },
            success: function(res) {
                if (!res || !res.success) return;
                const t = res.task;
                _modalStatus = parseInt(t.status);

                const isAdmin = !_isEmpPortal && (res.is_admin === true || res.is_admin === 1);

                // Read-Only Enforcement for Non-Admins / Employees
                const dueDateEl = document.getElementById('td-due-date');
                if (isAdmin) {
                    $('#td-title').prop('readonly', false).css({
                        'pointer-events': 'auto',
                        'border-bottom-color': 'transparent'
                    });
                    $('#td-description').prop('readonly', false).css('background', '#ffffff');
                    $('#td-due-date').prop('disabled', false).css('background', '#ffffff');
                    if (dueDateEl && dueDateEl._flatpickr && dueDateEl._flatpickr.altInput) {
                        dueDateEl._flatpickr.altInput.disabled = false;
                        $(dueDateEl._flatpickr.altInput).css('background', '#ffffff');
                    }
                    $('#td-priority').prop('disabled', false).css('background', '#ffffff');
                    $('#td-desc-upload-btn-wrap').show();
                } else {
                    $('#td-title').prop('readonly', true).css({
                        'pointer-events': 'none',
                        'border-bottom-color': 'transparent'
                    });
                    $('#td-description').prop('readonly', true).css('background', '#f8fafc');
                    $('#td-due-date').prop('disabled', true).css('background', '#f8fafc');
                    if (dueDateEl && dueDateEl._flatpickr && dueDateEl._flatpickr.altInput) {
                        dueDateEl._flatpickr.altInput.disabled = true;
                        $(dueDateEl._flatpickr.altInput).css('background', '#f8fafc');
                    }
                    $('#td-priority').prop('disabled', true).css('background', '#f8fafc');
                    $('#td-desc-upload-btn-wrap').hide();
                }

                $('#td-title').val(t.task_name);
                $('#td-description').val(t.description || '');
                $('#td-due-date').val(t.due_date || '');
                if (dueDateEl && dueDateEl._flatpickr) {
                    dueDateEl._flatpickr.setDate(t.due_date || '', false);
                }
                $('#td-priority').val(t.priority || '');
                $('#td-project-name').text(t.project_name || 'Project');
                const colName = $(`#task-list-${empId}`).closest('.todo-column').find('.emp-name').text();
                $('#td-in-list').text(colName || 'Assigned Task');
                if (_modalStatus === 1) {
                    $('#td-check-circle').addClass('td-completed');
                    $('#td-status-label').text('Mark Pending');
                }

                renderTdDescAttachments(res.attachments || [], isAdmin);
                renderTdActivity(res.comments || [], t, isAdmin);
            }
        });
    }

    function renderTdDescAttachments(attachments, isAdmin) {
        const list = $('#td-desc-attachments-list');
        list.empty();
        if (!attachments || attachments.length === 0) {
            $('#td-desc-attachments-container').hide();
            return;
        }

        $('#td-desc-attachments-container').show();
        attachments.forEach(att => {
            const fileName = escapeHtml(att.file_name || 'Document');
            const rawPath = att.file_path || '';
            const filePath = _isEmpPortal && rawPath && !rawPath.startsWith('../') ? '../admin_area/' + escapeHtml(rawPath) : escapeHtml(rawPath);
            const fileSize = escapeHtml(att.file_size || '');
            const sizeHtml = fileSize ? `<small style="color:#94a3b8; font-weight:normal; flex-shrink:0; margin-left:4px;">(${fileSize})</small>` : '';
            const delBtn = isAdmin ? `<i class="fa fa-trash-o" style="color:#ef4444; cursor:pointer; font-size:13px;" title="Delete Attachment" onclick="deleteTdDescAttachment(${att.id})"></i>` : '';

            list.append(`
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:7px 12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; font-size:12.5px; width:100%; box-sizing:border-box;">
                    <a href="${filePath}" target="_blank" style="color:#0f172a; font-weight:600; text-decoration:none; display:flex; align-items:center; gap:8px; flex:1; min-width:0; overflow:hidden;" title="${fileName}">
                        <i class="fa fa-file-text-o" style="color:#dd2127; flex-shrink:0;"></i>
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; flex:1; min-width:0;">${fileName}</span>
                        ${sizeHtml}
                    </a>
                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                        <a href="${filePath}" download style="color:#64748b; font-size:12px; text-decoration:none;" title="Download"><i class="fa fa-download"></i></a>
                        ${delBtn}
                    </div>
                </div>
            `);
        });
    }

    function uploadTdDescAttachment(input) {
        if (!input.files || !input.files[0] || !_modalTaskId) return;
        const formData = new FormData();
        formData.append('task_id', _modalTaskId);
        formData.append('attachment_file', input.files[0]);

        const label = $(input).closest('label');
        const origHtml = label.html();
        label.html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

        $.ajax({
            url: _ajaxBaseUrl + 'ajax_add_todo_attachment.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                label.html(origHtml);
                $(input).val('');
                if (res && res.success) {
                    $.post(_ajaxBaseUrl + 'ajax_get_todo_detail.php', {
                        task_id: _modalTaskId
                    }, function(r) {
                        if (r && r.success) renderTdDescAttachments(r.attachments || [], (r.is_admin === true || r.is_admin === 1));
                    });
                } else {
                    Swal.fire('Error', res ? res.message : 'Could not upload attachment.', 'error');
                }
            },
            error: function() {
                label.html(origHtml);
                Swal.fire('Error', 'Upload failed.', 'error');
            }
        });
    }

    function deleteTdDescAttachment(attId) {
        if (!confirm('Delete this document attachment?')) return;
        $.post(_ajaxBaseUrl + 'ajax_delete_todo_attachment.php', {
            attachment_id: attId
        }, function(res) {
            if (res && res.success) {
                $.post(_ajaxBaseUrl + 'ajax_get_todo_detail.php', {
                    task_id: _modalTaskId
                }, function(r) {
                    if (r && r.success) renderTdDescAttachments(r.attachments || [], (r.is_admin === true || r.is_admin === 1));
                });
            }
        });
    }

    function handleTdCommentFileSelect(input) {
        if (input.files && input.files[0]) {
            $('#td-comment-file-name').text(input.files[0].name);
            $('#td-comment-file-preview').css('display', 'inline-flex');
            $('#td-comment-actions').css('display', 'flex');
        }
    }

    function clearTdCommentFile() {
        $('#td-comment-file').val('');
        $('#td-comment-file-name').text('');
        $('#td-comment-file-preview').hide();
    }

    function closeTaskDetail() {
        $('#taskDetailOverlay').fadeOut(180);
        $('body').css('overflow', '');
        if (_modalEmpId) loadTasks(_modalEmpId);
        _modalTaskId = null;
        _modalEmpId = null;
    }

    function saveTdField(field, value) {
        if (!_modalTaskId || _isEmpPortal) return;
        const el = field === 'task_name' ? $('#td-title') : $(`#td-${field}`);
        if (el.prop('readonly') || el.prop('disabled')) return;
        if (field === 'task_name' && !value.trim()) return;
        $.post(_ajaxBaseUrl + 'ajax_update_todo_detail.php', {
            task_id: _modalTaskId,
            [field]: value
        }, function(res) {
            if (_modalEmpId) loadTasks(_modalEmpId);
        });
    }

    function tdToggleStatus() {
        if (!_modalTaskId) return;
        const newStatus = _modalStatus === 1 ? 0 : 1;
        $.ajax({
            url: _ajaxBaseUrl + 'ajax_toggle_team_todo.php',
            method: 'POST',
            data: {
                task_id: _modalTaskId,
                status: newStatus
            },
            success: function(res) {
                if (res && res.success) {
                    _modalStatus = newStatus;
                    if (newStatus === 1) {
                        $('#td-check-circle').addClass('td-completed');
                        $('#td-status-label').text('Mark Pending');
                    } else {
                        $('#td-check-circle').removeClass('td-completed');
                        $('#td-status-label').text('Mark Complete');
                    }
                    if (_modalEmpId) loadTasks(_modalEmpId);
                }
            }
        });
    }

    function tdDeleteCard() {
        if (!_modalTaskId || _isEmpPortal) return;
        Swal.fire({
            title: 'Delete Task?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dd2127',
            confirmButtonText: 'Yes, delete it'
        }).then(r => {
            if (r.isConfirmed) {
                const empId = _modalEmpId;
                $.ajax({
                    url: _ajaxBaseUrl + 'ajax_delete_team_todo.php',
                    method: 'POST',
                    data: {
                        task_id: _modalTaskId
                    },
                    success: function(res) {
                        if (res && res.success) {
                            closeTaskDetail();
                            if (empId) loadTasks(empId);
                        } else {
                            Swal.fire('Error', res ? res.message : 'Failed', 'error');
                        }
                    }
                });
            }
        });
    }

    function tdSubmitComment() {
        const comment = $('#td-comment-input').val().trim();
        const fileInput = $('#td-comment-file')[0];
        const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;

        if (!comment && !hasFile) return;
        if (!_modalTaskId) return;

        const btn = $('#td-comment-save');
        btn.prop('disabled', true).text('Saving...');

        const formData = new FormData();
        formData.append('task_id', _modalTaskId);
        formData.append('comment', comment || 'Attached document');
        formData.append('posted_by', _isEmpPortal ? 'employee' : 'admin');
        if (hasFile) {
            formData.append('comment_file', fileInput.files[0]);
        }

        $.ajax({
            url: _ajaxBaseUrl + 'ajax_add_todo_comment.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                btn.prop('disabled', false).text('Save');
                if (res && res.success) {
                    $('#td-comment-input').val('').attr('rows', 2);
                    clearTdCommentFile();
                    $('#td-comment-actions').hide();
                    if (typeof fetchLiveNotifications === 'function') fetchLiveNotifications();
                    $.ajax({
                        url: _ajaxBaseUrl + 'ajax_get_todo_detail.php',
                        method: 'POST',
                        data: {
                            task_id: _modalTaskId
                        },
                        success: function(r) {
                            if (r && r.success) renderTdActivity(r.comments || [], r.task, !_isEmpPortal && (r.is_admin === true || r.is_admin === 1));
                        }
                    });
                } else {
                    Swal.fire('Error', 'Could not save comment.', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Save');
                Swal.fire('Error', 'Network error saving comment.', 'error');
            }
        });
    }

    function tdDeleteComment(cid) {
        if (!confirm('Delete this comment?')) return;
        $.ajax({
            url: _ajaxBaseUrl + 'ajax_delete_todo_comment.php',
            method: 'POST',
            data: {
                comment_id: cid
            },
            success: function(res) {
                if (res && res.success) {
                    $(`#td-comment-${cid}`).fadeOut(150, function() {
                        $(this).remove();
                    });
                }
            }
        });
    }

    function renderTdActivity(comments, task, isAdmin) {
        const list = $('#td-activity');
        list.empty();

        comments.forEach(c => {
            const authorName = c.author_name || c.admin_name || c.comment_author_emp_name || 'User';
            const init = authorName.charAt(0).toUpperCase();
            const empTag = c.emp_name ?
                `<span style="display:inline-block; background:#ffeaeb; color:#dd2127; font-size:10px; font-weight:700; border-radius:4px; padding:1px 7px; margin-left:8px; vertical-align:middle;">${escapeHtml(c.emp_name)}</span>` :
                '';

            let commentTextHtml = '';
            const commentText = (c.comment || '').trim();
            if (commentText && commentText !== 'Attached document') {
                commentTextHtml = `<div style="word-break:break-word; overflow-wrap:anywhere;">${escapeHtml(commentText)}</div>`;
            }

            let attachmentHtml = '';
            if (c.attachment) {
                const attName = escapeHtml(c.attachment_name || 'Attachment');
                const rawAtt = c.attachment || '';
                const attPath = _isEmpPortal && rawAtt && !rawAtt.startsWith('../') ? '../admin_area/' + escapeHtml(rawAtt) : escapeHtml(rawAtt);
                attachmentHtml = `
                    <div class="td-comment-attachment-pill">
                        <i class="fa fa-paperclip" style="color:#dd2127; flex-shrink:0;"></i>
                        <a href="${attPath}" target="_blank" class="td-comment-attachment-link" title="${attName}">${attName}</a>
                        <a href="${attPath}" download style="color:#64748b; font-size:11px; flex-shrink:0; margin-left:auto;" title="Download"><i class="fa fa-download"></i></a>
                    </div>
                `;
            }

            const deleteBtnHtml = isAdmin ? `
                <div class="td-act-actions">
                    <button class="td-act-link" onclick="tdDeleteComment(${c.id})">Delete</button>
                </div>
            ` : '';

            list.append(`
                <div class="td-act-item" id="td-comment-${c.id}">
                    <div class="td-act-avatar">${escapeHtml(init)}</div>
                    <div class="td-act-body">
                        <div class="td-act-header">
                            <strong>${escapeHtml(authorName)}</strong>${empTag}
                            <small>${tdTimeAgo(c.created_at)}</small>
                        </div>
                        <div class="td-act-comment">
                            ${commentTextHtml}
                            ${attachmentHtml}
                        </div>
                        ${deleteBtnHtml}
                    </div>
                </div>
            `);
        });

        if (task.created_at) {
            list.append(`
                <div class="td-act-system">
                    <span class="td-act-dot"><i class="fa fa-plus"></i></span>
                    <span>Task created &nbsp;<small style="color:#94a3b8;">${tdTimeAgo(task.created_at)}</small></span>
                </div>
            `);
        }

        if (comments.length === 0 && !task.created_at) {
            list.html('<div style="color:#94a3b8;font-size:13px;text-align:center;padding:12px;">No activity yet</div>');
        }
    }

    function tdTimeAgo(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(/-/g, '/'));
        const diff = Math.floor((Date.now() - d.getTime()) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    function escapeHtml(unsafe) {
        if (unsafe === null || unsafe === undefined) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /* ===== COMMON TASK MODAL FUNCTIONS ===== */
    function openCommonTaskModal() {
        $('#ct-task-name').val('');
        $('#ct-description').val('');
        $('#ct-due-date').val('');
        $('#ct-priority').val('Medium');
        $('#ct-comment').val('');
        $('#ct-select-all').prop('checked', true);
        $('.ct-emp-checkbox').prop('checked', true);
        $('#commonTaskOverlay').fadeIn(200);
        $('body').css('overflow', 'hidden');
        setTimeout(() => $('#ct-task-name').focus(), 250);
    }

    function closeCommonTaskModal() {
        $('#commonTaskOverlay').fadeOut(180);
        $('body').css('overflow', '');
    }

    function toggleSelectAllEmployees(isChecked) {
        $('.ct-emp-checkbox').prop('checked', isChecked);
    }

    function submitCommonTask(e) {
        e.preventDefault();
        const taskName = $('#ct-task-name').val().trim();
        const description = $('#ct-description').val().trim();
        const dueDate = $('#ct-due-date').val();
        const priority = $('#ct-priority').val();
        const comment = $('#ct-comment').val().trim();

        const empIds = [];
        $('.ct-emp-checkbox:checked').each(function() {
            empIds.push($(this).val());
        });

        if (!taskName) {
            Swal.fire('Required', 'Please enter task name.', 'warning');
            return;
        }

        if (empIds.length === 0) {
            Swal.fire('Required', 'Please select at least one employee to assign task.', 'warning');
            return;
        }

        const btn = $('#ct-submit-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');
        console.log('Submitting common task, projectId:', projectId, 'empIds:', empIds);

        $.ajax({
            url: _ajaxBaseUrl + 'ajax_add_common_team_todo.php',
            method: 'POST',
            dataType: 'json',
            data: {
                project_id: projectId,
                emp_ids: empIds,
                task_name: taskName,
                description: description,
                due_date: dueDate,
                priority: priority,
                comment: comment
            },
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fa fa-plus-circle"></i> Create Common Task');
                if (typeof res === 'string') {
                    try {
                        res = JSON.parse(res);
                    } catch (e) {}
                }
                if (res && res.success) {
                    closeCommonTaskModal();
                    if (typeof fetchLiveNotifications === 'function') fetchLiveNotifications();
                    Swal.fire({
                        icon: 'success',
                        title: 'Common Task Created!',
                        text: res.message || `Task assigned to ${empIds.length} employee(s).`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    // Reload tasks for all employee columns
                    $('.todo-column').each(function() {
                        const empId = $(this).data('emp-id');
                        loadTasks(empId);
                    });
                } else {
                    Swal.fire('Error', res ? res.message : 'Could not add common task.', 'error');
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false).html('<i class="fa fa-plus-circle"></i> Create Common Task');
                Swal.fire('Error', 'Server error occurred while adding common task.', 'error');
            }
        });
    }

    $(document).ready(function() {
        <?php if (isset($_GET['open_task_id']) && intval($_GET['open_task_id']) > 0): ?>
            const autoTaskId = <?php echo intval($_GET['open_task_id']); ?>;
            const autoEmpId = <?php echo isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0; ?>;
            setTimeout(function() {
                openTaskDetail(autoTaskId, autoEmpId);
                if (window.history && window.history.replaceState) {
                    const cleanUrl = window.location.href.replace(/([&?])open_task_id=\d+(&|$)/, '$1').replace(/([&?])emp_id=\d+(&|$)/, '$1').replace(/[\?&]$/, '');
                    window.history.replaceState(null, '', cleanUrl);
                }
            }, 400);
        <?php endif; ?>
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            if ($('#taskDetailOverlay').is(':visible')) closeTaskDetail();
            if ($('#commonTaskOverlay').is(':visible')) closeCommonTaskModal();
        }
    });
</script>