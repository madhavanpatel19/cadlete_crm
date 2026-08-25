<?php
if (!isset($con)) {
    include(__DIR__ . '/db.php');
}

/**
 * Initialize system_notifications database table if not exists.
 * @return void
 */
function initNotificationTable(): void
{
    global $con;
    $sql = "CREATE TABLE IF NOT EXISTS system_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_type VARCHAR(20) NOT NULL,
        recipient_id INT DEFAULT 0,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        url VARCHAR(255) DEFAULT NULL,
        type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @mysqli_query($con, $sql);
}

/**
 * Add a single notification record.
 * @param string $recipient_type
 * @param int $recipient_id
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @return void
 */
function addSystemNotification(string $recipient_type, int $recipient_id, string $title, string $message, string $url = '', string $type = 'info'): void
{
    global $con;
    initNotificationTable();

    $rec_type = mysqli_real_escape_string($con, $recipient_type);
    $rec_id   = intval($recipient_id);
    $t_esc    = mysqli_real_escape_string($con, $title);
    $m_esc    = mysqli_real_escape_string($con, $message);
    $u_esc    = mysqli_real_escape_string($con, $url);
    $type_esc = mysqli_real_escape_string($con, $type);

    $sql = "INSERT INTO system_notifications (recipient_type, recipient_id, title, message, url, type) 
            VALUES ('$rec_type', $rec_id, '$t_esc', '$m_esc', '$u_esc', '$type_esc')";
    @mysqli_query($con, $sql);
}

/**
 * Notify assigned employees of a project.
 * @param int $project_id
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @param int $exclude_emp_id
 * @return void
 */
function notifyProjectMembers(int $project_id, string $title, string $message, string $url = '', string $type = 'info', int $exclude_emp_id = 0): void
{
    global $con;
    $project_id = intval($project_id);
    $exclude_emp_id = intval($exclude_emp_id);
    if ($project_id <= 0) return;

    $res = mysqli_query($con, "SELECT assigned_employees FROM client_projects WHERE id = $project_id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $assigned = array_filter(explode(',', $row['assigned_employees'] ?? ''), function ($id) {
            return !empty(trim($id));
        });
        foreach ($assigned as $emp_id) {
            $emp_id = intval($emp_id);
            if ($emp_id > 0 && $emp_id !== $exclude_emp_id) {
                addSystemNotification('employee', $emp_id, $title, $message, $url, $type);
            }
        }
    }
}

/**
 * Notify admins assigned to a project (and Super Admins).
 * @param int $project_id
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @param int $exclude_admin_id
 * @return void
 */
function notifyProjectAdmins(int $project_id, string $title, string $message, string $url = '', string $type = 'info', int $exclude_admin_id = 0, int $task_emp_id = 0): void
{
    global $con;
    initNotificationTable();
    $project_id = intval($project_id);
    $exclude_admin_id = intval($exclude_admin_id);
    $task_emp_id = intval($task_emp_id);

    $target_admins = [];
    $proj_depts = [];

    // 1. Get assigned admins & employee departments for this project
    if ($project_id > 0) {
        $p_res = mysqli_query($con, "SELECT assigned_admins, assigned_employees FROM client_projects WHERE id = $project_id LIMIT 1");
        if ($p_res && $p_row = mysqli_fetch_assoc($p_res)) {
            $assigned_raw = array_filter(explode(',', $p_row['assigned_admins'] ?? ''), function ($id) {
                return !empty(trim($id));
            });
            foreach ($assigned_raw as $aid) {
                $target_admins[] = intval($aid);
            }

            // Find departments of employees assigned to this project
            $emp_raw = array_filter(explode(',', $p_row['assigned_employees'] ?? ''), function ($id) {
                return !empty(trim($id));
            });
            if (!empty($emp_raw)) {
                $emp_ids_str = implode(',', array_map('intval', $emp_raw));
                $e_depts_q = mysqli_query($con, "SELECT DISTINCT department FROM emp_list WHERE id IN ($emp_ids_str) AND department IS NOT NULL AND TRIM(department) != ''");
                if ($e_depts_q) {
                    while ($edr = mysqli_fetch_assoc($e_depts_q)) {
                        $d = trim($edr['department']);
                        if (!empty($d) && strcasecmp($d, 'not assigned') !== 0) {
                            $proj_depts[] = strtolower($d);
                        }
                    }
                }
            }
        }
    }

    // 2. Include department of specific task employee if provided
    if ($task_emp_id > 0) {
        $e_single_q = mysqli_query($con, "SELECT department FROM emp_list WHERE id = $task_emp_id LIMIT 1");
        if ($e_single_q && $esr = mysqli_fetch_assoc($e_single_q)) {
            $sd = trim($esr['department'] ?? '');
            if (!empty($sd) && strcasecmp($sd, 'not assigned') !== 0) {
                $proj_depts[] = strtolower($sd);
            }
        }
    }
    $proj_depts = array_unique($proj_depts);

    // 3. Find admins whose assigned department matches any of $proj_depts
    if (!empty($proj_depts)) {
        $d_res = @mysqli_query($con, "SELECT admin_id, department FROM admins WHERE department IS NOT NULL AND TRIM(department) != ''");
        if ($d_res) {
            while ($d_row = mysqli_fetch_assoc($d_res)) {
                $raw_adept = trim($d_row['department'] ?? '');
                if (!empty($raw_adept) && strcasecmp($raw_adept, 'not assigned') !== 0) {
                    $adepts = array_map('strtolower', array_map('trim', explode(',', $raw_adept)));
                    foreach ($proj_depts as $pd) {
                        if (in_array($pd, $adepts, true)) {
                            $target_admins[] = intval($d_row['admin_id']);
                            break;
                        }
                    }
                }
            }
        }
    }

    // 4. Always include Super Admins
    $s_res = mysqli_query($con, "SELECT admin_id FROM admins WHERE is_super_admin = 1 OR LOWER(TRIM(admin_job)) = 'super admin'");
    if ($s_res) {
        while ($s_row = mysqli_fetch_assoc($s_res)) {
            $target_admins[] = intval($s_row['admin_id']);
        }
    }

    $unique_admins = array_unique($target_admins);
    foreach ($unique_admins as $aid) {
        $aid = intval($aid);
        if ($aid > 0 && $aid !== $exclude_admin_id) {
            addSystemNotification('admin', $aid, $title, $message, $url, $type);
        }
    }
}

/**
 * Notify all admins.
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @param int $exclude_admin_id
 * @return void
 */
function notifyAllAdmins(string $title, string $message, string $url = '', string $type = 'info', int $exclude_admin_id = 0): void
{
    notifyProjectAdmins(0, $title, $message, $url, $type, $exclude_admin_id);
}

/**
 * Notify both project members and assigned project admins.
 * @param int $project_id
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @param int $exclude_emp_id
 * @param int $exclude_admin_id
 * @param int $task_emp_id
 * @return void
 */
function notifyProjectTeamAndAdmins(int $project_id, string $title, string $message, string $url = '', string $type = 'info', int $exclude_emp_id = 0, int $exclude_admin_id = 0, int $task_emp_id = 0): void
{
    notifyProjectMembers($project_id, $title, $message, $url, $type, $exclude_emp_id);
    notifyProjectAdmins($project_id, $title, $message, $url, $type, $exclude_admin_id, $task_emp_id);
}
