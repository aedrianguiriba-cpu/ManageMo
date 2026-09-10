<?php
/**
 * Fire-and-forget endpoint called by the mobile app right after it confirms
 * a delivery directly against Supabase (the app has no PHP session/token —
 * it talks to Supabase on its own). Since the app only flips the request
 * row's status/delivery_status itself, this endpoint also runs the same
 * per-unit "delivered" side effects the web admin's "Mark Delivered" button
 * does — opening a borrow_records row for a borrow request, or transferring
 * ownership (disposing the inventory unit + creating its user_owned_items
 * row) for an item/acquire request — for every row in the request's group,
 * then sends the "delivered" email + bell notification, since Dart can't
 * reliably speak raw SMTP.
 *
 * No user auth token is required (the app doesn't hold one), so instead of
 * trusting the caller's claims, this re-reads the request row from the
 * database itself and only acts if that row is ACTUALLY marked delivered
 * AND was updated in the last few minutes — i.e. the caller must have just
 * performed that exact update (via the service_role key already embedded in
 * the app) for this to do anything. It can't be used to spam arbitrary
 * emails or side effects for arbitrary requests.
 */
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiFail(405, 'Method not allowed.');
}

$input      = apiInput();
$request_id = (int)($input['request_id'] ?? 0);
if ($request_id <= 0) {
    apiFail(422, 'request_id is required.');
}

$request = findById(getRequests(), $request_id);
if (!$request) {
    apiFail(404, 'Request not found.');
}

if ($request['delivery_status'] !== 'delivered') {
    apiFail(409, 'This request is not marked delivered yet — nothing to notify.');
}

$updatedAt = strtotime($request['updated_at'] ?? '');
if (!$updatedAt || (time() - $updatedAt) > 300) {
    apiFail(409, 'This delivery confirmation is not recent — refusing to (re)send a notification.');
}

$user = findById(getUsers(), (int)$request['user_id']);
if (!$user) {
    apiFail(404, 'Requester not found.');
}

// Run the same per-unit delivered side effects the web "Mark Delivered" button
// runs, for every row in this request's group (the app only flipped this one
// row plus its siblings' status directly in Supabase — it never ran this logic).
$group_id = $request['group_id'] ?? null;
$group_reqs = $group_id
    ? array_values(array_filter(getRequests(), fn($r) => ($r['group_id'] ?? '') === $group_id))
    : [$request];
foreach ($group_reqs as $gr) {
    processDeliveredRequestUnit($gr, $user);
}

$reqNumber = !empty($request['group_id']) ? $request['group_id'] : $request['request_number'];

sendStatusEmail($user['email'], $user['full_name'], $reqNumber, 'delivered');
notifyUser((int)$user['id'], 'Item delivered', "Request ($reqNumber) has been marked as delivered.", 'success', 'user/my-requests.php');

apiOk();
