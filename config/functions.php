<?php
require_once 'data.php';
require_once 'constants.php';
require_once 'smtp.php';

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

// Generate a group ID shared by all units added in the same batch
function generateGroupId() {
    return 'GRP-' . strtoupper(uniqid(sprintf("%06x", mt_rand())));
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

// Groups user-owned items. Uses group_id when present; falls back to item_name+category.
function groupOwnedItems(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = !empty($item['group_id'])
            ? 'gid:' . $item['group_id']
            : strtolower(trim($item['item_name'])) . '||' . strtolower(trim($item['category'] ?? ''));
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'group_id'    => $item['group_id'] ?? null,
                'item_name'   => $item['item_name'],
                'category'    => $item['category'] ?? '',
                'description' => $item['description'] ?? '',
                'units'       => [],
            ];
        }
        $groups[$key]['units'][] = $item;
    }
    return array_values($groups);
}

// Groups inventory items. Uses group_id when present; falls back to item_name+category+campus_id.
function groupInventoryItems(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = !empty($item['group_id'])
            ? 'gid:' . $item['group_id']
            : strtolower(trim($item['item_name'])) . '||' . strtolower(trim($item['category'] ?? '')) . '||' . (int)$item['campus_id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'group_id'    => $item['group_id'] ?? null,
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

// Send a status-change notification email to the requester via real SMTP.
// Returns true/false for success; never throws — a mail hiccup must never
// block the actual status-change action it's attached to.
function sendStatusEmail($to_email, $to_name, $request_number, $stage, array $extra = []) {
    $messages = [
        'approved' => [
            'subject'  => 'Your Request Has Been Approved',
            'headline' => 'Your request has been approved',
            'color'    => '#15803d', 'bg' => '#dcfce7', 'icon' => '✓',
            'detail'   => "We'll notify you again once it's "
                        . (!empty($extra['is_pickup']) ? 'ready for pickup' : 'out for delivery') . '.',
        ],
        'disapproved' => [
            'subject'  => 'Your Request Was Not Approved',
            'headline' => 'Your request was not approved',
            'color'    => '#b91c1c', 'bg' => '#fee2e2', 'icon' => '✕',
            'detail'   => (!empty($extra['reason']) ? 'Reason given: ' . $extra['reason'] . ' ' : '')
                        . 'If you have questions, please contact the property custodian\'s office.',
        ],
        'out_for_delivery' => [
            'subject'  => 'Your Request is Out for Delivery',
            'headline' => 'Your item is out for delivery',
            'color'    => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '🚚',
            'detail'   => (!empty($extra['scheduled_date']) ? 'Expected delivery date: ' . $extra['scheduled_date'] . '. ' : '')
                        . 'Please be available to receive the item(s) at your registered location.',
        ],
        'pickup_ready' => [
            'subject'  => 'Your Request is Ready for Pickup',
            'headline' => 'Your item is ready for pickup',
            'color'    => '#1d4ed8', 'bg' => '#dbeafe', 'icon' => '📦',
            'detail'   => (!empty($extra['scheduled_date']) ? 'Expected pickup date: ' . $extra['scheduled_date'] . '. ' : '')
                        . 'Please visit the property office to claim your item(s).',
        ],
        'delivered' => [
            'subject'  => 'Your Item Has Been Delivered',
            'headline' => 'Your item has been delivered',
            'color'    => '#15803d', 'bg' => '#dcfce7', 'icon' => '📬',
            'detail'   => 'Please check with the admin office if you have any concerns about the item(s) received.',
        ],
        'returned' => [
            'subject'  => 'Item Return Confirmed',
            'headline' => 'Thanks for returning your item(s)',
            'color'    => '#15803d', 'bg' => '#dcfce7', 'icon' => '↩',
            'detail'   => "We've recorded the item(s) for this request as returned.",
        ],
        'completed' => [
            'subject'  => 'Your Request Has Been Completed',
            'headline' => 'Your request has been completed',
            'color'    => '#15803d', 'bg' => '#dcfce7', 'icon' => '✓',
            'detail'   => 'This request has now been fully processed. Thank you.',
        ],
    ];

    if (!isset($messages[$stage])) return false;

    $mailer = SmtpMailer::fromEnv();
    if (!$mailer) return false; // SMTP not configured — fail silently, don't break the caller

    $m       = $messages[$stage];
    $subject = '[ManageMo] ' . $m['subject'] . ' – ' . $request_number;
    $text    = "Dear $to_name,\n\n" . $m['headline'] . " ($request_number).\n" . strip_tags($m['detail'])
             . "\n\n– ManageMo System, Pampanga State University";
    $html    = buildStatusEmailHtml($to_name, $request_number, $m);

    try {
        return $mailer->sendHtml($to_email, $to_name, $subject, $html, $text);
    } catch (\Throwable $e) {
        error_log('sendStatusEmail failed: ' . $e->getMessage());
        return false;
    }
}

// Renders the branded HTML shell used by sendStatusEmail(). Inline CSS only —
// email clients strip <style> blocks and external stylesheets.
function buildStatusEmailHtml($to_name, $request_number, array $m) {
    $safeName   = htmlspecialchars($to_name);
    $safeReqNum = htmlspecialchars($request_number);
    $safeHead   = htmlspecialchars($m['headline']);
    $safeDetail = htmlspecialchars($m['detail']);
    $color      = $m['color'];
    $bg         = $m['bg'];
    $icon       = $m['icon'];

    return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
  <tr>
    <td style="background:#8B0000;padding:22px 28px;">
      <span style="color:#ffffff;font-size:18px;font-weight:800;letter-spacing:0.3px;">ManageMo</span>
      <div style="color:rgba(255,255,255,0.75);font-size:12px;margin-top:2px;">Pampanga State University</div>
    </td>
  </tr>
  <tr>
    <td style="padding:32px 28px 8px;">
      <div style="display:inline-block;width:52px;height:52px;line-height:52px;text-align:center;border-radius:50%;background:{$bg};color:{$color};font-size:24px;margin-bottom:18px;">{$icon}</div>
      <div style="font-size:20px;font-weight:800;color:#1a1d23;margin-bottom:6px;">Dear {$safeName},</div>
      <div style="font-size:17px;font-weight:700;color:{$color};margin-bottom:14px;">{$safeHead}</div>
      <div style="display:inline-block;font-family:monospace;font-size:13px;font-weight:700;color:#8B0000;background:rgba(139,0,0,0.08);border-radius:6px;padding:4px 10px;margin-bottom:16px;">{$safeReqNum}</div>
      <p style="font-size:14.5px;line-height:1.6;color:#374151;margin:0 0 8px;">{$safeDetail}</p>
    </td>
  </tr>
  <tr>
    <td style="padding:20px 28px 28px;">
      <div style="border-top:1px solid #e5e7eb;padding-top:16px;font-size:12px;color:#9ca3af;line-height:1.6;">
        This is an automated message from the ManageMo inventory system. Please do not reply directly to this email — for questions, contact the property custodian's office.
      </div>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// Kept for any old call sites — delegates to sendStatusEmail.
function sendDeliveryEmail($to_email, $to_name, $request_number, $stage = 'out_for_delivery') {
    return sendStatusEmail($to_email, $to_name, $request_number, $stage);
}

// ── Password Reset Email ────────────────────────────────────────────────────
function _sendPasswordResetEmail(string $to_name, string $to_email, string $reset_url): bool {
    $mailer = SmtpMailer::fromEnv();
    if (!$mailer) return false;

    $safeName = htmlspecialchars($to_name);
    $safeUrl  = htmlspecialchars($reset_url);
    $subject  = '[ManageMo] Password Reset Request';

    $text = "Dear $to_name,\n\nYou requested a password reset for your ManageMo account.\n\n"
          . "Click the link below to set a new password (valid for 1 hour):\n$reset_url\n\n"
          . "If you did not request this, you can safely ignore this email — your password will not change.\n\n"
          . "– ManageMo System, Pampanga State University";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
  <tr>
    <td style="background:#8B0000;padding:22px 28px;">
      <span style="color:#ffffff;font-size:18px;font-weight:800;letter-spacing:0.3px;">ManageMo</span>
      <div style="color:rgba(255,255,255,0.75);font-size:12px;margin-top:2px;">Pampanga State University</div>
    </td>
  </tr>
  <tr>
    <td style="padding:32px 28px 8px;">
      <div style="display:inline-block;width:52px;height:52px;line-height:52px;text-align:center;border-radius:50%;background:#fee2e2;color:#8B0000;font-size:24px;margin-bottom:18px;">🔑</div>
      <div style="font-size:20px;font-weight:800;color:#1a1d23;margin-bottom:6px;">Dear {$safeName},</div>
      <div style="font-size:17px;font-weight:700;color:#8B0000;margin-bottom:14px;">Password Reset Request</div>
      <p style="font-size:14.5px;line-height:1.6;color:#374151;margin:0 0 20px;">
        We received a request to reset the password for your ManageMo account.
        Click the button below to choose a new password. This link is valid for <strong>1 hour</strong>.
      </p>
      <table role="presentation" cellpadding="0" cellspacing="0">
        <tr>
          <td style="border-radius:8px;background:#8B0000;">
            <a href="{$safeUrl}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;letter-spacing:0.3px;">
              Reset My Password
            </a>
          </td>
        </tr>
      </table>
      <p style="font-size:12.5px;line-height:1.6;color:#9ca3af;margin:20px 0 0;">
        If the button doesn't work, copy and paste this URL into your browser:<br>
        <span style="word-break:break-all;color:#8B0000;">{$safeUrl}</span>
      </p>
    </td>
  </tr>
  <tr>
    <td style="padding:20px 28px 28px;">
      <div style="border-top:1px solid #e5e7eb;padding-top:16px;font-size:12px;color:#9ca3af;line-height:1.6;">
        If you did not request a password reset, you can safely ignore this email — your password will not change.
        <br>This is an automated message from ManageMo. Do not reply to this email.
      </div>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;

    try {
        return $mailer->sendHtml($to_email, $to_name, $subject, $html, $text);
    } catch (\Throwable $e) {
        error_log('_sendPasswordResetEmail failed: ' . $e->getMessage());
        return false;
    }
}

// ── In-app notifications ────────────────────────────────────────────────────

function dbCreateNotification(array $data): ?array {
    $rows = supabase()->insert('notifications', $data);
    return $rows[0] ?? null;
}

/** Create a bell notification for one user. Never throws — a notification
 *  hiccup must never block the action that triggered it. */
function notifyUser(int $user_id, string $title, string $message = '', string $type = 'info', ?string $link = null): void {
    try {
        dbCreateNotification([
            'user_id' => $user_id,
            'title'   => $title,
            'message' => $message,
            'type'    => in_array($type, ['info', 'success', 'warning', 'danger']) ? $type : 'info',
            'link'    => $link,
        ]);
    } catch (\Throwable $e) {
        error_log('notifyUser failed: ' . $e->getMessage());
    }
}

/** Notify every admin account at once (e.g. "new request submitted"). */
function notifyAdmins(string $title, string $message = '', string $type = 'info', ?string $link = null): void {
    foreach (getUsers() as $u) {
        if ($u['role'] === ROLE_ADMIN && !empty($u['is_active'])) {
            notifyUser((int)$u['id'], $title, $message, $type, $link);
        }
    }
}

function markNotificationRead(int $id, int $user_id): bool {
    // Scoped to user_id so one user can't mark another's notification read via a guessed id.
    $rows = supabase()->update('notifications', "id=eq.$id&user_id=eq.$user_id", ['is_read' => true]);
    return !empty($rows);
}

function markAllNotificationsRead(int $user_id): bool {
    $rows = supabase()->update('notifications', "user_id=eq.$user_id&is_read=eq.false", ['is_read' => true]);
    return $rows !== [];
}

// Relative "time ago" label for notification timestamps.
function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
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

function dbDeleteUser(int $id): bool {
    $rows = supabase()->deleteById('users', $id);
    clearDataCache('users');
    return $rows !== [];
}

function dbCreateRequestItem(array $data): ?array {
    $rows = supabase()->insert('request_items', $data);
    return $rows[0] ?? null;
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
