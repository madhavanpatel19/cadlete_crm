<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
require_once __DIR__ . '/../../includes/notification_helper.php';

// ── Security headers ─────────────────────────────────────────────────────────
// Prevent browser/proxy caching of notification data
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
    
// Only allow AJAX requests (blocks direct URL tab access)
$is_xhr = (
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    !empty($_SERVER['HTTP_ACCEPT']) &&
    strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
);
if (!$is_xhr) {
    if (ob_get_length()) ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// ── Session / Auth check ──────────────────────────────────────────────────────
$portal = isset($_GET['portal']) ? $_GET['portal'] : (isset($_POST['portal']) ? $_POST['portal'] : '');

// last_id: client sends its highest seen notification ID so we can skip a
// full DB fetch when nothing has changed (cheap optimisation).
$client_last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

$is_admin = isset($_SESSION['admin_email']);
$is_emp   = isset($_SESSION['emp_id']);

$fetch_as_emp = false;
if ($portal === 'employee') {
    $fetch_as_emp = true;
} elseif ($portal === 'admin') {
    $fetch_as_emp = false;
} elseif ($is_emp && !$is_admin) {
    $fetch_as_emp = true;
}

$where = "";
if ($fetch_as_emp && $is_emp) {
    $emp_id = intval($_SESSION['emp_id']);
    $where  = "(recipient_type = 'all_employees' OR (recipient_type = 'employee' AND recipient_id = $emp_id))";
} elseif ($is_admin) {
    $admin_id = intval($_SESSION['admin_id'] ?? 0);
    $is_super = false;
    if (isset($_SESSION['admin_email'])) {
        $ae    = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $a_res = mysqli_query($con, "SELECT admin_id, is_super_admin, admin_job FROM admins WHERE admin_email = '$ae' LIMIT 1");
        if ($a_res && $a_row = mysqli_fetch_assoc($a_res)) {
            $admin_id = intval($a_row['admin_id']);
            $_SESSION['admin_id'] = $admin_id;
            if (intval($a_row['is_super_admin'] ?? 0) === 1 || strcasecmp(trim($a_row['admin_job'] ?? ''), 'Super Admin') === 0) {
                $is_super = true;
            }
        }
    }

    // Admins receive:
    // 1. Broadcasts to all admins ('all_admins')
    // 2. Direct notifications addressed to their admin_id ('admin' AND recipient_id = $admin_id)
    // 3. Fallback generic notifications ('admin' AND recipient_id = 0)
    $where = "(recipient_type = 'all_admins' OR (recipient_type = 'admin' AND (recipient_id = $admin_id OR recipient_id = 0)))";
} else {
    // Not logged in
    if (ob_get_length()) ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Auto-check time-based notifications (birthdays, leaves) server-side (throttled)
if (function_exists('checkTimeBasedSystemNotifications')) {
    checkTimeBasedSystemNotifications($con);
}

// ── Unread count ──────────────────────────────────────────────────────────────
$count_res    = mysqli_query($con, "SELECT COUNT(*) as unread FROM system_notifications WHERE $where AND is_read = 0");
$unread_count = 0;
if ($count_res && $row = mysqli_fetch_assoc($count_res)) {
    $unread_count = intval($row['unread']);
}

// ── Latest unread ID (cheap single-row check) ─────────────────────────────────
$max_res       = mysqli_query($con, "SELECT MAX(id) as max_id FROM system_notifications WHERE $where AND is_read = 0");
$server_max_id = 0;
if ($max_res && $mx = mysqli_fetch_assoc($max_res)) {
    $server_max_id = intval($mx['max_id'] ?? 0);
}

// ── Early exit if no new notifications since last poll ────────────────────────
// Exit early if the client already knows about all current notifications
// (server_max_id <= client_last_id). This prevents the JS from re-receiving
// already-seen notification IDs on every poll cycle.
if ($client_last_id > 0 && $server_max_id > 0 && $server_max_id <= $client_last_id) {
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success'      => true,
        'unread_count' => $unread_count,
        'no_change'    => true,
    ]);
    exit();
}

// ── Fetch 15 most recent unread notifications ─────────────────────────────────
// SELECT only the fields the frontend needs — do NOT expose recipient_type/recipient_id
$list_res      = mysqli_query($con, "SELECT id, title, message, url, type, is_read, created_at FROM system_notifications WHERE $where AND is_read = 0 ORDER BY id DESC LIMIT 15");
$notifications = [];
if ($list_res) {
    while ($r = mysqli_fetch_assoc($list_res)) {
        $created = strtotime($r['created_at']);
        $diff    = time() - $created;
        if ($diff < 60)        $time_ago = 'just now';
        elseif ($diff < 3600)  $time_ago = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400) $time_ago = floor($diff / 3600) . 'h ago';
        else                   $time_ago = date('d M', $created);

        $raw_url = $r['url'] ?? '';
        if ($fetch_as_emp) {
            if ($r['type'] === 'birthday_today') {
                $raw_url = 'index.php';
            } elseif (in_array($r['type'], ['task_assigned', 'task_completed', 'comment_added']) || strpos($raw_url, 'open_task_id') !== false) {
                $query_part = parse_url($raw_url, PHP_URL_QUERY);
                if (!$query_part && strpos($raw_url, '?') !== false) {
                    $query_part = substr($raw_url, strpos($raw_url, '?') + 1);
                }
                parse_str($query_part ?? '', $params);
                unset($params['team_todo'], $params['global_team_todos'], $params['view_project'], $params['projects']);
                $new_query = http_build_query(array_merge(['todo' => ''], $params));
                $new_query = str_replace(['todo=', 'todo&'], ['todo', 'todo&'], $new_query);
                $raw_url = 'index.php?' . ltrim($new_query, '&');
            }   
        } else {
            if ($r['type'] === 'birthday_today') {
                $raw_url = 'index.php?employees';
            } elseif (in_array($r['type'], ['task_assigned', 'task_completed', 'comment_added']) || strpos($raw_url, 'open_task_id') !== false) {
                $query_part = parse_url($raw_url, PHP_URL_QUERY);
                if (!$query_part && strpos($raw_url, '?') !== false) {
                    $query_part = substr($raw_url, strpos($raw_url, '?') + 1);
                }
                parse_str($query_part ?? '', $params);
                unset($params['team_todo'], $params['todo'], $params['view_project'], $params['projects']);
                $new_query = http_build_query(array_merge(['global_team_todos' => ''], $params));
                $new_query = str_replace(['global_team_todos=', 'global_team_todos&'], ['global_team_todos', 'global_team_todos&'], $new_query);
                $raw_url = 'index.php?' . ltrim($new_query, '&');
            }
        }

        // Only include safe, display-only fields in the response
        $notifications[] = [
            'id'       => intval($r['id']),
            'title'    => $r['title'],
            'message'  => $r['message'],
            'url'      => $raw_url,
            'type'     => $r['type'],
            'is_read'  => intval($r['is_read']),
            'time_ago' => $time_ago,
        ];
    }
}

if (ob_get_length()) ob_clean();
echo json_encode([
    'success'       => true,
    'unread_count'  => $unread_count,
    'notifications' => $notifications,
    'server_max_id' => $server_max_id,
    'no_change'     => false,
]);
