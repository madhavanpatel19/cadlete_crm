<?php
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

$client_id = isset($_GET['edit_client']) ? intval($_GET['edit_client']) : 0;
if ($client_id == 0) {
    echo "<script>window.location.href='index.php?client_directory';</script>";
    exit();
}

$get_client = "SELECT * FROM clients WHERE id = $client_id";
$run_client = mysqli_query($con, $get_client);
$client_data = mysqli_fetch_assoc($run_client);

if (!$client_data) {
    echo "<script>window.location.href='index.php?client_directory';</script>";
    exit();
}

// File Upload Function for Clients
if (!function_exists('handleClientImageUpload')) {
    function handleClientImageUpload($fileArray, $oldImg, $targetDir = "../uploads/client_images/")
    {
        if (isset($fileArray) && $fileArray['error'] == 0) {
            $file_name = $fileArray['name'];
            $tmp_name = $fileArray['tmp_name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target_path = $targetDir . $new_name;

            if (move_uploaded_file($tmp_name, $target_path)) {
                // Delete old image
                if (!empty($oldImg) && file_exists($targetDir . $oldImg)) {
                    unlink($targetDir . $oldImg);
                }
                return $new_name;
            }
        }
        return $oldImg;
    }
}

$success = false;
$error = '';

if (isset($_POST['update_client'])) {
    $name = mysqli_real_escape_string($con, $_POST['name']);
    $mobile = mysqli_real_escape_string($con, $_POST['mobile']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $country = mysqli_real_escape_string($con, $_POST['country']);
    $company_name = mysqli_real_escape_string($con, $_POST['company_name']);
    $website = mysqli_real_escape_string($con, $_POST['website']);

    $industry = isset($_POST['industry']) ? implode(', ', $_POST['industry']) : '';
    $industry = mysqli_real_escape_string($con, $industry);

    $client_image = handleClientImageUpload($_FILES['client_image'], $client_data['image']);

    $update_client = "UPDATE clients SET 
                      image='$client_image', 
                      name='$name', 
                      mobile='$mobile', 
                      email='$email', 
                      country='$country', 
                      company_name='$company_name', 
                      website='$website',
                      industry='$industry' 
                      WHERE id=$client_id";

    if (mysqli_query($con, $update_client)) {
        $success = true;
    } else {
        $error = "Error: " . mysqli_error($con);
    }
}

if ($success): ?>
    <style>
        .success-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .success-modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .success-modal-content {
            background: #fff;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            transform: translateY(20px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .success-modal-overlay.active .success-modal-content {
            transform: translateY(0) scale(1);
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background: #dcfce7;
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease 0.2s both;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
            }

            100% {
                transform: scale(1);
            }
        }

        .success-title {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .success-message {
            font-size: 16px;
            color: #475569;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .btn-success-go {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            border-radius: 12px;
            transition: 0.3s;
        }

        .btn-success-go:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            color: #fff;
        }

        .success-timer-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 6px;
            background: #22c55e;
            width: 100%;
            animation: timer 4s linear forwards;
        }

        @keyframes timer {
            0% {
                width: 100%;
            }

            100% {
                width: 0%;
            }
        }
    </style>
    <div class="success-modal-overlay" id="successModal">
        <div class="success-modal-content">
            <div class="success-icon-wrapper">
                <i class="fa fa-check"></i>
            </div>
            <h2 class="success-title">Updated!</h2>
            <p class="success-message">Client <strong><?php echo htmlspecialchars($name); ?></strong> has been updated successfully.</p>
            <div class="success-actions">
                <a href="index.php?client_directory" class="btn-success-go">
                    <i class="fa fa-users"></i> Go to Client Directory
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
                window.location.href = 'index.php?client_directory';
            }, 4000);
        });
    </script>
<?php exit();
endif; ?>

<style>
    .add-client-wrapper {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        padding: 40px;
        margin-top: 20px;
        width: 100%;
        box-sizing: border-box;
    }

    .section-header-custom {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f1f5f9;
    }

    .step-badge {
        width: 32px;
        height: 32px;
        background: #dc2626;
        color: #fff;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 14px;
    }

    .section-title-custom {
        margin: 0;
        font-weight: 800;
        color: #1e293b;
        font-size: 18px;
    }

    .section-subtitle-custom {
        margin: 4px 0 0 0;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }

    .form-group-custom {
        margin-bottom: 24px;
    }

    .form-label-custom {
        display: block;
        font-weight: 700;
        color: #334155;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .form-label-custom .req {
        color: #dc2626;
    }

    .p-input-premium {
        width: 100%;
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        padding: 12px 16px;
        font-size: 14px;
        color: #0f172a;
        transition: 0.3s;
        box-sizing: border-box;
    }

    .p-input-premium:focus {
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
        outline: none;
    }

    .upload-box {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 40px 20px;
        text-align: center;
        background: #f8fafc;
        position: relative;
        transition: 0.3s;
        height: calc(100% - 28px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 220px;
        cursor: pointer;
    }

    .upload-box:hover {
        border-color: #94a3b8;
        background: #f1f5f9;
    }

    .upload-icon {
        font-size: 32px;
        color: #dc2626;
        margin-bottom: 15px;
    }

    .upload-title {
        font-weight: 800;
        color: #1e293b;
        font-size: 16px;
        margin-bottom: 5px;
    }

    .upload-subtitle {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .btn-choose {
        padding: 8px 24px;
        border: 1.5px solid #dc2626;
        color: #dc2626;
        background: #fff;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
    }

    .upload-box:hover .btn-choose {
        background: #fef2f2;
    }

    .img-preview-wrapper {
        display: none;
        width: 100%;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }

    .img-preview-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .change-img-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        background: rgba(0, 0, 0, 0.6);
        color: #fff;
        padding: 8px 0;
        font-size: 12px;
        font-weight: 700;
        text-align: center;
        opacity: 0;
        transition: 0.3s;
    }

    .upload-box:hover .change-img-overlay {
        opacity: 1;
    }

    .actions-footer {
        display: flex;
        justify-content: flex-end;
        gap: 16px;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1.5px solid #f1f5f9;
    }

    .btn-cancel {
        padding: 12px 30px;
        background: #fff;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        color: #475569;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }

    .btn-cancel:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    @media (max-width: 768px) {
        .add-client-wrapper {
            padding: 20px;
        }

        .upload-box {
            min-height: 200px;
            margin-bottom: 20px;
        }
    }
</style>

<div class="page-wrapper premium-ui-enabled">
    <div class="add-client-wrapper">
        <form method="POST" id="edit_client_form" enctype="multipart/form-data">

            <div class="section-header-custom">
                <div class="step-badge">1</div>
                <div>
                    <h3 class="section-title-custom">Update Client Information</h3>
                    <p class="section-subtitle-custom">Modify details for <?php echo htmlspecialchars($client_data['name']); ?></p>
                </div>
            </div>

            <div class="row">
                <!-- Left Column: Image Upload -->
                <div class="col-md-4">
                    <div class="form-group-custom" style="height: 100%;">
                        <label class="form-label-custom">Client Image</label>
                        <label class="upload-box" for="client_image">
                            <?php
                            $preview_img = !empty($client_data['image']) ? '../uploads/client_images/' . $client_data['image'] : '';
                            ?>
                            <i class="fa fa-picture-o upload-icon"></i>
                            <div class="upload-title">Upload client image</div>
                            <div class="upload-subtitle">JPG, PNG up to 5MB</div>
                            <div class="btn-choose">Choose File</div>

                            <div class="img-preview-wrapper" id="preview_wrapper" style="display: <?php echo empty($preview_img) ? 'none' : 'block'; ?>;">
                                <img id="client_preview" src="<?php echo $preview_img; ?>">
                                <div class="change-img-overlay"><i class="fa fa-pencil"></i> Change Image</div>
                            </div>
                        </label>
                        <input type="file" name="client_image" id="client_image" style="display: none;" accept="image/*" onchange="handlePreview(this)">
                    </div>
                </div>

                <!-- Right Column: Basic Fields -->
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Client Name <span class="req">*</span></label>
                                <input type="text" name="name" class="p-input-premium" placeholder="Enter client name" value="<?php echo htmlspecialchars($client_data['name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Company Name <span class="req">*</span></label>
                                <input type="text" name="company_name" class="p-input-premium" placeholder="Type company name..." value="<?php echo htmlspecialchars($client_data['company_name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Email <span class="req">*</span></label>
                                <input type="email" name="email" class="p-input-premium" placeholder="Enter email address..." value="<?php echo htmlspecialchars($client_data['email']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Mobile <span class="req">*</span></label>
                                <input type="tel" name="mobile" class="p-input-premium" placeholder="Enter mobile number" value="<?php echo htmlspecialchars($client_data['mobile']); ?>" required maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-custom">
                                <label class="form-label-custom">Country <span class="req">*</span></label>
                                <select name="country" class="p-input-premium" required>
                                    <option value="">Select country...</option>
                                    <option value="India" <?php if ($client_data['country'] == 'India') echo 'selected'; ?>>India</option>
                                    <option value="United States" <?php if ($client_data['country'] == 'United States') echo 'selected'; ?>>United States</option>
                                    <option value="United Kingdom" <?php if ($client_data['country'] == 'United Kingdom') echo 'selected'; ?>>United Kingdom</option>
                                    <option value="Canada" <?php if ($client_data['country'] == 'Canada') echo 'selected'; ?>>Canada</option>
                                    <option value="Australia" <?php if ($client_data['country'] == 'Australia') echo 'selected'; ?>>Australia</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group-custom">
                        <label class="form-label-custom">Website</label>
                        <input type="url" name="website" class="p-input-premium" placeholder="Enter website (e.g. https://example.com)" value="<?php echo htmlspecialchars($client_data['website']); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group-custom">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <label class="form-label-custom" style="margin: 0;">Industry</label>
                            <button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#addIndustryModal" style="border-radius: 6px; padding: 2px 8px; font-size: 10px; font-weight: 700; background: #10b981; border: none; box-shadow: 0 2px 4px rgba(16,185,129,0.2);">
                                <i class="fa fa-plus"></i> New
                            </button>
                        </div>
                        <div style="background: #fff; padding: 10px; border-radius: 8px; border: 1.5px solid #e2e8f0; min-height: 48px; max-height: 150px; overflow-y: auto;" id="industry_checkbox_container">
                            <?php
                            $selected_industries = !empty($client_data['industry']) ? explode(', ', $client_data['industry']) : [];
                            $get_industries = "SELECT * FROM client_industries WHERE deleted_at IS NULL ORDER BY industry_name ASC";
                            $run_industries = mysqli_query($con, $get_industries);
                            if ($run_industries) {
                                while ($row_i = mysqli_fetch_array($run_industries)):
                                    $i_name = $row_i['industry_name'];
                                    $i_id = $row_i['id'];
                                    $checked = in_array($i_name, $selected_industries) ? 'checked' : '';
                            ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                        <label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0; font-size: 13px;">
                                            <input type="checkbox" name="industry[]" value="<?php echo htmlspecialchars($i_name); ?>" <?php echo $checked; ?> style="margin-right: 8px; width: 14px; height: 14px; vertical-align: middle; accent-color: #DD2127;"> <?php echo htmlspecialchars($i_name); ?>
                                        </label>
                                        <i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteIndustry(<?php echo $i_id; ?>, this)"></i>
                                    </div>
                            <?php
                                endwhile;
                            }
                            ?>
                        </div>
                        <small style="color:#64748b; font-size:12px; margin-top:4px; display:block;">Select all that apply</small>
                    </div>
                </div>
            </div>

            <div class="actions-footer">
                <a href="index.php?client_directory" class="btn-premium-cancel">Cancel</a>
                <button type="submit" name="update_client" class="btn-premium-add">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>

<script>
    function handlePreview(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('client_preview').src = e.target.result;
                document.getElementById('preview_wrapper').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<!-- Add Industry Modal -->
<div class="modal fade" id="addIndustryModal" tabindex="-1" role="dialog" aria-labelledby="addIndustryModalLabel">
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
                    Add New Industry
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <form id="add-industry-form-main">
                    <div style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Industry Name</label>
                        <input type="text" name="industry_name" id="new_industry_name" placeholder="e.g. Technology, Healthcare" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                        <button type="button" data-dismiss="modal" class="btn-premium-cancel">Cancel</button>
                        <button type="submit" class="btn-premium-add">
                            <i class="fa fa-save"></i> Save Industry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#add-industry-form-main').submit(function(e) {
            e.preventDefault();
            var industry = $('#new_industry_name').val();
            var submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: "ajax/misc/ajax_add_industry.php",
                method: "POST",
                data: {
                    industry_name: industry
                },
                success: function(response) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Industry');
                    var data;
                    try {
                        data = typeof response === 'object' ? response : JSON.parse(response.trim());
                    } catch (e) {
                        Swal.fire('Notification', "Server response error: " + response, 'error');
                        return;
                    }
                    if (data.status == "success") {
                        var newHtml = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">' +
                            '<label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0; font-size: 13px;">' +
                            '<input type="checkbox" name="industry[]" value="' + data.name + '" checked style="margin-right: 8px; width: 14px; height: 14px; vertical-align: middle; accent-color: #DD2127;"> ' + data.name +
                            '</label>' +
                            '<i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteIndustry(' + data.id + ', this)"></i>' +
                            '</div>';
                        $("#industry_checkbox_container").append(newHtml);
                        $('#addIndustryModal [data-dismiss="modal"]').first().trigger('click');
                        $('#addIndustryModal').hide();
                        $('#new_industry_name').val('');
                        $('.modal-backdrop').remove();
                        $('body').removeClass('modal-open');
                        $('body').css('padding-right', '');
                    } else {
                        if (data.message === "Industry already exists") {
                            var existingCheckbox = $("input[name='industry[]']").filter(function() {
                                return $(this).val().toLowerCase() === industry.toLowerCase();
                            });

                            if (existingCheckbox.length > 0) {
                                existingCheckbox.prop('checked', true);
                            } else {
                                var newHtml = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">' +
                                    '<label style="font-weight: 500; color: #475569; cursor: pointer; margin: 0; font-size: 13px;">' +
                                    '<input type="checkbox" name="industry[]" value="' + industry + '" checked style="margin-right: 8px; width: 14px; height: 14px; vertical-align: middle; accent-color: #DD2127;"> ' + industry +
                                    '</label>' +
                                    '<i class="fa fa-trash" style="color: #ef4444; cursor: pointer; font-size: 13px;" onclick="deleteIndustry(' + data.id + ', this)"></i>' +
                                    '</div>';
                                $("#industry_checkbox_container").append(newHtml);
                            }
                            $('#addIndustryModal [data-dismiss="modal"]').first().trigger('click');
                            $('#addIndustryModal').hide();
                            $('#new_industry_name').val('');
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $('body').css('padding-right', '');
                        } else {
                            Swal.fire('Notification', "Error: " + data.message, 'error');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Industry');
                    Swal.fire('Notification', "Connection Error. Details: " + xhr.responseText, 'error');
                }
            });
        });
    });

    function deleteIndustry(industryId, element) {
        if (!industryId || industryId <= 0) {
            Swal.fire({
                title: 'Error',
                text: 'Invalid Industry ID.',
                icon: 'error',
                confirmButtonColor: '#dd2127'
            });
            return;
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Industry?',
                html: 'Are you sure you want to delete this industry?<br><span style="font-size: 13px; color: #64748b;">This action cannot be undone.</span>',
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
                        url: "ajax/misc/ajax_delete_industry.php",
                        method: "POST",
                        data: {
                            industry_id: industryId,
                            id: industryId
                        },
                        dataType: "json",
                        success: function(data) {
                            if (data.status === "success") {
                                $(element).closest('div').remove();
                                Swal.fire({
                                    title: 'Industry Deleted Successfully!',
                                    text: 'The industry has been removed.',
                                    icon: 'success',
                                    confirmButtonColor: '#dd2127',
                                    confirmButtonText: 'OK',
                                    timer: 1800,
                                    showConfirmButton: false
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: data.message || 'Could not delete industry.',
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
            if (confirm("Do you really want to delete this industry?")) {
                $.ajax({
                    url: "ajax/misc/ajax_delete_industry.php",
                    method: "POST",
                    data: {
                        industry_id: industryId
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