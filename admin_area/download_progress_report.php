<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($con)) {
    include(__DIR__ . '/includes/db.php');
}

// Allow access if logged in as admin or employee
if (!isset($_SESSION['admin_email']) && !isset($_SESSION['emp_id'])) {
    die("Unauthorized access");
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    die("Invalid project ID");
}

// Fetch project details
$proj_q = "SELECT cp.*, c.name as client_name, c.email as client_email, c.mobile as client_phone 
           FROM client_projects cp 
           LEFT JOIN clients c ON cp.client_id = c.id 
           WHERE cp.id = $project_id";
$proj_res = mysqli_query($con, $proj_q);
$project = mysqli_fetch_assoc($proj_res);

if (!$project) {
    die("Project not found");
}

// Ensure posted_by column exists
try {
    @mysqli_query($con, "ALTER TABLE client_project_remarks ADD COLUMN posted_by VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
}

// Fetch project activity remarks
$remarks_q = "SELECT * FROM client_project_remarks WHERE project_id = $project_id ORDER BY created_at DESC";
$remarks_res = mysqli_query($con, $remarks_q);

$currency_symbol = ($project['currency'] == 'USD' || $project['currency'] == '$') ? '$' : '₹';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Progress Report - <?php echo htmlspecialchars($project['project_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="font-awesome/css/font-awesome.min.css">
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 40px 20px;
        }

        .report-container {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 25px;
            border-bottom: 2px solid #f1f5f9;
            margin-bottom: 30px;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .brand-sub {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
            font-weight: 600;
        }

        .report-badge {
            background: #eff6ff;
            color: #2563eb;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #f1f5f9;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 11px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .meta-val {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .timeline-table th {
            background: #f1f5f9;
            text-align: left;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .timeline-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: top;
        }

        .author-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .author-user {
            background: #eff6ff;
            color: #2563eb;
        }

        .author-system {
            background: #f1f5f9;
            color: #64748b;
        }

        .no-print {
            margin-bottom: 20px;
            text-align: right;
        }

        .btn-print {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        @page {
            size: auto;
            margin: 12mm 15mm;
        }

        @media print {
            @page {
                size: auto;
                margin: 12mm 15mm;
            }

            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
            }

            .report-container {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }
        }
    </style>
</head>

<body>

    <div class="report-container">
        <div class="no-print">
            <button onclick="window.print()" class="btn-print"><i class="fa fa-print"></i> Print / Download PDF</button>
        </div>

        <div class="report-header">
            <div>
                <h1 class="brand-title"><?php echo htmlspecialchars($project['project_name']); ?></h1>
                <div class="brand-sub"><i class="fa fa-building"></i> Client: <?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></div>
            </div>
            <div>
                <span class="report-badge">Status: <?php echo htmlspecialchars($project['status']); ?></span>
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span class="meta-label">Project ID</span>
                <span class="meta-val">#<?php echo str_pad($project['id'], 3, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Start Date</span>
                <span class="meta-val"><?php echo !empty($project['project_date']) ? date('d M Y', strtotime($project['project_date'])) : 'N/A'; ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Deadline</span>
                <span class="meta-val"><?php echo !empty($project['deadline']) ? date('d M Y', strtotime($project['deadline'])) : 'N/A'; ?></span>
            </div>
        </div>

        <div class="section-title">
            <i class="fa fa-history" style="color: #6366f1;"></i>
            Project Activity Timeline & Progress Updates
        </div>

        <table class="timeline-table">
            <thead>
                <tr>
                    <th style="width: 180px;">Date & Time</th>
                    <th style="width: 160px;">Updated By</th>
                    <th>Activity / Milestone Accomplished</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($remarks_res) > 0): ?>
                    <?php while ($r = mysqli_fetch_assoc($remarks_res)):
                        $author = !empty($r['posted_by']) ? htmlspecialchars($r['posted_by']) : '';
                        if (empty($author)) {
                            $author = (strpos($r['remark'], 'System:') === 0) ? 'System' : 'Team Member';
                        }
                        $is_sys = (strtolower($author) === 'system');
                    ?>
                        <tr>
                            <td style="font-weight: 600; color: #64748b;">
                                <?php echo date('d M Y • h:i A', strtotime($r['created_at'])); ?>
                            </td>
                            <td>
                                <span class="author-badge <?php echo $is_sys ? 'author-system' : 'author-user'; ?>">
                                    <i class="fa <?php echo $is_sys ? 'fa-cog' : 'fa-user'; ?>"></i>
                                    <?php echo $author; ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: #1e293b; line-height: 1.5;">
                                <?php echo nl2br(htmlspecialchars($r['remark'])); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; padding: 40px; color: #94a3b8;">
                            No activity records found for this project.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="margin-top: 50px; text-align: center; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            Generated on <?php echo date('d M Y • h:i A'); ?> — Cadlete CRM Progress Report
        </div>
    </div>

</body>

</html>