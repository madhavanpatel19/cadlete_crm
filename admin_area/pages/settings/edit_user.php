<?php
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit;
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('isSuperAdmin')) {
    include __DIR__ . '/includes/admin_permissions.php';
}
if (!function_exists('getUsedAdminPermissions')) {
    require_once __DIR__ . '/../../../settings/permissions/permissions.php';
}
if (!isset($_GET['edit_user'])) {
    header('Location: ../../index.php?view_users');
    exit;
}
// Auto-check and add department column to admins table if missing
$check_dept_col = @mysqli_query($con, "SHOW COLUMNS FROM admins LIKE 'department'");
if ($check_dept_col && mysqli_num_rows($check_dept_col) == 0) {
    @mysqli_query($con, "ALTER TABLE admins ADD COLUMN department VARCHAR(255) DEFAULT 'Management'");
}

$edit_id = (int)$_GET['edit_user'];
$run_admin = mysqli_query($con, "SELECT * FROM admins WHERE admin_id=" . $edit_id . " LIMIT 1");
if (!$run_admin || mysqli_num_rows($run_admin) == 0) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    exit;
}
$row_admin = mysqli_fetch_assoc($run_admin);
$admin_id = $row_admin['admin_id'];
$admin_name = $row_admin['admin_name'];
$admin_email = $row_admin['admin_email'];
$admin_pass = $row_admin['admin_pass'];
$admin_image = $row_admin['admin_image'];
$new_admin_image = $row_admin['admin_image'];
$admin_country = $row_admin['admin_country'];
$admin_job = $row_admin['admin_job'];
$admin_dept = isset($row_admin['department']) && trim($row_admin['department']) !== '' ? trim($row_admin['department']) : 'Management';
$saved_depts = array_filter(array_map('trim', explode(',', $admin_dept)));
$admin_contact = $row_admin['admin_contact'];
$admin_about = $row_admin['admin_about'];
$current_super = isset($row_admin['is_super_admin']) ? (int)$row_admin['is_super_admin'] : 0;
$perms_raw = isset($row_admin['permissions']) ? trim((string)$row_admin['permissions']) : '';
$current_perms = [];
if ($perms_raw !== '') {
    $current_perms = array_values(array_filter(array_map('trim', explode(',', $perms_raw)), function ($p) {
        return $p !== '';
    }));
}

if (isset($_POST['update'])) {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = $_POST['admin_pass'];
    $admin_country = $_POST['admin_country'];
    $admin_job = $_POST['admin_job'];
    $admin_dept = '';
    if (isset($_POST['department'])) {
        if (is_array($_POST['department'])) {
            $admin_dept = implode(', ', array_filter(array_map('trim', $_POST['department'])));
        } else {
            $admin_dept = trim($_POST['department']);
        }
    }
    if (empty($admin_dept)) {
        $admin_dept = 'Management';
    }
    $admin_contact = $_POST['admin_contact'];
    $admin_about = $_POST['admin_about'];
    $admin_image = $_FILES['admin_image']['name'];
    $temp_admin_image = $_FILES['admin_image']['tmp_name'];
    if (!empty($admin_image)) {
        move_uploaded_file($temp_admin_image, "admin_images/$admin_image");
    } else {
        $admin_image = $new_admin_image;
    }
    $permissions = '';
    if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
        $permissions = implode(',', array_map(function ($p) use ($con) {
            return mysqli_real_escape_string($con, $p);
        }, $_POST['permissions']));
    }
    $is_super = (isset($_POST['is_super_admin']) && $_POST['is_super_admin'] == '1') ? 1 : 0;
    $perm_esc = mysqli_real_escape_string($con, $permissions);
    $name_esc = mysqli_real_escape_string($con, $admin_name);
    $email_esc = mysqli_real_escape_string($con, $admin_email);
    $pass_esc = mysqli_real_escape_string($con, $admin_pass);
    $img_esc = mysqli_real_escape_string($con, $admin_image);
    $contact_esc = mysqli_real_escape_string($con, $admin_contact);
    $country_esc = mysqli_real_escape_string($con, $admin_country);
    $job_esc = mysqli_real_escape_string($con, $admin_job);
    $dept_esc = mysqli_real_escape_string($con, $admin_dept);
    $about_esc = mysqli_real_escape_string($con, $admin_about);
    $update_admin = "UPDATE admins SET admin_name='$name_esc', admin_email='$email_esc', admin_pass='$pass_esc', admin_image='$img_esc', admin_contact='$contact_esc', admin_country='$country_esc', admin_job='$job_esc', department='$dept_esc', admin_about='$about_esc', permissions='$perm_esc', is_super_admin='$is_super' WHERE admin_id='$admin_id'";
    $run_admin = mysqli_query($con, $update_admin);
    if ($run_admin) {
        $update_success = true;
    }
}

// Fetch distinct departments & functions/jobs for user dropdowns (only assigned & user added)
$user_depts = [];
foreach ($saved_depts as $sd) {
    if ($sd && strcasecmp($sd, 'not assigned') !== 0 && !in_array($sd, $user_depts)) {
        $user_depts[] = $sd;
    }
}
$user_dept_q = @mysqli_query($con, "SELECT DISTINCT department FROM admins WHERE department IS NOT NULL AND TRIM(department) != '' AND LOWER(TRIM(department)) != 'not assigned'");
if ($user_dept_q) {
    while ($udr = mysqli_fetch_assoc($user_dept_q)) {
        $udv = trim($udr['department']);
        if ($udv && !in_array($udv, $user_depts)) {
            $user_depts[] = $udv;
        }
    }
}
$emp_dept_q = @mysqli_query($con, "SELECT DISTINCT department FROM emp_list WHERE department IS NOT NULL AND TRIM(department) != '' AND LOWER(TRIM(department)) != 'not assigned'");
if ($emp_dept_q) {
    while ($edr = mysqli_fetch_assoc($emp_dept_q)) {
        $edv = trim($edr['department']);
        if ($edv && !in_array($edv, $user_depts)) {
            $user_depts[] = $edv;
        }
    }
}
sort($user_depts);

$user_desigs = [];
if ($admin_job && strcasecmp($admin_job, 'not assigned') !== 0 && !in_array($admin_job, $user_desigs)) {
    $user_desigs[] = $admin_job;
}
$user_job_q = @mysqli_query($con, "SELECT DISTINCT admin_job FROM admins WHERE admin_job IS NOT NULL AND TRIM(admin_job) != '' AND LOWER(TRIM(admin_job)) != 'not assigned'");
if ($user_job_q) {
    while ($ujr = mysqli_fetch_assoc($user_job_q)) {
        $ujv = trim($ujr['admin_job']);
        if ($ujv && !in_array($ujv, $user_desigs)) {
            $user_desigs[] = $ujv;
        }
    }
}
$emp_desig_q = @mysqli_query($con, "SELECT DISTINCT designation FROM emp_list WHERE designation IS NOT NULL AND TRIM(designation) != '' AND LOWER(TRIM(designation)) != 'not assigned'");
if ($emp_desig_q) {
    while ($esr = mysqli_fetch_assoc($emp_desig_q)) {
        $esv = trim($esr['designation']);
        if ($esv && !in_array($esv, $user_desigs)) {
            $user_desigs[] = $esv;
        }
    }
}
sort($user_desigs);
?>

<!-- Google Fonts: Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<div class="page-wrapper premium-ui-enabled">

    <form method="post" enctype="multipart/form-data">
        <!-- Card 1: Basic Account Details -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    1
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Edit User Details</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Basic details about the admin user</p>
                </div>
            </div>

            <div style="padding: 30px;">
                <div class="row">
                    <!-- Image Upload -->
                    <div class="col-md-4">
                        <label class="premium-label" style="font-size: 14px; color: #334155;">User Photo <span style="color: #64748b; font-weight: normal; font-size: 12px;">(Optional)</span></label>
                        <div class="upload-area" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; background: #f8fafc; position: relative; transition: 0.3s;">
                            <div style="width: 100px; height: 100px; background: #eff6ff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #dd2127; font-size: 20px; margin-bottom: 15px; margin-left: auto; margin-right: auto;">
                                <img id="usr_preview" src="admin_images/<?php echo htmlspecialchars($admin_image); ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            </div>
                            <h4 style="margin: 0 0 5px 0; font-size: 15px; font-weight: 700; color: #1e293b;">Upload user photo</h4>
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: #64748b;">JPG, PNG up to 5MB</p>

                            <label for="admin_image" class="btn btn-outline-primary" style="background: #fff; border: 1px solid #e2e8f0; color: #dd2127; font-weight: 600; padding: 8px 20px; border-radius: 8px; cursor: pointer;">
                                Choose File
                            </label>
                            <input type="file" name="admin_image" id="admin_image" style="display: none;" accept="image/*" onchange="previewImg(this)">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Name <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="admin_name" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. Madhavan" value="<?php echo htmlspecialchars($admin_name); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Email <span style="color: #ef4444;">*</span></label>
                                    <input type="email" name="admin_email" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="email@example.com" value="<?php echo htmlspecialchars($admin_email); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Password <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="admin_pass" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="••••••••" value="<?php echo htmlspecialchars($admin_pass); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Country <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="admin_country" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. India" value="<?php echo htmlspecialchars($admin_country); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Contact Number <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="admin_contact" class="p-input-premium" style="height: 48px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. 9876543210" value="<?php echo htmlspecialchars($admin_contact); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label class="premium-label" style="font-size: 14px; color: #334155; margin:0;">Assign Department(s) <span style="color: #ef4444;">*</span></label>
                                <button type="button" class="btn btn-sm btn-success" style="padding: 2px 10px; font-size: 11px; border-radius: 6px; font-weight: 700; background: #059669; border: none; cursor: pointer;" onclick="addNewDepartmentChecklist('dept_checklist_container')"><i class="fa fa-plus"></i> New</button>
                            </div>
                            <div id="dept_checklist_container" style="border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; max-height: 140px; overflow-y: auto; background: #fff; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                                <?php if (!empty($user_depts)): ?>
                                    <?php foreach ($user_depts as $dept):
                                        $is_sel = false;
                                        foreach ($saved_depts as $sd) {
                                            if (strcasecmp($sd, $dept) === 0) {
                                                $is_sel = true;
                                                break;
                                            }
                                        }
                                    ?>
                                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; font-weight: 500; margin-bottom: 6px; cursor: pointer; user-select: none;">
                                            <input type="checkbox" name="department[]" value="<?php echo htmlspecialchars($dept); ?>" <?php echo $is_sel ? 'checked' : ''; ?> style="accent-color: #dd2127; width: 16px; height: 16px; cursor: pointer;">
                                            <span><?php echo htmlspecialchars($dept); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:12px;" id="no_dept_text">No departments found. Click + New to add.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                                <label class="premium-label" style="font-size: 14px; color: #334155; margin:0;">Assign Function / Job <span style="color: #ef4444;">*</span></label>
                                <button type="button" class="btn btn-sm btn-success" style="padding: 2px 10px; font-size: 11px; border-radius: 6px; font-weight: 700; background: #059669; border: none; cursor: pointer;" onclick="addNewDesignation('user_job')"><i class="fa fa-plus"></i> New</button>
                            </div>
                            <select name="admin_job" id="user_job" class="p-input-premium" style="height: 48px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; padding:0 15px;" required>
                                <option value="">-- Select Function / Job --</option>
                                <?php foreach ($user_desigs as $desig): ?>
                                    <option value="<?php echo htmlspecialchars($desig); ?>" <?php echo (strcasecmp($admin_job, $desig) === 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($desig); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">About User</label>
                            <textarea name="admin_about" class="p-input-premium" style="height: 100px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; resize: none; padding:15px;" placeholder="Brief description of the admin..."><?php echo htmlspecialchars($admin_about); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div> <!-- End Card 1 -->


        <!-- Card 2: Access Permissions -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    2
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Access Permissions</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Manage system access and privileges</p>
                </div>
            </div>

            <div style="padding: 30px;">
                <?php if (function_exists('isSuperAdmin') && isSuperAdmin()): ?>
                    <div style="margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <label style="font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 12px; cursor: pointer; margin: 0;">
                            <div class="toggle-switch">
                                <input type="checkbox" name="is_super_admin" value="1" <?php echo $current_super ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </div>
                            <span style="user-select: none;">Super Admin Access (All Permissions)</span>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="row" style="display: flex; flex-wrap: wrap;">
                    <?php
                    $categories = [
                        'Dashboard' => ['dashboard_view'],
                        'Employee Management' => ['employee_view', 'employee_insert', 'employee_update', 'employee_delete'],
                        'Attendance' => ['attendance_view', 'attendance_insert'],
                        'Leaves' => ['leave_view', 'leave_insert', 'leave_approve'],
                        'Worksheet' => ['worksheet_view'],
                        'Finance & Salary' => ['salary_view', 'salary_insert', 'salary_update', 'salary_delete'],
                        'Project Budget' => ['budget_view', 'budget_insert', 'budget_update', 'budget_delete'],
                        'User & System' => ['user_view', 'user_insert', 'user_update', 'user_delete', 'announcement_view', 'announcement_insert', 'announcement_update', 'announcement_delete'],
                        'Projects' => ['project_view', 'project_insert', 'project_update', 'project_delete', 'project_assign_task', 'project_assigned_only'],
                        'Project Source' => ['project_source_view', 'project_source_insert', 'project_source_delete'],
                        'Todo' => ['todo_view', 'todo_insert', 'todo_update', 'todo_delete'],
                        'Leads' => ['lead_view', 'lead_insert', 'lead_update', 'lead_delete'],
                        'Clients' => ['client_view', 'client_insert', 'client_update', 'client_delete'],
                        'Company Links' => ['company_link_view', 'company_link_insert', 'company_link_update', 'company_link_delete'],
                        'Documents' => ['offer_letter_view', 'offer_letter_insert', 'nda_view', 'nda_insert', 'experience_letter_view', 'experience_letter_insert']
                    ];

                    foreach ($categories as $catName => $perms):
                    ?>
                        <div class="col-md-6" style="margin-bottom: 25px;">
                            <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; height: 100%;">
                                <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0;">
                                    <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #334155; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo $catName; ?></h4>
                                </div>
                                <div style="padding: 15px 20px; background: #fff;">
                                    <?php foreach ($perms as $perm):
                                        $is_checked = in_array($perm, $current_perms, true);
                                        $label = function_exists('getPermissionLabel') ? getPermissionLabel($perm) : ucwords(str_replace('_', ' ', $perm));
                                    ?>
                                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                            <label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0; display: flex; align-items: center; gap: 12px; width: 100%;">
                                                <div class="toggle-switch">
                                                    <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm); ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </div>
                                                <span style="user-select: none;"><?php echo htmlspecialchars($label); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 40px; display: flex; justify-content: flex-end; gap: 15px;">
                    <a href="index.php?view_users" class="btn-premium-cancel" style="padding: 12px 25px; border-radius: 8px; font-weight: 600; color: #475569; border: 1px solid #cbd5e1; background: #fff; text-decoration: none;">
                        Cancel
                    </a>
                    <button type="submit" name="update" class="btn-premium-add">
                        Save Changes
                    </button>
                </div>

            </div>
        </div> <!-- End Card 2 -->
    </form>
</div>

<style>
    .swal2-container.swal2-backdrop-show {
        background: rgba(15, 23, 42, 0.45) !important;
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
    }

    .premium-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: block;
    }

    .upload-area:hover {
        border-color: #dd2127;
    }

    /* Toggle Switch Styles */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    input:checked+.toggle-slider {
        background-color: #dd2127;
    }

    input:checked+.toggle-slider:before {
        transform: translateX(20px);
    }
</style>

<script>
    function previewImg(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('usr_preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    (function() {
        var superToggle = document.querySelector('input[name="is_super_admin"]');
        if (!superToggle) return;

        var permInputs = document.querySelectorAll('input[name="permissions[]"]');

        function applySuper(isSuper) {
            permInputs.forEach(function(chk) {
                if (isSuper) {
                    chk.checked = false;
                    chk.disabled = true;
                    chk.closest('.toggle-switch').style.opacity = '0.4';
                    chk.closest('.toggle-switch').style.pointerEvents = 'none';
                } else {
                    chk.disabled = false;
                    chk.closest('.toggle-switch').style.opacity = '';
                    chk.closest('.toggle-switch').style.pointerEvents = '';
                }
            });
        }

        // Apply on page load (if super admin is already ON)
        applySuper(superToggle.checked);

        // Apply on change
        superToggle.addEventListener('change', function() {
            applySuper(this.checked);
        });
    })();

    /**
     * Dynamic Department Checklist & Designation / Function Creation
     */
    function addNewDepartmentChecklist(containerId = 'dept_checklist_container') {
        const container = document.getElementById(containerId);
        if (!container) return;

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
                    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(chk => {
                        if (chk.value.toLowerCase() === newDept.toLowerCase()) {
                            chk.checked = true;
                            exists = true;
                        }
                    });
                    if (!exists) {
                        const noText = document.getElementById('no_dept_text');
                        if (noText) noText.remove();

                        const label = document.createElement('label');
                        label.style.cssText = 'display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; font-weight: 500; margin-bottom: 6px; cursor: pointer; user-select: none;';
                        label.innerHTML = `<input type="checkbox" name="department[]" value="${newDept}" checked style="accent-color: #dd2127; width: 16px; height: 16px; cursor: pointer;"> <span>${newDept}</span>`;
                        container.appendChild(label);
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Department Added',
                        text: `"${newDept}" has been added to the checklist.`,
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
                const checkboxes = container.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(chk => {
                    if (chk.value.toLowerCase() === val.toLowerCase()) {
                        chk.checked = true;
                        exists = true;
                    }
                });
                if (!exists) {
                    const noText = document.getElementById('no_dept_text');
                    if (noText) noText.remove();

                    const label = document.createElement('label');
                    label.style.cssText = 'display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; font-weight: 500; margin-bottom: 6px; cursor: pointer; user-select: none;';
                    label.innerHTML = `<input type="checkbox" name="department[]" value="${val}" checked style="accent-color: #dd2127; width: 16px; height: 16px; cursor: pointer;"> <span>${val}</span>`;
                    container.appendChild(label);
                }
            }
        }
    }

    function addNewDepartment(selectId = 'user_department') {
        addNewDepartmentChecklist('dept_checklist_container');
    }

    function addNewDesignation(selectId = 'user_job') {
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

<?php if (isset($update_success) && $update_success): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'User Updated Successfully!',
                    text: 'The admin user details have been saved.',
                    icon: 'success',
                    confirmButtonColor: '#dd2127',
                    confirmButtonText: 'OK',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'index.php?view_users';
                });
            } else {
                alert('User Updated Successfully!');
                window.location.href = 'index.php?view_users';
            }
        });
    </script>
<?php endif; ?>