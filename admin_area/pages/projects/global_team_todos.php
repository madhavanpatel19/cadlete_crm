<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// Determine Super Admin vs Sub-Admin vs Employee status
$is_super_admin = false;
$current_admin_id = 0;

$is_employee_portal = (strpos($_SERVER['REQUEST_URI'] ?? '', 'emp_area') !== false) || (isset($_SESSION['emp_id']) && !isset($_SESSION['admin_email']));
$logged_in_emp_id = isset($_SESSION['emp_id']) ? intval($_SESSION['emp_id']) : 0;

$admin_assigned_depts = [];
if (isset($_SESSION['admin_email'])) {
    $ae = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $check_dept = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'department'");
    if ($check_dept && mysqli_num_rows($check_dept) === 0) {
        @mysqli_query($con, "ALTER TABLE admins ADD COLUMN department VARCHAR(255) DEFAULT 'Management'");
        $check_dept = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'department'");
    }
    $has_dept = ($check_dept && mysqli_num_rows($check_dept) > 0);
    $select_cols = "admin_id, is_super_admin, admin_job" . ($has_dept ? ", department" : "");

    $a_res = @mysqli_query($con, "SELECT $select_cols FROM admins WHERE admin_email = '$ae' LIMIT 1");
    if ($a_res && $a_row = mysqli_fetch_assoc($a_res)) {
        $current_admin_id = intval($a_row['admin_id']);
        if (intval($a_row['is_super_admin'] ?? 0) === 1 || strcasecmp(trim($a_row['admin_job'] ?? ''), 'Super Admin') === 0) {
            $is_super_admin = true;
        }
        $raw_dept = trim($a_row['department'] ?? '');
        if (!empty($raw_dept) && strcasecmp($raw_dept, 'not assigned') !== 0) {
            $admin_assigned_depts = array_filter(array_map('trim', explode(',', $raw_dept)));
        }
    }
}

// If Employee Portal mode, build columns based on General Tasks + Assigned Projects for logged-in employee
$emp_projects = [];
if ($is_employee_portal) {
    // 1st Column: General Tasks
    $emp_projects[] = [
        'id' => 0,
        'project_name' => 'General Tasks',
        'client_name' => 'Personal & General Tasks',
        'department' => 'General'
    ];

    if ($logged_in_emp_id > 0) {
        $get_assigned_p = "SELECT cp.id, cp.project_name, c.name as client_name 
                           FROM client_projects cp 
                           LEFT JOIN clients c ON cp.client_id = c.id 
                           WHERE cp.deleted_at IS NULL 
                             AND FIND_IN_SET('$logged_in_emp_id', REPLACE(cp.assigned_employees, ' ', '')) > 0
                           ORDER BY cp.project_name ASC";
        $run_assigned_p = mysqli_query($con, $get_assigned_p);
        if ($run_assigned_p) {
            while ($p_row = mysqli_fetch_assoc($run_assigned_p)) {
                $p_row['department'] = 'General';
                $emp_projects[] = $p_row;
            }
        }
    }
}

// Build employee query condition based on role for Admin view
$emp_where = "status = 'Active' AND deleted_at IS NULL";

if (!$is_super_admin && $current_admin_id > 0) {
    if (!empty($admin_assigned_depts)) {
        // Admin has assigned department(s): filter employees strictly by these assigned departments
        $dept_conds = [];
        foreach ($admin_assigned_depts as $ditem) {
            $escaped_d = mysqli_real_escape_string($con, $ditem);
            $dept_conds[] = "LOWER(TRIM(department)) = LOWER('$escaped_d')";
        }
        $emp_where .= " AND (" . implode(' OR ', $dept_conds) . ")";
    } else {
        // Sub-Admin with no specific department assigned: filter by assigned projects
        $allowed_emp_ids = [];
        $p_res = mysqli_query($con, "SELECT assigned_employees FROM client_projects WHERE deleted_at IS NULL AND FIND_IN_SET('$current_admin_id', REPLACE(assigned_admins, ' ', '')) > 0");
        if ($p_res) {
            while ($p_row = mysqli_fetch_assoc($p_res)) {
                $raw_e = array_filter(explode(',', $p_row['assigned_employees'] ?? ''), function ($id) {
                    return !empty(trim($id));
                });
                foreach ($raw_e as $eid) {
                    $allowed_emp_ids[] = intval($eid);
                }
            }
        }
        $allowed_emp_ids = array_unique($allowed_emp_ids);
        if (!empty($allowed_emp_ids)) {
            $emp_where .= " AND id IN (" . implode(',', $allowed_emp_ids) . ")";
        } else {
            $emp_where .= " AND 1=0";
        }
    }
}

// Get active employees for columns & distinct departments for filter dropdown
$get_emps = "SELECT * FROM emp_list WHERE $emp_where ORDER BY name ASC";
$run_emps = mysqli_query($con, $get_emps);
$employees = [];
$departments_list = [];
if ($run_emps) {
    while ($e = mysqli_fetch_assoc($run_emps)) {
        $employees[] = $e;
        $d = trim($e['department'] ?? '');
        if (!empty($d) && !in_array($d, $departments_list)) {
            $departments_list[] = $d;
        }
    }
}
sort($departments_list);

// Get active projects for the dropdown when adding a task
$proj_where = "status = 'Active' AND deleted_at IS NULL";
if (!$is_super_admin && $current_admin_id > 0) {
    $proj_where .= " AND FIND_IN_SET('$current_admin_id', REPLACE(assigned_admins, ' ', '')) > 0";
}
$get_projs = "SELECT id, project_name FROM client_projects WHERE $proj_where ORDER BY project_name ASC";
$run_projs = mysqli_query($con, $get_projs);
$active_projects = [];
if ($run_projs) {
    while ($p = mysqli_fetch_assoc($run_projs)) {
        $active_projects[] = $p;
    }
}
?>
<style>
    .todo-board {
        display: flex;
        gap: 20px;
        overflow-x: auto;
        padding-bottom: 20px;
        align-items: flex-start;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
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
        min-width: 360px;
        max-width: 360px;
        flex: 0 0 360px;
        background: #ffffff;
        border: 1px solid #e8edf3;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .todo-column:hover {
        box-shadow: 0 4px 16px rgba(220, 38, 38, 0.1);
        transform: translateY(-2px);
    }

    .todo-col-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: #fff;
    }

    .emp-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .emp-avatar-fallback {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #fef2f2, #fee2e2);
        color: #dc2626;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 16px;
        border: 2px solid #fecaca;
        flex-shrink: 0;
    }

    .completed-section-header {
        margin: 4px 16px 4px;
        padding: 10px 12px;
        border-top: 1px solid #f1f5f9;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 11px;
        border-radius: 8px;
        transition: background 0.15s;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        user-select: none;
    }

    .completed-section-header:hover {
        background: #f8fafc;
        color: #64748b;
    }

    .emp-name {
        font-weight: 700;
        font-size: 15px;
        color: #0f172a;
        line-height: 1.3;
    }

    .emp-role {
        font-weight: 500;
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .icon-btn {
        background: transparent;
        border: none;
        color: #cbd5e1;
        cursor: pointer;
        font-size: 15px;
        padding: 6px 8px;
        border-radius: 6px;
        transition: all 0.15s ease;
        line-height: 1;
    }

    .icon-btn:hover {
        color: #64748b;
        background: #f1f5f9;
    }

    .add-task-trigger {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #64748b;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        padding: 10px 16px;
        margin-bottom: 5px;
        transition: 0.2s;
    }

    .add-task-trigger:hover {
        color: #dc2626;
    }

    .add-task-trigger:hover i {
        color: #dc2626 !important;
    }

    .add-task-form {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 15px;
        margin: 0 16px 15px 16px;
    }

    .task-input {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 500;
        outline: none;
        transition: 0.2s;
    }

    .task-input:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .task-date-input,
    .task-priority-input,
    .task-project-input {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        outline: none;
    }

    .task-project-input {
        font-size: 13px;
    }

    .save-task-btn {
        background: #dc2626;
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .cancel-task-btn {
        background: #fff;
        color: #64748b;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 6px 16px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .task-list {
        max-height: 400px;
        overflow-y: auto;
        padding: 0 16px 12px;
    }

    .task-list::-webkit-scrollbar {
        width: 4px;
    }

    .task-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .task-list::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 4px;
    }

    .task-list::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }

    .task-item {
        display: flex;
        flex-direction: column;
        padding: 12px 0;
        border-bottom: 1px solid #f8fafc;
        transition: background 0.15s ease;
        width: 100%;
    }

    .task-item:last-child {
        border-bottom: none;
    }

    .task-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 2px solid #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
        margin-top: 1px;
    }

    .task-checkbox:hover {
        border-color: #dc2626;
        background: #fef2f2;
    }

    .task-checkbox i {
        display: none;
        color: #fff;
        font-size: 10px;
    }

    .task-item.completed .task-checkbox {
        background: #dc2626;
        border-color: #dc2626;
    }

    .task-item.completed .task-checkbox i {
        display: block;
    }

    .task-proj-name {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 6px;
        line-height: 1.3;
    }

    .task-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.45;
        word-break: break-word;
        flex: 1;
        min-width: 0;
    }

    .task-item.completed .task-name {
        text-decoration: line-through;
        color: #94a3b8;
    }

    .task-meta {
        display: flex;
        align-items: center;
        width: 100%;
        box-sizing: border-box;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
        padding-left: 30px;
    }

    .date-badge {
        background: #fef2f2;
        color: #dc2626;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid #fecaca;
        white-space: nowrap;
        flex-shrink: 0;
        display: inline-block;
        line-height: 1.3;
    }

    .priority-flag {
        font-size: 11px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 2px 8px;
        border-radius: 20px;
        white-space: nowrap;
        flex-shrink: 0;
        line-height: 1.3;
    }

    .priority-High {
        color: #dc2626;
        background: #fef2f2;
    }

    .priority-Medium {
        color: #d97706;
        background: #fffbeb;
    }

    .priority-Low {
        color: #16a34a;
        background: #f0fdf4;
    }

    /* Premium Modal Inputs & Buttons */
    .premium-modal-input {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
        border: 1.5px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 10px 14px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #334155 !important;
        background-color: #fff !important;
        outline: none !important;
        transition: all 0.2s ease !important;
        box-shadow: none !important;
        height: auto !important;
    }

    .premium-modal-input:focus {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08) !important;
    }

    .premium-modal-input:disabled {
        background-color: #f8fafc !important;
        color: #94a3b8 !important;
        cursor: not-allowed !important;
    }

    .premium-btn-save {
        background: #dc2626 !important;
        color: #fff !important;
        border: none !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        padding: 10px 24px !important;
        font-size: 14px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 4px rgba(220, 38, 38, 0.25) !important;
        letter-spacing: 0.2px !important;
    }

    .premium-btn-save:hover {
        background: #b91c1c !important;
        box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3) !important;
        transform: translateY(-1px) !important;
    }

    .premium-btn-save:active {
        transform: translateY(0) !important;
        box-shadow: 0 1px 2px rgba(220, 38, 38, 0.2) !important;
    }

    .premium-btn-cancel {
        background: #fff !important;
        color: #64748b !important;
        border: 1.5px solid #e2e8f0 !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        padding: 9px 20px !important;
        font-size: 14px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }

    .premium-btn-cancel:hover {
        background: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #0f172a !important;
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

    #taskDetailModal .btn-modal-close {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        transform: none !important;
        flex-shrink: 0 !important;
    }

    #taskDetailModal .btn-modal-close:hover {
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
</style>

<div class="page-wrapper premium-ui-enabled" style="background: #f8fafc; min-height: calc(100vh - 60px);">
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <h1></h1>
            <div style="padding: 8px 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                All Tasks
            </div>
        </div>
        <div class="header-actions-premium" style="display: flex; gap: 16px; align-items: center;">
            <?php if (!$is_employee_portal): ?>
                <div style="position: relative;">
                    <select id="task-dept-filter" style="padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 600; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02); background: #fff;" title="Filter by Department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments_list as $dept) { ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div style="position: relative;">
                    <i class="fa fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" id="task-emp-search" placeholder="Search employee name..." style="padding: 10px 15px 10px 34px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 600; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02); width: 210px; background: #fff;" title="Type employee name to filter columns">
                </div>
                <div style="position: relative;">
                    <input type="date" id="task-date-filter" style="padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 500; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);" title="Filter by Due Date (Shows only incomplete tasks for date)">
                </div>
            <?php endif; ?>
            <!-- <div style="position: relative;">
                <i class="fa fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 14px;"></i>
                <input type="text" id="task-search" placeholder="Search tasks..." style="width: 250px; padding: 10px 15px 10px 38px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; font-weight: 500; outline: none; transition: 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            </div> -->
            <?php if (function_exists('canAdminAccess') && canAdminAccess('todo_insert')): ?>
                <button class="btn-premium-add" onclick="showGlobalAddTask()">
                    <i class="fa fa-plus"></i> Add Task
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="todo-board">
        <?php
        $module_colors = [
            // 0: General Tasks -> Indigo / Royal Theme
            ['primary' => '#4f46e5', 'border' => '#6366f1', 'bg' => '#e0e7ff', 'color' => '#3730a3', 'icon' => 'fa-tasks', 'badge_bg' => '#e0e7ff', 'badge_border' => '#c7d2fe'],
            // 1: Emerald Green
            ['primary' => '#059669', 'border' => '#10b981', 'bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-briefcase', 'badge_bg' => '#d1fae5', 'badge_border' => '#a7f3d0'],
            // 2: Amber / Golden Orange
            ['primary' => '#d97706', 'border' => '#f59e0b', 'bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-folder-open', 'badge_bg' => '#fef3c7', 'badge_border' => '#fde68a'],
            // 3: Rose / Coral Pink
            ['primary' => '#e11d48', 'border' => '#f43f5e', 'bg' => '#ffe4e6', 'color' => '#9f1239', 'icon' => 'fa-rocket', 'badge_bg' => '#ffe4e6', 'badge_border' => '#fecdd3'],
            // 4: Purple / Violet
            ['primary' => '#7c3aed', 'border' => '#8b5cf6', 'bg' => '#ede9fe', 'color' => '#5b21b6', 'icon' => 'fa-layer-group', 'badge_bg' => '#ede9fe', 'badge_border' => '#ddd6fe'],
            // 5: Cyan / Sky Blue
            ['primary' => '#0891b2', 'border' => '#06b6d4', 'bg' => '#cffaff', 'color' => '#155e75', 'icon' => 'fa-cube', 'badge_bg' => '#cffaff', 'badge_border' => '#a5f3fc'],
            // 6: Pink / Magenta
            ['primary' => '#db2777', 'border' => '#ec4899', 'bg' => '#fce7f3', 'color' => '#9d174d', 'icon' => 'fa-chart-pie', 'badge_bg' => '#fce7f3', 'badge_border' => '#fbcfe8'],
            // 7: Royal Blue
            ['primary' => '#2563eb', 'border' => '#3b82f6', 'bg' => '#dbeafe', 'color' => '#1e40af', 'icon' => 'fa-code', 'badge_bg' => '#dbeafe', 'badge_border' => '#bfdbfe'],
        ];

        if ($is_employee_portal) {
            foreach ($emp_projects as $proj_idx => $proj) {
                $p_id = intval($proj['id']);
                $p_name = htmlspecialchars($proj['project_name']);
                $p_sub = !empty($proj['client_name']) ? htmlspecialchars($proj['client_name']) : 'Project Task';
                $p_dept = htmlspecialchars($proj['department'] ?? 'General');
                $col_key = $logged_in_emp_id . '-' . $p_id;

                // Pick distinct color theme for this project module column
                if ($p_id === 0) {
                    $c_theme = $module_colors[0];
                } else {
                    $c_theme = $module_colors[(($proj_idx - 1) % (count($module_colors) - 1)) + 1];
                }
        ?>
                <div class="todo-column" data-emp-id="<?php echo $logged_in_emp_id; ?>" data-project-id="<?php echo $p_id; ?>" data-dept="<?php echo $p_dept; ?>">
                    <div class="todo-col-header" style="border-left: 4px solid <?php echo $c_theme['border']; ?>;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="emp-avatar-fallback" style="background: <?php echo $c_theme['bg']; ?>; color: <?php echo $c_theme['color']; ?>; border: 2px solid <?php echo $c_theme['badge_border']; ?>;">
                                <i class="fa <?php echo $p_id === 0 ? 'fa-tasks' : $c_theme['icon']; ?>"></i>
                            </div>
                            <div>
                                <div class="emp-name"><?php echo $p_name; ?></div>
                            </div>
                        </div>
                        <button class="icon-btn"><i class="fa fa-ellipsis-v"></i></button>
                    </div>

                    <div class="add-task-trigger" onclick="showInlineAddTask(<?php echo $logged_in_emp_id; ?>, <?php echo $p_id; ?>)">
                        <i class="fa fa-plus-circle" style="font-size: 16px; color: <?php echo $c_theme['primary']; ?>;"></i>
                        <span>Add a task</span>
                    </div>

                    <div class="add-task-form" id="inline-add-form-<?php echo $col_key; ?>" style="display: none;">
                        <input type="text" class="task-input" id="inline-task-input-<?php echo $col_key; ?>" placeholder="What needs to be done?">
                        <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                            <input type="date" class="task-date-input" id="inline-task-date-<?php echo $col_key; ?>">
                            <select class="task-priority-input" id="inline-task-priority-<?php echo $col_key; ?>">
                                <option value="Low">Low Priority</option>
                                <option value="Medium" selected>Medium Priority</option>
                                <option value="High">High Priority</option>
                            </select>
                            <button class="btn-premium-add" onclick="saveInlineTask(<?php echo $logged_in_emp_id; ?>, <?php echo $p_id; ?>)">Add</button>
                            <button class="btn-premium-cancel" onclick="hideInlineAddTask(<?php echo $logged_in_emp_id; ?>, <?php echo $p_id; ?>)">Cancel</button>
                        </div>
                    </div>

                    <div class="task-list" id="task-list-<?php echo $col_key; ?>">
                        <div style="text-align: center; padding: 20px;"><i class="fa fa-spinner fa-spin" style="color: #cbd5e1;"></i></div>
                    </div>
                </div>
                <?php
            }
        } else {
            if (empty($employees)) {
                echo '<div style="text-align: center; width: 100%; padding: 50px; color: #64748b; font-weight: 600;">No tasks found for this date.</div>';
            } else {
                foreach ($employees as $emp_idx => $emp) {
                    $emp_id = intval($emp['id']);
                    $emp_name = htmlspecialchars($emp['name']);
                    $emp_dept = !empty($emp['department']) ? $emp['department'] : (!empty($emp['designation']) ? $emp['designation'] : 'Employee');
                    $emp_job = htmlspecialchars($emp_dept);
                    $emp_img = !empty($emp['employee_image']) ? 'uploads/' . htmlspecialchars($emp['employee_image']) : null;

                    // Pick distinct color theme for this employee column
                    $c_theme = $module_colors[$emp_idx % count($module_colors)];
                ?>
                    <div class="todo-column" data-emp-id="<?php echo $emp_id; ?>" data-dept="<?php echo htmlspecialchars($emp['department'] ?? 'Not Assigned'); ?>">
                        <div class="todo-col-header" style="border-left: 4px solid <?php echo $c_theme['border']; ?>;">
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

                        <?php if (function_exists('canAdminAccess') && canAdminAccess('todo_insert')): ?>
                            <div class="add-task-trigger" onclick="showInlineAddTask(<?php echo $emp_id; ?>)">
                                <i class="fa fa-plus-circle" style="font-size: 16px; color: <?php echo $c_theme['primary']; ?>;"></i>
                                <span>Add a task</span>
                            </div>
                        <?php endif; ?>

                        <div class="add-task-form" id="inline-add-form-<?php echo $emp_id; ?>" style="display: none;">
                            <input type="text" class="task-input" id="inline-task-input-<?php echo $emp_id; ?>" placeholder="What needs to be done?">
                            <div style="display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap;">
                                <input type="date" class="task-date-input" id="inline-task-date-<?php echo $emp_id; ?>">
                                <select class="task-priority-input" id="inline-task-priority-<?php echo $emp_id; ?>">
                                    <option value="Low">Low Priority</option>
                                    <option value="Medium" selected>Medium Priority</option>
                                    <option value="High">High Priority</option>
                                </select>
                                <button class="btn-premium-add" onclick="saveInlineTask(<?php echo $emp_id; ?>)">Add</button>
                                <button class="btn-premium-cancel" onclick="hideInlineAddTask(<?php echo $emp_id; ?>)">Cancel</button>
                            </div>
                        </div>

                        <div class="task-list" id="task-list-<?php echo $emp_id; ?>">
                            <div style="text-align: center; padding: 20px;"><i class="fa fa-spinner fa-spin" style="color: #cbd5e1;"></i></div>
                        </div>
                    </div>
        <?php
                }
            }
        }
        ?>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="globalAddTaskModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document" style="max-width: 540px;">
        <div class="modal-content" style="border-radius: 14px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.18);">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="color: #fff; font-size: 14px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Add New Task</h5>
                        <p style="margin: 0; font-size: 12px; color: #94a3b8; font-weight: 500;">Assign a task to an employee</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #374151; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-briefcase" style="color: #dc2626; font-size: 11px;"></i> Project
                    </label>
                    <select class="form-control premium-modal-input" id="global-task-project" onchange="fetchProjectEmployees(this.value)">
                        <option value="">-- Select a Project --</option>
                        <?php foreach ($active_projects as $p) { ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['project_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #374151; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-user" style="color: #dc2626; font-size: 11px;"></i> Assign To
                    </label>
                    <select class="form-control premium-modal-input" id="global-task-employee" disabled>
                        <option value="">-- Select Project First --</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-weight: 600; color: #374151; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-tasks" style="color: #dc2626; font-size: 11px;"></i> Task Name
                    </label>
                    <input type="text" class="form-control premium-modal-input" id="global-task-input" placeholder="e.g. Prepare monthly report...">
                </div>

                <div class="row" style="margin: 0 -6px;">
                    <div class="col-md-6 form-group" style="padding: 0 6px; margin-bottom: 8px;">
                        <label style="font-weight: 600; color: #374151; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-calendar" style="color: #dc2626; font-size: 11px;"></i> Due Date
                        </label>
                        <input type="date" class="form-control premium-modal-input" id="global-task-date">
                    </div>
                    <div class="col-md-6 form-group" style="padding: 0 6px; margin-bottom: 8px;">
                        <label style="font-weight: 600; color: #374151; font-size: 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-flag" style="color: #dc2626; font-size: 11px;"></i> Priority
                        </label>
                        <select class="form-control premium-modal-input" id="global-task-priority">
                            <option value="Low">🟢 Low Priority</option>
                            <option value="Medium" selected>🟡 Medium Priority</option>
                            <option value="High">🔴 High Priority</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 16px 24px; background: #f8fafc; border-radius: 0 0 14px 14px; gap: 10px;">
                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn-premium-add" onclick="saveGlobalTask()">
                    <i class="fa fa-check" style="margin-right: 6px;"></i> Save Task
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== SAME-TO-SAME TRELLO CARD POPUP MODAL ===== -->
<div id="taskDetailOverlay">
    <div id="taskDetailModal">
        <!-- Top Navigation Bar -->
        <div class="tdm-top-bar">
            <div class="tdm-list-tag">
                <span id="td-in-list">List Name</span> <i class="fa fa-angle-down" style="font-size: 11px; margin-left: 4px;"></i>
            </div>
            <div class="tdm-top-actions">
                <button class="tdm-icon-btn" title="Project"><i class="fa fa-briefcase"></i> <span id="td-project-name" style="font-size:12px; font-weight:600;">Project</span></button>
                <button class="btn-modal-close" onclick="closeTaskDetail()" title="Close (Esc)"><i class="fa fa-times"></i></button>
            </div>
        </div>

        <!-- 2-Column Split Layout -->
        <div class="tdm-body">
            <!-- LEFT COLUMN: Title, Date, Priority & Description -->
            <div class="tdm-left">
                <div class="tdm-header">
                    <div id="td-check-circle" class="tdm-check" onclick="tdToggleStatus()" title="Toggle mark complete">
                        <i class="fa fa-check"></i>
                    </div>
                    <textarea id="td-title" class="tdm-title-input" rows="1" placeholder="Task title..."
                        onfocus="this.style.borderBottomColor='#dd2127'"
                        onblur="this.style.borderBottomColor='transparent'; saveTdField('task_name', this.value)"></textarea>
                </div>

                <div class="tdm-meta-row">
                    <div class="tdm-meta-group">
                        <div class="tdm-meta-label">DUE DATE</div>
                        <input type="date" id="td-due-date" class="tdm-date-input no-global-flatpickr"
                            onchange="saveTdField('due_date', this.value)">
                        <div id="td-due-date-display" style="display:none; padding:7px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; color:#0f172a; min-width:130px; user-select:none;"><i class="fa fa-calendar" style="color:#dd2127; margin-right:6px;"></i><span id="td-due-date-text">--</span></div>
                    </div>
                    <div class="tdm-meta-group">
                        <div class="tdm-meta-label">PRIORITY</div>
                        <select id="td-priority" class="tdm-select" onchange="saveTdField('priority', this.value)">
                            <option value="">— None</option>
                            <option value="Low">🟢 Low</option>
                            <option value="Medium">🟡 Medium</option>
                            <option value="High">🔴 High</option>
                        </select>
                        <div id="td-priority-display" style="display:none; padding:7px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:700; min-width:110px; user-select:none;"><span id="td-priority-text">--</span></div>
                    </div>
                </div>

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

                    <div id="td-desc-attachments-container" style="margin-top: 10px; display: none;">
                        <div style="font-size: 11px; font-weight: 800; color: #94a3b8; letter-spacing: 0.5px; margin-bottom: 6px;">ATTACHED DOCUMENTS</div>
                        <div id="td-desc-attachments-list" style="display: flex; flex-direction: column; gap: 6px;"></div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Comments and Activity Stream -->
            <div class="tdm-right">
                <div class="tdm-section-title" style="justify-content: space-between; margin-bottom: 16px;">
                    <span><i class="fa fa-comments-o"></i> Comments and activity</span>
                </div>

                <div class="tdm-comment-add">
                    <div class="tdm-comment-avatar"><i class="fa fa-user"></i></div>
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

                <div id="td-activity" class="tdm-activity"></div>
            </div>
        </div>
    </div>
</div>



<script>
    const ajaxBaseUrl = '<?php echo (strpos($_SERVER['REQUEST_URI'] ?? '', 'emp_area') !== false || (isset($_SESSION['emp_id']) && !isset($_SESSION['admin_email']))) ? '../admin_area/ajax/projects/' : 'ajax/projects/'; ?>';
    const canTodoDelete = <?php echo ($is_employee_portal || (function_exists('canAdminAccess') && (canAdminAccess('todo_delete') || canAdminAccess('project_assign_task')))) ? 'true' : 'false'; ?>;
    const canTodoUpdate = <?php echo ($is_employee_portal || (function_exists('canAdminAccess') && (canAdminAccess('todo_update') || canAdminAccess('project_assign_task')))) ? 'true' : 'false'; ?>;
</script>
<script>
    $(document).ready(function() {
        // Load tasks for all columns
        $('.todo-column').each(function() {
            const empId = $(this).data('emp-id');
            const projId = $(this).data('project-id');
            loadTasks(empId, projId);
        });

        function applyFilters() {
            const searchVal = $('#task-search').val();
            const term = searchVal ? searchVal.toLowerCase() : '';
            const filterDate = $('#task-date-filter').val();
            const filterDept = $('#task-dept-filter').val();
            const empSearchVal = $('#task-emp-search').val() ? $('#task-emp-search').val().toLowerCase().trim() : '';

            // Toggle column visibility by selected department & typed employee name
            $('.todo-column').each(function() {
                const empDept = $(this).attr('data-dept') || '';
                const empName = $(this).find('.emp-name').text().toLowerCase();

                let showCol = true;
                if (filterDept && empDept !== filterDept) {
                    showCol = false;
                }
                if (empSearchVal && !empName.includes(empSearchVal)) {
                    showCol = false;
                }

                if (showCol) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            const colCompletedCounts = {};
            const colPendingCounts = {};

            $('.task-item').each(function() {
                const name = $(this).find('.task-name').text().toLowerCase();
                const proj = $(this).find('.task-proj-name').text().toLowerCase();
                const taskDate = $(this).attr('data-date');
                const isCompleted = $(this).hasClass('completed');

                // Get column key from closest task list container
                const parentListId = $(this).closest('[id^="task-list-"]').attr('id');
                const colKey = parentListId ? parentListId.replace('task-list-', '') : null;

                if (colKey && colCompletedCounts[colKey] === undefined) {
                    colCompletedCounts[colKey] = 0;
                    colPendingCounts[colKey] = 0;
                }

                let matchText = true;
                if (term) {
                    matchText = name.includes(term) || proj.includes(term);
                }

                let showTask = true;
                if (filterDate) {
                    if (isCompleted) {
                        showTask = (taskDate === filterDate);
                    } else {
                        showTask = true;
                    }
                }

                if (matchText && showTask) {
                    $(this).show();
                    if (colKey) {
                        if (isCompleted) {
                            colCompletedCounts[colKey]++;
                        } else {
                            colPendingCounts[colKey]++;
                        }
                    }
                } else {
                    $(this).hide();
                }
            });

            // Update completed headers for each visible column
            $('.todo-column:visible').each(function() {
                const empId = $(this).data('emp-id');
                const projId = $(this).data('project-id');
                const hasProj = (projId !== undefined && projId !== null);
                const colKey = hasProj ? `${empId}-${projId}` : `${empId}`;

                const compCount = colCompletedCounts[colKey] || 0;
                const pendCount = colPendingCounts[colKey] || 0;
                const header = $(this).find('.completed-section-header');

                if (compCount > 0) {
                    header.show();
                    $(`#completed-text-${colKey}`).text(`Completed (${compCount})`);
                } else {
                    header.hide();
                    $(`#completed-tasks-${colKey}`).hide();
                    $(`#completed-icon-${colKey}`).removeClass('fa-chevron-right').addClass('fa-chevron-down');
                }

                const totalVisible = compCount + pendCount;
                const hasServerMsg = $(this).find('.empty-server-msg').length > 0;

                $(this).find('.filter-empty-msg').remove();

                if (totalVisible === 0 && !hasServerMsg) {
                    $(this).find('.task-list').append('<div class="filter-empty-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">No tasks match filters</div>');
                }
            });
        }

        $('#task-search').on('keyup input', applyFilters);
        $('#task-date-filter').on('change', applyFilters);
        $('#task-dept-filter').on('change', applyFilters);
        $('#task-emp-search').on('keyup input', applyFilters);
    });

    function showGlobalAddTask() {
        $('#global-task-project').val('');
        $('#global-task-employee').html('<option value="">-- Select Project First --</option>').prop('disabled', true);
        $('#global-task-input').val('');

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        $('#global-task-date').val(`${yyyy}-${mm}-${dd}`);

        $('#global-task-priority').val('Medium');
        $('#globalAddTaskModal').modal('show');
    }

    function showInlineAddTask(empId, projId) {
        const key = (projId !== undefined && projId !== null) ? `${empId}-${projId}` : `${empId}`;
        $(`#inline-add-form-${key}`).slideDown(200);
        $(`#inline-task-input-${key}`).focus();

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        $(`#inline-task-date-${key}`).val(`${yyyy}-${mm}-${dd}`);
    }

    function hideInlineAddTask(empId, projId) {
        const key = (projId !== undefined && projId !== null) ? `${empId}-${projId}` : `${empId}`;
        $(`#inline-add-form-${key}`).slideUp(200);
        $(`#inline-task-input-${key}`).val('');
        $(`#inline-task-date-${key}`).val('');
        $(`#inline-task-priority-${key}`).val('Medium');
    }

    function saveInlineTask(empId, projId) {
        const key = (projId !== undefined && projId !== null) ? `${empId}-${projId}` : `${empId}`;
        const targetProjId = (projId !== undefined && projId !== null) ? projId : 0;
        const name = $(`#inline-task-input-${key}`).val().trim();
        const date = $(`#inline-task-date-${key}`).val();
        const priority = $(`#inline-task-priority-${key}`).val();

        if (!name) {
            Swal.fire("Required", "Please enter task name", "warning");
            return;
        }

        $.ajax({
            url: ajaxBaseUrl + 'ajax_add_team_todo.php',
            method: 'POST',
            data: {
                project_id: targetProjId,
                emp_id: empId,
                task_name: name,
                due_date: date,
                priority: priority
            },
            success: function(res) {
                if (res.success) {
                    hideInlineAddTask(empId, projId);
                    loadTasks(empId, projId);
                } else {
                    Swal.fire("Error", "Could not add task.", "error");
                }
            }
        });
    }

    function fetchProjectEmployees(projectId) {
        if (!projectId) {
            $('#global-task-employee').html('<option value="">-- Select Project First --</option>').prop('disabled', true);
            return;
        }

        $('#global-task-employee').html('<option value="">Loading...</option>').prop('disabled', true);

        $.ajax({
            url: ajaxBaseUrl + 'ajax_get_project_employees.php',
            method: 'POST',
            data: {
                project_id: projectId
            },
            success: function(res) {
                if (res.success) {
                    if (res.employees.length > 0) {
                        let options = '<option value="">-- Select Employee --</option>';
                        res.employees.forEach(function(emp) {
                            options += `<option value="${emp.id}">${escapeHtml(emp.name)}</option>`;
                        });
                        $('#global-task-employee').html(options).prop('disabled', false);
                    } else {
                        $('#global-task-employee').html('<option value="">No employees assigned to this project</option>').prop('disabled', true);
                    }
                } else {
                    $('#global-task-employee').html('<option value="">Error loading employees</option>').prop('disabled', true);
                }
            }
        });
    }

    function loadTasks(empId, projId) {
        const hasProj = (projId !== undefined && projId !== null);
        const targetProjId = hasProj ? projId : 0;
        const strictProj = hasProj ? 1 : 0;
        const listId = hasProj ? `task-list-${empId}-${projId}` : `task-list-${empId}`;

        $.ajax({
            url: ajaxBaseUrl + 'ajax_get_team_todos.php',
            method: 'POST',
            data: {
                project_id: targetProjId,
                emp_id: empId,
                strict_project: strictProj
            },
            success: function(res) {
                if (res && res.success) {
                    renderTasks(empId, projId, res.tasks || []);
                } else {
                    $(`#${listId}`).html('<div class="empty-server-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">' + (res && res.message ? escapeHtml(res.message) : 'No tasks assigned') + '</div>');
                }
            },
            error: function() {
                $(`#${listId}`).html('<div class="empty-server-msg" style="color: #ef4444; font-size: 13px; text-align: center; padding: 20px;">Error loading tasks</div>');
            }
        });
    }

    function getProjectColorBadge(projName) {
        const palettes = [
            { bg: '#e0e7ff', color: '#3730a3', border: '#c7d2fe', icon: 'fa-tasks' },        // Indigo (General Tasks)
            { bg: '#d1fae5', color: '#065f46', border: '#a7f3d0', icon: 'fa-briefcase' },    // Emerald
            { bg: '#fef3c7', color: '#92400e', border: '#fde68a', icon: 'fa-folder-open' },  // Amber
            { bg: '#ffe4e6', color: '#9f1239', border: '#fecdd3', icon: 'fa-rocket' },       // Rose
            { bg: '#ede9fe', color: '#5b21b6', border: '#ddd6fe', icon: 'fa-layer-group' },  // Violet
            { bg: '#cffaff', color: '#155e75', border: '#a5f3fc', icon: 'fa-cube' },         // Cyan
            { bg: '#fce7f3', color: '#9d174d', border: '#fbcfe8', icon: 'fa-chart-pie' },    // Pink
            { bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe', icon: 'fa-code' },         // Blue
        ];
        if (!projName || projName.toLowerCase().includes('general')) {
            return palettes[0];
        }
        let hash = 0;
        for (let i = 0; i < projName.length; i++) {
            hash = projName.charCodeAt(i) + ((hash << 5) - hash);
        }
        const idx = 1 + (Math.abs(hash) % (palettes.length - 1));
        return palettes[idx];
    }

    function renderTasks(empId, projId, tasks) {
        const hasProj = (projId !== undefined && projId !== null);
        const listId = hasProj ? `task-list-${empId}-${projId}` : `task-list-${empId}`;
        const list = $(`#${listId}`);
        list.empty();

        if (!tasks || tasks.length === 0) {
            list.html('<div class="empty-server-msg" style="color: #94a3b8; font-size: 13px; text-align: center; padding: 20px;">No tasks</div>');
            return;
        }

        let pendingTasksHtml = '';
        let completedTasksHtml = '';
        let completedCount = 0;

        tasks.forEach(task => {
            try {
                const isCompleted = parseInt(task.status) === 1;
                const itemClass = isCompleted ? 'task-item completed' : 'task-item';

                let dateBadge = '';
                if (task.due_date) {
                    const due = new Date(task.due_date);
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

                let priorityHtml = '';
                if (task.priority) {
                    priorityHtml = `<div class="priority-flag priority-${task.priority}"><i class="fa fa-flag"></i> ${task.priority}</div>`;
                }

                let addedBadge = '';
                if (task.created_at) {
                    const createdAt = new Date(task.created_at.replace(/-/g, '/'));
                    const addedStr = createdAt.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short'
                    }) + ', ' + createdAt.toLocaleTimeString('en-US', {
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                    addedBadge = `<div style="font-size: 11px; color: #94a3b8; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; margin-left: auto; white-space: nowrap;"><i class="fa fa-clock-o"></i> ${addedStr}</div>`;
                }

                const projName = task.project_name ? escapeHtml(task.project_name) : 'General Task';
                const pTheme = getProjectColorBadge(projName);
                const safeDate = task.due_date ? task.due_date : '';
                const taskNameStyle = isCompleted ? 'text-decoration: line-through; color: #94a3b8;' : '';

                const projParam = hasProj ? `, ${projId}` : '';
                const checkboxHtml = canTodoUpdate ?
                    `<div class="task-checkbox" onclick="toggleTask(${task.id}, ${empId}, ${isCompleted ? 0 : 1}${projParam})"><i class="fa fa-check"></i></div>` :
                    `<div class="task-checkbox" style="cursor: default; opacity: 0.5;"><i class="fa fa-check"></i></div>`;

                let dropdownHtml = '';
                if (canTodoDelete) {
                    dropdownHtml = `
                        <div class="dropdown" style="flex-shrink: 0; margin-left: 8px;">
                            <div class="task-menu-btn" data-toggle="dropdown" style="cursor: pointer; padding: 2px 4px; color: #64748b;">
                                <i class="fa fa-ellipsis-v"></i>
                            </div>
                            <ul class="dropdown-menu dropdown-menu-right" style="border-radius: 8px; border: none; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); padding: 5px 0; min-width: 120px;">
                                <li><a href="#" onclick="deleteTask(${task.id}, ${empId}${projParam}); return false;" style="color: #ef4444; font-weight: 600; padding: 10px 20px;"><i class="fa fa-trash-o" style="margin-right: 8px;"></i> Delete</a></li>
                            </ul>
                        </div>
                    `;
                }

                const html = `
                    <div class="${itemClass}" data-task-id="${task.id}" data-date="${safeDate}">
                        <div style="display: flex; align-items: flex-start; gap: 10px; width: 100%;">
                            ${checkboxHtml}
                            <div class="task-name" style="${taskNameStyle} cursor: pointer;" onclick="openTaskDetail(${task.id}, ${empId})" title="Click to view details">${escapeHtml(task.task_name)}</div>
                            ${dropdownHtml}
                        </div>
                        <div class="task-meta" onclick="openTaskDetail(${task.id}, ${empId})" style="cursor: pointer;" title="Click to view details">
                            ${priorityHtml}
                            ${dateBadge}
                            <div class="task-proj-name" style="background: ${pTheme.bg}; color: ${pTheme.color}; border: 1px solid ${pTheme.border};"><i class="fa ${pTheme.icon}"></i> ${projName}</div>
                            ${addedBadge}
                        </div>
                    </div>
                `;

                if (isCompleted) {
                    completedTasksHtml += html;
                    completedCount++;
                } else {
                    pendingTasksHtml += html;
                }
            } catch (err) {
                console.error("Error rendering task item:", err);
            }
        });

        list.append(pendingTasksHtml);

        const compKey = hasProj ? `${empId}-${projId}` : `${empId}`;
        if (completedCount > 0) {
            const completedSection = `
                <div class="completed-section-header" onclick="toggleCompletedSection('${compKey}')">
                    <i class="fa fa-chevron-down" id="completed-icon-${compKey}" style="transition: transform 0.3s; font-size: 11px;"></i>
                    <span id="completed-text-${compKey}">Completed (${completedCount})</span>
                </div>
                <div id="completed-tasks-${compKey}" style="display: none;">
                    ${completedTasksHtml}
                </div>
            `;
            list.append(completedSection);
        }

        $('#task-search').trigger('keyup');
    }

    function saveGlobalTask() {
        const projId = $('#global-task-project').val();
        const empId = $('#global-task-employee').val();
        const name = $('#global-task-input').val().trim();
        const date = $('#global-task-date').val();
        const priority = $('#global-task-priority').val();

        if (!projId) {
            Swal.fire("Required", "Please select a project", "warning");
            return;
        }
        if (!empId) {
            Swal.fire("Required", "Please select an assigned employee", "warning");
            return;
        }
        if (!name) {
            Swal.fire("Required", "Please enter task name", "warning");
            return;
        }

        $.ajax({
            url: ajaxBaseUrl + 'ajax_add_team_todo.php',
            method: 'POST',
            data: {
                project_id: projId,
                emp_id: empId,
                task_name: name,
                due_date: date,
                priority: priority
            },
            success: function(res) {
                if (res.success) {
                    $('#globalAddTaskModal').modal('hide');
                    loadTasks(empId);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Task added successfully',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else {
                    Swal.fire("Error", "Could not add task.", "error");
                }
            }
        });
    }

    function toggleTask(taskId, empId, newStatus, projId) {
        $.ajax({
            url: ajaxBaseUrl + 'ajax_toggle_team_todo.php',
            method: 'POST',
            data: {
                task_id: taskId,
                status: newStatus
            },
            success: function(res) {
                if (res && res.success) {
                    loadTasks(empId, projId);
                } else {
                    Swal.fire("Error", (res && res.message) ? res.message : "Could not update task.", "error");
                }
            },
            error: function() {
                Swal.fire("Error", "Network error updating task.", "error");
            }
        });
    }

    function deleteTask(taskId, empId, projId) {
        Swal.fire({
            title: 'Delete Task?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxBaseUrl + 'ajax_delete_team_todo.php',
                    method: 'POST',
                    data: {
                        task_id: taskId
                    },
                    success: function(res) {
                        if (res.success) {
                            loadTasks(empId, projId);
                        } else {
                            Swal.fire("Error", res.message || "Failed to delete task", "error");
                        }
                    }
                });
            }
        });
    }

    function toggleCompletedSection(key) {
        $(`#completed-tasks-${key}`).slideToggle(200);
        const icon = $(`#completed-icon-${key}`);
        if (icon.hasClass('fa-chevron-down')) {
            icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        } else {
            icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
        }
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
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
        $('#td-in-list').text('');
        $('#td-desc-attachments-container').hide();
        $('#td-desc-attachments-list').empty();
        $('#td-desc-upload-btn-wrap').hide();
        clearTdCommentFile();

        $('#taskDetailOverlay').fadeIn(200);
        $('body').css('overflow', 'hidden');

        $.ajax({
            url: ajaxBaseUrl + 'ajax_get_todo_detail.php',
            method: 'POST',
            data: {
                task_id: taskId
            },
            success: function(res) {
                if (!res || !res.success) return;
                const t = res.task;
                _modalStatus = parseInt(t.status);

                const isEmployee = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;
                const isAdmin = !isEmployee && (res.is_admin === true || res.is_admin === 1);

                // Read-Only Enforcement for Non-Admins / Employees with Dedicated Display Badges
                const dueDateEl = document.getElementById('td-due-date');
                if (isAdmin) {
                    $('#td-due-date').show();
                    if (dueDateEl && dueDateEl._flatpickr && dueDateEl._flatpickr.altInput) {
                        $(dueDateEl._flatpickr.altInput).show();
                    }
                    $('#td-priority').show();
                    $('#td-due-date-display').hide();
                    $('#td-priority-display').hide();

                    $('#td-title').prop('readonly', false).css({
                        'pointer-events': 'auto',
                        'border-bottom-color': 'transparent'
                    });
                    $('#td-description').prop('readonly', false).css({
                        'background': '#ffffff',
                        'pointer-events': 'auto',
                        'color': '#334155'
                    });
                    $('#td-desc-upload-btn-wrap').show();
                } else {
                    $('#td-due-date').hide();
                    if (dueDateEl && dueDateEl._flatpickr && dueDateEl._flatpickr.altInput) {
                        $(dueDateEl._flatpickr.altInput).hide();
                    }
                    $('#td-priority').hide();
                    $('#td-due-date-display').css('display', 'inline-flex');
                    $('#td-priority-display').css('display', 'inline-flex');

                    $('#td-title').prop('readonly', true).css({
                        'pointer-events': 'none',
                        'border-bottom-color': 'transparent'
                    });
                    $('#td-description').prop('readonly', true).css({
                        'background': '#f8fafc',
                        'pointer-events': 'none',
                        'color': '#334155'
                    });
                    $('#td-desc-upload-btn-wrap').hide();
                }

                $('#td-title').val(t.task_name);
                $('#td-description').val(t.description || '');

                let cleanDueDate = '';
                let formattedDueDateText = '--';
                if (t.due_date) {
                    cleanDueDate = t.due_date.split(' ')[0].split('T')[0];
                    try {
                        const d = new Date(cleanDueDate.replace(/-/g, '/'));
                        formattedDueDateText = d.toLocaleDateString('en-GB', {
                            day: 'numeric',
                            month: 'short',
                            year: 'numeric'
                        });
                    } catch (e) {
                        formattedDueDateText = cleanDueDate;
                    }
                }
                $('#td-due-date').val(cleanDueDate);
                if (dueDateEl && dueDateEl._flatpickr) {
                    dueDateEl._flatpickr.setDate(cleanDueDate || '', false);
                }
                $('#td-due-date-text').text(formattedDueDateText);

                const prioVal = (t.priority || '').trim();
                $('#td-priority').val(prioVal);
                let prioBadgeHtml = '--';
                if (prioVal.toLowerCase() === 'high') {
                    prioBadgeHtml = '<span style="color:#dc2626;"><i class="fa fa-flag"></i> High Priority</span>';
                } else if (prioVal.toLowerCase() === 'medium') {
                    prioBadgeHtml = '<span style="color:#d97706;"><i class="fa fa-flag"></i> Medium Priority</span>';
                } else if (prioVal.toLowerCase() === 'low') {
                    prioBadgeHtml = '<span style="color:#059669;"><i class="fa fa-flag"></i> Low Priority</span>';
                }
                $('#td-priority-text').html(prioBadgeHtml);
                $('#td-project-name').text(t.project_name || 'General Task');

                let colName = 'Assigned Task';
                $('.todo-column').each(function() {
                    const eId = $(this).data('emp-id');
                    const pId = $(this).data('project-id');
                    if (eId == empId) {
                        colName = $(this).find('.emp-name').text() || $(this).find('.todo-col-header div span').text() || 'Assigned Task';
                    }
                });
                $('#td-in-list').text(colName);

                if (_modalStatus === 1) {
                    $('#td-check-circle').addClass('td-completed');
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

        const isEmp = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;
        $('#td-desc-attachments-container').show();
        attachments.forEach(att => {
            const fileName = escapeHtml(att.file_name || 'Document');
            const rawPath = att.file_path || '';
            const filePath = isEmp && rawPath && !rawPath.startsWith('../') ? '../admin_area/' + escapeHtml(rawPath) : escapeHtml(rawPath);
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
            url: ajaxBaseUrl + 'ajax_add_todo_attachment.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                label.html(origHtml);
                $(input).val('');
                if (res && res.success) {
                    $.post(ajaxBaseUrl + 'ajax_get_todo_detail.php', {
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
        $.post(ajaxBaseUrl + 'ajax_delete_todo_attachment.php', {
            attachment_id: attId
        }, function(res) {
            if (res && res.success) {
                $.post(ajaxBaseUrl + 'ajax_get_todo_detail.php', {
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
        if (_modalEmpId) {
            $('.todo-column').each(function() {
                const eId = $(this).data('emp-id');
                const pId = $(this).data('project-id');
                if (eId == _modalEmpId) {
                    loadTasks(eId, pId);
                }
            });
        }
        _modalTaskId = null;
        _modalEmpId = null;
    }

    function saveTdField(field, value) {
        const isEmp = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;
        if (!_modalTaskId || isEmp) return;
        const el = field === 'task_name' ? $('#td-title') : $(`#td-${field}`);
        if (el.prop('readonly') || el.prop('disabled')) return;
        if (field === 'task_name' && !value.trim()) return;
        $.post(ajaxBaseUrl + 'ajax_update_todo_detail.php', {
            task_id: _modalTaskId,
            [field]: value
        }, function(res) {
            if (_modalEmpId) {
                $('.todo-column').each(function() {
                    const eId = $(this).data('emp-id');
                    const pId = $(this).data('project-id');
                    if (eId == _modalEmpId) loadTasks(eId, pId);
                });
            }
        });
    }

    function tdToggleStatus() {
        if (!_modalTaskId) return;
        const newStatus = _modalStatus === 1 ? 0 : 1;
        $.ajax({
            url: ajaxBaseUrl + 'ajax_toggle_team_todo.php',
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
                    } else {
                        $('#td-check-circle').removeClass('td-completed');
                    }
                    if (_modalEmpId) {
                        $('.todo-column').each(function() {
                            const eId = $(this).data('emp-id');
                            const pId = $(this).data('project-id');
                            if (eId == _modalEmpId) loadTasks(eId, pId);
                        });
                    }
                }
            }
        });
    }

    function tdSubmitComment() {
        const comment = $('#td-comment-input').val().trim();
        const fileInput = $('#td-comment-file')[0];
        const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        const isEmp = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;

        if (!comment && !hasFile) return;
        if (!_modalTaskId) return;

        const btn = $('#td-comment-save');
        btn.prop('disabled', true).text('Saving...');

        const formData = new FormData();
        formData.append('task_id', _modalTaskId);
        formData.append('comment', comment || 'Attached document');
        formData.append('posted_by', isEmp ? 'employee' : 'admin');
        if (hasFile) {
            formData.append('comment_file', fileInput.files[0]);
        }

        $.ajax({
            url: ajaxBaseUrl + 'ajax_add_todo_comment.php',
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
                    $.ajax({
                        url: ajaxBaseUrl + 'ajax_get_todo_detail.php',
                        method: 'POST',
                        data: {
                            task_id: _modalTaskId
                        },
                        success: function(r) {
                            if (r && r.success) renderTdActivity(r.comments || [], r.task, !isEmp && (r.is_admin === true || r.is_admin === 1));
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
            url: ajaxBaseUrl + 'ajax_delete_todo_comment.php',
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
        const isEmp = <?php echo $is_employee_portal ? 'true' : 'false'; ?>;

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
                const attPath = isEmp && rawAtt && !rawAtt.startsWith('../') ? '../admin_area/' + escapeHtml(rawAtt) : escapeHtml(rawAtt);
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

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            if ($('#taskDetailOverlay').is(':visible')) closeTaskDetail();
        }
    });
</script>