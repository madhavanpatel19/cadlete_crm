<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
?>
<!DOCTYPE HTML>
<html>

<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../css/login.css">
    <link rel="shortcut icon" href="../../images/Cadlete_Black_logo_favicon.png?v=<?php echo time(); ?>" type="image/png">

    <style>
        .premium-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            font-family: inherit;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .premium-notification.active {
            transform: translateX(0);
        }

        .premium-notification i {
            font-size: 20px;
        }

        .notification-success {
            background: rgba(16, 185, 129, 0.9);
        }

        .notification-error {
            background: rgba(239, 68, 68, 0.9);
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <!-- <div class="left-section"></div> -->
        <!-- <div class="right-section">
            <div class="features-container">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa fa-shield"></i></div>
                    <div class="feature-text">
                        <h4>Secure Access</h4>
                        <p>Your data is protected</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa fa-bolt"></i></div>
                    <div class="feature-text">
                        <h4>Smart Workflow</h4>
                        <p>Manage everything in one place</p>
                    </div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa fa-users"></i></div>
                    <div class="feature-text">
                        <h4>Team Collaboration</h4>
                        <p>Built for better teamwork</p>
                    </div>
                </div>
            </div>
        </div> -->

        <div class="login-card">
            <form action="" method="post">
                <div class="brand-logo">
                    <img src="../../images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
                </div>
                <h2 class="welcome-text">Welcome Back!</h2>
                <p class="subtitle">Sign in to continue to Cadlete Designs Portal</p>

                <div class="login-input-wrap">
                    <i class="fa fa-envelope-o input-icon"></i>
                    <input type="email" name="admin_email" class="form-control" placeholder="Email Address" required>
                </div>

                <div class="login-input-wrap">
                    <i class="fa fa-lock input-icon"></i>
                    <input type="password" name="admin_pass" class="form-control" id="password_input" placeholder="Password" required>
                    <i class="fa fa-eye toggle-password" id="toggle_password" style="cursor:pointer" onclick="togglePasswordVisibility()"></i>
                </div>

                <div class="login-form-actions">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked> Remember me
                    </label>
                    <!-- <a href="forgot_pass.php" class="forgot-link">Forgot password?</a> -->
                </div>

                <button type="submit" name="admin_login" class="btn-login">
                    <span>Log in</span>
                    <div class="btn-icon-wrapper">
                        <i class="fa fa-sign-in"></i>
                    </div>
                </button>


                <!-- <p class="copyright">&copy; 2026 Cadlete Designs. All rights reserved.</p> -->
            </form>
        </div>
    </div>

    <script>
        function showPremiumAlert(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `premium-notification notification-${type}`;

            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            toast.innerHTML = `<i class="fa ${icon}"></i> <span>${message}</span>`;

            document.body.appendChild(toast);

            // Trigger animation
            setTimeout(() => toast.classList.add('active'), 10);

            // Remove after 3.5 seconds
            if (type === 'error') {
                setTimeout(() => {
                    toast.classList.remove('active');
                    setTimeout(() => toast.remove(), 400);
                }, 3500);
            }
        }

        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password_input');
            const toggleIcon = document.getElementById('toggle_password');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>
<?php
if (isset($_POST['admin_login'])) {
    $admin_email = mysqli_real_escape_string($con, $_POST['admin_email']);
    $raw_pass = $_POST['admin_pass'];
    $get_admin = "SELECT * FROM admins WHERE admin_email='$admin_email'";
    $run_admin = mysqli_query($con, $get_admin);

    if (!$run_admin) {
        die("Database Query Failed: " . mysqli_error($con));
    }

    $login_success = false;
    if ($run_admin && mysqli_num_rows($run_admin) > 0) {
        $row_admin = mysqli_fetch_assoc($run_admin);
        $stored_pass = $row_admin['admin_pass'];

        if (password_verify($raw_pass, $stored_pass) || $stored_pass === $raw_pass) {
            $login_success = true;
            // Upgrade legacy plain-text password to hash automatically
            if (password_get_info($stored_pass)['algo'] === 0) {
                $new_hash = password_hash($raw_pass, PASSWORD_DEFAULT);
                $aid = (int)$row_admin['admin_id'];
                mysqli_query($con, "UPDATE admins SET admin_pass='$new_hash' WHERE admin_id=$aid");
            }
        }
    }

    if ($login_success) {
        $_SESSION['admin_email'] = $admin_email;
        unset($_SESSION['_admin_super'], $_SESSION['_admin_perms']);
        echo "<script>showPremiumAlert('Logged in successfully! Redirecting...', 'success')</script>";
        echo "<script>setTimeout(() => window.open('../../index.php?dashboard','_self'), 1500)</script>";
    } else {
        echo "<script>showPremiumAlert('Invalid Email or Password', 'error')</script>";
    }
}
?>