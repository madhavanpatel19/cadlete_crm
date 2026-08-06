<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
global $con;

// Handle Quick Follow-up Submission
if (isset($_POST['add_quick_followup'])) {
    $lead_id = mysqli_real_escape_string($con, $_POST['lead_id']);
    $method = mysqli_real_escape_string($con, $_POST['followup_method']);
    $type = mysqli_real_escape_string($con, $_POST['followup_type']);
    $f_date = mysqli_real_escape_string($con, $_POST['followup_date']);
    $f_remark = mysqli_real_escape_string($con, $_POST['remark']);
    $next_date = mysqli_real_escape_string($con, $_POST['next_followup_date']);

    $insert_f = "INSERT INTO lead_followups (lead_id, followup_date, followup_method, followup_type, remark) 
                 VALUES ('$lead_id', '$f_date', '$method', '$type', '$f_remark')";
    if (mysqli_query($con, $insert_f)) {
        // Update the next follow-up date in leads table
        if (!empty($next_date)) {
            mysqli_query($con, "UPDATE leads SET followup_date = '$next_date' WHERE id = '$lead_id'");
        }

        // Redirect back to the same page or where they came from
        $redirect = "index.php?leads";
        if (isset($_GET['view_lead'])) {
            $redirect = "index.php?view_lead=" . $_GET['view_lead'];
        }
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
                    title: 'Success!',
                    text: 'Follow-up added successfully!',
                    icon: 'success',
                    confirmButtonColor: '#10b981',
                    background: '#ffffff',
                    customClass: { popup: 'premium-card' }
                }).then(() => {
                    window.location.href = '$redirect';
                });
            });
        </script>";
    } else {
        $err = mysqli_real_escape_string($con, mysqli_error($con));
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: '$err',
                    icon: 'error',
                    confirmButtonColor: '#ef4444'
                });
            });
        </script>";
    }
}
