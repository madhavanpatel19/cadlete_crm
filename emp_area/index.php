<?php
// =============================================================
// emp_area/index.php
// Employee portal main entry point.
// Moved from: admin_area/pages/employees/emp_index.php
// Paths updated to be relative from emp_area root.
// =============================================================
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
} else {
    $emp_id = $_SESSION['emp_id'];
    $emp_name = $_SESSION['emp_name'];
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title> Cadlete- Employee Dashboard</title>
        <link href="../admin_area/css/bootstrap.min.css" rel="stylesheet">
        <link href="../admin_area/css/style.css" rel="stylesheet">
        <link href="../admin_area/css/dashboard.css" rel="stylesheet">
        <link href="../admin_area/css/sidebar.css" rel="stylesheet">
        <link href="../admin_area/font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link rel="shortcut icon" href="../admin_area/images/Cadlete_Black_logo_favicon.png?v=<?php echo time(); ?>" type="image/png">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="../admin_area/js/jquery.min.js"></script>
        <script src="../admin_area/js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <style>
            /* Smooth transitions for dashboard panels */
            .panel {
                transition: all 0.3s ease;
            }

            .panel:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }

            .flatpickr-calendar {
                font-family: inherit;
                border-radius: 12px;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
            }

            .flatpickr-day.selected,
            .flatpickr-day.startRange,
            .flatpickr-day.endRange {
                background: #dd2127 !important;
                border-color: #dd2127 !important;
            }
        </style>
    </head>

    <body>
        <div id="wrapper">
            <?php include("includes/emp_sidebar.php"); ?>
            <div id="page-wrapper">
                <!-- Modern Topbar -->
                <div class="modern-topbar">
                    <div class="topbar-left">
                        <button type="button" class="menu-toggle" onclick="document.querySelector('.modern-sidebar').classList.toggle('show')">
                            <i class="fa fa-bars"></i>
                        </button>
                        <?php
                        $page_title = "Dashboard";
                        if (!empty($_GET)) {
                            $keys = array_keys($_GET);
                            $first_key = $keys[0];
                            $title_map = [
                                'dashboard' => '<i class="fa fa-dashboard"></i> Dashboard',
                                'projects' => '<i class="fa fa-sitemap"></i> Projects',
                                'leads' => '<i class="fa fa-bullseye"></i> Leads',
                                'view_lead' => '<i class="fa fa-bullseye"></i> Lead Details',
                                'worksheet' => '<i class="fa fa-file-text-o"></i> Worksheet',
                                'emp_profile' => '<i class="fa fa-user"></i> My Profile',
                                'leave_application' => '<i class="fa fa-paper-plane-o"></i> Leave Application',
                                'emp_salary_slip' => '<i class="fa fa-money"></i> Salary Slip',
                                'view_announcement' => '<i class="fa fa-bullhorn"></i> Announcements',
                                'announcements' => '<i class="fa fa-bullhorn"></i> Announcements'
                            ];
                            if (array_key_exists($first_key, $title_map)) {
                                $page_title = $title_map[$first_key];
                            } else {
                                $page_title = htmlspecialchars(ucwords(str_replace('_', ' ', $first_key)));
                            }
                        }
                        ?>
                        <h2 class="topbar-title"><?php echo $page_title; ?></h2>
                    </div>
                    <!-- <div class="topbar-search">
                        <i class="fa fa-search"></i>
                        <input type="text" placeholder="Search projects, employees...">
                    </div> -->
                    <div class="topbar-right">
                        <div class="notification-bell dropdown" id="emp-system-notif-dropdown">
                            <div data-toggle="dropdown" style="cursor: pointer; position: relative;" onclick="fetchLiveNotifications()">
                                <i class="fa fa-bell-o"></i>
                                <span class="notification-badge emp-sys-notif-badge" style="display: none;">0</span>
                            </div>
                            <ul class="dropdown-menu emp-sys-notif-list" style="right: -10px; left: auto; top: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-light); margin-top: 15px; min-width: 320px; max-height: 400px; overflow-y: auto; padding: 0;">
                                <li style="padding: 15px; text-align: center; color: #94a3b8;"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                            </ul>
                        </div>
                        <?php
                        // Fetch employee image and designation for header
                        $header_emp_img = '';
                        $emp_designation = 'Employee';
                        $get_header_img = "SELECT employee_image, designation FROM emp_list WHERE id = '$emp_id'";
                        $run_header_img = mysqli_query($con, $get_header_img);
                        if ($run_header_img) {
                            $header_row = mysqli_fetch_array($run_header_img);
                            $header_emp_img = $header_row['employee_image'] ?? '';
                            if (!empty($header_row['designation'])) {
                                $emp_designation = $header_row['designation'];
                            }
                        }
                        ?>
                        <div class="topbar-profile dropdown">
                            <div data-toggle="dropdown" style="display:flex; align-items:center; gap:12px; padding:6px;background: white; border-radius:11px; cursor:pointer;">
                                <div class="profile-avatar">
                                    <img src="<?php echo !empty($header_emp_img) ? '../admin_area/uploads/' . $header_emp_img : 'https://ui-avatars.com/api/?name=' . urlencode($emp_name) . '&background=dd2127&color=fff'; ?>" alt="Employee Avatar">
                                </div>
                                <div class="profile-info">
                                    <span class="profile-name"><?php echo htmlspecialchars($emp_name); ?></span>
                                    <span class="profile-role"><?php echo htmlspecialchars($emp_designation); ?></span>
                                </div>
                                <i class="fa fa-angle-down"></i>
                            </div>
                            <ul class="dropdown-menu" style="right: 0; left: auto; top: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-light); margin-top: 10px;">
                                <li>
                                    <a href="index.php?emp_profile" style="padding: 10px 20px; color: var(--text-main); font-weight: 500;">
                                        <i class="fa fa-fw fa-user"></i> Profile
                                    </a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <a href="pages/auth/logout.php" style="padding: 10px 20px; color: var(--red); font-weight: 500;">
                                        <i class="fa fa-fw fa-power-off"></i> Log Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="container-fluid">
                    <?php
                    if (isset($_GET['dashboard'])) {
                        include("pages/dashboard/emp_dashboard.php");
                    } elseif (isset($_GET['projects'])) {
                        include("pages/projects/projects.php");
                    } elseif (isset($_GET['leads'])) {
                        include("pages/leads/leads.php");
                    } elseif (isset($_GET['view_lead'])) {
                        $_GET['open_lead'] = $_GET['view_lead'];
                        include("pages/leads/leads.php");
                    } elseif (isset($_GET['team_todo'])) {
                        include("../admin_area/pages/projects/team_todo.php");
                    } elseif (isset($_GET['worksheet'])) {
                        $_GET['partial'] = true;
                        include("pages/worksheets/worksheet.php");
                    } elseif (isset($_GET['todo'])) {
                        $_GET['partial'] = true;
                        include("pages/todo/todo.php");
                    } elseif (isset($_GET['quick_links'])) {
                        $_GET['partial'] = true;
                        include("pages/quick_links/quick_links.php");
                    } elseif (isset($_GET['emp_profile'])) {
                        include("pages/profile/emp_profile.php");
                    } elseif (isset($_GET['leave_application'])) {
                        $_GET['partial'] = true;
                        include("pages/leaves/leave_application.php");
                    } elseif (isset($_GET['emp_salary_slip'])) {
                        $_GET['partial'] = true;
                        include("pages/salary/emp_salary_slip.php");
                    } elseif (isset($_GET['view_announcement'])) {
                        include("pages/announcements/view_announcement.php");
                    } elseif (isset($_GET['announcements'])) {
                        include("pages/announcements/announcements.php");
                    } else {
                        include("pages/dashboard/emp_dashboard.php");
                    }
                    ?>
                </div>
            </div>
        </div>
        <script>
            /* Ask notification permission & register service worker */
            document.addEventListener("DOMContentLoaded", function() {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('sw.js?v=4').then(function(registration) {
                        try {
                            registration.update();
                        } catch (e) {}
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }).catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });

                    navigator.serviceWorker.addEventListener('message', function(event) {
                        if (event.data && event.data.action === 'crm_notification_navigate' && event.data.url) {
                            window.location.href = event.data.url;
                        }
                    });
                }

                /* Smart notification polling — 30s interval, pauses when tab hidden */
                startEmpNotificationPolling();
            });

            /* notification popup */
            function showNotification(title, message) {
                showBrowserDesktopNotification(title, message, '', 0);
            }

            /* ── Employee Notification system ──────────────────────────────────────────
             *  • Background polling (45s) ensures Windows/OS desktop notifications
             *    pop up even when user is working on other windows or apps.
             *  • Throttled to prevent multiple rapid duplicate API calls.
             *  • On click: updates DOM immediately without redundant fetch cascades.
             * ─────────────────────────────────────────────────────────────── */
            const EMP_NOTIF_POLL_MS = 45000; // 45s interval for background desktop alerts
            let _lastEmpUnreadCount = 0;
            let _lastEmpSeenNotifId = 0;
            let _empNotifPolling = false;
            let _lastEmpFetchTime = 0;
            let _empNotifPollTimer = null;

            // Persist alerted IDs in localStorage so page reloads don't re-trigger old notifications
            function _loadEmpAlertedIds() {
                try {
                    var raw = localStorage.getItem('crm_emp_alerted_notif_ids');
                    return raw ? new Set(JSON.parse(raw)) : new Set();
                } catch(e) { return new Set(); }
            }
            function _saveEmpAlertedIds(s) {
                try {
                    var arr = Array.from(s);
                    if (arr.length > 200) arr = arr.slice(arr.length - 200);
                    localStorage.setItem('crm_emp_alerted_notif_ids', JSON.stringify(arr));
                } catch(e) {}
            }
            let _empAlertedNotifIds = _loadEmpAlertedIds();

            function requestBrowserNotificationPermission() {
                if ("Notification" in window && Notification.permission === "default") {
                    Notification.requestPermission().catch(function() {});
                }
            }

            function showBrowserDesktopNotification(title, message, targetUrl, notifId) {
                if (!("Notification" in window)) return;

                let resolvedUrl = window.location.href;
                if (targetUrl && targetUrl !== '#' && targetUrl !== 'javascript:void(0);') {
                    try {
                        if (targetUrl.startsWith('http://') || targetUrl.startsWith('https://')) {
                            resolvedUrl = targetUrl;
                        } else {
                            var loc = window.location;
                            var dir = loc.pathname.substring(0, loc.pathname.lastIndexOf('/') + 1);
                            var base = loc.protocol + '//' + loc.host + dir;
                            resolvedUrl = new URL(targetUrl, base).href;
                        }
                    } catch (err) {
                        resolvedUrl = window.location.href;
                    }
                }

                if (Notification.permission === "default") {
                    Notification.requestPermission().then(function(perm) {
                        if (perm === "granted") {
                            triggerNativeNotification(title, message, resolvedUrl, notifId);
                        }
                    });
                    return;
                }

                if (Notification.permission === "granted") {
                    triggerNativeNotification(title, message, resolvedUrl, notifId);
                }
            }

            function triggerNativeNotification(title, message, resolvedUrl, notifId) {
                // Use new Notification() directly — avoids SW double-fire
                try {
                    const notif = new Notification(title, {
                        body: message || '',
                        icon: 'https://cdn-icons-png.flaticon.com/512/1827/1827392.png',
                        tag: 'crm-emp-notif-' + notifId,
                        requireInteraction: false
                    });
                    notif.onclick = function(event) {
                        event.preventDefault();
                        window.focus();
                        if (resolvedUrl && resolvedUrl !== '#' && resolvedUrl !== 'javascript:void(0);') {
                            window.location.href = resolvedUrl;
                        }
                        notif.close();
                    };
                } catch (e) {
                    console.warn('Notification failed:', e);
                }
            }

            function testDesktopNotification(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                if (!("Notification" in window)) {
                    Swal.fire("Not Supported", "Your browser does not support desktop notifications.", "warning");
                    return;
                }

                if (Notification.permission === "granted") {
                    showBrowserDesktopNotification("🔔 CRM Desktop Alert", "Your device is ready to receive instant task & project alerts!", "index.php", Date.now());
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Test alert sent to your PC!',
                        showConfirmButton: false,
                        timer: 3000
                    });
                } else if (Notification.permission === "denied") {
                    Swal.fire({
                        title: "Notifications Blocked",
                        html: "Notifications are blocked in your browser.<br><br>Click the <b>lock icon / site settings</b> in your browser address bar and set Notifications to <b>Allow</b>.",
                        icon: "error"
                    });
                } else {
                    Notification.requestPermission().then(function(permission) {
                        if (permission === "granted") {
                            showBrowserDesktopNotification("✅ Desktop Alerts Active!", "You will now receive instant desktop notifications on your PC.", "index.php", Date.now());
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Desktop alerts enabled!',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        } else {
                            Swal.fire("Permission Required", "Please allow notifications to receive desktop alerts.", "warning");
                        }
                    });
                }
            }

            function startEmpNotificationPolling() {
                requestBrowserNotificationPermission();
                document.addEventListener('click', function _reqPermEmp() {
                    requestBrowserNotificationPermission();
                }, {
                    once: true
                });

                fetchLiveNotifications(true); // Initial fetch on page load

                // Background polling so desktop notifications pop up on Windows/devices even on other windows
                if (_empNotifPollTimer) clearInterval(_empNotifPollTimer);
                _empNotifPollTimer = setInterval(function() {
                    fetchLiveNotifications(false);
                }, EMP_NOTIF_POLL_MS);
            }

            function scheduleNextEmpNotifPoll() {
                // Legacy — no longer used; kept for backward compatibility
            }

            function fetchLiveNotifications(force) {
                const now = Date.now();
                if (!force && (now - _lastEmpFetchTime < 3000)) {
                    return; // Throttle multiple rapid clicks within 3s
                }
                if (_empNotifPolling) return;
                _empNotifPolling = true;
                _lastEmpFetchTime = now;

                const endpoint = '../admin_area/ajax/notifications/ajax_get_user_notifications.php?portal=employee&last_id=' + _lastEmpSeenNotifId;
                const markReadEndpoint = '../admin_area/ajax/notifications/ajax_mark_notification_read.php?portal=employee';

                $.ajax({
                    url: endpoint,
                    method: 'GET',
                    dataType: 'json',
                    timeout: 15000,
                    success: function(res) {
                        if (!res || !res.success) return;

                        const unread = res.unread_count || 0;
                        if (unread > 0) {
                            $('.emp-sys-notif-badge').text(unread).show();
                        } else {
                            $('.emp-sys-notif-badge').hide();
                        }

                        // Trigger chime and native Windows desktop notification (single popup per poll cycle)
                        if (res.notifications && res.notifications.length > 0) {
                            let hasNewAlert = false;
                            let latestNewNotif = null;

                            res.notifications.forEach(function(n) {
                                const notifId = parseInt(n.id);
                                const isUnread = parseInt(n.is_read) === 0;
                                if (isUnread && !_empAlertedNotifIds.has(notifId)) {
                                    _empAlertedNotifIds.add(notifId);
                                    hasNewAlert = true;
                                    if (!latestNewNotif) {
                                        latestNewNotif = n;
                                    }
                                }
                            });
                            _saveEmpAlertedIds(_empAlertedNotifIds);

                            if (hasNewAlert && latestNewNotif) {
                                showBrowserDesktopNotification(latestNewNotif.title, latestNewNotif.message, latestNewNotif.url, latestNewNotif.id);
                                playNotificationChime();
                            }
                        }

                        // Nothing new — skip DOM re-render
                        if (res.no_change) return;

                        if (res.server_max_id) {
                            _lastEmpSeenNotifId = Math.max(_lastEmpSeenNotifId, parseInt(res.server_max_id));
                        }
                        _lastEmpUnreadCount = unread;

                        const list = $('.emp-sys-notif-list');
                        list.empty();

                        if (!res.notifications || res.notifications.length === 0) {
                            list.append(`
                                <li style="padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 700; color: #0f172a;">
                                    <span>Notifications</span>
                                </li>
                                <li style="padding:20px; text-align:center; color:#94a3b8; font-size:13px;">No new notifications</li>
                            `);
                            return;
                        }

                        list.append(`
                            <li style="padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 700; color: #0f172a;">
                                <span>Notifications</span>
                                <div>
                                    <a href="#" onclick="markAllEmpNotificationsRead(event, '${markReadEndpoint}')" style="color: #dd2127; text-decoration: none; font-size: 11px;">Mark all read</a>
                                </div>
                            </li>
                        `);

                        res.notifications.forEach(n => {
                            const isUnread = parseInt(n.is_read) === 0;
                            const bg = isUnread ? '#fff5f5' : '#ffffff';
                            const targetUrl = n.url || '#';

                            let icon = 'fa-info-circle';
                            if (n.type === 'task_assigned') icon = 'fa-tasks';
                            else if (n.type === 'task_completed') icon = 'fa-check-circle';
                            else if (n.type === 'comment_added') icon = 'fa-commenting';
                            else if (n.type === 'project_assigned') icon = 'fa-briefcase';
                            else if (n.type === 'lead_assigned') icon = 'fa-user-plus';
                            else if (n.type === 'birthday_today' || n.type === 'birthday_tomorrow') icon = 'fa-birthday-cake';
                            else if (n.type === 'leave_request' || n.type === 'leave_approved' || n.type === 'leave_rejected') icon = 'fa-calendar-check-o';

                            list.append(`
                                <li style="background:${bg}; border-bottom:1px solid #f1f5f9; transition:0.15s;">
                                    <a href="${targetUrl}" onclick="handleEmpNotifClick(event, ${n.id}, '${targetUrl}', '${markReadEndpoint}')" style="display:flex; gap:10px; padding:10px 14px; text-decoration:none; color:#334155; font-size:12.5px;">
                                        <div style="width:28px; height:28px; border-radius:50%; background:#ffeaeb; color:#dd2127; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px;">
                                            <i class="fa ${icon}"></i>
                                        </div>
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-weight:700; color:#0f172a; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeEmpHtmlNotif(n.title)}</div>
                                            <div style="font-size:11.5px; color:#64748b; margin-top:2px; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${escapeEmpHtmlNotif(n.message)}</div>
                                            <small style="color:#94a3b8; font-size:10px; margin-top:4px; display:block;">${n.time_ago}</small>
                                        </div>
                                    </a>
                                </li>
                            `);
                        });
                    },
                    error: function() {},
                    complete: function() {
                        _empNotifPolling = false;
                    }
                });
            }


            /* Employee-specific notification click handler */
            function handleEmpNotifClick(e, id, url, markEndpoint) {
                if (e) e.preventDefault();
                const $badge = $('.emp-sys-notif-badge');
                let count = parseInt($badge.text()) || 0;
                if (count > 0) {
                    count--;
                    if (count > 0) $badge.text(count);
                    else $badge.hide().text('0');
                }

                // Mark clicked item visually as read in DOM immediately
                if (e && e.currentTarget) {
                    $(e.currentTarget).closest('li').css('background', '#ffffff');
                }

                // Asynchronously mark as read on server (no extra cascading fetch)
                $.ajax({
                    url: markEndpoint,
                    type: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json'
                });

                if (url && url !== '#' && url !== 'javascript:void(0);') {
                    // Check if clicking a task notification and already on the Todo page with modal available
                    var matchTask = url.match(/open_task_id=(\d+)/);
                    var matchEmp = url.match(/emp_id=(\d+)/);
                    var targetTaskId = matchTask ? parseInt(matchTask[1]) : 0;
                    var targetEmpId = matchEmp ? parseInt(matchEmp[1]) : 0;

                    if (targetTaskId > 0 && typeof openTaskDetail === 'function' && $('#taskDetailOverlay').length > 0) {
                        openTaskDetail(targetTaskId, targetEmpId);
                    } else {
                        // Normalize URL to employee To-do page
                        if (url.includes('team_todo') || url.includes('global_team_todos')) {
                            url = url.replace('global_team_todos', 'todo').replace('team_todo', 'todo');
                        }
                        window.location.href = url;
                    }
                }
            }

            function markAllEmpNotificationsRead(e, markEndpoint) {
                if (e) e.preventDefault();
                $('.emp-sys-notif-badge').hide().text('0');
                $('.emp-sys-notif-list li').css('background', '#ffffff');

                $.ajax({
                    url: markEndpoint,
                    type: 'POST',
                    data: {
                        mark_all: 'true'
                    },
                    dataType: 'json'
                });
            }

            function playNotificationChime() {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    const ctx = new AudioCtx();

                    const osc1 = ctx.createOscillator();
                    const gain1 = ctx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.value = 659.25;
                    osc1.connect(gain1);
                    gain1.connect(ctx.destination);
                    gain1.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
                    osc1.start(ctx.currentTime);
                    osc1.stop(ctx.currentTime + 0.3);

                    const osc2 = ctx.createOscillator();
                    const gain2 = ctx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.value = 880.00;
                    osc2.connect(gain2);
                    gain2.connect(ctx.destination);
                    gain2.gain.setValueAtTime(0.4, ctx.currentTime + 0.12);
                    gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.55);
                    osc2.start(ctx.currentTime + 0.12);
                    osc2.stop(ctx.currentTime + 0.55);
                } catch (e) {}
            }



            function escapeEmpHtmlNotif(str) {
                if (!str) return '';
                return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
            }
            /* alias used by toast */
            function escapeHtmlNotif(str) {
                return escapeEmpHtmlNotif(str);
            }

            /* ajax check announcements */
            function checkAnnouncement() {
                // Notification check disabled
                return;
                fetch("pages/announcements/check_announcement.php")
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "new") {
                            showNotification("New Announcement", data.message || data.title);
                        }
                    })
                    .catch(error => console.log('Error checking announcements:', error));
            }

            // Automatically convert all input[type="date"] & input[type="datetime-local"] to display dd-mm-yyyy format
            function initGlobalFlatpickr() {
                if (typeof flatpickr !== 'function') return;
                $('input[type="date"], input[type="datetime-local"]').each(function() {
                    if (this._flatpickr || $(this).hasClass('flatpickr-input')) return;
                    var $input = $(this);
                    var isReadonly = $input.is('[readonly]');
                    var rawVal = $input.attr('value') || $input.val();
                    var isDateTime = $input.attr('type') === 'datetime-local';

                    // Parse initial value to Y-m-d format if present
                    var defaultDateVal = null;
                    if (rawVal && rawVal.trim() !== '') {
                        var d = new Date(rawVal);
                        if (!isNaN(d.getTime())) {
                            defaultDateVal = d;
                        }
                    }

                    flatpickr(this, {
                        enableTime: isDateTime,
                        dateFormat: isDateTime ? 'Y-m-d H:i' : 'Y-m-d',
                        altInput: true,
                        altFormat: isDateTime ? 'd-m-Y h:i K' : 'd-m-Y',
                        allowInput: !isReadonly,
                        clickOpens: !isReadonly,
                        defaultDate: defaultDateVal,
                        onChange: function(selectedDates, dateStr, instance) {
                            $(instance.element).val(dateStr).trigger('change');
                        },
                        onReady: function(selectedDates, dateStr, instance) {
                            if (instance.altInput) {
                                instance.altInput.placeholder = $input.attr('placeholder') || (isDateTime ? "dd-mm-yyyy --:-- --" : "dd-mm-yyyy");
                                if (isReadonly) {
                                    instance.altInput.readOnly = true;
                                }
                                var origStyle = $input.attr('style');
                                if (origStyle) {
                                    $(instance.altInput).attr('style', origStyle);
                                }
                                var origClass = $input.attr('class');
                                if (origClass) {
                                    $(instance.altInput).addClass(origClass);
                                }
                            }
                        }
                    });
                });
            }

            $(document).ready(function() {
                initGlobalFlatpickr();
                setTimeout(initGlobalFlatpickr, 300);
                setTimeout(initGlobalFlatpickr, 1000);
            });

            $(document).ajaxComplete(function() {
                setTimeout(initGlobalFlatpickr, 100);
            });

            window.togglePasswordVisibility = function(inputId, eyeId) {
                var input = document.getElementById(inputId);
                var eye = document.getElementById(eyeId);
                if (input) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        if (eye) {
                            eye.className = 'fa fa-eye-slash';
                        }
                    } else {
                        input.type = 'password';
                        if (eye) {
                            eye.className = 'fa fa-eye';
                        }
                    }
                }
            };
        </script>
    </body>

    </html>
<?php } ?>