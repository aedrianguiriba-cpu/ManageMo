<?php
require_once dirname(__DIR__) . '/config/functions.php';
startSession();
$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>ManageMo - PSU Asset Management</title>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/pics/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/css/style.css'); ?>">
</head>
<body>
<!-- Skeleton loader — shown until the page/assets finish loading (see footer.php) -->
<div id="skeletonLoader">
    <div class="sk-sidebar">
        <div class="sk-block sk-line" style="width:60%;height:22px;margin-bottom:24px;"></div>
        <div class="sk-block sk-line" style="width:90%;"></div>
        <div class="sk-block sk-line" style="width:80%;"></div>
        <div class="sk-block sk-line" style="width:85%;"></div>
        <div class="sk-block sk-line" style="width:70%;"></div>
        <div class="sk-block sk-line" style="width:88%;"></div>
        <div class="sk-block sk-line" style="width:75%;"></div>
    </div>
    <div class="sk-main">
        <div class="sk-topbar">
            <div class="sk-block sk-line" style="width:160px;height:18px;margin:0;"></div>
        </div>
        <div class="sk-block sk-line" style="width:220px;height:22px;margin-bottom:20px;"></div>
        <div class="sk-kpi-row">
            <div class="sk-block sk-card"></div>
            <div class="sk-block sk-card"></div>
            <div class="sk-block sk-card"></div>
            <div class="sk-block sk-card"></div>
        </div>
        <div class="sk-grid-row">
            <div class="sk-block" style="height:220px;"></div>
            <div class="sk-block" style="height:220px;"></div>
            <div class="sk-block" style="height:220px;"></div>
        </div>
    </div>
</div>
