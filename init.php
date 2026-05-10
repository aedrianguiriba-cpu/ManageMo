<?php
/**
 * ManageMo - Central Initialization File
 * 
 * Include this file at the very top of any PHP file to access:
 * - BASE_URL constant
 * - All functions from config/functions.php
 * - All constants
 * 
 * Usage in any file:
 *   require_once __DIR__ . '/init.php';
 * 
 * Then use BASE_URL anywhere:
 *   <img src="<?php echo BASE_URL; ?>assets/pics/logo.png">
 *   <link href="<?php echo BASE_URL; ?>css/style.css">
 *   header('Location: ' . BASE_URL . 'admin/dashboard.php');
 */

// Prevent multiple includes
if (defined('MANAGEMO_INITIALIZED')) {
    return;
}
define('MANAGEMO_INITIALIZED', true);

// Get the base directory (where init.php is located)
$app_root = dirname(__FILE__);

// Require configuration in proper order
require_once $app_root . '/config/constants.php';
require_once $app_root . '/config/functions.php';

?>
