<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
session_start();
include("includes/db.php");
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
} else {
?>
    <?php
    $admin_session = $_SESSION['admin_email'];
    $get_admin = "select * from admins  where admin_email='$admin_session'";
    $run_admin = mysqli_query($con, $get_admin);
    $row_admin = mysqli_fetch_array($run_admin);
    $admin_id = $row_admin['admin_id'];
    $admin_name = $row_admin['admin_name'];
    $_SESSION['admin_id'] = $admin_id;
    $_SESSION['admin_name'] = $admin_name;
    $admin_email = $row_admin['admin_email'];
    $admin_image = $row_admin['admin_image'];
    $admin_country = $row_admin['admin_country'];
    $admin_job = $row_admin['admin_job'];
    $admin_contact = $row_admin['admin_contact'];
    $admin_about = $row_admin['admin_about'];
    $admin_is_super = !empty($row_admin['is_super_admin']);
    $admin_role_label = $admin_is_super ? 'Super Admin' : (!empty($row_admin['admin_job']) ? htmlspecialchars($row_admin['admin_job']) : 'Admin');
    $header_display_name = !empty($admin_name) ? htmlspecialchars($admin_name) : 'Admin User';
    include("includes/admin_permissions.php");
    ?>

    <!DOCTYPE html>
    <html>

    <head>
        <title>Cadlete Designs HRMS</title>
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
        <link href="css/dashboard.css" rel="stylesheet">
        <link href="css/sidebar.css" rel="stylesheet">
        <link href="font-awesome/css/font-awesome.min.css" rel="stylesheet">
        <link rel="shortcut icon" href="images/Cadlete_Black_logo_favicon.png?v=<?php echo time(); ?>" type="image/png">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="js/jquery.min.js"></script>
        <script src="js/bootstrap.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <style>
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
        <div id="wrapper"><!-- wrapper Starts -->
            <?php include("includes/sidebar.php");  ?>
            <div id="page-wrapper"><!-- page-wrapper Starts -->
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
                                'emp_directory' => '<i class="fa fa-users"></i> Employee Directory',
                                'add_emp' => '<i class="fa fa-user-plus"></i> Add Employee',
                                'edit_emp' => '<i class="fa fa-pencil"></i> Edit Employee',
                                'attendance' => '<i class="fa fa-clock-o"></i> Attendance',
                                'salary_slip' => '<i class="fa fa-money"></i> Salary Slip',
                                'view_leave_requests' => '<i class="fa fa-calendar-times-o"></i> Leave Requests',
                                'worksheettable' => '<i class="fa fa-table"></i> Worksheet',
                                'announcement' => '<i class="fa fa-bullhorn"></i> Announcements',
                                'view_client_feedback' => '<i class="fa fa-comments"></i> Client Feedback',
                                'client_directory' => '<i class="fa fa-address-book"></i> Client Directory',
                                'view_projects' => '<i class="fa fa-briefcase"></i> Projects',
                                'projects' => '<i class="fa fa-briefcase"></i> Projects',
                                'add_project' => '<i class="fa fa-plus-square"></i> Add Project',
                                'edit_project' => '<i class="fa fa-pencil-square"></i> Edit Project',
                                'view_project' => '<i class="fa fa-eye"></i> View Project',
                                'leads' => '<i class="fa fa-bullseye"></i> Leads',
                                'add_lead' => '<i class="fa fa-plus-circle"></i> Add Lead',
                                'edit_lead' => '<i class="fa fa-pencil"></i> Edit Lead',
                                'view_lead' => '<i class="fa fa-eye"></i> View Lead',
                                'company_links' => '<i class="fa fa-link"></i> Company Links',
                                'view_users' => '<i class="fa fa-users"></i> View Users',
                                'insert_user' => '<i class="fa fa-user-plus"></i> Add User',
                                'edit_user' => '<i class="fa fa-pencil"></i> Edit User',
                                'user_profile' => '<i class="fa fa-user"></i> User Profile',
                                'view_customers' => '<i class="fa fa-users"></i> View Customers',
                                'view_orders' => '<i class="fa fa-shopping-cart"></i> View Orders',
                                'view_payments' => '<i class="fa fa-credit-card"></i> View Payments',
                                'view_offer_letters' => '<i class="fa fa-file-text"></i> Offer Letters',
                                'edit_offer_letter' => '<i class="fa fa-pencil"></i> Edit Offer Letter',
                                'view_experience_letters' => '<i class="fa fa-file-text"></i> Experience Letters',
                                'edit_experience_letter' => '<i class="fa fa-pencil"></i> Edit Experience Letter',
                                'view_nda' => '<i class="fa fa-shield"></i> NDA Forms',
                                'edit_nda' => '<i class="fa fa-pencil"></i> Edit NDA',
                                'team_todo' => '<i class="fa fa-tasks"></i> Team Todo',
                                'global_team_todos' => '<i class="fa fa-globe"></i> Global Team Todos'
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
                        <div class="notification-bell dropdown" id="admin-system-notif-dropdown">
                            <div data-toggle="dropdown" style="cursor: pointer; position: relative;" onclick="fetchLiveNotifications()">
                                <i class="fa fa-bell-o"></i>
                                <span class="notification-badge sys-notif-badge" style="display: none;">0</span>
                            </div>
                            <ul class="dropdown-menu sys-notif-list" style="right: -10px; left: auto; top: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-light); margin-top: 15px; min-width: 320px; max-height: 400px; overflow-y: auto; padding: 0;">
                                <li style="padding: 15px; text-align: center; color: #94a3b8;"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                            </ul>
                        </div>
                        <div class="topbar-profile dropdown">
                            <div data-toggle="dropdown" style="display:flex; align-items:center; gap:12px; padding:6px;background: white; border-radius:11px; cursor:pointer;">
                                <div class="profile-avatar">
                                    <img src="<?php echo !empty($admin_image) ? 'admin_images/' . $admin_image : 'https://ui-avatars.com/api/?name=' . urlencode($header_display_name) . '&background=dd2127&color=fff'; ?>" alt="Admin Avatar">
                                </div>
                                <div class="profile-info">
                                    <span class="profile-name"><?php echo $header_display_name; ?></span>
                                    <span class="profile-role"><?php echo $admin_role_label; ?></span>
                                </div>
                                <i class="fa fa-angle-down"></i>
                            </div>
                            <ul class="dropdown-menu" style="right: 0; left: auto; top: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid var(--border-light); margin-top: 10px;">
                                <li>
                                    <a href="index.php?user_profile=<?php echo isset($admin_id) ? $admin_id : ''; ?>" style="padding: 10px 20px; color: var(--text-main); font-weight: 500;">
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
                <!-- /Modern Topbar -->

                <div class="container-fluid"><!-- container-fluid Starts -->
                    <?php
                    if (isset($_GET['access_denied'])) {
                        echo '<div class="alert alert-danger"><i class="fa fa-lock"></i> Access denied. You do not have permission to view that page.</div>';
                    }
                    if (isset($_GET['dashboard']) || empty($_GET)) {
                        if (canAdminAccess('dashboard_view')) {
                            include("pages/dashboard/dashboard.php");
                        } else {
                            if (!isset($_GET['access_denied'])) {
                                echo '<div style="padding: 40px; text-align: center; color: #64748b; margin-top: 20px;">';
                                echo '<i class="fa fa-ban" style="font-size: 48px; margin-bottom: 15px; color: #cbd5e1;"></i>';
                                echo '<h3 style="color: #475569;">Dashboard Restricted</h3>';
                                echo '<p>You do not have permission to view the main dashboard widgets. Please use the sidebar to navigate to your authorized areas.</p>';
                                echo '</div>';
                            }
                        }
                    }
                    // Previously had legacy e-commerce routes here (insert_product, delete_product,
                    // edit_product, view_customers, view_orders, view_payments, etc.)
                    // These files no longer exist and routes have been removed.
                    if (isset($_GET['insert_cat'])) {
                        include("pages/settings/insert_cat.php");
                    }
                    if (isset($_GET['view_cats'])) {
                        include("view_cats.php");
                    }
                    if (isset($_GET['delete_cat'])) {
                        include("delete_cat.php");
                    }
                    if (isset($_GET['edit_cat'])) {
                        include("edit_cat.php");
                    }
                    if (isset($_GET['insert_slide'])) {
                        include("insert_slide.php");
                    }
                    if (isset($_GET['view_slides'])) {
                        include("view_slides.php");
                    }
                    if (isset($_GET['delete_slide'])) {
                        include("delete_slide.php");
                    }
                    if (isset($_GET['edit_slide'])) {
                        include("edit_slide.php");
                    }
                    if (isset($_GET['view_customers'])) {
                        include("view_customers.php");
                    }
                    if (isset($_GET['customer_delete'])) {
                        include("pages/settings/customer_delete.php");
                    }
                    if (isset($_GET['view_orders'])) {
                        include("view_orders.php");
                    }
                    if (isset($_GET['order_delete'])) {
                        include("order_delete.php");
                    }
                    if (isset($_GET['view_payments'])) {
                        include("view_payments.php");
                    }
                    if (isset($_GET['payment_delete'])) {
                        include("payment_delete.php");
                    }
                    if (isset($_GET['insert_user'])) {
                        requireAdminPermission('user_insert');
                        include("pages/settings/insert_user.php");
                    }
                    if (isset($_GET['view_users'])) {
                        requireAdminPermission('user_view');
                        include("pages/settings/view_users.php");
                    }
                    if (isset($_GET['user_delete'])) {
                        requireAdminPermission('user_delete');
                        include("pages/settings/user_delete.php");
                    }
                    if (isset($_GET['edit_user'])) {
                        requireAdminPermission('user_update');
                        include("pages/settings/edit_user.php");
                    }
                    if (isset($_GET['user_profile'])) {
                        requireAdminPermission('user_view');
                        include("pages/settings/user_profile.php");
                    }
                    if (isset($_GET['insert_box'])) {
                        include("insert_box.php");
                    }
                    if (isset($_GET['view_boxes'])) {
                        include("view_boxes.php");
                    }
                    if (isset($_GET['delete_box'])) {
                        include("delete_box.php");
                    }
                    if (isset($_GET['edit_box'])) {
                        include("edit_box.php");
                    }
                    if (isset($_GET['insert_term'])) {
                        include("insert_term.php");
                    }
                    if (isset($_GET['view_terms'])) {
                        include("view_terms.php");
                    }
                    if (isset($_GET['delete_term'])) {
                        include("delete_term.php");
                    }
                    if (isset($_GET['edit_term'])) {
                        include("edit_term.php");
                    }
                    if (isset($_GET['edit_css'])) {
                        include("edit_css.php");
                    }
                    if (isset($_GET['insert_manufacturer'])) {
                        include("insert_manufacturer.php");
                    }
                    if (isset($_GET['view_manufacturers'])) {
                        include("view_manufacturers.php");
                    }
                    if (isset($_GET['delete_manufacturer'])) {
                        include("delete_manufacturer.php");
                    }
                    if (isset($_GET['edit_manufacturer'])) {
                        include("edit_manufacturer.php");
                    }
                    if (isset($_GET['insert_coupon'])) {
                        include("insert_coupon.php");
                    }
                    if (isset($_GET['view_coupons'])) {
                        include("view_coupons.php");
                    }
                    if (isset($_GET['delete_coupon'])) {
                        include("delete_coupon.php");
                    }
                    if (isset($_GET['edit_coupon'])) {
                        include("edit_coupon.php");
                    }
                    if (isset($_GET['insert_icon'])) {
                        include("insert_icon.php");
                    }
                    if (isset($_GET['view_icons'])) {
                        include("view_icons.php");
                    }
                    if (isset($_GET['delete_icon'])) {
                        include("delete_icon.php");
                    }
                    if (isset($_GET['edit_icon'])) {
                        include("edit_icon.php");
                    }
                    if (isset($_GET['insert_bundle'])) {
                        include("insert_bundle.php");
                    }
                    if (isset($_GET['view_bundles'])) {
                        include("view_bundles.php");
                    }
                    if (isset($_GET['delete_bundle'])) {
                        include("delete_bundle.php");
                    }
                    if (isset($_GET['edit_bundle'])) {
                        include("edit_bundle.php");
                    }
                    if (isset($_GET['insert_rel'])) {
                        include("insert_rel.php");
                    }
                    if (isset($_GET['view_rel'])) {
                        include("pages/settings/view_rel.php");
                    }
                    if (isset($_GET['delete_rel'])) {
                        include("delete_rel.php");
                    }
                    if (isset($_GET['edit_rel'])) {
                        include("edit_rel.php");
                    }
                    if (isset($_GET['edit_contact_us'])) {
                        include("pages/settings/edit_contact_us.php");
                    }
                    if (isset($_GET['insert_enquiry'])) {
                        include("insert_enquiry.php");
                    }
                    if (isset($_GET['view_enquiry'])) {
                        include("view_enquiry.php");
                    }
                    if (isset($_GET['delete_enquiry'])) {
                        include("delete_enquiry.php");
                    }
                    if (isset($_GET['edit_enquiry'])) {
                        include("edit_enquiry.php");
                    }
                    if (isset($_GET['edit_about_us'])) {
                        include("pages/settings/edit_about_us.php");
                    }
                    // Legacy e-commerce store routes removed (insert_store, view_store, etc.)
                    if (isset($_GET['add_emp'])) {
                        requireAdminPermission('employee_insert');
                        include("pages/employees/add_emp.php");
                    }
                    if (isset($_GET['emp_directory'])) {
                        requireAdminPermission('employee_view');
                        include("pages/employees/emp_directory.php");
                    }
                    if (isset($_GET['edit_emp'])) {
                        requireAdminPermission('employee_update');
                        include("pages/employees/edit_emp.php");
                    }
                    if (isset($_GET['attendance'])) {
                        requireAdminPermission('attendance_view');
                        include("pages/attendance/attendance.php");
                    }
                    if (isset($_GET['attendance_report'])) {
                        requireAdminPermission('attendance_view');
                        include("pages/attendance/attendance_report.php");
                    }
                    if (isset($_GET['salary_slip'])) {
                        requireAdminPermission('salary_view');
                        include("pages/salary/salary_slip.php");
                    }
                    if (isset($_GET['view_leave_requests'])) {
                        requireAdminPermission('leave_view');
                        include("pages/leaves/view_leave_requests.php");
                    }
                    if (isset($_GET['worksheettable'])) {

                        if (function_exists('requireAdminPermission')) {
                            requireAdminPermission('worksheet_view');
                        }

                        if (file_exists("pages/worksheets/worksheettable.php")) {
                            include("pages/worksheets/worksheettable.php");
                        } else {
                            echo "<div class='alert alert-danger'>worksheettable.php file not found</div>";
                        }
                    }
                    if (isset($_GET['announcement'])) {
                        requireAdminPermission('announcement_view');
                        include("pages/announcements/announcement.php");
                    }
                    if (isset($_GET['view_client_feedback'])) {
                        // requireAdminPermission('client_feedback_view'); // Optional: check if permission exists
                        include("pages/clients/view_client_feedback.php");
                    }
                    if (isset($_GET['view_offer_letters'])) {
                        requireAdminPermission('offer_letter_view');
                        include("pages/documents/view_offer_letters.php");
                    }
                    if (isset($_GET['edit_offer_letter'])) {
                        requireAdminPermission('offer_letter_insert');
                        include("pages/documents/edit_offer_letter.php");
                    }
                    if (isset($_GET['view_experience_letters'])) {
                        requireAdminPermission('experience_letter_view');
                        include("pages/documents/view_experience_letters.php");
                    }
                    if (isset($_GET['edit_experience_letter'])) {
                        requireAdminPermission('experience_letter_insert');
                        include("pages/documents/edit_experience_letter.php");
                    }
                    if (isset($_GET['view_nda'])) {
                        requireAdminPermission('nda_view');
                        include("pages/documents/view_nda.php");
                    }
                    if (isset($_GET['edit_nda'])) {
                        requireAdminPermission('nda_insert');
                        include("pages/documents/edit_nda.php");
                    }
                    if (isset($_GET['add_client'])) {
                        requireAdminPermission('client_insert');
                        include("pages/clients/add_client.php");
                    }
                    if (isset($_GET['client_directory'])) {
                        requireAdminPermission('client_view');
                        include("pages/clients/client_directory.php");
                    }
                    if (isset($_GET['view_projects'])) {
                        requireAdminPermission('project_view');
                        include("pages/projects/view_projects.php");
                    }
                    if (isset($_GET['edit_client'])) {
                        requireAdminPermission('client_update');
                        include("pages/clients/edit_client.php");
                    }
                    if (isset($_GET['delete_client'])) {
                        requireAdminPermission('client_delete');
                        include("pages/clients/delete_client.php");
                    }
                    if (isset($_GET['leads'])) {
                        requireAdminPermission('lead_view');
                        include("pages/leads/leads.php");
                    }
                    if (isset($_GET['add_lead'])) {
                        requireAdminPermission('lead_insert');
                        include("pages/leads/add_lead.php");
                    }
                    if (isset($_GET['edit_lead'])) {
                        requireAdminPermission('lead_update');
                        include("pages/leads/edit_lead.php");
                    }
                    if (isset($_GET['view_lead'])) {
                        requireAdminPermission('lead_view');
                        include("pages/leads/view_lead.php");
                    }
                    if (isset($_GET['delete_lead'])) {
                        requireAdminPermission('lead_delete');
                        include("pages/leads/delete_lead.php");
                    }
                    if (isset($_GET['projects'])) {
                        requireAdminPermission('project_view');
                        include("pages/projects/projects.php");
                    }
                    if (isset($_GET['add_project'])) {
                        requireAdminPermission('project_insert');
                        include("pages/projects/add_project.php");
                    }
                    if (isset($_GET['edit_project'])) {
                        requireAdminPermission('project_update');
                        include("pages/projects/edit_project.php");
                    }
                    // view_project.php & delete_project.php moved – use ajax handlers instead
                    if (isset($_GET['team_todo'])) {
                        requireAdminPermission('project_assign_task');
                        include("pages/projects/team_todo.php");
                    }
                    if (isset($_GET['global_team_todos'])) {
                        requireAdminPermission('todo_view');
                        include("pages/projects/global_team_todos.php");
                    }
                    if (isset($_GET['company_links'])) {
                        requireAdminPermission('company_link_view');
                        include("pages/settings/company_links.php");
                    }
                    ?>
                </div><!-- container-fluid Ends -->
            </div><!-- page-wrapper Ends -->
        </div><!-- wrapper Ends -->

        <script>
            /* Ask browser notification permission & register service worker */
            document.addEventListener("DOMContentLoaded", function() {
                if (!("Notification" in window)) {
                    console.log("This browser does not support notifications");
                    return;
                }

                if (Notification.permission !== "granted") {
                    Notification.requestPermission();
                }

                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('sw.js').then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    }).catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
                }

                /* check admin notifications every 1.5 seconds */
                setInterval(checkAdminNotifications, 1500);
                setInterval(fetchLiveNotifications, 1500);
                fetchLiveNotifications();
            });

            function showAnnouncementNotification(title, message) {
                // Play notification sound
                var audio = new Audio('https://commondatastorage.googleapis.com/codeskulptor-assets/week7-bounce.m4a');
                audio.play().catch(function(error) {
                    console.log("Audio play failed:", error);
                });

                // OS Desktop Notification
                if (Notification.permission === "granted") {
                    navigator.serviceWorker.ready.then(function(registration) {
                        registration.showNotification(title, {
                            body: message,
                            icon: "img/notification.png",
                            requireInteraction: true
                        });
                    }).catch(function() {
                        var notification = new Notification(title, {
                            body: message,
                            icon: "img/notification.png",
                            requireInteraction: true
                        });
                        notification.onclick = function() {
                            window.focus();
                            this.close();
                        };
                    });
                }

                // In-App Toast Notification (Guarantees visual display on PC)
                var toast = document.createElement('div');
                toast.style.position = 'fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.backgroundColor = '#6dc16fff'; // Green success color
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
                }, 7000); // Remove after 7 seconds
            }

            /* ajax check */
            let _lastUnreadCount = 0;
            let _lastSeenNotifId = 0;

            function fetchLiveNotifications() {
                const isEmp = (window.location.pathname.indexOf('emp_area') !== -1);
                const endpoint = isEmp ? '../admin_area/ajax/notifications/ajax_get_user_notifications.php?portal=employee' : 'ajax/notifications/ajax_get_user_notifications.php?portal=admin';
                const markReadEndpoint = isEmp ? '../admin_area/ajax/notifications/ajax_mark_notification_read.php?portal=employee' : 'ajax/notifications/ajax_mark_notification_read.php?portal=admin';

                $.ajax({
                    url: endpoint,
                    method: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res || !res.success) return;

                        const unread = res.unread_count || 0;

                        if (unread > 0) {
                            $('.sys-notif-badge, .emp-sys-notif-badge').text(unread).show();
                        } else {
                            $('.sys-notif-badge, .emp-sys-notif-badge').hide();
                        }

                        if (res.notifications && res.notifications.length > 0) {
                            const latest = res.notifications[0];
                            const latestId = parseInt(latest.id);
                            if (_lastSeenNotifId !== 0 && latestId > _lastSeenNotifId && parseInt(latest.is_read) === 0) {
                                playNotificationChime();
                                showFloatingToastNotification(latest.title, latest.message, latest.url, latest.id);
                            }
                            _lastSeenNotifId = latestId;
                        }
                        _lastUnreadCount = unread;

                        const list = $('.sys-notif-list, .emp-sys-notif-list');
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

            function checkAdminNotifications() {
                fetch("ajax/notifications/check_admin_notifications.php")
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "new") {
                            showAnnouncementNotification(data.title, data.message);
                        }
                    })
                    .catch(error => console.log('Error checking admin notifications:', error));
                fetchLiveNotifications();
            }

            // Automatically convert all input[type="date"] & input[type="datetime-local"] to display dd-mm-yyyy format
            function initGlobalFlatpickr() {
                if (typeof flatpickr !== 'function') return;
                $('input[type="date"], input[type="datetime-local"]').not('.no-global-flatpickr').each(function() {
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

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                var sidebar = document.querySelector('.modern-sidebar');
                var toggleBtn = document.querySelector('.menu-toggle');
                if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('show')) {
                    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        </script>
    </body>

    </html>
<?php } ?>