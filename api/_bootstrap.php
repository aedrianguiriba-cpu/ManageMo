<?php
/**
 * Shared bootstrap for JSON API endpoints used by the Flutter mobile app.
 * Auth is a stateless signed token (no session, no extra DB table):
 *   token = base64(user_id.expiry) + "." + hmac_sha256(user_id.expiry, API_TOKEN_SECRET)
 */

require_once dirname(__DIR__) . '/config/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function apiSecret(): string {
    $secret = getenv('API_TOKEN_SECRET') ?: ($_ENV['API_TOKEN_SECRET'] ?? '');
    if (!$secret) {
        apiFail(500, 'Server is missing API_TOKEN_SECRET configuration.');
    }
    return $secret;
}

function apiIssueToken(int $userId, int $ttlSeconds = 2592000): string {
    $expiry  = time() + $ttlSeconds; // default 30 days
    $payload = $userId . '.' . $expiry;
    $sig     = hash_hmac('sha256', $payload, apiSecret());
    return base64_encode($payload) . '.' . $sig;
}

function apiAuthorizationHeader(): string {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    // Some Apache/mod_php configs strip the Authorization header from $_SERVER entirely
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) return $value;
        }
    }
    return '';
}

/** Returns the authenticated user array, or null if missing/invalid/expired. */
function apiAuthenticatedUser(): ?array {
    $header = apiAuthorizationHeader();
    if (!$header || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        return null;
    }
    $token = $m[1];
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) return null;
    [$payloadB64, $sig] = $parts;

    $payload = base64_decode($payloadB64, true);
    if ($payload === false || !str_contains($payload, '.')) return null;

    $expected = hash_hmac('sha256', $payload, apiSecret());
    if (!hash_equals($expected, $sig)) return null;

    [$userId, $expiry] = explode('.', $payload, 2);
    if ((int)$expiry < time()) return null;

    $user = findById(getUsers(), (int)$userId);
    if (!$user || empty($user['is_active'])) return null;
    return $user;
}

function apiInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function apiOk($data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function apiFail(int $code, string $message): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function apiRequireUser(): array {
    $user = apiAuthenticatedUser();
    if (!$user) {
        apiFail(401, 'Invalid or expired session. Please log in again.');
    }
    return $user;
}
