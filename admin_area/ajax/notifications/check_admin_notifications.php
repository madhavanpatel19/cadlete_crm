<?php
// API is turned off / commented out
header('Content-Type: application/json');
echo json_encode(["status" => "none"]);
exit();

/*
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (!isset($_SESSION['admin_email'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit();
}

// --- BIRTHDAY LOGIC ---
$today_day_month    = date('m-d');
$tomorrow_day_month = date('m-d', strtotime('+1 day'));
$current_year       = date('Y');

// 1. Check for Birthdays TODAY (for Auto-Wish and Admin Notification)
$today_birthdays_query = "SELECT * FROM emp_list WHERE DATE_FORMAT(dob, '%m-%d') = '$today_day_month'";
$run_today = mysqli_query($con, $today_birthdays_query);

while ($emp = mysqli_fetch_array($run_today)) {
    $emp_id    = $emp['id'];
    $emp_name  = $emp['name'];
    $emp_email = $emp['email'];
    $last_wish = $emp['last_birthday_wish_year'];

    if ($last_wish != $current_year) {

        // =============================================================
        // PHPMailer - Send Auto Birthday Wish Email to Employee
        // STATUS: FULLY COMMENTED OUT
        // To enable: uncomment the require lines AND the $mail block below.
        // =============================================================
        //
        // require_once 'PHPMailer/src/Exception.php';
        // require_once 'PHPMailer/src/PHPMailer.php';
        // require_once 'PHPMailer/src/SMTP.php';
        //
        // $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // try {
        //     $mail->isSMTP();
        //     $mail->Host       = 'smtp.gmail.com';
        //     $mail->SMTPAuth   = true;
        //     $mail->Username   = 'madhavanpatel19@gmail.com';
        //     $mail->Password   = 'yawi nqpw wbhp icrx';
        //     $mail->SMTPSecure = 'tls';
        //     $mail->Port       = 587;
        //
        //     $mail->setFrom('madhavanpatel19@gmail.com', 'Cadlete HR Team');
        //     $mail->addAddress($emp_email);
        //
        //     $mail->isHTML(true);
        //     $mail->Subject = "Happy Birthday, $emp_name!";
        //     $mail->Body    = "
        //         <div style='font-family: Arial, sans-serif; text-align: center; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
        //             <h1 style='color: #4f46e5;'>🎉 Happy Birthday, $emp_name! 🎂</h1>
        //             <p>On behalf of the Cadlete team, we wish you a wonderful day filled with joy, laughter, and success!</p>
        //             <p>May this year bring you closer to your dreams and reward you with all the happiness you deserve.</p>
        //             <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
        //             <p style='font-size: 14px; color: #777;'>Sent with ❤️ from Cadlete</p>
        //         </div>
        //     ";
        //
        //     $mail->send();
        // } catch (Exception $e) {
        //     // Email failed silently – birthday notification still shows
        // }
        // =============================================================

        // -- Mark as wished even though email is disabled (prevents re-triggering) --
        mysqli_query($con, "UPDATE emp_list SET last_birthday_wish_year = '$current_year' WHERE id = '$emp_id'");

        // Notify admin that today is employee's birthday (notification still works)
        if (!isset($_SESSION['birthday_notified_' . $emp_id . '_' . $current_year])) {
            $_SESSION['birthday_notified_' . $emp_id . '_' . $current_year] = true;
            echo json_encode([
                "status"  => "new",
                "title"   => "🎂 Birthday Today!",
                "message" => "Today is $emp_name's birthday! (Auto email is disabled)"
            ]);
            exit();
        }
    }
}

// 2. Check for Birthdays TOMORROW (Reminder for Admin)
$tomorrow_birthdays_query = "SELECT * FROM emp_list WHERE DATE_FORMAT(dob, '%m-%d') = '$tomorrow_day_month'";
$run_tomorrow = mysqli_query($con, $tomorrow_birthdays_query);

if (mysqli_num_rows($run_tomorrow) > 0) {
    while ($emp = mysqli_fetch_array($run_tomorrow)) {
        $emp_id = $emp['id'];
        $emp_name = $emp['name'];

        // Show notification once per session per year
        if (!isset($_SESSION['tomorrow_bday_notified_' . $emp_id . '_' . $current_year])) {
            $_SESSION['tomorrow_bday_notified_' . $emp_id . '_' . $current_year] = true;
            echo json_encode([
                "status" => "new",
                "title" => "📅 Upcoming Birthday",
                "message" => "Tomorrow is $emp_name's birthday! Prepare the celebrations."
            ]);
            exit();
        }
    }
}

// --- ORIGINAL LEAVE NOTIFICATION LOGIC ---
// Get the latest leave application ID from the database
$query = "SELECT l.id, l.reason, e.name as emp_name FROM leave_applications l JOIN emp_list e ON l.emp_id = e.id ORDER BY l.id DESC LIMIT 1";
$run = mysqli_query($con, $query);

if (mysqli_num_rows($run) > 0) {
    $row = mysqli_fetch_array($run);
    $latest_id = $row['id'];
    $emp_name = $row['emp_name'];
    $reason = $row['reason'];

    // If the session variable is not set, initialize it
    if (!isset($_SESSION['last_admin_leave_id'])) {
        $_SESSION['last_admin_leave_id'] = $latest_id;
    } else {
        // Check if there's a new leave application
        if ($latest_id > $_SESSION['last_admin_leave_id']) {
            $_SESSION['last_admin_leave_id'] = $latest_id;
            echo json_encode([
                "status" => "new",
                "title" => "New Leave Request: " . $emp_name,
                "message" => "Reason: " . substr($reason, 0, 50) . "..."
            ]);
            exit();
        }
    }
}

// --- LEAD FOLLOW-UP NOTIFICATION LOGIC REMOVED ---
// Leads follow-ups are now shown in the top right red notification bell dropdown.

echo json_encode(["status" => "none"]);
*/