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
                'user_id'     => $item['user_id'] ?? null,
                'campus_id'   => $item['campus_id'] ?? null,
                'college_id'  => $item['college_id'] ?? null,
                'year_owned'  => $item['year_owned'] ?? null,
                'units'       => [],
            ];
        }
        $groups[$key]['units'][] = $item;
    }
    return array_values($groups);
}

// Groups inventory items. Uses group_id when present; falls back to item_name+category+college_id
// (campus_id is no longer relied on for inventory — it's kept only to satisfy the NOT NULL column).
function groupInventoryItems(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = !empty($item['group_id'])
            ? 'gid:' . $item['group_id']
            : strtolower(trim($item['item_name'])) . '||' . strtolower(trim($item['category'] ?? '')) . '||' . ($item['college_id'] ?? '');
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'group_id'    => $item['group_id'] ?? null,
                'item_name'   => $item['item_name'],
                'category'    => $item['category'] ?? '',
                'campus_id'   => (int)($item['campus_id'] ?? 1),
                'college_id'  => $item['college_id'] ?? null,
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

// Shared label/color for an inventory item's acquisition_mode ('borrow','request','both'),
// used to render a consistent badge wherever items are listed (admin inventory cards,
// user request catalogs).
function acquisitionModeBadge(?string $mode): array {
    switch ($mode) {
        case 'request': return ['label' => 'Acquire Only',  'bg' => 'rgba(34,197,94,0.12)',  'fg' => '#15803d'];
        case 'both':    return ['label' => 'Borrow & Acquire', 'bg' => 'rgba(139,92,246,0.12)', 'fg' => '#6d28d9'];
        default:        return ['label' => 'Borrowable',    'bg' => 'rgba(59,130,246,0.12)', 'fg' => '#1d4ed8'];
    }
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
    $items   = $extra['items'] ?? [];
    $text    = "Dear $to_name,\n\n" . $m['headline'] . " ($request_number).\n" . strip_tags($m['detail']);
    if ($items) {
        $text .= "\n\nItems:\n";
        foreach ($items as $it) {
            $text .= '- ' . ($it['name'] ?? 'Item') . ' (Qty: ' . ($it['qty'] ?? 1) . ")\n";
        }
    }
    $text   .= "\n\n– ManageMo System, Pampanga State University";
    $html    = buildStatusEmailHtml($to_name, $request_number, $m, $extra);

    try {
        return $mailer->sendHtml($to_email, $to_name, $subject, $html, $text);
    } catch (\Throwable $e) {
        error_log('sendStatusEmail failed: ' . $e->getMessage());
        return false;
    }
}

// Renders the branded HTML shell used by sendStatusEmail(). Inline CSS only —
// email clients strip <style> blocks and external stylesheets.
function buildStatusEmailHtml($to_name, $request_number, array $m, array $extra = []) {
    $safeName   = htmlspecialchars($to_name);
    $safeReqNum = htmlspecialchars($request_number);
    $safeHead   = htmlspecialchars($m['headline']);
    $safeDetail = htmlspecialchars($m['detail']);
    $color      = $m['color'];
    $bg         = $m['bg'];
    $icon       = $m['icon'];

    // ── Request summary strip — always shown, so every email carries the basics
    // (request #, when, method) even without an itemized list.
    $summary_rows = '';
    $summary_rows .= '<tr>'
        . '<td style="padding:7px 0;font-size:12.5px;color:#6b7280;width:140px;">Reference No.</td>'
        . '<td style="padding:7px 0;font-size:13px;color:#1a1d23;font-weight:700;font-family:Consolas,Menlo,monospace;">' . $safeReqNum . '</td>'
        . '</tr>';
    if (!empty($extra['receiving_method'])) {
        $summary_rows .= '<tr>'
            . '<td style="padding:7px 0;font-size:12.5px;color:#6b7280;">Receiving Method</td>'
            . '<td style="padding:7px 0;font-size:13px;color:#1a1d23;font-weight:600;">' . htmlspecialchars(ucfirst($extra['receiving_method'])) . '</td>'
            . '</tr>';
    }
    if (!empty($extra['scheduled_date'])) {
        $summary_rows .= '<tr>'
            . '<td style="padding:7px 0;font-size:12.5px;color:#6b7280;">Scheduled Date</td>'
            . '<td style="padding:7px 0;font-size:13px;color:#1a1d23;font-weight:600;">' . htmlspecialchars($extra['scheduled_date']) . '</td>'
            . '</tr>';
    }
    $summary_block = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 4px;background:#f9fafb;border:1px solid #eef0f3;border-radius:10px;">'
        . '<tr><td style="padding:14px 18px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $summary_rows . '</table></td></tr>'
        . '</table>';

    // ── Itemized list — populated for stages that pass $extra['items'] (e.g.
    // out for delivery), so the recipient sees exactly what to expect.
    $items_block = '';
    $items = $extra['items'] ?? [];
    if (!empty($items)) {
        $item_rows = '';
        foreach ($items as $it) {
            $name = htmlspecialchars($it['name'] ?? 'Item');
            $qty  = (int)($it['qty'] ?? 1);
            $meta = array_filter([
                !empty($it['category'])  ? htmlspecialchars($it['category'])         : null,
                !empty($it['condition']) ? ucfirst(htmlspecialchars($it['condition'])) . ' condition' : null,
                !empty($it['qr'])        ? 'QR: ' . htmlspecialchars($it['qr'])       : null,
            ]);
            $meta_line = $meta ? '<div style="font-size:11.5px;color:#9ca3af;margin-top:2px;">' . implode(' &bull; ', $meta) . '</div>' : '';
            $item_rows .= '<tr>'
                . '<td style="padding:11px 14px;border-bottom:1px solid #f0f0f0;font-size:13.5px;color:#1a1d23;font-weight:600;">' . $name . $meta_line . '</td>'
                . '<td style="padding:11px 14px;border-bottom:1px solid #f0f0f0;font-size:13.5px;color:#374151;text-align:center;white-space:nowrap;">x' . $qty . '</td>'
                . '</tr>';
        }
        $items_block = '<div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#8B0000;margin:22px 0 8px;">Items in This Request (' . count($items) . ')</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eef0f3;border-radius:10px;overflow:hidden;">'
            . '<tr style="background:#faf5f5;">'
            . '<td style="padding:9px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:#8B0000;">Item</td>'
            . '<td style="padding:9px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;color:#8B0000;text-align:center;">Qty</td>'
            . '</tr>'
            . $item_rows
            . '</table>';
    }

    $year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#eef0f3;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f3;padding:40px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,0.06),0 4px 16px rgba(16,24,40,0.08);">
  <tr>
    <td style="background:#8B0000;padding:26px 32px;">
      <span style="color:#ffffff;font-size:19px;font-weight:800;letter-spacing:0.3px;">ManageMo</span>
      <div style="color:rgba(255,255,255,0.78);font-size:12px;margin-top:3px;letter-spacing:0.2px;">Pampanga State University &mdash; Inventory &amp; Asset Management</div>
    </td>
  </tr>
  <tr>
    <td style="padding:0;background:{$color};height:4px;line-height:4px;font-size:0;">&nbsp;</td>
  </tr>
  <tr>
    <td style="padding:34px 32px 8px;">
      <div style="display:inline-block;width:54px;height:54px;line-height:54px;text-align:center;border-radius:50%;background:{$bg};color:{$color};font-size:25px;margin-bottom:20px;">{$icon}</div>
      <div style="font-size:15px;color:#6b7280;margin-bottom:4px;">Dear {$safeName},</div>
      <div style="font-size:20px;font-weight:800;color:#1a1d23;margin-bottom:16px;line-height:1.35;">{$safeHead}</div>
      <p style="font-size:14.5px;line-height:1.65;color:#374151;margin:0;">{$safeDetail}</p>
      {$summary_block}
      {$items_block}
    </td>
  </tr>
  <tr>
    <td style="padding:28px 32px 30px;">
      <div style="border-top:1px solid #eef0f3;padding-top:18px;font-size:11.5px;color:#9ca3af;line-height:1.7;">
        This is an automated message from the ManageMo inventory system &mdash; please do not reply directly to this email. For questions about this request, contact the property custodian's office.
        <div style="margin-top:10px;color:#c1c5cc;">&copy; {$year} Pampanga State University &bull; ManageMo</div>
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

// ── Password Reset Token (file-based, no DB columns needed) ─────────────────
function _pwrTokenDir(): string {
    $dir = sys_get_temp_dir() . '/managemo_pwr';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir;
}

function _pwrTokenFile(string $token): string {
    // Store by SHA-256 of token so the raw token never sits on disk as a filename
    return _pwrTokenDir() . '/' . hash('sha256', $token) . '.json';
}

function _pwrSaveToken(string $token, int $user_id, int $expires): void {
    $file = _pwrTokenFile($token);
    file_put_contents($file, json_encode(['user_id' => $user_id, 'expires' => $expires]), LOCK_EX);
}

function _pwrLoadToken(string $token): ?array {
    $file = _pwrTokenFile($token);
    if (!file_exists($file)) return null;
    $data = json_decode(file_get_contents($file), true);
    if (!$data || !isset($data['expires'])) return null;
    if ($data['expires'] < time()) {
        @unlink($file); // expired — clean up
        return null;
    }
    return $data;
}

function _pwrDeleteToken(string $token): void {
    @unlink(_pwrTokenFile($token));
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

function deleteNotification(int $id, int $user_id): bool {
    // Scoped to user_id so users can only delete their own notifications.
    $rows = supabase()->delete('notifications', "id=eq.$id&user_id=eq.$user_id");
    return $rows !== false;
}

function deleteAllNotifications(int $user_id): bool {
    $rows = supabase()->delete('notifications', "user_id=eq.$user_id");
    return $rows !== false;
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

// Renders the <optgroup> options for a single merged Campus/College/Office
// picker (used by the inventory Add/Edit forms — the picker writes a plain
// department abbreviation into inventory.college_id, regardless of type).
function renderDepartmentOptionGroups(?string $selected = null): void {
    $groups = [
        'Campuses'         => array_column(getDepartmentCampuses(), 'name', 'abbreviation'),
        'Colleges'         => getMainCampusColleges(),
        'Offices'          => getMainCampusOffices(),
    ];
    foreach ($groups as $label => $options) {
        if (empty($options)) continue;
        echo '<optgroup label="' . htmlspecialchars($label) . '">';
        foreach ($options as $abbr => $fullname) {
            $sel = ($selected !== null && $selected === $abbr) ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($abbr) . '"' . $sel . '>' . htmlspecialchars($fullname) . '</option>';
        }
        echo '</optgroup>';
    }
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

function dbUpdateBorrowRecord(int $id, array $data): bool {
    $rows = supabase()->updateById('borrow_records', $id, $data);
    clearDataCache('borrow_records');
    return !empty($rows);
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

// Runs the per-unit "delivered" side effects for one request row: for a borrow
// request, flips the inventory unit to 'borrowed' and opens a borrow_records
// row; for an item (acquire) request, transfers ownership — flips the unit to
// 'disposed' and creates its user_owned_items row. Does NOT touch the request's
// own status/delivery_status — callers are responsible for that.
//
// Shared by admin/requests.php's "Mark Delivered" button and
// api/notify_delivered.php (the mobile app confirms delivery by writing
// straight to Supabase, then only calls that API to trigger the notification —
// without this shared function that path never created the borrow record or
// transferred item ownership). Safe to call more than once for the same
// row — it checks for an existing borrow_records row / already-disposed unit
// first, so it won't create duplicates if both paths ever fire for the same request.
function processDeliveredRequestUnit(array $gr, ?array $req_user = null): void {
    if ($gr['request_type'] === 'borrow' && !empty($gr['inventory_id'])) {
        $already = array_filter(getBorrowRecords(), fn($b) => (int)($b['request_id'] ?? 0) === (int)$gr['id']);
        if (!empty($already)) return;
        dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'borrowed']);
        dbCreateBorrowRecord([
            'user_id'              => (int)$gr['user_id'],
            'inventory_id'         => (int)$gr['inventory_id'],
            'request_id'           => (int)$gr['id'],
            'borrow_date'          => date('Y-m-d'),
            'expected_return_date' => $gr['expected_return_date'] ?? null,
            'status'               => 'active',
            'notes'                => $gr['reason_for_request'] ?? null,
        ]);
    } elseif ($gr['request_type'] === 'item' && !empty($gr['inventory_id'])) {
        $inv_item = findById(getInventory(), (int)$gr['inventory_id']);
        if (!$inv_item) return;
        // "Already transferred" means an owned_items row actually exists for this
        // unit's QR — NOT just that inventory is 'disposed'. A unit can end up
        // disposed with no owned_items row if a prior attempt got this far and
        // then failed (e.g. a schema mismatch on the insert); checking status
        // alone would wrongly treat that half-done state as complete forever.
        $qr = $gr['qr_code_id'] ?? $inv_item['qr_code_id'] ?? null;
        if ($qr) {
            $already = array_filter(getUserOwnedItems(), fn($o) => ($o['qr_code_id'] ?? null) === $qr);
            if (!empty($already)) return;
        }
        // 'owned' — NOT 'disposed' — the unit still physically exists, it's just
        // permanently transferred to this user rather than in the shared pool.
        // Using 'disposed' here used to make acquired items show up in
        // Condemnation & Disposal's Disposed tab, which only means "condemned
        // and thrown away/sold/donated" — a completely different thing.
        dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'owned']);
        dbCreateUserOwnedItem([
            'user_id'     => (int)$gr['user_id'],
            'qr_code_id'  => $gr['qr_code_id'] ?? ($inv_item['qr_code_id'] ?? null),
            'item_name'   => $inv_item['item_name']  ?? 'Unknown Item',
            'category'    => $inv_item['category']   ?? 'General',
            'description' => $inv_item['description'] ?? null,
            'year_owned'  => (int)date('Y'),
            'college_id'  => $req_user['college_id'] ?? $inv_item['college_id'] ?? null,
            'quantity'    => 1,
            'condition'   => $inv_item['condition'] ?? null,
            'notes'       => $gr['reason_for_request'] ?? null,
            'group_id'    => $gr['group_id'] ?? null,
        ]);
    }
}

function dbAddCustomDepartment(string $type, array $data): bool {
    if ($type === 'campus') {
        // Campuses now live in the departments table alongside colleges/offices
        // (type='campus'), not the separate campuses table. A campus has no
        // natural abbreviation, so derive one from the name — it only exists to
        // satisfy the shared (type, abbreviation) uniqueness constraint.
        $abbr = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['name']));
        $rows = supabase()->insert('departments', [
            'type'         => 'campus',
            'abbreviation' => $abbr,
            'full_name'    => $data['name'],
            'location'     => $data['location'] ?? null,
            'description'  => $data['description'] ?? null,
            'is_default'   => false,
        ]);
        clearDataCache();
    } else {
        // Colleges/offices are global — not scoped to any campus.
        $rows = supabase()->insert('departments', [
            'type'         => $type,
            'abbreviation' => $data['abbreviation'],
            'full_name'    => $data['full_name'],
            'is_default'   => false,
        ]);
        clearDataCache();
    }
    return !empty($rows);
}

function dbDeleteCustomDepartment(string $type, string $abbreviation, ?int $campus_id = null): bool {
    $filter = 'type=eq.' . $type . '&abbreviation=eq.' . urlencode($abbreviation);
    $rows = supabase()->select('departments', $filter);
    if (empty($rows) || $rows[0]['is_default']) return false;
    supabase()->delete('departments', $filter);
    clearDataCache();
    return true;
}

// Campuses are departments rows (type='campus') now — deleting one goes
// through dbDeleteCustomDepartment('campus', $abbreviation) like colleges/offices.
?>
