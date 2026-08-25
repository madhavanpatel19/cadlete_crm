<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    /* Select2 Custom Responsive & Design Styling */
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

    /* Premium Label Styling */
    .premium-label {
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: block;
    }

    .upload-area:hover {
        border-color: #dd2127;
    }

    /* Common Unified Focus Styles for All Inputs, Selects, Textareas, Phase Inputs & Budget Wrapper */
    .p-input-premium:focus,
    .form-control:focus,
    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="date"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus,
    input[type="url"]:focus,
    select:focus,
    textarea:focus,
    .budget-input-wrapper:focus-within {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
        outline: none !important;
    }

    /* Reset inner Select2 search input box so no outline/border/pink box appears around cursor */
    .select2-search__field,
    .select2-search__field:focus,
    .select2-search--inline .select2-search__field:focus {
        border: none !important;
        box-shadow: none !important;
        outline: none !important;
        background: transparent !important;
    }

    /* Select2 Container Focus Styling */
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
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

// File Upload Function for Projects
if (!function_exists('handleProjectImageUpload')) {
    /**
     * @param array $fileArray
     * @param string $targetDir
     * @return string
     */
    function handleProjectImageUpload(array $fileArray, string $targetDir = __DIR__ . "/../../uploads/project_images/")
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        if (isset($fileArray) && isset($fileArray['error']) && $fileArray['error'] == 0) {
            $file_name = $fileArray['name'];
            $tmp_name = $fileArray['tmp_name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_path = $targetDir . $new_name;

            if (move_uploaded_file($tmp_name, $target_path)) {
                return $new_name;
            }
        }
        return '';
    }
}

$success = false;
$error = '';
$project_name = '';

if (isset($_POST['submit_project'])) {
    $project_name = mysqli_real_escape_string($con, $_POST['project_name']);
    $client_id = mysqli_real_escape_string($con, $_POST['client_id']);
    $project_desc = mysqli_real_escape_string($con, $_POST['project_desc']);
    $start_date_raw = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $start_date     = !empty($start_date_raw) ? date('Y-m-d', strtotime($start_date_raw)) : '';
    $deadline_raw   = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
    $deadline       = !empty($deadline_raw) ? date('Y-m-d', strtotime($deadline_raw)) : '';
    $status         = mysqli_real_escape_string($con, $_POST['status']);

    // Budget Fields
    $currency = mysqli_real_escape_string($con, $_POST['currency']);
    $budget = mysqli_real_escape_string($con, $_POST['budget']);
    if (empty($budget)) $budget = 0;

    $assigned_employees = isset($_POST['assigned_employees']) ? implode(',', $_POST['assigned_employees']) : '';
    $assigned_employees = mysqli_real_escape_string($con, $assigned_employees);

    $assigned_users = isset($_POST['assigned_users']) ? implode(',', $_POST['assigned_users']) : '';
    $assigned_users = mysqli_real_escape_string($con, $assigned_users);

    $assigned_admins = isset($_POST['assigned_admins']) ? implode(',', $_POST['assigned_admins']) : '';
    $assigned_admins = mysqli_real_escape_string($con, $assigned_admins);

    $project_image = handleProjectImageUpload($_FILES['project_image']);

    $source = isset($_POST['project_source']) ? implode(', ', $_POST['project_source']) : '';
    $source = mysqli_real_escape_string($con, $source);

    $insert_project = "INSERT INTO client_projects (client_id, project_name, project_date, deadline, status, project_desc, project_image, assigned_employees, assigned_users, assigned_admins, currency, budget, source) 
                       VALUES ('$client_id', '$project_name', '$start_date', '$deadline', '$status', '$project_desc', '$project_image', '$assigned_employees', '$assigned_users', '$assigned_admins', '$currency', '$budget', '$source')";

    if (mysqli_query($con, $insert_project)) {
        $project_id = mysqli_insert_id($con);

        // Send notifications to assigned employees
        require_once __DIR__ . '/../../includes/notification_helper.php';
        $assigned_ids = array_filter(explode(',', $assigned_employees), function($id) { return !empty(trim($id)); });
        foreach ($assigned_ids as $eid) {
            $eid = intval($eid);
            if ($eid > 0) {
                addSystemNotification('employee', $eid, "Assigned to Project: $project_name", "You have been assigned to project '$project_name'.", "index.php?team_todo&project_id=$project_id", 'project_assigned');
            }
        }

        // Insert Phases
        if (isset($_POST['phase_name']) && is_array($_POST['phase_name'])) {
            foreach ($_POST['phase_name'] as $key => $p_name) {
                $p_name_esc = mysqli_real_escape_string($con, $p_name);
                $p_desc_esc = mysqli_real_escape_string($con, $_POST['phase_desc'][$key]);
                $p_date_esc = mysqli_real_escape_string($con, $_POST['phase_date'][$key]);
                $p_cost_esc = mysqli_real_escape_string($con, $_POST['phase_cost'][$key]);

                if (!empty($p_name_esc)) {
                    $insert_phase = "INSERT INTO project_budget_phases (project_id, phase_name, description, expected_date, cost) 
                                     VALUES ('$project_id', '$p_name_esc', '$p_desc_esc', " . (!empty($p_date_esc) ? "'$p_date_esc'" : "NULL") . ", '$p_cost_esc')";
                    mysqli_query($con, $insert_phase);
                }
            }
        }

        // Insert General Documents
        if (isset($_FILES['doc_file']) && is_array($_FILES['doc_file']['name'])) {
            $docDir = __DIR__ . "/../../uploads/project_documents/";
            if (!is_dir($docDir)) mkdir($docDir, 0777, true);
            foreach ($_FILES['doc_file']['name'] as $key => $fileName) {
                $docName = mysqli_real_escape_string($con, $_POST['doc_name'][$key]);
                $tmpName = $_FILES['doc_file']['tmp_name'][$key];
                $error = $_FILES['doc_file']['error'][$key];

                if ($error == 0 && !empty($fileName)) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newName = time() . '_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $docDir . $newName)) {
                        $q = "INSERT INTO project_documents (project_id, document_name, file_path, is_proposal) VALUES ('$project_id', '$docName', '$newName', 0)";
                        mysqli_query($con, $q);
                    }
                }
            }
        }

        // Insert Proposal Documents (Super Admin & Admin)
        if ((isSuperAdmin() || isset($_SESSION['admin_email'])) && isset($_FILES['proposal_file']) && is_array($_FILES['proposal_file']['name'])) {
            $docDir = __DIR__ . "/../../uploads/project_documents/";
            if (!is_dir($docDir)) mkdir($docDir, 0777, true);
            foreach ($_FILES['proposal_file']['name'] as $key => $fileName) {
                $pName = isset($_POST['proposal_name'][$key]) ? trim(mysqli_real_escape_string($con, $_POST['proposal_name'][$key])) : '';
                if (empty($pName)) $pName = "Project Proposal";
                $tmpName = $_FILES['proposal_file']['tmp_name'][$key];
                $error = $_FILES['proposal_file']['error'][$key];

                if ($error == 0 && !empty($fileName)) {
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newName = time() . '_proposal_' . rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($tmpName, $docDir . $newName)) {
                        $q = "INSERT INTO project_documents (project_id, document_name, file_path, is_proposal) VALUES ('$project_id', '$pName', '$newName', 1)";
                        mysqli_query($con, $q);
                    }
                }
            }
        }

        // Insert Links
        if (isset($_POST['link_name']) && is_array($_POST['link_name'])) {
            foreach ($_POST['link_name'] as $key => $lName) {
                $link_name_esc = mysqli_real_escape_string($con, $lName);
                $link_url_esc = mysqli_real_escape_string($con, $_POST['link_url'][$key]);
                if (!empty($link_name_esc) && !empty($link_url_esc)) {
                    $q = "INSERT INTO project_links (project_id, link_name, link_url) VALUES ('$project_id', '$link_name_esc', '$link_url_esc')";
                    mysqli_query($con, $q);
                }
            }
        }

        $success = true;
    } else {
        $error = "Error: " . mysqli_error($con);
    }
}

if ($success): ?>
    <link rel="stylesheet" href="css/success_notification.css">
    <div class="success-modal-overlay" id="successModal">
        <div class="success-modal-content">
            <div class="success-icon-wrapper">
                <i class="fa fa-check"></i>
            </div>
            <h2 class="success-title">Success!</h2>
            <p class="success-message">Project <strong><?php echo htmlspecialchars($project_name); ?></strong> has been created successfully.</p>
            <div class="success-actions">
                <a href="index.php?projects" class="btn-success-go">
                    <i class="fa fa-list"></i> Go to Projects
                </a>
            </div>
            <div class="success-timer-bar" style="animation-duration: 4s;"></div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('successModal');
            setTimeout(() => {
                modal.classList.add('active');
            }, 100);
            setTimeout(() => {
                window.location.href = 'index.php?projects';
            }, 4000);
        });
    </script>
<?php exit();
endif; ?>

<?php
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

$current_admin_id = 0;
if (isset($_SESSION['admin_email'])) {
    $admin_email = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $get_curr_admin = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email = '$admin_email' LIMIT 1");
    if ($row_curr = mysqli_fetch_assoc($get_curr_admin)) {
        $current_admin_id = $row_curr['admin_id'];
    }
}

$get_clients = "SELECT id, name, image FROM clients WHERE deleted_at IS NULL ORDER BY name ASC";
$run_clients = mysqli_query($con, $get_clients);

$get_emps = "SELECT id, employee_image, name FROM emp_list WHERE deleted_at IS NULL ORDER BY name ASC";
$run_emps = mysqli_query($con, $get_emps);

$get_users = "SELECT id, employee_image, name FROM emp_list WHERE deleted_at IS NULL ORDER BY name ASC";
$run_users = mysqli_query($con, $get_users);

$get_admins = "SELECT admin_id, admin_image, admin_name FROM admins ORDER BY admin_name ASC";
$run_admins = mysqli_query($con, $get_admins);
?>

<div class="page-wrapper premium-ui-enabled">
    <!-- <div style="padding: 20px 30px; margin-bottom: 20px;">
        <a href="index.php?projects" style="color: #6366f1; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fa fa-chevron-left" style="font-size: 12px;"></i> Back to Projects
        </a>
        <h1 style="font-size: 28px; font-weight: 800; color: #0f172a; margin: 15px 0 5px 0;">Add New Project</h1>
        <p style="color: #64748b; font-size: 15px; margin: 0;">Create a new project and track it efficiently</p>
    </div> -->

    <form method="POST" id="add_project_form" enctype="multipart/form-data">

        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    1
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Project Information</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Enter project details below.</p>
                </div>
            </div>

            <div style="padding: 30px;">

                <div class="row">
                    <!-- Image Upload -->
                    <div class="col-md-4">
                        <label class="premium-label" style="font-size: 14px; color: #334155;">Project Image</label>
                        <div class="upload-area" style="border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; background: #f8fafc; position: relative; transition: 0.3s;">
                            <div style="width: 48px; height: 48px; background: #eff6ff; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; color: #dd2127; font-size: 20px; margin-bottom: 15px;">
                                <i class="fa fa-image"></i>
                            </div>
                            <h4 style="margin: 0 0 5px 0; font-size: 15px; font-weight: 700; color: #1e293b;">Upload project image</h4>
                            <p style="margin: 0 0 15px 0; font-size: 12px; color: #64748b;">JPG, PNG up to 5MB</p>

                            <label for="project_image" class="btn btn-outline-primary" style="background: #fff; border: 1px solid #e2e8f0; color: #dd2127; font-weight: 600; padding: 8px 20px; border-radius: 8px; cursor: pointer;">
                                Choose File
                            </label>
                            <input type="file" name="project_image" id="project_image" style="display: none;" accept="image/*" onchange="previewImage(this)">

                            <!-- Preview overlay -->
                            <div id="image_preview_container" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #fff; border-radius: 12px; overflow: hidden;">
                                <img id="project_preview" src="" style="width: 100%; height: 100%; object-fit: cover;">
                                <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.5); color: #fff; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="removeImage()">
                                    <i class="fa fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Project Name <span style="color: #ef4444;">*</span></label>
                                    <input type="text" name="project_name" class="p-input-premium" style="height: 48px;" placeholder="Enter project name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="premium-label">
                                        Client Name <span style="color:red">*</span>
                                    </label>

                                    <select id="clientSelect" name="client_id" required>
                                        <option value=""></option>

                                        <?php while ($client = mysqli_fetch_assoc($run_clients)) {
                                            $client_img = !empty($client['image']) ? 'uploads/client_images/' . $client['image'] : 'admin_images/default.png';
                                            $selected = (isset($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $client['id']; ?>"
                                                data-image="<?php echo $client_img; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group" style="margin-bottom: 20px;">
                                    <label class="premium-label" style="font-size: 14px; color: #334155;">Project Description</label>
                                    <textarea name="project_desc" class="p-input-premium" style="height: 100px; border-radius: 8px; border: 1px solid #e2e8f0; resize: none;" placeholder="Enter project description..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Start Date <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="start_date" class="p-input-premium" style="height: 48px; border-radius: 8px; border: 1px solid #e2e8f0;" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Deadline <span style="color: #ef4444;">*</span></label>
                            <input type="date" name="deadline" class="p-input-premium" style="height: 48px; border-radius: 8px; border: 1px solid #e2e8f0;" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="premium-label" style="font-size: 14px; color: #334155;">Initial Status <span style="color: #ef4444;">*</span></label>
                            <select name="status" class="p-input-premium" style="height: 48px; border-radius: 8px; border: 1px solid #e2e8f0;" required>
                                <option value="">Select status</option>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-6">
                        <!-- Assign Employees -->
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label class="premium-label" style="font-size:14px; color:#334155;">
                                Assign Employees <span style="color:#ef4444;">*</span>
                            </label>
                            <select id="employeeSelect" name="assigned_employees[]" multiple required>
                                <?php while ($emp = mysqli_fetch_assoc($run_emps)) {
                                    $emp_img = !empty($emp['employee_image']) ? 'uploads/' . $emp['employee_image'] : 'admin_images/default.png';
                                ?>
                                    <option value="<?php echo $emp['id']; ?>" data-image="<?php echo $emp_img; ?>">
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">
                                Select one or more employees for this project.
                            </small>
                        </div>

                        <!-- Assign Admin -->
                        <div class="form-group">
                            <label class="premium-label" style="font-size:14px; color:#334155;">
                                Assign Admin
                            </label>
                            <select id="adminSelect" name="assigned_admins[]" multiple>
                                <?php while ($adm = mysqli_fetch_assoc($run_admins)) {
                                    $adm_img = !empty($adm['admin_image']) ? 'admin_images/' . $adm['admin_image'] : 'admin_images/default.png';
                                    $selected = ($adm['admin_id'] == $current_admin_id) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $adm['admin_id']; ?>" data-image="<?php echo $adm_img; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($adm['admin_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">
                                Select admins to assign to this project.
                            </small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <?php if (canAdminAccess('project_source_view')): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <label class="premium-label" style="font-size:14px; color:#334155; margin: 0;">Source</label>
                                    <?php if (canAdminAccess('project_source_insert')): ?>
                                        <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#addSourceModal" style="border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #10b981; border: none; box-shadow: 0 2px 4px rgba(16,185,129,0.2);">
                                            <i class="fa fa-plus"></i> New
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; min-height: 48px; max-height: 150px; overflow-y: auto;" id="source_checkbox_container">
                                    <?php
                                    $get_sources = "SELECT * FROM lead_sources WHERE deleted_at IS NULL ORDER BY source_name ASC";
                                    $run_sources = mysqli_query($con, $get_sources);
                                    while ($row_s = mysqli_fetch_array($run_sources)):
                                        $s_name = $row_s['source_name'];
                                        $s_id = $row_s['id'];
                                    ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                            <label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0;">
                                                <input type="checkbox" name="project_source[]" value="<?php echo htmlspecialchars($s_name); ?>" style="margin-right: 8px; width: 16px; height: 16px; vertical-align: middle; accent-color: #dd2127;"> <?php echo htmlspecialchars($s_name); ?>
                                            </label>
                                            <?php if (canAdminAccess('project_source_delete')): ?>
                                                <i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteSource(<?php echo $s_id; ?>, this)"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">Select all that apply</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div> <!-- End First Card -->

        <!-- Second Card: Project Budget -->
        <?php if (canAdminAccess('budget_view')): ?>
            <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
                <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                        2
                    </div>
                    <div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Project Budget</h3>
                        <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Define total budget and phase-wise deliverables</p>
                    </div>
                </div>

                <div style="padding: 30px;">
                    <div class="row" style="margin-bottom: 25px;">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="premium-label">Currency <span style="color:#ef4444">*</span></label>
                                <select name="currency" class="p-input-premium" style="height:48px; border-radius:8px; border:1px solid #e2e8f0; width:100%;" required>
                                    <option value="INR">INR - Indian Rupee (₹)</option>
                                    <option value="USD">USD - US Dollar ($)</option>
                                    <option value="EUR">EUR - Euro (€)</option>
                                    <option value="GBP">GBP - British Pound (£)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="premium-label">Total Project Budget <span style="color:#ef4444">*</span></label>
                                <div class="budget-input-wrapper" style="display:flex; align-items:center; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; transition: all 0.2s;">
                                    <div style="background:#f8fafc; padding:0 15px; height:48px; display:flex; align-items:center; border-right:1px solid #e2e8f0; color:#64748b; font-weight:600;" id="currency_symbol">₹</div>
                                    <input type="number" name="budget" id="total_budget" class="p-input-premium" style="height:48px; border:none; width:100%; outline:none; padding:0 15px;" placeholder="Enter total budget" required min="0" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Phase Wise Breakdown -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <div style="padding: 15px 20px; border-bottom: 1px solid #e2e8f0; background: #f1f5f9;">
                            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #334155;">Phase Wise Breakdown</h4>
                        </div>

                        <div style="padding: 0;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Phase / Deliverable</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Description</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Expected Date</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Cost</th>
                                        <th style="padding: 12px 20px; text-align: center; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="phase_container">
                                    <tr class="phase-row" style="border-top: 1px solid #e2e8f0;">
                                        <td style="padding: 15px 20px;">
                                            <input type="text" name="phase_name[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="Enter phase name" value="Phase 1" required>
                                        </td>
                                        <td style="padding: 15px 20px;">
                                            <input type="text" name="phase_desc[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="Enter description">
                                        </td>
                                        <td style="padding: 15px 20px;">
                                            <input type="date" name="phase_date[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;">
                                        </td>
                                        <td style="padding: 15px 20px;">
                                            <input type="number" name="phase_cost[]" class="p-input-premium phase-cost" style="height: 42px; border-radius: 6px; width: 100%; text-align:right;" placeholder="0.00" min="0" step="0.01">
                                        </td>
                                        <td style="padding: 15px 20px; text-align: center;">
                                            <button type="button" class="btn btn-light text-danger delete-phase-btn" style="width:36px; height:36px; border-radius:6px; border:none; background:#fee2e2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center;">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div style="padding: 15px 20px; border-top: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center;">
                            <button type="button" id="add_phase_btn" class="btn btn-info btn-sm">
                                <i class="fa fa-plus"></i> Add New Phase
                            </button>

                            <div style="display:flex; align-items:center; gap:15px;">
                                <span style="color:#64748b; font-size:14px; font-weight:600;">Total Cost</span>
                                <span id="calculated_total_cost" style="color:#10b981; font-size:20px; font-weight:800;">₹ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- End Second Card -->
        <?php endif; ?>

        <!-- Third Card: Project Documents & Links -->
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #dd2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    3
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Project Documents & Links</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Upload important documents and add useful links</p>
                </div>
            </div>

            <div style="padding: 30px;">

                <!-- Files Section -->
                <div style="margin-bottom: 40px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Files</h4>
                        <!-- <button type="button" id="add_doc_btn" style="background: #f8fafc; color: #dd2127; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: 0.2s;">
                            <i class="fa fa-plus"></i> Add Document
                        </button> -->
                    </div>

                    <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; width: 45%;">Document Name</th>
                                    <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; width: 45%;">Select File</th>
                                    <th style="padding: 12px 20px; text-align: center; font-size: 12px; font-weight: 600; color: #64748b; width: 10%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="docs_container">
                                <tr style="border-top: 1px solid #e2e8f0;">
                                    <td colspan="3" style="padding: 20px; text-align: center; color: #94a3b8; font-size: 13px;" id="no_docs_msg">No documents added yet. Click "+ Add Document" to add one.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="padding: 15px 20px; border-top: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" id="add_doc_btn" class="btn btn-info btn-sm">
                            <i class="fa fa-plus"></i> Add Document
                        </button>
                    </div>

                    <!-- Links Section -->
                    <div style="margin-top: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b;">Links</h4>
                        </div>

                        <div style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; width: 45%;">Link Name</th>
                                        <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; width: 45%;">URL</th>
                                        <th style="padding: 12px 20px; text-align: center; font-size: 12px; font-weight: 600; color: #64748b; width: 10%;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="links_container">
                                    <tr style="border-top: 1px solid #e2e8f0;">
                                        <td colspan="3" style="padding: 20px; text-align: center; color: #94a3b8; font-size: 13px;" id="no_links_msg">No links added yet. Click "+ Add Link" to add one.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div style="padding: 15px 20px; border-top: 1px solid #e2e8f0; background: #fff; display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" id="add_link_btn" class="btn btn-info btn-sm">
                            <i class="fa fa-plus"></i> Add Link
                        </button>
                    </div>

                    <?php if (isSuperAdmin() || isset($_SESSION['admin_email'])): ?>
                        <!-- Project Proposal Section (Super Admin & Assigned Admin) -->
                        <div style="margin-top: 40px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #991b1b; display: flex; align-items: center; gap: 8px;">
                                    <i class="fa fa-lock" style="color: #dc2626;"></i> Project Proposal
                                    <span style="font-size: 10px; font-weight: 800; background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 6px; text-transform: uppercase;">Super Admin & Assigned Admin</span>
                                </h4>
                            </div>

                            <div style="border: 1.5px dashed #fecaca; border-radius: 8px; overflow: hidden; background: #fff5f5;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead style="background: #fef2f2;">
                                        <tr>
                                            <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #991b1b; width: 45%;">Proposal Name</th>
                                            <th style="padding: 12px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #991b1b; width: 45%;">Select Proposal File</th>
                                            <th style="padding: 12px 20px; text-align: center; font-size: 12px; font-weight: 600; color: #991b1b; width: 10%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="proposals_container">
                                        <tr style="border-top: 1px solid #fee2e2;">
                                            <td colspan="3" style="padding: 20px; text-align: center; color: #991b1b; font-size: 13px;" id="no_proposals_msg">No proposal files added yet. Click "+ Add Proposal" to add one.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div style="padding: 15px 20px; border-top: 1px solid #fee2e2; background: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 8px 8px;">
                                <button type="button" id="add_proposal_btn" class="btn btn-danger btn-sm" style="background: #dc2626; border-color: #dc2626;">
                                    <i class="fa fa-plus"></i> Add Proposal
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" style="margin-top: 20px; border-radius: 8px;">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div style="margin-top: 40px; display: flex; justify-content: flex-end; gap: 15px;">
                        <a href="index.php?projects" class="btn-premium-cancel">
                            Cancel
                        </a>
                        <button type="submit" name="submit_project" class="btn-premium-add">
                            Add Project
                        </button>
                    </div>
                </div>
            </div>

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
        border-color: #dd2127;
    }

    /* Common Unified Focus Styles for All Inputs, Selects, Textareas, Phase Inputs & Budget Wrapper */
    .p-input-premium:focus,
    .form-control:focus,
    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="date"]:focus,
    input[type="email"]:focus,
    input[type="password"]:focus,
    input[type="url"]:focus,
    input[type="search"]:focus,
    select:focus,
    textarea:focus,
    .budget-input-wrapper:focus-within {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
        outline: none !important;
    }

    /* Select2 container focus styling */
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--multiple,
    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--multiple {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
        outline: none !important;
    }
</style>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('project_preview').src = e.target.result;
                document.getElementById('image_preview_container').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        document.getElementById('project_image').value = "";
        document.getElementById('image_preview_container').style.display = 'none';
        document.getElementById('project_preview').src = "";
    }

    $(document).ready(function() {

        function formatWithImage(item) {
            if (!item.id) {
                return item.text;
            }
            var image = $(item.element).data('image');
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
            var $el = $(
                '<span style="display:flex;align-items:center;gap:6px;">' +
                '<img src="' + image + '" style="width:20px;height:20px;border-radius:50%;object-fit:cover;" onerror="this.src=\'admin_images/default.png\'"> ' +
                '<span>' + item.text + '</span>' +
                '</span>'
            );
            return $el;
        }

        $('#clientSelect').select2({
            placeholder: 'Type client name...',
            allowClear: true,
            templateResult: formatWithImage,
            templateSelection: formatSelectionWithImage
        });

        $('#employeeSelect').select2({
            placeholder: 'Select employees...',
            allowClear: true,
            closeOnSelect: false,
            templateResult: formatWithImage,
            templateSelection: formatSelectionWithImage
        });

        $('#userSelect').select2({
            placeholder: 'Select users...',
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

        $('#employeeSelect, #userSelect, #adminSelect').on('select2:select', function (e) {
            var self = this;
            setTimeout(function() {
                var $search = $(self).data('select2').$container.find('.select2-search__field');
                $search.val('').trigger('input');
            }, 0);
        });

        // Dynamic Phases Script
        const symbols = {
            'INR': '₹',
            'USD': '$',
            'EUR': '€',
            'GBP': '£'
        };

        $('select[name="currency"]').on('change', function() {
            const sym = symbols[$(this).val()] || '';
            $('#currency_symbol').text(sym);
            calculateTotalCost();
        });

        function calculateTotalCost() {
            let total = 0;
            $('.phase-cost').each(function() {
                const rawVal = $(this).val();
                if (rawVal) {
                    const val = parseFloat(rawVal.toString().replace(/,/g, ''));
                    if (!isNaN(val)) {
                        total += val;
                    }
                }
            });
            const sym = symbols[$('select[name="currency"]').val()] || '₹';
            $('#calculated_total_cost').text(sym + ' ' + total.toFixed(2));

            // Check if it matches total budget
            const totalBudget = parseFloat($('#total_budget').val()) || 0;
            if (total > totalBudget && totalBudget > 0) {
                $('#calculated_total_cost').css('color', '#ef4444');
            } else {
                $('#calculated_total_cost').css('color', '#10b981');
            }
        }

        $(document).on('input change keyup', '.phase-cost, #total_budget', function() {
            calculateTotalCost();
        });

        // Trigger initial calculation on page load
        calculateTotalCost();

        $('#add_phase_btn').on('click', function() {
            const nextPhaseNum = $('.phase-row').length + 1;
            const newRow = `
                <tr class="phase-row" style="border-top: 1px solid #e2e8f0;">
                    <td style="padding: 15px 20px;">
                        <input type="text" name="phase_name[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="Enter phase name" value="Phase ${nextPhaseNum}" required>
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="text" name="phase_desc[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="Enter description">
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="date" name="phase_date[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;">
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="number" name="phase_cost[]" class="p-input-premium phase-cost" style="height: 42px; border-radius: 6px; width: 100%; text-align:right;" placeholder="0.00" min="0" step="0.01">
                    </td>
                    <td style="padding: 15px 20px; text-align: center;">
                        <button type="button" class="btn btn-light text-danger delete-phase-btn" style="width:36px; height:36px; border-radius:6px; border:none; background:#fee2e2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#phase_container').append(newRow);
        });

        $(document).on('click', '.delete-phase-btn', function() {
            if ($('.phase-row').length > 1) {
                $(this).closest('tr').remove();
                calculateTotalCost();
            } else {
                Swal.fire('Notification', 'You must have at least one phase.', 'info');
            }
        });

        // Add Document Row
        $('#add_doc_btn').on('click', function() {
            $('#no_docs_msg').closest('tr').hide();
            const newDoc = `
                <tr class="doc-row" style="border-top: 1px solid #e2e8f0;">
                    <td style="padding: 15px 20px;">
                        <input type="text" name="doc_name[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="e.g. Design Spec" required>
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="file" name="doc_file[]" style="width: 100%;" required>
                    </td>
                    <td style="padding: 15px 20px; text-align: center;">
                        <button type="button" class="btn btn-light text-danger delete-doc-btn" style="width:36px; height:36px; border-radius:6px; border:none; background:#fee2e2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#docs_container').append(newDoc);
        });

        $(document).on('click', '.delete-doc-btn', function() {
            $(this).closest('tr').remove();
            if ($('.doc-row').length === 0) {
                $('#no_docs_msg').closest('tr').show();
            }
        });

        // Add Proposal Row (Super Admin Only)
        $('#add_proposal_btn').on('click', function() {
            $('#no_proposals_msg').closest('tr').hide();
            const newProp = `
                <tr class="proposal-row" style="border-top: 1px solid #fee2e2;">
                    <td style="padding: 15px 20px;">
                        <input type="text" name="proposal_name[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%; border-color: #fecaca;" value="Project Proposal" placeholder="Project Proposal">
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="file" name="proposal_file[]" style="width: 100%;" required>
                    </td>
                    <td style="padding: 15px 20px; text-align: center;">
                        <button type="button" class="btn btn-light text-danger delete-proposal-btn" style="width:36px; height:36px; border-radius:6px; border:none; background:#fee2e2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#proposals_container').append(newProp);
        });

        $(document).on('click', '.delete-proposal-btn', function() {
            $(this).closest('tr').remove();
            if ($('.proposal-row').length === 0) {
                $('#no_proposals_msg').closest('tr').show();
            }
        });

        // Add Link Row
        $('#add_link_btn').on('click', function() {
            $('#no_links_msg').closest('tr').hide();
            const newLink = `
                <tr class="link-row" style="border-top: 1px solid #e2e8f0;">
                    <td style="padding: 15px 20px;">
                        <input type="text" name="link_name[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="e.g. Figma Design" required>
                    </td>
                    <td style="padding: 15px 20px;">
                        <input type="url" name="link_url[]" class="p-input-premium" style="height: 42px; border-radius: 6px; width: 100%;" placeholder="https://" required>
                    </td>
                    <td style="padding: 15px 20px; text-align: center;">
                        <button type="button" class="btn btn-light text-danger delete-link-btn" style="width:36px; height:36px; border-radius:6px; border:none; background:#fee2e2; color:#ef4444; display:inline-flex; align-items:center; justify-content:center;">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $('#links_container').append(newLink);
        });

        $(document).on('click', '.delete-link-btn', function() {
            $(this).closest('tr').remove();
            if ($('.link-row').length === 0) {
                $('#no_links_msg').closest('tr').show();
            }
        });

    });
</script>

<!-- Add Source Modal -->
<div class="modal fade" id="addSourceModal" tabindex="-1" role="dialog" aria-labelledby="addSourceModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
                <h4 class="modal-title" id="addSourceModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                    <div style="background: #DD2127; color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="font-size: 14px;"></i>
                    </div>
                    Add New Source
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <form id="add-source-form-main" onsubmit="event.preventDefault();">
                    <div style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Source Name</label>
                        <input type="text" name="source_name" id="new_source_name" placeholder="e.g. Website, LinkedIn" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                        <button type="button" data-dismiss="modal" class="btn-premium-cancel">
                            Cancel
                        </button>
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
                success: function(response) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Source');
                    var data;
                    try {
                        data = typeof response === 'object' ? response : JSON.parse(response.trim());
                    } catch (e) {
                        Swal.fire('Notification', "Server response error: " + response, 'error');
                        return;
                    }
                    if (data.status == "success") {
                        var existingCheckbox = $("input[name='project_source[]']").filter(function() {
                            return $(this).val().toLowerCase() === data.name.toLowerCase();
                        });

                        if (existingCheckbox.length > 0) {
                            existingCheckbox.prop('checked', true);
                        } else {
                            var newHtml = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">' +
                                '<label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0;">' +
                                '<input type="checkbox" name="project_source[]" value="' + data.name + '" checked style="margin-right: 8px; width: 16px; height: 16px; vertical-align: middle; accent-color: #dd2127;"> ' + data.name +
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
                        }
                    } else {
                        Swal.fire('Notification', "Error: " + data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Source');
                    Swal.fire('Notification', "Connection Error. Details: " + xhr.responseText, 'error');
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

    function showPremiumAlert(message) {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.position = 'fixed';
            container.style.bottom = '20px';
            container.style.right = '20px';
            container.style.zIndex = '999999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.background = '#1e293b';
        toast.style.color = '#fff';
        toast.style.padding = '16px 24px';
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.gap = '12px';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '600';
        toast.style.transform = 'translateY(100px) scale(0.9)';
        toast.style.opacity = '0';
        toast.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';

        toast.innerHTML = `
        <div style="width: 24px; height: 24px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fa fa-check" style="font-size: 12px;"></i>
        </div>
        ${message}
    `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateY(0) scale(1)';
            toast.style.opacity = '1';
        }, 10);

        setTimeout(() => {
            toast.style.transform = 'translateY(20px) scale(0.9)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }
</script>