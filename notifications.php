<?php
/**
 * Handles notification bell actions (mark one/all as read) then bounces back
 * to wherever the user was — or to the notification's own link, if opening it.
 */
require_once __DIR__ . '/config/functions.php';

requireLogin();
$current_user = getCurrentUser();

$action   = $_GET['action'] ?? '';
$redirect = $_GET['redirect'] ?? (($current_user['role'] === ROLE_ADMIN) ? 'admin/dashboard.php' : 'user/dashboard.php');

if ($action === 'open') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) markNotificationRead($id, (int)$current_user['id']);
    $link = $_GET['link'] ?? '';
    header('Location: ' . ($link ?: $redirect));
    exit;
}

if ($action === 'mark_all_read') {
    markAllNotificationsRead((int)$current_user['id']);
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) deleteNotification($id, (int)$current_user['id']);
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete_all') {
    deleteAllNotifications((int)$current_user['id']);
    header('Location: ' . $redirect);
    exit;
}

header('Location: ' . $redirect);
exit;
