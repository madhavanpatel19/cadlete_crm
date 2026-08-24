<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('canAdminAccess')) {
    require_once __DIR__ . '/../../../settings/permissions/permissions.php';
}

$is_ajax = isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isset($_SESSION['admin_email'])) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit;
    }
    echo "<script>window.location.href='../../pages/auth/login.php';</script>";
    exit;
}

if (!canAdminAccess('user_delete')) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission denied: You do not have permission to delete users.']);
        exit;
    }
    echo "<script>alert('Permission denied'); window.location.href='index.php?view_users';</script>";
    exit;
}

if (isset($_GET['user_delete'])) {
    $delete_id = intval($_GET['user_delete']);

    if ($delete_id <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid User ID provided.']);
            exit;
        }
        echo "<script>window.location.href = 'index.php?view_users';</script>";
        exit;
    }

    // Check if the user is attempting to delete their own active session user ID
    $current_admin_id = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
    
    if ($current_admin_id > 0 && $current_admin_id === $delete_id) {
        $msg = 'You cannot delete your own logged-in user account.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        echo "<script>
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Action Prohibited', text: '$msg', icon: 'error', confirmButtonColor: '#dd2127' })
                    .then(() => { window.location.href = 'index.php?view_users'; });
            } else {
                alert('$msg');
                window.location.href = 'index.php?view_users';
            }
        </script>";
        exit;
    }

    // Check if target user exists in database
    $check_user = mysqli_query($con, "SELECT admin_name FROM admins WHERE admin_id = $delete_id LIMIT 1");
    if (!$check_user || mysqli_num_rows($check_user) === 0) {
        $msg = 'User record not found or already deleted.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        echo "<script>window.location.href = 'index.php?view_users';</script>";
        exit;
    }

    $u_data = mysqli_fetch_assoc($check_user);
    $u_name = $u_data['admin_name'];

    $delete_user = "DELETE FROM admins WHERE admin_id = $delete_id";
    $run_delete = mysqli_query($con, $delete_user);

    if ($run_delete) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "User '$u_name' has been deleted successfully."]);
            exit;
        }
        echo "<script>
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Deleted!', text: 'User has been deleted successfully.', icon: 'success', timer: 1500, showConfirmButton: false })
                    .then(() => { window.location.href = 'index.php?view_users'; });
            } else {
                alert('User has been deleted successfully.');
                window.location.href = 'index.php?view_users';
            }
        </script>";
        exit;
    } else {
        $error_msg = mysqli_error($con);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Database error: $error_msg"]);
            exit;
        }
        echo "<script>
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Delete Failed', text: '" . addslashes($error_msg) . "', icon: 'error', confirmButtonColor: '#dd2127' })
                    .then(() => { window.location.href = 'index.php?view_users'; });
            } else {
                alert('Failed to delete user: " . addslashes($error_msg) . "');
                window.location.href = 'index.php?view_users';
            }
        </script>";
        exit;
    }
} else {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No valid request parameters received.']);
        exit;
    }
    echo "<script>window.location.href = 'index.php?view_users';</script>";
    exit;
}
?>
