<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
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
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1>
            <i class="fa fa-briefcase" style="color: #333;"></i>
            Project Portfolio: <?php echo htmlspecialchars($client_data['name']); ?>
        </h1>
        <div class="header-actions-premium" style="display: flex; gap: 12px; align-items: center;">
            <a href="index.php?add_project&client_id=<?php echo $client_id; ?>" class="btn-premium-add">
                <i class="fa fa-plus"></i> Add New Project
            </a>
            <a href="index.php?client_directory" class="btn-premium-cancel">
                <i class="fa fa-arrow-left"></i> Back to Directory
            </a>
        </div>
    </div>

    <div class="row" style="margin-top: 0;">
        <div class="col-md-12">
        </div>
    </div>

    <!-- Projects Table -->
    <div class="premium-card">
        <div class="card-hdr">
            <i class="fa fa-list-ul"></i>
            <h3>Project Portfolio</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">#ID</th>
                        <th>Project Designation</th>
                        <th style="text-align: center;">Date</th>
                        <th style="text-align: center;">Budget</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">History</th>
                    </tr>
                </thead>
                <tbody id="full-projects-container">
                    <!-- Rows will be loaded via AJAX -->
                    <tr>
                        <td colspan="6" style="padding: 100px 0; text-align: center;">
                            <div class="spinner-premium" style="margin: 0 auto;"></div>
                            <p style="margin-top: 20px; color: #64748b; font-weight: 700; font-size: 14px;">Synchronizing workspace...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>


<style>
    .spinner-premium {
        width: 50px;
        height: 50px;
        border: 4px solid #f1f5f9;
        border-top: 4px solid #6366f1;
        border-radius: 50%;
        margin: 0 auto;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .table-premium th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 13px;
        text-transform: uppercase;
        padding: 16px 25px;
        text-align: left;
        border-bottom: 1.5px solid #e2e8f0;
        white-space: nowrap;
    }

    .table-premium td {
        padding: 20px 25px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .timeline-visual-wrapper {
        position: relative;
        padding-left: 20px;
    }

    .timeline-vertical-line {
        position: absolute;
        left: 4px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
        z-index: 1;
    }

    .timeline-remark-item {
        position: relative;
        z-index: 2;
    }

    .timeline-dot {
        position: absolute;
        left: -32px;
        top: 15px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #cbd5e1;
        z-index: 3;
    }

    .remark-content-box {
        background: #fff;
        padding: 20px 25px;
        border-radius: 18px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        position: relative;
        transition: 0.3s;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        display: block;
    }

    .remark-content-box:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border-color: #eef2f6;
    }

    /* Speech bubble tail */
    .remark-content-box::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 15px;
        width: 15px;
        height: 15px;
        background: #fff;
        border-left: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        transform: rotate(45deg);
    }

    .remark-time-premium {
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .remark-text-premium {
        font-size: 14px;
        color: #334155;
        line-height: 1.6;
        font-weight: 600;
        display: block;
        width: 100%;
        word-break: break-all !important;
        overflow-wrap: anywhere !important;
        white-space: normal !important;
    }

    .premium-field-label {
        font-weight: 700;
        color: #475569;
        display: block;
        margin-bottom: 12px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .p-input-premium {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        transition: all 0.3s;
        padding: 12px 20px;
        width: 100%;
        color: #0f172a;
        font-weight: 600;
    }

    .p-input-premium:focus {
        background: #fff;
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;
    }

    .id-badge-premium {
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 800;
        color: #94a3b8;
        font-size: 13px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 8px;
    }

    .glass-card-premium {
        background: rgba(255, 255, 255, 0.8) !important;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
    }

    /* Premium Modal Overlay Styles */
    .premium-confirm-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .premium-confirm-overlay.active {
        display: flex;
        opacity: 1;
    }

    .premium-confirm-modal {
        background: #fff;
        width: 100%;
        max-width: 400px;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        padding: 30px;
        text-align: center;
    }

    .premium-confirm-overlay.active .premium-confirm-modal {
        transform: scale(1);
    }

    .confirm-icon-box {
        width: 60px;
        height: 60px;
        background: #fee2e2;
        color: #ef4444;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 20px auto;
        animation: pulseDanger 2s infinite;
    }

    @keyframes pulseDanger {
        0% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }

        70% {
            box-shadow: 0 0 0 15px rgba(239, 68, 68, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }

    .premium-confirm-header h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    .premium-confirm-header p {
        margin: 0 0 25px 0;
        font-size: 14px;
        color: #64748b;
        line-height: 1.5;
    }

    .premium-confirm-footer {
        display: flex;
        gap: 12px;
    }

    .confirm-btn-cancel {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .confirm-btn-cancel:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .confirm-btn-delete {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
        background: #ef4444;
        color: #fff;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }

    .confirm-btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
    }
</style>

<script>
    $(document).ready(function() {
        const clientId = <?php echo $client_id; ?>;

        function loadProjects() {
            // Show loading state
            $('#full-projects-container').css('opacity', '0.6');

            $.ajax({
                url: 'pages/clients/fetch_client_projects.php',
                method: 'POST',
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    $('#full-projects-container').css('opacity', '1');
                    if (response.trim() === "") {
                        $('#full-projects-container').html(`
                        <tr>
                            <td colspan="6" style="padding: 100px 20px; text-align: center;">
                                <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fa fa-folder-open-o" style="font-size: 24px; color: #94a3b8;"></i>
                                </div>
                                <h4 style="color: #1e293b; font-weight: 700; margin-bottom: 5px;">No Projects Found</h4>
                                <p style="color: #64748b; font-size: 13px;">This client doesn't have any projects assigned yet.</p>
                            </td>
                        </tr>
                    `);
                    } else {
                        $('#full-projects-container').html(response);
                    }
                },
                error: function(xhr, status, error) {
                    $('#full-projects-container').css('opacity', '1').html(`
                    <tr>
                        <td colspan="6" style="padding: 50px; text-align: center;">
                            <div style="background: #fef2f2; padding: 20px; border-radius: 12px; border: 1.5px dashed #fecaca;">
                                <i class="fa fa-exclamation-circle" style="color: #ef4444; font-size: 32px; margin-bottom: 10px;"></i>
                                <h4 style="color: #991b1b; font-weight: 700;">Connection Error</h4>
                                <p style="color: #b91c1c; font-size: 13px;">Was unable to retrieve data. Status: ${status}</p>
                                <button onclick="location.reload()" class="btn btn-xs" style="margin-top: 10px; background: #ef4444; color: #fff; border-radius: 8px;">Retry</button>
                            </div>
                        </td>
                    </tr>
                `);
                }
            });
        }

        loadProjects();

        // Toggle remarks detail row
        $(document).on('click', '.toggle-project-detail', function(e) {
            // Prevent if clicking textarea/button inside the expanded row
            if ($(e.target).closest('.remark-action-premium').length) return;

            const row = $(this).closest('tr');
            const detailRow = row.next('.project-detail-row');
            const icon = row.find('.history-toggle-icon');

            detailRow.toggle();

            if (detailRow.is(':visible')) {
                row.css('background-color', '#f8fafc');
                icon.removeClass('fa-history').addClass('fa-times').css('color', '#ef4444');
            } else {
                row.css('background-color', '');
                icon.removeClass('fa-times').addClass('fa-history').css('color', '#6366f1');
            }
        });

        $('#add-project-form-main').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');

            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Initializing...');

            $.ajax({
                url: 'ajax/clients/ajax_add_client_project.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Deploy Project');
                    if (response.success) {
                        $('#addProjectModal').modal('hide');
                        $('#add-project-form-main')[0].reset();
                        loadProjects();
                        showPremiumAlert('New project initialized successfully');
                    } else {
                        Swal.fire({
                            title: 'Configuration Error',
                            text: response.message || 'Error occurred.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    submitBtn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Deploy Project');
                    Swal.fire({
                        title: 'Network Error',
                        text: 'Network error occurred during project initialization.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });

        $(document).on('click', '.add-remark-btn', function() {
            const btn = $(this);
            const projectId = btn.data('project-id');
            const actionWrapper = btn.closest('.remark-action-premium');
            const remarkInput = actionWrapper.find('.remark-textarea');
            const remarkText = remarkInput.val().trim();
            const detailRow = btn.closest('.project-detail-row');
            const container = detailRow.find('.remarks-history-premium');

            if (!remarkText) {
                remarkInput.focus();
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

            $.ajax({
                url: 'ajax/clients/ajax_add_client_remark.php',
                method: 'POST',
                data: {
                    project_id: projectId,
                    remark: remarkText,
                    user_type: 'admin'
                },
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i>');
                    if (response.success) {
                        const posterName = response.posted_by || 'You';
                        const newRemark = $(`
                        <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; display: none; width: 100%;">
                            <div class="timeline-dot" style="left: 0; background: #dd2127; border-color: #dd2127; box-shadow: 0 0 0 4px rgba(221, 33, 39, 0.1);"></div>
                            <div class="remark-content-box" style="border-left: 4px solid #dd2127; padding-left: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 6px; background: #eff6ff; color: #dd2127; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-user"></i> ${posterName}
                                    </span>
                                    <div class="remark-time-premium" style="margin: 0; font-size: 11px;">
                                        <i class="fa fa-clock-o"></i> JUST NOW
                                    </div>
                                </div>
                                <div class="remark-text-premium" style="font-size: 13px; color: #334155; font-weight: 600;">${remarkText.replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>`);

                        container.find('.no-remarks-placeholder').remove();
                        container.prepend(newRemark);
                        newRemark.slideDown(400);
                        remarkInput.val('');
                    } else {
                        Swal.fire({
                            title: 'Error Saving Remark',
                            text: response.message || 'Unknown error occurred.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i>');
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'Unable to connect to the server to save the remark.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });

        // Handle real-time status update from table
        $(document).on('change', '.project-status-select', function() {
            const select = $(this);
            const projectId = select.data('project-id');
            const newStatus = select.val();

            // Visual feedback
            select.css('opacity', '0.5');

            $.ajax({
                url: 'ajax/projects/ajax_update_project_status.php',
                method: 'POST',
                data: {
                    project_id: projectId,
                    status: newStatus
                },
                success: function(response) {
                    select.css('opacity', '1');
                    if (response.success) {
                        showPremiumAlert(`Status updated to ${newStatus}`);
                        // Refresh colors if needed (handled by CSS automatically usually)
                    } else {
                        Swal.fire({
                            title: 'Update Failed',
                            text: response.message || 'Error updating status.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    select.css('opacity', '1');
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'Unable to communicate with the server.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });

        // Handle project deletion
        let projectToDelete = null;
        window.deleteProject = function(id, name) {
            projectToDelete = id;
            $('#delete_project_name_label').text(name);
            $('#projectDeleteConfirmOverlay').addClass('active');
        }

        window.closeProjectDeleteConfirm = function() {
            $('#projectDeleteConfirmOverlay').removeClass('active');
            projectToDelete = null;
        }

        $('#confirmProjectDeleteBtn').on('click', function() {
            if (!projectToDelete) return;

            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Erasing...');

            $.ajax({
                url: 'ajax/projects/ajax_delete_project.php',
                method: 'POST',
                data: {
                    project_id: projectToDelete
                },
                success: function(response) {
                    btn.prop('disabled', false).html('Delete Project');
                    if (response.success) {
                        closeProjectDeleteConfirm();
                        loadProjects();
                        showPremiumAlert('Project and history purged successfully');
                    } else {
                        Swal.fire({
                            title: 'Deletion Error',
                            text: response.message || 'Could not delete project.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('Delete Project');
                    closeProjectDeleteConfirm();
                    Swal.fire({
                        title: 'Network Error',
                        text: 'Network error occurred during project excision.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });
    });

    function showPremiumAlert(message) {
        // Check if toast container exists
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

        // Trigger slide in
        setTimeout(() => toast.style.transform = 'translateX(0)', 10);

        // Slide out and remove
        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }
</script>

<!-- Add Project Modal -->
<div class="modal fade" id="addProjectModal" tabindex="-1" role="dialog" aria-labelledby="addProjectModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #1e293b; color: #fff; padding: 20px 25px; border: none;">
                <h4 class="modal-title" id="addProjectModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 12px;">
                    <div style="background: rgba(255,255,255,0.1); width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-plus" style="font-size: 14px;"></i>
                    </div>
                    Initialize New Project
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.8; font-size: 24px; position: absolute; right: 20px;">&times;</button>
                </h4>
            </div>
            <div class="modal-body" style="padding: 30px; background: #fff;">
                <form id="add-project-form-main">
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <div style="margin-bottom: 25px;">
                                <label class="premium-field-label">Project Designation</label>
                                <input type="text" name="project_name" class="p-input-premium" placeholder="e.g. Enterprise Cloud Integration" required style="height: 50px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="margin-bottom: 25px;">
                                <label class="premium-field-label">Initial Status</label>
                                <select name="status" class="p-input-premium" style="height: 50px; cursor: pointer;">
                                    <option value="Active">Active</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div style="margin-bottom: 25px;">
                                <label class="premium-field-label">Kickoff Date</label>
                                <input type="date" name="project_date" class="p-input-premium" value="<?php echo date('Y-m-d'); ?>" required style="height: 50px;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="margin-bottom: 25px;">
                                <label class="premium-field-label">Financial Allocation (?)</label>
                                <input type="number" step="0.01" name="budget" class="p-input-premium" placeholder="0.00" style="height: 50px; font-weight: 700;">
                            </div>
                        </div>
                    </div>
                    <div style="margin-bottom: 30px;">
                        <label class="premium-field-label">Executive Summary</label>
                        <textarea name="remark" class="p-input-premium" style="height: 100px;" placeholder="Define primary objectives..."></textarea>
                    </div>
                    <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-premium-add">
                            <i class="fa fa-paper-plane"></i> Deploy Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Project Delete Confirmation Modal -->
<div class="premium-confirm-overlay" id="projectDeleteConfirmOverlay">
    <div class="premium-confirm-modal">
        <div class="premium-confirm-header">
            <div class="confirm-icon-box">
                <i class="fa fa-trash-o"></i>
            </div>
            <h3>Delete Project?</h3>
            <p>You are about to permanently delete <strong id="delete_project_name_label">this project</strong>. This will also erase all associated remarks and activity history.</p>
        </div>
        <div class="premium-confirm-footer">
            <button class="confirm-btn-cancel" onclick="closeProjectDeleteConfirm()" type="button">Cancel</button>
            <button class="confirm-btn-delete" id="confirmProjectDeleteBtn" type="button">Delete Project</button>
        </div>
    </div>
</div>