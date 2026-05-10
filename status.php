<?php
/**
 * ManageMo - Quick Status Check
 * Simple status page to verify site is working
 */

// No requires - we're just checking server variables
$status_ok = true;
$issues = [];

// Check if we can access config
if (!file_exists('config/constants.php')) {
    $status_ok = false;
    $issues[] = 'config/constants.php is missing';
}

// Check folders
$required_folders = ['css', 'js', 'assets', 'config', 'admin', 'user'];
foreach ($required_folders as $folder) {
    if (!is_dir($folder)) {
        $status_ok = false;
        $issues[] = "Folder missing: /$folder/";
    }
}

// Check critical files
$critical_files = [
    'css/style.css' => 'Stylesheet',
    'js/script.js' => 'JavaScript',
    'assets/pics/logo.png' => 'Logo image',
];

foreach ($critical_files as $file => $desc) {
    if (!file_exists($file)) {
        $status_ok = false;
        $issues[] = "File missing: /$file ($desc)";
    }
}

$icon = $status_ok ? '✓' : '✗';
$color = $status_ok ? 'green' : 'red';

?>
<!DOCTYPE html>
<html>
<head>
    <title>ManageMo - Status Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; text-align: center; background: #f5f5f5; }
        h1 { color: <?php echo $color; ?>; font-size: 48px; margin: 0; }
        .status { 
            background: white; 
            padding: 30px; 
            border-radius: 5px; 
            max-width: 500px; 
            margin: 20px auto;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .message { font-size: 18px; margin: 10px 0; }
        .issues { 
            background: #fff3cd; 
            color: #856404;
            padding: 15px;
            border-radius: 3px;
            text-align: left;
            margin-top: 15px;
        }
        .issues ul { margin: 10px 0; padding-left: 20px; }
        .issues li { margin: 5px 0; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1><?php echo $icon; ?></h1>
    <div class="status">
        <div class="message" style="color: <?php echo $color; ?>; font-weight: bold; font-size: 20px;">
            <?php echo $status_ok ? 'Site Structure OK' : 'Issues Detected'; ?>
        </div>

        <?php if ($status_ok): ?>
            <p>Your folder structure looks good!</p>
            <p><a href="/">👉 Visit your site</a> or <a href="debug.php">📊 Run detailed scan</a></p>
        <?php else: ?>
            <p>Some files or folders are missing:</p>
            <div class="issues">
                <ul>
                    <?php foreach ($issues as $issue): ?>
                    <li><?php echo htmlspecialchars($issue); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin-top: 15px; font-size: 12px;">
                    👉 <a href="debug.php" style="color: #0066cc;">Run detailed diagnostic</a> for more info
                </p>
            </div>
        <?php endif; ?>

        <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
        <p style="font-size: 12px; color: #666;">
            <a href="debug.php">📋 Full Diagnostic Report</a> | 
            <a href="validate.php">✓ Complete Validation</a>
        </p>
    </div>
</body>
</html>
