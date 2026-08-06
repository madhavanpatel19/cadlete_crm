<?php
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (isset($_GET['project_id'])) {
    $project_id = mysqli_real_escape_string($con, $_GET['project_id']);

    $get_links = "SELECT * FROM project_links WHERE project_id = '$project_id' AND deleted_at IS NULL ORDER BY created_at DESC";
    $run_links = mysqli_query($con, $get_links);

    if (mysqli_num_rows($run_links) > 0) {
        echo '<div style="display: grid; gap: 16px;">';
        while ($l = mysqli_fetch_assoc($run_links)) {
            $link_id = $l['id'];
            $name = htmlspecialchars($l['link_name']);
            $raw_url = trim($l['link_url']);
            $url = 'javascript:void(0);';
            $onclick_attr = '';
            $target_attr = '';

            if (!empty($raw_url)) {
                if (!preg_match("~^(?:f|ht)tps?://~i", $raw_url)) {
                    $raw_url = "https://" . $raw_url;
                }
                $url = htmlspecialchars($raw_url);
                $target_attr = 'target="_blank"';
            } else {
                $url = 'javascript:void(0);';
                $onclick_attr = 'onclick="Swal.fire(\'Notice\', \'No URL specified for this link.\', \'info\'); return false;"';
            }

            $date = date('d M, Y', strtotime($l['created_at']));

            // Domain identification for icon
            $domain = parse_url($url, PHP_URL_HOST);
            $icon = 'fa-external-link';
            $icon_color = '#6366f1';
            $icon_bg = 'rgba(99, 102, 241, 0.08)';

            if (strpos($domain, 'figma.com') !== false) {
                $icon = 'fa-pencil-square-o';
                $icon_color = '#a21caf';
                $icon_bg = 'rgba(162, 28, 175, 0.08)';
            } elseif (strpos($domain, 'github.com') !== false) {
                $icon = 'fa-github';
                $icon_color = '#1f2937';
                $icon_bg = 'rgba(31, 41, 55, 0.08)';
            } elseif (strpos($domain, 'trello.com') !== false) {
                $icon = 'fa-trello';
                $icon_color = '#0079bf';
                $icon_bg = 'rgba(0, 121, 191, 0.08)';
            } elseif (strpos($domain, 'google.com') !== false) {
                $icon = 'fa-google';
                $icon_color = '#ea4335';
                $icon_bg = 'rgba(234, 67, 53, 0.08)';
            }

            echo '
            <div class="artifact-card-premium" style="background: #fff; border: 1.5px solid #f1f5f9; border-radius: 20px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);" onmouseover="this.style.borderColor=\'#e2e8f0\'; this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 10px 15px -3px rgba(0, 0, 0, 0.05)\'" onmouseout="this.style.borderColor=\'#f1f5f9\'; this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 6px -1px rgba(0, 0, 0, 0.02)\'">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 54px; height: 54px; background:#ffeaeb; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #dd2127;">
                        <i class="fa ' . $icon . '"></i>
                    </div>
                    <div>
                        <div style="font-weight: 800; color: #0f172a; font-size: 15px; letter-spacing: -0.2px;">' . $name . '</div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                            <span style="font-size: 11px; color: #94a3b8; font-weight: 750; text-transform: uppercase; letter-spacing: 0.5px;">External Resource</span>
                            <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                            <span style="font-size: 11px; color: #64748b; font-weight: 600;">Saved ' . $date . '</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="' . $url . '" ' . $target_attr . ' ' . $onclick_attr . ' class="btn-icon-premium btn-icon-sm btn-icon-edit" title="Visit URL">
                        <i class="fa fa-paper-plane"></i>
                    </a>
                    <button onclick="deleteDoc(' . $link_id . ', ' . $project_id . ')" class="btn-icon-premium btn-icon-sm btn-icon-delete" title="Remove Link">
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
                <i class="fa fa-link" style="font-size: 28px;"></i>
            </div>
            <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 5px;">No Links Archived</h4>
            <p style="font-size: 13px;">External resources have not been added for this project yet.</p>
        </div>';
    }
}
