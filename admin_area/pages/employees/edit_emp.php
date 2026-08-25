<?php
// =============================================================
// admin_area/pages/employees/edit_emp.php
// Admin: Edit / Update Existing Employee
// Functions: edit_user() - updates employee with optional password reset
//            handleFileUpload() - handles document uploads
// PHPMailer: Fully commented out (uncomment to enable email sending)
// =============================================================

if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

// ------------------------------------------------------------------
// PHPMailer Includes (commented out - uncomment to enable email)
// ------------------------------------------------------------------
// require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
// ------------------------------------------------------------------

$employee = null;
if (isset($_GET['edit_emp'])) {
    $id = mysqli_real_escape_string($con, $_GET['edit_emp']);
    $query = "SELECT * FROM emp_list WHERE id = '$id'";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $employee = mysqli_fetch_assoc($result);
    }
}

if (!$employee) {
    echo "<script>Swal.fire({title: 'Notification', text: 'Employee not found', icon: 'info'}).then(() => { window.location.href='index.php?emp_directory'; });</script>";
    exit;
}

// =============================================================
// FUNCTION: handleFileUpload()
// Handles single file upload, unlocks PDFs via qpdf if available.
// Returns uploaded filename or empty string on failure.
// =============================================================
/**
 * @param array $fileArray
 * @param string $targetDir
 * @return string
 */
if (!function_exists('handleFileUpload')) {
    function handleFileUpload(array $fileArray, string $targetDir = __DIR__ . "/../../uploads/"): string
    {
        if (isset($fileArray) && $fileArray['error'] == 0) {
            $file_name = $fileArray['name'];
            $tmp_name  = $fileArray['tmp_name'];
            $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name  = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_path = $targetDir . $new_name;
            if (move_uploaded_file($tmp_name, $target_path)) {
                // PDF Unlock Logic
                if ($ext === "pdf") {
                    $qpdf          = "C:/Program Files/qpdf/qpdf 12.3.2/bin/qpdf.exe";
                    $unlocked_file = $targetDir . "unlock_" . $new_name;
                    $command       = "\"$qpdf\" --decrypt \"$target_path\" \"$unlocked_file\" 2>&1";
                    exec($command, $output, $return_var);
                    if ($return_var === 0 && file_exists($unlocked_file)) {
                        unlink($target_path);
                        rename($unlocked_file, $target_path);
                    }
                }
                return $new_name;
            }
        }
        return '';
    }
}

// =============================================================
// FUNCTION: edit_user()
// Handles the full employee update process:
//   - Validates input (phone digits)
//   - Handles file uploads / replacements
//   - Optionally resets password (dynamic, admin-controlled)
//   - Updates employee in DB
//   - [OPTIONAL] Sends new-password email via PHPMailer (commented)
// Called when: $_POST['update'] is set
// =============================================================
/**
 * @param mysqli|mixed $con
 * @param array|mixed $employee
 */
function edit_user($con, $employee)
{
    // -- Show loading spinner while processing --
    echo '
    <div id="php_server_loader" style="width: 100%; min-height: 80vh; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; font-family: sans-serif;">
        <div style="width: 50px; height: 50px; border: 4px solid #f1f5f9; border-top: 4px solid #dd2127; border-radius: 50%; animation: spin 1s linear infinite;"></div>
        <h3 style="margin-top: 20px; color: #1e293b;">Updating Profile...</h3>
        <p style="color: #64748b; margin-top: 5px;">Please wait while we save the changes and upload new documents.</p>
        <style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
    </div>
    ';
    @ob_flush();
    @flush();

    // -- Sanitize fields --
    $id            = mysqli_real_escape_string($con, $_POST['id']);
    $name          = mysqli_real_escape_string($con, $_POST['name']);
    $email         = mysqli_real_escape_string($con, $_POST['email']);
    $company_email = mysqli_real_escape_string($con, $_POST['company_email'] ?? '');
    $contact       = preg_replace('/\D+/', '', $_POST['number']);

    // -- Validate phone (must be exactly 10 digits) --
    if (strlen($contact) != 10) {
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='background:#f1f5f9;'>";
        echo "<script>
            if(document.getElementById('php_server_loader')) document.getElementById('php_server_loader').style.display = 'none';
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Contact must be 10 digits!',
                    confirmButtonColor: '#dd2127'
                }).then((result) => {
                    window.history.back();
                });
            });
        </script></body></html>";
        exit;
    }

    // -- Sanitize remaining fields --
    $address     = mysqli_real_escape_string($con, $_POST['address']);
    $blood       = mysqli_real_escape_string($con, $_POST['blood']);
    $gender      = mysqli_real_escape_string($con, $_POST['gender']);
    $joinDate    = mysqli_real_escape_string($con, $_POST['joinDate']);
    $department  = mysqli_real_escape_string($con, $_POST['department']  ?? 'Not Assigned');
    $designation = mysqli_real_escape_string($con, $_POST['designation'] ?? 'Not Assigned');

    // -- Personal details --
    $age        = mysqli_real_escape_string($con, $_POST['age']              ?? '');
    $dob        = mysqli_real_escape_string($con, $_POST['dob']              ?? '');
    $work_exp   = mysqli_real_escape_string($con, $_POST['work_experience']  ?? '');
    $marital    = mysqli_real_escape_string($con, $_POST['marital_status']   ?? '');
    $dependents = mysqli_real_escape_string($con, $_POST['num_dependents']   ?? 0);

    // -- Emergency contact --
    $e_name = mysqli_real_escape_string($con, $_POST['emergency_name']         ?? '');
    $e_rel  = mysqli_real_escape_string($con, $_POST['emergency_relationship'] ?? '');
    $e_addr = mysqli_real_escape_string($con, $_POST['emergency_address']      ?? '');
    $e_phone = mysqli_real_escape_string($con, $_POST['emergency_phone']        ?? '');

    // -- Education & Employment JSON --
    $edu_json = mysqli_real_escape_string($con, $_POST['education_json']  ?? '[]');
    $emp_json = mysqli_real_escape_string($con, $_POST['employment_json'] ?? '[]');

    // -- Bank details --
    $acc_name = mysqli_real_escape_string($con, $_POST['account_name']      ?? '');
    $bank_br  = mysqli_real_escape_string($con, $_POST['bank_branch']       ?? '');
    $acc_num  = mysqli_real_escape_string($con, $_POST['account_number']    ?? '');
    $acc_ifsc = mysqli_real_escape_string($con, $_POST['account_type_ifsc'] ?? '');

    // -- Salary fields (only if admin has permission) --
    if (canAdminAccess('salary_update')) {
        $basic      = $_POST['basic_salary'] ?? 0;
        $hra        = $_POST['hra']          ?? 0;
        $allowance  = $_POST['allowance']    ?? 0;
        $deductions = $_POST['deductions']   ?? 0;
        $salary     = $_POST['salary']       ?? 0;
    }

    // -- Status (Active/Inactive toggle) --
    $status = isset($_POST['status']) ? 'Active' : 'Inactive';

    // -- Handle File Updates --
    $q_extra = "";
    $docs_to_check = [
        'employee_image'        => 'employee_image',
        'offer_latter'          => 'offer_latter',
        'NDA'                   => 'NDA',
        'Aadhar_card'           => 'Aadhar_card',
        'Pan_card'              => 'Pan_card',
        'Passportsize_photo'    => 'Passportsize_photo',
        'old_company_slary_slip' => 'old_company_slary_slip'
    ];

    foreach ($docs_to_check as $postKey => $dbCol) {
        if (!empty($_FILES[$postKey]['name'])) {
            $newFile = handleFileUpload($_FILES[$postKey]);
            if ($newFile) {
                // Delete old file if exists
                if (!empty($employee[$dbCol]) && file_exists("../../uploads/" . $employee[$dbCol])) {
                    @unlink("../../uploads/" . $employee[$dbCol]);
                }
                $q_extra .= ", $dbCol = '$newFile'";
            }
        }
    }

    // =============================================================
    // DYNAMIC PASSWORD RESET LOGIC
    // Admin can:
    //   (a) Type a new password in the edit form  → password is updated
    //   (b) Leave blank                           → password remains unchanged
    //   (c) Click 'Generate New Password'         → auto-generate new password
    // =============================================================
    $passwordResetMsg = '';
    $newPassword      = trim($_POST['edit_emp_password'] ?? '');
    if (!empty($newPassword)) {
        $safeNewPassword = mysqli_real_escape_string($con, $newPassword);
        $q_extra .= ", password = '$safeNewPassword'";
        $passwordResetMsg = $newPassword; // Store for success message display
    }

    // -- Build UPDATE query --
    $query = "UPDATE emp_list SET
              name = '$name', phone_number = '$contact', address = '$address', email = '$email', company_email = '$company_email', blood_group = '$blood', gender = '$gender', join_date = '$joinDate',
              department = '$department', designation = '$designation',
              age = '$age', dob = '$dob', work_experience = '$work_exp', marital_status = '$marital', num_dependents = '$dependents',
              emergency_name = '$e_name', emergency_relationship = '$e_rel', emergency_address = '$e_addr', emergency_phone = '$e_phone',
              education_json = '$edu_json', employment_json = '$emp_json',
              account_name = '$acc_name', bank_branch = '$bank_br', account_number = '$acc_num', account_type_ifsc = '$acc_ifsc',
              status = '$status'";

    // -- Append salary fields if admin has permission --
    if (canAdminAccess('salary_update')) {
        $query .= ", basic_salary = '$basic', hra = '$hra', allowance = '$allowance', deductions = '$deductions', salary = '$salary' ";
    }

    $query .= " $q_extra WHERE id = '$id'";

    $result = mysqli_query($con, $query);
    if ($result) {
        // -- Handle extra documents upload --
        if (!empty($_FILES['documents']['name'][0])) {
            foreach ($_FILES['documents']['name'] as $key => $doc_name) {
                if ($_FILES['documents']['error'][$key] == 0) {
                    $new_name = handleFileUpload(['name' => $doc_name, 'tmp_name' => $_FILES['documents']['tmp_name'][$key], 'error' => 0]);
                    if ($new_name) {
                        mysqli_query($con, "INSERT INTO employee_documents (emp_id, file_name) VALUES ('$id', '$new_name')");
                    }
                }
            }
        }

        // =============================================================
        // PHPMailer - Send New Password Notification to Employee
        // STATUS: FULLY COMMENTED OUT
        // To enable: uncomment the PHPMailer includes at the top AND
        // uncomment this entire block.
        // =============================================================
        //
        // if (!empty($passwordResetMsg)) { // Only send email if password was changed
        //     $mail = new PHPMailer(true);
        //     try {
        //         // SMTP Server Settings
        //         $mail->isSMTP();
        //         $mail->Host       = 'smtp.gmail.com';            // SMTP host
        //         $mail->SMTPAuth   = true;                        // Enable SMTP auth
        //         $mail->Username   = 'madhavanpatel19@gmail.com'; // SMTP username (Gmail)
        //         $mail->Password   = 'yawi nqpw wbhp icrx';       // Gmail App Password
        //         $mail->SMTPSecure = 'tls';                       // Encryption: tls | ssl
        //         $mail->Port       = 587;                         // SMTP port
        //
        //         // Sender & Recipient
        //         $mail->setFrom('madhavanpatel19@gmail.com', 'Cadlete HR');
        //         $mail->addAddress($email);
        //
        //         // Email Content
        //         $mail->isHTML(true);
        //         $mail->Subject = 'Your Cadlete Login Password Has Been Updated';
        //         $mail->Body    = "
        //             <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #eee;border-radius:10px;'>
        //                 <h2 style='color:#dd2127;'>Password Updated</h2>
        //                 <p>Dear <strong>$name</strong>,</p>
        //                 <p>Your employee portal password has been updated by an administrator.</p>
        //                 <table style='background:#f8fafc;padding:15px;border-radius:8px;width:100%;'>
        //                     <tr><td><strong>Email:</strong></td><td>$email</td></tr>
        //                     <tr><td><strong>New Password:</strong></td><td><strong>$passwordResetMsg</strong></td></tr>
        //                 </table>
        //                 <p style='margin-top:15px;'>Please login and change your password at your earliest convenience.</p>
        //                 <p style='color:#64748b;font-size:12px;'>This is an automated message. Do not reply.</p>
        //             </div>
        //         ";
        //         $mail->send();
        //     } catch (Exception $e) {
        //         // Email failed silently – profile is still updated
        //         // Log: $e->getMessage()
        //     }
        // }
        // =============================================================

        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='background:#f1f5f9;'>";
        echo "<script>
            if(document.getElementById('php_server_loader')) document.getElementById('php_server_loader').style.display = 'none';
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Profile Updated Successfully',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.href = 'index.php?emp_directory';
                });
            });
        </script></body></html>";
    } else {
        $dbError = addslashes(mysqli_error($con));
        echo "<!DOCTYPE html><html><head><script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script></head><body style='background:#f1f5f9;'>";
        echo "<script>
            if(document.getElementById('php_server_loader')) document.getElementById('php_server_loader').style.display = 'none';
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error updating',
                    text: '{$dbError}',
                    confirmButtonColor: '#dd2127'
                }).then(() => {
                    window.history.back();
                });
            });
        </script></body></html>";
    }
} // end edit_user()

// -- Trigger edit_user() on form submit --
if (isset($_POST['update'])) {
    edit_user($con, $employee);
}
?>


<?php
// Fetch distinct departments & designations for dropdowns (only assigned & user added)
$existing_depts = [];
$dept_q = mysqli_query($con, "SELECT DISTINCT department FROM emp_list WHERE department IS NOT NULL AND TRIM(department) != '' AND LOWER(TRIM(department)) != 'not assigned'");
if ($dept_q) {
    while ($dr = mysqli_fetch_assoc($dept_q)) {
        $dv = trim($dr['department']);
        if ($dv && !in_array($dv, $existing_depts)) {
            $existing_depts[] = $dv;
        }
    }
}
$admin_dept_q = @mysqli_query($con, "SELECT DISTINCT department FROM admins WHERE department IS NOT NULL AND TRIM(department) != '' AND LOWER(TRIM(department)) != 'not assigned'");
if ($admin_dept_q) {
    while ($adr = mysqli_fetch_assoc($admin_dept_q)) {
        $adv = trim($adr['department']);
        if ($adv && !in_array($adv, $existing_depts)) {
            $existing_depts[] = $adv;
        }
    }
}
sort($existing_depts);

$existing_desigs = [];
$desig_q = mysqli_query($con, "SELECT DISTINCT designation FROM emp_list WHERE designation IS NOT NULL AND TRIM(designation) != '' AND LOWER(TRIM(designation)) != 'not assigned'");
if ($desig_q) {
    while ($dsr = mysqli_fetch_assoc($desig_q)) {
        $dsv = trim($dsr['designation']);
        if ($dsv && !in_array($dsv, $existing_desigs)) {
            $existing_desigs[] = $dsv;
        }
    }
}
$admin_job_q = mysqli_query($con, "SELECT DISTINCT admin_job FROM admins WHERE admin_job IS NOT NULL AND TRIM(admin_job) != '' AND LOWER(TRIM(admin_job)) != 'not assigned'");
if ($admin_job_q) {
    while ($ajr = mysqli_fetch_assoc($admin_job_q)) {
        $ajv = trim($ajr['admin_job']);
        if ($ajv && !in_array($ajv, $existing_desigs)) {
            $existing_desigs[] = $ajv;
        }
    }
}
sort($existing_desigs);
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <div class="header-actions-premium">
            <a href="index.php?emp_directory" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Directory
            </a>
        </div>
    </div>

    <form method="POST" id="edit_employee_form" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">

        <!-- 1. Personal Information -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">1</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Personal Information</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Basic details and identity</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <!-- Identity & Photo Section -->
                <div class="row align-items-center" style="margin-bottom: 25px;">
                    <div class="col-md-3">
                        <div class="form-group text-center" style="margin-bottom: 0;">
                            <div style="position: relative; display: inline-block;">
                                <img id="edit_preview" src="uploads/<?php echo !empty($employee['employee_image']) ? $employee['employee_image'] : '../admin_images/default.png'; ?>" style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                <label for="employee_image" style="position: absolute; bottom: 5px; right: 5px; background: #333; color: #fff; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #fff; transition: 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                    <i class="fa fa-pencil" style="font-size: 16px;"></i>
                                </label>
                                <input type="file" name="employee_image" id="employee_image" style="display: none;" accept="image/*" onchange="handleImagePreview(this, 'edit_preview')">
                            </div>
                            <small style="color: #64748b; margin-top: 12px; display: block; font-weight: 600;">Edit Profile Photo</small>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Full Name *</label>
                                    <input type="text" name="name" class="p-input-premium" value="<?php echo htmlspecialchars($employee['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Gender *</label>
                                    <select name="gender" class="p-input-premium" required>
                                        <option <?php if ($employee['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                        <option <?php if ($employee['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                                        <option <?php if ($employee['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Blood Group *</label>
                                    <select name="blood" class="p-input-premium" required>
                                        <?php $bgs = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                        foreach ($bgs as $bg) echo "<option " . ($employee['blood_group'] == $bg ? 'selected' : '') . ">$bg</option>"; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-top: 12px;">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Date of Birth *</label>
                                    <input type="date" id="edit_dob" name="dob" class="p-input-premium" value="<?php echo $employee['dob']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Age (Auto)</label>
                                    <input type="number" name="age" id="edit_age" class="p-input-premium" value="<?php echo $employee['age']; ?>" readonly style="background: #f8fafc; cursor: not-allowed;">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Marital Status</label>
                                    <div class="p-radio-group" style="height: 48px; align-items: center;">
                                        <label class="p-radio-item" style="margin-bottom: 0;">
                                            <input type="radio" name="marital_status" value="Single" <?php if ($employee['marital_status'] == 'Single') echo 'checked'; ?>> Single
                                        </label>
                                        <label class="p-radio-item" style="margin-bottom: 0;">
                                            <input type="radio" name="marital_status" value="Married" <?php if ($employee['marital_status'] == 'Married') echo 'checked'; ?>> Married
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Dependent(s)</label>
                                    <input type="number" name="num_dependents" class="p-input-premium" value="<?php echo $employee['num_dependents']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 2. Contact Information -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">2</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Contact Information</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">How to reach the employee</p>
                </div>
            </div>
            <div style="padding: 30px;">
                <div class="row" style="margin-bottom: 10px;">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Contact Number *</label>
                            <div style="position: relative;">
                                <i class="fa fa-phone" style="position: absolute; left: 15px; top: 16px; color: #64748b; font-size: 14px;"></i>
                                <input type="tel" name="number" class="p-input-premium" value="<?php echo htmlspecialchars($employee['phone_number']); ?>" maxlength="10" required style="padding-left: 40px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Company Email (For Login) *</label>
                            <div style="position: relative;">
                                <i class="fa fa-building" style="position: absolute; left: 15px; top: 16px; color: #dd2127; font-size: 14px;"></i>
                                <input type="email" name="company_email" class="p-input-premium" value="<?php echo htmlspecialchars($employee['company_email'] ?? ''); ?>" placeholder="work@company.com" required style="padding-left: 40px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Personal Email Address *</label>
                            <div style="position: relative;">
                                <i class="fa fa-envelope" style="position: absolute; left: 15px; top: 16px; color: #64748b; font-size: 14px;"></i>
                                <input type="email" name="email" class="p-input-premium" value="<?php echo htmlspecialchars($employee['email']); ?>" required style="padding-left: 40px;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Residential Address *</label>
                            <div style="position: relative;">
                                <i class="fa fa-map-marker" style="position: absolute; left: 15px; top: 16px; color: #64748b; font-size: 14px;"></i>
                                <input type="text" name="address" class="p-input-premium" value="<?php echo htmlspecialchars($employee['address']); ?>" required style="padding-left: 40px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Password Reset Row -->
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">
                                <i class="fa fa-key" style="color:#dd2127;"></i> Login Password
                                <span style="font-weight:400; color:#64748b; font-size:12px; margin-left:8px;">(Current password shown — edit to change, or click Generate New Password)</span>
                            </label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <div style="position: relative; flex: 1;">
                                    <i class="fa fa-lock" style="position: absolute; left: 15px; top: 16px; color: #64748b; font-size: 14px;"></i>
                                    <input type="text" name="edit_emp_password" id="edit_emp_password_field"
                                        class="p-input-premium"
                                        value="<?php echo htmlspecialchars($employee['password'] ?? ''); ?>"
                                        placeholder="Current password"
                                        style="padding-left: 40px; font-family: monospace; letter-spacing: 1px;">
                                </div>
                                <button type="button" onclick="generateEditPassword()"
                                    style="white-space:nowrap; background: linear-gradient(135deg,#dd2127,#ff6b6b); color:#fff; border:none; border-radius:8px; padding:12px 20px; font-weight:600; cursor:pointer; font-size:13px; transition:0.3s;">
                                    <i class="fa fa-refresh"></i> Generate New Password
                                </button>
                                <button type="button" onclick="toggleEditPassword()"
                                    style="background:#f1f5f9; color:#475569; border:1.5px solid #e2e8f0; border-radius:8px; padding:12px 16px; cursor:pointer; font-size:13px;" title="Show/Hide Password">
                                    <i class="fa fa-eye" id="edit_pass_eye_icon"></i>
                                </button>
                            </div>
                            <small style="color:#64748b; margin-top:6px; display:block; font-weight:500;">
                                <i class="fa fa-info-circle"></i>
                                Password can only be changed by admin from this page. Employee cannot reset it themselves.
                            </small>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 3. Employee Documents -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">3</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Employee Documents</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Upload important files</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <div class="row">
                    <?php
                    $docs = [
                        'Offer Letter' => 'offer_latter',
                        'Aadhar Card' => 'Aadhar_card',
                        'PAN Card' => 'Pan_card',
                        'NDA Document' => 'NDA',
                        'Passport Photo' => 'Passportsize_photo',
                        'Salary Slip' => 'old_company_slary_slip'
                    ];
                    foreach ($docs as $lbl => $fld): ?>
                        <div class="col-md-4" style="margin-bottom: 15px;">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;"><?php echo $lbl; ?></label>
                                <input type="file" name="<?php echo $fld; ?>" class="p-input-premium">
                                <?php
                                $val = !empty($employee[$fld]) ? trim($employee[$fld]) : '';
                                $hasFile = (!empty($val) && strtolower($val) !== 'not assigned' && strtolower($val) !== 'not uploaded' && strtolower($val) !== 'null');
                                if ($hasFile):
                                    $fileUrl = (strpos($val, 'uploads/') === 0) ? $val : 'uploads/' . $val;
                                ?>
                                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; width: fit-content;">
                                        <i class="fa fa-check-circle" style="color: #059669;"></i>
                                        <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" style="font-size: 12px; color: #333; font-weight: 600; text-decoration: none;">View Current</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>

        <!-- 4. Emergency Contact Details -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">4</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Emergency Contact Details</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Who to call in an emergency</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Full Name</label>
                            <input type="text" name="emergency_name" class="p-input-premium" value="<?php echo htmlspecialchars($employee['emergency_name']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Relationship</label>
                            <input type="text" name="emergency_relationship" class="p-input-premium" value="<?php echo htmlspecialchars($employee['emergency_relationship']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Contact Phone</label>
                            <input type="tel" name="emergency_phone" class="p-input-premium" value="<?php echo htmlspecialchars($employee['emergency_phone']); ?>" maxlength="10">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Address</label>
                            <input type="text" name="emergency_address" class="p-input-premium" value="<?php echo htmlspecialchars($employee['emergency_address']); ?>">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 5. Educational Background -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">5</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Educational Background</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Academic history</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <div class="table-premium" style="overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 20px;">
                    <table class="table" id="edu_table" style="margin-bottom: 0; width: 100%; min-width: 750px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="border: none; white-space: nowrap; min-width: 160px;">Degree/Course</th>
                                <th style="border: none; white-space: nowrap; min-width: 180px;">University/Institute</th>
                                <th style="border: none; white-space: nowrap; min-width: 100px;">Year</th>
                                <th style="border: none; white-space: nowrap; min-width: 100px;">Grade</th>
                                <th style="border: none; white-space: nowrap; min-width: 120px;">City</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $edu_data = json_decode($employee['education_json'], true) ?: [];
                            for ($i = 0; $i < max(2, count($edu_data)); $i++):
                                $row = $edu_data[$i] ?? []; ?>
                                <tr>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium edu-degree" value="<?php echo htmlspecialchars($row['degree'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium edu-univ" value="<?php echo htmlspecialchars($row['univ'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium edu-year" value="<?php echo htmlspecialchars($row['year'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium edu-grade" value="<?php echo htmlspecialchars($row['grade'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium edu-city" value="<?php echo htmlspecialchars($row['city'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <input type="hidden" name="education_json" id="education_json">

            </div>
        </div>

        <!-- 6. Employment History -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">6</div>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Employment History</h3>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Previous work experience</p>
                    </div>
                </div>
                <button type="button" id="edit_add_employment_row" class="btn-premium-add" style="background: #f1f5f9 !important; color: #475569 !important; border: 1.5px solid #e2e8f0 !important; box-shadow: none !important; padding: 10px 22px !important; font-size: 14px !important;">
                    <i class="fa fa-plus"></i> Add Row
                </button>
            </div>
            <div style="padding: 30px;">

                <div class="table-premium" style="overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px;">
                    <table class="table" id="emp_hist_table" style="margin-bottom: 0; width: 100%; min-width: 780px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="border: none; white-space: nowrap; min-width: 170px;">Company Name</th>
                                <th style="border: none; white-space: nowrap; min-width: 150px;">Position</th>
                                <th style="border: none; white-space: nowrap; min-width: 120px;">Duration/Year</th>
                                <th style="border: none; white-space: nowrap; min-width: 170px;">Reason for Leaving</th>
                                <th style="border: none; width: 70px; min-width: 70px; text-align: center; white-space: nowrap;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="employment_body">
                            <?php
                            $hist_data = json_decode($employee['employment_json'], true) ?: [];
                            $hist_rows = max(2, count($hist_data));
                            for ($i = 0; $i < $hist_rows; $i++):
                                $row = $hist_data[$i] ?? [];
                                $pos_val = $row['pos'] ?? $row['position'] ?? '';
                            ?>
                                <tr>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium hist-company" value="<?php echo htmlspecialchars($row['company'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium hist-pos" value="<?php echo htmlspecialchars($pos_val); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium hist-year" value="<?php echo htmlspecialchars($row['year'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="padding: 8px;"><input type="text" class="p-input-premium hist-reason" value="<?php echo htmlspecialchars($row['reason'] ?? ''); ?>" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>
                                    <td style="text-align: center; vertical-align: middle; padding: 6px; width: 70px; min-width: 70px;">
                                        <button type="button" class="employment-remove-row btn btn-danger btn-sm" title="Remove row" style="width: 32px; height: 32px; line-height: 1; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 16px; margin: 0 auto;">×</button>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="employment_json" id="employment_json">

            </div>
        </div>

        <!-- 7. Bank Account Details -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">7</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Bank Account Details</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Financial information</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Account Holder Name</label>
                            <input type="text" name="account_name" class="p-input-premium" value="<?php echo htmlspecialchars($employee['account_name']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Bank & Branch</label>
                            <input type="text" name="bank_branch" class="p-input-premium" value="<?php echo htmlspecialchars($employee['bank_branch']); ?>">
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Account Number</label>
                            <input type="text" name="account_number" class="p-input-premium" value="<?php echo htmlspecialchars($employee['account_number']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">IFSC Code / Account Type</label>
                            <input type="text" name="account_type_ifsc" class="p-input-premium" value="<?php echo htmlspecialchars($employee['account_type_ifsc']); ?>">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- 8. Professional & Salary Details -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">8</div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Professional & Salary Details</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Compensation structure</p>
                </div>
            </div>
            <div style="padding: 30px;">

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label style="font-weight: 600; color: #475569; margin: 0;">Designation *</label>
                                <button type="button" class="btn btn-sm btn-success" style="padding: 2px 10px; font-size: 11px; border-radius: 6px; font-weight: 700; background: #059669; border: none; cursor: pointer;" onclick="addNewDesignation()"><i class="fa fa-plus"></i> New</button>
                            </div>
                            <?php
                            $cur_desig = trim($employee['designation'] ?? '');
                            if ($cur_desig && !in_array($cur_desig, $existing_desigs)) {
                                $existing_desigs[] = $cur_desig;
                                sort($existing_desigs);
                            }
                            ?>
                            <select name="designation" id="emp_designation" class="p-input-premium" required>
                                <option value="">-- Select Designation --</option>
                                <?php foreach ($existing_desigs as $desig): ?>
                                    <option value="<?php echo htmlspecialchars($desig); ?>" <?php echo (strcasecmp($cur_desig, $desig) === 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($desig); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label style="font-weight: 600; color: #475569; margin: 0;">Department *</label>
                                <button type="button" class="btn btn-sm btn-success" style="padding: 2px 10px; font-size: 11px; border-radius: 6px; font-weight: 700; background: #059669; border: none; cursor: pointer;" onclick="addNewDepartment()"><i class="fa fa-plus"></i> New</button>
                            </div>
                            <?php
                            $cur_dept = trim($employee['department'] ?? '');
                            if ($cur_dept && !in_array($cur_dept, $existing_depts)) {
                                $existing_depts[] = $cur_dept;
                                sort($existing_depts);
                            }
                            ?>
                            <select name="department" id="emp_department" class="p-input-premium" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach ($existing_depts as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (strcasecmp($cur_dept, $dept) === 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Joining Date *</label>
                            <input type="date" name="joinDate" class="p-input-premium" value="<?php echo $employee['join_date']; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <?php
                    $can_update_salary = canAdminAccess('salary_update');
                    $salary_readonly = $can_update_salary ? '' : 'readonly style="background: #f1f5f9;"';
                    ?>
                    <?php if (canAdminAccess('salary_view') || $can_update_salary): ?>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Basic Pay <?php echo $can_update_salary ? '*' : ''; ?></label>
                                <input type="number" id="edit_basic_salary" name="basic_salary" class="p-input-premium" value="<?php echo $employee['basic_salary']; ?>" <?php echo $can_update_salary ? 'required' : ''; ?> <?php echo $salary_readonly; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">HRA</label>
                                <input type="number" id="edit_hra" name="hra" class="p-input-premium" value="<?php echo $employee['hra']; ?>" <?php echo $salary_readonly; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Other Allowance</label>
                                <input type="number" id="edit_allowance" name="allowance" class="p-input-premium" value="<?php echo $employee['allowance']; ?>" <?php echo $salary_readonly; ?>>
                            </div>
                        </div>
                </div>

                <div class="row" style="margin-top: 15px;">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Monthly Deductions</label>
                            <input type="number" id="edit_deductions" name="deductions" class="p-input-premium" value="<?php echo $employee['deductions']; ?>" <?php echo $salary_readonly; ?>>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Net Monthly Salary</label>
                            <input type="text" id="edit_salary" name="salary" class="p-input-premium" value="<?php echo $employee['salary']; ?>" readonly style="background: #f0fdf4; font-weight: 800; color: #059669; font-size: 18px; border-color: #bbf7d0;">
                        </div>
                    </div>
                <?php endif; ?>
                </div>

                <style>
                    .status-switch {
                        position: relative;
                        display: inline-block;
                        width: 48px;
                        height: 26px;
                        margin-bottom: 0;
                    }

                    .status-switch input {
                        opacity: 0;
                        width: 0;
                        height: 0;
                    }

                    .status-slider {
                        position: absolute;
                        cursor: pointer;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background-color: #cbd5e1;
                        transition: .4s;
                        border-radius: 34px;
                    }

                    .status-slider:before {
                        position: absolute;
                        content: "";
                        height: 20px;
                        width: 20px;
                        left: 3px;
                        bottom: 3px;
                        background-color: white;
                        transition: .4s;
                        border-radius: 50%;
                    }

                    .status-switch input:checked+.status-slider {
                        background-color: #2563eb;
                    }

                    .status-switch input:checked+.status-slider:before {
                        transform: translateX(22px);
                    }
                </style>
                <?php $is_active = (!isset($employee['status']) || $employee['status'] !== 'Inactive'); ?>
                <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 20px;">
                    <div style="flex-shrink: 0;">
                        <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Status <span style="color: red;">*</span></label>
                        <label class="status-switch">
                            <input type="checkbox" name="status" id="emp_status_toggle" value="Active" <?php echo $is_active ? 'checked' : ''; ?>>
                            <span class="status-slider"></span>
                        </label>
                    </div>
                    <div style="margin-top: 25px;">
                        <div style="font-weight: 700; color: #1e293b; font-size: 15px;"><span id="status_label_text"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span> <span style="color: #94a3b8; font-weight: 400; margin-left: 8px;">| Inactive employees will not be able to access the system.</span></div>
                    </div>
                </div>
                <script>
                    document.getElementById('emp_status_toggle').addEventListener('change', function() {
                        document.getElementById('status_label_text').innerText = this.checked ? 'Active' : 'Inactive';
                        document.getElementById('status_label_text').style.color = this.checked ? '#1e293b' : '#64748b';
                    });
                </script>

            </div>
        </div>

        <div style="margin: 0 30px 40px 30px; text-align: right;">
            <button type="submit" name="update" class="btn-premium-add" style="padding: 14px 40px !important; font-size: 16px !important; background: #dd2127 !important; border: none; box-shadow: 0 4px 6px -1px rgba(223, 33, 39, 0.3);" onclick="serializeTables()">
                <i class="fa fa-save"></i> Update Employee Profile
            </button>
        </div>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function addNewDepartment() {
        Swal.fire({
            title: 'Add New Department',
            input: 'text',
            inputPlaceholder: 'e.g. Quality Assurance',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            confirmButtonText: '<i class="fa fa-plus"></i> Add Department',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Please enter a department name!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const newDept = result.value.trim();
                const select = document.getElementById('emp_department');
                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === newDept.toLowerCase()) {
                        select.selectedIndex = i;
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    const opt = new Option(newDept, newDept, true, true);
                    select.add(opt);
                }
            }
        });
    }

    function addNewDesignation() {
        Swal.fire({
            title: 'Add New Designation',
            input: 'text',
            inputPlaceholder: 'e.g. Senior Tech Lead',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            confirmButtonText: '<i class="fa fa-plus"></i> Add Designation',
            cancelButtonText: 'Cancel',
            inputValidator: (value) => {
                if (!value || !value.trim()) {
                    return 'Please enter a designation name!';
                }
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                const newDesig = result.value.trim();
                const select = document.getElementById('emp_designation');
                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === newDesig.toLowerCase()) {
                        select.selectedIndex = i;
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    const opt = new Option(newDesig, newDesig, true, true);
                    select.add(opt);
                }
            }
        });
    }

    function handleImagePreview(input, previewId) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(previewId).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function autoCalculateAge(dobValue, ageInputId) {
        if (!dobValue || dobValue === '0000-00-00') return;
        const birthDate = new Date(dobValue);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        document.getElementById(ageInputId).value = age > 0 ? age : 0;
    }

    function serializeTables() {
        const edu = [];
        document.querySelectorAll('#edu_table tbody tr').forEach(tr => {
            const row = {
                degree: tr.querySelector('.edu-degree').value,
                univ: tr.querySelector('.edu-univ').value,
                year: tr.querySelector('.edu-year').value,
                grade: tr.querySelector('.edu-grade').value,
                city: tr.querySelector('.edu-city').value
            };
            if (row.degree || row.univ) edu.push(row);
        });
        document.getElementById('education_json').value = JSON.stringify(edu);

        const hist = [];
        document.querySelectorAll('#emp_hist_table tbody tr').forEach(tr => {
            const company = (tr.querySelector('.hist-company')?.value || '').trim();
            const pos = (tr.querySelector('.hist-pos')?.value || '').trim();
            const year = (tr.querySelector('.hist-year')?.value || '').trim();
            const reason = (tr.querySelector('.hist-reason')?.value || '').trim();
            if (company || pos || year || reason) {
                hist.push({
                    company,
                    pos,
                    year,
                    reason
                });
            }
        });
        document.getElementById('employment_json').value = JSON.stringify(hist);
    }

    function employmentHistoryRowHtml() {
        return '<td style="padding: 8px;"><input type="text" class="p-input-premium hist-company" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>' +
            '<td style="padding: 8px;"><input type="text" class="p-input-premium hist-pos" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>' +
            '<td style="padding: 8px;"><input type="text" class="p-input-premium hist-year" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>' +
            '<td style="padding: 8px;"><input type="text" class="p-input-premium hist-reason" style="height: 38px; font-size: 13px; width: 100%; box-sizing: border-box;"></td>' +
            '<td style="text-align: center; vertical-align: middle; padding: 8px;">' +
            '<button type="button" class="employment-remove-row btn btn-danger btn-sm" title="Remove row" style="min-width: 32px; border-radius: 8px; padding: 4px 8px;">×</button></td>';
    }

    function addEmploymentRowEdit() {
        const tbody = document.getElementById('employment_body');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.innerHTML = employmentHistoryRowHtml();
        tbody.appendChild(tr);
    }

    document.getElementById('edit_add_employment_row')?.addEventListener('click', addEmploymentRowEdit);

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.employment-remove-row');
        if (!btn || !document.getElementById('employment_body')?.contains(btn)) return;
        const tr = btn.closest('tr');
        if (tr) tr.remove();
    });

    document.getElementById('edit_employee_form')?.addEventListener('submit', function() {
        serializeTables();

        // Ensure SweetAlert is loaded, or fallback to native if not available
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Updating Profile...',
                text: 'Please wait while we save the changes and upload new documents.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    });

    document.getElementById('edit_dob').addEventListener('change', function() {
        autoCalculateAge(this.value, 'edit_age');
    });

    function calculateTotalEdit() {
        let basic = parseFloat(document.getElementById('edit_basic_salary').value) || 0;
        let hra = parseFloat(document.getElementById('edit_hra').value) || 0;
        let allowance = parseFloat(document.getElementById('edit_allowance').value) || 0;
        let deduction = parseFloat(document.getElementById('edit_deductions').value) || 0;
        document.getElementById('edit_salary').value = (basic + hra + allowance - deduction).toFixed(2);
    }

    ['edit_basic_salary', 'edit_hra', 'edit_allowance', 'edit_deductions'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', calculateTotalEdit);
    });

    // =============================================================
    // Dynamic Password: Generate New & Toggle Visibility (Edit Form)
    // =============================================================

    /**
     * generateEditPassword()
     * Fills the password reset input with a random 10-character strong password.
     * Admin clicks "Generate New Password" to create and populate.
     */
    function generateEditPassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        let pwd = '';
        for (let i = 0; i < 10; i++) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        const input = document.getElementById('edit_emp_password_field');
        if (input) {
            input.value = pwd;
            input.type = 'text'; // Show the generated password
            const icon = document.getElementById('edit_pass_eye_icon');
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    /**
     * toggleEditPassword()
     * Toggles the edit password field between visible text and hidden password.
     */
    function toggleEditPassword() {
        const input = document.getElementById('edit_emp_password_field');
        const icon = document.getElementById('edit_pass_eye_icon');
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        } else {
            input.type = 'password';
            if (icon) {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }

    /**
     * Dynamic Department & Designation / Function Creation
     */
    function addNewDepartment(selectId = 'emp_department') {
        const select = document.getElementById(selectId);
        if (!select) return;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Add New Department',
                input: 'text',
                inputLabel: 'Enter Department Name',
                inputPlaceholder: 'e.g. Quality Assurance',
                showCancelButton: true,
                confirmButtonText: 'Add Department',
                confirmButtonColor: '#dd2127',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Please enter a department name!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const newDept = result.value.trim();
                    let exists = false;
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value.toLowerCase() === newDept.toLowerCase()) {
                            select.selectedIndex = i;
                            exists = true;
                            break;
                        }
                    }
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = newDept;
                        opt.textContent = newDept;
                        opt.selected = true;
                        select.appendChild(opt);
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Department Added',
                        text: `"${newDept}" has been added and selected.`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        } else {
            const newDept = prompt('Enter New Department Name:');
            if (newDept && newDept.trim()) {
                const val = newDept.trim();
                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === val.toLowerCase()) {
                        select.selectedIndex = i;
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    opt.selected = true;
                    select.appendChild(opt);
                }
            }
        }
    }

    function addNewDesignation(selectId = 'emp_designation') {
        const select = document.getElementById(selectId);
        if (!select) return;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Add New Function / Designation',
                input: 'text',
                inputLabel: 'Enter Function / Designation Name',
                inputPlaceholder: 'e.g. Lead Architect',
                showCancelButton: true,
                confirmButtonText: 'Add Designation',
                confirmButtonColor: '#dd2127',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'Please enter a designation / function name!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const newDesig = result.value.trim();
                    let exists = false;
                    for (let i = 0; i < select.options.length; i++) {
                        if (select.options[i].value.toLowerCase() === newDesig.toLowerCase()) {
                            select.selectedIndex = i;
                            exists = true;
                            break;
                        }
                    }
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = newDesig;
                        opt.textContent = newDesig;
                        opt.selected = true;
                        select.appendChild(opt);
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Function / Designation Added',
                        text: `"${newDesig}" has been added and selected.`,
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        } else {
            const newDesig = prompt('Enter New Function / Designation Name:');
            if (newDesig && newDesig.trim()) {
                const val = newDesig.trim();
                let exists = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value.toLowerCase() === val.toLowerCase()) {
                        select.selectedIndex = i;
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    opt.selected = true;
                    select.appendChild(opt);
                }
            }
        }
    }
</script>