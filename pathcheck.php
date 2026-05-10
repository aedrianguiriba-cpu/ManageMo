<?php
/**
 * ManageMo - Path Detection Debugger
 * 
 * This shows exactly what the folder detection is seeing
 * Visit: http://localhost/managemo/pathcheck.php
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Path Detection Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .box { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #0066cc; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 3px; margin: 5px 0; overflow-x: auto; }
        .success { border-left-color: green; }
        .error { border-left-color: red; }
        strong { color: #0066cc; }
    </style>
</head>
<body>
    <h1>🔍 Path Detection Debug (New Method)</h1>";

// Show raw server variables
echo "<div class='box'>
    <strong>Raw Server Variables:</strong>
    <div class='code'>
        \$_SERVER['SERVER_NAME'] = " . htmlspecialchars($_SERVER['SERVER_NAME']) . "<br>
        \$_SERVER['DOCUMENT_ROOT'] = " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . "<br>
        __FILE__ = " . htmlspecialchars(__FILE__) . "
    </div>
</div>";

// Show localhost detection
$is_localhost = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    $_SERVER['SERVER_NAME'] === '::1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
);

echo "<div class='box " . ($is_localhost ? 'success' : 'error') . "'>
    <strong>Localhost Detection:</strong>
    <div class='code'>
        Is Localhost? " . ($is_localhost ? 'YES ✓' : 'NO ✗') . "
    </div>
</div>";

// Show new path extraction method using realpath
if ($is_localhost) {
    $file_path = realpath(__FILE__);
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    
    // Normalize paths
    $file_path_norm = str_replace('\\', '/', $file_path);
    $doc_root_norm = str_replace('\\', '/', $doc_root);
    $doc_root_norm = rtrim($doc_root_norm, '/');
    
    echo "<div class='box'>
        <strong>Path Detection (Using realpath with normalization):</strong>
        <div class='code'>
            Original __FILE__ = " . htmlspecialchars($file_path) . "<br>
            Normalized = " . htmlspecialchars($file_path_norm) . "<br>
            <br>
            Original DOCUMENT_ROOT = " . htmlspecialchars($doc_root) . "<br>
            Normalized = " . htmlspecialchars($doc_root_norm) . "
        </div>
    </div>";
    
    if (strpos($file_path_norm, $doc_root_norm) === 0) {
        $rel_path = substr($file_path_norm, strlen($doc_root_norm) + 1);
        $parts = explode('/', $rel_path);
        $folder = !empty($parts[0]) ? $parts[0] : '';
        
        echo "<div class='box' style='border-left-color: green;'>
            <strong>✓ Path Comparison Successful!</strong>
            <div class='code'>
                Relative path = " . htmlspecialchars($rel_path) . "<br>
                After explode('/') = Array(" . implode(", ", array_map('htmlspecialchars', $parts)) . ")<br>
                First folder = " . htmlspecialchars($folder) . "<br>
                Lowercase = " . htmlspecialchars(strtolower($folder)) . "<br>
                <br>
                <strong>Detected BASE_URL: /" . htmlspecialchars(strtolower($folder)) . "/</strong>
            </div>
        </div>";
    } else {
        echo "<div class='box error'>
            <strong>Error: Path comparison failed</strong>
            <div class='code'>
                File path doesn't start with DOCUMENT_ROOT<br>
                File: " . htmlspecialchars($file_path_norm) . "<br>
                Root: " . htmlspecialchars($doc_root_norm) . "
            </div>
        </div>";
    }
} else {
    echo "<div class='box error'>
        <strong>Not localhost - will use BASE_URL = /</strong>
    </div>";
}

// Now require constants and show result
require_once 'config/constants.php';

echo "<div class='box' style='background: #fff3cd; border: 2px solid #ff9800;'>
    <strong>🔍 DEBUG: Inside constants.php Detection Logic</strong>
    <div class='code'>";

if (defined('_DEBUG_FILE_PATH')) {
    echo "_DEBUG_FILE_PATH = " . htmlspecialchars(constant('_DEBUG_FILE_PATH')) . "<br>";
    echo "_DEBUG_DOC_ROOT = " . htmlspecialchars(constant('_DEBUG_DOC_ROOT')) . "<br>";
    echo "_DEBUG_STRLEN_DOC_ROOT = " . constant('_DEBUG_STRLEN_DOC_ROOT') . "<br>";
}

if (defined('_DEBUG_STRPOS_RESULT')) {
    echo "_DEBUG_STRPOS_RESULT = " . constant('_DEBUG_STRPOS_RESULT') . " (strpos check FAILED)<br>";
} else if (defined('_DEBUG_REL_PATH')) {
    echo "_DEBUG_REL_PATH = " . htmlspecialchars(constant('_DEBUG_REL_PATH')) . "<br>";
    echo "_DEBUG_PARTS_COUNT = " . constant('_DEBUG_PARTS_COUNT') . "<br>";
    echo "_DEBUG_FOLDER_NAME = " . constant('_DEBUG_FOLDER_NAME') . "<br>";
    echo "_DEBUG_BASE_URL_SET = " . (constant('_DEBUG_BASE_URL_SET') ? 'TRUE (folder detected!)' : 'FALSE (parts[0] was empty)') . "<br>";
}

if (defined('_DEBUG_ENV_OVERRIDE')) {
    echo "_DEBUG_ENV_OVERRIDE = " . constant('_DEBUG_ENV_OVERRIDE') . "<br>";
}

if (defined('_DEBUG_BASE_URL_BEFORE_DEFINE')) {
    echo "_DEBUG_BASE_URL_BEFORE_DEFINE = " . htmlspecialchars(constant('_DEBUG_BASE_URL_BEFORE_DEFINE')) . " <-- VALUE BEFORE define()<br>";
}

echo "    </div>
</div>";

echo "<div class='box' style='background: #f0f0f0;'>
    <strong>Environment Variables Check:</strong>
    <div class='code'>
        getenv('MANAGEMO_BASE_URL') = " . (getenv('MANAGEMO_BASE_URL') ? "'" . htmlspecialchars(getenv('MANAGEMO_BASE_URL')) . "'" : 'FALSE/EMPTY') . "<br>
        putenv('MANAGEMO_BASE_URL') = " . (function_exists('ini_get') ? 'function available' : 'N/A') . "
    </div>
</div>";

$expected = '/managemo/' ; // or '/ManageMo/' depending on folder name
$is_correct = (BASE_URL === '/managemo/' || BASE_URL === '/ManageMo/');

echo "<div class='box " . ($is_correct ? 'success' : 'error') . "'>
    <strong>Final BASE_URL Result:</strong>
    <div class='code'>
        BASE_URL = " . htmlspecialchars(BASE_URL) . "<br>
        " . ($is_correct ? '✓ CORRECT! (should contain folder name)' : '✗ WRONG - should be /managemo/ or /ManageMo/') . "
    </div>
</div>";

echo "<div class='box'>
    <strong>Test Links (these should work):</strong>
    <div class='code'>
        <a href='" . BASE_URL . "'>Home (" . BASE_URL . ")</a><br>
        <a href='" . BASE_URL . "index.php'>Login (" . BASE_URL . "index.php)</a><br>
        <a href='" . BASE_URL . "admin/dashboard.php'>Admin (" . BASE_URL . "admin/dashboard.php)</a>
    </div>
</div>";

echo "</body>
</html>";
?>

<html>
<head>
    <title>Path Detection Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        .box { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #0066cc; }
        .code { background: #f0f0f0; padding: 10px; border-radius: 3px; margin: 5px 0; }
        .success { border-left-color: green; }
        .error { border-left-color: red; }
        strong { color: #0066cc; }
    </style>
</head>
<body>
    <h1>🔍 Path Detection Debug</h1>";

// Show raw server variables
echo "<div class='box'>
    <strong>Raw Server Variables:</strong>
    <div class='code'>
        \$_SERVER['SERVER_NAME'] = " . htmlspecialchars($_SERVER['SERVER_NAME']) . "<br>
        \$_SERVER['SCRIPT_NAME'] = " . htmlspecialchars($_SERVER['SCRIPT_NAME']) . "<br>
        \$_SERVER['DOCUMENT_ROOT'] = " . htmlspecialchars($_SERVER['DOCUMENT_ROOT']) . "<br>
        \$_SERVER['REQUEST_URI'] = " . htmlspecialchars($_SERVER['REQUEST_URI']) . "
    </div>
</div>";

// Show localhost detection
$is_localhost = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    $_SERVER['SERVER_NAME'] === '::1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
);

echo "<div class='box " . ($is_localhost ? 'success' : 'error') . "'>
    <strong>Localhost Detection:</strong>
    <div class='code'>
        Is Localhost? " . ($is_localhost ? 'YES ✓' : 'NO ✗') . "
    </div>
</div>";

// Show path extraction
if ($is_localhost) {
    $script_name = $_SERVER['SCRIPT_NAME'];
    $parts = explode('/', trim($script_name, '/'));
    $folder = !empty($parts[0]) ? $parts[0] : '';
    
    echo "<div class='box'>
        <strong>Path Extraction (Localhost):</strong>
        <div class='code'>
            SCRIPT_NAME = " . htmlspecialchars($script_name) . "<br>
            After trim('/') = " . htmlspecialchars(trim($script_name, '/')) . "<br>
            After explode('/') = Array(" . implode(", ", array_map('htmlspecialchars', $parts)) . ")<br>
            First part = " . htmlspecialchars($folder) . "<br>
            <br>
            <strong>Extracted folder: /" . htmlspecialchars($folder) . "/</strong>
        </div>
    </div>";
} else {
    echo "<div class='box error'>
        <strong>Not localhost - will use BASE_URL = /</strong>
    </div>";
}

// Now require constants and show result
require_once 'config/constants.php';

echo "<div class='box " . (BASE_URL === '/managemo/' ? 'success' : 'error') . "'>
    <strong>Final BASE_URL Result:</strong>
    <div class='code'>
        BASE_URL = " . htmlspecialchars(BASE_URL) . "<br>
        " . (BASE_URL === '/managemo/' ? '✓ CORRECT!' : '✗ WRONG - Expected /managemo/') . "
    </div>
</div>";

echo "<div class='box'>
    <strong>Test Links:</strong>
    <div class='code'>
        <a href='" . BASE_URL . "'>Home</a><br>
        <a href='" . BASE_URL . "index.php'>Login</a><br>
        <a href='" . BASE_URL . "css/style.css'>CSS File</a><br>
        <a href='" . BASE_URL . "admin/dashboard.php'>Admin</a>
    </div>
</div>";

echo "</body>
</html>";
?>
