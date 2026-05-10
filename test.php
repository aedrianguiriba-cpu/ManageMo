<?php
/**
 * ManageMo - Quick Test for Localhost
 * 
 * Run this at: http://localhost/managemo/test.php
 * 
 * Should show:
 * ✓ BASE_URL = /managemo/
 * ✓ CSS loads from /managemo/css/style.css
 * ✓ Logo loads from /managemo/assets/pics/logo.png
 */

require_once 'config/constants.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ManageMo - Localhost Test</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <style>
        body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
        .test-box { background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #999; }
        .pass { border-left-color: green; background: #d4edda; }
        .fail { border-left-color: red; background: #f8d7da; }
        .info { background: #e7f3ff; border-left-color: #0066cc; }
        h1 { color: #333; }
        code { background: white; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🧪 ManageMo - Localhost Test</h1>

    <div class="test-box info">
        <strong>📍 Server Information:</strong><br>
        Server: <?php echo $_SERVER['SERVER_NAME']; ?><br>
        Path: <?php echo $_SERVER['SCRIPT_NAME']; ?><br>
        Base URL: <code><?php echo BASE_URL; ?></code>
    </div>

    <div class="test-box <?php echo (BASE_URL === '/managemo/' ? 'pass' : 'fail'); ?>">
        <strong><?php echo (BASE_URL === '/managemo/' ? '✓' : '✗'); ?> BASE_URL Detection</strong><br>
        Current: <code><?php echo BASE_URL; ?></code><br>
        Expected: <code>/managemo/</code><br>
        Status: <?php echo (BASE_URL === '/managemo/' ? 'PASS - Correct!' : 'FAIL - Check configuration'); ?>
    </div>

    <div class="test-box">
        <strong>📁 Resource Paths</strong><br>
        CSS: <code><?php echo BASE_URL; ?>css/style.css</code><br>
        JS: <code><?php echo BASE_URL; ?>js/script.js</code><br>
        Logo: <code><?php echo BASE_URL; ?>assets/pics/logo.png</code>
    </div>

    <div class="test-box">
        <strong>🔗 Navigation Links</strong><br>
        <a href="<?php echo BASE_URL; ?>">Home</a> | 
        <a href="<?php echo BASE_URL; ?>index.php">Login</a> | 
        <a href="<?php echo BASE_URL; ?>debug.php">Debug</a>
    </div>

    <div class="test-box info">
        <strong>✓ Next Steps:</strong><br>
        1. Click on the links above to test navigation<br>
        2. Check that CSS styling applies (fonts, colors)<br>
        3. Visit <a href="<?php echo BASE_URL; ?>index.php">login page</a> - logo should show<br>
        4. If everything works, delete this file (<code>test.php</code>)
    </div>
</body>
</html>
