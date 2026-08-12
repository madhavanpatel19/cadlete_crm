<?php
// =============================================================
// emp_area/pages/dashboard/emp_dashboard.php
// Employee dashboard – real DB data
// =============================================================
if (!isset($con) || !$con) {
    include(__DIR__ . '/../../includes/db.php');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit();
}

$emp_id   = (int)$_SESSION['emp_id'];
$emp_name = $_SESSION['emp_name'];
$today    = date('Y-m-d');

if (!function_exists('parse_work_details')) {
    function parse_work_details(string $text = '')
    {
        $text = trim($text ?? '');
        $res = ['progress' => '', 'planning' => '', 'issues' => '', 'help' => ''];
        if (empty($text)) {
            return $res;
        }

        $headers_pattern = "/(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?)/iu";

        if (preg_match($headers_pattern, $text)) {
            $clean = function ($s) {
                $s = preg_replace("/^Today[’']s Progress:\s*/iu", "", $s);
                $s = preg_replace("/^Planning for Tomorrow:\s*/iu", "", $s);
                $s = preg_replace("/^Issues:\s*/iu", "", $s);
                $s = preg_replace("/^Need any Help\??:\s*/iu", "", $s);
                $s = preg_replace("/\n{2,}/", "\n", $s);
                return trim($s);
            };

            if (preg_match("/Today[’']s Progress:\s*(.*?)(?=(?:Planning for Tomorrow:|Issues:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['progress'] = $clean($m[1]);
            }
            if (preg_match("/Planning for Tomorrow:\s*(.*?)(?=(?:Today[’']s Progress:|Issues:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['planning'] = $clean($m[1]);
            }
            if (preg_match("/Issues:\s*(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Need any Help\?:?)|$)/isu", $text, $m)) {
                $res['issues'] = $clean($m[1]);
            }
            if (preg_match("/Need any Help\?:?\s*(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:)|$)/isu", $text, $m)) {
                $res['help'] = $clean($m[1]);
            }

            if (empty($res['progress'])) {
                if (preg_match("/^(.*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?))/isu", $text, $m)) {
                    $res['progress'] = $clean($m[1]);
                }
            }
        } else {
            $res['progress'] = $text;
        }
        return $res;
    }
}

// ── Attendance today ──────────────────────────────────────────
$res          = mysqli_query($con, "SELECT * FROM attendance WHERE emp_id='$emp_id' AND attendance_date='$today'");
$today_record = mysqli_fetch_assoc($res);
$parsed_remarks = parse_work_details($today_record['remarks'] ?? '');

// ── Auto-fill today's completed To-Do tasks into Today's Progress ──────────
// Build authoritative deduplicated list from DB (single source of truth)
$todos_today_q = mysqli_query($con, "SELECT t.task_name, p.project_name
    FROM project_team_todos t
    LEFT JOIN client_projects p ON t.project_id = p.id
    WHERE t.emp_id = '$emp_id'
      AND t.status = 1
      AND DATE(COALESCE(t.completed_at, t.due_date, t.created_at)) = '$today'
      AND t.deleted_at IS NULL
    ORDER BY t.id ASC");
if ($todos_today_q && mysqli_num_rows($todos_today_q) > 0) {
    $db_entries   = [];
    while ($ct = mysqli_fetch_assoc($todos_today_q)) {
        $t_name = trim($ct['task_name']);
        $p_name = !empty($ct['project_name']) ? trim($ct['project_name']) : '';
        $entry  = $p_name ? "Completed Task [$p_name]: $t_name" : "Completed Task: $t_name";
        $db_entries[] = '- ' . $entry;
    }
    // Keep any custom (non-Completed Task) lines the employee wrote
    $custom_lines = [];
    foreach (explode("\n", $parsed_remarks['progress']) as $line) {
        $line = trim($line);
        if ($line !== '' && stripos($line, 'Completed Task') === false) {
            $custom_lines[] = $line;
        }
    }
    $all_lines = array_merge($db_entries, $custom_lines);
    $parsed_remarks['progress'] = implode("\n", $all_lines);
}


if ($today_record) {
    $att_id = (int)$today_record['id'];
    $logs_res = mysqli_query($con, "SELECT * FROM attendance_logs WHERE att_id = $att_id ORDER BY action_time ASC");
    if ($logs_res && mysqli_num_rows($logs_res) > 0) {
        $events = [];
        while ($lr = mysqli_fetch_assoc($logs_res)) {
            $events[] = $lr;
        }
        $sum_secs = 0;
        $current_start = null;
        foreach ($events as $ev) {
            if (in_array($ev['action'], ['check_in', 'resume'])) {
                $current_start = $ev;
            } elseif (in_array($ev['action'], ['pause', 'check_out']) && $current_start) {
                $start_ts = strtotime($current_start['action_time']);
                $end_ts   = strtotime($ev['action_time']);
                $sum_secs += max(0, $end_ts - $start_ts);
                $current_start = null;
            }
        }
        $today_record['total_duration_secs'] = $sum_secs;
    }
}
// ── Latest Announcement ───────────────────────────────────────
$latest_announcement = "";
$has_announcement = false;
$ticker_res = mysqli_query($con, "SELECT title FROM announcements WHERE is_active=1
    AND (publish_date IS NULL OR publish_date <= NOW())
    AND (end_date IS NULL OR end_date >= NOW())
    ORDER BY publish_date DESC LIMIT 1");
if ($ticker_res && mysqli_num_rows($ticker_res) > 0) {
    $latest_announcement = htmlspecialchars(mysqli_fetch_array($ticker_res)['title']);
    $has_announcement = true;
}

// ── My Tasks (project_team_todos) ────────────────────────────
$tasks_res = mysqli_query(
    $con,
    "SELECT t.*, cp.project_name
     FROM project_team_todos t
     LEFT JOIN client_projects cp ON cp.id = t.project_id
     WHERE t.emp_id = '$emp_id' AND t.status = 0
     ORDER BY CASE WHEN t.priority = 'High' THEN 1 WHEN t.priority = 'Medium' THEN 2 ELSE 3 END ASC, t.created_at DESC LIMIT 5"
);
$tasks = [];
$pending_task_count = 0;
if ($tasks_res) {
    while ($row = mysqli_fetch_assoc($tasks_res)) {
        $tasks[] = $row;
        if ((int)$row['status'] === 0) $pending_task_count++;
    }
}
$total_tasks = count($tasks);

// ── My Projects (assigned_employees contains emp_id) ─────────
$proj_res = mysqli_query(
    $con,
    "SELECT cp.*, cl.name AS client_name
     FROM client_projects cp
     LEFT JOIN clients cl ON cl.id = cp.client_id
     WHERE cp.status = 'Active'
       AND FIND_IN_SET('$emp_id', cp.assigned_employees) > 0
     ORDER BY cp.created_at DESC"
);
$projects = [];
if ($proj_res) {
    while ($row = mysqli_fetch_assoc($proj_res)) $projects[] = $row;
}
$active_proj_count = count($projects);

// ── This week's time log ──────────────────────────────────────
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_res   = mysqli_query(
    $con,
    "SELECT attendance_date, total_duration_secs, is_working, last_resume_time
     FROM attendance WHERE emp_id='$emp_id'
       AND attendance_date BETWEEN '$week_start' AND '$today'
     ORDER BY attendance_date ASC"
);
$week_data = [];
$week_total_secs = 0;
if ($week_res) {
    while ($r = mysqli_fetch_assoc($week_res)) {
        $secs = ($r['attendance_date'] == $today && isset($today_record['total_duration_secs'])) ? (int)$today_record['total_duration_secs'] : (int)$r['total_duration_secs'];
        if ($r['attendance_date'] == $today && $r['is_working'] && $r['last_resume_time']) {
            $secs += time() - strtotime($r['last_resume_time']);
        }
        $week_data[$r['attendance_date']] = $secs;
        $week_total_secs += $secs;
    }
}
$week_days     = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$week_secs_arr = [];
$displayed_week_mins = 0;
$base_past_mins = 0;
for ($i = 0; $i < 7; $i++) {
    $d = date('Y-m-d', strtotime("monday this week +{$i} days"));
    $s = $week_data[$d] ?? 0;
    $week_secs_arr[] = ['label' => $week_days[$i], 'secs' => $s, 'date' => $d];

    $mins = floor($s / 60);
    $displayed_week_mins += $mins;
    if ($d != $today) {
        $base_past_mins += $mins;
    }
}
$displayed_week_secs = $displayed_week_mins * 60;
$max_week = max(array_column($week_secs_arr, 'secs')) ?: 1;

// ── Today's logged duration ───────────────────────────────────
$today_secs = (int)($today_record['total_duration_secs'] ?? 0);
if ($today_record && $today_record['is_working']) {
    $startTime = !empty($today_record['last_resume_time']) ? $today_record['last_resume_time'] : ($today . ' ' . $today_record['check_in_time']);
    if ($startTime) {
        $today_secs += time() - strtotime($startTime);
    }
}

function fmtHM(int $secs)
{
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return "{$h}h " . str_pad($m, 2, '0', STR_PAD_LEFT) . "m";
}

function fmtHMS(int $secs)
{
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    $s = $secs % 60;
    return str_pad($h, 2, '0', STR_PAD_LEFT) . ":" . str_pad($m, 2, '0', STR_PAD_LEFT) . ":" . str_pad($s, 2, '0', STR_PAD_LEFT);
}

// ── Pinned Quick Links ───────────────────────────────────────
$allowed_categories = [];
$cat_q = mysqli_query($con, "SELECT category FROM company_links_assignments WHERE emp_id = '$emp_id'");
if ($cat_q) {
    while ($row = mysqli_fetch_assoc($cat_q)) {
        $allowed_categories[] = "'" . mysqli_real_escape_string($con, $row['category']) . "'";
    }
}
$pinned_links = [];
if (!empty($allowed_categories)) {
    $cat_list = implode(',', $allowed_categories);
    $q_pinned = "SELECT * FROM company_links WHERE category IN ($cat_list) AND is_pinned = 1 ORDER BY created_at DESC LIMIT 5";
    $run_pinned = mysqli_query($con, $q_pinned);
    if ($run_pinned) {
        while ($r = mysqli_fetch_assoc($run_pinned)) {
            $pinned_links[] = $r;
        }
    }
}

function getResourceTypePhp(string $url)
{
    $path = parse_url($url, PHP_URL_PATH);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['pdf'])) return ['type' => 'PDF', 'icon' => 'fa-file-pdf-o', 'color' => '#ef4444', 'bg' => '#fef2f2'];
    if (in_array($ext, ['xlsx', 'xls', 'csv'])) return ['type' => 'Excel', 'icon' => 'fa-file-excel-o', 'color' => '#10b981', 'bg' => '#ecfdf5'];
    if (in_array($ext, ['docx', 'doc'])) return ['type' => 'Word', 'icon' => 'fa-file-word-o', 'color' => '#3b82f6', 'bg' => '#eff6ff'];
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'svg'])) return ['type' => 'Image', 'icon' => 'fa-file-image-o', 'color' => '#8b5cf6', 'bg' => '#f5f3ff'];
    if (in_array($ext, ['zip', 'rar'])) return ['type' => 'Archive', 'icon' => 'fa-file-archive-o', 'color' => '#f59e0b', 'bg' => '#fffbeb'];
    return ['type' => 'Link', 'icon' => 'fa-link', 'color' => '#3b82f6', 'bg' => '#eff6ff'];
}
?>
<style>
    /* ═══════════════════════════════════════════════════════
   EMPLOYEE DASHBOARD STYLES
   ═══════════════════════════════════════════════════════ */
    .dash-wrap {
        padding: 0 24px 40px;
        font-family: 'Inter', sans-serif;
        background: #f9fafb;
        min-height: 100vh;
    }

    /* ── Announcement ── */
    .premium-swal-popup {
        border-radius: 20px !important;
        padding: 24px !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
        border: none !important;
        width: 440px !important;
        max-width: 90vw !important;
    }

    .dash-announce {
        background: linear-gradient(90deg, #fff1f2, #fce7f3);
        border-left: 4px solid #e11d48;
        border-radius: 10px;
        padding: 11px 18px;
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .dash-announce i {
        color: #e11d48;
        font-size: 16px;
    }

    /* ── Stat Cards ── */
    .dash-cards {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }

    .dc {
        background: #fff;
        border: 1px solid #f3f4f6;
        border-radius: 14px;
        padding: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        position: relative;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        transition: box-shadow .2s, transform .2s;
        overflow: hidden;
    }

    .dc:hover {
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .dc-icon {
        width: 50px;
        height: 50px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        flex-shrink: 0;
    }

    .dc-icon.red {
        background: #ffe4e6;
        color: #e11d48;
    }

    .dc-icon.blue {
        background: #ffeaeb;
        color: #dd2127;
    }

    .dc-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .dc-icon.orange {
        background: #ffedd5;
        color: #ea580c;
    }

    .dc-body h5 {
        margin: 0 0 2px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #9ca3af;
    }

    .dc-body h2 {
        margin: 0 0 2px;
        font-size: 26px;
        font-weight: 800;
        color: #111827;
        line-height: 1;
    }

    .dc-body p {
        margin: 0;
        font-size: 11px;
        color: #9ca3af;
    }

    .dc-link {
        position: absolute;
        bottom: 12px;
        right: 14px;
        font-size: 11px;
        font-weight: 700;
        color: #e11d48;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 3px;
        opacity: 0;
        transition: opacity .2s;
    }

    .dc:hover .dc-link {
        opacity: 1;
    }

    /* ── Attendance Card ── */
    .dc.att-dc {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        padding: 14px 16px;
    }

    .att-lbl {
        font-size: 11px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .att-times {
        display: flex;
        gap: 6px;
    }

    .att-ti {
        flex: 1;
        background: #f9fafb;
        border: 1px solid #f3f4f6;
        border-radius: 8px;
        padding: 6px;
        text-align: center;
    }

    .att-ti span {
        display: block;
        font-size: 9px;
        font-weight: 700;
        color: #9ca3af;
        text-transform: uppercase;
    }

    .att-ti strong {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
    }

    .att-ti.dur strong {
        color: #2563eb;
    }

    .att-btns {
        display: flex;
        gap: 7px;
    }

    .att-btn {
        flex: 1;
        border: none;
        border-radius: 9px;
        padding: 9px 0;
        font-weight: 700;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        cursor: pointer;
        color: #fff;
        transition: opacity .15s, transform .15s;
    }

    .att-btn:hover:not(:disabled) {
        opacity: .88;
        transform: translateY(-1px);
    }

    .att-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .ab-checkin {
        background: #10b981;
    }

    .ab-pause {
        background: #6b7280;
    }

    .ab-checkout {
        background: #f59e0b;
    }

    .ab-done {
        background: #e5e7eb;
        color: #6b7280;
    }

    /* ── Main grid ── */
    .dash-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 18px;
    }

    /* ── Section header ── */
    .sec-hd {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .sec-hd h3 {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .sec-hd a {
        font-size: 12px;
        font-weight: 700;
        color: #e11d48;
        text-decoration: none;
        outline: none;
    }

    .sec-hd a:focus,
    .sec-hd a:active {
        outline: none !important;
        box-shadow: none !important;
    }

    /* ── Card box ── */
    .cbox {
        background: #fff;
        border: 1px solid #f3f4f6;
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 18px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        display: flex;
        flex-direction: column;
    }

    /* ── Task item ── */
    .t-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #f1f5f9;
        background: #fff;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }

    .t-row:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
        transform: translateX(2px);
    }

    .t-row:last-child {
        margin-bottom: 0;
    }

    .t-chk {
        width: 20px;
        height: 20px;
        border: 2px solid #d1d5db;
        border-radius: 50%;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .t-chk.done {
        background: #10b981;
        border-color: #10b981;
    }

    .t-chk.done::after {
        content: '✓';
        color: #fff;
        font-size: 11px;
        font-weight: 700;
    }

    .t-info {
        flex: 1;
        min-width: 0;
    }

    .t-name {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        margin: 0;
    }

    .t-name.done-txt {
        text-decoration: line-through;
        color: #9ca3af;
    }

    .t-proj {
        font-size: 11px;
        color: #e11d48;
        margin: 2px 0 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .t-badge {
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: .04em;
        flex-shrink: 0;
    }

    .tb-high {
        background: #fee2e2;
        color: #dc2626;
    }

    .tb-medium {
        background: #fef3c7;
        color: #d97706;
    }

    .tb-low {
        background: #dcfce7;
        color: #16a34a;
    }

    .t-date {
        font-size: 11px;
        color: #9ca3af;
        flex-shrink: 0;
        min-width: 48px;
        text-align: right;
    }

    /* ── Project item ── */
    .p-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #f1f5f9;
        background: #fff;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }

    .p-row:hover {
        background: #f8fafc;
        border-color: #e2e8f0;
        transform: translateX(2px);
    }

    .p-row:last-child {
        margin-bottom: 0;
    }

    .p-av {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #ffe4e6;
        color: #e11d48;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 800;
        flex-shrink: 0;
    }

    .p-info {
        flex: 1;
        min-width: 0;
    }

    .p-info h4 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: #1f2937;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .p-info small {
        font-size: 11px;
        color: #9ca3af;
    }

    .p-dl {
        font-size: 11px;
        color: #6b7280;
        text-align: right;
        flex-shrink: 0;
    }

    /* ── Quick links ── */
    .ql-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px 10px;
        flex: 1;
        align-content: center;
    }

    .ql-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        color: #4b5563;
        font-size: 10px;
        font-weight: 600;
        text-align: center;
        transition: color .2s;
        outline: none;
    }

    .ql-item:focus,
    .ql-item:active {
        outline: none !important;
        box-shadow: none !important;
        text-decoration: none !important;
    }

    .ql-item:hover {
        color: #e11d48;
    }

    .ql-ic {
        width: 50px;
        height: 50px;
        background: #fff1f2;
        color: #e11d48;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        transition: transform .2s, box-shadow .2s;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .ql-item:hover .ql-ic {
        transform: scale(1.09);
        box-shadow: 0 4px 12px rgba(225, 29, 72, 0.2);
    }

    /* ── Time log chart ── */
    .tl-total {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
        margin: 0 0 4px;
        line-height: 1.2;
    }

    .tl-sub {
        font-size: 13px;
        color: #64748b;
        margin: 0 0 20px;
        font-weight: 500;
    }

    .chart-box-premium {
        background: #ffffff;
        border: 1.5px solid #f1f5f9;
        border-radius: 16px;
        padding: 24px 16px 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .chart-wrap {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex: 1;
        position: relative;
        min-height: 140px;
    }

    .bar-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        flex: 1;
        gap: 8px;
        height: 100%;
        position: relative;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .bar-col:hover {
        transform: translateY(-3px);
    }

    .bar-val {
        font-size: 10px;
        color: #64748b;
        white-space: nowrap;
        font-weight: 700;
        position: absolute;
        top: 0;
        opacity: 0.8;
        transition: opacity 0.2s;
    }

    .bar-col:hover .bar-val {
        opacity: 1;
        color: #1e293b;
    }

    .bar-fill {
        width: 100%;
        max-width: 32px;
        border-radius: 8px;
        min-height: 4px;
        background: #fda4af;
        transition: height .6s cubic-bezier(0.4, 0, 0.2, 1), background 0.3s;
    }

    .bar-col:hover .bar-fill {
        background: #fb7185;
    }

    .bar-fill.today-bar {
        background: #e11d48;
        box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
    }

    .bar-col:hover .bar-fill.today-bar {
        background: #be123c;
    }

    .bar-fill.empty-bar {
        background: #e2e8f0;
    }

    .bar-col:hover .bar-fill.empty-bar {
        background: #cbd5e1;
    }

    .bar-lbl {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bar-col.today-lbl .bar-lbl {
        color: #e11d48;
    }

    /* ── Empty state ── */
    .empty-s {
        text-align: center;
        padding: 30px 15px;
        color: #94a3b8;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 150px;
        font-size: 13px;
        font-weight: 500;
    }

    .empty-s i {
        font-size: 36px;
        display: block;
        margin-bottom: 10px;
        opacity: .4;
        color: #94a3b8;
    }

    /* ── Scrollbars ── */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* ── Bookmarks ── */
    .bm-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        border-color: #dd2127 !important;
    }

    .bm-box:hover .bm-rem {
        opacity: 1 !important;
    }

    .bm-box-empty:hover {
        border-color: #dd2127 !important;
        background: #ffeaeb !important;
    }

    .bm-box-empty:hover i {
        color: #dd2127 !important;
    }

    /* ── SweetAlert Input ── */
    .swal2-input:focus {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
    }

    /* ── Responsive ── */
    @media (max-width:1100px) {
        .dash-grid {
            grid-template-columns: 1fr;
        }

        .dash-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width:600px) {
        .dash-cards {
            grid-template-columns: 1fr;
        }

        .dash-wrap {
            padding: 0 12px 30px;
        }

        .ql-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }
</style>

<div class="dash-wrap">

    <!-- Announcement -->
    <?php if ($has_announcement): ?>
        <div class="dash-announce">
            <i class="fa fa-bullhorn" style="flex-shrink: 0;"></i>
            <marquee scrollamount="5" scrolldelay="50" onmouseover="this.stop();" onmouseout="this.start();" style="margin: 0; padding: 0;">
                <?php echo $latest_announcement; ?>
            </marquee>
        </div>
    <?php endif; ?>

    <!-- ── Stat Cards ── -->
    <div class="dash-cards">

        <!-- Tasks -->
        <div class="dc">
            <div class="dc-icon red"><i class="fa fa-tasks"></i></div>
            <div class="dc-body">
                <h5>My Tasks</h5>
                <h2><?php echo $pending_task_count; ?></h2>
                <p><?php echo $total_tasks; ?> total assigned</p>
            </div>
            <!-- <a href="#taskSec" class="dc-link">View <i class="fa fa-arrow-right"></i></a> -->
        </div>

        <!-- Projects -->
        <div class="dc">
            <div class="dc-icon blue"><i class="fa fa-briefcase"></i></div>
            <div class="dc-body">
                <h5>Active Projects</h5>
                <h2><?php echo $active_proj_count; ?></h2>
                <p>Ongoing projects</p>
            </div>
            <!-- <a href="#projSec" class="dc-link">View <i class="fa fa-arrow-right"></i></a> -->
        </div>

        <!-- Today Work Log -->
        <!-- <div class="dc">
            <div class="dc-icon green"><i class="fa fa-clock-o"></i></div>
            <div class="dc-body">
                <h5>Today's Work Log</h5>
                <h2 id="displayDurationCard"><?php echo $today_secs > 0 ? fmtHM($today_secs) : '--:--'; ?></h2>
                <p>Logged today</p>
            </div>
            <a href="index.php?worksheet" class="dc-link">View <i class="fa fa-arrow-right"></i></a>
        </div> -->

        <!-- Attendance Widget -->
        <div class="dc att-dc">
            <div class="att-lbl"><i class="fa fa-calendar-check-o"></i> Attendance</div>
            <div class="att-times">
                <div class="att-ti">
                    <span>In</span>
                    <strong id="displayCheckIn"><?php echo ($today_record && $today_record['check_in_time']) ? date('h:i A', strtotime($today_record['check_in_time'])) : '--:--'; ?></strong>
                </div>
                <div class="att-ti">
                    <span>Out</span>
                    <strong id="displayCheckOut"><?php echo ($today_record && $today_record['check_out_time']) ? date('h:i A', strtotime($today_record['check_out_time'])) : '--:--'; ?></strong>
                </div>
                <div class="att-ti dur">
                    <span>Dur</span>
                    <strong id="displayDuration"><?php echo $today_secs > 0 ? fmtHMS($today_secs) : '00:00:00'; ?></strong>
                </div>
            </div>
            <div class="att-btns">
                <?php if (!$today_record || empty($today_record['check_in_time'])): ?>
                    <button id="btnCheckIn" class="att-btn ab-checkin"><i class="fa fa-play"></i> Check In</button>
                <?php elseif (empty($today_record['check_out_time'])): ?>
                    <?php if ($today_record['is_working']): ?>
                        <button id="btnPause" class="att-btn ab-pause"><i class="fa fa-pause"></i> Pause</button>
                    <?php else: ?>
                        <button id="btnResume" class="att-btn ab-checkin"><i class="fa fa-play"></i> Resume</button>
                    <?php endif; ?>
                    <button id="btnCheckOut" class="att-btn ab-checkout"><i class="fa fa-stop"></i> Out</button>
                <?php else: ?>
                    <button class="att-btn ab-done" disabled><i class="fa fa-check"></i> Completed</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Main Grid ── -->
    <div class="dash-grid">
        <!-- My Tasks -->
        <div class="cbox" style="margin-bottom: 0;">
            <div class="sec-hd" id="taskSec">
                <h3><i class="fa fa-tasks" style="color:#e11d48;"></i> My Tasks</h3>
                <a href="index.php?todo">View All</a>
            </div>
            <div id="emptyTasksMsg" class="empty-s" style="<?php echo empty($tasks) ? '' : 'display:none;'; ?>">
                <i class="fa fa-check-circle"></i>You're all caught up! No pending tasks.
            </div>
            <div style="max-height: 220px; overflow-y: auto; padding-right: 5px;" class="custom-scrollbar">
                <?php foreach ($tasks as $task):
                    $done     = (int)$task['status'] === 1;
                    $priority = strtolower($task['priority'] ?? 'low');
                    $due      = !empty($task['due_date']) ? date('d-m-Y', strtotime($task['due_date'])) : '';
                    $pname    = $task['project_name'] ?: 'General';
                    $task_id  = $task['id'];
                ?>
                    <div class="t-row" id="taskRow_<?php echo $task_id; ?>" style="cursor: pointer;" onclick="completeTask(<?php echo $task_id; ?>)">
                        <div class="t-chk <?php echo $done ? 'done' : ''; ?>"></div>
                        <div class="t-info">
                            <div class="t-name <?php echo $done ? 'done-txt' : ''; ?>"><?php echo htmlspecialchars($task['task_name']); ?></div>
                            <div class="t-proj"><?php echo htmlspecialchars($pname); ?></div>
                        </div>
                        <span class="t-badge tb-<?php echo $priority; ?>"><?php echo ucfirst($priority); ?></span>
                        <?php if ($due): ?><div class="t-date"><?php echo $due; ?></div><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- My Bookmarks -->
        <div class="cbox" style="margin-bottom: 0;">
            <div class="sec-hd">
                <h3><i class="fa fa-bookmark" style="color:#e11d48;"></i> My Bookmarks</h3>
                <a href="#" onclick="clearBookmarks(); return false;" style="font-weight:normal; font-size:11px; color:#6b7280;"><i class="fa fa-trash"></i> Clear All</a>
            </div>
            <div id="bookmarksGrid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px;">
                <!-- Filled via JS -->
            </div>
        </div>

        <!-- My Projects -->
        <div class="cbox" style="margin-bottom: 0;">
            <div class="sec-hd" id="projSec">
                <h3><i class="fa fa-briefcase" style="color:#dd2127;"></i> My Projects</h3>
                <a href="index.php?projects">View All</a>
            </div>
            <?php if (empty($projects)): ?>
                <div class="empty-s"><i class="fa fa-folder-open-o"></i>No active projects assigned to you.</div>
            <?php else: ?>
                <div style="max-height: 320px; overflow-y: auto; padding-right: 5px;" class="custom-scrollbar">
                    <?php foreach ($projects as $proj):
                        $init = strtoupper(mb_substr($proj['project_name'], 0, 2));
                        $dl   = !empty($proj['deadline']) ? date('d-m-Y', strtotime($proj['deadline'])) : 'No deadline';
                    ?>
                        <div class="p-row">
                            <div class="p-av"><?php echo $init; ?></div>
                            <div class="p-info">
                                <h4><?php echo htmlspecialchars($proj['project_name']); ?></h4>
                                <small><?php echo htmlspecialchars($proj['client_name'] ?? 'Client'); ?></small>
                            </div>
                            <div class="p-dl"><?php echo $dl; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- This Week's Time Log -->
        <div class="cbox" style="margin-bottom: 0;">
            <div class="sec-hd">
                <h3><i class="fa fa-bar-chart" style="color:#e11d48;"></i> Time Log <span style="font-size:11px;color:#9ca3af;font-weight:400;">(This Week)</span></h3>
                <a href="index.php?worksheet">View All</a>
            </div>
            <p class="tl-total" id="weekTotalLabel"><?php echo fmtHM($displayed_week_secs); ?></p>
            <p class="tl-sub">Total logged this week</p>
            <?php if ($week_total_secs > 0): ?>
                <div class="chart-box-premium">
                    <div class="chart-wrap">
                        <?php foreach ($week_secs_arr as $day):
                            $pct     = $max_week > 0 ? ($day['secs'] / $max_week) * 95 : 0;
                            $barH    = max(4, $pct);
                            $isToday = ($day['date'] == $today);
                            $isEmpty = ($day['secs'] == 0);
                            $cls     = $isToday ? 'today-bar' : ($isEmpty ? 'empty-bar' : '');
                        ?>
                            <div class="bar-col <?php echo $isToday ? 'today-lbl' : ''; ?>">
                                <span class="bar-val" <?php echo $isToday ? 'id="todayBarVal"' : ''; ?>><?php echo fmtHM($day['secs']); ?></span>
                                <div class="bar-fill <?php echo $cls; ?>" <?php echo $isToday ? 'id="todayBarFill"' : ''; ?> style="height:<?php echo $barH; ?>px;"></div>
                                <span class="bar-lbl"><?php echo $day['label']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-s"><i class="fa fa-bar-chart"></i>No time logged this week yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var AJAX_URL = '../admin_area/ajax/attendance/ajax_handle_attendance.php';

        var att = {
            isWorking: <?php echo ($today_record && $today_record['is_working']) ? 'true' : 'false'; ?>,
            totalSecs: <?php echo ($today_record && $today_record['total_duration_secs']) ? (int)$today_record['total_duration_secs'] : 0; ?>,
            lastResume: <?php echo ($today_record && $today_record['last_resume_time']) ? (strtotime($today_record['last_resume_time']) * 1000) : 'null'; ?>,
            checkedOut: <?php echo ($today_record && !empty($today_record['check_out_time'])) ? 'true' : 'false'; ?>,
            basePastMins: <?php echo (int)$base_past_mins; ?>,
            maxWeek: <?php echo $max_week > 0 ? $max_week : 1; ?>
        };
        if (att.totalSecs === 0 && <?php echo ($today_record && $today_record['check_in_time']) ? 'true' : 'false'; ?>) {
            att.lastResume = <?php echo ($today_record && $today_record['check_in_time']) ? (strtotime($today . ' ' . $today_record['check_in_time']) * 1000) : 'null'; ?>;
        }

        var serverTime = <?php echo time() * 1000; ?>;
        var serverClientOffset = serverTime - Date.now();

        function fmtDur(s) {
            var h = Math.floor(s / 3600),
                m = Math.floor((s % 3600) / 60);
            return String(h) + 'h ' + String(m).padStart(2, '0') + 'm';
        }

        function fmtHMS(s) {
            var h = Math.floor(s / 3600),
                m = Math.floor((s % 3600) / 60),
                sec = Math.floor(s % 60);
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }

        function tick() {
            if (att.checkedOut) return;
            var cur = att.totalSecs;
            var activeSecs = 0;
            if (att.isWorking && att.lastResume) {
                var adjustedNow = Date.now() + serverClientOffset;
                activeSecs = Math.floor((adjustedNow - att.lastResume) / 1000);
            }
            cur += activeSecs;

            if (cur > 0) {
                var t = fmtDur(cur);
                var tHMS = fmtHMS(cur);
                $('#displayDuration,#displayDurationCard').text(tHMS);

                var todayEl = $('#todayBarVal');
                if (todayEl.length) {
                    todayEl.text(t);
                    var currentMax = Math.max(att.maxWeek, cur);
                    var pct = (cur / currentMax) * 95;
                    var barH = Math.max(4, pct);
                    $('#todayBarFill').css('height', barH + 'px');
                }
                var weekEl = $('#weekTotalLabel');
                if (weekEl.length) {
                    var totalMins = att.basePastMins + Math.floor(cur / 60);
                    weekEl.text(fmtDur(totalMins * 60));
                }
            }
        }
        if (!att.checkedOut) {
            setInterval(tick, 1000);
            tick();
        } else {
            $('#displayDuration,#displayDurationCard').text(fmtHMS(att.totalSecs));
        }

        function ajaxAtt(action, $btn, resetHtml) {
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: AJAX_URL,
                type: 'POST',
                data: {
                    action: action
                },
                dataType: 'json',
                success: function(r) {
                    if (r.status === 'success') location.reload();
                    else {
                        $btn.prop('disabled', false).html(resetHtml);
                        Swal.fire('Notification', r.message, 'info');
                    }
                }
            });
        }

        $(document).on('click', '#btnCheckIn', function() {
            ajaxAtt('check_in', $(this), '<i class="fa fa-play"></i> Check In');
        });
        $(document).on('click', '#btnPause', function() {
            ajaxAtt('pause', $(this), '<i class="fa fa-pause"></i> Pause');
        });
        $(document).on('click', '#btnResume', function() {
            ajaxAtt('resume', $(this), '<i class="fa fa-play"></i> Resume');
        });

        /* Check Out – open modal */
        $(document).on('click', '#btnCheckOut', function() {
            var now = new Date();
            $('#modalCheckOutTime').val(String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0'));
            var ci = $('#displayCheckIn').text().trim();
            if (ci !== '--:--') {
                var m = ci.match(/(\d+):(\d+)\s*(AM|PM)/i);
                if (m) {
                    var h = parseInt(m[1]);
                    if (m[3].toUpperCase() === 'PM' && h < 12) h += 12;
                    if (m[3].toUpperCase() === 'AM' && h === 12) h = 0;
                    $('#modalCheckInTime').val(String(h).padStart(2, '0') + ':' + m[2]);
                }
            }
            $('#checkOutModal').modal('show');
        });

        window.parseWorkDetails = function(text) {
            text = text || '';
            var progress = '', planning = '', issues = '', help = '';

            function cleanHeaders(s) {
                if (!s) return '';
                return s.replace(/^Today[’']s Progress:\s*/gi, '')
                        .replace(/^Planning for Tomorrow:\s*/gi, '')
                        .replace(/^Issues:\s*/gi, '')
                        .replace(/^Need any Help\??:\s*/gi, '')
                        .trim();
            }

            var headersPattern = /(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?)/i;

            if (headersPattern.test(text)) {
                var matchP = text.match(/Today[’']s Progress:\s*([\s\S]*?)(?=(?:Planning for Tomorrow:|Issues:|Need any Help\?:?)|$)/i);
                if (matchP) progress = cleanHeaders(matchP[1]);

                var matchPlan = text.match(/Planning for Tomorrow:\s*([\s\S]*?)(?=(?:Today[’']s Progress:|Issues:|Need any Help\?:?)|$)/i);
                if (matchPlan) planning = cleanHeaders(matchPlan[1]);

                var matchIss = text.match(/Issues:\s*([\s\S]*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Need any Help\?:?)|$)/i);
                if (matchIss) issues = cleanHeaders(matchIss[1]);

                var matchHelp = text.match(/Need any Help\?:?\s*([\s\S]*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:)|$)/i);
                if (matchHelp) help = cleanHeaders(matchHelp[1]);

                if (!progress) {
                    var matchFirst = text.match(/^([\s\S]*?)(?=(?:Today[’']s Progress:|Planning for Tomorrow:|Issues:|Need any Help\?:?))/i);
                    if (matchFirst) progress = cleanHeaders(matchFirst[1]);
                }
            } else {
                progress = text.trim();
            }

            return {
                progress: progress,
                planning: planning,
                issues: issues,
                help: help
            };
        };

        /* Confirm Check Out */
        $(document).on('click', '#confirmCheckOut', function() {
            function cleanHeaders(s) {
                if (!s) return '';
                s = s.replace(/Today.*?Progress:\s*/gi, '')
                    .replace(/Planning for Tomorrow:\s*/gi, '')
                    .replace(/Issues:\s*/gi, '')
                    .replace(/Need any Help\??:\s*/gi, '');
                return s.trim();
            }
            var progress = cleanHeaders($('#wsTodayProgress').val());
            var planning = cleanHeaders($('#wsPlanningTomorrow').val());
            var issues = cleanHeaders($('#wsIssues').val());
            var help = cleanHeaders($('#wsNeedHelp').val());

            if (!progress) {
                Swal.fire('Notification', 'Please provide Today’s Progress.', 'info');
                return;
            }

            var parts = [];
            parts.push("Today’s Progress:\n" + progress);
            if (planning) parts.push("Planning for Tomorrow:\n" + planning);
            if (issues) parts.push("Issues:\n" + issues);
            if (help) parts.push("Need any Help?:\n" + help);

            var wd = parts.join("\n\n");
            if (!$('#modalCheckInTime').val() || !$('#modalCheckOutTime').val()) {
                Swal.fire('Notification', 'Check-in and check-out times required.', 'info');
                return;
            }
            var hasPhoto = false;
            for (var i = 1; i <= 4; i++) {
                var fi = document.getElementById('work_photo_' + i);
                var prev = document.getElementById('preview_' + i);
                if ((fi && fi.files && fi.files.length > 0) || (prev && prev.src && prev.style.display !== 'none' && prev.src !== '' && !prev.src.endsWith('/'))) {
                    hasPhoto = true;
                }
            }
            if (!hasPhoto) {
                Swal.fire('Notification', 'Please upload at least 1 work photo.', 'info');
                return;
            }
            var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
            $('#btnCancelCheckOut').prop('disabled', true);
            var fd = new FormData();
            fd.append('action', 'check_out');
            fd.append('work_details', wd);
            fd.append('check_in_time', $('#modalCheckInTime').val());
            fd.append('check_out_time', $('#modalCheckOutTime').val());
            for (var i = 1; i <= 4; i++) {
                var fi = document.getElementById('work_photo_' + i);
                if (fi && fi.files[0]) fd.append('work_photos[]', fi.files[0]);
            }
            $.ajax({
                url: AJAX_URL,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(r) {
                    if (r.status === 'success') location.reload();
                    else {
                        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Submit Worksheet & Check Out');
                        $('#btnCancelCheckOut').prop('disabled', false);
                        Swal.fire('Notification', r.message, 'info');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Submit Worksheet & Check Out');
                    $('#btnCancelCheckOut').prop('disabled', false);
                    Swal.fire('Error', 'An unexpected error occurred. This is likely due to the uploaded images being too large.', 'error');
                    console.error("AJAX Error: ", status, error, xhr.responseText);
                }
            });
        });

        window.previewWorkPhoto = function(input, id) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#preview_' + id).attr('src', e.target.result).show();
                    $('#box_' + id).find('i').hide();
                };
                reader.readAsDataURL(input.files[0]);
            }
        };

        // --- Bookmarks functionality ---
        window.initBookmarks = function() {
            let b = localStorage.getItem('empBookmarks_<?php echo $emp_id; ?>');
            let bookmarks = b ? JSON.parse(b) : [];

            let html = '';
            for (let i = 0; i < 8; i++) {
                if (bookmarks[i] && bookmarks[i].name) {
                    let domain = '';
                    try {
                        domain = new URL(bookmarks[i].url).hostname;
                    } catch (e) {
                        domain = bookmarks[i].url;
                    }
                    html += `<div style="height:80px; border:1px solid #dd2127; border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; position:relative; background:#ffffff; transition:all 0.2s; cursor:pointer;" class="bm-box" onclick="window.open('${bookmarks[i].url}', '_blank')">
                        <div onclick="event.stopPropagation(); removeBookmark(${i})" style="position:absolute; top:-6px; right:-6px; background:#ef4444; color:#fff; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; cursor:pointer; opacity:0; transition:0.2s; box-shadow:0 2px 4px rgba(0,0,0,0.2);" class="bm-rem"><i class="fa fa-times"></i></div>
                        <div style="width:36px; height:36px; border-radius:10px; background:#ffeaeb; display:flex; align-items:center; justify-content:center; margin-bottom:6px;">
                            <img src="https://www.google.com/s2/favicons?domain=${domain}&sz=64" style="width:18px; height:18px; border-radius:3px;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="fa fa-globe" style="color:#3b82f6; font-size:16px; display:none;"></i>
                        </div>
                        <span style="font-size:11px; font-weight:600; color:#1e293b; text-align:center; width:90%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${bookmarks[i].name}</span>
                    </div>`;
                } else {
                    html += `<div onclick="addBookmark(${i})" style="height:80px; border:2px dashed #cbd5e1; border-radius:12px; display:flex; align-items:center; justify-content:center; cursor:pointer; background:#f8fafc; transition:all 0.2s;" class="bm-box-empty">
                        <i class="fa fa-plus" style="color:#94a3b8; font-size:20px;"></i>
                    </div>`;
                }
            }
            $('#bookmarksGrid').html(html);
        };

        window.addBookmark = function(idx) {
            Swal.fire({
                html: `
                    <div style="padding: 10px 5px 5px 5px;">
                        <div style="width: 48px; height: 48px; background: #ffeaeb; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
                            <i class="fa fa-bookmark" style="color: #dc2626; font-size: 20px;"></i>
                        </div>
                        <h4 style="font-weight: 800; color: #0f172a; font-size: 19px; margin: 0 0 6px 0;">Add Bookmark</h4>                        
                        <div style="text-align: left; margin-bottom: 16px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-tag" style="color: #dc2626; font-size: 11px;"></i> Bookmark Name <span style="color: #dc2626;">*</span>
                            </label>
                            <input id="swal-input1" type="text" class="form-control" placeholder="e.g. Google, Figma, Portal..." style="height: 46px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #0f172a; width: 100%; outline: none; box-sizing: border-box; transition: 0.2s;" onfocus="this.style.borderColor='#dc2626'; this.style.boxShadow='0 0 0 3px rgba(220, 38, 38, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>

                        <div style="text-align: left; margin-bottom: 10px;">
                            <label style="font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-link" style="color: #dc2626; font-size: 11px;"></i> Website URL <span style="color: #dc2626;">*</span>
                            </label>
                            <input id="swal-input2" type="text" class="form-control" placeholder="e.g. google.com, figma.com..." style="height: 46px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; font-size: 13px; font-weight: 600; color: #0f172a; width: 100%; outline: none; box-sizing: border-box; transition: 0.2s;" onfocus="this.style.borderColor='#dc2626'; this.style.boxShadow='0 0 0 3px rgba(220, 38, 38, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                    </div>
                `,
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-check" style="margin-right: 6px;"></i> Save Bookmark',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#94a3b8',
                customClass: {
                    popup: 'premium-swal-popup',
                    confirmButton: 'btn-premium-add',
                    cancelButton: 'btn-premium-cancel'
                },
                didOpen: () => {
                    const el = document.getElementById('swal-input1');
                    if (el) el.focus();
                },
                preConfirm: () => {
                    const val1 = document.getElementById('swal-input1').value;
                    const val2 = document.getElementById('swal-input2').value;
                    if (!val1.trim() || !val2.trim()) {
                        Swal.showValidationMessage('Please fill in both Bookmark Name and URL');
                        return false;
                    }
                    return [val1, val2];
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    let name = result.value[0].trim();
                    let url = result.value[1].trim();
                    if (!url.startsWith('http://') && !url.startsWith('https://')) {
                        url = 'https://' + url;
                    }
                    let b = localStorage.getItem('empBookmarks_<?php echo $emp_id; ?>');
                    let bookmarks = b ? JSON.parse(b) : [];
                    bookmarks[idx] = {
                        name: name,
                        url: url
                    };
                    localStorage.setItem('empBookmarks_<?php echo $emp_id; ?>', JSON.stringify(bookmarks));
                    initBookmarks();
                }
            });
        };

        window.removeBookmark = function(idx) {
            let b = localStorage.getItem('empBookmarks_<?php echo $emp_id; ?>');
            if (b) {
                let bookmarks = JSON.parse(b);
                bookmarks[idx] = null;
                localStorage.setItem('empBookmarks_<?php echo $emp_id; ?>', JSON.stringify(bookmarks));
                initBookmarks();
            }
        };

        window.clearBookmarks = function() {
            Swal.fire({
                html: `
                    <div style="padding: 10px 5px 5px 5px;">
                        <div style="width: 48px; height: 48px; background: #fef2f2; border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 14px auto;">
                            <i class="fa fa-trash-o" style="color: #dc2626; font-size: 22px;"></i>
                        </div>
                        <h4 style="font-weight: 800; color: #0f172a; font-size: 19px; margin: 0 0 6px 0;">Clear All Bookmarks?</h4>
                        <p style="margin: 0; font-size: 13px; color: #64748b; font-weight: 500; line-height: 1.5;">Are you sure you want to remove all saved links? This action cannot be undone.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fa fa-trash-o" style="margin-right: 6px;"></i> Yes, Clear All',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'premium-swal-popup',
                    confirmButton: 'btn-premium-add',
                    cancelButton: 'btn-premium-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    localStorage.removeItem('empBookmarks_<?php echo $emp_id; ?>');
                    initBookmarks();
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Bookmarks cleared',
                        showConfirmButton: false,
                        timer: 2000
                    });
                }
            });
        };

        initBookmarks();
    });

    function completeTask(taskId) {
        var $row = $('#taskRow_' + taskId);
        var $chk = $row.find('.t-chk');
        var $txt = $row.find('.t-name');
        $chk.addClass('done');
        $txt.addClass('done-txt');

        $.ajax({
            url: 'ajax_toggle_todo.php',
            type: 'POST',
            data: {
                task_id: taskId
            },
            dataType: 'json',
            success: function(r) {
                if (r.success) {
                    if (r.all_progress) {
                        // Use the complete list of today's completed tasks
                        $('#wsTodayProgress').val(r.all_progress);
                    } else if (r.work_details) {
                        var parsed = (typeof window.parseWorkDetails === 'function') ? window.parseWorkDetails(r.work_details) : {
                            progress: r.work_details,
                            planning: '',
                            issues: '',
                            help: ''
                        };
                        $('#wsTodayProgress').val(parsed.progress);
                        $('#wsPlanningTomorrow').val(parsed.planning);
                        $('#wsIssues').val(parsed.issues);
                        $('#wsNeedHelp').val(parsed.help);
                    }
                    $row.slideUp(300, function() {
                        $(this).remove();
                        if ($('.t-row').length === 0) {
                            $('#emptyTasksMsg').fadeIn(300);
                        }
                    });
                } else {
                    alert(r.message || 'Failed to complete task.');
                    $chk.removeClass('done');
                    $txt.removeClass('done-txt');
                }
            },
            error: function() {
                Swal.fire('Notification', 'Network error while completing task.', 'error');
                $chk.removeClass('done');
                $txt.removeClass('done-txt');
            }
        });
    }
</script>

<!-- Check-Out Modal -->
<div class="modal fade" id="checkOutModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:18px;overflow:hidden;border:none;box-shadow:0 25px 60px rgba(0,0,0,.2);">
            <!-- <div class="modal-header" style="background:#1f2937;color:#fff;padding:16px 22px;border:none;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;"><span>&times;</span></button>
                <h4 class="modal-title" style="font-weight:700;font-size:14px;letter-spacing:.04em;">
                    <i class="fa fa-pencil-square-o"></i> Submit Worksheet & Check Out
                </h4>
            </div> -->
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background:#ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                    <div style="width: 36px; height: 36px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-pencil-square-o" style="color: #fff; font-size: 14px;"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Submit Worksheet & Check Out</h5>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            <div class="modal-body" style="padding:22px;">
                <form class="form-horizontal">
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Date</label>
                        <div class="col-md-8"><input type="date" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='var(--p-bg-color)'; this.style.boxShadow='0 4px 10px rgba(166, 166, 167, 0.2)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';" value="<?php echo date('Y-m-d'); ?>" readonly></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Check-in <span class="text-danger">*</span></label>
                        <div class="col-md-8"><input type="time" id="modalCheckInTime" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='var(--p-bg-color)'; this.style.boxShadow='0 4px 10px rgba(166, 166, 167, 0.2)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';" required readonly></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Check-out <span class="text-danger">*</span></label>
                        <div class="col-md-8"><input type="time" id="modalCheckOutTime" class="form-control" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='var(--p-bg-color)'; this.style.boxShadow='0 4px 10px rgba(166, 166, 167, 0.2)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';" required readonly></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Today’s Progress <span class="text-danger">*</span></label>
                        <div class="col-md-8"><textarea id="wsTodayProgress" class="form-control" style="height:85px;border-radius:12px;border:1.5px solid #e2e8f0;resize:none;padding:10px 14px;font-size:13px;outline:none;" placeholder="What did you accomplish today?"><?php echo htmlspecialchars($parsed_remarks['progress']); ?></textarea></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Planning for Tomorrow</label>
                        <div class="col-md-8"><textarea id="wsPlanningTomorrow" class="form-control" style="height:60px;border-radius:12px;border:1.5px solid #e2e8f0;resize:none;padding:10px 14px;font-size:13px;outline:none;" placeholder="What will you work on tomorrow?"><?php echo htmlspecialchars($parsed_remarks['planning']); ?></textarea></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Issues</label>
                        <div class="col-md-8"><textarea id="wsIssues" class="form-control" style="height:60px;border-radius:12px;border:1.5px solid #e2e8f0;resize:none;padding:10px 14px;font-size:13px;outline:none;" placeholder="Any blockers or challenges faced today?"><?php echo htmlspecialchars($parsed_remarks['issues']); ?></textarea></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Need any Help?</label>
                        <div class="col-md-8"><textarea id="wsNeedHelp" class="form-control" style="height:60px;border-radius:12px;border:1.5px solid #e2e8f0;resize:none;padding:10px 14px;font-size:13px;outline:none;" placeholder="Do you need any assistance?"><?php echo htmlspecialchars($parsed_remarks['help']); ?></textarea></div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-4 control-label" style="text-align:left;color:#64748b;font-weight:600;">Work Photos <span class="text-danger">*</span></label>
                        <div class="col-md-8">
                            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                <?php
                                $prefill_photos = (!empty($today_record['work_photos'])) ? json_decode($today_record['work_photos'], true) : [];
                                if (!is_array($prefill_photos)) $prefill_photos = [];
                                for ($id = 1; $id <= 4; $id++):
                                    $photo_src = '';
                                    $has_prefill = isset($prefill_photos[$id - 1]) && !empty($prefill_photos[$id - 1]);
                                    if ($has_prefill) {
                                        $p_url = $prefill_photos[$id - 1];
                                        $photo_src = (strpos($p_url, 'http') === 0 || strpos($p_url, '/') === 0) ? $p_url : '../admin_area/' . $p_url;
                                    }
                                ?>
                                    <div id="box_<?php echo $id; ?>" onclick="document.getElementById('work_photo_<?php echo $id; ?>').click()"
                                        style="width:70px;height:70px;border:2px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;background:#f8fafc;">
                                        <i class="fa fa-plus" style="color:#94a3b8;font-size:18px; <?php echo $has_prefill ? 'display:none;' : ''; ?>"></i>
                                        <input type="file" id="work_photo_<?php echo $id; ?>" style="display:none;" accept="image/*" onchange="previewWorkPhoto(this,<?php echo $id; ?>)">
                                        <img id="preview_<?php echo $id; ?>" src="<?php echo htmlspecialchars($photo_src); ?>" style="<?php echo $has_prefill ? 'display:block;' : 'display:none;'; ?>width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:22px;margin-bottom:0;">
                        <div class="col-md-12" style="display:flex;gap:10px;justify-content:flex-end;">
                            <button type="button" id="btnCancelCheckOut" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                            <button type="button" id="confirmCheckOut" class="btn-premium-add">
                                <i class="fa fa-paper-plane"></i> Submit &amp; Check Out
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>