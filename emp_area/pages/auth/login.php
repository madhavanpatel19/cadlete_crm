<?php
// =============================================================
// emp_area/pages/auth/login.php
// Employee login page.
// Moved from: admin_area/pages/auth/emp-login.php
// Paths updated to be relative from emp_area/pages/auth.
// Functions: login_user() - handles employee authentication
// PHPMailer: Used in forgot_pass.php for OTP (not deleted/not here)
// =============================================================
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

$login_status = "";

// =============================================================
// FUNCTION: login_user()
// Handles employee authentication:
//   - Validates email & password against emp_list table
//   - Checks account active/inactive status
//   - Sets session variables on success
//   - Returns status: 'success' | 'error' | 'inactive'
// Called when: $_POST['login'] is set
// =============================================================
function login_user($con)
{
    // -- Sanitize inputs --
    $email    = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password']; // Plain text comparison (same as existing)

    // -- Query emp_list for matching credentials (ONLY Company Email is accepted for login) --
    $query = mysqli_query($con, "SELECT * FROM emp_list WHERE (company_email='$email' OR ((company_email IS NULL OR company_email='') AND email='$email')) AND password='$password'");

    if ($query && mysqli_num_rows($query) > 0) {
        $user = mysqli_fetch_assoc($query);

        // -- Check if account is active --
        if (isset($user['status']) && $user['status'] === 'Inactive') {
            return "inactive";
        } else {
            // -- Start session and set session variables --
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['emp_id']  = $user['id'];
            $_SESSION['emp_name'] = $user['name'];
            return "success";
        }
    } else {
        return "error";
    }
} // end login_user()

// -- Trigger login_user() on form submit --
if (isset($_POST['login'])) {
    $login_status = login_user($con);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employee Login | Cadlete</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../admin_area/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../admin_area/font-awesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../../admin_area/css/login.css">
    <link rel="shortcut icon" href="../../../admin_area/images/Cadlete_Black_logo_favicon.png?v=<?php echo time(); ?>" type="image/png">

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
        <div class="login-card">
            <form action="" method="post">
                <div class="brand-logo">
                    <img src="../../../admin_area/images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
                </div>
                <h2 class="welcome-text">Employee Login</h2>
                <p class="subtitle">Sign in to continue to Cadlete Designs Portal</p>

                <div class="login-input-wrap">
                    <i class="fa fa-envelope-o input-icon"></i>
                    <input type="email" name="email" class="form-control" placeholder="Company Email Address" required autocomplete="email">
                </div>

                <div class="login-input-wrap">
                    <i class="fa fa-lock input-icon"></i>
                    <input type="password" name="password" class="form-control" id="password_input" placeholder="Password" required autocomplete="current-password">
                    <i class="fa fa-eye toggle-password" id="toggle_password" style="cursor:pointer" onclick="togglePasswordVisibility()"></i>
                </div>

                <div class="login-form-actions">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" checked> Remember me
                    </label>
                    <!-- Forgot Password disabled: password changes are managed by Admin via Edit Employee page -->
                    <!-- <a href="forgot_pass.php" class="forgot-link">Forgot Password?</a> -->
                </div>

                <button type="submit" name="login" class="btn-login">
                    <span>Log in</span>
                    <div class="btn-icon-wrapper">
                        <i class="fa fa-sign-in"></i>
                    </div>
                </button>
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

            setTimeout(() => toast.classList.add('active'), 10);

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

        <?php if ($login_status === 'success'): ?>
            showPremiumAlert('Logged in successfully! Redirecting...', 'success');
            setTimeout(() => window.open('../../index.php?dashboard', '_self'), 1500);
        <?php elseif ($login_status === 'error'): ?>
            showPremiumAlert('Invalid Email or Password', 'error');
        <?php elseif ($login_status === 'inactive'): ?>
            showPremiumAlert('Your account is inactive. Access denied.', 'error');
        <?php endif; ?>
    </script>
</body>

</html>