<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiFail(405, 'Method not allowed.');
}

$user = apiRequireUser();

$inventory = getInventory();
$mine = array_values(array_filter(getRequests(), fn($r) =>
    (int)$r['user_id'] === (int)$user['id'] && ($r['delivery_status'] ?? null) === 'out_for_delivery'
));

// Group by group_id so multi-unit requests appear once
$groups = [];
foreach ($mine as $r) {
    $key = !empty($r['group_id']) ? $r['group_id'] : 'id:' . $r['id'];
    if (!isset($groups[$key])) $groups[$key] = ['rows' => [], 'first' => $r];
    $groups[$key]['rows'][] = $r;
}

$out = [];
foreach ($groups as $g) {
    $first = $g['first'];
    $names = [];
    foreach ($g['rows'] as $r) {
        $inv = !empty($r['inventory_id']) ? findById($inventory, (int)$r['inventory_id']) : null;
        $names[] = $inv['item_name'] ?? ($r['service_description'] ?? 'Item');
    }
    $out[] = [
        'request_number' => $first['request_number'],
        'group_id'       => $first['group_id'] ?? null,
        'qr_code_id'     => $first['qr_code_id'] ?? null,
        'item_names'     => array_values(array_unique($names)),
        'unit_count'     => count($g['rows']),
        'created_at'     => $first['created_at'],
    ];
}

apiOk(['deliveries' => $out]);
