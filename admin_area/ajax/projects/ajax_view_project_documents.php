<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}
if (!function_exists('isSuperAdmin')) {
    require_once(__DIR__ . '/../../includes/admin_permissions.php');
}

if (isset($_GET['project_id'])) {
    $project_id = mysqli_real_escape_string($con, $_GET['project_id']);
    $user_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : '';

    $current_admin_id = 0;
    if (isset($_SESSION['admin_email'])) {
        $email_esc = mysqli_real_escape_string($con, $_SESSION['admin_email']);
        $r_adm = mysqli_query($con, "SELECT admin_id FROM admins WHERE admin_email = '$email_esc' LIMIT 1");
        if ($r_adm && $row_a = mysqli_fetch_assoc($r_adm)) {
            $current_admin_id = (int)$row_a['admin_id'];
        }
    }

    $res_p = mysqli_query($con, "SELECT assigned_admins FROM client_projects WHERE id = '$project_id' LIMIT 1");
    $assigned_admins_list = [];
    if ($res_p && $row_p = mysqli_fetch_assoc($res_p)) {
        if (!empty($row_p['assigned_admins'])) {
            $assigned_admins_list = array_map('trim', explode(',', $row_p['assigned_admins']));
        }
    }

    // Proposals are ONLY visible to Admins (Super Admin or Assigned Admin) in Admin Portal.
    // If request comes from Employee Portal, proposal documents are strictly hidden.
    if ($user_type === 'employee') {
        $can_see_proposal = false;
    } else {
        $can_see_proposal = isSuperAdmin() || ($current_admin_id > 0 && (empty($assigned_admins_list) || in_array((string)$current_admin_id, $assigned_admins_list, true)));
    }

    $where_proposal = $can_see_proposal ? "" : " AND (is_proposal = 0 OR is_proposal IS NULL) ";

    $get_docs = "SELECT * FROM project_documents WHERE project_id = '$project_id' AND deleted_at IS NULL $where_proposal ORDER BY created_at DESC";
    $run_docs = mysqli_query($con, $get_docs);

    if (mysqli_num_rows($run_docs) > 0) {
        echo '<div style="display: grid; gap: 16px;">';
        while ($doc = mysqli_fetch_assoc($run_docs)) {
            $doc_id = $doc['id'];
            $name = htmlspecialchars($doc['document_name']);
            $raw_path = trim($doc['file_path']);
            $file_url = 'javascript:void(0);';
            $onclick_attr = '';
            $target_attr = '';

            if (!empty($raw_path)) {
                if (preg_match("~^(?:f|ht)tps?://~i", $raw_path)) {
                    $file_url = htmlspecialchars($raw_path);
                    $target_attr = 'target="_blank"';
                } else {
                    $file_basename = basename($raw_path);
                    $possible_paths = array_unique([
                        $raw_path,
                        'uploads/project_documents/' . $file_basename,
                        'uploads/project_docs/' . $file_basename,
                        'project_documents/' . $file_basename,
                        'project_docs/' . $file_basename,
                        'uploads/' . $file_basename
                    ]);

                    $found_rel_path = null;
                    foreach ($possible_paths as $p) {
                        $check_sys = __DIR__ . '/../../' . $p;
                        if (file_exists($check_sys) && !is_dir($check_sys)) {
                            $found_rel_path = $p;
                            break;
                        }
                    }

                    if ($found_rel_path !== null) {
                        $admin_area_web_base = str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))));
                        $file_url = rtrim($admin_area_web_base, '/') . '/' . ltrim(htmlspecialchars($found_rel_path), '/');
                        $target_attr = 'target="_blank"';
                    } else {
                        $file_url = 'javascript:void(0);';
                        $onclick_attr = 'onclick="Swal.fire(\'File Not Found\', \'The file (' . htmlspecialchars($file_basename) . ') is not stored on the server.\', \'warning\'); return false;"';
                    }
                }
            } else {
                $file_url = 'javascript:void(0);';
                $onclick_attr = 'onclick="Swal.fire(\'Notice\', \'No file path specified for this artifact.\', \'info\'); return false;"';
            }

            $date = !empty($doc['created_at']) ? date('d M, Y', strtotime($doc['created_at'])) : 'N/A';
            $ext = strtolower(pathinfo($raw_path, PATHINFO_EXTENSION));
            $is_proposal = !empty($doc['is_proposal']);

            // Icon mapping
            $icon = 'fa-file-o';
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $icon = 'fa-file-image-o';
            } elseif ($ext == 'pdf') {
                $icon = 'fa-file-pdf-o';
            } elseif (in_array($ext, ['doc', 'docx'])) {
                $icon = 'fa-file-word-o';
            } elseif (in_array($ext, ['xls', 'xlsx'])) {
                $icon = 'fa-file-excel-o';
            } elseif ($ext == 'zip') {
                $icon = 'fa-file-archive-o';
            }

            $badge_html = '';
            if ($is_proposal) {
                $badge_html = '<span style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-lock"></i> PROPOSAL (SUPER ADMIN & ASSIGNED ADMIN)</span>';
            }

            echo '
            <div class="artifact-card-premium" style="background: #fff; border: 1.5px solid #f1f5f9; border-radius: 20px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);" onmouseover="this.style.borderColor=\'#e2e8f0\'; this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 10px 15px -3px rgba(0, 0, 0, 0.05)\'" onmouseout="this.style.borderColor=\'#f1f5f9\'; this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.02)\'">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 54px; height: 54px; background:#ffeaeb; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; color:#dd2127">
                        <i class="fa ' . $icon . '"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #0f172a; font-size: 15px; letter-spacing: -0.2px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span>' . $name . '</span> ' . $badge_html . '
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <span style="font-size: 11px; color: #94a3b8; font-weight: 750; text-transform: uppercase; letter-spacing: 0.5px;">' . ($ext ? $ext : 'file') . ' Artifact</span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span style="font-size: 11px; color: #64748b; font-weight: 600;">Recorded ' . $date . '</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="' . $file_url . '" ' . $target_attr . ' ' . $onclick_attr . ' class="btn-icon-premium btn-icon-sm btn-icon-edit" title="Open Artifact">
                        <i class="fa fa-external-link"></i>
                    </a>
                    <button onclick="deleteDoc(' . $doc_id . ', ' . $project_id . ')" class="btn-icon-premium btn-icon-sm btn-icon-delete" title="Purge Artifact">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>';
        }
        echo '</div>';
    } else {
        echo '
        <div style="text-align: center; padding: 40px 20px; color: #94a3b8;">
            <div style="width: 64px; height: 64px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fa fa-folder-open-o" style="font-size: 28px;"></i>
            </div>
            <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 5px;">Empty Repository</h4>
            <p style="font-size: 13px;">No documents have been attached to this project yet.</p>
        </div>';
    }
}
