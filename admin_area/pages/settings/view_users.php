<?php
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit;
}
?>
<style>
    .swal2-container.swal2-backdrop-show {
        background: rgba(15, 23, 42, 0.45) !important;
        backdrop-filter: blur(6px) !important;
        -webkit-backdrop-filter: blur(6px) !important;
    }
</style>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <?php if (canAdminAccess('user_insert')): ?>
            <a href="index.php?insert_user" class="btn-premium-add">
                <i class="fa fa-user-plus"></i>Add User
            </a>
        <?php endif; ?>
    </div>

    <div class="premium-card">
        <div class="card-hdr">
            <i class="fa fa-list"></i>
            <h3>All Admins</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th style="text-align: center;">Email</th>
                        <th style="text-align: center;">Department</th>
                        <th style="text-align: center;">Function / Role</th>
                        <th style="text-align: center;">Country</th>
                        <th style="text-align: center;">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $get_admin = "select * from admins";
                    $run_admin = mysqli_query($con, $get_admin);
                    while ($row_admin = mysqli_fetch_array($run_admin)) {
                        $admin_id = $row_admin['admin_id'];
                        $admin_name = $row_admin['admin_name'];
                        $admin_email = $row_admin['admin_email'];
                        $admin_image = $row_admin['admin_image'];
                        $admin_country = $row_admin['admin_country'];
                        $admin_job = $row_admin['admin_job'];
                        $admin_dept = isset($row_admin['department']) && trim($row_admin['department']) !== '' ? $row_admin['department'] : 'Management';
                    ?>
                        <tr id="user_row_<?php echo $admin_id; ?>">
                            <td style="text-align: center;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="admin_images/<?php echo !empty($admin_image) ? $admin_image : 'default.png'; ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                    <span style="font-weight: 700; color: var(--p-text); font-size: 15px;"><?php echo htmlspecialchars($admin_name); ?></span>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <div style="color: var(--p-secondary); font-size: 13px;">
                                    <i class="fa fa-envelope-o" style="margin-right: 5px;"></i> <?php echo htmlspecialchars($admin_email); ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <span class="p-badge" style="background: #e0f2fe; color: #0369a1; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-size: 12px; display: inline-block;"><?php echo htmlspecialchars($admin_dept); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="p-badge p-badge-primary"><?php echo htmlspecialchars($admin_job); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span style="font-weight: 600; color: #475569;"><i class="fa fa-globe" style="margin-right: 5px; color: #94a3b8;"></i> <?php echo htmlspecialchars($admin_country); ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    <?php if (canAdminAccess('user_update')): ?>
                                        <a href="index.php?edit_user=<?php echo $admin_id; ?>" class="btn-icon-premium btn-icon-edit" title="Edit User">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (canAdminAccess('user_delete')): ?>
                                        <button type="button" onclick="confirmDeleteUser(<?php echo $admin_id; ?>, '<?php echo addslashes(htmlspecialchars($admin_name)); ?>')" class="btn-icon-premium btn-icon-delete" title="Delete User">
                                            <i class="fa fa-trash-o"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php }
                    if (mysqli_num_rows($run_admin) == 0) {
                        echo "<tr id='no_users_tr'>
                                <td colspan='6' style='padding: 0; border-bottom: none;'>
                                    <div style='display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; width: 100%;'>
                                        <div style='width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;'>
                                            <i class='fa fa-folder-open-o' style='font-size: 28px; color: #cbd5e1;'></i>
                                        </div>
                                        <div style='font-size: 15px; font-weight: 700; color: #64748b; margin-bottom: 4px;'>No Users Found</div>
                                        <div style='font-size: 13px; color: #94a3b8;'>There are no users to display at this time.</div>
                                    </div>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function confirmDeleteUser(id, name) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Admin User?',
                html: 'Are you sure you want to delete user <strong>' + name + '</strong>?<br><span style="font-size: 13px; color: #64748b;">This action is permanent and cannot be undone.</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dd2127',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fa fa-trash"></i> Yes, Delete User',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state modal
                    Swal.fire({
                        title: 'Deleting User...',
                        text: 'Please wait while the user account is being removed.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    fetch('pages/settings/user_delete.php?user_delete=' + id + '&ajax=1', {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Deleted Successfully!',
                                    text: data.message || 'User has been removed.',
                                    icon: 'success',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                Swal.fire({
                                    title: 'Cannot Delete User',
                                    text: data.message || 'Error occurred while deleting user.',
                                    icon: 'error',
                                    confirmButtonColor: '#dd2127'
                                });
                            }
                        })
                        .catch(err => {
                            console.error('Delete error:', err);
                            Swal.fire({
                                title: 'Error',
                                text: err.message || 'A network error occurred while communicating with the server.',
                                icon: 'error',
                                confirmButtonColor: '#dd2127'
                            });
                        });
                }
            });
        } else {
            if (confirm('Are you sure you want to delete user "' + name + '"?')) {
                window.location.href = 'index.php?user_delete=' + id;
            }
        }
    }
</script>