<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    .select2-container {
        width: 100% !important;
    }

    .select2-container--default .select2-selection--single,
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #e2e8f0 !important;
        border-radius: 8px !important;
        min-height: 48px !important;
        background-color: #fff !important;
        display: flex;
        align-items: center;
        padding: 0 8px;
        transition: all 0.3s ease;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155 !important;
        line-height: normal !important;
        padding-left: 8px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px !important;
        right: 10px !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        border-radius: 6px !important;
        color: #1e3a8a !important;
        padding: 4px 8px 4px 24px !important;
        margin-top: 6px !important;
        position: relative !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #1e3a8a !important;
        border-right: 1px solid rgba(30, 58, 138, 0.2) !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        bottom: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 6px !important;
        margin: 0 !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        background-color: rgba(30, 58, 138, 0.1) !important;
        color: #ef4444 !important;
    }

    .select2-search--inline .select2-search__field {
        margin-top: 8px !important;
        font-family: inherit !important;
        color: #334155 !important;
    }

    .select2-search--inline .select2-search__field:focus {
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        background: transparent !important;
    }

    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--multiple,
    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--multiple {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
        outline: none !important;
    }
</style>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
global $con;

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit;
}

$edit_id = null;
$c_name = '';
$c_phone = '';
$c_email = '';
$c_company = '';
$c_project = '';
$c_desc = '';
$c_remark = '';
$c_budget = '';
$c_currency = 'INR';
$c_status = '';
$c_followup = '';
$c_source = [];

if (isset($_GET['edit_lead'])) {
    $edit_id = mysqli_real_escape_string($con, $_GET['edit_lead']);
    $get_lead = "SELECT * FROM leads WHERE id = '$edit_id'";
    $run_lead = mysqli_query($con, $get_lead);
    $row_lead = mysqli_fetch_array($run_lead);

    if (!$row_lead) {
        echo "<script>Swal.fire({title: 'Notification', text: 'Lead not found!', icon: 'error'}).then(() => { window.open('../../index.php?leads','_self'); });</script>";
        exit;
    }

    $c_name = $row_lead['client_name'];
    $c_phone = $row_lead['phone'];
    $c_email = $row_lead['email'];
    $c_company = $row_lead['company_name'];
    $c_project = $row_lead['project_name'];
    $c_desc = $row_lead['description'];
    $c_remark = $row_lead['remark'];
    $c_budget = $row_lead['budget'];
    $c_status = $row_lead['status'];
    $c_followup = $row_lead['followup_date'];
    $c_source = explode(', ', $row_lead['lead_source']);
    $c_assigned_employees = !empty($row_lead['assigned_employees']) ? explode(',', $row_lead['assigned_employees']) : [];
    $c_assigned_admins = !empty($row_lead['assigned_admins']) ? explode(',', $row_lead['assigned_admins']) : [];
}

$get_emps = "SELECT id, employee_image, name FROM emp_list WHERE deleted_at IS NULL ORDER BY name ASC";
$run_emps = mysqli_query($con, $get_emps);

$get_admins = "SELECT admin_id, admin_image, admin_name FROM admins ORDER BY admin_name ASC";
$run_admins = mysqli_query($con, $get_admins);

if (isset($_POST['update_lead'])) {
    $client_name = isset($_POST['client_name']) ? mysqli_real_escape_string($con, $_POST['client_name']) : '';
    $phone = isset($_POST['phone']) ? mysqli_real_escape_string($con, $_POST['phone']) : '';
    $email = isset($_POST['email']) ? mysqli_real_escape_string($con, $_POST['email']) : '';
    $company_name = isset($_POST['company_name']) ? mysqli_real_escape_string($con, $_POST['company_name']) : '';
    $project_name = isset($_POST['project_name']) ? mysqli_real_escape_string($con, $_POST['project_name']) : '';
    $description = isset($_POST['description']) ? mysqli_real_escape_string($con, $_POST['description']) : '';
    $remark = isset($_POST['remark']) ? mysqli_real_escape_string($con, $_POST['remark']) : '';
    $budget = isset($_POST['budget']) ? mysqli_real_escape_string($con, $_POST['budget']) : '';
    $currency = isset($_POST['currency']) ? mysqli_real_escape_string($con, $_POST['currency']) : '';
    $status = isset($_POST['status']) ? mysqli_real_escape_string($con, $_POST['status']) : '';
    $followup_date_raw = isset($_POST['followup_date']) ? trim($_POST['followup_date']) : '';
    $followup_date = !empty($followup_date_raw) ? date('Y-m-d', strtotime($followup_date_raw)) : '';

    $lead_sources = isset($_POST['lead_source']) ? $_POST['lead_source'] : [];
    $lead_source_str = implode(', ', $lead_sources);

    $assigned_employees_arr = isset($_POST['assigned_employees']) && is_array($_POST['assigned_employees']) ? $_POST['assigned_employees'] : [];
    $assigned_employees_str = mysqli_real_escape_string($con, implode(',', $assigned_employees_arr));

    $assigned_admins_arr = isset($_POST['assigned_admins']) && is_array($_POST['assigned_admins']) ? $_POST['assigned_admins'] : [];
    $assigned_admins_str = mysqli_real_escape_string($con, implode(',', $assigned_admins_arr));

    $update_lead = "UPDATE leads SET 
                    client_name='$client_name', 
                    phone='$phone', 
                    email='$email', 
                    company_name='$company_name', 
                    project_name='$project_name', 
                    description='$description', 
                    remark='$remark', 
                    budget='$budget', 
                    currency='$currency', 
                    lead_source='$lead_source_str', 
                    status='$status', 
                    assigned_employees='$assigned_employees_str',
                    assigned_admins='$assigned_admins_str',
                    followup_date='$followup_date' 
                    WHERE id='$edit_id'";

    if (mysqli_query($con, $update_lead)) {
        if (file_exists(__DIR__ . '/../../includes/notification_helper.php')) {
            require_once __DIR__ . '/../../includes/notification_helper.php';
            foreach ($assigned_employees_arr as $eid) {
                $eid = intval($eid);
                if ($eid > 0) {
                    addSystemNotification('employee', $eid, "Lead Assignment: $client_name", "You have been assigned to lead '$client_name'.", "index.php?view_lead=$edit_id", 'lead_assigned');
                }
            }
        }
        echo "<style>
            body.swal2-shown:not(.swal2-no-backdrop):not(.swal2-toast-shown) {
                overflow: hidden !important;
            }
            .swal2-backdrop-show {
                backdrop-filter: blur(5px) !important;
                background: rgba(15, 23, 42, 0.6) !important;
            }
        </style>";
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Lead updated successfully!',
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    background: '#ffffff',
                    customClass: { popup: 'premium-card' }
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
    } else {
        $err = mysqli_real_escape_string($con, mysqli_error($con));
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '$err',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        </script>";
    }
}
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #1e293b;">Edit Lead</h1>
        <div class="header-actions-premium">
            <a href="index.php?leads" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Leads
            </a>
        </div>
    </div>

    <div class="premium-card">
        <div class="card-hdr" style="background: var(--p-bg-header)">
            <i class="fa fa-edit"></i>
            <h3>Update Lead Details</h3>
        </div>

        <div style="padding: 40px;">
            <form method="POST" class="form-horizontal">
                <!-- Section: Client Details -->
                <div style="margin-bottom: 35px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #4f46e5; margin: 0;"><i class="fa fa-user-circle-o"></i> Client Information</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Client Name <span class="text-danger">*</span></label>
                            <div class="col-md-8">
                                <input type="text" name="client_name" class="p-input-premium" required value="<?php echo $c_name; ?>" placeholder="Full Name">
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">
                                Phone No</span>
                            </label>
                            <div class="col-md-8">
                                <div style="position: relative;">
                                    <i class="fa fa-phone" style="position: absolute; left: 12px; top: 15px; color: #94a3b8; font-size: 14px;"></i>
                                    <input type="text"
                                        name="phone"
                                        class="p-input-premium"
                                        value="<?php echo $c_phone; ?>"
                                        placeholder="Mobile Number"
                                        minlength="10"
                                        maxlength="10"
                                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                        style="padding-left: 35px;">
                                </div>
                            </div>
                        </div>


                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Email ID</label>
                            <div class="col-md-8">
                                <div style="position: relative;">
                                    <i class="fa fa-envelope-o" style="position: absolute; left: 12px; top: 15px; color: #94a3b8; font-size: 14px;"></i>
                                    <input type="email" name="email" class="p-input-premium" value="<?php echo $c_email; ?>" placeholder="email@example.com" style="padding-left: 35px;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Company</label>
                            <div class="col-md-8">
                                <input type="text" name="company_name" class="p-input-premium" value="<?php echo $c_company; ?>" placeholder="Organization Name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Project Name</label>
                            <div class="col-md-8">
                                <input type="text" name="project_name" class="p-input-premium" value="<?php echo $c_project; ?>" placeholder="Project Name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Budget</label>
                            <div class="col-md-8">
                                <div style="display: flex; gap: 10px;">
                                    <select name="currency" class="p-input-premium" style="width: 100px; flex-shrink: 0;">
                                        <option value="INR" <?php if ($c_currency == 'INR') echo 'selected'; ?>>₹ INR</option>
                                        <option value="USD" <?php if ($c_currency == 'USD') echo 'selected'; ?>>$ USD</option>
                                        <option value="EUR" <?php if ($c_currency == 'EUR') echo 'selected'; ?>>€ EUR</option>
                                        <option value="GBP" <?php if ($c_currency == 'GBP') echo 'selected'; ?>>£ GBP</option>
                                        <option value="AED" <?php if ($c_currency == 'AED') echo 'selected'; ?>>د.إ AED</option>
                                    </select>
                                    <input type="text" name="budget" class="p-input-premium" value="<?php echo $c_budget; ?>" placeholder="e.g. 50k, 1 Lac">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Lead Status & Source -->
                <div style="margin: 40px 0 35px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #10b981; margin: 0;"><i class="fa fa-sliders"></i> Classification & Follow-up</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Status</label>
                            <div class="col-md-8">
                                <select name="status" class="p-input-premium">
                                    <option value="active" <?php if ($c_status == 'active') echo 'selected'; ?>>Active</option>
                                    <option value="future" <?php if ($c_status == 'future') echo 'selected'; ?>>Future</option>
                                    <option value="expired" <?php if ($c_status == 'expired') echo 'selected'; ?>>Expired</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Next Follow-up</label>
                            <div class="col-md-8">
                                <input type="date" name="followup_date" class="p-input-premium" value="<?php echo $c_followup; ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <?php if (canAdminAccess('project_source_view')): ?>
                                <div class="col-md-4" style="display: flex; justify-content: flex-start; align-items: center; gap: 10px; padding-top: 7px; padding-right: 0;">
                                    <label class="control-label" style="text-align: left; color: #475569; font-weight: 600; margin: 0; padding-top: 0;">Source</label>
                                    <?php if (canAdminAccess('project_source_insert')): ?>
                                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#addSourceModal" style="border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #10b981; border: none; box-shadow: 0 2px 4px rgba(16,185,129,0.2);">
                                            <i class="fa fa-plus"></i> New
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <div style="background: #f8fafc; padding: 10px; border-radius: 12px; border: 1px solid #e2e8f0; min-height: 100px; max-height: 150px; overflow-y: auto;" id="source_checkbox_container">
                                        <?php
                                        $get_sources = "SELECT * FROM lead_sources WHERE deleted_at IS NULL ORDER BY source_name ASC";
                                        $run_sources = mysqli_query($con, $get_sources);
                                        while ($row_s = mysqli_fetch_array($run_sources)):
                                            $s_name = $row_s['source_name'];
                                            $s_id = $row_s['id'];
                                            $is_checked = in_array($s_name, $c_source) ? 'checked' : '';
                                        ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                                <label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0;">
                                                    <input type="checkbox" name="lead_source[]" value="<?php echo htmlspecialchars($s_name); ?>" <?php echo $is_checked; ?> style="margin-right: 8px; width: 16px; height: 16px; vertical-align: middle; accent-color: #dd2127;"> <?php echo htmlspecialchars($s_name); ?>
                                                </label>
                                                <?php if (canAdminAccess('project_source_delete')): ?>
                                                    <i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteSource(<?php echo $s_id; ?>, this)"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <small style="color: #94a3b8; font-size: 11px; margin-top: 5px; display: block;">Select all that apply</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section: Team Assignment -->
                <div style="margin: 40px 0 35px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #3b82f6; margin: 0;"><i class="fa fa-users"></i> Team Assignment</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Assign Employees</label>
                            <div class="col-md-8">
                                <select id="employeeSelect" name="assigned_employees[]" multiple class="p-input-premium" style="width: 100%;">
                                    <?php while ($emp = mysqli_fetch_assoc($run_emps)) {
                                        $emp_img = !empty($emp['employee_image']) ? 'uploads/' . $emp['employee_image'] : 'admin_images/default.png';
                                        $selected = in_array((string)$emp['id'], $c_assigned_employees) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $emp['id']; ?>" data-image="<?php echo $emp_img; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($emp['name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small style="color: #94a3b8; font-size: 11px; margin-top: 5px; display: block;">Select employees to assign to this lead.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Assign Admin</label>
                            <div class="col-md-8">
                                <select id="adminSelect" name="assigned_admins[]" multiple class="p-input-premium" style="width: 100%;">
                                    <?php while ($adm = mysqli_fetch_assoc($run_admins)) {
                                        $adm_img = !empty($adm['admin_image']) ? 'admin_images/' . $adm['admin_image'] : 'admin_images/default.png';
                                        $selected = in_array((string)$adm['admin_id'], $c_assigned_admins) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $adm['admin_id']; ?>" data-image="<?php echo $adm_img; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($adm['admin_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                                <small style="color: #94a3b8; font-size: 11px; margin-top: 5px; display: block;">Select admins to assign to this lead.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Remarks -->
                <div style="margin: 40px 0 35px 0; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                    <h4 style="font-weight: 700; color: #64748b; margin: 0;"><i class="fa fa-commenting-o"></i> Additional Remarks</h4>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Description</label>
                            <div class="col-md-8">
                                <textarea name="description" class="p-input-premium" style="height: 100px; padding: 12px;" placeholder="Detailed lead requirements..."><?php echo htmlspecialchars($c_desc); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="col-md-4 control-label" style="text-align: left; color: #475569; font-weight: 600;">Internal Note</label>
                            <div class="col-md-8">
                                <textarea name="remark" class="p-input-premium" style="height: 100px; padding: 12px;" placeholder="Initial internal remarks..."><?php echo htmlspecialchars($c_remark); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 50px; text-align: right; border-top: 1.5px solid #f1f5f9; padding-top: 30px;">
                    <a href="index.php?leads" class="btn-premium-cancel">Cancel</a>
                    <button type="submit" name="update_lead" class="btn-premium-add">
                        <i class="fa fa-save"></i> Update Lead Information
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Source Modal -->
<div class="modal fade" id="addSourceModal" tabindex="-1" role="dialog" aria-labelledby="addSourceModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #1e293b; color: #fff; padding: 20px 25px; border: none;">

                <h4 class="modal-title" id="addSourceModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                    <div style="background: rgba(255,255,255,0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="font-size: 14px;"></i>
                    </div>
                    Add New Source
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8; font-size: 24px; position: absolute; right: 20px; top: 20px;">&times;</button>
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <form id="add-source-form-main" onsubmit="event.preventDefault();">
                    <div style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Source Name</label>
                        <input type="text" name="source_name" id="new_source_name" placeholder="e.g. Website, LinkedIn" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#6366f1'; this.style.boxShadow='0 0 0 4px rgba(99, 102, 241, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-premium-add">
                            <i class="fa fa-save"></i> Save Source
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function showPremiumAlert(message) {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.cssText = 'position: fixed; top: 30px; right: 30px; z-index: 10000;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.style.cssText = 'background: #0f172a; color: #fff; padding: 18px 25px; border-radius: 16px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.1); min-width: 300px;';
        toast.innerHTML = `
        <div style="background: #10b981; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fa fa-check" style="font-size: 14px;"></i>
        </div>
        <div style="flex-grow: 1;">
            <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Success</div>
            <div style="font-size: 14px; font-weight: 600;">${message}</div>
        </div>
    `;
        container.appendChild(toast);
        setTimeout(() => toast.style.transform = 'translateX(0)', 10);
        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    $(document).ready(function() {
        $('#add-source-form-main').submit(function(e) {
            e.preventDefault();
            var source = $('#new_source_name').val().trim();
            if (!source) return;
            var submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: "ajax/misc/ajax_add_source.php",
                method: "POST",
                data: {
                    source_name: source
                },
                dataType: "json",
                success: function(data) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Source');
                    if (data.status == "success") {
                        var existingCheckbox = $("input[name='lead_source[]']").filter(function() {
                            return $(this).val().toLowerCase() === data.name.toLowerCase();
                        });

                        if (existingCheckbox.length > 0) {
                            existingCheckbox.prop('checked', true);
                        } else {
                            var newHtml = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">' +
                                '<label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0;">' +
                                '<input type="checkbox" name="lead_source[]" value="' + data.name + '" checked style="margin-right: 8px; width: 16px; height: 16px; vertical-align: middle; accent-color: #4f46e5;"> ' + data.name +
                                '</label>' +
                                '<i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteSource(' + data.id + ', this)"></i>' +
                                '</div>';
                            $("#source_checkbox_container").append(newHtml);
                        }

                        $('#addSourceModal [data-dismiss="modal"]').first().trigger('click');
                        $('#addSourceModal').modal('hide');
                        $('#new_source_name').val('');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open').css('padding-right', '');

                        if (typeof showPremiumAlert === 'function') {
                            showPremiumAlert("Source selected!");
                        } else {
                            Swal.fire({
                                title: 'Source Selected!',
                                text: 'The source has been checked and selected.',
                                icon: 'success',
                                confirmButtonColor: '#4f46e5',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        Swal.fire('Notification', "Error: " + data.message, 'error');
                    }
                },
                error: function() {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Source');
                    Swal.fire('Notification', "Connection Error.", 'error');
                }
            });
        });
    });

    function deleteSource(sourceId, element) {
        if (!sourceId || sourceId <= 0) {
            Swal.fire({
                title: 'Error',
                text: 'Invalid Source ID.',
                icon: 'error',
                confirmButtonColor: '#dd2127'
            });
            return;
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Source?',
                html: 'Are you sure you want to delete this source?<br><span style="font-size: 13px; color: #64748b;">This action cannot be undone.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dd2127',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa fa-trash"></i> Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "ajax/misc/ajax_delete_source.php",
                        method: "POST",
                        data: {
                            source_id: sourceId,
                            id: sourceId
                        },
                        dataType: "json",
                        success: function(data) {
                            if (data.status === "success") {
                                $(element).closest('div').remove();
                                Swal.fire({
                                    title: 'Source Deleted Successfully!',
                                    text: 'The source has been removed.',
                                    icon: 'success',
                                    confirmButtonColor: '#dd2127',
                                    confirmButtonText: 'OK',
                                    timer: 1800,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'Could not delete source.',
                                    icon: 'error',
                                    confirmButtonColor: '#dd2127'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            var errMsg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : (xhr.responseText ? xhr.responseText : 'Failed to connect to the server.');
                            Swal.fire({
                                title: 'Error',
                                text: errMsg,
                                icon: 'error',
                                confirmButtonColor: '#dd2127'
                            });
                        }
                    });
                }
            });
        } else {
            if (confirm("Do you really want to delete this source?")) {
                $.ajax({
                    url: "ajax/misc/ajax_delete_source.php",
                    method: "POST",
                    data: {
                        source_id: sourceId
                    },
                    dataType: "json",
                    success: function(data) {
                        if (data.status === "success") {
                            $(element).closest('div').remove();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    }
                });
            }
        }
    }
</script>
<script>
    $(document).ready(function() {
        function formatWithImage(item) {
            if (!item.id) {
                return item.text;
            }
            var image = $(item.element).data('image');
            if (!image) image = 'admin_images/default.png';
            var $el = $(
                '<span style="display:flex;align-items:center;gap:10px;">' +
                '<img src="' + image + '" style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;flex-shrink:0;" onerror="this.src=\'admin_images/default.png\'"> ' +
                '<span>' + item.text + '</span>' +
                '</span>'
            );
            return $el;
        }

        function formatSelectionWithImage(item) {
            if (!item.id) {
                return item.text;
            }
            var image = $(item.element).data('image');
            if (!image) image = 'admin_images/default.png';
            var $el = $(
                '<span style="display:flex;align-items:center;gap:6px;">' +
                '<img src="' + image + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;" onerror="this.src=\'admin_images/default.png\'"> ' +
                '<span>' + item.text + '</span>' +
                '</span>'
            );
            return $el;
        }

        if ($.fn.select2) {
            $('#employeeSelect').select2({
                placeholder: 'Select employees...',
                allowClear: true,
                closeOnSelect: false,
                templateResult: formatWithImage,
                templateSelection: formatSelectionWithImage
            });

            $('#adminSelect').select2({
                placeholder: 'Select admins...',
                allowClear: true,
                closeOnSelect: false,
                templateResult: formatWithImage,
                templateSelection: formatSelectionWithImage
            });

            $('#employeeSelect, #adminSelect').on('select2:select', function (e) {
                var self = this;
                setTimeout(function() {
                    var $search = $(self).data('select2').$container.find('.select2-search__field');
                    $search.val('').trigger('input');
                }, 0);
            });
        }
    });
</script>