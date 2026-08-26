<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/../../includes/db.php');
}

header('Content-Type: application/json');

// ── 1. Ensure tables exist ──────────────────────────────────────────────────

// Master SOP items table
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_items` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(100) NOT NULL,
    `item_text` TEXT NOT NULL,
    `sort_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Per-project checklist state
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `project_sop_checklist` (
    `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT(11) NOT NULL,
    `sop_item_id` INT(11) NOT NULL,
    `is_checked` TINYINT(1) DEFAULT 0,
    `checked_by` VARCHAR(255) DEFAULT NULL,
    `checked_at` DATETIME DEFAULT NULL,
    UNIQUE KEY `unique_project_sop` (`project_id`, `sop_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Remove "Google My Business updated" if it exists in DB
mysqli_query($con, "DELETE FROM project_sop_checklist WHERE sop_item_id IN (SELECT id FROM project_sop_items WHERE item_text LIKE '%Google My Business updated%')");
mysqli_query($con, "DELETE FROM project_sop_items WHERE item_text LIKE '%Google My Business updated%'");

// ── 2. Seed master SOP items if empty ──────────────────────────────────────

$count_check = mysqli_query($con, "SELECT COUNT(*) as cnt FROM project_sop_items");
$cnt_row = mysqli_fetch_assoc($count_check);
if ((int)$cnt_row['cnt'] === 0) {
    $sop_items = [
        // SETUP
        ['SETUP',      'Job Card created in Job Card Tracker',                             1],
        ['SETUP',      'Drive Main Project Folder created',                                2],
        ['SETUP',      'Social Media folder created (3d Cad Images) - PORTFOLIO SECTION', 3],
        ['SETUP',      'Canva Whiteboard created for design tracking',                     4],
        // EXECUTION
        ['EXECUTION',  'Daily work photos uploaded to CRM as work log',                    5],
        ['EXECUTION',  'Design changes tracked on Canva Whiteboard (dated)',               6],
        ['EXECUTION',  'Phase-wise site images saved in portfolio folder',                 7],
        ['EXECUTION',  'Changelog updated after every revision',                           8],
        // COMPLETION
        ['COMPLETION', 'All final files saved on Drive with Date',                         9],
        ['COMPLETION', 'Photorealistic renders created and saved',                        10],
        ['COMPLETION', 'BOM with vendors created',                                        11],
        ['COMPLETION', 'Vendors added to Vendor Sheet',                                   12],
        ['COMPLETION', 'Job Card updated with all final links (Fusion Link + Drive + Canva)', 13],
        ['COMPLETION', 'Project Folder downloaded locally on Master Computer',            14],
        // MARKETING
        ['MARKETING',  'Add Content of Portfolio on Sheet',                               15],
        ['MARKETING',  'Add Content of Case Study on Sheet',                              16],
        ['MARKETING',  'Portfolio images organised in Drive by phase',                    17],
        ['MARKETING',  'Figma - Social Media content created (Insta, Case Study, etc.)', 18],
        ['MARKETING',  'Portfolio website updated FR + Case Study',                       19],
        ['MARKETING',  'Social Media Posts published on all Social Media platforms',      20]
    ];

    $insert_stmt = mysqli_prepare($con, "INSERT INTO project_sop_items (category, item_text, sort_order) VALUES (?, ?, ?)");
    foreach ($sop_items as $item) {
        mysqli_stmt_bind_param($insert_stmt, 'ssi', $item[0], $item[1], $item[2]);
        mysqli_stmt_execute($insert_stmt);
    }
    mysqli_stmt_close($insert_stmt);
}

// ── 3. Get project_id ───────────────────────────────────────────────────────

$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit;
}

// ── 4. Fetch all items with checked state for this project ──────────────────

$sql = "SELECT 
            si.id,
            si.category,
            si.item_text,
            si.sort_order,
            COALESCE(sc.is_checked, 0) AS is_checked,
            sc.checked_by,
            sc.checked_at
        FROM project_sop_items si
        LEFT JOIN project_sop_checklist sc 
            ON sc.sop_item_id = si.id AND sc.project_id = $project_id
        ORDER BY si.sort_order ASC";

$result = mysqli_query($con, $sql);

$categories = [];
$total = 0;
$completed = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $cat = $row['category'];
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = [
        'id'         => (int)$row['id'],
        'text'       => $row['item_text'],
        'is_checked' => (int)$row['is_checked'],
        'checked_by' => $row['checked_by'],
        'checked_at' => $row['checked_at'],
    ];
    $total++;
    if ((int)$row['is_checked']) $completed++;
}

echo json_encode([
    'success'    => true,
    'project_id' => $project_id,
    'total'      => $total,
    'completed'  => $completed,
    'categories' => $categories,
]);
