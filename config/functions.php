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

// Returns QR code(s) for an inventory item.
// Items with quantity=1 (the new per-unit model) return their own QR code directly.
// Legacy items with quantity>1 still get derived per-unit suffixes for backward compat.
function getItemUnitQRCodes($item) {
    $base = $item['qr_code_id'];
    $qty  = max(1, (int)($item['quantity'] ?? 1));
    if ($qty === 1) return [$base];
    $units = [];
    for ($i = 1; $i <= $qty; $i++) {
        $units[] = $base . '-U' . str_pad($i, 2, '0', STR_PAD_LEFT);
    }
    return $units;
}

// Groups user-owned items by user_id + item_name + category.
function groupOwnedItems(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = (int)$item['user_id']
             . '||' . strtolower(trim($item['item_name']))
             . '||' . strtolower(trim($item['category'] ?? ''));
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'user_id'     => (int)$item['user_id'],
                'item_name'   => $item['item_name'],
                'category'    => $item['category'] ?? '',
                'campus_id'   => (int)$item['campus_id'],
                'year_owned'  => $item['year_owned'],
                'description' => $item['description'] ?? '',
                'notes'       => $item['notes'] ?? '',
                'created_at'  => $item['created_at'] ?? '',
                'units'       => [],
            ];
        }
        $groups[$key]['units'][] = $item;
    }
    return array_values($groups);
}

// Groups a flat array of inventory items by item_name + category + campus_id.
// Each group has a 'units' key containing the individual item rows.
function groupInventoryItems(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = strtolower(trim($item['item_name']))
             . '||' . strtolower(trim($item['category'] ?? ''))
             . '||' . (int)$item['campus_id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'item_name'   => $item['item_name'],
                'category'    => $item['category'] ?? '',
                'campus_id'   => (int)$item['campus_id'],
                'location'    => $item['location'] ?? '',
                'description' => $item['description'] ?? '',
                'cost'        => $item['cost'],
                'created_at'  => $item['created_at'] ?? '',
                'units'       => [],
            ];
        }
        $groups[$key]['units'][] = $item;
    }
    return array_values($groups);
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

// ── Database mutation helpers ─────────────────────────────────────────────────

function dbNextRequestNumber(): string {
    $rows = supabase()->select('requests', 'select=request_number&order=id.desc&limit=1');
    if (empty($rows)) return 'REQ-00001';
    preg_match('/REQ-(\d+)$/', $rows[0]['request_number'] ?? '', $m);
    $next = isset($m[1]) ? (int)$m[1] + 1 : 1;
    return 'REQ-' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

function dbCreateRequest(array $data): array {
    $db   = supabase();
    $rows = $db->insert('requests', $data);
    if (empty($rows)) {
        return ['success' => false, 'error' => $db->lastError ?: 'Insert returned no data'];
    }
    clearDataCache('requests');
    return ['success' => true, 'row' => $rows[0]];
}

function dbUpdateRequest(int $id, array $data): bool {
    $data['updated_at'] = date('Y-m-d H:i:s');
    $rows = supabase()->updateById('requests', $id, $data);
    clearDataCache('requests');
    return !empty($rows);
}

function dbCreateInventory(array $data): ?array {
    $rows = supabase()->insert('inventory', $data);
    clearDataCache('inventory');
    return $rows[0] ?? null;
}

function dbUpdateInventory(int $id, array $data): bool {
    $rows = supabase()->updateById('inventory', $id, $data);
    clearDataCache('inventory');
    return !empty($rows);
}

function dbDeleteInventory(int $id): bool {
    $rows = supabase()->deleteById('inventory', $id);
    clearDataCache('inventory');
    return $rows !== [];
}

function dbCreateUser(array $data): ?array {
    $rows = supabase()->insert('users', $data);
    clearDataCache('users');
    return $rows[0] ?? null;
}

function dbUpdateUser(int $id, array $data): bool {
    $data['updated_at'] = date('Y-m-d H:i:s');
    $rows = supabase()->updateById('users', $id, $data);
    clearDataCache('users');
    return !empty($rows);
}

function dbCreateBorrowRecord(array $data): ?array {
    $rows = supabase()->insert('borrow_records', $data);
    clearDataCache('borrow_records');
    return $rows[0] ?? null;
}

function dbCreateUserOwnedItem(array $data): ?array {
    $rows = supabase()->insert('user_owned_items', $data);
    clearDataCache('user_owned_items');
    return $rows[0] ?? null;
}

function dbUpdateUserOwnedItem(int $id, array $data): bool {
    $rows = supabase()->updateById('user_owned_items', $id, $data);
    clearDataCache('user_owned_items');
    return !empty($rows);
}

function dbAddCustomDepartment(string $type, array $data): bool {
    if ($type === 'campus') {
        $rows = supabase()->insert('campuses', [
            'name'        => $data['name'],
            'location'    => $data['location'] ?? null,
            'description' => $data['description'] ?? null,
            'is_default'  => false,
        ]);
        clearDataCache('campuses');
    } else {
        $rows = supabase()->insert('departments', [
            'type'         => $type,
            'abbreviation' => $data['abbreviation'],
            'full_name'    => $data['full_name'],
            'is_default'   => false,
        ]);
        clearDataCache('departments_colleges', 'departments_offices');
    }
    return !empty($rows);
}

function dbDeleteCustomDepartment(string $type, string $abbreviation): bool {
    $rows = supabase()->select('departments', 'type=eq.' . $type . '&abbreviation=eq.' . urlencode($abbreviation));
    if (empty($rows) || $rows[0]['is_default']) return false;
    supabase()->delete('departments', 'type=eq.' . $type . '&abbreviation=eq.' . urlencode($abbreviation));
    clearDataCache('departments_colleges', 'departments_offices');
    return true;
}

function dbDeleteCustomCampus(int $id): bool {
    $campus = supabase()->find('campuses', $id);
    if (!$campus || $campus['is_default']) return false;
    supabase()->deleteById('campuses', $id);
    clearDataCache('campuses');
    return true;
}
?>
