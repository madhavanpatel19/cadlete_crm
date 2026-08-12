<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($_SESSION['admin_email'])) {
    echo "<script>window.open('pages/auth/login.php','_self')</script>";
    exit();
}

if (!isset($con)) {
    include(__DIR__ . "/includes/db.php");
}

if (isset($_GET['edit_experience_letter'])) {
    $edit_id = mysqli_real_escape_string($con, $_GET['edit_experience_letter']);
    $get_exp = "SELECT * FROM experience_letters WHERE id='$edit_id'";
    $run_exp = mysqli_query($con, $get_exp);
    $row_exp = mysqli_fetch_array($run_exp);

    if (!$row_exp) {
        echo "<script>Swal.fire({title: 'Notification', text: 'Experience letter not found.', icon: 'error'}).then(() => { window.open('index.php?view_experience_letters','_self'); });</script>";
        exit();
    }

    $name = $row_exp['name'];
    $email = $row_exp['email'];
    $number = $row_exp['number'];
    $designation = $row_exp['designation'];
    $join_date = $row_exp['join_date'];
    $relieve_date = $row_exp['relieve_date'];
} else {
    echo "<script>window.open('index.php?view_experience_letters','_self')</script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_exp'])) {
    $u_name = mysqli_real_escape_string($con, $_POST['name']);
    $u_email = mysqli_real_escape_string($con, $_POST['email']);
    $u_number = mysqli_real_escape_string($con, $_POST['number']);
    $u_designation = mysqli_real_escape_string($con, $_POST['designation']);
    $u_join_date = mysqli_real_escape_string($con, $_POST['join_date']);
    $u_relieve_date = mysqli_real_escape_string($con, $_POST['relieve_date']);

    $update_query = "UPDATE experience_letters SET 
                     name='$u_name', 
                     email='$u_email', 
                     number='$u_number', 
                     designation='$u_designation', 
                     join_date='$u_join_date',
                     relieve_date='$u_relieve_date'
                     WHERE id='$edit_id'";

    $run_update = mysqli_query($con, $update_query);

    if ($run_update) {
        $_SESSION['exp_updated'] = true;
        echo "<script>window.open('index.php?view_experience_letters','_self')</script>";
        exit();
    } else {
        $error_msg = "Error updating record: " . mysqli_error($con);
    }
}
?>

<div class="page-wrapper premium-ui-enabled">
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-danger" style="margin: 0 30px 20px 30px; border-radius: 12px; font-weight: 600;">
            <i class="fa fa-exclamation-circle"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="premium-card" style="margin: 0 30px 30px 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); background: #fff;">
            <div style="padding: 25px 30px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <div style="width: 32px; height: 32px; background: #DD2127; color: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                    1
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Candidate Information</h3>
                    <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Basic details about the experience letter</p>
                </div>
            </div>

            <div style="padding: 30px;">
                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Candidate Name</label>
                        <input type="text" name="name" class="p-input-premium" value="<?php echo htmlspecialchars($name); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Designation</label>
                        <input type="text" name="designation" class="p-input-premium" value="<?php echo htmlspecialchars($designation); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Email Address</label>
                        <input type="email" name="email" class="p-input-premium" value="<?php echo htmlspecialchars($email); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Phone Number</label>
                        <input type="text" name="number" class="p-input-premium" value="<?php echo htmlspecialchars($number); ?>" style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Joining Date</label>
                        <input type="date" name="join_date" class="p-input-premium" value="<?php echo htmlspecialchars($join_date); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                    <div class="col-md-6" style="margin-bottom: 20px;">
                        <label style="font-weight: 700; color: #475569; display: block; margin-bottom: 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Relieving Date</label>
                        <input type="date" name="relieve_date" class="p-input-premium" value="<?php echo htmlspecialchars($relieve_date); ?>" required style="height: 48px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; padding: 0 15px; transition: all 0.3s;" onfocus="this.style.borderColor='#DD2127'; this.style.boxShadow='0 0 0 4px rgba(223, 33, 39, 0.1)';" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
                    </div>
                </div>

                <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
                    <a href="index.php?view_experience_letters" class="btn-premium-cancel">Cancel</a>
                    <button type="submit" name="update_exp" class="btn-premium-add">Save Changes</button>
                </div>
            </div>
        </div>
    </form>
</div>