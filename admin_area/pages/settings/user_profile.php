<?php
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
} else {
    if (isset($_GET['user_profile'])) {
        $edit_id = $_GET['user_profile'];
        $get_admin = "select * from admins where admin_id='$edit_id'";
        $run_admin = mysqli_query($con, $get_admin);
        $row_admin = mysqli_fetch_array($run_admin);
        $admin_id = $row_admin['admin_id'];
        $admin_name = $row_admin['admin_name'];
        $admin_email = $row_admin['admin_email'];
        $admin_pass = $row_admin['admin_pass'];
        $admin_image = $row_admin['admin_image'];
        $new_admin_image = $row_admin['admin_image'];
        $admin_country = $row_admin['admin_country'];
        $admin_job = $row_admin['admin_job'];
        $admin_contact = $row_admin['admin_contact'];
        $admin_about = $row_admin['admin_about'];
    }
?>
    <div class="page-wrapper premium-ui-enabled">
        <div class="page-header-premium">
            <h1><i class="fa fa-user-circle" style="color: #333;"></i> My Profile Settings</h1>
            <div class="header-actions-premium">
                <a href="index.php?dashboard" class="btn-premium-cancel">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <ol class="breadcrumb">
            <li><i class="fa fa-dashboard"></i> Dashboard</li>
            <li class="active">Edit Profile</li>
        </ol>

        <div class="premium-card">
            <div class="card-hdr">
                <i class="fa fa-edit"></i>
                <h3>Update Your Account Information</h3>
            </div>

            <div style="padding: 40px;">
                <form method="post" enctype="multipart/form-data">

                    <!-- Avatar & Identity Section -->
                    <div class="row align-items-center" style="margin-bottom: 40px;">
                        <div class="col-md-3">
                            <div class="form-group text-center" style="margin-bottom: 0;">
                                <div style="position: relative; display: inline-block;">
                                    <img id="profile_preview" src="admin_images/<?php echo !empty($admin_image) ? $admin_image : 'default.png'; ?>" style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                    <label for="admin_image" style="position: absolute; bottom: 5px; right: 5px; background: #333; color: #fff; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #fff; transition: 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                        <i class="fa fa-camera" style="font-size: 16px;"></i>
                                    </label>
                                    <input type="file" name="admin_image" id="admin_image" style="display: none;" accept="image/*" onchange="handleImagePreview(this, 'profile_preview')">
                                </div>
                                <small style="color: #64748b; margin-top: 12px; display: block; font-weight: 600;">Update Profile Picture</small>
                            </div>
                        </div>

                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">User Name *</label>
                                        <input type="text" name="admin_name" class="p-input-premium" value="<?php echo htmlspecialchars($admin_name); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Email Address *</label>
                                        <input type="email" name="admin_email" class="p-input-premium" value="<?php echo htmlspecialchars($admin_email); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row" style="margin-top: 15px;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Password *</label>
                                        <input type="password" name="admin_pass" class="p-input-premium" value="<?php echo htmlspecialchars($admin_pass); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Job Role *</label>
                                        <input type="text" name="admin_job" class="p-input-premium" value="<?php echo htmlspecialchars($admin_job); ?>" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;">
                        <h4 style="font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                            <i class="fa fa-info-circle" style="color: #333; opacity: 0.7;"></i> Additional Details
                        </h4>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Contact Number *</label>
                                <input type="text" name="admin_contact" class="p-input-premium" value="<?php echo htmlspecialchars($admin_contact); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">Country *</label>
                                <input type="text" name="admin_country" class="p-input-premium" value="<?php echo htmlspecialchars($admin_country); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 8px; display: block;">About / Bio</label>
                                <textarea name="admin_about" class="p-input-premium" rows="1" style="height: auto; min-height: 46px;"><?php echo htmlspecialchars($admin_about); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 50px; text-align: right; border-top: 1.5px solid #f1f5f9; padding-top: 30px;">
                        <button type="submit" name="update" class="btn-premium-add">
                            <i class="fa fa-save"></i> Save Changes & Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function handleImagePreview(input, previewId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <?php
    if (isset($_POST['update'])) {
        $admin_name = $_POST['admin_name'];
        $admin_email = $_POST['admin_email'];
        $admin_pass = $_POST['admin_pass'];
        $admin_country = $_POST['admin_country'];
        $admin_job = $_POST['admin_job'];
        $admin_contact = $_POST['admin_contact'];
        $admin_about = $_POST['admin_about'];

        $admin_image = $_FILES['admin_image']['name'];
        $temp_admin_image = $_FILES['admin_image']['tmp_name'];

        if (!empty($admin_image)) {
            move_uploaded_file($temp_admin_image, __DIR__ . "/../../admin_images/$admin_image");
        } else {
            $admin_image = $new_admin_image;
        }

        $update_admin = "update admins set admin_name='$admin_name',admin_email='$admin_email',admin_pass='$admin_pass',admin_image='$admin_image',admin_contact='$admin_contact',admin_country='$admin_country',admin_job='$admin_job',admin_about='$admin_about' where admin_id='$admin_id'";
        $run_admin = mysqli_query($con, $update_admin);

        if ($run_admin) {
            echo "<script>Swal.fire({title: 'Notification', text: 'Your profile has been updated successfully. Please login again to see changes.', icon: 'success'});</script>";
            echo "<script>window.open('pages/auth/login.php','_self')</script>";
            session_destroy();
        }
    }
    ?>
<?php } ?>