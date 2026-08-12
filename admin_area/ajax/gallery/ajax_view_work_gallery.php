<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

if (!isset($_SESSION['admin_email'])) {
    exit('Access Denied');
}

$filter_emp    = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : '';
$filter_date   = isset($_GET['date']) ? trim($_GET['date']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_from   = isset($_GET['from']) ? trim($_GET['from']) : '';
$filter_to     = isset($_GET['to']) ? trim($_GET['to']) : '';

$where = ["a.work_photos IS NOT NULL AND a.work_photos != '' AND a.work_photos != '[]'"];

if (!empty($filter_emp)) $where[] = "a.emp_id = $filter_emp";
if (!empty($filter_date)) $where[] = "DATE(a.attendance_date) = '" . mysqli_real_escape_string($con, $filter_date) . "'";
if (!empty($filter_status)) {
    $st_esc = mysqli_real_escape_string($con, $filter_status);
    $where[] = "a.status = '$st_esc'";
}
if (!empty($filter_from)) $where[] = "DATE(a.attendance_date) >= '" . mysqli_real_escape_string($con, $filter_from) . "'";
if (!empty($filter_to))   $where[] = "DATE(a.attendance_date) <= '" . mysqli_real_escape_string($con, $filter_to) . "'";

$whereSql = "WHERE " . implode(" AND ", $where);

$sql = "SELECT a.*, e.name AS emp_name, e.employee_image 
        FROM attendance a 
        LEFT JOIN emp_list e ON a.emp_id = e.id 
        $whereSql
        ORDER BY a.attendance_date DESC";
$result = mysqli_query($con, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    echo '<div class="work-gallery-grid">';
    while ($row = mysqli_fetch_assoc($result)) {
        $photos = json_decode($row['work_photos'], true);
        if (empty($photos)) continue;

        $emp_name = htmlspecialchars($row['emp_name']);
        $date = date('d M Y', strtotime($row['attendance_date']));
        $emp_img = !empty($row['employee_image']) ? 'uploads/' . $row['employee_image'] : '../admin_area/admin_images/default.png';

        foreach ($photos as $p) {
            $p_esc = htmlspecialchars($p);
            echo "
            <div class='work-gallery-item' onclick=\"window.open('$p_esc')\">
                <img src='$p_esc' loading='lazy'>
                <div class='item-overlay'>
                    <div class='item-info'>
                        <div class='item-emp'>
                            <img src='$emp_img' class='emp-mini-img'>
                            <span>$emp_name</span>
                        </div>
                        <div class='item-date'>$date</div>
                    </div>
                    <i class='fa fa-search-plus'></i>
                </div>
            </div>";
        }
    }
    echo '</div>';
} else {
    echo '
    <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
        <i class="fa fa-image" style="font-size: 50px; display: block; margin-bottom: 15px; opacity: 0.3;"></i>
        <h4 style="font-weight: 700; color: #64748b;">No Photos Found</h4>
        <p style="font-size: 14px;">Try adjusting your filters to find more worksheet photos.</p>
    </div>';
}
?>

<style>
    .work-gallery-grid {
        display: grid;
        grid-template-columns: repeat(8, minmax(0, 1fr));
        gap: 10px;
        padding: 15px;
        animation: galleryReveal 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @media (max-width: 1100px) {
        .work-gallery-grid {
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .work-gallery-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    @media (max-width: 480px) {
        .work-gallery-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @keyframes galleryReveal {
        from {
            opacity: 0;
            transform: scale(0.98) translateY(15px);
        }

        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .work-gallery-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.25s ease;
        border: 1.5px solid #e2e8f0;
    }

    .work-gallery-item:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.15);
        border-color: #6366f1;
    }

    .work-gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: 0.8s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .work-gallery-item:hover img {
        transform: scale(1.15);
        filter: brightness(0.7);
    }

    .item-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, transparent 0%, rgba(15, 23, 42, 0) 50%, rgba(15, 23, 42, 0.8) 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 20px;
        opacity: 0;
        transition: 0.4s ease;
    }

    .work-gallery-item:hover .item-overlay {
        opacity: 1;
    }

    .item-info {
        transform: translateY(15px);
        transition: 0.5s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .work-gallery-item:hover .item-info {
        transform: translateY(0);
    }

    .item-emp {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 800;
        font-size: 13px;
        color: #fff;
    }

    .emp-mini-img {
        width: 22px !important;
        height: 22px !important;
        border-radius: 6px !important;
        border: 1.5px solid rgba(255, 255, 255, 0.4) !important;
    }

    .item-date {
        font-size: 10px;
        font-weight: 600;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 3px;
    }

    .item-overlay i {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) scale(0.5);
        color: #fff;
        font-size: 24px;
        background: #dd2127;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        opacity: 0;
        transition: 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    }

    .work-gallery-item:hover .item-overlay i {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
</style>