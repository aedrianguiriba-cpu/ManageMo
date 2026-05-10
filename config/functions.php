<?php
require_once 'data.php';
require_once 'constants.php';

// Session management
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

// Get current user
function getCurrentUser() {
    startSession();
    
    if (!isLoggedIn()) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $users = getUsers();
    return findById($users, $user_id);
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

// Check user role
function checkRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!checkRole(ROLE_ADMIN)) {
        header('Location: ' . BASE_URL . 'user/dashboard.php');
        exit;
    }
}

// Redirect if not user
function requireUser() {
    requireLogin();
    if (!checkRole(ROLE_USER)) {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
        exit;
    }
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

// Generate unique QR code ID
function generateQRCodeId() {
    return 'QR-' . strtoupper(uniqid(sprintf("%08x", mt_rand())));
}

// Generate per-unit QR code IDs for an inventory item.
// Returns an array where each element is a unique QR code for one physical unit.
// e.g. item with qr_code_id='QR-ABC' and quantity=3 → ['QR-ABC-U01','QR-ABC-U02','QR-ABC-U03']
function getItemUnitQRCodes($item) {
    $base = $item['qr_code_id'];
    $qty  = max(1, (int)($item['quantity'] ?? 1));
    $units = [];
    for ($i = 1; $i <= $qty; $i++) {
        $units[] = $base . '-U' . str_pad($i, 2, '0', STR_PAD_LEFT);
    }
    return $units;
}

// Generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Redirect with message
function redirectWithMessage($url, $message, $type = 'info') {
    startSession();
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header('Location: ' . $url);
    exit;
}

// Display message
function displayMessage() {
    startSession();
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

// Log activity
function logActivity($user_id, $action, $description, $table = null, $record_id = null) {
    // Activity logging is disabled in hardcoded data mode
    return true;
}

// Send delivery notification email to the user
function sendDeliveryEmail($to_email, $to_name, $request_number, $stage = 'out_for_delivery') {
    if ($stage === 'out_for_delivery') {
        $subject = '[ManageMo] Your Request is Out for Delivery – ' . $request_number;
        $body    = "Dear " . $to_name . ",\n\n"
                 . "Your request (" . $request_number . ") has been dispatched and is now OUT FOR DELIVERY.\n"
                 . "Please be available to receive the item(s) at your registered location.\n\n"
                 . "If you have any questions, please contact the property custodian.\n\n"
                 . "– ManageMo System, Pampanga State University";
    } elseif ($stage === 'pickup_ready') {
        $subject = '[ManageMo] Your Request is Ready for Pickup – ' . $request_number;
        $body    = "Dear " . $to_name . ",\n\n"
                 . "Your request (" . $request_number . ") is now READY FOR PICKUP.\n"
                 . "Please visit the property office to claim your item(s).\n\n"
                 . "– ManageMo System, Pampanga State University";
    } else {
        return false;
    }
    $headers = "From: no-reply@psu.edu.ph\r\nX-Mailer: ManageMo";
    return mail($to_email, $subject, $body, $headers);
}

// Format date
function formatDate($date, $format = 'M d, Y H:i') {
    return date($format, strtotime($date));
}

// Get campus by ID
function getCampus($campus_id) {
    $campuses = getCampuses();
    return findById($campuses, $campus_id);
}

// Get all campuses
function getAllCampuses() {
    return getCampuses();
}

// Get inventory count for campus
function getInventoryCount($campus_id) {
    $inventory = getInventory();
    $items = filterByColumn($inventory, 'campus_id', $campus_id);
    return count($items);
}

// Get pending requests count
function getPendingRequestsCount() {
    $requests = getRequests();
    $pending = filterByColumn($requests, 'status', REQUEST_STATUS_PENDING);
    return count($pending);
}
?>
