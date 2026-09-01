<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
/** @var mysqli $con */

header('Content-Type: application/json');

if (!isset($_SESSION['admin_email'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'get_vendors') {
    $section   = isset($_GET['section']) ? mysqli_real_escape_string($con, trim($_GET['section'])) : '';
    $search    = isset($_GET['search']) ? mysqli_real_escape_string($con, trim($_GET['search'])) : '';
    $city      = isset($_GET['city']) ? mysqli_real_escape_string($con, trim($_GET['city'])) : '';
    $sub_group = isset($_GET['sub_group']) ? mysqli_real_escape_string($con, trim($_GET['sub_group'])) : '';

    $where = " WHERE deleted_at IS NULL ";
    if (!empty($section)) {
        $where .= " AND category_section = '$section' ";
    }
    if (!empty($search)) {
        $where .= " AND (company_name LIKE '%$search%' OR contact_person LIKE '%$search%' OR vendor_custom_id LIKE '%$search%' OR city LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%' OR notes LIKE '%$search%') ";
    }
    if (!empty($city)) {
        $where .= " AND city = '$city' ";
    }
    if (!empty($sub_group)) {
        $where .= " AND sub_group = '$sub_group' ";
    }

    $get_vendors = "SELECT * FROM vendors $where ORDER BY sub_group ASC, id DESC";
    $run_vendors = mysqli_query($con, $get_vendors);

    $grouped = [];
    if ($run_vendors) {
        while ($row = mysqli_fetch_assoc($run_vendors)) {
            $sub = !empty($row['sub_group']) ? $row['sub_group'] : 'General';
            if (!isset($grouped[$sub])) {
                $grouped[$sub] = [];
            }
            $grouped[$sub][] = [
                'id' => (int)$row['id'],
                'vendor_custom_id' => $row['vendor_custom_id'] ?? '',
                'category_section' => $row['category_section'] ?? '',
                'sub_group' => $row['sub_group'] ?? '',
                'company_name' => $row['company_name'] ?? '',
                'contact_person' => $row['contact_person'] ?? '',
                'phone' => $row['phone'] ?? '',
                'email' => $row['email'] ?? '',
                'city' => $row['city'] ?? '',
                'address' => $row['address'] ?? '',
                'projects_count' => (int)$row['projects_count'],
                'notes' => $row['notes'] ?? ''
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'section' => $section,
        'grouped' => $grouped
    ]);
    exit;
}

if ($action === 'get_vendor') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Vendor ID']);
        exit;
    }

    $res = mysqli_query($con, "SELECT * FROM vendors WHERE id = '$id' AND deleted_at IS NULL LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        echo json_encode(['status' => 'success', 'vendor' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Vendor not found']);
    }
    exit;
}

if ($action === 'add_vendor') {
    $vendor_custom_id = mysqli_real_escape_string($con, trim($_POST['vendor_custom_id'] ?? ''));
    $category_section = mysqli_real_escape_string($con, trim($_POST['category_section'] ?? ''));
    $sub_group        = mysqli_real_escape_string($con, trim($_POST['sub_group'] ?? ''));
    $company_name     = mysqli_real_escape_string($con, trim($_POST['company_name'] ?? ''));
    $contact_person   = mysqli_real_escape_string($con, trim($_POST['contact_person'] ?? ''));
    $raw_phone        = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    $phone            = mysqli_real_escape_string($con, substr($raw_phone, 0, 10));
    $email            = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
    $city             = mysqli_real_escape_string($con, trim($_POST['city'] ?? ''));
    $address          = mysqli_real_escape_string($con, trim($_POST['address'] ?? ''));
    $projects_count   = (int)($_POST['projects_count'] ?? 0);
    $notes            = mysqli_real_escape_string($con, trim($_POST['notes'] ?? ''));

    if (empty($vendor_custom_id)) {
        $prefix = 'VND';
        if (preg_match_all('/\b(\w)/', $category_section, $m) && !empty($m[1])) {
            $prefix = strtoupper(implode('', array_slice($m[1], 0, 2)));
        }
        $max_res = mysqli_query($con, "SELECT MAX(id) as max_id FROM vendors");
        $next_id = 1;
        if ($max_res && $r = mysqli_fetch_assoc($max_res)) {
            $next_id = ((int)$r['max_id']) + 1;
        }
        $vendor_custom_id = $prefix . '-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
    }

    if (empty($company_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Company Name is required']);
        exit;
    }

    $insert = "INSERT INTO vendors (vendor_custom_id, category_section, sub_group, company_name, contact_person, phone, email, city, address, projects_count, notes) 
               VALUES ('$vendor_custom_id', '$category_section', '$sub_group', '$company_name', '$contact_person', '$phone', '$email', '$city', '$address', '$projects_count', '$notes')";

    if (mysqli_query($con, $insert)) {
        echo json_encode(['status' => 'success', 'message' => 'Vendor added successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($action === 'update_vendor') {
    $id               = (int)($_POST['id'] ?? 0);
    $vendor_custom_id = mysqli_real_escape_string($con, trim($_POST['vendor_custom_id'] ?? ''));
    $category_section = mysqli_real_escape_string($con, trim($_POST['category_section'] ?? ''));
    $sub_group        = mysqli_real_escape_string($con, trim($_POST['sub_group'] ?? ''));
    $company_name     = mysqli_real_escape_string($con, trim($_POST['company_name'] ?? ''));
    $contact_person   = mysqli_real_escape_string($con, trim($_POST['contact_person'] ?? ''));
    $raw_phone        = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
    $phone            = mysqli_real_escape_string($con, substr($raw_phone, 0, 10));
    $email            = mysqli_real_escape_string($con, trim($_POST['email'] ?? ''));
    $city             = mysqli_real_escape_string($con, trim($_POST['city'] ?? ''));
    $address          = mysqli_real_escape_string($con, trim($_POST['address'] ?? ''));
    $projects_count   = (int)($_POST['projects_count'] ?? 0);
    $notes            = mysqli_real_escape_string($con, trim($_POST['notes'] ?? ''));

    if ($id <= 0 || empty($company_name)) {
        echo json_encode(['status' => 'error', 'message' => 'Valid ID and Company Name are required']);
        exit;
    }

    $update = "UPDATE vendors SET 
               vendor_custom_id = '$vendor_custom_id',
               category_section = '$category_section',
               sub_group = '$sub_group',
               company_name = '$company_name',
               contact_person = '$contact_person',
               phone = '$phone',
               email = '$email',
               city = '$city',
               address = '$address',
               projects_count = '$projects_count',
               notes = '$notes'
               WHERE id = '$id'";

    if (mysqli_query($con, $update)) {
        echo json_encode(['status' => 'success', 'message' => 'Vendor updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

if ($action === 'delete_vendor') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Vendor ID']);
        exit;
    }

    $delete = "UPDATE vendors SET deleted_at = NOW() WHERE id = '$id'";
    if (mysqli_query($con, $delete)) {
        echo json_encode(['status' => 'success', 'message' => 'Vendor deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . mysqli_error($con)]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
