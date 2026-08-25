<?php
// permissions.php

if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ALL SYSTEM PERMISSIONS
 */
if (!function_exists('getAllPermissions')) {
    function getAllPermissions()
    {
        return [

            // Dashboard
            'dashboard_view',

            // Employee
            'employee_view',
            'employee_insert',
            'employee_update',
            'employee_delete',

            // Attendance
            'attendance_view',
            'attendance_insert',

            // Salary
            'salary_view',
            'salary_insert',
            'salary_update',
            'salary_delete',

            // Leave
            'leave_view',
            'leave_insert',
            'leave_approve',

            // Worksheet
            'worksheet_view',

            // Announcement
            'announcement_view',
            'announcement_insert',
            'announcement_update',
            'announcement_delete',

            // Admin Users
            'user_view',
            'user_insert',
            'user_update',
            'user_delete',

            // Projects
            'project_view',
            'project_insert',
            'project_update',
            'project_delete',
            'project_assign_task',
            'project_assigned_only',

            // Project Source
            'project_source_view',
            'project_source_insert',
            'project_source_delete',

            // Budget
            'budget_view',
            'budget_insert',
            'budget_update',
            'budget_delete',

            // Todo
            'todo_view',
            'todo_insert',
            'todo_update',
            'todo_delete',

            // Leads
            'lead_view',
            'lead_insert',
            'lead_update',
            'lead_delete',

            // Clients
            'client_view',
            'client_insert',
            'client_update',
            'client_delete',

            // Company Links
            'company_link_view',
            'company_link_insert',
            'company_link_update',
            'company_link_delete',

            // Documents
            'offer_letter_view',
            'offer_letter_insert',

            'nda_view',
            'nda_insert',

            'experience_letter_view',
            'experience_letter_insert',
        ];
    }
}

/**
 * PERMISSIONS TO SHOW IN ADMIN PANEL
 */
if (!function_exists('getUsedAdminPermissions')) {
    function getUsedAdminPermissions()
    {
        return getAllPermissions();
    }
}

/**
 * HUMAN READABLE LABELS
 */
if (!function_exists('getPermissionLabel')) {
    function getPermissionLabel($permissionKey)
    {

        $labels = [

            'dashboard_view' => 'Dashboard',

            // Employee
            'employee_view' => 'View Employees',
            'employee_insert' => 'Add Employee',
            'employee_update' => 'Edit Employee',
            'employee_delete' => 'Delete Employee',

            // Attendance
            'attendance_view' => 'View Attendance',
            'attendance_insert' => 'Add Attendance',

            // Salary
            'salary_view' => 'View Salary',
            'salary_insert' => 'Add Salary',
            'salary_update' => 'Edit Salary',
            'salary_delete' => 'Delete Salary',

            // Leave
            'leave_view' => 'View Leave',
            'leave_insert' => 'Add Leave',
            'leave_approve' => 'Approve Leave',

            // Worksheet
            'worksheet_view' => 'View Worksheet',

            // Announcement
            'announcement_view' => 'View Announcement',
            'announcement_insert' => 'Add Announcement',
            'announcement_update' => 'Edit Announcement',
            'announcement_delete' => 'Delete Announcement',

            // Users
            'user_view' => 'View Admin User',
            'user_insert' => 'Add Admin User',
            'user_update' => 'Edit Admin User',
            'user_delete' => 'Delete Admin User',

            // Projects
            'project_view' => 'View Project',
            'project_insert' => 'Add Project',
            'project_update' => 'Edit Project',
            'project_delete' => 'Delete Project',
            'project_assign_task' => 'Assign Project Task',
            'project_assigned_only' => 'Assigned Projects Only',

            // Project Source
            'project_source_view' => 'View Project Source',
            'project_source_insert' => 'Add Project Source',
            'project_source_delete' => 'Delete Project Source',

            // Budget
            'budget_view' => 'View Budget',
            'budget_insert' => 'Add Budget',
            'budget_update' => 'Edit Budget',
            'budget_delete' => 'Delete Budget',

            // Todo
            'todo_view' => 'View Todo',
            'todo_insert' => 'Add Todo',
            'todo_update' => 'Edit Todo',
            'todo_delete' => 'Delete Todo',

            // Leads
            'lead_view' => 'View Leads',
            'lead_insert' => 'Add Lead',
            'lead_update' => 'Edit Lead',
            'lead_delete' => 'Delete Lead',

            // Clients
            'client_view' => 'View Client',
            'client_insert' => 'Add Client',
            'client_update' => 'Edit Client',
            'client_delete' => 'Delete Client',

            // Company Links
            'company_link_view' => 'View Company Links',
            'company_link_insert' => 'Add Company Links',
            'company_link_update' => 'Edit Company Links',
            'company_link_delete' => 'Delete Company Links',

            // Documents
            'offer_letter_view' => 'View Offer Letter',
            'offer_letter_insert' => 'Create Offer Letter',

            'nda_view' => 'View NDA',
            'nda_insert' => 'Create NDA',

            'experience_letter_view' => 'View Experience Letter',
            'experience_letter_insert' => 'Create Experience Letter',
        ];

        return $labels[$permissionKey]
            ?? ucwords(str_replace('_', ' ', $permissionKey));
    }
}

/**
 * CHECK USER PERMISSION
 */
if (!function_exists('userHasPermission')) {
    function userHasPermission($userId, $permission)
    {

        global $con;

        if (
            isset($_SESSION['is_super_admin'])
            && $_SESSION['is_super_admin'] == 1
        ) {
            return true;
        }

        if (!isset($_SESSION['admin_email'])) {
            return false;
        }

        $email = mysqli_real_escape_string(
            $con,
            $_SESSION['admin_email']
        );

        $query = mysqli_query(
            $con,
            "SELECT permissions, is_super_admin
             FROM admins
             WHERE admin_email='$email'
             LIMIT 1"
        );

        if ($query && $row = mysqli_fetch_assoc($query)) {

            if (isset($row['is_super_admin']) && $row['is_super_admin'] == 1) {
                return true;
            }

            $permissions = empty($row['permissions'])
                ? []
                : array_map(
                    'trim',
                    explode(',', $row['permissions'])
                );

            return in_array(
                $permission,
                $permissions,
                true
            );
        }

        return false;
    }
}

/**
 * REQUIRE PERMISSION
 */
if (!function_exists('requirePermission')) {
    function requirePermission($permission)
    {

        if (!userHasPermission(null, $permission)) {

            header(
                "Location:index.php?dashboard&access_denied=1"
            );
            exit();
        }
    }
}

/**
 * ATTENDANCE EDIT CHECK
 */
if (!function_exists('userCanEditAttendance')) {
    function userCanEditAttendance($userId)
    {
        return userHasPermission(
            $userId,
            'attendance_insert'
        );
    }
}

// ==========================================
// MIGRATED FROM admin_permissions.php
// ==========================================

// Ensure admins table has permission columns (one-time migration)
if (isset($con)) {
    $check = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'is_super_admin'");
    if (!$check || mysqli_num_rows($check) === 0) {
        @mysqli_query($con, "ALTER TABLE admins ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0");
        @mysqli_query($con, "ALTER TABLE admins ADD COLUMN permissions TEXT NULL");
        @mysqli_query($con, "UPDATE admins SET is_super_admin = 1 WHERE admin_id = 1 LIMIT 1");
    }
    $check_perm = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'permissions'");
    if (!$check_perm || mysqli_num_rows($check_perm) === 0) {
        @mysqli_query($con, "ALTER TABLE admins ADD COLUMN permissions TEXT NULL");
    }
    $check_dept = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'department'");
    if (!$check_dept || mysqli_num_rows($check_dept) === 0) {
        @mysqli_query($con, "ALTER TABLE admins ADD COLUMN department VARCHAR(255) DEFAULT 'Management'");
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin()
    {
        return userHasPermission(null, '__SUPER_ADMIN_INTERNAL_CHECK__');
    }
}

if (!function_exists('getCurrentAdminPermissions')) {
    function getCurrentAdminPermissions()
    {
        global $con;
        if (!isset($_SESSION['admin_email'])) return [];
        $email = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $r = mysqli_query($con, "SELECT permissions FROM admins WHERE admin_email='$email' LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $p = isset($row['permissions']) ? trim($row['permissions']) : '';
            return $p === '' ? [] : array_map('trim', explode(',', $p));
        }
        return [];
    }
}

if (!function_exists('_adminPermissionAliases')) {
    function _adminPermissionAliases($permission)
    {
        $map = [
            'employee_insert' => ['add_employee'],
            'employee_update' => ['edit_employee'],
            'employee_delete' => ['delete_employee'],
            'employee_view'   => ['show_employee'],
            'attendance_view' => ['show_attendance'],
            'salary_view'     => ['show_salary'],
            'user_insert'     => ['add_permission'],
            'user_update'     => ['edit_user'],
            'user_delete'     => ['delete_user'],
            'user_view'       => ['show_user'],
            'leave_view'      => ['show_leave'],
            'worksheet_view'  => ['show_worksheet'],
            'announcement_view' => ['show_announcement'],
            'project_view'      => ['show_project'],
            'project_insert'    => ['add_project'],
            'project_update'    => ['edit_project'],
            'project_delete'    => ['delete_project'],
            'client_view'       => ['show_client'],
            'client_insert'     => ['add_client'],
            'client_update'     => ['edit_client'],
            'client_delete'     => ['delete_client'],
            'lead_view'         => ['show_lead'],
            'lead_insert'       => ['add_lead'],
            'lead_update'       => ['edit_lead'],
            'lead_delete'       => ['delete_lead'],
        ];

        $perms = [$permission];
        if (isset($map[$permission])) {
            $perms = array_merge($perms, $map[$permission]);
        }
        return $perms;
    }
}

if (!function_exists('canAdminAccess')) {
    function canAdminAccess($permission)
    {
        if (isSuperAdmin()) {
            return true;
        }
        foreach (_adminPermissionAliases($permission) as $p) {
            if (userHasPermission(null, $p)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('requireAdminPermission')) {
    function requireAdminPermission($permission, $attendanceDate = null)
    {
        // Special case: allow editing today's attendance for all admins
        if ($permission === 'attendance_insert' && $attendanceDate !== null) {
            $today = date('Y-m-d');
            if ($attendanceDate === $today) {
                return;
            }
        }
        if (canAdminAccess($permission)) {
            return;
        }
        $redirect = 'index.php?dashboard&access_denied=1';
        if (headers_sent()) {
            echo "<script>window.location.href='" . htmlspecialchars($redirect) . "';</script>";
            exit;
        }
        header('Location: ' . $redirect);
        exit;
    }
}
