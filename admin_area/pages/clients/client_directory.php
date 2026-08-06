<?php
if (!isset($con)) {
    if (!isset($con)) {
        include(__DIR__ . '/../../includes/db.php');
    }
}

$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($con, $_GET['status']) : '';
$industry_filter = isset($_GET['industry']) ? mysqli_real_escape_string($con, $_GET['industry']) : '';
$country_filter = isset($_GET['country']) ? mysqli_real_escape_string($con, $_GET['country']) : '';

$where_clauses = [];
if ($search) {
    $where_clauses[] = "(name LIKE '%$search%' OR company_name LIKE '%$search%' OR country LIKE '%$search%')";
}
if ($status_filter) {
    if ($status_filter == 'Active') {
        $where_clauses[] = "id IN (SELECT client_id FROM client_projects WHERE status = 'Active' AND deleted_at IS NULL)";
    } else if ($status_filter == 'Inactive') {
        $where_clauses[] = "id NOT IN (SELECT client_id FROM client_projects WHERE status = 'Active' AND deleted_at IS NULL)";
    }
}
if ($industry_filter) {
    $where_clauses[] = "industry LIKE '%$industry_filter%'";
}
if ($country_filter) {
    $where_clauses[] = "country = '$country_filter'";
}

$where_clauses[] = "deleted_at IS NULL";

$where = "";
if (count($where_clauses) > 0) {
    $where = "WHERE " . implode(" AND ", $where_clauses);
}

// Count queries for stat cards
$total_clients_q = mysqli_query($con, "SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL");
$total_clients = mysqli_fetch_assoc($total_clients_q)['count'];

$active_clients_q = mysqli_query($con, "SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL AND id IN (SELECT client_id FROM client_projects WHERE status = 'Active' AND deleted_at IS NULL)");
$active_clients = mysqli_fetch_assoc($active_clients_q)['count'];

$inactive_clients = $total_clients - $active_clients;

$total_projects_q = mysqli_query($con, "SELECT COUNT(*) as count FROM client_projects WHERE deleted_at IS NULL");
$total_projects = mysqli_fetch_assoc($total_projects_q)['count'];

$active_percent = $total_clients > 0 ? round(($active_clients / $total_clients) * 100, 1) : 0;
$inactive_percent = $total_clients > 0 ? round(($inactive_clients / $total_clients) * 100, 1) : 0;

$get_clients = "SELECT * FROM clients $where ORDER BY id DESC";
$run_clients = mysqli_query($con, $get_clients);

// Base query string for stat card filtering (preserving other filters)
$query_params = [];
if (!empty($search)) $query_params['search'] = $search;
if (!empty($industry_filter)) $query_params['industry'] = $industry_filter;
if (!empty($country_filter)) $query_params['country'] = $country_filter;


$base_query = '';
if (!empty($query_params)) {
    $base_query = '&' . http_build_query($query_params);
}

// Get distinct countries and industries for dropdowns
$countries_q = mysqli_query($con, "SELECT DISTINCT country FROM clients WHERE deleted_at IS NULL AND country IS NOT NULL AND country != '' ORDER BY country");
$industries_q = mysqli_query($con, "SELECT industry_name as industry FROM client_industries WHERE deleted_at IS NULL ORDER BY industry_name");
?>

<div class="page-wrapper premium-ui-enabled">
    <div class="page-header-premium">
        <h1></h1>
        <div class="header-actions-premium" style="display: flex; align-items: center; gap: 15px;">
            <div style="position: relative;">
                <i class="fa fa-search" style="position: absolute; left: 15px; top: 13px; color: #94a3b8;"></i>
                <input type="text" id="header_search" class="p-input-premium" placeholder=" Search... " value="<?php echo htmlspecialchars($search); ?>" style="padding-left: 40px; height: 42px; width: 300px; font-size: 14px;" onchange="applyColumnFilter('search', this.value)">
            </div>
            <?php if (canAdminAccess('client_insert')): ?>
                <a href="index.php?add_client" class="btn-premium-add">
                    <i class="fa fa-plus"></i> Add Client
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-cards-row">
        <!-- Total Clients -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?client_directory<?php echo $base_query; ?>'">
            <div class="stat-card-icon sc-blue">
                <i class="fa fa-users"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Total Clients</div>
                <div class="stat-card-value"><?php echo $total_clients; ?></div>
            </div>
        </div>

        <!-- Active Clients -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?client_directory&status=Active<?php echo $base_query; ?>'">
            <div class="stat-card-icon sc-green">
                <i class="fa fa-building-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Active</div>
                <div class="stat-card-value"><?php echo $active_clients; ?></div>
            </div>
        </div>

        <!-- Inactive Clients -->
        <div class="stat-card" style="cursor: pointer; transition: 0.3s;" onclick="window.location.href='index.php?client_directory&status=Inactive<?php echo $base_query; ?>'">
            <div class="stat-card-icon sc-orange">
                <i class="fa fa-clock-o"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Inactive</div>
                <div class="stat-card-value"><?php echo $inactive_clients; ?></div>
            </div>
        </div>

        <!-- Total Projects -->
        <div class="stat-card">
            <div class="stat-card-icon sc-purple">
                <i class="fa fa-folder-open"></i>
            </div>
            <div class="stat-card-body">
                <div class="stat-card-title">Total Projects</div>
                <div class="stat-card-value"><?php echo $total_projects; ?></div>
            </div>
        </div>
    </div>



    <div class="premium-card">
        <div class="card-hdr">
            <i class="fa fa-table"></i>
            <h3>All Clients</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 80px; text-align: center;">ID</th>
                        <th style="width: 80px; text-align: center;">Photo</th>
                        <th>Client Info</th>
                        <th style="text-align: center;">Company</th>
                        <th style="position: relative; overflow: visible; min-width: 100px; padding: 15px 10px !important;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 13px; color: <?php echo !empty($_GET['country']) ? '#1e293b' : '#475569'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                <?php echo !empty($_GET['country']) ? htmlspecialchars($_GET['country']) : 'Country'; ?>
                                <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($_GET['country']) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                            </div>
                            <select id="countrySelect" onchange="applyColumnFilter('country', this.value)"
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                <option value="">All Countries</option>
                                <?php
                                mysqli_data_seek($countries_q, 0);
                                while ($cnt = mysqli_fetch_assoc($countries_q)): ?>
                                    <option value="<?php echo htmlspecialchars($cnt['country']); ?>" <?php if ($country_filter == $cnt['country']) echo 'selected'; ?>><?php echo htmlspecialchars($cnt['country']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </th>
                        <th style="position: relative; overflow: visible; min-width: 100px; padding: 15px 10px !important;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 6px; font-weight: 800; font-size: 13px; color: <?php echo !empty($_GET['industry']) ? '#1e293b' : '#475569'; ?>; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s;">
                                <?php echo !empty($_GET['industry']) ? htmlspecialchars($_GET['industry']) : 'Industry'; ?>
                                <i class="fa fa-filter" style="font-size: 11px; color: <?php echo !empty($_GET['industry']) ? '#4f46e5' : '#94a3b8'; ?>;"></i>
                            </div>
                            <select id="industrySelect" onchange="applyColumnFilter('industry', this.value)"
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;">
                                <option value="">All Industries</option>
                                <?php
                                mysqli_data_seek($industries_q, 0);
                                while ($ind = mysqli_fetch_assoc($industries_q)): ?>
                                    <option value="<?php echo htmlspecialchars($ind['industry']); ?>" <?php if ($industry_filter == $ind['industry']) echo 'selected'; ?>><?php echo htmlspecialchars($ind['industry']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </th>
                        <th style="text-align: center;">Link</th>
                        <th style="text-align: center;">Projects</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (mysqli_num_rows($run_clients) > 0):
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($run_clients)):
                            $image_path = '../uploads/client_images/' . $row['image'];
                            $img = (!empty($row['image']) && file_exists($image_path)) ? $image_path : 'admin_images/default.png';
                    ?>
                            <tr>
                                <td style="text-align: center;">
                                    <span class="id-badge-premium">#<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <img src="<?php echo $img; ?>" class="client-table-img" alt="Profile"
                                        onclick="viewImage('<?php echo $img; ?>', '<?php echo htmlspecialchars($row['name']); ?>')"
                                        title="Click to zoom">
                                </td>
                                <td>
                                    <div style="font-weight: 800; color: #0f172a; font-size: 15px; letter-spacing: -0.3px;"><?php echo htmlspecialchars($row['name']); ?></div>
                                    <div style="font-size: 11px; color: #94a3b8; font-weight: 700; margin-top: 4px; display: grid; align-items: center; gap: 8px;">
                                        <span><i class="fa fa-envelope-o"></i> <?php echo htmlspecialchars($row['email']); ?></span>
                                        <span><i class="fa fa-phone"></i> <?php echo htmlspecialchars($row['mobile']); ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div style="font-weight: 700; color: #475569; font-size: 13px; text-align: center;"><?php echo htmlspecialchars($row['company_name'] ?: '-'); ?></div>
                                </td>
                                <td style="color: #64748b; font-size: 13px; font-weight: 700; text-align: center;"><?php echo htmlspecialchars($row['country'] ?: '-'); ?></td>
                                <td style="text-align: center;">
                                    <div style="font-weight: 700; color: #475569; font-size: 12px; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0; display: inline-block; white-space: normal; word-wrap: break-word; line-height: 1.4; max-width: 140px;"><?php echo htmlspecialchars($row['industry'] ?: '-'); ?></div>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['website']): ?>
                                        <a href="<?php echo htmlspecialchars($row['website']); ?>" target="_blank" class="btn-icon-premium" title="Visit Website">
                                            <i class="fa fa-globe" style="color: #6366f1;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #cbd5e1; font-weight: 800;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php
                                $client_id_for_proj = $row['id'];
                                $proj_q = mysqli_query($con, "SELECT COUNT(*) as total_projects, SUM(CASE WHEN status='Active' THEN 1 ELSE 0 END) as active_projects FROM client_projects WHERE client_id='$client_id_for_proj' AND deleted_at IS NULL");
                                $proj_data = mysqli_fetch_assoc($proj_q);
                                $client_total_proj = $proj_data['total_projects'];
                                $client_active_proj = $proj_data['active_projects'];
                                ?>
                                <td style="text-align: center;">
                                    <span style="font-weight: 800; color: #dd2127; background: #ffeaeb; padding: 4px 12px; border-radius: 8px; font-size: 13px;"><?php echo $client_total_proj; ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($client_active_proj > 0): ?>
                                        <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700;">Active</span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 700;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 10px; justify-content: center;">
                                        <a href="index.php?view_projects&id=<?php echo $row['id']; ?>" class="btn-icon-premium" title="View Projects" style="background: #f0f9ff; border-color: #e0f2fe;">
                                            <i class="fa fa-briefcase" style="color: #0ea5e9;"></i>
                                        </a>
                                        <?php if (canAdminAccess('client_update')): ?>
                                            <a href="index.php?edit_client=<?php echo $row['id']; ?>" class="btn-icon-premium" title="Edit Client" style="background: #fdfaf1; border-color: #fef3c7;">
                                                <i class="fa fa-pencil" style="color: #d97706;"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (canAdminAccess('client_delete')): ?>
                                            <a href="javascript:void(0)" class="btn-icon-premium" title="Delete Client" style="background: #fef2f2; border-color: #fee2e2; color: #ef4444;" onclick="confirmDeleteClient(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr>
                            <td colspan="9" style="padding: 0; border-bottom: none;">
                                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; width: 100%;">
                                    <div style="width: 64px; height: 64px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                                        <i class="fa fa-folder-open-o" style="font-size: 28px; color: #cbd5e1;"></i>
                                    </div>
                                    <div style="font-size: 15px; font-weight: 700; color: #64748b; margin-bottom: 4px;">No clients found.</div>
                                    <div style="font-size: 13px; color: #94a3b8;">No clients to show right now.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>




<style>
    /* Premium Modal Overlay Styles */
    .premium-confirm-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .premium-confirm-overlay.active {
        display: flex;
        opacity: 1;
    }

    .premium-confirm-modal {
        background: #fff;
        width: 100%;
        max-width: 400px;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
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
        color: #fff !important;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
    }

    .confirm-btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
    }

    /* Table Premium Standard */

    .table-premium td {
        padding: 20px 25px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }


    .client-table-img {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        object-position: center 10%;
        border: 2px solid #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    .client-table-img:hover {
        transform: scale(1.15) rotate(5deg);
        border-color: #dd2127;
        box-shadow: 0 10px 15px -3px rgba(221, 33, 39, 0.4);
    }

    .p-input-premium {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        border-radius: 14px;
        transition: all 0.3s;
        padding: 12px 20px;
        width: 100%;
        color: #0f172a;
        font-weight: 600;
        height: 50px;
    }

    .p-input-premium:focus {
        background: #fff;
        border-color: #dd2127 !important;
        box-shadow: 0 0 0 3px #ffeaeb !important;

    }

    .id-badge-premium {
        font-family: 'Monaco', 'Consolas', monospace;
        font-weight: 800;
        color: #94a3b8;
        font-size: 13px;
        background: #f1f5f9;
        padding: 4px 10px;
        border-radius: 8px;
    }

    .view-image-round {
        width: 320px;
        height: 320px;
        border-radius: 50%;
        object-fit: cover;
        object-position: center 10%;
        border: 8px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        background: #f8fafc;
        padding: 5px;
    }
</style>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" role="dialog" style="background: rgba(15, 23, 42, 0.9);">
    <div class="modal-dialog" role="document" style="width: fit-content; max-width: 90vw; margin: 10vh auto;">
        <div class="modal-content" style="background: transparent; border: none; box-shadow: none;">
            <div class="modal-body text-center" style="padding: 0; position: relative;">
                <button type="button" class="close" data-dismiss="modal" style="position: absolute; right: -40px; top: -10px; color: white; opacity: 1; font-size: 35px; text-shadow: 0 0 10px rgba(0,0,0,0.5);">&times;</button>
                <img id="viewer_img" src="" class="view-image-round">
                <h3 id="viewer_name" style="color: white; margin-top: 25px; font-weight: 700; font-size: 24px; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">Client Name</h3>
            </div>
        </div>
    </div>
</div>

<script>
    function viewImage(img, name) {
        var viewerImg = document.getElementById('viewer_img');
        var viewerName = document.getElementById('viewer_name');
        if (viewerImg && viewerName) {
            viewerImg.src = img;
            viewerName.textContent = name;
            $('#imageViewerModal').modal('show');
        }
    }

    // Client Deletion Modern Modal
    function confirmDeleteClient(id, name) {
        document.getElementById('delete_target_name').textContent = name;
        document.getElementById('confirm_delete_btn').href = 'index.php?delete_client=' + id;
        document.getElementById('clientDeleteConfirmOverlay').classList.add('active');
    }

    function closeDeleteConfirm() {
        document.getElementById('clientDeleteConfirmOverlay').classList.remove('active');
    }

    function applyColumnFilter(param, value) {
        let url = new URL(window.location.href);
        if (value) {
            url.searchParams.set(param, value);
        } else {
            url.searchParams.delete(param);
        }
        window.location.href = url.toString();
    }
</script>

<!-- Client Delete Confirmation Modal -->
<div class="premium-confirm-overlay" id="clientDeleteConfirmOverlay">
    <div class="premium-confirm-modal">
        <div class="premium-confirm-header">
            <div class="confirm-icon-box">
                <i class="fa fa-trash-o"></i>
            </div>
            <h3>Delete Client?</h3>
            <p>You are about to permanently delete <strong id="delete_target_name">this client</strong>. This action will remove all their records from the directory.</p>
        </div>
        <div class="premium-confirm-footer">
            <button class="confirm-btn-cancel" onclick="closeDeleteConfirm()" type="button">Cancel</button>
            <a class="confirm-btn-delete" id="confirm_delete_btn" href="#">Delete Client</a>
        </div>
    </div>
</div>