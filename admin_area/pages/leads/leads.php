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

// Auto-create leads and lead_followups tables if they don't exist
$create_leads = "CREATE TABLE IF NOT EXISTS leads (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255),
    company_name VARCHAR(255),
    project_name VARCHAR(255),
    description TEXT,
    remark TEXT,
    budget VARCHAR(100),
    currency VARCHAR(10) DEFAULT 'INR',
    lead_source VARCHAR(255),
    status ENUM('active', 'future', 'expired') DEFAULT 'active',
    assigned_employees TEXT NULL,
    assigned_admins TEXT NULL,
    followup_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($con, $create_leads);

// Auto-add assigned_employees and assigned_admins if missing
$check_col = mysqli_query($con, "SHOW COLUMNS FROM leads LIKE 'assigned_employees'");
if (mysqli_num_rows($check_col) == 0) {
    mysqli_query($con, "ALTER TABLE leads ADD COLUMN assigned_employees TEXT NULL AFTER status");
}
$check_col_admin = mysqli_query($con, "SHOW COLUMNS FROM leads LIKE 'assigned_admins'");
if (mysqli_num_rows($check_col_admin) == 0) {
    mysqli_query($con, "ALTER TABLE leads ADD COLUMN assigned_admins TEXT NULL AFTER assigned_employees");
}

$create_followups = "CREATE TABLE IF NOT EXISTS lead_followups (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    lead_id INT(11) NOT NULL,
    followup_date DATE NOT NULL,
    followup_method VARCHAR(100),
    followup_type VARCHAR(100),
    remark TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
)";
mysqli_query($con, $create_followups);

$create_sources = "CREATE TABLE IF NOT EXISTS lead_sources (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($con, $create_sources);

// Seed default sources if empty
$check_sources = mysqli_query($con, "SELECT id FROM lead_sources WHERE deleted_at IS NULL LIMIT 1");
if (mysqli_num_rows($check_sources) == 0) {
    mysqli_query($con, "INSERT IGNORE INTO lead_sources (source_name) VALUES ('Mechanical'), ('BNI'), ('Turnkey'), ('Electrical'), ('Civil')");
}

include("leads_logic.php");

// Filters & Search
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$source_filter = isset($_GET['source']) ? mysqli_real_escape_string($con, $_GET['source']) : '';
$cost_filter   = isset($_GET['cost']) ? mysqli_real_escape_string($con, $_GET['cost']) : '';
$search_query  = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';

// Count Leads for Cards
$total_leads  = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE deleted_at IS NULL"));
$active_leads = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='active' AND deleted_at IS NULL"));
$future_leads = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='future' AND deleted_at IS NULL"));
$expired_leads = mysqli_num_rows(mysqli_query($con, "SELECT id FROM leads WHERE status='expired' AND deleted_at IS NULL"));

/* ==============================
   PAGINATION SETUP & QUERIES
============================== */
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$start_from = $offset;

$where_clause = " WHERE deleted_at IS NULL ";
if ($status_filter) $where_clause .= " AND status='$status_filter' ";
if ($source_filter) $where_clause .= " AND lead_source LIKE '%$source_filter%' ";
if ($search_query) {
    $search_clean = trim($search_query);
    $emp_id_matches = [];
    $get_matching_emps = mysqli_query($con, "SELECT id FROM emp_list WHERE name LIKE '%$search_clean%'");
    if ($get_matching_emps && mysqli_num_rows($get_matching_emps) > 0) {
        while ($e_row = mysqli_fetch_assoc($get_matching_emps)) {
            $emp_id_matches[] = (int)$e_row['id'];
        }
    }
    $emp_where = "";
    if (!empty($emp_id_matches)) {
        $emp_conditions = [];
        foreach ($emp_id_matches as $e_id) {
            $emp_conditions[] = "FIND_IN_SET('$e_id', REPLACE(assigned_employees, ' ', '')) > 0";
        }
        $emp_where = " OR " . implode(" OR ", $emp_conditions);
    }
    $where_clause .= " AND (client_name LIKE '%$search_clean%' OR phone LIKE '%$search_clean%' OR company_name LIKE '%$search_clean%' OR project_name LIKE '%$search_clean%' $emp_where) ";
}

// Count total records with filters
$countSql = "SELECT COUNT(*) as total FROM leads" . $where_clause;
$countResult = mysqli_query($con, $countSql);
$totalRecords = 0;
if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRecords = $countRow['total'];
}
$totalPages = ceil($totalRecords / $limit);
$total_pages = $totalPages;

$order_by = " ORDER BY id DESC ";
if ($cost_filter === 'high_to_low') {
    $order_by = " ORDER BY CAST(REPLACE(REPLACE(budget, ',', ''), ' ', '') AS DECIMAL(15,2)) DESC, id DESC ";
} elseif ($cost_filter === 'low_to_high') {
    $order_by = " ORDER BY CAST(REPLACE(REPLACE(budget, ',', ''), ' ', '') AS DECIMAL(15,2)) ASC, id DESC ";
}

// Get filtered records for current page
$get_leads = "SELECT * FROM leads $where_clause $order_by LIMIT $offset, $limit";
$run_leads = mysqli_query($con, $get_leads);

?>

<style>
    .employee-group {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .emp-avatar-item {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-left: -10px;
        transition: transform 0.2s ease, z-index 0.2s ease;
    }

    .emp-avatar-item:first-child {
        margin-left: 0;
    }

    .emp-avatar-item:hover {
        z-index: 10;
        transform: translateY(-2px);
    }

    .emp-avatar-item img,
    .emp-avatar-item .emp-initial {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .emp-avatar-item .emp-initial {
        background: #dee2e6;
        color: #fff;
        font-size: 12px;
        font-weight: 700;
    }

    .employee-group .more {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #e2e8f0;
        border: 2px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        color: #475569;
        margin-left: -10px;
    }

    .employee-group .more:hover {
        background: #cbd5e1;
    }

    /* Fast Custom Tooltip */
    .emp-avatar-item[data-tooltip] {
        position: relative;
    }

    .emp-avatar-item[data-tooltip]::after {
        content: attr(data-tooltip);
        position: absolute;
        bottom: calc(100% + 8px);
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        background: #dd2127;
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease, transform 0.15s ease;
        z-index: 99999;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .emp-avatar-item[data-tooltip]::before {
        content: '';
        position: absolute;
        bottom: calc(100% + 3px);
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        border-width: 5px 5px 0 5px;
        border-style: solid;
        border-color: #dd2127 transparent transparent transparent;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease, transform 0.15s ease;
        z-index: 99999;
    }

    .emp-avatar-item[data-tooltip]:hover::after,
    .emp-avatar-item[data-tooltip]:hover::before {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }
</style>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <div class="header-actions-premium">
            <a href="pages/leads/export_leads.php" class="btn-premium-add" style="background: #10b981 !important;">
                <i class="fa fa-file-excel-o"></i> Download Report
            </a>
            <?php if (canAdminAccess('lead_insert')): ?>
                <a href="index.php?add_lead" class="btn-premium-add" style="margin-left: 10px;">
                    <i class="fa fa-plus"></i> Add New Lead
                </a>
            <?php endif; ?>
        </div>
    </div>


    <!-- Stats Cards -->
    <!-- <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-3">
            <a href="index.php?leads" style="text-decoration: none; outline: none; border: none;">
                <div class="premium-card" style="margin-bottom: 0; padding: 20px; border-left: 5px solid #4f46e5;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Total Leads</p>
                            <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo $total_leads; ?></h2>
                        </div>
                        <div style="width: 45px; height: 45px; background: rgba(79, 70, 229, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-users" style="color: #4f46e5; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?leads&status=active&source=<?php echo $source_filter; ?>" style="text-decoration: none; outline: none; border: none;">
                <div class="premium-card" style="margin-bottom: 0; padding: 20px; border-left: 5px solid #10b981;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Active</p>
                            <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo $active_leads; ?></h2>
                        </div>
                        <div style="width: 45px; height: 45px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?leads&status=future&source=<?php echo $source_filter; ?>" style="text-decoration: none; outline: none; border: none;">
                <div class="premium-card" style="margin-bottom: 0; padding: 20px; border-left: 5px solid #3b82f6;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Future</p>
                            <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo $future_leads; ?></h2>
                        </div>
                        <div style="width: 45px; height: 45px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-calendar" style="color: #3b82f6; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="index.php?leads&status=expired&source=<?php echo $source_filter; ?>" style="text-decoration: none; outline: none; border: none;">
                <div class="premium-card" style="margin-bottom: 0; padding: 20px; border-left: 5px solid #ef4444;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <p style="color: #64748b; font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 5px;">Expired</p>
                            <h2 style="margin: 0; color: #1e293b; font-weight: 800;"><?php echo $expired_leads; ?></h2>
                        </div>
                        <div style="width: 45px; height: 45px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fa fa-times-circle" style="color: #ef4444; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div> -->

    <div class="stat-cards-row">
        <?php $current_source = isset($_GET['source']) ? '&source=' . htmlspecialchars($_GET['source']) : ''; ?>
        <!-- Total Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-purple">
                <i class="fa fa-briefcase"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Total Leads</div>
                <div class="stat-card-value"><?php echo $total_leads; ?></div>
            </div>
        </div>

        <!-- Active Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=active<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-green">
                <i class="fa fa-folder-open"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Current Leads</div>
                <div class="stat-card-value"><?php echo $active_leads; ?></div>
            </div>
        </div>

        <!-- Completed Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=future<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-orange">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Upcoming</div>
                <div class="stat-card-value"><?php echo $future_leads; ?></div>
            </div>
        </div>

        <!-- Pending Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?leads&status=expired<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-blue">
                <i class="fa fa-clock-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Lost Leads</div>
                <div class="stat-card-value"><?php echo $expired_leads; ?></div>
            </div>
        </div>
    </div>



    <!-- Filters & List -->
    <div class="premium-card">
        <div class="card-hdr">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-list"></i>
                <h3>All Leads</h3>
            </div>

            <form method="GET" style="display: flex; gap: 10px; margin: 0;">
                <input type="hidden" name="leads">
                <input type="hidden" name="source" value="<?php echo $source_filter; ?>">
                <!-- <div style="position: relative; width: 250px;">
                    <i class="fa fa-search" style="position: absolute; left: 12px; top: 12px; color: #94a3b8;"></i>
                    <input type="text" name="search" class="p-input-premium" value="<?php echo $search_query; ?>" placeholder="Search name/phone..." style="height: 38px; padding-left: 35px; border: none; background: rgba(255,255,255,0.1); color: #fff;">
                </div> -->
                <!-- <select name="status" class="p-input-premium" style="height: 38px; border: none; background: rgba(255,255,255,0.1); color: #fff; width: 120px;" onchange="this.form.submit()">
                    <option value="" style="color: #333;">All Status</option>
                    <option value="active" <?php if ($status_filter == 'active') echo 'selected'; ?> style="color: #333;">Active</option>
                    <option value="future" <?php if ($status_filter == 'future') echo 'selected'; ?> style="color: #333;">Future</option>
                    <option value="expired" <?php if ($status_filter == 'expired') echo 'selected'; ?> style="color: #333;">Expired</option>
                </select> -->
                <!-- <button type="submit" class="btn btn-primary btn-sm" style="height: 38px; border-radius: 10px; background: #4f46e5;">Filter</button> -->
            </form>
        </div>

        <div class="table-responsive">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th>Client Info</th>
                        <th>Project Type</th>
                        <th style="text-align: center; min-width: 130px;">Assigned Emp</th>
                        <?php if (canAdminAccess('project_source_view')): ?>
                            <th style="position: relative; overflow: visible; min-width: 100px; text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 12px; color: <?php echo !empty($source_filter) ? '#1e293b' : '#64748b'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                    <?php echo !empty($source_filter) ? $source_filter : 'Source'; ?>
                                    <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($source_filter) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                                </div>
                                <select id="sourceSelect" onchange="applySourceFilter(this.value)"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                    <?php
                                    $get_all_sources = "SELECT * FROM lead_sources WHERE deleted_at IS NULL ORDER BY source_name ASC";
                                    $run_all_sources = mysqli_query($con, $get_all_sources);
                                    while ($s_row = mysqli_fetch_array($run_all_sources)) {
                                        $s_name = $s_row['source_name'];
                                        $selected = ($source_filter == $s_name) ? 'selected' : '';
                                        echo "<option value='" . htmlspecialchars($s_name) . "' $selected>" . htmlspecialchars($s_name) . "</option>";
                                    }
                                    ?>
                                </select>
                            </th>
                        <?php endif; ?>
                        <th style="position: relative; overflow: visible; min-width: 110px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 12px; color: <?php echo !empty($cost_filter) ? '#1e293b' : '#64748b'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                <?php
                                if ($cost_filter == 'high_to_low') {
                                    echo 'High to Low';
                                } elseif ($cost_filter == 'low_to_high') {
                                    echo 'Low to High';
                                } else {
                                    echo 'Cost';
                                }
                                ?>
                                <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($cost_filter) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                            </div>
                            <select id="costSelect" onchange="applyCostFilter(this.value)"
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                <option value="" <?php if (empty($cost_filter) || $cost_filter == 'recent') echo 'selected'; ?>>Recent Leads (Default)</option>
                                <option value="high_to_low" <?php if ($cost_filter == 'high_to_low') echo 'selected'; ?>>High to Low</option>
                                <option value="low_to_high" <?php if ($cost_filter == 'low_to_high') echo 'selected'; ?>>Low to High</option>
                            </select>
                        </th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Next Call</th>
                        <th style="text-align: center;">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $i = $start_from;
                    if ($run_leads && mysqli_num_rows($run_leads) > 0):
                        while ($row = mysqli_fetch_array($run_leads)):
                            $i++;
                            $id = $row['id'];
                            $name = $row['client_name'];
                            $phone = $row['phone'];
                            $email = $row['email'];
                            $status = $row['status'];
                            $project_name = $row['project_name'];
                            $source = $row['lead_source'];
                            $budget = $row['budget'];
                            $currency = isset($row['currency']) ? $row['currency'] : 'INR';
                            $f_date = $row['followup_date'];
                    ?>
                            <tr>
                                <td style="text-align: center; color: #94a3b8; font-weight: 700;"><?php echo $i; ?></td>
                                <td>
                                    <div style="display: flex; flex-direction: column; align-items: flex-start;">
                                        <strong style="color: #1e293b; font-size: 15px;"><?php echo $name; ?></strong>
                                        <span style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                            <i class="fa fa-phone"></i> <?php echo $phone; ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="font-weight: 500; color: #475569;"><?php echo !empty($project_name) ? $project_name : '-'; ?></td>
                                <td style="text-align: center;">
                                    <div class="employee-wrap" style="display:flex; justify-content:center;">
                                        <?php
                                        $assigned_team = [];

                                        // 1. Fetch assigned employees
                                        $emp_ids_str = !empty($row['assigned_employees']) ? $row['assigned_employees'] : '';
                                        if (!empty($emp_ids_str)) {
                                            $emp_ids_arr = array_filter(array_map('intval', explode(',', $emp_ids_str)));
                                            if (!empty($emp_ids_arr)) {
                                                $ids_impl = implode(',', $emp_ids_arr);
                                                $run_emps_list = mysqli_query($con, "SELECT name, employee_image FROM emp_list WHERE id IN ($ids_impl)");
                                                if ($run_emps_list) {
                                                    while ($e_info = mysqli_fetch_assoc($run_emps_list)) {
                                                        $assigned_team[] = [
                                                            'name'  => $e_info['name'],
                                                            'image' => !empty($e_info['employee_image']) ? 'uploads/' . $e_info['employee_image'] : '',
                                                            'type'  => 'employee'
                                                        ];
                                                    }
                                                }
                                            }
                                        }

                                        // 2. Fetch assigned admins
                                        $adm_ids_str = !empty($row['assigned_admins']) ? $row['assigned_admins'] : '';
                                        if (!empty($adm_ids_str)) {
                                            $adm_ids_arr = array_filter(array_map('intval', explode(',', $adm_ids_str)));
                                            if (!empty($adm_ids_arr)) {
                                                $ids_adm_impl = implode(',', $adm_ids_arr);
                                                $run_adms_list = mysqli_query($con, "SELECT admin_name, admin_image FROM admins WHERE admin_id IN ($ids_adm_impl)");
                                                if ($run_adms_list) {
                                                    while ($a_info = mysqli_fetch_assoc($run_adms_list)) {
                                                        $assigned_team[] = [
                                                            'name'  => $a_info['admin_name'],
                                                            'image' => !empty($a_info['admin_image']) ? 'admin_images/' . $a_info['admin_image'] : '',
                                                            'type'  => 'admin'
                                                        ];
                                                    }
                                                }
                                            }
                                        }

                                        if (!empty($assigned_team)) {
                                            echo '<div class="employee-group">';
                                            $team_limit = 3;
                                            $t_count = 0;

                                            foreach ($assigned_team as $member) {
                                                $m_name = htmlspecialchars($member['name']);
                                                $m_img = $member['image'];
                                                $isHidden = $t_count >= $team_limit ? 'display: none;' : '';
                                                $hiddenClass = $t_count >= $team_limit ? 'hidden-employee' : '';

                                                if (!empty($m_img) && file_exists($m_img)) {
                                                    echo '<span class="emp-avatar-item ' . $hiddenClass . '" data-tooltip="' . $m_name . '" style="' . $isHidden . '">';
                                                    echo '<img src="' . htmlspecialchars($m_img) . '" alt="' . $m_name . '" onerror="this.src=\'admin_images/default.png\'">';
                                                    echo '</span>';
                                                } else {
                                                    $initial = strtoupper(substr($member['name'], 0, 1));
                                                    $bg_color = $member['type'] == 'admin' ? '#ef4444' : '#3b82f6';
                                                    echo '<span class="emp-avatar-item ' . $hiddenClass . '" data-tooltip="' . $m_name . '" style="' . $isHidden . '">';
                                                    echo '<div class="emp-initial" style="background:' . $bg_color . '; color:#fff;">' . $initial . '</div>';
                                                    echo '</span>';
                                                }
                                                $t_count++;
                                            }

                                            if ($t_count > $team_limit) {
                                                echo '<span class="more emp-avatar-item" data-tooltip="Show all" onclick="this.parentElement.querySelectorAll(\'.hidden-employee\').forEach(el => el.style.display = \'inline-flex\'); this.style.display = \'none\';">+' . ($t_count - $team_limit) . '</span>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span style="color:#94a3b8; font-size:12px;">Unassigned</span>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <?php if (canAdminAccess('project_source_view')): ?>
                                    <td style="text-align: center;">
                                        <span style="font-size: 12px; color: #475569; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;width: 90px;display: inline-block;white-space: normal;word-wrap: break-word;"><?php echo $source; ?></span>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: center; font-weight: 700; color: #1e293b;"><?php echo !empty($budget) ? (isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency) . ' ' . $budget : '-'; ?></td>
                                <td style="text-align: center;">
                                    <?php
                                    $badge = 'p-badge-success';
                                    if ($status == 'future') $badge = 'p-badge-primary';
                                    if ($status == 'expired') $badge = 'p-badge-danger';

                                    // Map status to colors for inline select styling
                                    $color_map = [
                                        'active' => '#16a34a',
                                        'future' => '#2563eb',
                                        'expired' => '#ef4444'
                                    ];
                                    $bg_map = [
                                        'active' => '#f0fdf4',
                                        'future' => '#eff6ff',
                                        'expired' => '#fef2f2'
                                    ];
                                    $border_map = [
                                        'active' => '#dcfce7',
                                        'future' => '#dbeafe',
                                        'expired' => '#fee2e2'
                                    ];
                                    $current_color = isset($color_map[$status]) ? $color_map[$status] : '#475569';
                                    $current_bg = isset($bg_map[$status]) ? $bg_map[$status] : '#f8fafc';
                                    $current_border = isset($border_map[$status]) ? $border_map[$status] : '#e2e8f0';
                                    ?>
                                    <select class="lead-status-select" data-lead-id="<?php echo $id; ?>" <?php echo !canAdminAccess('lead_update') ? 'disabled' : ''; ?>
                                        style="appearance: none; -webkit-appearance: none; background: <?php echo $current_bg; ?> url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2212%22 height=%2212%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22<?php echo urlencode($current_color); ?>%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpolyline points=%226 9 12 15 18 9%22%3E%3C/polyline%3E%3C/svg%3E') no-repeat right 12px center; color: <?php echo $current_color; ?>; border: 1px solid <?php echo $current_border; ?>; font-size: 10px; font-weight: 900; text-transform: uppercase; padding: 7px 32px 7px 15px; border-radius: 20px; letter-spacing: 0.8px; cursor: pointer; outline: none; transition: all 0.3s ease; width: auto; min-width: 120px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); <?php echo !canAdminAccess('lead_update') ? 'opacity: 0.7; cursor: not-allowed;' : ''; ?>">
                                        <option value="active" <?php if ($status == 'active') echo 'selected'; ?>>Active</option>
                                        <option value="future" <?php if ($status == 'future') echo 'selected'; ?>>Future</option>
                                        <option value="expired" <?php if ($status == 'expired') echo 'selected'; ?>>Expired</option>
                                    </select>
                                </td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?php
                                    if (!empty($f_date)) {
                                        $display_date = date('d-m-Y', strtotime($f_date));
                                        $today_str = date('Y-m-d');

                                        if ($f_date == $today_str && $status == 'active') {
                                            // Today's Follow-up
                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; color:#ef4444; background:#fef2f2; padding:4px 10px; border-radius:6px; border:1px solid #fee2e2;">';
                                            echo '<span style="display:inline-block; width:6px; height:6px; background:#ef4444; border-radius:50%; box-shadow:0 0 0 0 rgba(239, 68, 68, 1); animation:pulse-red 2s infinite;"></span>';
                                            echo $display_date;
                                            echo '</div>';
                                        } else if ($f_date < $today_str && $status == 'active') {
                                            // Overdue Follow-up
                                            echo '<div style="display:inline-flex; align-items:center; gap:6px; color:#eab308; background:#fefce8; padding:4px 10px; border-radius:6px; border:1px solid #fef08a;">';
                                            echo '<i class="fa fa-exclamation-circle"></i>';
                                            echo $display_date;
                                            echo '</div>';
                                        } else {
                                            // Normal Future or other status
                                            echo '<div style="color: #4f46e5;">' . $display_date . '</div>';
                                        }
                                    } else {
                                        echo '<span style="color:#94a3b8;">N/A</span>';
                                    }
                                    ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <a href="index.php?view_lead=<?php echo $id; ?>" class="btn-icon-premium btn-icon-view" title="View History">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                        <?php if (canAdminAccess('lead_update')): ?>
                                            <a href="index.php?edit_lead=<?php echo $id; ?>" class="btn-icon-premium btn-icon-edit" title="Edit Lead">
                                                <i class="fa fa-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        <!-- <button onclick="openFollowupModal(<?php echo $id; ?>, '<?php echo htmlspecialchars($name); ?>')" class="btn-icon-premium btn-icon-edit" style="background: rgba(16, 185, 129, 0.1); color: #10b981;" title="Add Follow-up">
                                            <i class="fa fa-plus"></i>
                                        </button> -->
                                        <?php if (canAdminAccess('lead_delete')): ?>
                                            <a href="javascript:void(0)" onclick="confirmLeadDelete(<?php echo $id; ?>, '<?php echo addslashes($name); ?>')" class="btn-icon-premium btn-icon-delete" title="Delete Lead">
                                                <i class="fa fa-trash-o"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 60px; color: #94a3b8;">
                                <div style="background: #f8fafc; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                                    <i class="fa fa-bullseye" style="font-size: 35px; color: #cbd5e1;"></i>
                                </div>
                                <h3 style="color: #64748b; font-size: 18px; font-weight: 700; margin-bottom: 5px;">No leads found.</h3>
                                <p style="font-size: 14px; color: #94a3b8; margin-bottom: 20px;">No leads match your search.</p>
                                <?php if (!empty($source_filter) || !empty($status_filter) || !empty($search_query)): ?>
                                    <a href="index.php?leads" class="btn btn-primary btn-sm" style="background: #4f46e5; border: none; border-radius: 8px; padding: 8px 20px;">Clear All Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- Pagination UI -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-premium">
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $qString = http_build_query($queryParams);
                $baseUrl = "index.php";
                if (!empty($qString)) {
                    $baseUrl .= "?" . $qString . "&";
                } else {
                    $baseUrl .= "?";
                }
                ?>
                <a href="<?php echo $baseUrl; ?>page=<?php echo max(1, $page - 1); ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fa fa-angle-left"></i> Prev</a>

                <?php
                // Display up to 5 page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $startPage + 4);
                if ($endPage - $startPage < 4) {
                    $startPage = max(1, $endPage - 4);
                }

                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="<?php echo $baseUrl; ?>page=<?php echo $p; ?>" class="page-link <?php echo $page == $p ? 'active' : ''; ?>"><?php echo $p; ?></a>
                <?php endfor; ?>

                <a href="<?php echo $baseUrl; ?>page=<?php echo min($totalPages, $page + 1); ?>" class="page-link <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">Next <i class="fa fa-angle-right"></i></a>
            </div>
        <?php endif; ?>


    </div>
</div>

<?php include("leads_modal.php"); ?>

<style>
    @keyframes pulse-red {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        }
    }



    .table-premium td {
        padding: 20px 25px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    .table-premium td .fa-phone {
        margin-right: 3px;
    }

    .p-badge-danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .pagination-premium {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        padding: 20px;
        gap: 8px;
        border-top: 1px solid #f1f5f9;
    }

    .page-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-weight: 600;
        font-size: 13px;
        text-decoration: none !important;
        transition: all 0.2s;
        gap: 6px;
    }

    .page-link:hover:not(.disabled) {
        background: #dd2127;
        color: #FFEAEB;
        border-color: #dd2127;
    }


    .page-link.active {
        background: #FFEAEB;
        color: #dd2127;
        border-color: #dd2127;
        text-decoration: none !important;
    }

    .page-link.disabled {
        opacity: 0.5;
        pointer-events: none;
        background: #f8fafc;
    }


    /* SweetAlert2 Premium Overrides (kept for status update alerts) */
    .swal2-backdrop-show {
        backdrop-filter: blur(8px) !important;
        background: rgba(15, 23, 42, 0.5) !important;
    }

    /* Custom Confirmation Modal (Matching Projects UI) */
    .premium-confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(8px);
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .premium-confirm-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .premium-confirm-modal {
        background: #fff;
        width: 100%;
        max-width: 400px;
        border-radius: 30px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: scale(0.9);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        padding: 40px 30px;
        text-align: center;
    }

    .premium-confirm-overlay.active .premium-confirm-modal {
        transform: scale(1);
    }

    .confirm-icon-box {
        width: 70px;
        height: 70px;
        background: #fee2e2;
        color: #ef4444;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin: 0 auto 25px auto;
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

    .premium-confirm-header h3 {
        margin: 0 0 12px 0;
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
    }

    .premium-confirm-header p {
        margin: 0 0 30px 0;
        font-size: 14px;
        color: #64748b;
        line-height: 1.6;
    }

    .premium-confirm-footer {
        display: flex;
        gap: 15px;
    }

    .confirm-btn-cancel {
        flex: 1;
        padding: 14px;
        border-radius: 14px;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .confirm-btn-cancel:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .confirm-btn-delete {
        flex: 1;
        padding: 14px;
        border-radius: 14px;
        background: #ef4444;
        color: #fff;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
    }

    .confirm-btn-delete:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(239, 68, 68, 0.4);
    }

    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<!-- Custom Confirmation Overlay for Deletion (Matching Projects UI) -->
<div id="leadDeleteConfirmOverlay" class="premium-confirm-overlay">
    <div class="premium-confirm-modal">
        <div class="confirm-icon-box">
            <i class="fa fa-trash"></i>
        </div>
        <div class="premium-confirm-header">
            <h3>Erase Lead?</h3>
            <p>You are about to permanently delete <strong id="delete_lead_name_label" style="color: #1e293b;"></strong>. This action cannot be undone and all associated follow-ups will be lost.</p>
        </div>
        <div class="premium-confirm-footer">
            <button class="confirm-btn-cancel" onclick="closeLeadDeleteConfirm()">Cancel</button>
            <button class="confirm-btn-delete" id="confirmLeadDeleteBtn">Delete Lead</button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php
        // Check if there are any follow-ups today
        $today_date = date('Y-m-d');
        $check_today = mysqli_query($con, "SELECT COUNT(*) as cnt FROM leads WHERE followup_date = '$today_date' AND status != 'expired' AND deleted_at IS NULL");
        $today_count = 0;
        if ($check_today) {
            $today_row = mysqli_fetch_assoc($check_today);
            $today_count = $today_row['cnt'];
        }
        if ($today_count > 0 && empty($search_query) && empty($status_filter) && empty($source_filter) && !isset($_SESSION['lead_toast_shown'])) {
            $_SESSION['lead_toast_shown'] = true; // Mark as shown
            // Only show toast on main leads load, not when filtering
            echo "
                setTimeout(() => {
                    let container = document.getElementById('toast-container-custom');
                    if (!container) {
                        container = document.createElement('div');
                        container.id = 'toast-container-custom';
                        container.style.cssText = 'position: fixed; top: 30px; right: 30px; z-index: 10000;';
                        document.body.appendChild(container);
                    }
                    const toast = document.createElement('div');
                    toast.style.cssText = 'background: #0f172a; color: #fff; padding: 18px 25px; border-radius: 16px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.1); min-width: 300px; border-left: 4px solid #ef4444;';
                    toast.innerHTML = `
                    <div style=\"background: rgba(239, 68, 68, 0.2); color: #ef4444; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;\">
                        <i class=\"fa fa-bell\" style=\"font-size: 14px; animation: pulse-red 2s infinite;\"></i>
                    </div>
                    <div style=\"flex-grow: 1;\">
                        <div style=\"font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;\">Follow-up Reminder</div>
                        <div style=\"font-size: 14px; font-weight: 600;\">You have $today_count lead(s) to follow up today!</div>
                    </div>
                    `;
                    container.appendChild(toast);
                    setTimeout(() => toast.style.transform = 'translateX(0)', 10);
                    setTimeout(() => {
                        toast.style.transform = 'translateX(120%)';
                        setTimeout(() => toast.remove(), 400);
                    }, 6000);
                }, 500);
            ";
        }
        ?>
    });

    function applySourceFilter(value) {
        const status = "<?php echo $status_filter; ?>";
        const search = "<?php echo $search_query; ?>";
        const cost = "<?php echo $cost_filter; ?>";
        window.location.href = `index.php?leads&source=${encodeURIComponent(value)}&status=${status}&search=${search}&cost=${cost}`;
    }

    function applyCostFilter(value) {
        const status = "<?php echo $status_filter; ?>";
        const search = "<?php echo $search_query; ?>";
        const source = "<?php echo $source_filter; ?>";
        window.location.href = `index.php?leads&cost=${encodeURIComponent(value)}&status=${status}&search=${search}&source=${source}`;
    }

    function showPremiumAlert(message) {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.cssText = 'position: fixed; top: 30px; right: 30px; z-index: 10000;';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.style.cssText = 'background: #0f172a; color: #fff; padding: 18px 25px; border-radius: 16px; margin-bottom: 15px; display: flex; align-items: center; gap: 15px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); transform: translateX(120%); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.1); min-width: 300px;';
        toast.innerHTML = `
        <div style="background: #10b981; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="fa fa-check" style="font-size: 14px;"></i>
        </div>
        <div style="flex-grow: 1;">
            <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px;">Success</div>
            <div style="font-size: 14px; font-weight: 600;">${message}</div>
        </div>
    `;
        container.appendChild(toast);
        setTimeout(() => toast.style.transform = 'translateX(0)', 10);
        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    $(document).on('change', '.lead-status-select', function() {
        const select = $(this);
        const leadId = select.data('lead-id');
        const newStatus = select.val();

        select.css('opacity', '0.5');

        $.ajax({
            url: 'ajax/leads/ajax_update_lead_status.php',
            method: 'POST',
            data: {
                lead_id: leadId,
                status: newStatus
            },
            success: function(response) {
                select.css('opacity', '1');
                if (response.success) {
                    // Update colors dynamically
                    const colorMap = {
                        'active': '#16a34a',
                        'future': '#2563eb',
                        'expired': '#ef4444'
                    };
                    const bgMap = {
                        'active': '#f0fdf4',
                        'future': '#eff6ff',
                        'expired': '#fef2f2'
                    };
                    const borderMap = {
                        'active': '#dcfce7',
                        'future': '#dbeafe',
                        'expired': '#fee2e2'
                    };

                    select.css({
                        'background': bgMap[newStatus],
                        'color': colorMap[newStatus],
                        'border-color': borderMap[newStatus]
                    });

                    showPremiumAlert(`Status updated to ${newStatus}`);
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', response.message, 'error');
                    } else {
                        Swal.fire('Notification', "Error: " + response.message, 'error');
                    }
                }
            },
            error: function() {
                select.css('opacity', '1');
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Connection error.', 'error');
                } else {
                    Swal.fire('Notification', "Connection error.", 'error');
                }
            }
        });
    });

    function openFollowupModal(leadId, clientName) {
        document.getElementById('modalLeadId').value = leadId;
        document.getElementById('modalClientName').innerText = clientName;
        $('#followupModal').modal('show');
    }

    let leadToDelete = null;

    function confirmLeadDelete(id, name) {
        leadToDelete = id;
        document.getElementById('delete_lead_name_label').innerText = name;
        document.getElementById('leadDeleteConfirmOverlay').classList.add('active');
    }

    function closeLeadDeleteConfirm() {
        document.getElementById('leadDeleteConfirmOverlay').classList.remove('active');
        leadToDelete = null;
    }

    document.getElementById('confirmLeadDeleteBtn').addEventListener('click', function() {
        if (leadToDelete) {
            window.location.href = `index.php?delete_lead=${leadToDelete}`;
        }
    });
</script>