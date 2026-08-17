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
    apiFail(409, 'This delivery has already been confirmed.');
}

if (($match['delivery_status'] ?? null) !== 'out_for_delivery') {
    apiFail(409, 'This item is not out for delivery yet.');
}

// Apply to the whole group, same as the admin "mark delivered" action
$gid = !empty($match['group_id']) ? $match['group_id'] : null;
$group_reqs = $gid
    ? array_values(array_filter($all_requests, fn($r) => ($r['group_id'] ?? '') === $gid))
    : [$match];

$inventory = getInventory();
$item_names = [];

foreach ($group_reqs as $gr) {
    dbUpdateRequest((int)$gr['id'], ['delivery_status' => 'delivered', 'status' => 'delivered']);

    if ($gr['request_type'] === 'borrow' && !empty($gr['inventory_id'])) {
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
    }

    $inv = !empty($gr['inventory_id']) ? findById($inventory, (int)$gr['inventory_id']) : null;
    $item_names[] = $inv['item_name'] ?? ($gr['service_description'] ?? 'Item');
}

logActivity((int)$user['id'], 'UPDATE', "Confirmed delivery via QR scan for group " . ($gid ?? $match['request_number']), 'requests', (int)$match['id']);

apiOk([
    'message'      => 'Delivery confirmed. Thank you!',
    'request_number' => $match['request_number'],
    'item_names'   => array_values(array_unique($item_names)),
    'unit_count'   => count($group_reqs),
]);
