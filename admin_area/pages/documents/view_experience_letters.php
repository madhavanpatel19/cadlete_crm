<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('../../pages/auth/login.php','_self')</script>";
    exit();
}

if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (isset($_POST['ajax_delete_exp']) || isset($_GET['ajax_delete_exp'])) {
    $delete_id = isset($_POST['ajax_delete_exp'])
        ? $_POST['ajax_delete_exp']
        : $_GET['ajax_delete_exp'];

    $delete_id = intval($delete_id);
    $now       = date('Y-m-d H:i:s');

    $deleted = mysqli_query($con, "UPDATE experience_letters SET deleted_at = '$now' WHERE id = $delete_id AND deleted_at IS NULL");

    if (!headers_sent()) {
        header('Content-Type: application/json');
    }

    echo json_encode([
        "status" => ($deleted && mysqli_affected_rows($con) > 0) ? "success" : "error"
    ]);
    exit;
}
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <?php if (canAdminAccess('experience_letter_insert')): ?>
            <button type="button" class="btn-premium-add" data-toggle="modal" data-target="#newExpModal">
                <i class="fa fa-plus"></i>Create Letter
            </button>
        <?php endif; ?>
    </div>

    <style>
        .premium-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .premium-notification.active {
            transform: translateX(0);
        }

        .notification-success {
            background: rgba(16, 185, 129, 0.9);
        }

        .notification-error {
            background: rgba(239, 68, 68, 0.9);
        }

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


        .table-premium th,
        .table-premium td {
            text-align: center !important;
            vertical-align: middle !important;
        }
    </style>

    <div class="premium-card" style="background: #fff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;">
        <div class="card-hdr" style="padding: 20px 24px; background: var(--p-bg-header); border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; color:white">
            <i class="fa fa-list"></i>
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;">All Letters</h3>
        </div>
        <div style="overflow-x: auto; padding: 0 10px 10px 10px;">
            <table class="table-premium" style="width: 100%; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr style="background: #fff;">
                        <th>#</th>
                        <th>Name</th>
                        <th>Job Title</th>
                        <th>Work Period</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = 0;
                    $get_exps = "SELECT * FROM experience_letters WHERE deleted_at IS NULL ORDER BY id DESC";
                    $run_exps = mysqli_query($con, $get_exps);

                    if ($run_exps) {
                        while ($row_exp = mysqli_fetch_array($run_exps)) {
                            $id = $row_exp['id'];
                            $name = $row_exp['name'];
                            $email = $row_exp['email'];
                            $designation = $row_exp['designation'];
                            $join_date = date("d-m-Y", strtotime($row_exp['join_date']));
                            $relieve_date = date("d-m-Y", strtotime($row_exp['relieve_date']));
                            $i++;
                    ?>
                            <tr data-exp-row="<?php echo $id; ?>" style="transition: background 0.2s;">
                                <td style="padding: 15px; text-align: center; font-weight: 600; color: #94a3b8; border-bottom: 1px solid #f1f5f9;"><?php echo $i; ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($name); ?></div>
                                    <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($email); ?></div>
                                </td>
                                <td style="padding: 15px; border-bottom: 1px solid #f1f5f9;">
                                    <span class="badge" style="background: #ffeaeb; color: #dd2127; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700;">
                                        <?php echo htmlspecialchars($designation); ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; color: #475569; font-size: 14px; border-bottom: 1px solid #f1f5f9;">
                                    <i class="fa fa-calendar" style="color: #94a3b8; margin-right: 6px;"></i>
                                    <?php echo $join_date; ?> - <?php echo $relieve_date; ?>
                                </td>
                                <td style="padding: 15px; text-align: center; border-bottom: 1px solid #f1f5f9;">
                                    <div style="display: flex; justify-content: center; gap: 8px;">
                                        <a href="pages/documents/generate_experience.php?id=<?php echo $id; ?>&action=view" target="_blank" class="btn-icon-premium btn-icon-view" title="Print/View">
                                            <i class="fa fa-list-alt"></i>
                                        </a>
                                        <a href="pages/documents/generate_experience.php?id=<?php echo $id; ?>&action=download" class="btn-icon-premium btn-icon-download" title="Download PDF">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <?php if (canAdminAccess('experience_letter_insert')): ?>
                                            <a href="index.php?edit_experience_letter=<?php echo $id; ?>" class="btn-icon-premium btn-icon-edit" title="Edit">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                            <button onclick="showDeleteConfirm(<?php echo $id; ?>)" type="button" class="btn-icon-premium btn-icon-delete" title="Delete">
                                                <i class="fa fa-trash-o"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        }
                    }
                    if ($i == 0) {
                        echo "<tr>
                                <td colspan='5' style='padding: 0; border-bottom: none;'>
                                    <div style='display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; width: 100%;'>
                                        <div style='width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;'>
                                            <i class='fa fa-folder-open-o' style='font-size: 28px; color: #cbd5e1;'></i>
                                        </div>
                                        <div style='font-size: 15px; font-weight: 700; color: #64748b; margin-bottom: 4px;'>No letters found.</div>
                                        <div style='font-size: 13px; color: #94a3b8;'>No letters to show right now.</div>
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

<div class="modal fade" id="newExpModal" tabindex="-1" role="dialog" aria-labelledby="newExpModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
                <h4 class="modal-title" id="newExpModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 12px; margin: 0;">
                    <div style="background: #c70039; color:white;width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-file-text-o" style="font-size: 14px;"></i>
                    </div>
                    Generate Experience & Relieving Letter
                </h4>
            </div>
            <form action="pages/documents/generate_experience.php" method="post" target="_blank">
                <div class="modal-body" style="padding: 30px; background: #fff;">
                    <div class="row">
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Employee Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Employee Name" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Designation</label>
                            <input type="text" name="designation" class="form-control" placeholder="Designation" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Phone Number</label>
                            <input type="tel" name="number" class="form-control" maxlength="10" placeholder="Phone Number" style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Joining Date</label>
                            <input type="date" name="join_date" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                        <div class="col-md-6" style="margin-bottom: 20px;">
                            <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Relieving Date</label>
                            <input type="date" name="relieve_date" class="form-control" required style="height: 50px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 14px; padding: 12px 20px; width: 100%; color: #0f172a; font-weight: 600; outline: none; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                        </div>
                    </div>

                    <div style="text-align: right; gap: 12px; display: flex; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-premium-add">
                            <i class="fa fa-file-pdf-o"></i> Generate Letter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="premium-confirm-overlay" id="deleteConfirmOverlay">
    <div class="premium-confirm-modal">
        <div class="confirm-icon-box">
            <i class="fa fa-trash"></i>
        </div>
        <h3 style="margin: 0 0 10px 0; font-weight: 700; color: #0f172a;">Delete Experience Letter?</h3>
        <p style="margin: 0 0 25px 0; font-size: 14px; color: #64748b;">This action will permanently remove this record. This cannot be undone.</p>
        <div style="display: flex; gap: 12px;">
            <button class="confirm-btn-cancel" onclick="closeDeleteConfirm()" type="button" style="flex: 1; padding: 12px; border-radius: 12px; background: #f1f5f9; color: #64748b; border: none; font-weight: 700; cursor: pointer;">Cancel</button>
            <button class="confirm-btn-delete" id="confirmDeleteBtn" type="button" style="flex: 1; padding: 12px; border-radius: 12px; background: #ef4444; color: #fff; border: none; font-weight: 700; cursor: pointer;">Delete Now</button>
        </div>
    </div>
</div>

<script>
    function showPremiumAlert(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `premium-notification notification-${type}`;
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        toast.innerHTML = `<i class="fa ${icon}"></i> <span>${message}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => toast.classList.add('active'), 10);
        setTimeout(() => {
            toast.classList.remove('active');
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    let currentDeleteId = null;
    const deleteOverlay = document.getElementById('deleteConfirmOverlay');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    function showDeleteConfirm(id) {
        currentDeleteId = id;
        deleteOverlay.classList.add('active');
    }

    function closeDeleteConfirm() {
        deleteOverlay.classList.remove('active');
        currentDeleteId = null;
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.innerHTML = 'Delete Now';
    }

    deleteOverlay.addEventListener('click', function(e) {
        if (e.target === deleteOverlay) {
            closeDeleteConfirm();
        }
    });

    confirmDeleteBtn.addEventListener('click', function() {
        if (!currentDeleteId) return;

        confirmDeleteBtn.disabled = true;
        confirmDeleteBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';

        fetch('pages/documents/view_experience_letters.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `ajax_delete_exp=${encodeURIComponent(currentDeleteId)}`
            })
            .then(res => res.json())
            .then(result => {
                if (result.status === 'success') {
                    const row = document.querySelector(`[data-exp-row="${currentDeleteId}"]`);
                    if (row) {
                        row.remove();
                    }
                    closeDeleteConfirm();
                    showPremiumAlert('Experience Letter deleted successfully');
                    return;
                }

                throw new Error('Delete failed');
            })
            .catch(() => {
                showPremiumAlert('Error deleting experience letter', 'error');
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.innerHTML = 'Delete Now';
            });
    });

    $(document).ready(function() {
        $('form').on('submit', function() {
            setTimeout(function() {
                $('#newExpModal').modal('hide');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            }, 500);
        });
    });
</script>