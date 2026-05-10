<?php
/**
 * ManageMo - Advanced Debug & Diagnostic Tool
 * 
 * This script helps identify folder access issues
 * Delete this after diagnosis
 */

require_once 'config/constants.php';

// Collect diagnostic data
$diagnostics = [
    'server_info' => [
        'Server Name' => $_SERVER['SERVER_NAME'] ?? 'N/A',
        'Script Name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
        'Request URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
    ],
    'base_url_info' => [
        'BASE_URL Constant' => BASE_URL,
        'Is Root (/)' => BASE_URL === '/' ? 'Yes ✓' : 'No ✗',
    ],
    'folder_checks' => [],
    'file_checks' => [],
];

// Check if folders exist and are readable
$folders_to_check = [
    'css' => 'Stylesheets',
    'js' => 'JavaScript',
    'assets' => 'Assets',
    'assets/pics' => 'Logo images',
    'assets/uploads' => 'User uploads',
    'assets/qrcodes' => 'QR codes',
    'config' => 'Configuration',
    'includes' => 'Templates',
    'admin' => 'Admin pages',
    'user' => 'User pages',
    'lib' => 'Libraries',
];

$doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';

foreach ($folders_to_check as $folder => $description) {
    $full_path = rtrim($doc_root, '/') . '/' . $folder;
    $exists = is_dir($full_path);
    $readable = $exists && is_readable($full_path);
    $perms = $exists ? substr(sprintf('%o', fileperms($full_path)), -4) : 'N/A';
    
    $diagnostics['folder_checks'][$folder] = [
        'Description' => $description,
        'Path' => $full_path,
        'Exists' => $exists ? 'Yes ✓' : 'No ✗',
        'Readable' => $readable ? 'Yes ✓' : 'No ✗',
        'Permissions' => $perms,
    ];
}

// Check critical files
$files_to_check = [
    'css/style.css' => 'Main stylesheet',
    'js/script.js' => 'Main JavaScript',
    'assets/pics/logo.png' => 'Logo image',
    'config/constants.php' => 'Constants config',
    'config/functions.php' => 'Core functions',
    'includes/header.php' => 'Header template',
    'admin/dashboard.php' => 'Admin dashboard',
    'user/dashboard.php' => 'User dashboard',
];

foreach ($files_to_check as $file => $description) {
    $full_path = rtrim($doc_root, '/') . '/' . $file;
    $exists = file_exists($full_path) && is_file($full_path);
    $readable = $exists && is_readable($full_path);
    $size = $exists ? filesize($full_path) : 'N/A';
    
    $diagnostics['file_checks'][$file] = [
        'Description' => $description,
        'Path' => $full_path,
        'Exists' => $exists ? 'Yes ✓' : 'No ✗',
        'Readable' => $readable ? 'Yes ✓' : 'No ✗',
        'Size' => is_numeric($size) ? $size . ' bytes' : 'N/A',
    ];
}

// Analyze problems
$problems = [];
$missing_folders = array_filter($diagnostics['folder_checks'], function($check) {
    return $check['Exists'] === 'No ✗';
});

$missing_files = array_filter($diagnostics['file_checks'], function($check) {
    return $check['Exists'] === 'No ✗';
});

$unreadable = array_merge(
    array_filter($diagnostics['folder_checks'], function($check) {
        return $check['Readable'] === 'No ✗';
    }),
    array_filter($diagnostics['file_checks'], function($check) {
        return $check['Readable'] === 'No ✗';
    })
);

if (!empty($missing_folders)) {
    $problems[] = [
        'type' => 'error',
        'title' => 'Missing Folders',
        'count' => count($missing_folders),
        'folders' => $missing_folders,
    ];
}

if (!empty($missing_files)) {
    $problems[] = [
        'type' => 'error',
        'title' => 'Missing Files',
        'count' => count($missing_files),
        'files' => $missing_files,
    ];
}

if (!empty($unreadable)) {
    $problems[] = [
        'type' => 'warning',
        'title' => 'Permission Issues',
        'count' => count($unreadable),
        'details' => 'Some files/folders are not readable',
    ];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ManageMo - Folder Access Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card {
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 2px solid #e9ecef;
            font-weight: bold;
            color: #333;
        }
        .card-content {
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f0f0f0;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .yes { color: #27ae60; font-weight: bold; }
        .no { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .path { font-family: monospace; font-size: 11px; color: #666; }
        .status-box {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .status-error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        .status-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        .status-success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        .solution {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-size: 14px;
        }
        .solution h4 {
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .solution ol {
            margin-left: 20px;
        }
        .solution li {
            margin: 8px 0;
            line-height: 1.5;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Folder Access Diagnostic Report</h1>

        <?php if (empty($problems)): ?>
            <div class="status-box status-success">
                <strong>✓ All Systems Operational!</strong><br>
                All folders and critical files are accessible. Your site structure looks good.
            </div>
        <?php else: ?>
            <div class="status-box status-error">
                <strong>✗ Issues Detected (<?php echo count($problems); ?>)</strong><br>
                See details below and follow the solutions provided.
            </div>

            <?php foreach ($problems as $problem): ?>
                <div class="status-box status-<?php echo $problem['type']; ?>">
                    <strong><?php echo $problem['title']; ?> — <?php echo $problem['count']; ?> item(s)</strong>
                    
                    <?php if ($problem['type'] === 'error'): ?>
                        <div class="solution">
                            <h4>⚠️ Why this happens:</h4>
                            <?php if (isset($problem['folders'])): ?>
                                <p><strong>Missing folders:</strong></p>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <?php foreach ($problem['folders'] as $folder => $check): ?>
                                        <li><code><?php echo $folder; ?>/</code> — <?php echo $check['Description']; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p style="margin-top: 15px;"><strong>Solution:</strong> Upload these folders to your server using FTP </p>
                            <?php elseif (isset($problem['files'])): ?>
                                <p><strong>Missing files:</strong></p>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <?php foreach ($problem['files'] as $file => $check): ?>
                                        <li><code><?php echo $file; ?></code> — <?php echo $check['Description']; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <p style="margin-top: 15px;"><strong>Solution:</strong> Upload these files to your server using FTP</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">📡 Server Configuration</div>
            <div class="card-content">
                <table>
                    <tr>
                        <th>Property</th>
                        <th>Value</th>
                    </tr>
                    <?php foreach ($diagnostics['server_info'] as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo $key; ?></strong></td>
                        <td class="path"><?php echo htmlspecialchars($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

    <div class="card">
        <div class="card-header">🌐 BASE_URL Configuration</div>
        <div class="card-content">
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td><strong>Current BASE_URL</strong></td>
                    <td class="path"><?php echo htmlspecialchars(BASE_URL); ?></td>
                    <td><span class="yes">✓ <?php echo (BASE_URL === '/' ? 'Root' : 'Subfolder'); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Server Type</strong></td>
                    <td class="path">
                        <?php
                        $server_name = $_SERVER['SERVER_NAME'] ?? 'Unknown';
                        if ($server_name === 'localhost' || strpos($server_name, '127.0.0.1') !== false || strpos($server_name, '::1') !== false) {
                            echo '🖥️ Localhost / Local Development';
                        } else if (strpos($server_name, 'managemo.ct.ws') !== false) {
                            echo '☁️ InfinityFree (Production)';
                        } else {
                            echo '🌐 ' . htmlspecialchars($server_name);
                        }
                        ?>
                    </td>
                    <td>-</td>
                </tr>
                <tr>
                    <td><strong>Expected BASE_URL</strong></td>
                    <td class="path">
                        <?php
                        $server_name = $_SERVER['SERVER_NAME'] ?? '';
                        $expected = '/';
                        if ($server_name === 'localhost' || strpos($server_name, '127.0.0.1') !== false || strpos($server_name, '::1') !== false) {
                            if (strpos($_SERVER['SCRIPT_NAME'], '/managemo/') !== false) {
                                $expected = '/managemo/';
                            }
                        }
                        echo htmlspecialchars($expected);
                        ?>
                    </td>
                    <td class="<?php echo (BASE_URL === $expected ? 'yes' : 'no'); ?>">
                        <?php echo (BASE_URL === $expected ? '✓ Correct' : '✗ Mismatch'); ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>SCRIPT_NAME</strong></td>
                    <td class="path"><?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']); ?></td>
                    <td>Reference</td>
                </tr>
            </table>
        </div>
    </div>

        <div class="card">
            <div class="card-header">📁 Folder Status</div>
            <div class="card-content">
                <table>
                    <tr>
                        <th>Folder</th>
                        <th>Description</th>
                        <th>Exists</th>
                        <th>Readable</th>
                        <th>Permissions</th>
                    </tr>
                    <?php foreach ($diagnostics['folder_checks'] as $folder => $check): ?>
                    <tr>
                        <td><code><?php echo $folder; ?>/</code></td>
                        <td><?php echo $check['Description']; ?></td>
                        <td><span class="<?php echo str_replace(' ✓', '', str_replace(' ✗', '', $check['Exists']) === 'Yes' ? 'yes' : 'no'); ?>"><?php echo $check['Exists']; ?></span></td>
                        <td><span class="<?php echo str_replace(' ✓', '', str_replace(' ✗', '', $check['Readable']) === 'Yes' ? 'yes' : 'no'); ?>"><?php echo $check['Readable']; ?></span></td>
                        <td><?php echo $check['Permissions']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">📄 Critical Files Status</div>
            <div class="card-content">
                <table>
                    <tr>
                        <th>File</th>
                        <th>Description</th>
                        <th>Exists</th>
                        <th>Readable</th>
                        <th>Size</th>
                    </tr>
                    <?php foreach ($diagnostics['file_checks'] as $file => $check): ?>
                    <tr>
                        <td><code><?php echo $file; ?></code></td>
                        <td><?php echo $check['Description']; ?></td>
                        <td><span class="<?php echo str_replace(' ✓', '', str_replace(' ✗', '', $check['Exists']) === 'Yes' ? 'yes' : 'no'); ?>"><?php echo $check['Exists']; ?></span></td>
                        <td><span class="<?php echo str_replace(' ✓', '', str_replace(' ✗', '', $check['Readable']) === 'Yes' ? 'yes' : 'no'); ?>"><?php echo $check['Readable']; ?></span></td>
                        <td><?php echo $check['Size']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <div class="status-box status-warning" style="margin-top: 30px;">
            <strong>Next Steps:</strong>
            <ol style="margin-left: 20px; margin-top: 10px;">
                <li>Review the tables above — look for any "✗ No" entries</li>
                <li>If folders are missing: Use FTP to upload them from your local copy</li>
                <li>If files are missing: Re-upload the entire ManageMo folder</li>
                <li>Ensure the folder structure exactly matches your local version</li>
                <li>Refresh this page after uploading to verify</li>
                <li><strong>Delete this file (debug.php) when everything shows ✓</strong></li>
            </ol>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <title>ManageMo - Debug Info</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .info-box { background: white; padding: 15px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
        .label { font-weight: bold; color: #0066cc; }
        .value { color: #333; font-family: monospace; }
        .success { color: green; }
        .error { color: red; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔧 ManageMo Debug Information</h1>
    
    <div class="info-box">
        <h2>Server Information</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
            </tr>
            <tr>
                <td class="label">SERVER_NAME</td>
                <td class="value"><?php echo $_SERVER['SERVER_NAME']; ?></td>
            </tr>
            <tr>
                <td class="label">SCRIPT_NAME</td>
                <td class="value"><?php echo $_SERVER['SCRIPT_NAME']; ?></td>
            </tr>
            <tr>
                <td class="label">SCRIPT_FILENAME</td>
                <td class="value"><?php echo $_SERVER['SCRIPT_FILENAME']; ?></td>
            </tr>
            <tr>
                <td class="label">DOCUMENT_ROOT</td>
                <td class="value"><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td>
            </tr>
            <tr>
                <td class="label">REQUEST_URI</td>
                <td class="value"><?php echo $_SERVER['REQUEST_URI']; ?></td>
            </tr>
        </table>
    </div>

    <div class="info-box">
        <h2>BASE_URL Detection</h2>
        <table>
            <tr>
                <th>Property</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td class="label">BASE_URL constant</td>
                <td class="value"><?php echo BASE_URL; ?></td>
                <td class="<?php echo (BASE_URL !== '' ? 'success' : 'error'); ?>">
                    <?php echo (BASE_URL !== '' ? '✓ Defined' : '✗ Empty'); ?>
                </td>
            </tr>
            <tr>
                <td class="label">dirname(SCRIPT_NAME)</td>
                <td class="value"><?php echo dirname($_SERVER['SCRIPT_NAME']); ?></td>
                <td>Reference</td>
            </tr>
            <tr>
                <td class="label">Expected BASE_URL</td>
                <td class="value">
                    <?php
                    $expected = '/';
                    if (strpos($_SERVER['HTTP_HOST'], 'managemo.ct.ws') !== false) {
                        $expected = '/';
                        echo $expected . ' <em style="color: #666;">(InfinityFree subdomain)</em>';
                    } elseif (strpos($_SERVER['REQUEST_URI'], '/managemo/') !== false) {
                        $expected = '/managemo/';
                        echo $expected . ' <em style="color: #666;">(Subdirectory)</em>';
                    } else {
                        echo $expected . ' <em style="color: #666;">(Root domain)</em>';
                    }
                    ?>
                </td>
                <td class="<?php echo (BASE_URL === $expected ? 'success' : 'error'); ?>">
                    <?php echo (BASE_URL === $expected ? '✓ Correct' : '⚠ May be wrong'); ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-box">
        <h2>Resource Paths</h2>
        <p>These are the paths your resources will be loaded from:</p>
        <table>
            <tr>
                <th>Resource</th>
                <th>URL Path</th>
                <th>Full URL</th>
            </tr>
            <tr>
                <td>CSS File</td>
                <td class="value"><?php echo BASE_URL; ?>css/style.css</td>
                <td><small><?php echo 'https://' . $_SERVER['SERVER_NAME'] . BASE_URL . 'css/style.css'; ?></small></td>
            </tr>
            <tr>
                <td>JavaScript File</td>
                <td class="value"><?php echo BASE_URL; ?>js/script.js</td>
                <td><small><?php echo 'https://' . $_SERVER['SERVER_NAME'] . BASE_URL . 'js/script.js'; ?></small></td>
            </tr>
            <tr>
                <td>Logo Image</td>
                <td class="value"><?php echo BASE_URL; ?>assets/pics/logo.png</td>
                <td><small><?php echo 'https://' . $_SERVER['SERVER_NAME'] . BASE_URL . 'assets/pics/logo.png'; ?></small></td>
            </tr>
        </table>
    </div>

    <div class="info-box">
        <h2>File/Folder Existence Check</h2>
        <table>
            <tr>
                <th>Path</th>
                <th>Exists</th>
                <th>Status</th>
            </tr>
            <tr>
                <td class="value">/css/style.css</td>
                <td><?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ? 'success' : 'error'; ?>">
                    <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/css/style.css') ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td class="value">/js/script.js</td>
                <td><?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/js/script.js') ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/js/script.js') ? 'success' : 'error'; ?>">
                    <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/js/script.js') ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td class="value">/assets/pics/logo.png</td>
                <td><?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/pics/logo.png') ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/pics/logo.png') ? 'success' : 'error'; ?>">
                    <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/pics/logo.png') ? '✓' : '✗'; ?>
                </td>
            </tr>
            <tr>
                <td class="value">/config/constants.php</td>
                <td><?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/constants.php') ? 'Yes' : 'No'; ?></td>
                <td class="<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/constants.php') ? 'success' : 'error'; ?>">
                    <?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/config/constants.php') ? '✓' : '✗'; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
        <h2>⚠️ Troubleshooting InfinityFree Issues</h2>
        <p>If CSS/JS/images are returning 404 from <code>errors.infinityfree.net</code>:</p>
        <ol>
            <li><strong>Check BASE_URL above</strong> — Should be <code>/</code> for managemo.ct.ws</li>
            <li><strong>Verify all files uploaded:</strong>
                <ul>
                    <li>Open browser DevTools (F12) → Network tab</li>
                    <li>Refresh the page</li>
                    <li>Look for red 404 entries</li>
                    <li>Check the exact path it's trying to load from (should start with /css/, /js/, /assets/)</li>
                </ul>
            </li>
            <li><strong>Common solutions:</strong>
                <ul>
                    <li>Re-upload entire folder structure using FTP</li>
                    <li>Ensure file names are lowercase (style.css not Style.css)</li>
                    <li>Check that subfolders aren't missing (css/, js/, assets/)</li>
                </ul>
            </li>
            <li><strong>If BASE_URL is wrong:</strong>
                <ul>
                    <li>Create a file named <code>.htaccess</code> in your root with:</li>
                    <li><code style="display: block; margin: 10px 0; padding: 10px; background: #f0f0f0;">SetEnv MANAGEMO_BASE_URL "/"</code></li>
                    <li>Upload it and refresh debug.php</li>
                </ul>
            </li>
        </ol>
    </div>

    <hr style="margin-top: 30px;">
    <p style="color: #666; font-size: 12px;">
        This debug page can be safely deleted. View the page source or check the console for more details.
    </p>
</body>
</html>
