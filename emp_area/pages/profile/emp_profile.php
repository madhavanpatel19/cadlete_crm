<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
} else {
    $emp_id = $_SESSION['emp_id'];

    // Fetch employee details
    $get_emp = "SELECT * FROM emp_list WHERE id = '$emp_id'";
    $run_emp = mysqli_query($con, $get_emp);
    $employee = mysqli_fetch_array($run_emp);

    if (!$employee) {
        echo "<div class='alert alert-danger'>Employee profile not found.</div>";
        exit();
    }

    $emp_name = $employee['name'];
    $emp_personal_email = $employee['email'];
    $emp_company_email = !empty($employee['company_email']) ? $employee['company_email'] : $employee['email'];
    $emp_email = $emp_company_email;
    $emp_designation = !empty($employee['designation']) ? $employee['designation'] : 'Employee';
    $emp_contact = $employee['phone_number'];
    $emp_address = $employee['address'];
    $emp_image = $employee['employee_image'];
    $emp_job = "Employee"; // Default if not in table
    $emp_join = $employee['join_date'];
    $emp_blood = $employee['blood_group'];
    $emp_gender = $employee['gender'];
    $emp_dob = $employee['dob'];

?>

    <div class="page-wrapper">
        <div class="page-header-premium">
            <h1>My Profile</h1>
            <div class="header-actions">
                <button class="btn-premium-add" onclick="window.location.reload();">
                    <i class="fa fa-refresh"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Main Container -->
        <div class="row">
            <!-- Left Profile Card -->
            <div class="col-md-4">
                <div class="premium-card" style="border: none; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08); background: #fff; margin-bottom: 30px;">
                    <div style="height: 100px; background: var(--p-bg-header)"></div>
                    <div style="padding: 0 30px 30px 30px; margin-top: -50px; text-align: center;">
                        <div style="position: relative; display: inline-block;">
                            <img src="../admin_area/uploads/<?php echo !empty($emp_image) ? $emp_image : '../admin_images/default.png'; ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.1); background: #fff;">
                            <div style="position: absolute; bottom: 5px; right: 5px; width: 22px; height: 22px; background: #10b981; border: 3px solid #fff; border-radius: 50%;"></div>
                        </div>
                        <h2 style="margin: 15px 0 5px 0; font-size: 20px; font-weight: 800; color: #1e293b;"><?php echo htmlspecialchars($emp_name); ?></h2>
                        <p style="color: #64748b; font-size: 14px; margin-bottom: 20px; font-weight: 600;"><?php echo htmlspecialchars($emp_designation); ?> (ID: #<?php echo $emp_id; ?>)</p>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 20px; display: flex; flex-direction: column; gap: 15px; text-align: left;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: #fee2e2; color: #dd2127; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                    <i class="fa fa-building"></i>
                                </div>
                                <div>
                                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Company Email (Login)</div>
                                    <div style="font-size: 13px; font-weight: 700; color: #dd2127;"><?php echo htmlspecialchars($emp_company_email); ?></div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                    <i class="fa fa-envelope"></i>
                                </div>
                                <div>
                                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Personal Email</div>
                                    <div style="font-size: 13px; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($emp_personal_email); ?></div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: #ecfdf5; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                    <i class="fa fa-phone"></i>
                                </div>
                                <div>
                                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Phone Number</div>
                                    <div style="font-size: 13px; font-weight: 600; color: #1e293b;"><?php echo $emp_contact; ?></div>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 32px; height: 32px; border-radius: 10px; background: #fff7ed; color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                    <i class="fa fa-calendar-check-o"></i>
                                </div>
                                <div>
                                    <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Joined On</div>
                                    <div style="font-size: 13px; font-weight: 600; color: #1e293b;"><?php echo date('d-m-Y', strtotime($emp_join)); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Details Tabs -->
            <div class="col-lg-8">
                <div class="premium-card" style="border: none; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08); background: #fff; margin-bottom: 30px;">
                    <div class="card-hdr" style="background: var(--p-bg-header); color: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fa fa-user" style="font-size: 18px;"></i>
                            <h3 style="margin: 0; font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">Employee Information</h3>
                        </div>
                        <button class="btn-premium-add" data-toggle="modal" data-target="#editProfileModal">
                            <i class="fa fa-pencil"></i> Edit Profile
                        </button>
                    </div>

                    <div style="padding: 30px;">
                        <!-- Section: Personal Details -->
                        <div style="margin-bottom: 40px;">
                            <h4 style="font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #3b82f6;"></span> Personal Details
                            </h4>
                            <div class="row">
                                <div class="col-md-4" style="margin-bottom: 25px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Full Name</div>
                                    <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?php echo $emp_name; ?></div>
                                </div>
                                <div class="col-md-4" style="margin-bottom: 25px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Gender</div>
                                    <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?php echo $emp_gender; ?></div>
                                </div>
                                <div class="col-md-4" style="margin-bottom: 25px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Blood Group</div>
                                    <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><span style="color: #ef4444;"><i class="fa fa-tint"></i></span> <?php echo $emp_blood; ?></div>
                                </div>
                                <div class="col-md-4" style="margin-bottom: 25px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Date of Birth</div>
                                    <div style="font-size: 15px; font-weight: 700; color: #1e293b;"><?php echo date('d-m-Y', strtotime($emp_dob)); ?></div>
                                </div>
                                <div class="col-md-8" style="margin-bottom: 25px;">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">Address</div>
                                    <div style="font-size: 14px; font-weight: 600; color: #1e293b; line-height: 1.5;"><?php echo $emp_address; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Emergency Contact -->
                        <div style="margin-bottom: 40px; padding: 25px; background: #fdf2f2; border-radius: 20px; border: 1px solid #fee2e2;">
                            <h4 style="font-size: 13px; font-weight: 800; color: #b91c1c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <i class="fa fa-ambulance"></i> Emergency Contact
                            </h4>
                            <div class="row">
                                <div class="col-md-4">
                                    <div style="font-size: 10px; font-weight: 700; color: #991b1b; text-transform: uppercase; opacity: 0.7; margin-bottom: 3px;">Name</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #7f1d1d;"><?php echo !empty($employee['emergency_name']) ? $employee['emergency_name'] : '-'; ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div style="font-size: 10px; font-weight: 700; color: #991b1b; text-transform: uppercase; opacity: 0.7; margin-bottom: 3px;">Relationship</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #7f1d1d;"><?php echo !empty($employee['emergency_relationship']) ? $employee['emergency_relationship'] : '-'; ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div style="font-size: 10px; font-weight: 700; color: #991b1b; text-transform: uppercase; opacity: 0.7; margin-bottom: 3px;">Phone</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #7f1d1d;"><?php echo !empty($employee['emergency_phone']) ? $employee['emergency_phone'] : '-'; ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Bank Details -->
                        <div>
                            <h4 style="font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span> Salary & Bank Details
                            </h4>
                            <div class="row" style="gap: 20px 0;">
                                <div class="col-md-6">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px;">Bank Name & Branch</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #1e293b;"><?php echo !empty($employee['bank_branch']) ? $employee['bank_branch'] : '-'; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px;">Account Number</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #1e293b;"><?php echo !empty($employee['account_number']) ? $employee['account_number'] : '-'; ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 5px;">IFSC Code</div>
                                    <div style="font-size: 14px; font-weight: 700; color: #1e293b;"><?php echo !empty($employee['account_type_ifsc']) ? $employee['account_type_ifsc'] : '-'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content" style="border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 20px 24px; background: #ffeaeb; border-radius: 14px 14px 0 0; position: relative;">
                    <div style="display: flex; align-items: center; width: 100%; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #dc2626; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-plus" style="color: #fff; font-size: 14px;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 17px; margin: 0;">Edit My Profile</h5>
                        </div>
                    </div>
                    <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <div class="modal-body" style="padding: 25px;">
                    <form class="form-horizontal" method="POST" enctype="multipart/form-data">
                        <div style="text-align: center; margin-bottom: 25px;">
                            <div style="position: relative; display: inline-block;">
                                <img id="edit_profile_preview" src="../admin_area/uploads/<?php echo !empty($emp_image) ? $emp_image : '../admin_images/default.png'; ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #f1f5f9;">
                                <label for="edit_employee_image" style="position: absolute; bottom: 0; right: 0; background: #334155; color: #fff; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #fff;">
                                    <i class="fa fa-camera" style="font-size: 12px;"></i>
                                </label>
                                <input type="file" name="employee_image" id="edit_employee_image" style="display: none;" accept="image/*" onchange="handleProfilePreview(this)">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Full Name</label>
                            <div class="col-md-8">
                                <input type="text" name="name" class="p-input-premium" value="<?php echo htmlspecialchars($emp_name); ?>" required>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Phone Number</label>
                            <div class="col-md-8">
                                <input type="tel" name="phone" class="p-input-premium" value="<?php echo htmlspecialchars($emp_contact); ?>" required maxlength="10">
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="col-md-4 control-label" style="text-align: left; color: #64748b; font-weight: 600;">Residential Address</label>
                            <div class="col-md-8">
                                <textarea name="address" class="p-input-premium" rows="3" style="height: auto; resize: none;" required><?php echo htmlspecialchars($emp_address); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group" style="margin-top: 30px; margin-bottom: 0;">
                            <div class="col-md-12" style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_profile" class="btn-premium-add">
                                    <i class="fa fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function handleProfilePreview(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('edit_profile_preview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <?php
    if (isset($_POST['update_profile'])) {
        $new_name = mysqli_real_escape_string($con, $_POST['name']);
        $new_phone = mysqli_real_escape_string($con, $_POST['phone']);
        $new_address = mysqli_real_escape_string($con, $_POST['address']);

        $update_image = "";
        if (!empty($_FILES['employee_image']['name'])) {
            $file_name = $_FILES['employee_image']['name'];
            $tmp_name = $_FILES['employee_image']['tmp_name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_img_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($tmp_name, "../admin_area/uploads/" . $new_img_name)) {
                $update_image = ", employee_image = '$new_img_name'";
                // Update session if needed, but session doesn't store image
            }
        }

        $update_query = "UPDATE emp_list SET name = '$new_name', phone_number = '$new_phone', address = '$new_address' $update_image WHERE id = '$emp_id'";
        if (mysqli_query($con, $update_query)) {
            $_SESSION['emp_name'] = $new_name; // Sync session
            echo "<script>Swal.fire({title: 'Notification', text: 'Profile updated successfully!', icon: 'success'}).then(() => { window.location.href='index.php?emp_profile'; });</script>";
        } else {
            echo "<script>Swal.fire({title: 'Notification', text: 'Error updating profile: " . mysqli_error($con) . "', icon: 'error'});</script>";
        }
    }
    ?>

<?php } ?>