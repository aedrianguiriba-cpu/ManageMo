<?php
/**
 * ManageMo - Folder Structure Validator
 * 
 * This script validates that all required folders and files exist.
 * Run this on your server to verify the structure is correct.
 * 
 * Delete this file after validation.
 */

// Get the document root
$root = $_SERVER['DOCUMENT_ROOT'];

// Define required structure
$required_files = [
    'index.php' => 'Root index page',
    'auth.php' => 'Authentication',
    'signup.php' => 'Signup page',
    'logout.php' => 'Logout handler',
    'forgot-password.php' => 'Password reset',
    'init.php' => 'Initialization file',
    'config/constants.php' => 'Configuration constants',
    'config/functions.php' => 'Core functions',
    'config/data.php' => 'Data configuration',
    'css/style.css' => 'Stylesheet',
    'js/script.js' => 'JavaScript',
    'lib/qrcode.php' => 'QR code library',
    'assets/pics/logo.png' => 'Logo image',
    'includes/header.php' => 'Header template',
    'includes/navbar.php' => 'Navigation bar',
    'includes/topbar.php' => 'Top bar',
    'includes/footer.php' => 'Footer template',
    'admin/dashboard.php' => 'Admin dashboard',
    'admin/inventory.php' => 'Admin inventory',
    'user/dashboard.php' => 'User dashboard',
    'user/inventory.php' => 'User inventory',
];

$required_dirs = [
    'config' => 'Configuration directory',
    'css' => 'Stylesheet directory',
    'js' => 'JavaScript directory',
    'lib' => 'Libraries directory',
    'assets' => 'Assets directory',
    'assets/pics' => 'Images directory',
    'assets/uploads' => 'Uploads directory',
    'assets/qrcodes' => 'QR codes directory',
    'includes' => 'Includes directory',
    'admin' => 'Admin pages directory',
    'user' => 'User pages directory',
    'database' => 'Database documentation',
];

// Check files and folders
$file_results = [];
$dir_results = [];
$all_passed = true;

foreach ($required_files as $path => $description) {
    $full_path = $root . '/' . $path;
    $exists = file_exists($full_path) && is_file($full_path);
    $file_results[$path] = [
        'description' => $description,
        'exists' => $exists,
        'full_path' => $full_path,
    ];
    if (!$exists) $all_passed = false;
}

foreach ($required_dirs as $path => $description) {
    $full_path = $root . '/' . $path;
    $exists = is_dir($full_path);
    $dir_results[$path] = [
        'description' => $description,
        'exists' => $exists,
        'full_path' => $full_path,
    ];
    if (!$exists) $all_passed = false;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ManageMo - Folder Structure Validator</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0066cc;
            padding-bottom: 10px;
        }
        .status-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-good {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .status-bad {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f0f0f0;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .pass {
            color: green;
            font-weight: bold;
        }
        .fail {
            color: red;
            font-weight: bold;
        }
        .path {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        .icon {
            font-size: 18px;
            margin-right: 5px;
        }
        .summary {
            font-size: 16px;
            font-weight: bold;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .summary.pass {
            background: #d4edda;
            color: #155724;
        }
        .summary.fail {
            background: #f8d7da;
            color: #721c24;
        }
        .note {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .note h3 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 ManageMo - Folder Structure Validator</h1>

        <?php if ($all_passed): ?>
            <div class="summary pass">
                <span class="icon">✓</span> All required files and folders exist!
            </div>
        <?php else: ?>
            <div class="summary fail">
                <span class="icon">✗</span> Some files or folders are missing. See details below.
            </div>
        <?php endif; ?>

        <div class="status-box">
            <h2>📁 Required Directories</h2>
            <table>
                <tr>
                    <th>Directory</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($dir_results as $path => $result): ?>
                <tr class="<?php echo $result['exists'] ? 'status-good' : 'status-bad'; ?>">
                    <td class="path">/<?php echo $path; ?>/</td>
                    <td><?php echo $result['description']; ?></td>
                    <td class="<?php echo $result['exists'] ? 'pass' : 'fail'; ?>">
                        <?php echo $result['exists'] ? '✓ EXISTS' : '✗ MISSING'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="status-box">
            <h2>📄 Required Files (Sample)</h2>
            <p>Showing first 20 critical files. Total: <?php echo count($file_results); ?></p>
            <table>
                <tr>
                    <th>File</th>
                    <th>Description</th>
                    <th>Status</th>
                </tr>
                <?php foreach (array_slice($file_results, 0, 20) as $path => $result): ?>
                <tr class="<?php echo $result['exists'] ? 'status-good' : 'status-bad'; ?>">
                    <td class="path">/<?php echo $path; ?></td>
                    <td><?php echo $result['description']; ?></td>
                    <td class="<?php echo $result['exists'] ? 'pass' : 'fail'; ?>">
                        <?php echo $result['exists'] ? '✓ EXISTS' : '✗ MISSING'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="note">
            <h3>📋 Next Steps</h3>
            <ol>
                <li>If all items show ✓ (green), your structure is correct!</li>
                <li>If any items show ✗ (red), upload those files/folders from your local copy</li>
                <li>Use an FTP client (like FileZilla) to upload missing items</li>
                <li>After fixing, refresh this page to verify</li>
                <li><strong>Delete this file (validate.php) when done</strong></li>
            </ol>
        </div>

        <div class="status-box">
            <h2>🔧 Server Information</h2>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Document Root</td>
                    <td class="path"><?php echo $root; ?></td>
                </tr>
                <tr>
                    <td>Server Name</td>
                    <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo phpversion(); ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
