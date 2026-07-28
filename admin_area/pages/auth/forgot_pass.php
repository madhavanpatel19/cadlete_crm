<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata'); // Synchronize with local server time
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error = '';
$success = '';

// Capture success message from resend redirect
if (isset($_SESSION['resend_success'])) {
    $success = $_SESSION['resend_success'];
    unset($_SESSION['resend_success']);
}

// Handle Clear/Restart action
if (isset($_GET['clear'])) {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_step']);
    header("Location: forgot_pass.php");
    exit();
}

// Handle Resend action (Only on GET, not POST, to prevent auto-clobbering OTP during password reset)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['resend']) && isset($_SESSION['reset_email'])) {
    $email = $_SESSION['reset_email'];
    $_POST['send_otp'] = true; // Mimic form submission to reuse logic
    $_POST['email'] = $email;
    $_SESSION['resend_triggered'] = true; // Flag for later redirect
}

// Determine current state based on session
$currentState = 'email'; // default
if (isset($_SESSION['reset_step'])) {
    $currentState = $_SESSION['reset_step'];
}

// 1. SEND OTP ACTION
if (isset($_POST['send_otp'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $query = "SELECT * FROM emp_list WHERE email='$email'";
    $run_query = mysqli_query($con, $query);

    if ($run_query && mysqli_num_rows($run_query) > 0) {
        $otp = rand(100000, 999999);
        $expire = date("Y-m-d H:i:s", strtotime("+15 minutes")); // Increased to 15 mins for reliability

        // Update DB with OTP
        mysqli_query($con, "UPDATE emp_list SET otp='$otp', otp_expire='$expire' WHERE email='$email'");

        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'madhavanpatel19@gmail.com'; // your gmail
            $mail->Password   = 'yawi nqpw wbhp icrx';       // gmail app password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('madhavanpatel19@gmail.com', 'Cadlete Support');
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - Cadlete';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #2c3e50; text-align: center;'>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your Cadlete account. Use the OTP below to proceed:</p>
                    <div style='background: #f4f7f6; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                        <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #3498db;'>$otp</span>
                    </div>
                    <p style='color: #e74c3c; font-weight: bold;'>This OTP will expire in 15 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #7f8c8d; text-align: center;'>&copy; " . date('Y') . " Cadlete. All rights reserved.</p>
                </div>
            ";

            $mail->send();

            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_step'] = 'otp';
            $currentState = 'otp';
            $success = "A 6-digit OTP has been sent to your registered email.";

            // If this was a resend, redirect to clear the ?resend=true from URL
            if (isset($_SESSION['resend_triggered'])) {
                unset($_SESSION['resend_triggered']);
                $_SESSION['resend_success'] = $success;
                header("Location: forgot_pass.php");
                exit();
            }
        } catch (Exception $e) {
            $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "The provided email address is not registered in our system.";
    }
}

// 2. VERIFY OTP & RESET PASSWORD ACTION
if (isset($_POST['reset_password'])) {
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    $otp = mysqli_real_escape_string($con, $_POST['otp']);
    $newpass = $_POST['newpass'];
    $confpass = $_POST['confpass'];

    if ($email == '') {
        $error = "Session expired. Please start over.";
        unset($_SESSION['reset_step']);
        $currentState = 'email';
    } elseif ($newpass !== $confpass) {
        $error = "Passwords do not match. Please try again.";
        $currentState = 'otp';
    } else {
        $current_time = date("Y-m-d H:i:s");
        // Detailed check: First find if such an OTP exists for this email
        $query = "SELECT * FROM emp_list WHERE email='$email' AND otp='$otp'";
        $run_query = mysqli_query($con, $query);

        if ($run_query && mysqli_num_rows($run_query) > 0) {
            $row = mysqli_fetch_array($run_query);
            $db_expire = $row['otp_expire'];

            // Now check if it's expired
            if ($db_expire < $current_time) {
                $error = "This OTP has expired (15-minute limit). Please click 'Resend OTP' below.";
                $currentState = 'otp';
            } else {
                // Success! Reset password
                // Note: Using plain text as requested by user
                mysqli_query($con, "UPDATE emp_list SET password='$newpass', otp='', otp_expire=NULL WHERE email='$email'");

                // Clean up session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_step']);

                echo "<script>Swal.fire({title: 'Notification', text: 'Password Reset Successful! Please login with your new password.', icon: 'success'}).then(() => { window.location.href='emp-login.php'; });</script>";
                exit();
            }
        } else {
            $error = "Invalid OTP code. Please check your email and try again.";
            $currentState = 'otp';
        }
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Forgot Password - Cadlete</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <link rel="stylesheet" href="../../css/login.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

</head>

<body>
    <div class="page-wrapper">
        <!-- <div class="left-section"></div>
        <div class="right-section">
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
            <?php if ($currentState === 'email') { ?>
                <form action="" method="POST">
                    <div class="brand-logo">
                        <img src="../../images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
                    </div>
                    <h2 class="welcome-text">Forgot Password</h2>
                    <p class="subtitle">Enter your email to receive an OTP</p>

                    <?php if ($error) echo "<div class='alert alert-danger' style='color:red;font-size:13px;margin-bottom:15px;'><i class='fa fa-exclamation-circle'></i> $error</div>"; ?>
                    <?php if ($success) echo "<div class='alert alert-success' style='color:green;font-size:13px;margin-bottom:15px;'><i class='fa fa-check-circle'></i> $success</div>"; ?>

                    <div class="login-input-wrap">
                        <i class="fa fa-envelope-o input-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter Registered Email" required>
                    </div>

                    <button type="submit" name="send_otp" class="btn-login" style="margin-top: 15px; margin-bottom: 15px">
                        <span>Send OTP</span>
                        <div class="btn-icon-wrapper">
                            <i class="fa fa-paper-plane"></i>
                        </div>
                    </button>

                    <div style="text-align:center;">
                        <a href="emp-login.php" class="forgot-link"><i class="fa fa-arrow-left"></i> Back to Login</a>
                    </div>
                </form>
            <?php } else { ?>
                <form action="" method="POST">
                    <div class="brand-logo">
                        <img src="images/Cadlete_logo Landscape.png" alt="Cadlete Designs">
                    </div>
                    <h2 class="welcome-text">Reset Password</h2>
                    <p class="subtitle">Create a new secure password</p>

                    <?php if ($error) echo "<div class='alert alert-danger' style='color:red;font-size:13px;margin-bottom:15px;'><i class='fa fa-exclamation-circle'></i> $error</div>"; ?>
                    <?php if ($success) echo "<div class='alert alert-success' style='color:green;font-size:13px;margin-bottom:15px;'><i class='fa fa-check-circle'></i> $success</div>"; ?>

                    <div style="text-align: center; margin-bottom: 15px;">
                        <span style="font-size: 13px; color: var(--primary-color); background: #e0e7ff; padding: 6px 12px; border-radius: 12px; font-weight: 500;"><i class="fa fa-envelope-o"></i> OTP Sent to <?php echo htmlspecialchars($_SESSION['reset_email']); ?></span>
                    </div>

                    <div class="login-input-wrap">
                        <i class="fa fa-key input-icon"></i>
                        <input type="text" name="otp" class="form-control" placeholder="Enter OTP" required style="letter-spacing: 3px;">
                    </div>

                    <div class="login-input-wrap">
                        <i class="fa fa-lock input-icon"></i>
                        <input type="password" name="newpass" class="form-control" placeholder="New Password" required>
                    </div>

                    <div class="login-input-wrap">
                        <i class="fa fa-lock input-icon"></i>
                        <input type="password" name="confpass" class="form-control" placeholder="Confirm Password" required>
                    </div>

                    <button type="submit" name="reset_password" class="btn-login" style="margin-top: 15px;">
                        <span>Reset Password</span>
                        <div class="btn-icon-wrapper">
                            <i class="fa fa-check"></i>
                        </div>
                    </button>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="?resend=true" class="forgot-link" style="margin-right:15px;"><i class="fa fa-refresh"></i> Resend OTP</a>
                        <a href="?clear=true" class="forgot-link" style="color:var(--text-muted);">Try another email</a>
                    </div>

                    <div style="text-align:center; margin-top:15px;">
                        <a href="emp-login.php" class="forgot-link"><i class="fa fa-arrow-left"></i> Back to Login</a>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</body>

</html>