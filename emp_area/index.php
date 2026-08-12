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
                        <div class="notification-bell dropdown">
                            <div data-toggle="dropdown" style="cursor: pointer; position: relative;">
                                <i class="fa fa-bell-o"></i>
                                <?php
                                $get_unread_count = "SELECT COUNT(*) AS total FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) AND id NOT IN (SELECT announcement_id FROM announcement_read WHERE emp_id='$emp_id')";
                                $run_unread_count = mysqli_query($con, $get_unread_count);
                                $unread_count = 0;
                                if ($run_unread_count) {
                                    $row_unread_count = mysqli_fetch_array($run_unread_count);
                                    $unread_count = $row_unread_count['total'];
                                }
                                ?>
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </div>
                            <ul class="dropdown-menu" style="right: -10px; left: auto; top: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-light); margin-top: 15px; min-width: 250px;">
                                <?php
                                $get_recent_announcements = "SELECT * FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY publish_date DESC LIMIT 5";
                                $run_recent = mysqli_query($con, $get_recent_announcements);
                                if ($run_recent && mysqli_num_rows($run_recent) > 0) {
                                    while ($row_recent = mysqli_fetch_array($run_recent)) {
                                        $ann_id = $row_recent['id'];
                                        $ann_title = $row_recent['title'];
                                        $ann_date = date('M d, H:i', strtotime(!empty($row_recent['publish_date']) ? $row_recent['publish_date'] : $row_recent['created_at']));
                                        $check_read = "SELECT * FROM announcement_read WHERE announcement_id='$ann_id' AND emp_id='$emp_id'";
                                        $run_check = mysqli_query($con, $check_read);
                                        $is_unread = mysqli_num_rows($run_check) == 0;
                                        $bg_style = $is_unread ? "background-color: #f5f3ff;" : "";
                                ?>
                                        <li style="<?php echo $bg_style; ?> border-bottom: 1px solid var(--border-light);">
                                            <a href="index.php?view_announcement=<?php echo $ann_id; ?>" style="white-space: normal; padding: 12px 20px; display: block; color: var(--text-main); text-decoration: none;">
                                                <strong style="color: var(--text-main); font-size: 14px;"><?php echo htmlspecialchars($ann_title); ?></strong><br>
                                                <small style="color: var(--text-muted); font-size: 12px;"><?php echo $ann_date; ?></small>
                                                <?php if ($is_unread) : ?>
                                                    <span class="label label-danger pull-right" style="background-color: #ef4444; margin-top: 2px;">New</span>
                                                <?php endif; ?>
                                            </a>
                                        </li>
                                <?php
                                    }
                                } else {
                                    echo "<li><a href='#' style='padding: 15px 20px; color: var(--text-muted); text-align: center; display: block; pointer-events: none;'>No announcements found.</a></li>";
                                }
                                ?>
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

                /* check announcement every 5 seconds */
                setInterval(checkAnnouncement, 5000);
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