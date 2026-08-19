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
                if ("Notification" in window) {
                    if (Notification.permission !== "granted") {
                        Notification.requestPermission();
                    }
                }

                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('../admin_area/sw.js').then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }).catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
                }

                /* check announcement every 1.5 seconds */
                setInterval(checkAnnouncement, 1500);
                setInterval(fetchLiveNotifications, 1500);
                fetchLiveNotifications();
            });

            /* notification popup */
            function showNotification(title, message) {
                var audio = new Audio('https://commondatastorage.googleapis.com/codeskulptor-assets/week7-bounce.m4a');
                audio.play().catch(function(error) {
                    console.log("Audio play failed:", error);
                });

                if (Notification.permission === "granted") {
                    navigator.serviceWorker.ready.then(function(registration) {
                        registration.showNotification(title, {
                            body: message,
                            icon: "https://cdn-icons-png.flaticon.com/512/1827/1827392.png",
                            requireInteraction: true
                        });
                    }).catch(function() {
                        var notification = new Notification(title, {
                            body: message,
                            icon: "https://cdn-icons-png.flaticon.com/512/1827/1827392.png",
                            requireInteraction: true
                        });
                        notification.onclick = function() {
                            window.focus();
                            this.close();
                        };
                    });
                }

                var toast = document.createElement('div');
                toast.style.position = 'fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.backgroundColor = '#4caf50';
                toast.style.color = '#fff';
                toast.style.padding = '15px 20px';
                toast.style.borderRadius = '5px';
                toast.style.zIndex = '99999';
                toast.style.boxShadow = '0 4px 6px rgba(0,0,0,0.3)';
                toast.style.minWidth = '250px';
                toast.style.fontFamily = 'Arial, sans-serif';

                toast.innerHTML = '<strong style="font-size:16px;">🔔 ' + title + '</strong><br><span style="font-size:14px;">' + message + '</span>';

                document.body.appendChild(toast);

                setTimeout(function() {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.5s ease-in-out';
                    setTimeout(function() {
                        toast.remove();
                    }, 500);
                }, 7000);
            }

            let _lastEmpUnreadCount = 0;
            let _lastEmpSeenNotifId = 0;

            function fetchLiveNotifications() {
                const endpoint = '../admin_area/ajax/notifications/ajax_get_user_notifications.php?portal=employee';
                const markReadEndpoint = '../admin_area/ajax/notifications/ajax_mark_notification_read.php?portal=employee';

                $.ajax({
                    url: endpoint,
                    method: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res || !res.success) return;

                        const unread = res.unread_count || 0;

                        if (unread > 0) {
                            $('.emp-sys-notif-badge').text(unread).show();
                        } else {
                            $('.emp-sys-notif-badge').hide();
                        }

                        if (res.notifications && res.notifications.length > 0) {
                            const latest = res.notifications[0];
                            const latestId = parseInt(latest.id);
                            if (_lastEmpSeenNotifId !== 0 && latestId > _lastEmpSeenNotifId && parseInt(latest.is_read) === 0) {
                                playNotificationChime();
                                showFloatingToastNotification(latest.title, latest.message, latest.url, latest.id);
                            }
                            _lastEmpSeenNotifId = latestId;
                        }
                        _lastEmpUnreadCount = unread;

                        const list = $('.emp-sys-notif-list');
                        list.empty();

                        if (!res.notifications || res.notifications.length === 0) {
                            list.append('<li style="padding:15px; text-align:center; color:#94a3b8; font-size:13px;">No new notifications</li>');
                            return;
                        }

                        list.append(`
                            <li style="padding: 10px 15px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 12px; font-weight: 700; color: #0f172a;">
                                <span>Notifications</span>
                                <a href="#" onclick="markAllNotificationsRead(event, '${markReadEndpoint}')" style="color: #dd2127; text-decoration: none; font-size: 11px;">Mark all read</a>
                            </li>
                        `);

                        res.notifications.forEach(n => {
                            const isUnread = parseInt(n.is_read) === 0;
                            const bg = isUnread ? '#fff5f5' : '#ffffff';
                            const targetUrl = n.url || '#';

                            let icon = 'fa-info-circle';
                            if (n.type === 'task_assigned') icon = 'fa-tasks';
                            else if (n.type === 'comment_added') icon = 'fa-commenting';
                            else if (n.type === 'project_assigned') icon = 'fa-briefcase';

                            list.append(`
                                <li style="background:${bg}; border-bottom:1px solid #f1f5f9; transition:0.15s;">
                                    <a href="${targetUrl}" onclick="handleNotifClick(event, ${n.id}, '${targetUrl}', '${markReadEndpoint}')" style="display:flex; gap:10px; padding:10px 14px; text-decoration:none; color:#334155; font-size:12.5px;">
                                        <div style="width:28px; height:28px; border-radius:50%; background:#ffeaeb; color:#dd2127; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px;">
                                            <i class="fa ${icon}"></i>
                                        </div>
                                        <div style="flex:1; min-width:0;">
                                            <div style="font-weight:700; color:#0f172a; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlNotif(n.title)}</div>
                                            <div style="font-size:11.5px; color:#64748b; margin-top:2px; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${escapeHtmlNotif(n.message)}</div>
                                            <small style="color:#94a3b8; font-size:10px; margin-top:4px; display:block;">${n.time_ago}</small>
                                        </div>
                                    </a>
                                </li>
                            `);
                        });
                    }
                });
            }

            function handleNotifClick(e, id, url, markEndpoint) {
                if (e) e.preventDefault();

                const $badge = $('.sys-notif-badge, .emp-sys-notif-badge');
                let count = parseInt($badge.text()) || 0;
                if (count > 0) {
                    count--;
                    if (count > 0) $badge.text(count);
                    else $badge.hide().text('0');
                }

                $.ajax({
                    url: markEndpoint,
                    type: 'POST',
                    data: {
                        id: id
                    },
                    dataType: 'json'
                }).always(function() {
                    fetchLiveNotifications();
                    if (url && url !== '#' && url !== 'javascript:void(0);') {
                        var matchTask = url.match(/open_task_id=(\d+)/);
                        var matchEmp = url.match(/emp_id=(\d+)/);

                        var targetTaskId = matchTask ? parseInt(matchTask[1]) : 0;
                        var targetEmpId = matchEmp ? parseInt(matchEmp[1]) : 0;

                        if (targetTaskId > 0 && typeof openTaskDetail === 'function' && $('#taskDetailOverlay').length > 0) {
                            openTaskDetail(targetTaskId, targetEmpId);
                        } else {
                            window.location.href = url;
                        }
                    }
                });
            }

            function markAllNotificationsRead(e, markEndpoint) {
                if (e) e.preventDefault();
                $('.sys-notif-badge, .emp-sys-notif-badge').hide().text('0');
                $.ajax({
                    url: markEndpoint,
                    type: 'POST',
                    data: {
                        mark_all: 'true'
                    },
                    dataType: 'json'
                }).always(function() {
                    fetchLiveNotifications();
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

            function showFloatingToastNotification(title, message, url, notifId) {
                const existing = document.getElementById('sys-floating-toast');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.id = 'sys-floating-toast';
                toast.style.cssText = `
                    position: fixed;
                    top: 24px;
                    right: 24px;
                    z-index: 999999;
                    background: #ffffff;
                    border-left: 4px solid #dd2127;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.18), 0 4px 12px rgba(221, 33, 39, 0.12);
                    border-radius: 12px;
                    padding: 14px 18px;
                    width: 320px;
                    max-width: calc(100vw - 32px);
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    display: flex;
                    gap: 12px;
                    align-items: flex-start;
                    cursor: pointer;
                    transition: transform 0.25s ease, opacity 0.25s ease;
                `;

                toast.innerHTML = `
                    <div style="width:34px; height:34px; border-radius:50%; background:#ffeaeb; color:#dd2127; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:15px; margin-top:2px;">
                        <i class="fa fa-bell"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; color:#0f172a; font-size:13.5px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlNotif(title)}</div>
                        <div style="font-size:12px; color:#475569; margin-top:3px; line-height:1.35; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${escapeHtmlNotif(message)}</div>
                        <div style="font-size:11px; color:#dd2127; font-weight:700; margin-top:6px; display:inline-flex; align-items:center; gap:4px;">
                            View Details <i class="fa fa-arrow-right" style="font-size:9px;"></i>
                        </div>
                    </div>
                    <button type="button" onclick="event.stopPropagation(); document.getElementById('sys-floating-toast')?.remove();" style="background:none; border:none; color:#94a3b8; font-size:16px; cursor:pointer; padding:0 2px; margin-left:4px; line-height:1;" title="Dismiss">&times;</button>
                `;

                toast.onclick = function(e) {
                    toast.remove();
                    const isEmp = (window.location.pathname.indexOf('emp_area') !== -1);
                    const markEndpoint = isEmp ? '../admin_area/ajax/notifications/ajax_mark_notification_read.php?portal=employee' : 'ajax/notifications/ajax_mark_notification_read.php?portal=admin';
                    handleNotifClick(e, notifId, url, markEndpoint);
                };

                document.body.appendChild(toast);

                setTimeout(function() {
                    if (toast && toast.parentNode) {
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateY(-10px)';
                        setTimeout(function() {
                            if (toast && toast.parentNode) toast.remove();
                        }, 300);
                    }
                }, 7000);
            }

            function escapeHtmlNotif(str) {
                if (!str) return '';
                return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            }

            /* ajax check announcements */
            function checkAnnouncement() {
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
        </script>
    </body>

    </html>
<?php } ?>