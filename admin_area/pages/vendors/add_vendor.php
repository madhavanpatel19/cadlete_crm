<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit;
}

if (isset($_POST['save_vendor'])) {
    $category_section = mysqli_real_escape_string($con, trim($_POST['category_section'] ?? ''));
    $sub_group        = mysqli_real_escape_string($con, trim($_POST['sub_group'] ?? ''));

    // Auto generate unique vendor ID (e.g. IM-006 or VND-006)
    $prefix = 'VND';
    if (preg_match_all('/\b(\w)/', $category_section, $m) && !empty($m[1])) {
        $prefix = strtoupper(implode('', array_slice($m[1], 0, 2)));
    }
    $max_res = mysqli_query($con, "SELECT MAX(id) as max_id FROM vendors");
    $next_id = 1;
    if ($max_res && $r = mysqli_fetch_assoc($max_res)) {
        $next_id = ((int)$r['max_id']) + 1;
    }
    $vendor_custom_id = $prefix . '-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

    $company_name   = mysqli_real_escape_string($con, trim($_POST['company_name'] ?? ''));
    $contact_person = mysqli_real_escape_string($con, trim($_POST['contact_person'] ?? ''));
    $raw_phone      = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    $phone          = mysqli_real_escape_string($con, substr($raw_phone, 0, 10));
    $email          = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
    $city           = mysqli_real_escape_string($con, trim($_POST['city'] ?? ''));
    $address        = mysqli_real_escape_string($con, trim($_POST['address'] ?? ''));
    $project_name   = mysqli_real_escape_string($con, trim($_POST['project_name'] ?? ''));
    $notes          = mysqli_real_escape_string($con, trim($_POST['notes'] ?? ''));

    if (!empty($company_name)) {
        $insert = "INSERT INTO vendors (vendor_custom_id, category_section, sub_group, company_name, contact_person, phone, email, city, address, project_name, projects_count, notes) 
                   VALUES ('$vendor_custom_id', '$category_section', '$sub_group', '$company_name', '$contact_person', '$phone', '$email', '$city', '$address', '$project_name', 0, '$notes')";

        if (mysqli_query($con, $insert)) {
            echo "<script>
                Swal.fire({
                    title: 'Success!',
                    text: 'New Vendor added successfully with ID: " . $vendor_custom_id . "',
                    icon: 'success',
                    confirmButtonColor: '#dd2127'
                }).then(() => {
                    window.location.href = 'index.php?vendors';
                });
            </script>";
        } else {
            echo "<script>Swal.fire('Error', 'Database error: " . mysqli_error($con) . "', 'error');</script>";
        }
    }
}
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1e293b;">Add Vendor</h1>
        <div class="header-actions-premium">
            <a href="index.php?vendors" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Vendors
            </a>
        </div>
    </div>

    <div class="premium-card">
        <div class="card-hdr" style="background: var(--p-bg-header)">
            <i class="fa fa-truck"></i>
            <h3>Add New Vendor Details</h3>
        </div>

        <div style="padding: 40px;">
            <form method="POST" class="form-horizontal">
                <!-- Section 1: Classification -->
                <div style="margin-bottom: 35px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #dd2127; margin: 0;"><i class="fa fa-tags"></i> Category & Classification</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Category Section</label>
                            <div class="col-md-8">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <select name="category_section" id="category_section" class="p-input-premium" style="width: 100%;" required>
                                        <?php
                                        $db_secs_res = mysqli_query($con, "SELECT DISTINCT category_section FROM vendors WHERE deleted_at IS NULL AND category_section != '' ORDER BY category_section ASC");
                                        $count_secs = 0;
                                        if ($db_secs_res) {
                                            while ($r = mysqli_fetch_assoc($db_secs_res)) {
                                                $count_secs++;
                                                echo "<option value='" . htmlspecialchars($r['category_section']) . "'>" . htmlspecialchars($r['category_section']) . "</option>";
                                            }
                                        }
                                        if ($count_secs === 0) {
                                            echo "<option value='' disabled selected>-- Click + New to add Category Section --</option>";
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="btn-premium-add" data-toggle="modal" data-target="#addCategoryModal">
                                        <i class="fa fa-plus"></i> New
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Sub Group</label>
                            <div class="col-md-8">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <select name="sub_group" id="sub_group" class="p-input-premium" style="width: 100%;">
                                        <?php
                                        $db_subs_res = mysqli_query($con, "SELECT DISTINCT sub_group FROM vendors WHERE deleted_at IS NULL AND sub_group != '' ORDER BY sub_group ASC");
                                        $count_subs = 0;
                                        if ($db_subs_res) {
                                            while ($r = mysqli_fetch_assoc($db_subs_res)) {
                                                $count_subs++;
                                                echo "<option value='" . htmlspecialchars($r['sub_group']) . "'>" . htmlspecialchars($r['sub_group']) . "</option>";
                                            }
                                        }
                                        if ($count_subs === 0) {
                                            echo "<option value='' disabled selected>-- Click + New to add Sub Group --</option>";
                                        }
                                        ?>
                                    </select>
                                    <button type="button" class="btn-premium-add" data-toggle="modal" data-target="#addSubGroupModal" style="border-radius: 8px; padding: 10px 14px; font-weight: 700; background: #10b981; border: none; white-space: nowrap; height: 46px; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa fa-plus"></i> New
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Vendor Details -->
                <div style="margin: 40px 0 35px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #dd2127; margin: 0;"><i class="fa fa-building-o"></i> Vendor Company Details</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Company Name <span class="text-danger">*</span></label>
                            <div class="col-md-8">
                                <input type="text" name="company_name" required class="p-input-premium" placeholder="e.g. Polymer Solutions Pvt. Ltd.">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Contact Person</label>
                            <div class="col-md-8">
                                <input type="text" name="contact_person" class="p-input-premium" placeholder="e.g. Rajesh Patel">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Phone No</label>
                            <div class="col-md-8">
                                <div style="position: relative;">
                                    <i class="fa fa-phone" style="position: absolute; left: 12px; top: 15px; color: #94a3b8; font-size: 14px;"></i>
                                    <input type="text" name="phone" id="vendor_phone" class="p-input-premium" placeholder="e.g. 9876543210" maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)" style="padding-left: 35px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Email ID</label>
                            <div class="col-md-8">
                                <div style="position: relative;">
                                    <i class="fa fa-envelope-o" style="position: absolute; left: 12px; top: 15px; color: #94a3b8; font-size: 14px;"></i>
                                    <input type="email" name="email" class="p-input-premium" placeholder="email@example.com" style="padding-left: 35px;">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">City</label>
                            <div class="col-md-8">
                                <input type="text" name="city" class="p-input-premium" placeholder="e.g. Ahmedabad">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Address</label>
                            <div class="col-md-8">
                                <input type="text" name="address" class="p-input-premium" placeholder="GIDC, Vatva, Ahmedabad, Gujarat 382445">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Additional Details -->
                <div style="margin: 40px 0 35px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #dd2127; margin: 0;"><i class="fa fa-info-circle"></i> Additional Details</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Project Name</label>
                            <div class="col-md-8">
                                <input type="text" name="project_name" list="project_list" class="p-input-premium" placeholder="e.g. 8dots CRM / Project Alpha">
                                <datalist id="project_list">
                                    <?php
                                    $proj_res = mysqli_query($con, "SELECT DISTINCT project_name FROM client_projects WHERE deleted_at IS NULL AND project_name != '' ORDER BY project_name ASC");
                                    if ($proj_res) {
                                        while ($pr = mysqli_fetch_assoc($proj_res)) {
                                            echo "<option value=\"" . htmlspecialchars($pr['project_name']) . "\">";
                                        }
                                    }
                                    ?>
                                </datalist>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Notes / Remarks</label>
                            <div class="col-md-8">
                                <input type="text" name="notes" class="p-input-premium" placeholder="Reliable supplier, on-time delivery...">
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 50px; text-align: right; border-top: 1.5px solid #f1f5f9; padding-top: 30px;">
                    <a href="index.php?vendors" class="btn-premium-cancel">Cancel</a>
                    <button type="submit" name="save_vendor" class="btn-premium-add">
                        <i class="fa fa-save"></i> Save Vendor Information
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 1: Add New Category Section -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 20px; top: 20px; opacity: 0.8; outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                    <div style="background: #DD2127; color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="font-size: 14px;"></i>
                    </div>
                    Add New Category Section
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <div style="margin-bottom: 25px;">
                    <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Category Section Name</label>
                    <input type="text" id="new_category_name_input" placeholder="e.g. CNC Machining & Tooling" class="p-input-premium" style="width: 100%;">
                </div>
                <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-premium-add" onclick="addNewCategorySection()">
                        <i class="fa fa-save"></i> Save Category
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Add New Sub Group -->
<div class="modal fade" id="addSubGroupModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 20px; top: 20px; opacity: 0.8; outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                    <div style="background: #DD2127; color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="font-size: 14px;"></i>
                    </div>
                    Add New Sub Group
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <div style="margin-bottom: 25px;">
                    <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Sub Group Name</label>
                    <input type="text" id="new_subgroup_name_input" placeholder="e.g. Raw Material Supply" class="p-input-premium" style="width: 100%;">
                </div>
                <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                    <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-premium-add" onclick="addNewSubGroup()">
                        <i class="fa fa-save"></i> Save Sub Group
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function addNewCategorySection() {
        var name = $('#new_category_name_input').val().trim();
        if (!name) {
            alert('Please enter Category Section name');
            return;
        }
        var exists = false;
        $('#category_section option').each(function() {
            if ($(this).val().toLowerCase() === name.toLowerCase()) {
                exists = true;
            }
        });
        if (!exists) {
            $('#category_section').append(new Option(name, name, true, true));
        } else {
            $('#category_section').val(name);
        }
        $('#new_category_name_input').val('');
        $('#addCategoryModal').modal('hide');
    }

    function addNewSubGroup() {
        var name = $('#new_subgroup_name_input').val().trim();
        if (!name) {
            alert('Please enter Sub Group name');
            return;
        }
        var exists = false;
        $('#sub_group option').each(function() {
            if ($(this).val().toLowerCase() === name.toLowerCase()) {
                exists = true;
            }
        });
        if (!exists) {
            $('#sub_group').append(new Option(name, name, true, true));
        } else {
            $('#sub_group').val(name);
        }
        $('#new_subgroup_name_input').val('');
        $('#addSubGroupModal').modal('hide');
    }
</script>