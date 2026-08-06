<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$rawFile = trim($_GET['file']);
$fileName = basename($rawFile);
$uploadDir = realpath(__DIR__ . '/../../uploads/');
$filePath = realpath($uploadDir . '/' . $fileName);

// Security check: ensure path is within uploads directory
if (!$filePath || strpos($filePath, $uploadDir) !== 0 || !file_exists($filePath)) {
    die("File not found or access denied.");
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// 1. PDF Documents -> Inline Browser PDF Viewer
if ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();
}

// 2. Image Files -> Inline Image Output
$imgMimes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml'
];
if (isset($imgMimes[$ext])) {
    header('Content-Type: ' . $imgMimes[$ext]);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit();
}

// 3. CSV Files -> Render as a clean, full-tab HTML Table
if ($ext === 'csv') {
    $content = file_get_contents($filePath);
    $lines = explode("\n", trim($content));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>View Document - <?php echo htmlspecialchars($fileName); ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
            .header-bar { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .header-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 16px; }
            .btn-download { background: #3b82f6; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
            .btn-download:hover { background: #2563eb; }
            .table-container { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
            table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
            th { background: #f1f5f9; color: #334155; font-weight: 700; padding: 12px 16px; border-bottom: 2px solid #cbd5e1; }
            td { padding: 10px 16px; border-bottom: 1px solid #e2e8f0; color: #334155; }
            tr:hover { background: #f8fafc; }
        </style>
    </head>
    <body>
        <div class="header-bar">
            <div class="header-title">
                <i class="fa fa-table" style="color: #059669; font-size: 20px;"></i>
                <span><?php echo htmlspecialchars($fileName); ?></span>
            </div>
            <a href="../../uploads/<?php echo urlencode($fileName); ?>" download class="btn-download"><i class="fa fa-download"></i> Download Original CSV</a>
        </div>
        <div class="table-container">
            <table>
                <?php foreach ($lines as $idx => $line): 
                    if (trim($line) === '') continue;
                    $cols = str_getcsv($line);
                ?>
                    <tr>
                        <?php foreach ($cols as $col): ?>
                            <?php if ($idx === 0): ?>
                                <th><?php echo htmlspecialchars($col); ?></th>
                            <?php else: ?>
                                <td><?php echo htmlspecialchars($col); ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 4. Plain Text / JSON / Logs / Code -> Inline Plaintext
if (in_array($ext, ['txt', 'json', 'log', 'xml', 'html'])) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    readfile($filePath);
    exit();
}

// 5. Fallback for Word/Excel/Binary documents -> Direct Download / Inline View Page
header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="' . $fileName . '"');
readfile($filePath);
exit();
