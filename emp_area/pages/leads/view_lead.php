<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

if (!isset($_SESSION['emp_id'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit;
}

$emp_id = (int)$_SESSION['emp_id'];

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
    $view_id = (int)$_GET['view_lead'];
    // Verify lead exists, is not deleted, and is assigned to this employee
    $get_lead = "SELECT * FROM leads WHERE id = '$view_id' AND deleted_at IS NULL AND FIND_IN_SET('$emp_id', REPLACE(assigned_employees, ' ', '')) > 0";
    $run_lead = mysqli_query($con, $get_lead);
    $row_lead = mysqli_fetch_assoc($run_lead);

    if (!$row_lead) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Access Denied',
                    text: 'Lead not found or you are not assigned to this lead.',
                    icon: 'error',
                    confirmButtonColor: '#dd2127'
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
        exit;
    }

    $client_name = $row_lead['client_name'];

    // Handle quick follow-up log by employee
    if (isset($_POST['add_emp_followup'])) {
        $method = mysqli_real_escape_string($con, $_POST['followup_method']);
        $type = mysqli_real_escape_string($con, $_POST['followup_type']);
        $f_date = mysqli_real_escape_string($con, $_POST['followup_date']);
        $f_remark = mysqli_real_escape_string($con, $_POST['remark']);
        $next_date = !empty($_POST['next_followup_date']) ? mysqli_real_escape_string($con, $_POST['next_followup_date']) : null;

        $emp_name = $_SESSION['emp_name'] ?? 'Employee';
        $full_remark = $f_remark . " (Logged by $emp_name)";

        $insert_f = "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                     VALUES ('$view_id', '$f_date', '$method', '$type', '$full_remark')";
        if (mysqli_query($con, $insert_f)) {
            if (!empty($next_date)) {
                mysqli_query($con, "UPDATE leads SET followup_date = '$next_date' WHERE id = '$view_id'");
            }
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Follow-up saved successfully.',
                        icon: 'success',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        window.location.href = 'index.php?view_lead=$view_id';
                    });
                });
            </script>";
            exit;
        }
    }

    // Get Follow-up History
    $get_followups = "SELECT * FROM lead_followups WHERE lead_id = '$view_id' ORDER BY followup_date DESC, id DESC";
    $run_followups = mysqli_query($con, $get_followups);
}
?>

<div class="page-wrapper premium-ui-enabled">
    <!-- Top Action Header: Back button ONLY (no edit, no delete) -->
    <div class="page-header-premium" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="font-size: 22px; font-weight: 800; color: #1e293b; margin: 0;">Lead Details</h1>
            <p style="color: #64748b; margin: 4px 0 0 0; font-size: 13px;">View full information and activity timeline</p>
        </div>
        <div class="header-actions-premium">
            <a href="index.php?leads" class="btn-premium-add" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none;">
                <i class="fa fa-arrow-left"></i> Back to Leads
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Lead Information Card -->
        <div class="col-md-5">
            <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                <div class="card-hdr" style="background: #52525b; color: #fff; padding: 16px 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-info-circle"></i>
                    <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">Lead Information</h3>
                </div>
                <div style="padding: 24px;">
                    <!-- Client Header Box -->
                    <div style="background: #f8fafc; padding: 20px; border-radius: 14px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #1e293b; font-weight: 800; font-size: 20px;">
                            <?php echo htmlspecialchars($row_lead['client_name']); ?>
                        </h2>
                        <p style="color: #64748b; margin-top: 6px; font-weight: 500; font-size: 13px;">
                            <i class="fa fa-building-o"></i> <?php echo !empty($row_lead['company_name']) ? htmlspecialchars($row_lead['company_name']) : 'Individual Client'; ?>
                        </p>
                        <!-- Quick Contact Buttons -->
                        <div style="margin-top: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if (!empty($row_lead['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($row_lead['phone']); ?>" class="btn btn-default btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fa fa-phone"></i> Call
                                </a>
                                <a href="https://wa.me/91<?php echo preg_replace('/[^0-9]/', '', $row_lead['phone']); ?>" target="_blank" class="btn btn-success btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #16a34a; border-color: #16a34a;">
                                    <i class="fa fa-whatsapp"></i> WhatsApp
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($row_lead['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($row_lead['email']); ?>" class="btn btn-primary btn-sm" style="border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; background: #4f46e5; border-color: #4f46e5;">
                                    <i class="fa fa-envelope"></i> Email
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Details Table -->
                    <table class="table" style="margin-bottom: 0;">
                        <tr>
                            <th style="border: none; color: #64748b; font-size: 12px; text-transform: uppercase; width: 140px; padding: 10px 0;">Project Name</th>
                            <td style="border: none; font-weight: 700; color: #1e293b; padding: 10px 0;"><?php echo !empty($row_lead['project_name']) ? htmlspecialchars($row_lead['project_name']) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Phone</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 600; color: #1e293b; padding: 10px 0;"><?php echo !empty($row_lead['phone']) ? htmlspecialchars($row_lead['phone']) : '-'; ?></td>
                        </tr>
                        <?php if (!empty($row_lead['email'])): ?>
                            <tr>
                                <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Email</th>
                                <td style="border-top: 1px solid #f1f5f9; font-weight: 600; color: #1e293b; padding: 10px 0;"><?php echo htmlspecialchars($row_lead['email']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Lead Source</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 600; color: #475569; padding: 10px 0;">
                                <span style="font-size: 12px; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;"><?php echo htmlspecialchars(!empty($row_lead['lead_source']) ? $row_lead['lead_source'] : 'Direct'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Budget</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 700; color: #1e293b; padding: 10px 0;">
                                <?php echo !empty($row_lead['budget']) ? (isset($currency_symbols[$row_lead['currency']]) ? $currency_symbols[$row_lead['currency']] : '₹') . ' ' . htmlspecialchars($row_lead['budget']) : 'N/A'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Status</th>
                            <td style="border-top: 1px solid #f1f5f9; padding: 10px 0;">
                                <?php
                                $st = strtolower($row_lead['status'] ?? 'active');
                                $st_bg = '#ffeaeb';
                                $st_color = '#dd2127';
                                $st_border = '#fecdd3';
                                $st_label = 'ACTIVE';

                                if ($st === 'active') {
                                    $st_bg = '#ffeaeb';
                                    $st_color = '#dd2127';
                                    $st_border = '#fecdd3';
                                    $st_label = 'ACTIVE';
                                } elseif ($st === 'future') {
                                    $st_bg = '#eff6ff';
                                    $st_color = '#2563eb';
                                    $st_border = '#bfdbfe';
                                    $st_label = 'FUTURE';
                                } elseif ($st === 'expired') {
                                    $st_bg = '#fef2f2';
                                    $st_color = '#dc2626';
                                    $st_border = '#fecaca';
                                    $st_label = 'EXPIRED';
                                }
                                ?>
                                <span style="display: inline-block; padding: 4px 14px; border-radius: 14px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; background: <?php echo $st_bg; ?>; color: <?php echo $st_color; ?>; border: 1px solid <?php echo $st_border; ?>;">
                                    <?php echo $st_label; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th style="border-top: 1px solid #f1f5f9; color: #64748b; font-size: 12px; text-transform: uppercase; padding: 10px 0;">Next Follow-up</th>
                            <td style="border-top: 1px solid #f1f5f9; font-weight: 700; color: #4f46e5; padding: 10px 0;">
                                <?php echo !empty($row_lead['followup_date']) ? date('d-m-Y', strtotime($row_lead['followup_date'])) : 'Not Scheduled'; ?>
                            </td>
                        </tr>
                    </table>

                    <?php if (!empty($row_lead['description'])): ?>
                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                            <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Description</label>
                            <p style="color: #475569; line-height: 1.6; margin-top: 5px; font-size: 13px;"><?php echo nl2br(htmlspecialchars($row_lead['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($row_lead['remark'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9;">
                            <label style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700;">Remark</label>
                            <p style="color: #475569; line-height: 1.6; margin-top: 5px; font-size: 13px;"><?php echo nl2br(htmlspecialchars($row_lead['remark'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Follow-up Timeline Card -->
        <div class="col-md-7">
            <div class="premium-card" style="border-radius: 16px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; background: #fff;">
                <div class="card-hdr" style="background: #52525b; color: #fff; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-history"></i>
                        <h3 style="margin: 0; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #fff;">Follow-up Timeline</h3>
                    </div>
                    <button type="button" class="btn btn-sm" style="background: #dd2127; color: #fff; border-radius: 8px; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;" data-toggle="modal" data-target="#empFollowupModal">
                        <i class="fa fa-plus"></i> Add Note
                    </button>
                </div>
                <div style="padding: 25px; max-height: 550px; overflow-y: auto; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                    <?php if ($run_followups && mysqli_num_rows($run_followups) > 0): ?>
                        <div class="emp-timeline">
                            <?php while ($f_row = mysqli_fetch_assoc($run_followups)):
                                $method = strtolower($f_row['followup_method'] ?? 'other');
                                $icon = 'fa-comment';
                                if ($method === 'phone') $icon = 'fa-phone';
                                elseif ($method === 'whatsapp') $icon = 'fa-whatsapp';
                                elseif ($method === 'email') $icon = 'fa-envelope';
                                elseif ($method === 'meeting') $icon = 'fa-users';
                            ?>
                                <div class="emp-timeline-item">
                                    <div class="emp-timeline-icon">
                                        <i class="fa <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="emp-timeline-body">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                            <h4 style="margin: 0; color: #1e293b; font-weight: 700; font-size: 14px;">
                                                <?php echo htmlspecialchars($f_row['followup_method']); ?> - <?php echo htmlspecialchars($f_row['followup_type']); ?>
                                            </h4>
                                            <span style="font-size: 12px; font-weight: 700; color: #94a3b8;">
                                                <?php echo date('d-m-Y', strtotime($f_row['followup_date'])); ?>
                                            </span>
                                        </div>
                                        <p style="margin: 0; color: #475569; font-size: 13px; line-height: 1.5;">
                                            <?php echo nl2br(htmlspecialchars($f_row['remark'])); ?>
                                        </p>
                                        <div style="margin-top: 10px; display: flex; align-items: center; gap: 5px; font-size: 11px; color: #94a3b8;">
                                            <i class="fa fa-clock-o"></i> Logged at <?php echo date('h:i A, d M Y', strtotime($f_row['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center" style="padding: 60px 20px; background: #f8fafc; border-radius: 14px; border: 2px dashed #e2e8f0;">
                            <div style="width: 70px; height: 70px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); color: #cbd5e1; font-size: 28px;">
                                <i class="fa fa-history"></i>
                            </div>
                            <h4 style="color: #64748b; font-weight: 700; margin-bottom: 5px;">No Timeline History Yet</h4>
                            <p style="color: #94a3b8; font-size: 13px; max-width: 280px; margin: 0 auto;">Click "+ Add Note" above to record any client discussion or interaction.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Follow-up Note for Employee -->
<div id="empFollowupModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <form method="POST">
                <div class="modal-header" style="background: #ffeaeb; color: #1e293b; padding: 22px 25px; border-bottom: 1px solid #f1f5f9; position: relative;">
                    <button type="button" data-dismiss="modal" style="position: absolute; right: 20px; top: 20px; background: #dd2127; color: #fff; border: none; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        <i class="fa fa-times"></i>
                    </button>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: #dd2127; color: white; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-history" style="font-size: 16px;"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 18px; margin: 0; color: #0f172a;">Add Follow-up Note</h4>
                            <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b;">Record interaction for <strong><?php echo htmlspecialchars($client_name); ?></strong></p>
                        </div>
                    </div>
                </div>
                <div class="modal-body" style="padding: 25px; background: #fff;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Interaction Date</label>
                                <input type="date" name="followup_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Method</label>
                                <select name="followup_method" class="form-control" style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                                    <option value="Phone">Phone</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                    <option value="Email">Email</option>
                                    <option value="Meeting">Meeting</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Follow-up Type</label>
                        <select name="followup_type" class="form-control" style="height: 42px; border-radius: 8px; border-color: #cbd5e1;">
                            <option value="General Discussion">General Discussion</option>
                            <option value="Client Requirement Update">Client Requirement Update</option>
                            <option value="Quotation Discussion">Quotation Discussion</option>
                            <option value="Follow-up Call">Follow-up Call</option>
                            <option value="Urgent Update">Urgent Update</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label style="font-weight: 600; color: #475569; margin-bottom: 6px; display: block; font-size: 13px;">Remark / Discussion</label>
                        <textarea name="remark" class="form-control" rows="3" style="border-radius: 8px; border-color: #cbd5e1; padding: 12px;" placeholder="What did you discuss with the client..." required></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 15px; padding: 14px; background: #f0fdf4; border-radius: 10px; border: 1px solid #dcfce7;">
                        <label style="font-weight: 700; color: #166534; margin-bottom: 6px; display: block; font-size: 13px;">Next Follow-up Date (Optional)</label>
                        <input type="date" name="next_followup_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" style="height: 40px; border-radius: 8px; border-color: #bbf7d0;">
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: right;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Cancel</button>
                    <button type="submit" name="add_emp_followup" class="btn" style="background: #dd2127; color: #fff; border-radius: 8px; font-weight: 700; padding: 7px 20px;">
                        <i class="fa fa-save"></i> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .emp-timeline {
        position: relative;
        padding-left: 45px;
    }

    .emp-timeline::before {
        content: '';
        position: absolute;
        left: 17px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #f1f5f9;
    }

    .emp-timeline-item {
        position: relative;
        margin-bottom: 25px;
    }

    .emp-timeline-icon {
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
        color: #dd2127;
        z-index: 2;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.05);
    }

    .emp-timeline-body {
        background: #fff;
        padding: 18px 20px;
        border-radius: 14px;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }

    .emp-timeline-item:hover .emp-timeline-body {
        border-color: #e2e8f0;
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.04);
        transform: translateX(4px);
    }
</style>