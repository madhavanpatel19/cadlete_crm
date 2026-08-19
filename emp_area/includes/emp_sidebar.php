<?php
// =============================================================
// emp_area/includes/emp_sidebar.php
// Employee navigation sidebar & top navbar.
// Moved from: admin_area/includes/emp_sidebar.php
// Paths updated to be relative from emp_area root.
// =============================================================
if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
} else {
    $emp_name = $_SESSION['emp_name'];
    $header_display_name = htmlspecialchars($emp_name);
?>
    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php?dashboard">Cadlete Designs (Employee)</a>
        </div>
        <ul class="nav navbar-right top-nav">
            <?php
            $emp_id = $_SESSION['emp_id'];
            $unread_count = 0;
            $get_unread_count = "SELECT COUNT(*) AS total FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) AND id NOT IN (SELECT announcement_id FROM announcement_read WHERE emp_id='$emp_id')";
            $run_unread_count = mysqli_query($con, $get_unread_count);
            if ($run_unread_count) {
                $row_unread_count = mysqli_fetch_array($run_unread_count);
                $unread_count = $row_unread_count['total'];
            }
            ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <i class="fa fa-bell"></i>
                    <?php if ($unread_count > 0) : ?>
                        <span class="label label-danger" style="position: absolute; top: 10px; right: 5px; border-radius: 50%; padding: 2px 5px; font-size: 10px;"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu" style="width: 300px; max-height: 400px; overflow-y: auto;">
                    <li class="header" style="padding: 10px; border-bottom: 1px solid #ddd; font-weight: bold;">Announcements</li>
                    <?php
                    $get_recent_announcements = "SELECT * FROM announcements WHERE is_active = 1 AND (publish_date IS NULL OR publish_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY publish_date DESC LIMIT 5";
                    $run_recent = mysqli_query($con, $get_recent_announcements);
                    if (mysqli_num_rows($run_recent) > 0) {
                        while ($row_recent = mysqli_fetch_array($run_recent)) {
                            $ann_id = $row_recent['id'];
                            $ann_title = $row_recent['title'];
                            $ann_date = date('M d, H:i', strtotime(!empty($row_recent['publish_date']) ? $row_recent['publish_date'] : $row_recent['created_at']));

                            // Check if read
                            $check_read = "SELECT * FROM announcement_read WHERE announcement_id='$ann_id' AND emp_id='$emp_id'";
                            $run_check = mysqli_query($con, $check_read);
                            $is_unread = mysqli_num_rows($run_check) == 0;
                            $bg_style = $is_unread ? "background-color: #f9f9f9;" : "";
                    ?>
                            <li style="<?php echo $bg_style; ?>">
                                <a href="index.php?view_announcement=<?php echo $ann_id; ?>" style="white-space: normal; padding: 10px;">
                                    <strong><?php echo htmlspecialchars($ann_title); ?></strong><br>
                                    <small class="text-muted"><?php echo $ann_date; ?></small>
                                    <?php if ($is_unread) : ?>
                                        <span class="label label-primary pull-right">New</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                    <?php
                        }
                    } else {
                        echo "<li style='padding: 10px;'>No announcements found.</li>";
                    }
                    ?>
                </ul>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <i class="fa fa-user"></i> <?php echo $header_display_name; ?>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a href="pages/auth/logout.php">
                            <i class="fa fa-fw fa-power-off"> </i> Log Out
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <!-- MODERN SIDEBAR -->
    <aside class="modern-sidebar">
        <div class="sidebar-logo">
            <img src="../admin_area/images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
        </div>

        <div class="sidebar-menu">
            <h3 class="menu-heading">WORKSPACE</h3>
            <ul>
                <li class="<?php if (isset($_GET['dashboard'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?dashboard"><i class="fa fa-th-large"></i> Dashboard</a>
                </li>
                <li class="<?php if (isset($_GET['projects']) || isset($_GET['team_todo'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?projects"><i class="fa fa-sitemap"></i> Projects</a>
                </li>
                <li class="<?php if (isset($_GET['worksheet'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?worksheet"><i class="fa fa-file-text-o"></i> Worksheet</a>
                </li>
                <li class="<?php if (isset($_GET['todo'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?todo"><i class="fa fa-tasks"></i> To-do</a>
                </li>
                <li class="<?php if (isset($_GET['quick_links'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?quick_links"><i class="fa fa-link"></i> Quick Links</a>
                </li>
            </ul>

            <h3 class="menu-heading">HR</h3>
            <ul>
                <li class="<?php if (isset($_GET['leave_application'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?leave_application"><i class="fa fa-paper-plane"></i> Leave Application</a>
                </li>
                <li class="<?php if (isset($_GET['emp_salary_slip'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?emp_salary_slip"><i class="fa fa-money"></i> Salary Slip</a>
                </li>
                <li class="<?php if (isset($_GET['view_announcement']) || isset($_GET['announcements'])) {
                                echo "active";
                            } ?>">
                    <a href="index.php?announcements"><i class="fa fa-bullhorn"></i> Announcements</a>
                </li>
            </ul>

            <!-- <h3 class="menu-heading">SETTINGS</h3>
            <ul>
                <li>
                    <a href="pages/auth/logout.php"><i class="fa fa-power-off"></i> Log Out</a>
                </li>
            </ul> -->
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var sidebar = document.querySelector('.modern-sidebar');
                if (sidebar) {
                    var scrollPos = localStorage.getItem('empSidebarScrollPos');
                    if (scrollPos) {
                        sidebar.scrollTop = scrollPos;
                    }

                    sidebar.addEventListener('scroll', function() {
                        localStorage.setItem('empSidebarScrollPos', sidebar.scrollTop);
                    });
                }
            });
        </script>
    </aside>
    <!-- END MODERN SIDEBAR -->
<?php } ?>