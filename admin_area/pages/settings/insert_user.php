<?php
require_once __DIR__ . '/../../../settings/permissions/permissions.php';
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('isSuperAdmin')) {
    include(__DIR__ . '/../../includes/admin_permissions.php');
}
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit;
}

if (isset($_POST['submit'])) {
    $admin_name = $_POST['admin_name'];
    $admin_email = $_POST['admin_email'];
    $admin_pass = $_POST['admin_pass'];
    $admin_country = $_POST['admin_country'];
    $admin_job = $_POST['admin_job'];
    $admin_contact = $_POST['admin_contact'];
    $admin_about = $_POST['admin_about'];
    $admin_image = $_FILES['admin_image']['name'];
    $temp_admin_image = $_FILES['admin_image']['tmp_name'];
    move_uploaded_file($temp_admin_image, "admin_images/$admin_image");

    $permissions = '';
    if (!empty($_POST['permissions'])) {
        $permissions = implode(',', array_map(function ($p) use ($con) {
            return mysqli_real_escape_string($con, $p);
        }, $_POST['permissions']));
    }

    $is_super = (isset($_POST['is_super_admin']) && $_POST['is_super_admin'] == '1') ? 1 : 0;
    $perm_esc = mysqli_real_escape_string($con, $permissions);

    $insert_admin = "INSERT INTO admins (admin_name,admin_email,admin_pass,admin_image,admin_contact,admin_country,admin_job,admin_about,permissions,is_super_admin) VALUES ('" . mysqli_real_escape_string($con, $admin_name) . "','" . mysqli_real_escape_string($con, $admin_email) . "','" . mysqli_real_escape_string($con, $admin_pass) . "','" . mysqli_real_escape_string($con, $admin_image) . "','" . mysqli_real_escape_string($con, $admin_contact) . "','" . mysqli_real_escape_string($con, $admin_country) . "','" . mysqli_real_escape_string($con, $admin_job) . "','" . mysqli_real_escape_string($con, $admin_about) . "','$perm_esc','$is_super')";

    $run_admin = mysqli_query($con, $insert_admin);
    if ($run_admin) {
        echo "<script>Swal.fire({title: 'Notification', text: 'User Created Successfully', icon: 'success'}).then(() => { window.location.href='index.php?view_users'; });</script>";
    }
}
?>

<!-- Google Fonts: Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<div class="page-wrapper premium-ui-enabled">

    <form method="post" enctype="multipart/form-data">
        <!-- Card 1: Basic Account Details -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #DF2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    1
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Insert User Details</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Basic details about the admin user</p>
                </div>
            </div>

            <div style="padding: 30px;">
                <div class="row">
                    <!-- Image Upload -->
                    <div class="col-md-4">
                        <label class="premium-label" style="font-size: 14px; color: #334155;">User Photo <span style="color: #ef4444;">*</span></label>
                        <div class="upload-area" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; background: #f8fafc; position: relative; transition: 0.3s;">
                            <div style="width: 100px; height: 100px; background: #eff6ff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #DF2127; font-size: 20px; margin-bottom: 15px; margin-left: auto; margin-right: auto;">
                                <img id="usr_preview" src="admin_images/default.png" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            </div>
                            <h4 style="margin: 0 0 5px 0; font-size: 15px; font-weight: 700; color: #1e293b;">Upload user photo</h4>
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: #64748b;">JPG, PNG up to 5MB</p>

                            <label for="admin_image" class="btn btn-outline-primary" style="background: #fff; border: 1px solid #e2e8f0; color: #DF2127; font-weight: 600; padding: 8px 20px; border-radius: 8px; cursor: pointer;">
                                Choose File
                            </label>
                            <input type="file" name="admin_image" id="admin_image" style="display: none;" accept="image/*" required onchange="previewImg(this)">
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Name <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="admin_name" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. Madhavan" value="" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Email <span style="color: #ef4444;">*</span></label>
                                    <input type="email" name="admin_email" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="email@example.com" value="" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Admin Password <span style="color: #ef4444;">*</span></label>
                                    <input type="password" name="admin_pass" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="••••••••" value="" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Country <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="admin_country" class="p-input-premium" style="height: 48px; width:100%; border-radius:8px; border:1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. India" value="" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Contact Number <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="admin_contact" class="p-input-premium" style="height: 48px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. 9876543210" value="" required>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Designation / Job <span style="color: #ef4444;">*</span></label>
                            <input type="text" name="admin_job" class="p-input-premium" style="height: 48px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; padding:0 15px;" placeholder="e.g. HR Manager" value="" required>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 20px;">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">About User</label>
                            <textarea name="admin_about" class="p-input-premium" style="height: 100px; width:100%; border-radius: 8px; border: 1px solid #e2e8f0; resize: none; padding:15px;" placeholder="Brief description of the admin..."></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div> <!-- End Card 1 -->


        <!-- Card 2: Access Permissions -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #DF2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
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
                                <input type="checkbox" name="is_super_admin" value="1" <?php echo false ? 'checked' : ''; ?>>
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
                                        $is_checked = false;
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
                    <button type="submit" name="submit" class="btn-premium-add">
                        Insert User
                    </button>
                </div>

            </div>
        </div> <!-- End Card 2 -->
    </form>
</div>

<style>
    .premium-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: block;
    }

    .upload-area:hover {
        border-color: #DF2127;
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
        background-color: #DF2127;
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

        // Apply on page load
        applySuper(superToggle.checked);

        // Apply on change
        superToggle.addEventListener('change', function() {
            applySuper(this.checked);
        });
    })();
</script>