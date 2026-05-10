<?php
require_once 'config/functions.php';
startSession();
session_destroy();
header('Location: index.php');
exit;
?>
