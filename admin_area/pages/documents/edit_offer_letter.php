<?php
if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit();
}

if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (isset($_GET['edit_offer_letter'])) {
    $edit_id = mysqli_real_escape_string($con, $_GET['edit_offer_letter']);
    $get_edit = "SELECT * FROM offer_letters WHERE id='$edit_id'";
    $run_edit = mysqli_query($con, $get_edit);
    $row_edit = mysqli_fetch_array($run_edit);

    if (!$row_edit) {
        echo "<script>Swal.fire({title: 'Notification', text: 'Offer letter not found.', icon: 'error'}).then(() => { window.open('index.php?view_offer_letters','_self'); });</script>";
        exit();
    }

    $id = $row_edit['id'];
    $name = $row_edit['name'];
    $email = $row_edit['email'];
    $number = $row_edit['number'];
    $position = $row_edit['position'];
    $salary = $row_edit['salary'];
    $start_date = $row_edit['start_date'];
    $notice_period = $row_edit['notice_period'];
}

if (isset($_POST['update_offer'])) {
    $update_id = mysqli_real_escape_string($con, $_POST['id']);
    $u_name = mysqli_real_escape_string($con, $_POST['name']);
    $u_email = mysqli_real_escape_string($con, $_POST['email']);
    $u_number = mysqli_real_escape_string($con, $_POST['number']);
    $u_position = mysqli_real_escape_string($con, $_POST['position']);
    $u_salary = mysqli_real_escape_string($con, $_POST['salary']);
    $u_start_date_raw = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $u_start_date     = !empty($u_start_date_raw) ? date('Y-m-d', strtotime($u_start_date_raw)) : '';
    $u_notice_period = mysqli_real_escape_string($con, $_POST['notice_period']);

    $update_query = "UPDATE offer_letters SET 
        name='$u_name', 
        email='$u_email', 
        number='$u_number', 
        position='$u_position', 
        salary='$u_salary', 
        start_date='$u_start_date', 
        notice_period='$u_notice_period' 
        WHERE id='$update_id'";

    $run_update = mysqli_query($con, $update_query);

    if ($run_update) {
        echo "<script>Swal.fire({title: 'Notification', text: 'Offer Letter has been updated successfully', icon: 'success'});</script>";
        echo "<script>window.open('index.php?view_offer_letters','_self')</script>";
    } else {
        echo "<script>Swal.fire({title: 'Notification', text: 'Error: Could not update offer letter.', icon: 'error'});</script>";
    }
}
?>

<div class="page-wrapper premium-ui-enabled">
    <form method="post" action="">
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #DD2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    1
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Candidate Information</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Basic details about the offer letter</p>
                </div>
            </div>

            <div style="padding: 30px;">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Candidate Name</label>
                        <input type="text" name="name" class="p-input-premium" value="<?php echo htmlspecialchars($name); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Job Position</label>
                        <input type="text" name="position" class="p-input-premium" value="<?php echo htmlspecialchars($position); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Email Address</label>
                        <input type="email" name="email" class="p-input-premium" value="<?php echo htmlspecialchars($email); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Phone Number</label>
                        <input type="tel" name="number" class="p-input-premium" maxlength="10" value="<?php echo htmlspecialchars($number); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Monthly Salary (₹)</label>
                        <input type="number" name="salary" class="p-input-premium" value="<?php echo htmlspecialchars($salary); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Start Date</label>
                        <input type="date" name="start_date" class="p-input-premium" value="<?php echo $start_date; ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Notice Period</label>
                        <input type="text" name="notice_period" class="p-input-premium" value="<?php echo htmlspecialchars($notice_period); ?>" style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#dd2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
                    <a href="index.php?view_offer_letters" class="btn-premium-cancel">Cancel</a>
                    <button type="submit" name="update_offer" class="btn-premium-add">Save Changes</button>
                </div>
            </div>
        </div>
    </form>
</div>