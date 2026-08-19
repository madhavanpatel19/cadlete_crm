<?php
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
} else {
    if (!function_exists('canAdminAccess')) {
        include(__DIR__ . '/admin_permissions.php');
    }
    if (!isset($admin_name) && isset($con) && !empty($_SESSION['admin_email'])) {
        $email = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $res = @mysqli_query($con, "SELECT admin_id, admin_name FROM admins WHERE admin_email='$email' LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $admin_id = isset($admin_id) ? $admin_id : $row['admin_id'];
            $admin_name = $row['admin_name'];
        } else {
            $admin_name = $_SESSION['admin_email'];
        }
    }
    $header_display_name = isset($admin_name) && $admin_name !== '' ? htmlspecialchars($admin_name) : htmlspecialchars($_SESSION['admin_email']);

    // Count pending leave applications
    $pending_leave_count = 0;
    $count_leave_query = "SELECT count(*) AS total FROM leave_applications WHERE status='pending'";
    $run_count_leave = mysqli_query($con, $count_leave_query);
    if ($run_count_leave) {
        $row_count_leave = mysqli_fetch_array($run_count_leave);
        $pending_leave_count = $row_count_leave['total'];
    }

    // Count unread client feedback
    $unread_feedback_count = 0;
    $count_feedback_query = "SELECT count(*) AS total FROM customer_feedback WHERE is_read=0";
    $run_count_feedback = mysqli_query($con, $count_feedback_query);
    if ($run_count_feedback) {
        $row_count_feedback = mysqli_fetch_array($run_count_feedback);
        $unread_feedback_count = $row_count_feedback['total'];
    }

    // Count follow-up leads for today
    $today_followup_count = 0;
    $today_date = date('Y-m-d');
    $count_followup_query = "SELECT count(*) AS total FROM leads WHERE followup_date = '$today_date' AND status != 'expired' AND deleted_at IS NULL";
    $run_count_followup = mysqli_query($con, $count_followup_query);
    if ($run_count_followup) {
        $row_count_followup = mysqli_fetch_array($run_count_followup);
        $today_followup_count = $row_count_followup['total'];
    }

    $total_notifications = $pending_leave_count + $unread_feedback_count + $today_followup_count;
?>
    <nav class="navbar navbar-inverse navbar-fixed-top"><!-- navbar navbar-inverse navbar-fixed-top Starts -->
        <div class="navbar-header"><!-- navbar-header Starts -->
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse"><!-- navbar-ex1-collapse Starts -->
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button><!-- navbar-ex1-collapse Ends -->
            <a class="navbar-brand" href="index.php?dashboard">Cadlete</a>
        </div><!-- navbar-header Ends -->
        <ul class="nav navbar-right top-nav"><!-- nav navbar-right top-nav Starts -->
            <li class="dropdown" id="system-notif-dropdown"><!-- notification dropdown Starts -->
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" onclick="fetchLiveNotifications()">
                    <i class="fa fa-bell"></i>
                    <span class="label label-danger sys-notif-badge" style="position: absolute; top: 10px; right: 5px; border-radius: 50%; padding: 2px 5px; font-size: 10px; display: none;">0</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-right sys-notif-list" style="min-width: 320px; max-height: 400px; overflow-y: auto; padding: 0;">
                    <li style="padding: 15px; text-align: center; color: #94a3b8;"><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                </ul>
            </li><!-- notification dropdown Ends -->
            <li class="dropdown"><!-- dropdown Starts -->
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <i class="fa fa-user"></i> <?php echo $header_display_name; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-right"><!-- dropdown-menu Starts -->
                    <?php if (canAdminAccess('user_view')): ?>
                        <li><!-- li Starts -->
                            <a href="index.php?user_profile=<?php echo $admin_id; ?>">
                                <i class="fa fa-fw fa-user"></i> Profile
                            </a>
                        </li>
                        <li><!-- li Starts -->
                            <a href="index.php?view_users">
                                <i class="fa fa-fw fa-users"></i> Users
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="divider"></li>
                    <li><!-- li Starts -->
                        <a href="pages/auth/logout.php">
                            <i class="fa fa-fw fa-power-off"> </i> Log Out
                        </a>
                    </li><!-- li Ends -->
                </ul><!-- dropdown-menu Ends -->
            </li><!-- dropdown Ends -->
        </ul><!-- nav navbar-right top-nav Ends -->

    </nav><!-- navbar navbar-inverse navbar-fixed-top Ends -->

    <!-- MODERN SIDEBAR -->
    <aside class="modern-sidebar">
        <div class="sidebar-logo">
            <img src="images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
        </div>

        <div class="sidebar-menu">
            <h3 class="menu-heading">WORKSPACE</h3>
            <ul>
                <li class="<?php if (isset($_GET['dashboard'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?dashboard"><i class="fa fa-th-large"></i> Dashboard</a>
                </li>
                <?php if (canAdminAccess('project_view')): ?>
                    <li class="<?php if (isset($_GET['projects']) || isset($_GET['add_project']) || isset($_GET['edit_project']) || isset($_GET['view_projects']) || isset($_GET['team_todo'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?projects"><i class="fa fa-briefcase"></i> Projects</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('todo_view')): ?>
                    <li class="<?php if (isset($_GET['global_team_todos'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?global_team_todos"><i class="fa fa-list-alt"></i> Team To-Do</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('lead_view')): ?>
                    <li class="<?php if (isset($_GET['leads'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?leads"><i class="fa fa-bullseye"></i> Leads</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('worksheet_view')): ?>
                    <li class="<?php if (isset($_GET['worksheettable'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?worksheettable"><i class="fa fa-table"></i> Worksheet</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('company_link_view')): ?>
                    <li class="<?php if (isset($_GET['company_links'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?company_links"><i class="fa fa-link"></i> Company Links</a>
                    </li>
                <?php endif; ?>
            </ul>

            <h3 class="menu-heading">HR MANAGEMENT</h3>
            <ul>
                <?php if (canAdminAccess('employee_view')): ?>
                    <li class="<?php if (isset($_GET['emp_directory'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?emp_directory"><i class="fa fa-users"></i> View Employees</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('attendance_view')): ?>
                    <li class="<?php if (isset($_GET['attendance'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?attendance"><i class="fa fa-calendar"></i> Attendance</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('leave_view')): ?>
                    <li class="<?php if (isset($_GET['view_leave_requests'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?view_leave_requests"><i class="fa fa-file-text"></i> Leave Requests</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('salary_view')): ?>
                    <li class="<?php if (isset($_GET['salary_slip'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?salary_slip"><i class="fa fa-money"></i> Salary Slips</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('offer_letter_view')): ?>
                    <li class="<?php if (isset($_GET['view_offer_letters'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?view_offer_letters"><i class="fa fa-file-text-o"></i> Offer Letters</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('nda_view')): ?>
                    <li class="<?php if (isset($_GET['view_nda'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?view_nda"><i class="fa fa-shield"></i> NDA Forms</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('experience_letter_view')): ?>
                    <li class="<?php if (isset($_GET['view_experience_letters'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?view_experience_letters"><i class="fa fa-certificate"></i> Experience Letters</a>
                    </li>
                <?php endif; ?>
            </ul>

            <h3 class="menu-heading">ADMIN</h3>
            <ul>
                <?php if (canAdminAccess('user_view')): ?>
                    <li class="<?php if (isset($_GET['view_users'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?view_users"><i class="fa fa-user-secret"></i> View Users</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('announcement_view')): ?>
                    <li class="<?php if (isset($_GET['announcement'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?announcement"><i class="fa fa-bullhorn"></i> Announcement</a>
                    </li>
                <?php endif; ?>
                <?php if (canAdminAccess('client_view')): ?>
                    <li class="<?php if (isset($_GET['client_directory'])) {
                                    echo "active";
                                } ?>">
                        <a href="index.php?client_directory"><i class="fa fa-users"></i> View Clients</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var sidebar = document.querySelector('.modern-sidebar');
                if (sidebar) {
                    var scrollPos = localStorage.getItem('sidebarScrollPos');
                    if (scrollPos) {
                        sidebar.scrollTop = scrollPos;
                    }

                    sidebar.addEventListener('scroll', function() {
                        localStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
                    });
                }
            });
        </script>
    </aside>
    <!-- END MODERN SIDEBAR -->
<?php } ?>