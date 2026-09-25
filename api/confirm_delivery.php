<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiFail(405, 'Method not allowed.');
}

$user = apiRequireUser();

$input = apiInput();
$qr    = trim($input['qr_code_id'] ?? '');

if (!$qr) {
    apiFail(422, 'qr_code_id is required.');
}

$all_requests = getRequests();
$match = null;
foreach ($all_requests as $r) {
    if (($r['qr_code_id'] ?? null) === $qr) {
        $match = $r;
        break;
    }
}

if (!$match) {
    apiFail(404, 'This QR code does not match any request.');
}

if ((int)$match['user_id'] !== (int)$user['id']) {
    apiFail(403, 'This item was not requested by you.');
}

if (($match['delivery_status'] ?? null) === 'delivered') {
    apiFail(409, 'This item has already been scanned.');
}

if (($match['delivery_status'] ?? null) !== 'out_for_delivery') {
    apiFail(409, 'This item is not out for delivery yet.');
}

// Confirm ONLY the unit that was actually scanned — each request row is one
// physical unit (its own QR code). A multi-unit request only becomes fully
// "Delivered" once every one of its units has been individually scanned;
// marking the whole group here (like the admin's "Mark Delivered" button
// does deliberately, as one atomic dispatch action) would silently complete
// units nobody actually scanned yet — and the next real scan of one of those
// would then wrongly report "already confirmed" even though it was never
// scanned before.
dbUpdateRequest((int)$match['id'], ['delivery_status' => 'delivered', 'status' => 'delivered']);
processDeliveredRequestUnit($match, $user);

$inventory = getInventory();
$inv       = !empty($match['inventory_id']) ? findById($inventory, (int)$match['inventory_id']) : null;
$item_name = $inv['item_name'] ?? ($match['service_description'] ?? 'Item');

// Work out how many units in the group are left, so the app can show real
// progress instead of assuming one scan finishes the whole delivery.
$gid = !empty($match['group_id']) ? $match['group_id'] : null;
$group_reqs = $gid
    ? array_values(array_filter($all_requests, fn($r) => ($r['group_id'] ?? '') === $gid))
    : [$match];
$total_count     = count($group_reqs);
$delivered_count = 1; // the unit just confirmed above
foreach ($group_reqs as $gr) {
    if ((int)$gr['id'] === (int)$match['id']) continue;
    if (($gr['delivery_status'] ?? null) === 'delivered') $delivered_count++;
}
$all_delivered = $delivered_count >= $total_count;

logActivity((int)$user['id'], 'UPDATE', "Confirmed delivery via QR scan for unit #{$match['id']}" . ($gid ? " (group $gid)" : ''), 'requests', (int)$match['id']);

// Only send the "delivered" notification once every unit in the group has
// actually been scanned — not after the first one.
if ($all_delivered) {
    $reqNumber = $gid ?? $match['request_number'];
    sendStatusEmail($user['email'], $user['full_name'], $reqNumber, 'delivered');
    notifyUser((int)$user['id'], 'Item delivered', "Request ($reqNumber) has been marked as delivered.", 'success', 'user/my-requests.php');
}

apiOk([
    'message'         => $all_delivered
        ? 'Delivery confirmed. Thank you!'
        : 'Item confirmed. ' . ($total_count - $delivered_count) . ' item(s) still need to be scanned.',
    'request_number'  => $match['request_number'],
    'item_name'       => $item_name,
    'delivered_count' => $delivered_count,
    'unit_count'      => $total_count,
    'all_delivered'   => $all_delivered,
]);
