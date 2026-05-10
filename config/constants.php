<?php
// Base URL Configuration
// Robust detection for localhost and production

$base_url = '/';

// Determine if running on localhost
$is_localhost = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    $_SERVER['SERVER_NAME'] === '::1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false
);

if ($is_localhost) {
    // Get the real path of the current file
    $file_path = realpath(__FILE__); // e.g., C:\xampp\htdocs\ManageMo\config\constants.php
    $doc_root = $_SERVER['DOCUMENT_ROOT']; // e.g., C:/xampp/htdocs
    
    // Normalize both paths to use forward slashes for comparison
    $file_path_normalized = str_replace('\\', '/', $file_path);
    $doc_root_normalized = str_replace('\\', '/', $doc_root);
    
    // Ensure doc_root doesn't have trailing slash
    $doc_root_normalized = rtrim($doc_root_normalized, '/');
    
    // Store for debugging
    define('_DEBUG_FILE_PATH', $file_path_normalized);
    define('_DEBUG_DOC_ROOT', $doc_root_normalized);
    define('_DEBUG_STRLEN_DOC_ROOT', strlen($doc_root_normalized));
    
    // Check if file path starts with document root
    if (strpos($file_path_normalized, $doc_root_normalized) === 0) {
        // Extract the relative path from document root
        $rel_path = substr($file_path_normalized, strlen($doc_root_normalized) + 1); // +1 to skip the slash
        
        // Get the first folder name (e.g., "ManageMo")
        $parts = explode('/', $rel_path);
        if (!empty($parts[0])) {
            // Use lowercase for consistency
            $folder_name = strtolower($parts[0]);
            $base_url = '/' . $folder_name . '/';
            define('_DEBUG_BASE_URL_SET', true);
            define('_DEBUG_FOLDER_NAME', $folder_name);
        } else {
            define('_DEBUG_BASE_URL_SET', false);
            define('_DEBUG_FOLDER_NAME', 'EMPTY');
        }
        define('_DEBUG_REL_PATH', $rel_path);
        define('_DEBUG_PARTS_COUNT', count($parts));
    } else {
        define('_DEBUG_BASE_URL_SET', false);
        define('_DEBUG_STRPOS_RESULT', 'FAILED');
    }
}

// CUSTOM OVERRIDE via environment variable
// Only apply on production - dont override localhost auto-detection
if (!$is_localhost && !empty(getenv('MANAGEMO_BASE_URL'))) {
    $base_url = getenv('MANAGEMO_BASE_URL');
    define('_DEBUG_ENV_OVERRIDE', 'YES (production) - ' . getenv('MANAGEMO_BASE_URL'));
} else {
    define('_DEBUG_ENV_OVERRIDE', 'NO (using auto-detection for localhost or no env var)');
}

define('_DEBUG_BASE_URL_BEFORE_DEFINE', $base_url);
define('BASE_URL', $base_url);

// Application Constants
define('APP_NAME', 'ManageMo');
define('UNIVERSITY_NAME', 'Pampanga State University');
define('UNIVERSITY_SHORT', 'PSU');
define('APP_VERSION', '1.0.0');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Request Types
define('REQUEST_TYPE_ITEM', 'item');
define('REQUEST_TYPE_BORROW', 'borrow');
define('REQUEST_TYPE_SERVICE', 'service');

// Request Status
define('REQUEST_STATUS_PENDING', 'pending');
define('REQUEST_STATUS_APPROVED', 'approved');
define('REQUEST_STATUS_DISAPPROVED', 'disapproved');
define('REQUEST_STATUS_DELIVERED', 'delivered');
define('REQUEST_STATUS_RETURNED', 'returned');

// Urgency Levels
define('URGENCY_LOW', 'low');
define('URGENCY_MEDIUM', 'medium');
define('URGENCY_HIGH', 'high');
define('URGENCY_CRITICAL', 'critical');

// Pagination
define('ITEMS_PER_PAGE', 15);

// Upload Settings
define('UPLOAD_DIR', 'assets/uploads/');
define('QRCODE_DIR', 'assets/qrcodes/');
define('MAX_FILE_SIZE', 5242880); // 5MB
?>
