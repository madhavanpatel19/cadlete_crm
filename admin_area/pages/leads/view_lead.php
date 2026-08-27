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

$currency_symbols = [
    'INR' => '₹',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'AED' => 'د.إ'
];

$view_id = null;
$row_lead = [];
$run_followups = null;

if (isset($_GET['view_lead'])) {
    $view_id = mysqli_real_escape_string($con, $_GET['view_lead']);
    $get_lead = "SELECT * FROM leads WHERE id = '$view_id'";
    $run_lead = mysqli_query($con, $get_lead);
    $row_lead = mysqli_fetch_array($run_lead);

    if (!$row_lead) {
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
                    title: 'Error!',
                    text: 'Lead not found!',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
        exit;
    }

    $client_name = $row_lead['client_name'];

    // Get Follow-up History
    $get_followups = "SELECT * FROM lead_followups WHERE lead_id = '$view_id' ORDER BY followup_date DESC, id DESC";
    $run_followups = mysqli_query($con, $get_followups);
}
include("leads_logic.php");
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <div class="header-actions-premium">
            <a href="index.php?leads" class="btn-premium-add">
                <i class="fa fa-arrow-left"></i> Back
            </a>
            <?php if (canAdminAccess('lead_update')): ?>
                <a href="index.php?edit_lead=<?php echo $view_id; ?>" class="btn-premium-add">
                    <i class="fa fa-pencil"></i> Edit
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Lead Details -->
        <div class="col-md-5">
            <div class="premium-card">
                <div class="card-hdr" style="background: var(--p-bg-header);">
                    <i class="fa fa-info-circle"></i>
                    <h3>Lead Information</h3>
                </div>
                <div style="padding: 20px;">
                    <div style="background: #f8fafc; padding: 20px; border-radius: 15px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo $row_lead['client_name']; ?></h2>
                        <p style="color: #64748b; margin-top: 5px; font-weight: 500;">
                            <i class="fa fa-building"></i> <?php echo !empty($row_lead['company_name']) ? $row_lead['company_name'] : 'Individual Client'; ?>
                        </p>
                        <div style="margin-top: 15px; display: flex; gap: 10px;">
                            <a href="tel:<?php echo $row_lead['phone']; ?>" class="btn btn-default btn-sm" style="border-radius: 10px;">
                                <i class="fa fa-phone"></i> Call
                            </a>
                            <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $row_lead['phone']); ?>" target="_blank" class="btn btn-success btn-sm" style="border-radius: 10px;">
                                <i class="fa fa-whatsapp"></i> WhatsApp
                            </a>
                            <?php if (!empty($row_lead['email'])): ?>
                                <a href="mailto:<?php echo $row_lead['email']; ?>" class="btn btn-primary btn-sm" style="border-radius: 10px; background: #4f46e5;">
                                    <i class="fa fa-envelope"></i> Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <table class="table table-clean">
                        <tr>
                            <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Project</th>
                            <td style="border:none; font-weight: 700; color: #1e293b;"><?php echo !empty($row_lead['project_name']) ? $row_lead['project_name'] : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Budget</th>
                            <td style="border:none; font-weight: 700; color: #1e293b;"><?php echo !empty($row_lead['budget']) ? (isset($row_lead['currency']) && isset($currency_symbols[$row_lead['currency']]) ? $currency_symbols[$row_lead['currency']] : (isset($row_lead['currency']) ? $row_lead['currency'] : 'INR')) . ' ' . $row_lead['budget'] : 'N/A'; ?></td>
                        </tr>
                        <?php if (canAdminAccess('project_source_view')): ?>
                            <tr>
                                <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Lead Source</th>
                                <td style="border:none; font-weight: 600;"><?php echo $row_lead['lead_source']; ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Current Status</th>
                            <td style="border:none;">
                                <?php
                                $status = $row_lead['status'];
                                $badge_class = 'p-badge-success';
                                if ($status == 'future') $badge_class = 'p-badge-primary';
                                if ($status == 'expired') $badge_class = 'p-badge-danger';
                                ?>
                                <span class="p-badge <?php echo $badge_class; ?>" style="text-transform:capitalize;"><?php echo $status; ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Next Follow-up</th>
                            <td style="border:none; font-weight: 700; color: #4f46e5;"><?php echo !empty($row_lead['followup_date']) ? date('d-m-Y', strtotime($row_lead['followup_date'])) : 'Not Scheduled'; ?></td>
                        </tr>
                        <tr>
                            <th style="border:none; color: #64748b; font-size: 12px; text-transform: uppercase;">Assigned Team</th>
                            <td style="border:none;">
                                <?php
                                $v_emp_names = [];
                                $v_emp_ids = !empty($row_lead['assigned_employees']) ? array_filter(array_map('intval', explode(',', $row_lead['assigned_employees']))) : [];
                                if (!empty($v_emp_ids)) {
                                    $v_emp_impl = implode(',', $v_emp_ids);
                                    $r_emps = mysqli_query($con, "SELECT name FROM emp_list WHERE id IN ($v_emp_impl)");
                                    if ($r_emps) {
                                        while ($e_r = mysqli_fetch_assoc($r_emps)) {
                                            $v_emp_names[] = htmlspecialchars($e_r['name']);
                                        }
                                    }
                                }
                                $v_adm_names = [];
                                $v_adm_ids = !empty($row_lead['assigned_admins']) ? array_filter(array_map('intval', explode(',', $row_lead['assigned_admins']))) : [];
                                if (!empty($v_adm_ids)) {
                                    $v_adm_impl = implode(',', $v_adm_ids);
                                    $r_adms = mysqli_query($con, "SELECT admin_name FROM admins WHERE admin_id IN ($v_adm_impl)");
                                    if ($r_adms) {
                                        while ($a_r = mysqli_fetch_assoc($r_adms)) {
                                            $v_adm_names[] = htmlspecialchars($a_r['admin_name']);
                                        }
                                    }
                                }

                                if (!empty($v_emp_names) || !empty($v_adm_names)) {
                                    echo '<div style="display:flex; flex-wrap:wrap; gap:5px;">';
                                    foreach ($v_emp_names as $en) {
                                        echo '<span class="label label-info" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:600;"><i class="fa fa-user" style="margin-right:4px;"></i>' . $en . '</span>';
                                    }
                                    foreach ($v_adm_names as $an) {
                                        echo '<span class="label label-warning" style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; border-radius:12px; padding:3px 10px; font-size:11px; font-weight:600;"><i class="fa fa-user-secret" style="margin-right:4px;"></i>' . $an . '</span>';
                                    }
                                    echo '</div>';
                                } else {
                                    echo '<span style="color:#94a3b8;">Unassigned</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                        <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Description</label>
                        <p style="color: #475569; line-height: 1.6; margin-top: 5px;"><?php echo !empty($row_lead['description']) ? nl2br($row_lead['description']) : 'No description provided.'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Follow-up History -->
        <div class="col-md-7">
            <div class="premium-card">
                <div class="card-hdr" style="background: var(--p-bg-header); display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-history"></i>
                        <h3>Follow-up Timeline</h3>
                    </div>
                    <?php if (canAdminAccess('lead_update')): ?>
                        <button onclick="openFollowupModal(<?php echo $view_id; ?>, '<?php echo htmlspecialchars($client_name); ?>')" class="btn-premium-add">
                            <i class="fa fa-plus"></i> Add Follow-up
                        </button>
                    <?php endif; ?>
                </div>
                <div style="padding: 25px; max-height: 500px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                    <?php if (mysqli_num_rows($run_followups) > 0): ?>
                        <div class="timeline-premium">
                            <?php while ($f_row = mysqli_fetch_array($run_followups)): ?>
                                <div class="timeline-item-premium">
                                    <div class="timeline-icon-premium">
                                        <?php
                                        $method = strtolower($f_row['followup_method']);
                                        $icon = 'fa-comment';
                                        if ($method == 'phone') $icon = 'fa-phone';
                                        if ($method == 'whatsapp') $icon = 'fa-whatsapp';
                                        if ($method == 'email') $icon = 'fa-envelope';
                                        ?>
                                        <i class="fa <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="timeline-body-premium">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <h4 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 14px;">
                                                <?php echo $f_row['followup_method']; ?> - <?php echo $f_row['followup_type']; ?>
                                            </h4>
                                            <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">
                                                <?php echo date('d-m-Y', strtotime($f_row['followup_date'])); ?>
                                            </span>
                                        </div>
                                        <p style="margin: 8px 0 0 0; color: #475569; font-size: 13px; line-height: 1.5;">
                                            <?php echo nl2br($f_row['remark']); ?>
                                        </p>
                                        <div style="margin-top: 10px; display: flex; align-items: center; gap: 5px; font-size: 11px; color: #94a3b8;">
                                            <i class="fa fa-clock-o"></i> Logged at <?php echo date('h:i A', strtotime($f_row['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 60px 20px; background: #f8fafc; border-radius: 20px; border: 2.5px dashed #e2e8f0;">
                            <div style="width: 80px; height: 80px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                                <i class="fa fa-calendar-times-o" style="font-size: 30px; color: #cbd5e1;"></i>
                            </div>
                            <h4 style="color: #64748b; font-weight: 700;">No History Yet</h4>
                            <p style="color: #94a3b8; font-size: 13px; max-width: 250px; margin: 5px auto 0;">Start logging follow-ups to track your interactions with this lead.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("leads_modal.php"); ?>

<style>
    .table-clean th {
        padding: 12px 0 !important;
        width: 130px;
    }

    .table-clean td {
        padding: 12px 0 !important;
    }

    /* Timeline Styles */
    .timeline-premium {
        position: relative;
        padding-left: 45px;
    }

    .timeline-premium::before {
        content: '';
        position: absolute;
        left: 17px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #f1f5f9;
    }

    .timeline-item-premium {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-icon-premium {
        position: absolute;
        left: -45px;
        width: 36px;
        height: 36px;
        background: #fff;
        border: 2px solid #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #4f46e5;
        z-index: 2;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .timeline-body-premium {
        background: #fff;
        padding: 20px;
        border-radius: 15px;
        border: 1px solid #f1f5f9;
        transition: all 0.3s;
    }

    .timeline-item-premium:hover .timeline-body-premium {
        border-color: #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        transform: translateX(5px);
    }

    .timeline-item-premium:hover .timeline-icon-premium {
        border-color: #4f46e5;
        background: #4f46e5;
        color: #fff;
    }

    .p-badge-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }
</style>

<script>
    function openFollowupModal(leadId, clientName) {
        document.getElementById('modalLeadId').value = leadId;
        document.getElementById('modalClientName').innerText = clientName;
        $('#followupModal').modal('show');
    }
</script>