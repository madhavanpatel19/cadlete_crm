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

if (isset($_GET['delete_lead'])) {
    $delete_id = intval($_GET['delete_lead']);
    $now       = date('Y-m-d H:i:s');

    // Soft delete follow-ups
    mysqli_query($con, "UPDATE lead_followups SET deleted_at = '$now' WHERE lead_id = $delete_id AND deleted_at IS NULL");

    // Soft delete lead
    $delete_lead = "UPDATE leads SET deleted_at = '$now' WHERE id = $delete_id AND deleted_at IS NULL";
    $run_delete  = mysqli_query($con, $delete_lead);

    if ($run_delete) {
        echo "<script>window.open('index.php?leads','_self');</script>";
    } else {
        $err = mysqli_real_escape_string($con, mysqli_error($con));
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '$err',
                    icon: 'error',
                    confirmButtonColor: '#ef4444',
                    customClass: { popup: 'premium-card' }
                }).then(() => {
                    window.location.href = 'index.php?leads';
                });
            });
        </script>";
    }
}
?>
