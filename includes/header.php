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
