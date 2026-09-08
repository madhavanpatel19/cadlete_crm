<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (isset($_GET['project_id'])) {
    $project_id = (int)$_GET['project_id'];
    $category = isset($_GET['category']) ? trim($_GET['category']) : 'project_images';
    $user_type = isset($_GET['user_type']) ? trim($_GET['user_type']) : 'admin';

    $is_images = ($category === 'project_images');
    $empty_icon = $is_images ? 'fa-picture-o' : 'fa-share-alt';
    $empty_title = $is_images ? 'No Project Images Uploaded' : 'No Social Media Posts Uploaded';
    $empty_desc = $is_images ? 'Click "+ Upload Image" above to attach required 3D CAD, final product, or render assets.' : 'Click "+ Upload Post / Reel" above to attach portfolio & social marketing assets.';

    // Fetch only actually uploaded assets
    $get_assets = "SELECT * FROM project_media_assets WHERE project_id = '$project_id' AND category = '$category' AND deleted_at IS NULL ORDER BY created_at DESC, id DESC";
    $run_assets = mysqli_query($con, $get_assets);
    $admin_area_web_base = str_replace('\\', '/', dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))));

    if ($run_assets && mysqli_num_rows($run_assets) > 0) {
        echo '<div style="display: grid; gap: 14px;">';
        while ($asset = mysqli_fetch_assoc($run_assets)) {
            $asset_id = (int)$asset['id'];
            $name = htmlspecialchars($asset['asset_name']);
            $spec = !empty($asset['dimension_spec']) ? htmlspecialchars($asset['dimension_spec']) : '';
            $orig_name = !empty($asset['original_file_name']) ? htmlspecialchars($asset['original_file_name']) : '';
            $is_link = !empty($asset['link_url']);
            $date = date('d M, Y', strtotime($asset['created_at']));
            $uploader = !empty($asset['uploaded_by']) ? htmlspecialchars($asset['uploaded_by']) : 'Team';

            $file_url = 'javascript:void(0);';
            $is_img = false;
            $target_attr = 'target="_blank"';

            if ($is_link) {
                $file_url = htmlspecialchars($asset['link_url']);
            } elseif (!empty($asset['file_path'])) {
                $raw_path = $asset['file_path'];
                if (preg_match("~^(?:f|ht)tps?://~i", $raw_path)) {
                    $file_url = htmlspecialchars($raw_path);
                } else {
                    $file_url = rtrim($admin_area_web_base, '/') . '/' . ltrim(htmlspecialchars($raw_path), '/');
                }
                $ext = strtolower(pathinfo($raw_path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                    $is_img = true;
                }
            }

            // Dimension / Tag Badge
            $spec_badge = '';
            if (!empty($spec)) {
                $spec_badge = '<span style="background: #f1f5f9; color: #475569; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0;"><i class="fa fa-tag" style="color: #dd2127;"></i> ' . $spec . '</span>';
            }

            // Left Icon / Image Preview
            $icon_html = '';
            if ($is_img && !empty($file_url) && $file_url !== 'javascript:void(0);') {
                $icon_html = '
                <div style="width: 52px; height: 52px; border-radius: 14px; overflow: hidden; border: 1.5px solid #e2e8f0; flex-shrink: 0; background: #f8fafc; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.04);" onclick="window.open(\'' . $file_url . '\', \'_blank\')">
                    <img src="' . $file_url . '" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                </div>';
            } else {
                $icon_class = $is_link ? 'fa-link' : ($asset['file_type'] === 'video' ? 'fa-video-camera' : 'fa-file-image-o');
                $icon_html = '
                <div style="width: 52px; height: 52px; background: #ffeaeb; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #dd2127; flex-shrink: 0;">
                    <i class="fa ' . $icon_class . '"></i>
                </div>';
            }

            echo '
            <div class="artifact-card-premium" style="background: #fff; border: 1.5px solid #f1f5f9; border-radius: 18px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; transition: all 0.25s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor=\'#e2e8f0\'; this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 8px 16px rgba(0,0,0,0.05)\'" onmouseout="this.style.borderColor=\'#f1f5f9\'; this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.02)\'">
                <div style="display: flex; align-items: center; gap: 16px; min-width: 0; flex: 1;">
                    ' . $icon_html . '
                    <div style="min-width: 0; flex: 1;">
                        <div style="font-weight: 800; color: #0f172a; font-size: 14.5px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span>' . $name . '</span>
                            ' . $spec_badge . '
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; font-size: 12px; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ' . (!empty($orig_name) ? '<span style="font-weight: 600; color: #334155;">' . $orig_name . '</span><span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>' : '') . '
                            <span>Uploaded ' . $date . ' by ' . $uploader . '</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <a href="' . $file_url . '" ' . $target_attr . ' class="btn-icon-premium btn-icon-sm btn-icon-edit" title="Open Asset">
                        <i class="fa ' . ($is_link ? 'fa-external-link' : 'fa-eye') . '"></i>
                    </a>
                    <button type="button" onclick="deleteMediaAsset(' . $asset_id . ')" class="btn-icon-premium btn-icon-sm btn-icon-delete" title="Remove Asset">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>';
        }
        echo '</div>';
    } else {
        echo '
        <div style="text-align: center; padding: 45px 20px; color: #94a3b8;">
            <div style="width: 64px; height: 64px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                <i class="fa ' . $empty_icon . '" style="font-size: 26px; color: #94a3b8;"></i>
            </div>
            <h4 style="font-weight: 800; color: #1e293b; margin-bottom: 5px; font-size: 16px;">' . $empty_title . '</h4>
            <p style="font-size: 13px; max-width: 440px; margin: 0 auto; line-height: 1.5;">' . $empty_desc . '</p>
        </div>';
    }
}
