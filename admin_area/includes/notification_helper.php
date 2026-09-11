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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient_type, recipient_id, is_read),
        INDEX idx_created (created_at)
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
 * @param int|array $exclude_emp_ids
 * @return void
 */
function notifyProjectMembers(int $project_id, string $title, string $message, string $url = '', string $type = 'info', $exclude_emp_ids = 0): void
{
    global $con;
    $project_id = intval($project_id);
    if ($project_id <= 0) return;

    $exclude_array = is_array($exclude_emp_ids) 
        ? array_map('intval', $exclude_emp_ids) 
        : [intval($exclude_emp_ids)];

    $res = mysqli_query($con, "SELECT assigned_employees FROM client_projects WHERE id = $project_id LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $assigned = array_filter(explode(',', $row['assigned_employees'] ?? ''), function ($id) {
            return !empty(trim($id));
        });
        foreach ($assigned as $emp_id) {
            $emp_id = intval($emp_id);
            if ($emp_id > 0 && !in_array($emp_id, $exclude_array, true)) {
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
 * @param int|array $exclude_admin_ids
 * @param int $task_emp_id
 * @return void
 */
function notifyProjectAdmins(int $project_id, string $title, string $message, string $url = '', string $type = 'info', $exclude_admin_ids = 0, int $task_emp_id = 0): void
{
    global $con;
    initNotificationTable();
    $project_id = intval($project_id);
    $exclude_array = is_array($exclude_admin_ids) 
        ? array_map('intval', $exclude_admin_ids) 
        : [intval($exclude_admin_ids)];
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

    // 4. Always include Super Admins, CEOs, and primary admins
    $s_res = mysqli_query($con, "SELECT admin_id FROM admins WHERE is_super_admin = 1 OR LOWER(TRIM(admin_job)) IN ('super admin', 'ceo', 'admin', 'director')");
    if ($s_res) {
        while ($s_row = mysqli_fetch_assoc($s_res)) {
            $target_admins[] = intval($s_row['admin_id']);
        }
    }

    // 5. Fallback: if no specific admins matched, notify all registered admins
    if (empty($target_admins)) {
        $all_adm = mysqli_query($con, "SELECT admin_id FROM admins");
        if ($all_adm) {
            while ($ar = mysqli_fetch_assoc($all_adm)) {
                $target_admins[] = intval($ar['admin_id']);
            }
        }
    }

    $unique_admins = array_unique($target_admins);
    foreach ($unique_admins as $aid) {
        $aid = intval($aid);
        if ($aid > 0 && !in_array($aid, $exclude_array, true)) {
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
 * @param int|array $exclude_admin_ids
 * @return void
 */
function notifyAllAdmins(string $title, string $message, string $url = '', string $type = 'info', $exclude_admin_ids = 0): void
{
    notifyProjectAdmins(0, $title, $message, $url, $type, $exclude_admin_ids);
}

/**
 * Notify both project members and assigned project admins.
 * @param int $project_id
 * @param string $title
 * @param string $message
 * @param string $url
 * @param string $type
 * @param int|array $exclude_emp_ids
 * @param int|array $exclude_admin_ids
 * @param int $task_emp_id
 * @return void
 */
function notifyProjectTeamAndAdmins(int $project_id, string $title, string $message, string $url = '', string $type = 'info', $exclude_emp_ids = 0, $exclude_admin_ids = 0, int $task_emp_id = 0): void
{
    notifyProjectMembers($project_id, $title, $message, $url, $type, $exclude_emp_ids);
    notifyProjectAdmins($project_id, $title, $message, $url, $type, $exclude_admin_ids, $task_emp_id);
}

/**
 * Automatically check and seed time-based notifications (birthdays, upcoming birthdays, leave requests).
 * Throttled to execute at most once every 30 minutes per session to prevent database overhead.
 *
 * @param mysqli $con
 * @return int Number of new notifications inserted
 */
function checkTimeBasedSystemNotifications($con): int
{
    if (!$con) return 0;

    $last_check = isset($_SESSION['last_time_based_notif_check']) ? intval($_SESSION['last_time_based_notif_check']) : 0;
    if (time() - $last_check < 1800) {
        return 0; // Throttled: checked recently in this session
    }
    $_SESSION['last_time_based_notif_check'] = time();

    initNotificationTable();

    $today_day_month    = date('m-d');
    $current_year       = intval(date('Y'));
    $today_date         = date('Y-m-d');
    $inserted           = 0;

    // 0. Auto-cleanup: remove past birthday notifications from previous days
    @mysqli_query($con, "DELETE FROM system_notifications WHERE type LIKE 'birthday%' AND DATE(created_at) < '$today_date'");

    // 1. Birthday Today — Notify ALL employees and ALL admins (once per employee per day)
    $bday_q = mysqli_query($con,
        "SELECT id, name FROM emp_list
         WHERE dob IS NOT NULL 
           AND dob != '0000-00-00' 
           AND DATE_FORMAT(dob, '%m-%d') = '$today_day_month'"
    );
    if ($bday_q) {
        while ($emp = mysqli_fetch_assoc($bday_q)) {
            $emp_id   = intval($emp['id']);
            $emp_name = mysqli_real_escape_string($con, $emp['name']);

            // Check if already notified today for this employee
            $already = mysqli_query($con,
                "SELECT id FROM system_notifications
                 WHERE type = 'birthday_today'
                   AND message LIKE '%#$emp_id%'
                   AND DATE(created_at) = '$today_date'
                 LIMIT 1"
            );
            if ($already && mysqli_num_rows($already) > 0) continue;

            $title   = "🎂 Birthday Today!";
            $message = "Today is {$emp_name}'s birthday! Wish them a very Happy Birthday! 🎉 #$emp_id";

            // Send notification to all admins
            addSystemNotification('all_admins', 0, $title, $message, 'index.php?employees', 'birthday_today');

            // Send notification to all employees
            addSystemNotification('all_employees', 0, $title, $message, 'index.php', 'birthday_today');

            @mysqli_query($con, "UPDATE emp_list SET last_birthday_wish_year = '$current_year' WHERE id = $emp_id");
            $inserted += 2;
        }
    }

    // 3. New Leave Requests — Notify all admins for any pending unnotified leave
    $leave_q = mysqli_query($con,
        "SELECT l.id, l.reason, l.leave_from, l.leave_to, l.emp_id,
                COALESCE(lt.leave_name, 'Leave') AS leave_type,
                e.name AS emp_name
         FROM leave_applications l
         JOIN emp_list e ON l.emp_id = e.id
         LEFT JOIN leave_types lt ON lt.id = l.leave_type_id
         WHERE l.status = 'pending'
         ORDER BY l.id DESC
         LIMIT 20"
    );
    if ($leave_q) {
        while ($row = mysqli_fetch_assoc($leave_q)) {
            $leave_id   = intval($row['id']);
            $emp_name   = $row['emp_name'];
            $reason     = substr($row['reason'] ?? '', 0, 60);
            $leave_type = $row['leave_type'];
            $from_date  = !empty($row['leave_from']) ? date('d M', strtotime($row['leave_from'])) : '';
            $to_date    = !empty($row['leave_to'])   ? date('d M', strtotime($row['leave_to']))   : '';

            $already = mysqli_query($con,
                "SELECT id FROM system_notifications
                 WHERE type = 'leave_request'
                   AND recipient_type = 'all_admins'
                   AND url LIKE '%leave_id=$leave_id%'
                 LIMIT 1"
            );
            if ($already && mysqli_num_rows($already) > 0) continue;

            $title   = "📋 Leave Request: $emp_name";
            $message = "$emp_name applied for $leave_type ($from_date – $to_date). Reason: $reason";
            $url     = "index.php?view_leave_requests&leave_id=$leave_id";
            addSystemNotification('all_admins', 0, $title, $message, $url, 'leave_request');
            $inserted++;
        }
    }

    return $inserted;
}

