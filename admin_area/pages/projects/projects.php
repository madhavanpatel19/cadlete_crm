<?php
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

// Always read admin status fresh from DB (bypass session cache)
$current_admin_id_proj = 0;
$is_super_admin_proj = false;
if (isset($_SESSION['admin_email'])) {
    $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
    $r = mysqli_query($con, "SELECT admin_id, is_super_admin FROM admins WHERE admin_email='$email_esc' LIMIT 1");
    if ($r && $row_a = mysqli_fetch_assoc($r)) {
        $current_admin_id_proj = (int)$row_a['admin_id'];
        $is_super_admin_proj   = !empty($row_a['is_super_admin']);
    }
}

// If NOT super admin, restrict to projects where this admin is assigned ONLY IF they have the restriction permission
$admin_project_filter = '';
if (!$is_super_admin_proj && $current_admin_id_proj > 0) {
    if (canAdminAccess('project_assigned_only') || canAdminAccess('subadmin_assigned_project_only')) {
        $admin_project_filter = " AND (FIND_IN_SET('$current_admin_id_proj', REPLACE(assigned_admins, ' ', '')) > 0) ";
    }
}

// Fetch all active clients for the dropdown
$get_clients = "SELECT * FROM clients WHERE deleted_at IS NULL ORDER BY name ASC";
$run_clients = mysqli_query($con, $get_clients);


$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$source_filter = isset($_GET['source']) ? mysqli_real_escape_string($con, $_GET['source']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';


// Count projects for Cards (scoped to visible projects)
$total_projects     = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE deleted_at IS NULL $admin_project_filter"));
$active_projects    = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Active' AND deleted_at IS NULL $admin_project_filter"));
$pending_projects   = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Pending' AND deleted_at IS NULL $admin_project_filter"));
$completed_projects = mysqli_num_rows(mysqli_query($con, "SELECT id FROM client_projects WHERE status='Completed' AND deleted_at IS NULL $admin_project_filter"));
$employees = mysqli_fetch_assoc(mysqli_query($con, "SELECT assigned_employees from client_projects WHERE deleted_at IS NULL "));

/* ==============================
   PAGINATION SETUP & QUERIES
============================== */
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;
$start_from = $offset;

$where_clause = " WHERE cp.deleted_at IS NULL AND c.deleted_at IS NULL $admin_project_filter ";
if ($status_filter) $where_clause .= " AND cp.status='$status_filter' ";
if ($source_filter) $where_clause .= " AND cp.source LIKE '%$source_filter%' ";
if ($search) {
    $search_clean = trim($search);
    $search_clean = ltrim($search_clean, '#');

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
            $emp_conditions[] = "FIND_IN_SET('$e_id', REPLACE(cp.assigned_employees, ' ', '')) > 0";
        }
        $emp_where = " OR " . implode(" OR ", $emp_conditions);
    }

    $full_match = "(cp.project_name LIKE '%$search_clean%' OR c.name LIKE '%$search_clean%' OR cp.id LIKE '%$search_clean%' OR cp.source LIKE '%$search_clean%' OR cp.status LIKE '%$search_clean%' $emp_where)";

    $words = array_filter(explode(' ', $search_clean), function ($w) {
        return strlen(trim($w)) > 1;
    });

    if (count($words) > 1) {
        $word_clauses = [];
        foreach ($words as $w) {
            $w_esc = mysqli_real_escape_string($con, $w);
            $word_clauses[] = "(cp.project_name LIKE '%$w_esc%' OR c.name LIKE '%$w_esc%' OR cp.id LIKE '%$w_esc%' OR cp.source LIKE '%$w_esc%' OR cp.status LIKE '%$w_esc%')";
        }
        $all_words_clause = "(" . implode(" AND ", $word_clauses) . ")";
        $where_clause .= " AND ($full_match OR $all_words_clause) ";
    } else {
        $where_clause .= " AND $full_match ";
    }
}


// Count total records with filters
$countSql = "SELECT COUNT(*) as total FROM client_projects cp JOIN clients c ON cp.client_id = c.id " . $where_clause;
$countResult = mysqli_query($con, $countSql);
$totalRecords = 0;
if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $totalRecords = $countRow['total'];
}
$totalPages = ceil($totalRecords / $limit);
$total_pages = $totalPages;

// Get filtered records for current page
$get_projects = "SELECT cp.*, c.name as client_name, c.image FROM client_projects cp JOIN clients c ON cp.client_id = c.id $where_clause ORDER BY cp.id DESC LIMIT $offset, $limit";
$run_projects = mysqli_query($con, $get_projects);


?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1>
            <!-- <i class="fa fa-project-diagram" style="color: #333;"></i>         
            Global Project Portfolio -->
        </h1>
        <div class="header-actions-premium" style="display: flex; gap: 12px; align-items: center;">
            <div style="position: relative;">
                <i class="fa fa-search" style="position: absolute; left: 15px; top: 13px; color: #94a3b8;"></i>
                <input type="text" id="header_search" class="p-input-premium" placeholder=" Search... " value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 40px; height: 42px; width: 300px; font-size: 14px;" onchange="applyColumnFilter('search', this.value)">
            </div>
            <?php if (canAdminAccess('project_insert')): ?>
                <a href="index.php?add_project" class="btn-premium-add">
                    <i class="fa fa-plus"></i> Add New Project
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-cards-row">
        <?php $current_source = isset($_GET['source']) ? '&source=' . htmlspecialchars($_GET['source']) : ''; ?>
        <!-- Total Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-purple">
                <i class="fa fa-briefcase"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Total Projects</div>
                <div class="stat-card-value"><?php echo $total_projects; ?></div>
            </div>
        </div>

        <!-- Active Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Active<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-green">
                <i class="fa fa-folder-open"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Active Projects</div>
                <div class="stat-card-value"><?php echo $active_projects; ?></div>
            </div>
        </div>

        <!-- Completed Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Completed<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-orange">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Completed Projects</div>
                <div class="stat-card-value"><?php echo $completed_projects; ?></div>
            </div>
        </div>

        <!-- Pending Projects -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?projects&status=Pending<?php echo $current_source; ?>'">
            <div class="stat-card-icon sc-blue">
                <i class="fa fa-clock-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Pending Projects</div>
                <div class="stat-card-value"><?php echo $pending_projects; ?></div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 0;">
        <div class="col-md-12">
            <!-- Projects Table -->
            <div class="premium-card">
                <div class="card-hdr">
                    <div class="header-left">
                        <i class="fa fa-list-ul"></i>
                        <h3>All Projects</h3>
                    </div>
                    <!-- <button type="button" class="btn-premium-add" data-toggle="modal" data-target="#addProjectModal">
                        <i class="fa fa-plus"></i> Add New Project
                    </button> -->
                </div>
                <div style="overflow-x: auto;">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">ID</th>
                                <th>Project Name</th>
                                <th>Team Members</th>
                                <?php if (canAdminAccess('project_source_view')): ?>
                                    <th style="position: relative; overflow: visible; min-width: 100px; padding: 15px 10px !important;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 12px; color: <?php echo !empty($_GET['source']) ? '#1e293b' : '#64748b'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                            <?php echo !empty($_GET['source']) ? htmlspecialchars($_GET['source']) : 'Source'; ?>
                                            <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($_GET['source']) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                                        </div>
                                        <select id="sourceSelect" onchange="applySourceFilter(this.value)"
                                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                            <?php
                                            $source_filter = isset($_GET['source']) ? $_GET['source'] : '';
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
                                <th style="text-align: center;">Expenses</th>
                                <th style="position: relative; overflow: visible; min-width: 110px; padding: 15px 10px !important; text-align: center;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 12px; color: <?php echo !empty($_GET['cost']) ? '#1e293b' : '#64748b'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                        <?php
                                        $c_filt = isset($_GET['cost']) ? $_GET['cost'] : '';
                                        if ($c_filt == 'high_to_low') {
                                            echo 'High to Low';
                                        } elseif ($c_filt == 'low_to_high') {
                                            echo 'Low to High';
                                        } else {
                                            echo 'Cost';
                                        }
                                        ?>
                                        <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($c_filt) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                                    </div>
                                    <select id="costSelect" onchange="applyCostFilter(this.value)"
                                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                        <option value="" <?php if (empty($c_filt) || $c_filt == 'recent') echo 'selected'; ?>>Recent Leads (Default)</option>
                                        <option value="high_to_low" <?php if ($c_filt == 'high_to_low') echo 'selected'; ?>>High to Low</option>
                                        <option value="low_to_high" <?php if ($c_filt == 'low_to_high') echo 'selected'; ?>>Low to High</option>
                                    </select>
                                </th>
                                <th style="text-align: center;">SOP</th>
                                <th style="text-align: center;">Files</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="full-projects-container">
                            <!-- Rows will be loaded via AJAX -->
                            <tr>
                                <td colspan="9" style="padding: 100px 0; text-align: center;">
                                    <div class="spinner-premium" style="margin: 0 auto;"></div>
                                    <p style="margin-top: 20px; color: #64748b; font-weight: 700; font-size: 14px;">Synchronizing workspace...</p>
                                </td>
                            </tr>
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
    </div>
</div>

<!-- Add Project Modal -->
<!-- <div id="addProjectModal" class="modal fade" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog" style="margin-top: 100px;">
        <div class="modal-content premium-modal-content" style="border: none; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 20px 20px 0 0; padding: 25px;">
                <button type="button" class="close" data-dismiss="modal" style="opacity: 0.5;">&times;</button>
                <h4 class="modal-title" style="font-weight: 800; color: #0f172a; font-size: 20px;">
                    <i class="fa fa-folder-plus" style="color: #6366f1; margin-right: 8px;"></i> Initiate New Project
                </h4>
            </div>
            <form id="add-project-form-main" method="POST">
                <div class="modal-body" style="padding: 30px;">
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Select Client <span style="color: #ef4444;">*</span></label>
                        <select name="client_id" class="p-input-premium" required>
                            <option value="">Select a client...</option>
                            <?php while ($client = mysqli_fetch_assoc($run_clients)) { ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Project Designation <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="project_name" class="p-input-premium" placeholder="e.g. Q3 Marketing Campaign" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 25px;">
                                <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Start Date</label>
                                <input type="date" name="project_date" class="p-input-premium" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 25px;">
                                <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Budget</label>
                                <div style="display: flex; gap: 10px;">
                                    <select name="currency" class="p-input-premium" style="width: 100px; flex-shrink: 0;">
                                        <option value="INR">₹ INR</option>
                                        <option value="USD">$ USD</option>
                                        <option value="EUR">€ EUR</option>
                                        <option value="GBP">£ GBP</option>
                                        <option value="AED">د.إ AED</option>
                                    </select>
                                    <input type="number" name="budget" class="p-input-premium" placeholder="0" min="0" step="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Initial Status</label>
                        <select name="status" class="p-input-premium">
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block;">Initial Remark <span style="color: #94a3b8; font-weight: 500; font-size: 12px;">(Optional)</span></label>
                        <textarea name="remark" class="p-input-premium" rows="2" placeholder="Project goals or initial notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 20px 30px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 20px 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 10px; font-weight: 600; padding: 10px 20px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #4f46e5; border: none; border-radius: 10px; font-weight: 700; padding: 10px 25px; margin-left: 10px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.3);">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div> -->



<!-- View Documents Modal (Unified Repository) -->
<div id="viewDocumentsModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg" style="margin-top: 80px; max-width: 700px;">
        <div class="modal-content premium-modal-content" style="border: none; border-radius: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); overflow: hidden;">
            <div class="modal-header" style="background: #FFEAEB; color: #000; padding: 30px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: #dd2127; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff;">
                            <i class="fa fa-folder-open"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 20px; letter-spacing: -0.5px; margin: 0;">Project Resource Hub</h4>
                            <p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Centralized Project Assets</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 12px; margin-right: 40px;">
                        <button type="button" id="btn-add-artifact" class="btn-premium-add-inline" onclick="toggleAddResourceForm('document')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);">
                            <i class="fa fa-upload"></i>
                            <span class="btn-text">Add Document</span>
                        </button>
                        <button type="button" id="btn-add-link" class="btn-premium-add-inline" onclick="toggleAddResourceForm('link')" style="background: #dd2127; color: #fff; border: none; border-radius: 12px; padding: 10px 18px; font-weight: 700; font-size: 13px; display: none; align-items: center; gap: 8px; transition: 0.3s; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);">
                            <i class="fa fa-globe"></i> <span class="btn-text">Add Link</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-body" style="padding: 0; background: #fff;">
                <!-- Tab Navigation -->
                <div style="background: #f1f5f9; padding: 0 30px; display: flex; gap: 30px; border-bottom: 1px solid #e2e8f0;">
                    <div class="repo-tab active" onclick="switchRepoTab('documents')" id="tab-documents">
                        <i class="fa fa-files-o"></i> Documents
                    </div>
                    <div class="repo-tab" onclick="switchRepoTab('links')" id="tab-links">
                        <i class="fa fa-link"></i> External Links
                    </div>
                </div>

                <!-- Inline Resource Forms -->
                <div id="resource-forms-container" style="display: none; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 30px; animation: slideDown 0.3s ease-out;">
                    <!-- Document Form -->
                    <form id="add-document-form-unified" method="POST" enctype="multipart/form-data" class="resource-form">
                        <input type="hidden" name="project_id" id="doc_project_id_unified">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Artifact Name</label>
                                    <input type="text" name="document_name" class="p-input-premium" placeholder="e.g. Design Spec" required style="height: 45px;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Select File</label>
                                    <div class="file-upload-wrapper-premium-mini" style="position: relative; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 10px; text-align: center; background: #fff; transition: 0.3s;">
                                        <input type="file" name="project_doc" id="project_doc_input_unified" required style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; opacity: 0; cursor: pointer;">
                                        <span id="file-name-label-unified" style="color: #64748b; font-weight: 600; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;">Choose file...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Doc Type</label>
                                    <?php if (isSuperAdmin()): ?>
                                        <select name="is_proposal" class="p-input-premium" style="height: 45px; width: 100%; font-size: 12px; font-weight: 600;">
                                            <option value="0">General</option>
                                            <option value="1">🔒 Project Proposal</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="hidden" name="is_proposal" value="0">
                                        <input type="text" class="p-input-premium" value="General" readonly style="height: 45px; background: #f1f5f9; color: #64748b; font-size: 12px; font-weight: 600;">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn-premium-cancel" onclick="toggleAddResourceForm()">Discard</button>
                            <button type="submit" class="btn-premium-add">Add Document</button>
                        </div>
                    </form>

                    <!-- Link Form -->
                    <form id="add-link-form-unified" method="POST" class="resource-form" style="display: none;">
                        <input type="hidden" name="project_id" id="link_project_id_unified">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">Link Title</label>
                                    <input type="text" name="link_name" class="p-input-premium" placeholder="e.g. Figma Design" required style="height: 45px;">
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label style="font-weight: 700; color: #475569; margin-bottom: 8px; display: block; font-size: 11px; text-transform: uppercase;">URL (https://...)</label>
                                    <input type="url" name="link_url" class="p-input-premium" placeholder="https://www.figma.com/file/..." required style="height: 45px;">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="button" class="btn-premium-cancel" onclick="toggleAddResourceForm()">Discard</button>
                            <button type="submit" class="btn-premium-add">Add Link</button>
                        </div>
                    </form>
                </div>

                <div id="docs-list-container" style="max-height: 550px; overflow-y: auto; padding: 30px;">
                    <!-- Documents will be loaded here -->
                    <div class="spinner-premium" style="margin: 50px auto;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Project Budget Modal -->
<div id="projectBudgetModal" class="modal fade" role="dialog" style="z-index: 99999;">
    <div class="modal-dialog modal-lg" style="max-width: 1250px; width: 92%; overflow-y:auto; margin-top: 30px;">
        <div class="modal-content premium-modal-content" style="border: none; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 15px; position: relative; z-index: 1;">
                    <div style="width: 46px; height: 46px; background: #dd2127; border: 1px solid rgba(223, 33, 39, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-money" style="color: white; font-size: 22px;"></i>
                    </div>
                    <div>
                        <h4 class="modal-title" style="font-weight: 900; font-size: 20px; color: #000000; margin: 0; letter-spacing: -0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Project Budget Control</h4>
                        <p id="budget_project_name_title" style="margin: 4px 0 0 0; font-size: 11px; color: #000000; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;">Architecting Financials</p>
                    </div>
                    <input type="hidden" id="budget_currency">
                </div>
            </div>

            <div class="modal-body" style="padding: 0; background: #fff;">
                <!-- Summary Stats (Glassmorphism inspired) -->
                <div style="padding: 25px 30px; background: #f8fafc; border-bottom: 1px solid #eef2f6; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <!-- Total Cost -->
                    <div style="background: #fff; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
                        <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; position: relative; z-index: 1;">Total Estimated Cost</div>
                        <div id="summary_total_cost" style="font-size: 26px; font-weight: 900; color: #0f172a; letter-spacing: -1px; position: relative; z-index: 1;">₹ 0</div>
                        <div style="width: 30px; height: 4px; background: #6366f1; border-radius: 10px; margin-top: 10px;"></div>
                    </div>
                    <!-- Received -->
                    <div style="background: #fff; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
                        <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; position: relative; z-index: 1;">Collections Received</div>
                        <div id="summary_received_amount" style="font-size: 26px; font-weight: 900; color: #16a34a; letter-spacing: -1px; position: relative; z-index: 1;">₹ 0</div>
                        <div style="width: 30px; height: 4px; background: #22c55e; border-radius: 10px; margin-top: 10px;"></div>
                    </div>
                    <!-- Pending -->
                    <div style="background: #fff; padding: 20px; border-radius: 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); position: relative; overflow: hidden;">
                        <div style="font-size: 10px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 8px; position: relative; z-index: 1;">Outstanding Balance</div>
                        <div id="summary_pending_amount" style="font-size: 26px; font-weight: 900; color: #ef4444; letter-spacing: -1px; position: relative; z-index: 1;">₹ 0</div>
                        <div style="width: 30px; height: 4px; background: #ef4444; border-radius: 10px; margin-top: 10px;"></div>
                    </div>
                </div>

                <div style="padding: 30px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
                        <div>
                            <h5 style="font-weight: 900; font-size: 16px; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 10px;">
                                <span style="width: 32px; height: 32px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #dd2127;">
                                    <i class="fa fa-money" style="font-size: 14px;"></i>
                                </span>
                                Payment History
                            </h5>
                            <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b; font-weight: 500;">Track all payments,receipts and outstanding dues.</p>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn-premium-add" onclick="createInvoice()">
                                <i class="fa fa-calculator" style="font-size: 16px;"></i>Invoice
                            </button>
                            <button type="button" onclick="downloadStatement()" class="btn-premium-add">
                                <i class="fa fa-download" style="font-size: 16px;"></i>Statement
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive" style="border: 1px solid #eef2f6; border-radius: 16px; overflow-x: auto; background: #fff;">
                        <table class="table" style="margin: 0; border-collapse: separate; border-spacing: 0; width: 100%;">
                            <thead>
                                <tr>
                                    <th style="padding: 14px 15px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: left; white-space: nowrap;">Phase</th>
                                    <th style="padding: 14px 12px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; white-space: nowrap;">Description</th>
                                    <th style="padding: 14px 12px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; white-space: nowrap;">Total Cost (<span class="phase-currency-sym">₹</span>)</th>
                                    <th style="padding: 14px 12px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; white-space: nowrap;">Received (<span class="phase-currency-sym">₹</span>)</th>
                                    <th style="padding: 14px 12px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; white-space: nowrap;">Pending (<span class="phase-currency-sym">₹</span>)</th>
                                    <th style="padding: 14px 12px; border: none; font-size: 10px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; white-space: nowrap;">Payment Method</th>
                                </tr>
                            </thead>
                            <tbody id="budget_phases_body">
                                <!-- Dynamic rows go here -->
                            </tbody>
                        </table>
                    </div>

                    <div id="no_phases_msg" style="display: none; text-align: center; padding: 60px 20px; background: #fff; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
                        <i class="fa fa-info-circle" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px;"></i>
                        <h4 style="font-weight: 700; color: #475569;">No Budget Phases Defined</h4>
                        <p style="color: #94a3b8; font-size: 14px;">Click the "Add New Phase" button to start building the budget.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background: #fff; padding: 25px 35px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 15px;">
                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Discard Changes</button>
                <?php if (canAdminAccess('budget_insert') || canAdminAccess('budget_update') || canAdminAccess('budget_delete')): ?>
                    <button type="button" onclick="saveBudget()" class="btn-premium-add" id="btn_save_budget">
                        <i class="fa fa-save"></i>Save Data
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Phase Modal -->
<div class="modal fade" id="phaseEditModal" tabindex="-1" aria-hidden="true" style="z-index: 100005;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button class="btn-modal-close" data-dismiss="modal" aria-label="Close">
                    <i class="fa fa-times"></i>
                </button>
                <h5 class="modal-title" id="phaseModalTitle" style="font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; background: #dd2127; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa fa-list-ol"></i>
                    </div>
                    <span>Add Payment Phase</span>
                </h5>
            </div>
            <div class="modal-body" style="padding: 30px;">
                <form id="phase-edit-form">
                    <input type="hidden" id="phase_edit_index" value="">

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Phase Title <span style="color:#ef4444">*</span></label>
                        <select id="phase_edit_name" required style="width: 100%; height: 45px; border: 2px solid #e2e8f0; border-radius: 12px; padding: 0 15px; font-weight: 600; color: #0f172a; font-size: 14px; outline: none; transition: 0.3s; background: #fff;">
                            <option value="">Select Phase</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Operational Details</label>
                        <textarea id="phase_edit_desc" style="width: 100%; height: 80px; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 15px; font-weight: 500; color: #475569; font-size: 13px; outline: none; transition: 0.3s; resize: none;" placeholder="Details about this phase..."></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Received <span class="phase-currency-sym">₹</span></label>
                            <input type="number" id="phase_edit_received" step="0.01" min="0" style="width: 100%; height: 45px; border: 2px solid #e2e8f0; border-radius: 12px; padding: 0 15px; font-weight: 700; color: #16a34a; font-size: 14px; outline: none; transition: 0.3s;" placeholder="0.00">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:800;color:#475569;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                                Payment Method
                            </label>

                            <select id="phase_edit_method"
                                style="width:100%;height:45px;border:2px solid #e2e8f0;border-radius:12px;padding:0 15px;font-weight:600;color:#0f172a;font-size:14px;outline:none;background:#fff;">
                                <option value="">Select Payment Method</option>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Net Banking">Net Banking</option>
                                <option value="Google Pay">Google Pay</option>
                                <option value="PhonePe">PhonePe</option>
                                <option value="Paytm">Paytm</option>
                                <option value="Wish">Wish</option>
                                <option value="Remitly">Remitly</option>
                                <option value="Paypal">Paypal</option>
                                <option value="Wire Transfer">Wire Transfer</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Date</label>
                            <input type="date" id="phase_edit_date" style="width: 100%; height: 45px; border: 2px solid #e2e8f0; border-radius: 12px; padding: 0 15px; font-weight: 600; color: #0f172a; font-size: 14px; outline: none; transition: 0.3s;">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding: 20px 30px; background: #fff; border-radius: 0 0 20px 20px;">
                <button type="button" data-dismiss="modal" class="btn-premium-cancel">Cancel</button>
                <button type="button" onclick="savePhaseEdit()" class="btn-premium-add">Save Phase</button>
            </div>
        </div>
    </div>
</div>

<style>
    .id-badge-premium {
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 800;
        color: #94a3b8;
        font-size: 13px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 8px;
        display: inline-block;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .btn-premium-add-inline:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
    }

    /* Force SweetAlert to stay on top of modals */
    .swal2-container {
        z-index: 999999 !important;
    }

    .repo-tab {
        padding: 18px 0;
        font-weight: 800;
        font-size: 13px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        position: relative;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .repo-tab.active {
        color: #0f172a;
    }

    .repo-tab.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 3px;
        background: #0f172a;
        border-radius: 3px 3px 0 0;
    }

    .repo-tab i {
        font-size: 15px;
    }

    /* Budget Input Styles */
    .budget-table-input {
        width: 100%;
        border: 1px solid #eef2f6;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        background: #fbfcfe;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        outline: none;
    }

    .budget-table-input:focus {
        border-color: #6366f1;
        background: #fff;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.08);
        transform: translateY(-1px);
    }

    .budget-table-input::placeholder {
        color: #cbd5e1;
        font-weight: 500;
    }

    .delete-phase-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #fef2f2;
        color: #ef4444;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
        cursor: pointer;
    }

    .delete-phase-btn:hover {
        background: #ef4444;
        color: #fff;
        transform: scale(1.1);
    }

    .table thead,
    .table thead tr,
    .table thead th {
        background: #5b5b5b !important;
        color: white !important;
    }

    .employee-group {
        display: flex;
        align-items: center;
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
        width: 30px;
        height: 30px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .emp-avatar-item .emp-initial {
        background: #dee2e6;
        color: #333;
        font-size: 13px;
        font-weight: 700;
    }

    .employee-group .more {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e9ecef;
        border: 2px solid #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
        color: #475569;
    }

    .employee-group .more:hover {
        background: #dee2e6;
    }

    /* Fast/Instant Custom Tooltip (0.15s fast popup) */
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
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.15s ease, transform 0.15s ease;
        z-index: 99999;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .emp-avatar-item[data-tooltip]::before {
        content: '';
        position: absolute;
        bottom: calc(100% + 3px);
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        border-width: 5px 5px 0 5px;
        border-style: solid;
        border-color: #0f172a transparent transparent transparent;
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


    .card-hdr {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-hdr h3 {
        margin: 0;
    }

    .spinner-premium {
        width: 50px;
        height: 50px;
        border: 4px solid #f1f5f9;
        border-top: 4px solid #6366f1;
        border-radius: 50%;
        margin: 0 auto;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }


    .timeline-visual-wrapper {
        position: relative;
        padding-left: 20px;
    }

    .timeline-vertical-line {
        position: absolute;
        left: 4px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
        z-index: 1;
    }

    .timeline-remark-item {
        position: relative;
        z-index: 2;
    }

    .timeline-dot {
        position: absolute;
        left: -32px;
        top: 15px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #fff;
        border: 3px solid #cbd5e1;
        z-index: 3;
    }

    .remark-content-box {
        background: #fff;
        padding: 20px 25px;
        border-radius: 18px;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
        position: relative;
        transition: 0.3s;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        display: block;
    }

    .remark-content-box:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        border-color: #eef2f6;
    }

    /* Speech bubble tail */
    .remark-content-box::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 15px;
        width: 15px;
        height: 15px;
        background: #fff;
        border-left: 1px solid #f1f5f9;
        border-bottom: 1px solid #f1f5f9;
        transform: rotate(45deg);
    }

    .remark-time-premium {
        font-size: 10px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .remark-text-premium {
        font-size: 14px;
        color: #334155;
        line-height: 1.6;
        word-break: break-word;
    }

    .premium-confirm-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.7);
        backdrop-filter: blur(4px);
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
        border-radius: 24px;
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

    .premium-confirm-header h3 {
        margin: 0 0 10px 0;
        font-size: 20px;
        font-weight: 700;
        color: #0f172a;
    }

    .premium-confirm-header p {
        margin: 0 0 25px 0;
        font-size: 14px;
        color: #64748b;
        line-height: 1.5;
    }

    .premium-confirm-footer {
        display: flex;
        gap: 12px;
    }

    .confirm-btn-cancel {
        flex: 1;
        padding: 12px;
        border-radius: 12px;
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
        padding: 12px;
        border-radius: 12px;
        background: #ef4444;
        color: #fff;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }

    .confirm-btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
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
</style>

<!-- Project Delete Confirmation Modal -->
<div class="premium-confirm-overlay" id="projectDeleteConfirmOverlay">
    <div class="premium-confirm-modal">
        <div class="premium-confirm-header">
            <div class="confirm-icon-box">
                <i class="fa fa-trash-o"></i>
            </div>
            <h3>Delete Project?</h3>
            <p>You are about to permanently delete <strong id="delete_project_name_label">this project</strong>. This will also erase all associated remarks and activity history.</p>
        </div>
        <div class="premium-confirm-footer">
            <button class="confirm-btn-cancel" onclick="closeProjectDeleteConfirm()" type="button">Cancel</button>
            <button class="confirm-btn-delete" id="confirmProjectDeleteBtn" type="button">Delete Project</button>
        </div>
    </div>
</div>

<script>
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    function applyColumnFilter(column, value) {
        const status = getUrlParameter('status');
        const source = getUrlParameter('source');
        const cost = getUrlParameter('cost');
        const search = (column === 'search') ? value : getUrlParameter('search');
        let url = 'index.php?projects';
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (source) url += `&source=${encodeURIComponent(source)}`;
        if (cost) url += `&cost=${encodeURIComponent(cost)}`;
        url += '&page=1';
        window.location.href = url;
    }

    function applySourceFilter(value) {
        const status = getUrlParameter('status');
        const cost = getUrlParameter('cost');
        const search = getUrlParameter('search');
        let url = 'index.php?projects';
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (value) url += `&source=${encodeURIComponent(value)}`;
        if (cost) url += `&cost=${encodeURIComponent(cost)}`;
        url += '&page=1';
        window.location.href = url;
    }

    function applyCostFilter(value) {
        const status = getUrlParameter('status');
        const source = getUrlParameter('source');
        const search = getUrlParameter('search');
        let url = 'index.php?projects';
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;
        if (source) url += `&source=${encodeURIComponent(source)}`;
        if (value) url += `&cost=${encodeURIComponent(value)}`;
        url += '&page=1';
        window.location.href = url;
    }

    var currentPage = <?php echo isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1; ?>;

    $(document).ready(function() {

        const urlParams = new URLSearchParams(window.location.search);
        let currentStatusFilter = urlParams.get('status') || '';
        let currentSourceFilter = urlParams.get('source') || '';
        let currentCostFilter = urlParams.get('cost') || '';
        let currentSearchQuery = urlParams.get('search') || '';

        $('#project-status-filter').val(currentStatusFilter);

        $('#project-status-filter').change(function() {
            currentStatusFilter = $(this).val();
            loadProjects();
        });

        // Dynamic live search on typing
        let searchDebounce = null;
        $(document).on('input', '#header_search', function() {
            clearTimeout(searchDebounce);
            const val = $(this).val();
            searchDebounce = setTimeout(function() {
                currentSearchQuery = val;
                currentPage = 1;
                loadProjects();
            }, 300);
        });

        $(document).on('keypress', '#header_search', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                applyColumnFilter('search', $(this).val());
            }
        });

        loadProjects();

        // Filters change
        $('#status-filter, #source-filter').on('change', function() {
            var s = $('#status-filter').val();
            var src = $('#source-filter').val();
            var url = 'index.php?page=1';
            if (s) url += '&status=' + encodeURIComponent(s);
            if (src) url += '&source=' + encodeURIComponent(src);
            window.location.href = url;
        });


        function loadProjects() {
            $('#full-projects-container').css('opacity', '0.6');

            let fetchPage = currentPage;
            if (currentSearchQuery && currentSearchQuery.trim() !== '') {
                fetchPage = 1;
            }

            $.ajax({
                url: 'pages/projects/fetch_all_projects.php',
                method: 'GET',
                data: {
                    status: currentStatusFilter,
                    source: currentSourceFilter,
                    cost: currentCostFilter,
                    search: currentSearchQuery,
                    page: fetchPage
                },
                success: function(response) {
                    $('#full-projects-container').css('opacity', '1');
                    if (response.trim() === "") {
                        $('#full-projects-container').html(`
                    <tr>
                        <td colspan="8" style="padding: 100px 20px; text-align: center;">
                                <div style="width: 60px; height: 60px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fa fa-folder-open-o" style="font-size: 24px; color: #94a3b8;"></i>
                                </div>
                                <h4 style="color: #64748b; font-weight: 600; font-size: 16px;">No projects found</h4>
                                <p style="color: #64748b; font-size: 13px;">There are no projects to show right now.</p>
                            </td>
                        </tr>
                    `);
                    } else {
                        $('#full-projects-container').html(response);
                    }
                },
                error: function(xhr, status, error) {
                    $('#full-projects-container').css('opacity', '1').html(`
                    <tr>
                        <td colspan="7" style="padding: 50px; text-align: center;">
                            <div style="background: #fef2f2; padding: 20px; border-radius: 12px; border: 1.5px dashed #fecaca;">
                                <i class="fa fa-exclamation-circle" style="color: #ef4444; font-size: 32px; margin-bottom: 10px;"></i>
                                <h4 style="color: #991b1b; font-weight: 700;">Connection Error</h4>
                                <p style="color: #b91c1c; font-size: 13px;">Was unable to retrieve data. Status: ${status}</p>
                                <button onclick="location.reload()" class="btn btn-xs" style="margin-top: 10px; background: #ef4444; color: #fff; border-radius: 8px;">Retry</button>
                            </div>
                        </td>
                    </tr>
                `);
                }
            });
        }

        loadProjects();

        // Toggle remarks detail row (Triggered only by History Button)
        $(document).on('click', '.btn-toggle-history', function(e) {
            e.preventDefault();

            const row = $(this).closest('tr');
            const detailRow = row.next('.project-detail-row');
            const icon = $(this).find('.history-toggle-icon');

            detailRow.toggle();

            if (detailRow.is(':visible')) {
                row.css('background-color', '#f8fafc');
                icon.removeClass('fa-history').addClass('fa-times').css('color', '#ef4444');
                $(this).css('background', '#fee2e2').css('border-color', '#fecaca');
            } else {
                row.css('background-color', '');
                icon.removeClass('fa-times').addClass('fa-history').css('color', '#7c3aed');
                $(this).css('background', '#f5f3ff').css('border-color', '#ede9fe');
            }
        });

        $('#add-project-form-main').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();

            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

            $.ajax({
                url: 'ajax/clients/ajax_add_client_project.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        $('#addProjectModal').modal('hide');
                        $('#add-project-form-main')[0].reset();
                        loadProjects();
                        showPremiumAlert('Project initiated successfully!');
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    submitBtn.prop('disabled', false).html(originalText);
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'A network error occurred.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });



        $(document).on('click', '.add-remark-btn', function() {
            const btn = $(this);
            const projectId = btn.data('project-id');
            const container = btn.closest('.project-detail-row').find('.remarks-history-premium');
            const remarkInput = btn.siblings('.remark-textarea');
            const remarkText = remarkInput.val().trim();

            if (!remarkText) {
                remarkInput.focus();
                return;
            }

            btn.prop('disabled', true).html('<i class="fa fa-circle-o-notch fa-spin"></i>');

            $.ajax({
                url: 'ajax/clients/ajax_add_client_remark.php',
                method: 'POST',
                data: {
                    project_id: projectId,
                    remark: remarkText,
                    user_type: 'admin'
                },
                success: function(response) {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i> Post Update');
                    if (response.success) {
                        const posterName = response.posted_by || 'You';
                        const newRemark = $(`
                        <div class="timeline-remark-item" style="margin-bottom: 25px; position: relative; padding-left: 32px; display: none; width: 100%;">
                            <div class="timeline-dot" style="left: 0;"></div>
                            <div class="remark-content-box" style="padding-left: 20px;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="font-size: 11px; font-weight: 800; padding: 3px 9px; border-radius: 6px; background: #ffeaeb; color: #dd2127; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fa fa-user"></i> ${posterName}
                                    </span>
                                    <div class="remark-time-premium" style="margin: 0; font-size: 11px;">
                                        <i class="fa fa-clock-o"></i> JUST NOW
                                    </div>
                                </div>
                                <div class="remark-text-premium" style="font-size: 13px; color: #334155; font-weight: 600;">${remarkText.replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>`);

                        container.find('.no-remarks-placeholder').remove();
                        container.prepend(newRemark);
                        newRemark.slideDown(400);
                        remarkInput.val('');
                    } else {
                        Swal.fire({
                            title: 'Error Saving Remark',
                            text: response.message || 'Unknown error occurred.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-send"></i> Post Update');
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'Unable to connect to the server to save the remark.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });

        $(document).on('change', '.project-status-select', function() {
            const select = $(this);
            const projectId = select.data('project-id');
            const newStatus = select.val();

            select.css('opacity', '0.5');

            $.ajax({
                url: 'ajax/projects/ajax_update_project_status.php',
                method: 'POST',
                data: {
                    project_id: projectId,
                    status: newStatus
                },
                success: function(response) {
                    select.css('opacity', '1');
                    if (response.success) {
                        showPremiumAlert(`Status updated to ${newStatus}`);
                    } else {
                        Swal.fire({
                            title: 'Update Failed',
                            text: response.message || 'Error updating status.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card swal2-premium'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    select.css('opacity', '1');
                    Swal.fire({
                        title: 'Connection Error',
                        text: 'Unable to communicate with the server.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card swal2-premium'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        }); // Close document.on change

        let projectToDelete = null;
        window.deleteProject = function(id, name) {
            projectToDelete = id;
            $('#delete_project_name_label').text(name);
            $('#projectDeleteConfirmOverlay').addClass('active');
        }

        window.closeProjectDeleteConfirm = function() {
            $('#projectDeleteConfirmOverlay').removeClass('active');
            projectToDelete = null;
        }

        $('#confirmProjectDeleteBtn').on('click', function() {
            if (!projectToDelete) return;

            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Erasing...');

            $.ajax({
                url: 'ajax/projects/ajax_delete_project.php',
                method: 'POST',
                data: {
                    project_id: projectToDelete
                },
                success: function(response) {
                    btn.prop('disabled', false).html('Delete Project');
                    if (response.success) {
                        closeProjectDeleteConfirm();
                        loadProjects();
                        showPremiumAlert('Project and history purged successfully');
                    } else {
                        Swal.fire({
                            title: 'Deletion Error',
                            text: response.message || 'Could not delete project.',
                            icon: 'error',
                            customClass: {
                                popup: 'premium-card'
                            },
                            confirmButtonColor: '#ef4444'
                        });
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('Delete Project');
                    closeProjectDeleteConfirm();
                    Swal.fire({
                        title: 'Network Error',
                        text: 'Network error occurred during project excision.',
                        icon: 'error',
                        customClass: {
                            popup: 'premium-card'
                        },
                        confirmButtonColor: '#ef4444'
                    });
                }
            });
        });

        // Unified Hub Logic
        let currentProjectIdRepo = null;
        let currentRepoTab = 'documents';

        window.switchRepoTab = function(tab) {
            currentRepoTab = tab;
            $('.repo-tab').removeClass('active');
            $(`#tab-${tab}`).addClass('active');

            // Toggle buttons
            if (tab === 'documents') {
                $('#btn-add-artifact').show();
                $('#btn-add-link').hide();
            } else {
                $('#btn-add-artifact').hide();
                $('#btn-add-link').show();
            }

            // Hide any open forms
            $('#resource-forms-container').hide();
            $('.btn-premium-add-inline .btn-text').each(function() {
                $(this).text($(this).parent().attr('id') === 'btn-add-artifact' ? 'Add Document' : 'Add Link');
            });

            refreshRepoContent();
        }

        window.toggleAddResourceForm = function(type) {
            const container = $('#resource-forms-container');
            const formDoc = $('#add-document-form-unified');
            const formLink = $('#add-link-form-unified');

            if (container.is(':visible')) {
                container.slideUp(300);
                $(`#btn-add-${type === 'link' ? 'link' : 'artifact'} .btn-text`).text(type === 'link' ? 'Add Link' : 'Add Document');
            } else {
                $('.resource-form').hide();
                if (type === 'link' || currentRepoTab === 'links') {
                    formLink.show();
                } else {
                    formDoc.show();
                }
                container.slideDown(300);
            }
        }

        function refreshRepoContent() {
            $('#docs-list-container').html('<div class="spinner-premium" style="margin: 30px auto;"></div>');
            const url = currentRepoTab === 'documents' ? 'ajax/projects/ajax_view_project_documents.php' : 'ajax/projects/ajax_view_project_links.php';

            $.ajax({
                url: url,
                method: 'GET',
                data: {
                    project_id: currentProjectIdRepo,
                    user_type: 'admin'
                },
                success: function(response) {
                    $('#docs-list-container').html(response);
                }
            });
        }

        // Link Submission
        $('#add-link-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();

            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

            $.ajax({
                url: 'ajax/projects/ajax_add_project_link.php',
                method: 'POST',
                data: formData,
                success: function(response) {
                    submitBtn.prop('disabled', false).html(originalText);
                    if (response.success) {
                        toggleAddResourceForm('link');
                        $('#add-link-form-unified')[0].reset();
                        refreshRepoContent();
                        showPremiumAlert('Link saved to repository');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        });

        // Existing Document Logic Update
        $('#add-document-form-unified').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Uploading...');

            $.ajax({
                url: 'ajax/projects/ajax_add_project_document.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    submitBtn.prop('disabled', false).html('Archive Document');
                    if (response.success) {
                        toggleAddResourceForm('document');
                        $('#add-document-form-unified')[0].reset();
                        $('#file-name-label-unified').text('Choose file...');
                        refreshRepoContent();
                        showPremiumAlert('Document archived');
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        });

        window.viewDocs = function(id, initialTab = 'documents') {
            currentProjectIdRepo = id;
            $('#doc_project_id_unified').val(id);
            $('#link_project_id_unified').val(id);
            switchRepoTab(initialTab);
            $('#viewDocumentsModal').modal('show');
        }

        window.deleteDoc = function(docId, projectId) {
            Swal.fire({
                title: 'Confirm Removal',
                text: "This resource will be permanently deleted.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: 'Yes, delete it!',
                customClass: {
                    popup: 'premium-card'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const url = currentRepoTab === 'documents' ? 'ajax/projects/ajax_delete_project_document.php' : 'ajax/projects/ajax_delete_project_link.php';
                    const data = currentRepoTab === 'documents' ? {
                        doc_id: docId
                    } : {
                        link_id: docId
                    };

                    $.ajax({
                        url: url,
                        method: 'POST',
                        data: data,
                        success: function(response) {
                            if (response.success) {
                                refreshRepoContent();
                                showPremiumAlert('Resource removed');
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        }
                    });
                }
            });
        }

        $('#project_doc_input_unified').change(function() {
            const fileName = $(this).val().split('\\').pop();
            if (fileName) $('#file-name-label-unified').text(fileName).css('color', '#4f46e5');
        });
    });

    function showPremiumAlert(message) {
        let container = document.getElementById('toast-container-custom');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container-custom';
            container.style.position = 'fixed';
            container.style.bottom = '20px';
            container.style.right = '20px';
            container.style.zIndex = '999999';
            container.style.display = 'flex';
            container.style.flexDirection = 'column';
            container.style.gap = '10px';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.background = '#1e293b';
        toast.style.color = '#fff';
        toast.style.padding = '16px 24px';
        toast.style.borderRadius = '12px';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0,0,0,0.1)';
        toast.style.display = 'flex';
        toast.style.alignItems = 'center';
        toast.style.gap = '12px';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '600';
        toast.style.transform = 'translateY(100px) scale(0.9)';
        toast.style.opacity = '0';
        toast.style.transition = 'all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275)';

        toast.innerHTML = `
        <div style="width: 24px; height: 24px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
            <i class="fa fa-check" style="font-size: 12px;"></i>
        </div>
        ${message}
    `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateY(0) scale(1)';
            toast.style.opacity = '1';
        }, 10);

        setTimeout(() => {
            toast.style.transform = 'translateY(20px) scale(0.9)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    // Budget Management Logic
    let currentBudgetId = null;
    window.currentProjectBudget = 0;

    window.openBudgetModal = function(id, name, initialBudget = 0, currency = 'INR') {
        currentBudgetId = id;
        window.currentProjectBudget = parseFloat(initialBudget) || 0;
        $('#budget_project_name_title').text('Project: ' + name);
        $('#budget_currency').val(currency); // Set hidden input
        $('#budget_phases_body').empty();
        $('#no_phases_msg').hide();

        // Set initial display
        updateSummarySymbols();
        const sym = getCurrencySymbol();
        $('#summary_total_cost').text(sym + ' ' + initialBudget.toLocaleString());
        $('#summary_received_amount, #summary_pending_amount').text(sym + ' 0');

        $('#projectBudgetModal').modal('show');

        // Fetch existing phases
        $.ajax({
            url: 'ajax/projects/ajax_get_project_budget.php',
            method: 'GET',
            data: {
                project_id: id
            },
            success: function(response) {
                if (response.success) {
                    // Update currency from database
                    if (response.currency) {
                        $('#budget_currency').val(response.currency);
                        updateSummarySymbols();
                    }

                    if (response.data.length > 0) {
                        response.data.forEach(phase => {
                            addPhaseRow(phase);
                        });
                    } else {
                        $('#no_phases_msg').show();
                    }
                }
                calculateTotals();
            }
        });
    }

    function getCurrencySymbol() {
        const symbols = {
            'INR': '₹',
            'USD': '$',
            'EUR': '€',
            'GBP': '£',
            'AED': 'د.إ'
        };
        return symbols[$('#budget_currency').val()] || '₹';
    }

    function updateSummarySymbols() {
        const sym = getCurrencySymbol();
        $('.phase-currency-sym').text(sym);
    }

    $('#budget_currency').on('change', function() {
        updateSummarySymbols();
        calculateTotals();
    });

    $('#phaseEditModal').on('hidden.bs.modal', function() {
        if ($('#projectBudgetModal').hasClass('in') || $('#projectBudgetModal').is(':visible')) {
            $('body').addClass('modal-open');
        }
    });

    $('#phase_edit_name').on('change', function() {
        const option = $(this).find('option:selected');
        if (option.val()) {
            $('#phase_edit_index').val(option.attr('data-index'));
            $('#phase_edit_desc').val(option.attr('data-desc'));
            const rec = parseFloat(option.attr('data-received')) || 0;
            $('#phase_edit_received').val(rec > 0 ? rec : '');
            $('#phase_edit_method').val(option.attr('data-method'));
            $('#phase_edit_date').val(option.attr('data-date'));
        } else {
            $('#phase_edit_index').val('');
            $('#phase_edit_desc').val('');
            $('#phase_edit_received').val('');
            $('#phase_edit_method').val('');
            $('#phase_edit_date').val('');
        }
    });

    window.openAddPhaseModal = function() {
        $('#phase_edit_index').val('');
        $('#phase-edit-form')[0].reset();

        let options = '<option value="">Select Phase</option>';
        $('.budget-phase-row').each(function(i) {
            const name = $(this).attr('data-name');
            const cost = parseFloat($(this).attr('data-cost')) || 0;
            const desc = $(this).attr('data-desc') || '';
            const received = parseFloat($(this).attr('data-received')) || 0;
            const method = $(this).attr('data-method') || '';
            const date = $(this).attr('data-date') || '';

            options += `<option value="${escapeHtml(name)}" data-index="${i}" data-cost="${cost}" data-desc="${escapeHtml(desc)}" data-received="${received}" data-method="${escapeHtml(method)}" data-date="${escapeHtml(date)}">${escapeHtml(name)} (Cost: ${getCurrencySymbol()} ${cost.toLocaleString()})</option>`;
        });
        $('#phase_edit_name').html(options).prop('disabled', false);

        $('#phaseEditModal').modal('show');
        setTimeout(() => {
            $('.modal-backdrop').last().css('z-index', '100004');
        }, 50);
    }

    window.editPhase = function(btn) {
        const row = $(btn).closest('.budget-phase-row');
        const index = $('.budget-phase-row').index(row);
        const name = row.attr('data-name');
        const desc = row.attr('data-desc');
        const cost = row.attr('data-cost');
        const received = row.attr('data-received');
        const method = row.attr('data-method');
        const date = row.attr('data-date');

        $('#phase_edit_index').val(index);

        let options = `<option value="${escapeHtml(name)}" data-index="${index}" data-cost="${cost}" selected>${escapeHtml(name)}</option>`;
        $('#phase_edit_name').html(options).prop('disabled', true);

        $('#phase_edit_desc').val(desc);
        $('#phase_edit_received').val(received > 0 ? received : '');
        $('#phase_edit_method').val(method);
        $('#phase_edit_date').val(date);

        $('#phaseEditModal').modal('show');
        setTimeout(() => {
            $('.modal-backdrop').last().css('z-index', '100004');
        }, 50);
    }

    window.savePhaseEdit = function() {
        const name = $('#phase_edit_name').val();
        if (!name) {
            Swal.fire('Validation Error', 'Phase Title is required.', 'warning');
            return;
        }

        const desc = $('#phase_edit_desc').val().trim();
        const selectedOpt = $('#phase_edit_name').find('option:selected');
        const cost = parseFloat(selectedOpt.attr('data-cost')) || 0;
        const receivedInput = parseFloat($('#phase_edit_received').val()) || 0;
        const method = $('#phase_edit_method').val().trim();
        const date = $('#phase_edit_date').val();

        const index = $('#phase_edit_index').val();

        if (index !== '') {
            // Updating or adding payment to existing phase
            const targetRow = $('.budget-phase-row').eq(parseInt(index));
            if (targetRow.length > 0) {
                let payments = targetRow.data('payments') || [];

                // If opened from + Add Payment modal (select enabled) and received amount entered:
                if (!$('#phase_edit_name').prop('disabled')) {
                    if (receivedInput > 0) {
                        payments.push({
                            amount: receivedInput,
                            date: date || new Date().toISOString().split('T')[0],
                            method: method || 'Net Banking',
                            note: desc || 'Payment Installment'
                        });
                    }
                } else {
                    // Editing phase directly from pencil icon
                    targetRow.attr('data-desc', desc);
                    if (cost > 0) targetRow.attr('data-cost', cost);
                }

                targetRow.data('payments', payments);
                const detailRowId = targetRow.find('.btn-toggle-phase-detail').attr('data-target');
                const detailRow = $(detailRowId);
                renderPhasePaymentsList(targetRow, detailRow);
            }
        } else {
            // New phase
            const data = {
                phase_name: name,
                description: desc,
                cost: cost,
                received_amount: receivedInput,
                remark: method,
                received_date: date,
                payments: receivedInput > 0 ? [{
                    amount: receivedInput,
                    date: date || new Date().toISOString().split('T')[0],
                    method: method || 'Net Banking',
                    note: desc || 'Initial Payment'
                }] : []
            };
            addPhaseRow(data);
        }

        $('#phaseEditModal').modal('hide');
        calculateTotals();
    }

    window.addPhaseRow = function(data = null) {
        $('#no_phases_msg').hide();
        const sym = getCurrencySymbol();

        let cost = 0,
            received = 0,
            payments = [];

        if (data) {
            cost = parseFloat(data.cost) || 0;
            received = parseFloat(data.received_amount) || 0;
            if (data.payments && Array.isArray(data.payments)) {
                payments = data.payments;
            } else if (received > 0) {
                payments = [{
                    amount: received,
                    date: data.received_date || '',
                    method: data.remark || 'Payment Received',
                    note: 'Initial Payment'
                }];
            }
        }

        let pending = cost - received;

        const rowId = 'phase_row_' + Math.random().toString(36).substring(2, 9);
        const detailRowId = rowId + '_detail';

        const row = $(`
        <tr class="budget-phase-row" id="${rowId}" style="transition: 0.3s;" 
            data-name="${data ? escapeHtml(data.phase_name) : ''}"
            data-desc="${data ? escapeHtml(data.description || '') : ''}"
            data-cost="${cost}"
            data-received="${received}"
            data-method="${data ? escapeHtml(data.remark || '') : ''}"
            data-date="${data ? escapeHtml(data.received_date || '') : ''}">
            <td style="padding: 14px 15px; border-bottom: 1px solid #f8fafc; text-align: left; vertical-align: middle; white-space: nowrap;">
                <div style="display: flex; align-items: center; justify-content: flex-start; gap: 10px;">
                    <button type="button" class="btn-toggle-phase-detail" data-target="#${detailRowId}" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; width: 26px; height: 26px; font-size: 10px; color: #475569; cursor: pointer; transition: 0.2s; flex-shrink: 0;" title="View payment breakdown & add installments">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">${data ? escapeHtml(data.phase_name) : ''}</div>
                </div>
            </td>
            <td style="padding: 14px 12px; border-bottom: 1px solid #f8fafc; text-align: center; vertical-align: middle; font-weight: 500; color: #64748b; font-size: 12px; white-space: nowrap;">
                <span class="display-desc">${data && data.description ? escapeHtml(data.description) : '—'}</span>
            </td>
            <td style="padding: 14px 12px; border-bottom: 1px solid #f8fafc; text-align: center; vertical-align: middle; font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                <span class="phase-currency-sym">${sym}</span> <span class="display-cost">${cost.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2})}</span>
            </td>
            <td style="padding: 14px 12px; border-bottom: 1px solid #f8fafc; text-align: center; vertical-align: middle; font-weight: 800; color: #16a34a; font-size: 13px; white-space: nowrap;">
                <span class="phase-currency-sym">${sym}</span> <span class="display-received">${received.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2})}</span>
            </td>
            <td style="padding: 14px 12px; border-bottom: 1px solid #f8fafc; text-align: center; vertical-align: middle; font-weight: 800; color: ${pending > 0 ? '#ef4444' : '#16a34a'}; font-size: 13px; white-space: nowrap;">
                <span class="phase-currency-sym">${sym}</span> <span class="display-pending">${pending.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2})}</span>
            </td>
            <td style="padding: 14px 12px; border-bottom: 1px solid #f8fafc; text-align: center; vertical-align: middle; font-weight: 600; color: #64748b; font-size: 12px; white-space: nowrap;">
                <span class="display-method">${data && data.remark ? escapeHtml(data.remark) : '—'}</span>
            </td>
        </tr>
        <tr class="phase-detail-box-row" id="${detailRowId}" style="display: none; background: #fafafa;">
            <td colspan="6" style="padding: 15px 25px; border-bottom: 2px solid #e2e8f0;">
                <div style="background: #fff; border: 1.5px solid #cbd5e1; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 12px;">
                        <div style="font-weight: 800; color: #1e293b; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-history" style="color: #dd2127;"></i>
                            Payment History & Dues breakdown for <span class="phase-title-tag" style="color: #dd2127;">${data ? escapeHtml(data.phase_name) : ''}</span>
                        </div>
                        <div style="display: flex; gap: 15px; font-size: 12px; font-weight: 700;">
                            <span style="color: #64748b;">Phase Cost: <strong style="color: #0f172a;" class="box-phase-cost">${sym} ${cost.toLocaleString()}</strong></span>
                            <span style="color: #16a34a;">Total Received: <strong class="box-phase-received">${sym} ${received.toLocaleString()}</strong></span>
                            <span style="color: #ef4444;">Remaining Pending: <strong class="box-phase-pending">${sym} ${pending.toLocaleString()}</strong></span>
                        </div>
                    </div>

                    <!-- Installments List Table -->
                    <div style="margin-bottom: 15px; overflow-x: auto;">
                        <table class="table table-bordered table-condensed phase-payments-table" style="margin: 0; font-size: 12px; background: #fff; table-layout: fixed; width: 100%;">
                            <thead>
                                <tr style="background: #525252; color: #ffffff; font-size: 11px; text-transform: uppercase;">
                                    <th style="width: 5%; text-align: center;">#</th>
                                    <th style="width: 15%; text-align: center;">Date</th>
                                    <th style="width: 18%; text-align: center;">Amount Paid (<span class="phase-currency-sym">${sym}</span>)</th>
                                    <th style="width: 18%; text-align: center;">Payment Method</th>
                                    <th style="width: 32%; text-align: left; padding-left: 12px;">Remarks / Reference</th>
                                    <th style="width: 12%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody class="phase-payments-list">
                                <!-- Dynamic installment rows -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Add Installment Payment Form -->
                    <?php if (canAdminAccess('budget_insert') || canAdminAccess('budget_update')): ?>
                    <div style="background: #f8fafc; padding: 12px 15px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                        <div style="font-weight: 800; font-size: 12px; color: #334155; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-plus-circle" style="color: #10b981;"></i> Add Payment:
                        </div>
                        <input type="number" step="0.01" min="0.01" class="p-input-premium pmt-input-amount" placeholder="Amount (e.g. 5000)" style="width: 140px; height: 34px; font-size: 12px; padding: 4px 10px; border-radius: 6px;">
                        <input type="date" class="p-input-premium pmt-input-date" style="width: 140px; height: 34px; font-size: 12px; padding: 4px 10px; border-radius: 6px;" value="${new Date().toISOString().split('T')[0]}">
                        <select class="p-input-premium pmt-input-method" style="width: 150px; height: 34px; font-size: 12px; padding: 4px 10px; border-radius: 6px;">
                            <option value="UPI">UPI</option>
                            <option value="NEFT/RTGS">NEFT/RTGS</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Remitly">Remitly</option>
                            <option value="Wise">Wise</option>
                            <option value="Paypal">Paypal</option>
                        </select>
                        <input type="text" class="p-input-premium pmt-input-note" placeholder="Note / Ref ID" style="width: 220px; height: 34px; font-size: 12px; padding: 4px 10px; border-radius: 6px;">
                        <button type="button" class="btn-add-phase-pmt" style="background: #10b981; color: #fff; border: none; height: 34px; padding: 0 16px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; transition: 0.2s;">
                            <i class="fa fa-check"></i> Add Payment
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
`);

        row.data('payments', payments);
        $('#budget_phases_body').append(row);

        const mainRow = $('#budget_phases_body tr.budget-phase-row').last();
        const detailRow = $('#budget_phases_body tr.phase-detail-box-row').last();
        renderPhasePaymentsList(mainRow, detailRow);
    }

    window.updatePhaseRow = function(index, data) {
        const row = $('#budget_phases_body tr.budget-phase-row').eq(index);

        let cost = parseFloat(data.cost) || 0;
        let received = parseFloat(data.received_amount) || 0;
        let pending = cost - received;

        row.attr('data-name', data.phase_name);
        row.attr('data-desc', data.description);
        row.attr('data-cost', cost);
        row.attr('data-received', received);
        row.attr('data-method', data.remark);
        row.attr('data-date', data.received_date);

        row.find('.display-cost').text(cost.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        row.find('.display-received').text(received.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        row.find('.display-pending').text(pending.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        row.find('.display-pending').parent().css('color', pending > 0 ? '#ef4444' : '#16a34a');

        row.find('td').eq(0).find('div').eq(1).text(data.phase_name);
        row.find('.display-method').text(data.remark ? data.remark : '—');
        row.find('.display-desc').text(data.description ? data.description : '—');

        const detailRowId = row.find('.btn-toggle-phase-detail').attr('data-target');
        const detailRow = $(detailRowId);
        detailRow.find('.phase-title-tag').text(data.phase_name);
        if (detailRow.is(':visible')) {
            renderPhasePaymentsList(row, detailRow);
        }
    }

    function renderPhasePaymentsList(mainRow, detailRow) {
        const sym = getCurrencySymbol();
        const payments = mainRow.data('payments') || [];
        const cost = parseFloat(mainRow.attr('data-cost')) || 0;

        let totalRec = 0;
        payments.forEach(p => {
            totalRec += parseFloat(p.amount) || 0;
        });

        let pending = cost - totalRec;

        mainRow.attr('data-received', totalRec);
        mainRow.find('.display-received').text(totalRec.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        mainRow.find('.display-pending').text(pending.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        mainRow.find('.display-pending').parent().css('color', pending > 0 ? '#ef4444' : '#16a34a');

        if (payments.length > 1) {
            mainRow.find('.display-method').text('Multiple');
            mainRow.attr('data-method', 'Multiple');
            mainRow.attr('data-date', payments[payments.length - 1].date || '');
        } else if (payments.length === 1) {
            mainRow.find('.display-method').text(payments[0].method || '—');
            mainRow.attr('data-method', payments[0].method || '');
            mainRow.attr('data-date', payments[0].date || '');
        } else {
            mainRow.find('.display-method').text('—');
            mainRow.attr('data-method', '');
            mainRow.attr('data-date', '');
        }

        detailRow.find('.box-phase-cost').text(sym + ' ' + cost.toLocaleString());
        detailRow.find('.box-phase-received').text(sym + ' ' + totalRec.toLocaleString());
        detailRow.find('.box-phase-pending').text(sym + ' ' + pending.toLocaleString());

        const listBody = detailRow.find('.phase-payments-list');
        listBody.empty();

        if (payments.length === 0) {
            listBody.html(`<tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 15px;">No installment payments recorded yet for this phase.</td></tr>`);
        } else {
            payments.forEach((p, idx) => {
                const pAmt = parseFloat(p.amount) || 0;
                const canDeletePmt = <?php echo (canAdminAccess('budget_delete') || canAdminAccess('budget_update')) ? 'true' : 'false'; ?>;
                const canEditPmt = <?php echo (canAdminAccess('budget_update') || canAdminAccess('budget_insert')) ? 'true' : 'false'; ?>;

                const editBtnHtml = canEditPmt ? `
                    <button type="button" class="btn-edit-pmt-item" data-index="${idx}" style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0284c7; width: 24px; height: 24px; border-radius: 4px; font-size: 10px; cursor: pointer; margin-right: 4px;" title="Edit installment">
                        <i class="fa fa-pencil"></i>
                    </button>
                ` : '';

                const deleteBtnHtml = canDeletePmt ? `
                    <button type="button" class="btn-delete-pmt-item" data-index="${idx}" style="background: #fee2e2; border: 1px solid #fecaca; color: #ef4444; width: 24px; height: 24px; border-radius: 4px; font-size: 10px; cursor: pointer;" title="Remove installment">
                        <i class="fa fa-times"></i>
                    </button>
                ` : '';

                const tr = $(`
                    <tr>
                        <td style="text-align: center; font-weight: 700; color: #64748b;">${idx + 1}</td>
                        <td style="text-align: center; font-weight: 600;">${p.date || '—'}</td>
                        <td style="text-align: center; font-weight: 800; color: #16a34a;">${sym} ${pAmt.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2})}</td>
                        <td style="text-align: center;">${escapeHtml(p.method || '—')}</td>
                        <td style="text-align: left; padding-left: 12px; font-weight: 500; color: #475569;">${escapeHtml(p.note || '—')}</td>
                        <td style="text-align: center;">
                            ${editBtnHtml}${deleteBtnHtml || (!canEditPmt ? '—' : '')}
                        </td>
                    </tr>
                `);
                listBody.append(tr);
            });
        }

        calculateTotals();
    }

    $(document).on('click', '.btn-edit-pmt-item', function(e) {
        e.preventDefault();
        const idx = parseInt($(this).attr('data-index'));
        const box = $(this).closest('.phase-detail-box-row');
        const mainRow = box.prev('.budget-phase-row');
        const payments = mainRow.data('payments') || [];

        if (idx < 0 || idx >= payments.length) return;
        const pmt = payments[idx];

        box.find('.pmt-input-amount').val(pmt.amount);
        box.find('.pmt-input-date').val(pmt.date || new Date().toISOString().split('T')[0]);
        if (pmt.method) {
            box.find('.pmt-input-method').val(pmt.method);
        }
        box.find('.pmt-input-note').val(pmt.note || '');

        const btnSave = box.find('.btn-add-phase-pmt');
        btnSave.attr('data-edit-index', idx);
        btnSave.html('<i class="fa fa-save"></i> Update Payment').css('background', '#0284c7');
        box.find('.pmt-input-amount').focus();
    });

    $(document).on('click', '.btn-toggle-phase-detail', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const btn = $(this);
        const targetId = btn.attr('data-target');
        const detailRow = $(targetId);
        const mainRow = btn.closest('.budget-phase-row');
        const isCurrentlyVisible = detailRow.is(':visible');

        // Close all other open phase detail rows
        $('.phase-detail-box-row').not(detailRow).hide();
        $('.btn-toggle-phase-detail').not(btn).html('<i class="fa fa-chevron-down"></i>').css('background', '#f1f5f9').css('color', '#475569');

        if (isCurrentlyVisible) {
            detailRow.hide();
            btn.html('<i class="fa fa-chevron-down"></i>').css('background', '#f1f5f9').css('color', '#475569');
        } else {
            detailRow.show();
            btn.html('<i class="fa fa-chevron-up"></i>').css('background', '#e0e7ff').css('color', '#4f46e5');
            renderPhasePaymentsList(mainRow, detailRow);
        }
    });

    $(document).on('click', '.btn-add-phase-pmt', function(e) {
        e.preventDefault();
        const btn = $(this);
        const box = btn.closest('.phase-detail-box-row');
        const mainRow = box.prev('.budget-phase-row');

        const amtInput = box.find('.pmt-input-amount');
        const dateInput = box.find('.pmt-input-date');
        const methodInput = box.find('.pmt-input-method');
        const noteInput = box.find('.pmt-input-note');

        const amt = parseFloat(amtInput.val()) || 0;
        if (amt <= 0) {
            Swal.fire('Validation Error', 'Please enter a valid payment amount.', 'warning');
            amtInput.focus();
            return;
        }

        const payments = mainRow.data('payments') || [];
        const editIdxAttr = btn.attr('data-edit-index');

        if (editIdxAttr !== undefined && editIdxAttr !== false && editIdxAttr !== '') {
            const editIdx = parseInt(editIdxAttr);
            if (editIdx >= 0 && editIdx < payments.length) {
                payments[editIdx] = {
                    amount: amt,
                    date: dateInput.val() || new Date().toISOString().split('T')[0],
                    method: methodInput.val() || 'UPI',
                    note: noteInput.val().trim()
                };
                showPremiumAlert('Installment payment updated!');
            }
            btn.removeAttr('data-edit-index');
            btn.html('<i class="fa fa-check"></i> Add Payment').css('background', '#10b981');
        } else {
            payments.push({
                amount: amt,
                date: dateInput.val() || new Date().toISOString().split('T')[0],
                method: methodInput.val() || 'UPI',
                note: noteInput.val().trim()
            });
            showPremiumAlert('Installment payment added!');
        }

        mainRow.data('payments', payments);
        amtInput.val('');
        noteInput.val('');

        renderPhasePaymentsList(mainRow, box);
    });

    $(document).on('click', '.btn-delete-pmt-item', function(e) {
        e.preventDefault();
        const idx = parseInt($(this).attr('data-index'));
        const box = $(this).closest('.phase-detail-box-row');
        const mainRow = box.prev('.budget-phase-row');

        let payments = mainRow.data('payments') || [];
        if (idx >= 0 && idx < payments.length) {
            payments.splice(idx, 1);
            mainRow.data('payments', payments);
            renderPhasePaymentsList(mainRow, box);
            showPremiumAlert('Installment payment removed');
        }
    });

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) {
            return map[m];
        });
    }

    $(document).on('click', '.delete-phase-btn', function() {
        const mainRow = $(this).closest('.budget-phase-row');
        const btnToggle = mainRow.find('.btn-toggle-phase-detail');
        const targetId = btnToggle.attr('data-target');
        const detailRow = $(targetId);

        mainRow.fadeOut(200, function() {
            mainRow.remove();
            detailRow.remove();
            if ($('#budget_phases_body tr.budget-phase-row').length === 0) {
                $('#no_phases_msg').show();
            }
            calculateTotals();
        });
    });

    function calculateTotals() {
        const totalCost = window.currentProjectBudget || 0;
        let totalReceived = 0;
        const sym = getCurrencySymbol();

        $('.budget-phase-row').each(function() {
            const received = parseFloat($(this).attr('data-received')) || 0;
            totalReceived += received;
        });

        const pending = totalCost - totalReceived;

        $('#summary_total_cost').text(sym + ' ' + totalCost.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        $('#summary_received_amount').text(sym + ' ' + totalReceived.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));
        $('#summary_pending_amount').text(sym + ' ' + pending.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }));

        if (pending <= 0) {
            $('#summary_pending_amount').css('color', '#16a34a'); // Green for no pending
        } else {
            $('#summary_pending_amount').css('color', '#ef4444'); // Red for outstanding
        }
    }

    window.saveBudget = function() {
        const phases = [];

        $('.budget-phase-row').each(function() {
            const row = $(this);
            const payments = row.data('payments') || [];

            phases.push({
                phase_name: row.attr('data-name'),
                description: row.attr('data-desc'),
                cost: parseFloat(row.attr('data-cost')) || 0,
                received_amount: parseFloat(row.attr('data-received')) || 0,
                remark: row.attr('data-method'),
                received_date: row.attr('data-date'),
                payments: payments
            });
        });

        const btn = $('#btn_save_budget');
        const currency = $('#budget_currency').val();
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'ajax/projects/ajax_save_project_budget.php',
            method: 'POST',
            data: {
                project_id: currentBudgetId,
                phases: JSON.stringify(phases),
                currency: currency
            },
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Data');
                if (response.success) {
                    $('#projectBudgetModal').modal('hide');
                    showPremiumAlert('Budget data saved successfully!');
                    loadProjects();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Data');
                Swal.fire('Connection Error', 'Network synchronization failed.', 'error');
            }
        });
    };

    window.downloadStatement = function() {
        if (!currentBudgetId) return;
        var url = '<?php
                    // Compute web-accessible URL to generate_statement.php
                    $doc_root  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
                    $file_dir  = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
                    $web_path  = str_replace($doc_root, '', $file_dir);
                    echo $web_path . '/generate_statement.php';
                    ?>';
        window.open(url + '?project_id=' + currentBudgetId, '_blank');
    };

    window.downloadExpenseStatement = function() {
        if (!currentExpenseProjectId) return;
        var url = '<?php
                    $doc_root  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
                    $file_dir  = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
                    $web_path  = str_replace($doc_root, '', $file_dir);
                    echo $web_path . '/generate_expense_statement.php';
                    ?>';
        window.open(url + '?project_id=' + currentExpenseProjectId, '_blank');
    };

    window.createInvoice = function() {
        if (!currentBudgetId) return;
        var url = '<?php
                    $doc_root  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
                    $file_dir  = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
                    $web_path  = str_replace($doc_root, '', $file_dir);
                    echo $web_path . '/generate_invoice.php';
                    ?>';
        window.open(url + '?project_id=' + currentBudgetId, '_blank');
    };

    function generateStatementPDF(btn, originalContent) {
        var element = document.querySelector('#projectBudgetModal .modal-body').cloneNode(true);
        var projectName = $('#budget_project_name_title').text().replace('Project: ', '');

        // Remove Action column from the header and body
        $(element).find('table th:last-child').remove();
        $(element).find('table td:last-child').remove();

        // Remove Phase column from the header and body (per user request to "cut phase")
        $(element).find('table th:first-child').remove();
        $(element).find('table td:first-child').remove();

        // ONLY IN STATEMENT: Add "Received Date" column at the end
        $(element).find('table thead tr').append('<th>Received Date</th>');
        $(element).find('table tbody tr').each(function() {
            var dateVal = $(this).attr('data-date') || '—';
            // Format the date to look nice if it exists
            if (dateVal && dateVal !== '—') {
                var d = new Date(dateVal);
                if (!isNaN(d.getTime())) {
                    dateVal = d.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                }
            }
            $(this).append('<td><span style="font-weight:600; color:#0f172a;">' + dateVal + '</span></td>');
        });

        // Remove the Add Payment / Download Statement buttons block
        $(element).find('div[style*="gap: 10px;"]').remove();

        // Replace input fields and textareas with span values so they print nicely
        $(element).find('input, textarea').each(function() {
            $(this).replaceWith('<span style="font-weight:600; color:#0f172a;">' + $(this).val() + '</span>');
        });

        // Get current date and time
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        var timeStr = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });

        // Add a nice header for the PDF with date and time
        var headerHtml = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #eef2f6; padding-bottom: 20px; font-family: Arial, sans-serif;">' +
            '<div style="text-align: left;">' +
            '<h2 style="margin:0 0 8px 0; color:#0f172a; font-size: 28px; font-weight: 900; letter-spacing: -0.5px;">' + projectName + '</h2>' +
            '<p style="margin:0; color:#64748b; font-size: 13px; text-transform: uppercase; font-weight: 700; letter-spacing: 1.5px;">Project Statement</p>' +
            '</div>' +
            '<div style="text-align: right; background: #f8fafc; padding: 12px 20px; border-radius: 12px; border: 1px solid #f1f5f9;">' +
            '<p style="margin: 0 0 5px 0; color: #94a3b8; font-size: 10px; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">Generated On</p>' +
            '<p style="margin: 0 0 2px 0; color: #0f172a; font-size: 15px; font-weight: 800;">' + dateStr + '</p>' +
            '<p style="margin: 0; color: #64748b; font-size: 12px; font-weight: 600;">' + timeStr + '</p>' +
            '</div>' +
            '</div>';
        $(element).prepend(headerHtml);

        // Ensure background is transparent for PDF wrapper and clean up table layout
        $(element).css({
            'padding': '0',
            'background': 'transparent',
            'font-family': 'Arial, sans-serif',
            'max-height': 'none',
            'height': 'auto',
            'overflow': 'visible'
        });

        // Ensure table wrappers don't clip content vertically or horizontally
        $(element).find('.table-responsive').css({
            'max-height': 'none',
            'height': 'auto',
            'overflow': 'visible',
            'border': 'none',
            'background': 'transparent'
        });

        // Set perfect table formatting for the PDF export
        $(element).find('table').css({
            'width': '100%',
            'border-collapse': 'collapse',
            'margin-top': '25px',
            'table-layout': 'auto'
        });
        $(element).find('table th').css({
            'background-color': '#475569',
            'border': '1px solid #334155',
            'padding': '12px 8px',
            'color': '#ffffff',
            'font-weight': 'bold',
            'text-transform': 'uppercase',
            'font-size': '10px',
            'text-align': 'center',
            'white-space': 'normal',
            'width': 'auto'
        });
        $(element).find('table td').css({
            'border': '1px solid #e2e8f0',
            'padding': '12px 8px',
            'font-size': '11px',
            'color': '#334155',
            'text-align': 'center',
            'white-space': 'normal',
            'width': 'auto',
            'word-break': 'break-word'
        });

        var letterheadHtml = `
            <style>
                * { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

                /* ── A4 CONTAINER ── */
                .pdf-container {
                    width: 794px;
                    height: 1123px;
                    position: relative;
                    background: #fff;
                    overflow: hidden;
                    font-family: 'Montserrat', 'Arial', sans-serif;
                }

                /* ── HEADER: exact match to experience letter ── */
                .top-shape {
                    height: 80px;
                    position: relative;
                    background: transparent;
                }

                .top-shape .black-bar {
                    width: 65%;
                    height: 45px;
                    background: #222;
                    clip-path: polygon(0 0, 100% 0, 92% 100%, 0 100%);
                }

                .top-shape .red-stripe {
                    width: 50%;
                    height: 12px;
                    background: #e31e24;
                    margin-top: 12px;
                    clip-path: polygon(0 0, 100% 0, 96% 100%, 0 100%);
                }

                .brand-row {
                    display: flex;
                    justify-content: flex-end;
                    align-items: center;
                    padding: 0 52px;
                    margin-top: -65px;
                }

                .brand-row img {
                    height: 85px;
                    object-fit: contain;
                }

                /* ── CONTENT ── */
                #pdf-content-wrapper {
                    padding: 6px 52px 120px 52px;
                }

                /* ── FOOTER: exact match to experience letter ── */
                .footer-bar {
                    position: absolute;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: #d1d1d1;
                    padding: 15px 52px;
                }

                .footer-inner {
                    display: flex;
                    justify-content: flex-start;
                    gap: 40px;
                    flex-wrap: nowrap;
                    font-size: 13px;
                    color: #222;
                    font-weight: 600;
                    padding-right: 160px;
                }

                .footer-col {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }

                .footer-item {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .corner-red {
                    position: absolute;
                    right: 0;
                    bottom: 0;
                    width: 180px;
                    height: 80px;
                    background: #e31e24;
                    clip-path: polygon(30% 0, 100% 0, 100% 100%, 0 100%);
                    z-index: 10;
                }
            </style>

            <div class="pdf-container">

                <!-- ── HEADER ── -->
                <div class="top-shape">
                    <div class="black-bar"></div>
                    <div class="red-stripe"></div>
                </div>
                <div class="brand-row">
                    <img src="images/Cadlete_logo Landscape.png" alt="CADLETE DESIGNS Logo">
                </div>

                <!-- ── CONTENT ── -->
                <div id="pdf-content-wrapper">
                    <!-- Report injected here -->
                </div>

                <!-- ── FOOTER ── -->
                <div class="footer-bar">
                    <div class="footer-inner">
                        <div class="footer-col">
                            <div class="footer-item">📞 091 83202 11773</div>
                            <div class="footer-item">✉ info@cadletedesigns.com</div>
                        </div>
                        <div class="footer-col">
                            <div class="footer-item">📍 A-106, Sun South Street, Ahmedabad</div>
                            <div class="footer-item">🌐 www.cadletedesigns.com</div>
                        </div>
                    </div>
                </div>
                <div class="corner-red"></div>

            </div>
            `;
        var container = document.createElement('div');
        container.innerHTML = letterheadHtml;
        container.querySelector('#pdf-content-wrapper').appendChild(element);

        var opt = {
            margin: 0,
            filename: projectName.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_statement.pdf',
            image: {
                type: 'jpeg',
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            },
            jsPDF: {
                unit: 'in',
                format: 'a4',
                orientation: 'portrait'
            }
        };

        window.html2pdf().set(opt).from(container).save().then(function() {
            btn.html(originalContent);
            btn.prop('disabled', false);
        }).catch(function(err) {
            console.error(err);
            btn.html(originalContent);
            btn.prop('disabled', false);
            Swal.fire("Error", "There was an error generating the PDF.", "error");
        });
    }

    /* =========================================================
       PROJECT EXPENSES MODAL & AJAX LOGIC
    ========================================================= */
    var currentExpenseProjectId = 0;

    window.openExpenseModal = function(projectId, projectName) {
        currentExpenseProjectId = projectId;
        $('#expense_modal_project_name').text('PROJECT: ' + projectName);
        $('#projectExpensesModal').modal('show');
        loadProjectExpenses(projectId);
    };

    window.toggleAddExpenseForm = function() {
        if (!currentExpenseProjectId) return;
        $('#popup_exp_project_id').val(currentExpenseProjectId);
        if ($('#add_expense_form_popup').length && $('#add_expense_form_popup')[0]) {
            $('#add_expense_form_popup')[0].reset();
        }
        $('#popup_att_file_name').text('Upload invoice or receipt');
        const sym = '₹';
        $('.popup_exp_curr_sym').text(sym);
        calcPopupExpTotal();
        $('#addExpenseFormModal').modal('show');
    };

    window.calcPopupExpTotal = function() {
        const qty = parseFloat($('#popup_exp_qty').val()) || 1;
        const cost = parseFloat($('#popup_exp_cost').val()) || 0;
        const total = qty * cost;
        $('#popup_exp_total_display').val(total.toFixed(2));
    };

    function loadProjectExpenses(projectId) {
        $('#project_expenses_table_body').html('<tr><td colspan="10" style="text-align:center; padding:30px; color:#64748b;"><i class="fa fa-spinner fa-spin"></i> Loading expenses...</td></tr>');

        $.ajax({
            url: 'ajax/projects/ajax_get_project_expenses.php',
            method: 'GET',
            data: {
                project_id: projectId
            },
            success: function(res) {
                if (res.success) {
                    const sym = '₹';
                    $('.exp_curr_sym').text(sym);
                    $('#project_expenses_grand_total').text(sym + ' ' + res.formatted_total_sum);

                    if (res.expenses.length === 0) {
                        $('#project_expenses_table_body').html('<tr><td colspan="10" style="text-align:center; padding:45px 20px; color:#94a3b8; font-weight:600; font-size:14px; background:#ffffff;">No expenses recorded for this project yet. Click + Add Expense to create one.</td></tr>');
                        return;
                    }

                    let html = '';
                    res.expenses.forEach(function(exp) {
                        let ordHtml = exp.ordered_from ? htmlEscapeExp(exp.ordered_from) : '-';
                        if (exp.ordered_from_url) {
                            ordHtml += ` <a href="${htmlEscapeExp(exp.ordered_from_url)}" target="_blank" style="color:#6366f1; font-size:11px;" title="Visit Website"><i class="fa fa-external-link"></i></a>`;
                        }

                        let invHtml = exp.invoice_no ? `Invoice #${htmlEscapeExp(exp.invoice_no)}` : '-';
                        if (exp.invoice_file) {
                            invHtml += ` <a href="${htmlEscapeExp(exp.invoice_file)}" target="_blank" style="color:#6366f1; font-weight:600; margin-left:5px; font-size:12px;">View</a>`;
                        }

                        let attHtml = '-';
                        if (exp.attachment) {
                            attHtml = `<a href="${htmlEscapeExp(exp.attachment)}" target="_blank" style="color:#6366f1; font-weight:600; font-size:12px;">View</a>`;
                        }

                        html += `
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="font-weight:700; color:#334155; font-size:13px; padding:12px 14px;">${htmlEscapeExp(exp.item_name)}</td>
                                <td style="text-align:center; font-weight:600; color:#475569; font-size:13px; padding:12px 14px;">${exp.qty}</td>
                                <td style="text-align:center; font-weight:700; color:#334155; font-size:13px; padding:12px 14px;">${sym}${parseFloat(exp.cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                <td style="text-align:center; font-weight:800; color:#1e293b; font-size:13px; padding:12px 14px;">${sym}${parseFloat(exp.total_cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
                                <td style="text-align:center; color:#64748b; font-size:12px; font-weight:600; padding:12px 14px;">${exp.expense_date}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${ordHtml}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${exp.paid_by ? htmlEscapeExp(exp.paid_by) : '-'}</td>
                                <td style="text-align:center; font-size:12px; color:#334155; font-weight:600; padding:12px 14px;">${invHtml}</td>
                                <td style="text-align:center; font-size:12px; padding:12px 14px;">${attHtml}</td>
                                <td style="text-align:center; padding:12px 14px;">
                                    <button type="button" class="btn-delete-expense-item" data-id="${exp.id}" data-project-id="${projectId}" onclick="deleteProjectExpense(${exp.id}, ${projectId})" style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:14px; padding:4px 8px;" title="Delete Expense"><i class="fa fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    $('#project_expenses_table_body').html(html);
                }
            }
        });
    }

    function htmlEscapeExp(str) {
        if (!str) return '';
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    $(document).on('submit', '#add_expense_form', function(e) {
        e.preventDefault();
        const btn = $('#btn_save_expense');
        btn.prop('disabled', true).text('Saving...');

        const formData = new FormData(this);
        $.ajax({
            url: 'ajax/projects/ajax_add_project_expense.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                btn.prop('disabled', false).text('Save Expense');
                if (res.success) {
                    $('#add_expense_form')[0].reset();
                    $('#add_expense_form_container').slideUp();
                    const pId = $('#exp_form_project_id').val();
                    loadProjectExpenses(pId);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    } else {
                        alert(res.message);
                    }
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Save Expense');
                alert('Network error saving expense.');
            }
        });
    });

    $(document).on('submit', '#add_expense_form_popup', function(e) {
        e.preventDefault();
        const btn = $('#btn_submit_popup_expense');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Submitting...');

        const formData = new FormData(this);
        $.ajax({
            url: 'ajax/projects/ajax_add_project_expense.php',
            method: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fa fa-file-text"></i> Submit Expense');
                if (res.success) {
                    $('#addExpenseFormModal').modal('hide');
                    loadProjectExpenses(currentExpenseProjectId);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Expense Added',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    } else {
                        alert(res.message);
                    }
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fa fa-file-text"></i> Submit Expense');
                alert('Network error saving expense.');
            }
        });
    });

    window.deleteProjectExpense = function(expId, projectId) {
        if (!expId) return;

        function doDelete() {
            $.ajax({
                url: 'ajax/projects/ajax_delete_project_expense.php',
                method: 'POST',
                data: {
                    expense_id: expId
                },
                dataType: 'json',
                success: function(res) {
                    if (typeof res === 'string') {
                        try {
                            res = JSON.parse(res.trim());
                        } catch (e) {}
                    }
                    if (res && res.success) {
                        loadProjectExpenses(projectId || currentExpenseProjectId);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted',
                                text: res.message || 'Expense deleted successfully.',
                                timer: 1500,
                                showConfirmButton: false
                            });
                        }
                    } else {
                        const msg = (res && res.message) ? res.message : 'Could not delete expense';
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', msg, 'error');
                        } else {
                            alert(msg);
                        }
                    }
                },
                error: function(xhr, status, err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Network error deleting expense.', 'error');
                    } else {
                        alert('Could not delete expense due to network error.');
                    }
                }
            });
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Expense?',
                text: 'Are you sure you want to delete this expense entry?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    doDelete();
                }
            });
        } else {
            if (confirm('Are you sure you want to delete this expense entry?')) {
                doDelete();
            }
        }
    };

    $(document).on('click', '.btn-delete-expense-item', function(e) {
        e.preventDefault();
        const id = $(this).attr('data-id') || $(this).data('id');
        const pId = $(this).attr('data-project-id') || $(this).data('project-id');
        if (id) {
            deleteProjectExpense(id, pId);
        }
    });
</script>

<!-- Project Expenses Modal -->
<style>
    #projectExpensesModal .modal-dialog {
        width: 92% !important;
        max-width: 1300px !important;
        margin: 25px auto !important;
    }

    #projectExpensesModal .table th,
    #projectExpensesModal .table td {
        vertical-align: middle !important;
    }

    #projectExpensesModal .table thead th {
        position: sticky !important;
        top: 0 !important;
        z-index: 10 !important;
        background-color: #52525b !important;
        color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    #addExpenseFormModal {
        z-index: 1070 !important;
    }

    #addExpenseFormModal .modal-content {
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #addExpenseFormModal .modal-body {
        overflow-y: auto !important;
        max-height: calc(88vh - 80px);
    }
</style>
<div class="modal fade" id="projectExpensesModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog" role="document" style="width: 92% !important; max-width: 1300px !important; margin: 30px auto !important;">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
            <!-- Modal Header -->
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 14px; text-align: left;">
                    <div style="width: 42px; height: 42px; background: #dd2127; color: #ffffff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                        <i class="fa fa-calculator"></i>
                    </div>
                    <div style="text-align: left;">
                        <h4 class="modal-title" style="margin: 0; font-size: 20px; font-weight: 800; color: #1e293b; letter-spacing: -0.3px; text-align: left;">Project Expenses</h4>
                        <div id="expense_modal_project_name" style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; text-align: left;">PROJECT: VET PET CARE</div>
                    </div>
                </div>

            </div>

            <!-- Modal Body -->
            <div class="modal-body" style="padding: 30px; background: #f8fafc;">
                <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">

                    <!-- Subheader: Expense History + Add Expense Button -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-receipt" style="color: #dd2127;"></i> Expense History
                            </h3>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500;">Track all project purchases, expenses and attachments.</p>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button" class="btn-premium-add" onclick="downloadExpenseStatement()">
                                <i class="fa fa-file-text-o"></i> Download Statement
                            </button>
                            <button type="button" class="btn-premium-add" onclick="toggleAddExpenseForm()">
                                <i class="fa fa-plus"></i> Add Expense
                            </button>
                        </div>
                    </div>

                    <!-- Expenses Table -->
                    <div style="overflow-x: auto; overflow-y: auto; max-height: 380px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff;">
                        <table class="table" style="margin: 0; width: 100%; border-collapse: collapse;">
                            <thead style="background: #52525b; color: #ffffff; position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ITEM NAME</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">QTY</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">COST</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">TOTAL COST</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">DATE</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ORDERED FROM</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">PAID BY</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">INVOICE</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ATTACHMENT</th>
                                    <th style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; padding: 13px 14px; border: none; text-align: center; position: sticky; top: 0; background: #52525b; color: #ffffff; z-index: 10;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody id="project_expenses_table_body">
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 40px; color: #64748b;">Loading expenses...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Total Expenses Footer -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px; padding-top: 15px; border-top: 1.5px solid #f1f5f9;">
                        <div style="font-size: 14px; font-weight: 800; color: #475569; letter-spacing: 0.5px; text-transform: uppercase;">
                            TOTAL PROJECT EXPENSES:
                        </div>
                        <div id="project_expenses_grand_total" style="font-size: 24px; font-weight: 900; color: #ef4444; letter-spacing: -0.5px;">
                            ₹ 0.00
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dedicated Add Project Expense Sub-Modal -->
<div class="modal fade" id="addExpenseFormModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document" style="width: 580px !important; max-width: 95% !important; margin: 40px auto !important;">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);">
            <!-- Header -->
            <div class="modal-header" style="background: #ffedeb; color: #1e293b; padding: 20px 25px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" aria-label="Close" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: flex-start; gap: 14px;">
                    <div style="width: 44px; height: 44px; background: #dd2127; color: #ffffff; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                        <i class="fa fa-calculator"></i>
                    </div>
                    <div>
                        <h4 class="modal-title" style="margin: 0; font-size: 19px; font-weight: 800; color: #0f172a; letter-spacing: -0.3px;">Add Project Expense</h4>
                        <p style="margin: 3px 0 0 0; font-size: 13px; color: #64748b; font-weight: 500;">Add a new expense to this project.</p>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body" style="padding: 10px 28px 28px 28px; background: #ffffff;">
                <form id="add_expense_form_popup" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" id="popup_exp_project_id">

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-7">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Item Name</label>
                            <input type="text" name="item_name" class="form-control p-input-premium" placeholder="Enter item name" required style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                        <div class="col-md-5">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Qty</label>
                            <input type="number" name="qty" id="popup_exp_qty" value="1" min="1" class="form-control p-input-premium" required oninput="calcPopupExpTotal()" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Cost</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 12px; font-weight: 700; color: #64748b; font-size: 14px;" class="popup_exp_curr_sym">₹</span>
                                <input type="number" step="0.01" name="cost" id="popup_exp_cost" placeholder="0.00" class="form-control p-input-premium" required oninput="calcPopupExpTotal()" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding-left: 32px; font-size: 13.5px; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Total Cost</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 12px; font-weight: 700; color: #64748b; font-size: 14px;" class="popup_exp_curr_sym">₹</span>
                                <input type="text" id="popup_exp_total_display" value="0.00" class="form-control p-input-premium" readonly style="height: 44px; border-radius: 10px; border: 1px solid #e2e8f0; padding-left: 32px; font-size: 13.5px; font-weight: 800; background: #f8fafc; color: #1e293b; width: 100%;">
                            </div>
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Date</label>
                            <input type="date" name="expense_date" class="form-control p-input-premium" value="<?php echo date('Y-m-d'); ?>" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Ordered From</label>
                            <input type="text" name="ordered_from" class="form-control p-input-premium" placeholder="e.g. DigiKey / Robu / Mouser" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom: 16px;">
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Order Link</label>
                            <div style="position: relative;">
                                <i class="fa fa-link" style="position: absolute; left: 14px; top: 14px; color: #94a3b8; font-size: 13px;"></i>
                                <input type="url" name="ordered_from_url" class="form-control p-input-premium" placeholder="https://" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding-left: 36px; font-size: 13.5px; width: 100%;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Paid By</label>
                            <input type="text" name="paid_by" class="form-control p-input-premium" placeholder="e.g. CADLETE / Varun" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Invoice Number</label>
                        <input type="text" name="invoice_no" class="form-control p-input-premium" placeholder="Enter invoice number" style="height: 44px; border-radius: 10px; border: 1px solid #cbd5e1; padding: 0 14px; font-size: 13.5px; width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label style="font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Upload Attachment</label>
                        <div style="border: 2px dashed #cbd5e1; border-radius: 14px; padding: 24px; text-align: center; background: #f8fafc; cursor: pointer; transition: 0.3s; position: relative;" onclick="document.getElementById('popup_expense_attachment').click()">
                            <div style="width: 44px; height: 44px; background: #ffffff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; color: #475569; font-size: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 8px;">
                                <i class="fa fa-cloud-upload"></i>
                            </div>
                            <div style="font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 3px;" id="popup_att_file_name">Upload invoice or receipt</div>
                            <div style="font-size: 12px; color: #94a3b8;">PDF, JPG, PNG</div>
                            <input type="file" name="attachment" id="popup_expense_attachment" style="display: none;" accept="image/*,.pdf" onchange="if(this.files[0]) $('#popup_att_file_name').text(this.files[0].name);">
                        </div>
                    </div>

                    <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                        <button type="button" class="btn-premium-cancel" data-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn_submit_popup_expense" class="btn-premium-add">
                            <i class="fa fa-file-text"></i> Submit Expense
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===================== PROJECT SOP CHECKLIST MODAL ===================== -->
<div class="modal fade" id="projectSopModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" role="document" style="max-width: 700px; width: 96%; margin: 30px auto;">
        <div class="modal-content" style="border-radius: 24px; border: none; overflow: hidden; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.35);">
            <!-- Header -->
            <div class="modal-header" style="background: #FFEAEB; color: #000; padding: 22px 28px; border: none; position: relative;">
                <button type="button" class="btn-modal-close" data-dismiss="modal" style="z-index: 10;">
                    <i class="fa fa-times"></i>
                </button>
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: #dd2127; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; box-shadow: 0 4px 12px rgba(221, 33, 39, 0.3);">
                            <i class="fa fa-check-square-o"></i>
                        </div>
                        <div>
                            <h4 class="modal-title" style="font-weight: 800; font-size: 20px; letter-spacing: -0.5px; margin: 0; color: #0f172a;">Project SOP Checklist</h4>
                            <p id="sop_modal_project_name" style="margin: 4px 0 0 0; font-size: 12px; color: #dd2127; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;"></p>
                        </div>
                    </div>
                    <div style="margin-left: auto; margin-right: 45px; text-align: center;">
                        <div id="sop_progress_ring_wrap" style="position: relative; width: 64px; height: 64px; margin: 0 auto;">
                            <svg width="64" height="64" style="transform: rotate(-90deg);">
                                <circle cx="32" cy="32" r="26" fill="none" stroke="#fecdd3" stroke-width="6" />
                                <circle id="sop_ring_fill" cx="32" cy="32" r="26" fill="none" stroke="#dd2127" stroke-width="6"
                                    stroke-dasharray="163.4" stroke-dashoffset="163.4"
                                    style="transition: stroke-dashoffset 0.6s ease; stroke-linecap: round;" />
                            </svg>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 13px; font-weight: 900; color: #dd2127;" id="sop_pct_label">0%</div>
                        </div>
                        <div id="sop_counter_label" style="font-size: 11px; font-weight: 700; color: #64748b; margin-top: 4px; white-space: nowrap;">0 / 0 done</div>
                    </div>
                </div>
            </div>
            <!-- Body -->
            <div class="modal-body" style="padding: 0; background: #fff; max-height: 65vh; overflow-y: auto;">
                <div id="sop_checklist_body" style="padding: 24px 28px;">
                    <div style="text-align: center; padding: 50px 0; color: #94a3b8;">
                        <i class="fa fa-spinner fa-spin" style="font-size: 28px;"></i>
                        <p style="margin-top: 12px; font-weight: 600;">Loading checklist...</p>
                    </div>
                </div>
            </div>
            <!-- Footer -->
            <div class="modal-footer" style="padding: 16px 28px; background: #f8fafc; border-top: 1px solid #f1f5f9; border: none;">
                <button type="button" class="btn-premium-cancel" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* SOP Checklist styles */
    .sop-section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 12px;
        margin-bottom: 12px;
        margin-top: 8px;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 1.2px;
    }

    .sop-item-row {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 14px;
        border-radius: 10px;
        margin-bottom: 6px;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        transition: 0.25s;
        cursor: pointer;
    }

    .sop-item-row:hover {
        background: #f5f3ff;
        border-color: #ede9fe;
    }

    .sop-item-row.sop-checked {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }

    .sop-item-row.sop-checked .sop-item-text {
        text-decoration: line-through;
        color: #94a3b8;
    }

    .sop-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 6px;
        border: 2px solid #cbd5e1;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 1px;
        transition: 0.2s;
    }

    .sop-checked .sop-checkbox {
        background: #16a34a;
        border-color: #16a34a;
    }

    .sop-item-text {
        font-size: 13px;
        font-weight: 600;
        color: #334155;
        line-height: 1.5;
        flex: 1;
    }

    .sop-checked-by {
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 2px;
    }
</style>

<script>
    // ── SOP Modal ──────────────────────────────────────────────────────────────
    let _sopCurrentProjectId = null;

    window.openSopModal = function(projectId, projectName) {
        _sopCurrentProjectId = projectId;
        $('#sop_modal_project_name').text(projectName);
        $('#sop_checklist_body').html(
            '<div style="text-align:center;padding:50px 0;color:#94a3b8;"><i class="fa fa-spinner fa-spin" style="font-size:28px;"></i><p style="margin-top:12px;font-weight:600;">Loading checklist...</p></div>'
        );
        $('#projectSopModal').modal('show');
        _loadSopChecklist(projectId);
    };

    function _loadSopChecklist(projectId) {
        $.ajax({
            url: 'ajax/projects/ajax_get_project_sop.php',
            method: 'GET',
            data: {
                project_id: projectId
            },
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    $('#sop_checklist_body').html('<p style="color:red;padding:20px;">Error loading checklist.</p>');
                    return;
                }
                _renderSopChecklist(res);
            },
            error: function() {
                $('#sop_checklist_body').html('<p style="color:red;padding:20px;">Network error.</p>');
            }
        });
    }

    var SOP_CAT_CONFIG = {
        'SETUP': {
            color: '#1d4ed8',
            bg: '#eff6ff',
            border: '#bfdbfe',
            icon: 'fa-cog'
        },
        'EXECUTION': {
            color: '#b45309',
            bg: '#fff7ed',
            border: '#fed7aa',
            icon: 'fa-wrench'
        },
        'COMPLETION': {
            color: '#065f46',
            bg: '#ecfdf5',
            border: '#a7f3d0',
            icon: 'fa-flag'
        },
        'MARKETING': {
            color: '#9d174d',
            bg: '#fdf2f8',
            border: '#f9a8d4',
            icon: 'fa-bullhorn'
        },
    };

    function _renderSopChecklist(res) {
        _updateSopProgress(res.completed, res.total);
        // Update badge in table
        var badgeEl = $('#sop_badge_' + res.project_id);
        if (badgeEl.length) {
            badgeEl.find('span').text(res.completed + '/' + res.total);
            var c = (res.completed === res.total && res.total > 0) ? '#16a34a' : (res.completed > 0 ? '#7c3aed' : '#94a3b8');
            var bg = (res.completed === res.total && res.total > 0) ? '#f0fdf4' : (res.completed > 0 ? '#f5f3ff' : '#f8fafc');
            var br = (res.completed === res.total && res.total > 0) ? '#bbf7d0' : (res.completed > 0 ? '#ede9fe' : '#e2e8f0');
            badgeEl.css({
                color: c,
                background: bg,
                'border-color': br
            });
        }

        var html = '';
        var catOrder = ['SETUP', 'EXECUTION', 'COMPLETION', 'MARKETING'];
        catOrder.forEach(function(cat) {
            if (!res.categories[cat] || res.categories[cat].length === 0) return;
            var cfg = SOP_CAT_CONFIG[cat] || {
                color: '#475569',
                bg: '#f8fafc',
                border: '#e2e8f0',
                icon: 'fa-list'
            };
            var done = res.categories[cat].filter(function(i) {
                return i.is_checked;
            }).length;
            html += '<div class="sop-section-header" style="background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';color:' + cfg.color + ';">' +
                '<i class="fa ' + cfg.icon + '"></i><span>' + cat + '</span>' +
                '<span style="margin-left:auto;font-size:10px;opacity:0.8;">' + done + '/' + res.categories[cat].length + '</span>' +
                '</div>';
            res.categories[cat].forEach(function(item) {
                var checked = item.is_checked ? 'sop-checked' : '';
                var checkIcon = item.is_checked ? '<i class="fa fa-check" style="color:#fff;font-size:11px;"></i>' : '';
                var byText = item.is_checked && item.checked_by ? '<div class="sop-checked-by"><i class="fa fa-user"></i> ' + $('<div>').text(item.checked_by).html() + '</div>' : '';
                html += '<div class="sop-item-row ' + checked + '" data-item-id="' + item.id + '" onclick="_toggleSopItem(' + res.project_id + ',' + item.id + ',this)">' +
                    '<div class="sop-checkbox">' + checkIcon + '</div>' +
                    '<div class="flex-1"><div class="sop-item-text">' + $('<div>').text(item.text).html() + '</div>' + byText + '</div>' +
                    '</div>';
            });
            html += '<div style="height:8px;"></div>';
        });
        $('#sop_checklist_body').html(html);
    }

    function _updateSopProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        var circumference = 163.4;
        var offset = circumference - (pct / 100) * circumference;
        $('#sop_ring_fill').attr('stroke-dashoffset', offset);
        if (done === total && total > 0) {
            $('#sop_ring_fill').attr('stroke', '#16a34a');
            $('#sop_pct_label').css('color', '#16a34a');
        } else {
            $('#sop_ring_fill').attr('stroke', '#7c3aed');
            $('#sop_pct_label').css('color', '#7c3aed');
        }
        $('#sop_pct_label').text(pct + '%');
        $('#sop_counter_label').text(done + ' / ' + total + ' done');
    }

    window._toggleSopItem = function(projectId, itemId, el) {
        var $row = $(el);
        var isNowChecked = !$row.hasClass('sop-checked') ? 1 : 0;

        // Optimistic UI
        if (isNowChecked) {
            $row.addClass('sop-checked');
            $row.find('.sop-checkbox').html('<i class="fa fa-check" style="color:#fff;font-size:11px;"></i>');
        } else {
            $row.removeClass('sop-checked');
            $row.find('.sop-checkbox').html('');
            $row.find('.sop-checked-by').remove();
        }

        $.ajax({
            url: 'ajax/projects/ajax_toggle_sop_item.php',
            method: 'POST',
            data: {
                project_id: projectId,
                sop_item_id: itemId,
                is_checked: isNowChecked,
                portal: 'admin'
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    if (isNowChecked && res.checked_by) {
                        $row.find('.sop-checked-by').remove();
                        $row.find('.flex-1, div[style*="flex:1"]').first().append(
                            '<div class="sop-checked-by"><i class="fa fa-user"></i> ' + $('<div>').text(res.checked_by).html() + '</div>'
                        );
                    }
                    _updateSopProgress(res.completed, res.total);
                    // update section header counts
                    var $section = $row.prevAll('.sop-section-header').first();
                    if ($section.length) {
                        var $items = $section.nextUntil('.sop-section-header, .sop-divider').filter('.sop-item-row');
                        var sectionDone = $items.filter('.sop-checked').length;
                        var sectionTotal = $items.length;
                        $section.find('span').last().text(sectionDone + '/' + sectionTotal);
                    }
                    // update table badge
                    var badgeEl = $('#sop_badge_' + projectId);
                    if (badgeEl.length) {
                        badgeEl.find('span').text(res.completed + '/' + res.total);
                        var c = (res.completed === res.total && res.total > 0) ? '#16a34a' : (res.completed > 0 ? '#7c3aed' : '#94a3b8');
                        var bg = (res.completed === res.total && res.total > 0) ? '#f0fdf4' : (res.completed > 0 ? '#f5f3ff' : '#f8fafc');
                        var br = (res.completed === res.total && res.total > 0) ? '#bbf7d0' : (res.completed > 0 ? '#ede9fe' : '#e2e8f0');
                        badgeEl.css({
                            color: c,
                            background: bg,
                            'border-color': br
                        });
                    }

                    // ── Auto-Completed: update status dropdown + toast + notification bell ──
                    if (res.auto_completed) {
                        // Update the project-status-select in the table row
                        var $statusSelect = $('select.project-status-select[data-project-id="' + projectId + '"]');
                        if ($statusSelect.length) {
                            $statusSelect.val('Completed');
                            // Re-style the status select to Completed green
                            $statusSelect.css({
                                'background-color': '#eff6ff',
                                'color': '#2563eb',
                                'border-color': '#dbeafe'
                            });
                        }
                        // Show SweetAlert toast
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: '🎉 Project Completed!',
                                html: '<b>All SOP items checked!</b><br>Project status has been automatically set to <b>Completed</b>.',
                                timer: 4000,
                                timerProgressBar: true,
                                showConfirmButton: false,
                                position: 'top-end',
                                toast: true
                            });
                        }
                        // Refresh admin notification bell
                        if (typeof loadUserNotifications === 'function') {
                            setTimeout(function() {
                                loadUserNotifications();
                            }, 800);
                        }
                        // Reload the projects table so status column reflects change
                        if (typeof loadProjects === 'function') {
                            setTimeout(function() {
                                loadProjects();
                            }, 1500);
                        }
                    }
                }
            }
        });
    };
</script>