<?php
$page_title = 'Manage Requests';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();
$action = $_GET['action'] ?? 'list';
$page = $_GET['page'] ?? 1;
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';

// Handle request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id  = (int)sanitizeInput($_POST['request_id']);
    $action_type = sanitizeInput($_POST['action']);

    // Resolve the full group this action applies to
    $trigger_req = findById(getRequests(), $request_id);
    $gid         = !empty($trigger_req['group_id']) ? $trigger_req['group_id'] : null;
    $group_reqs  = $gid
        ? array_values(array_filter(getRequests(), fn($r) => ($r['group_id'] ?? '') === $gid))
        : ($trigger_req ? [$trigger_req] : []);
    $redirect_param = $gid ? 'group_id=' . urlencode($gid) : 'id=' . $request_id;

    if ($action_type === 'approve') {
        foreach ($group_reqs as $gr) {
            dbUpdateRequest((int)$gr['id'], ['status' => 'approved', 'approved_by' => $current_user['id'], 'approved_at' => date('Y-m-d H:i:s')]);
            if (!empty($gr['inventory_id'])) {
                if ($gr['request_type'] === 'borrow')  dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'requested']);
                if ($gr['request_type'] === 'service') dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'maintenance']);
            }
        }
        logActivity($current_user['id'], 'APPROVE', "Approved group $gid (" . count($group_reqs) . " units)", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Request approved successfully!', 'success');

    } elseif ($action_type === 'disapprove') {
        foreach ($group_reqs as $gr) {
            dbUpdateRequest((int)$gr['id'], ['status' => 'disapproved', 'approved_by' => $current_user['id'], 'approved_at' => date('Y-m-d H:i:s')]);
            if (!empty($gr['inventory_id'])) {
                $inv = findById(getInventory(), (int)$gr['inventory_id']);
                if ($inv && in_array($inv['status'], ['requested', 'maintenance'])) {
                    dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'available']);
                }
            }
        }
        logActivity($current_user['id'], 'DISAPPROVE', "Disapproved group $gid", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Request disapproved.', 'info');

    } elseif ($action_type === 'change_receiving_method') {
        $new_method = sanitizeInput($_POST['receiving_method'] ?? '');
        if (in_array($new_method, ['delivery', 'pickup'])) {
            foreach ($group_reqs as $gr) {
                dbUpdateRequest((int)$gr['id'], ['receiving_method' => $new_method]);
            }
            redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Receiving method updated to ' . ucfirst($new_method) . '.', 'success');
        }

    } elseif ($action_type === 'mark_out_for_delivery') {
        foreach ($group_reqs as $gr) {
            dbUpdateRequest((int)$gr['id'], ['delivery_status' => 'out_for_delivery']);
        }
        $notif_user  = findById(getUsers(), $trigger_req['user_id'] ?? 0);
        $recv_method = $trigger_req['receiving_method'] ?? 'delivery';
        if ($notif_user) {
            $stage = ($recv_method === 'pickup') ? 'pickup_ready' : 'out_for_delivery';
            sendDeliveryEmail($notif_user['email'], $notif_user['full_name'], $gid ?? $trigger_req['request_number'], $stage);
        }
        $label = ($recv_method === 'pickup') ? 'Marked as Ready for Pickup.' : 'Marked as Out for Delivery.';
        redirectWithMessage('requests.php?action=view&' . $redirect_param, $label, 'success');

    } elseif ($action_type === 'mark_delivered') {
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
        }
        logActivity($current_user['id'], 'UPDATE', "Delivered group $gid", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Marked as Delivered.', 'success');

    } elseif ($action_type === 'mark_returned') {
        $borrow_records = getBorrowRecords();
        foreach ($group_reqs as $gr) {
            foreach ($borrow_records as $br) {
                if ((int)$br['request_id'] === (int)$gr['id'] && $br['status'] === 'active') {
                    supabase()->updateById('borrow_records', (int)$br['id'], ['status' => 'returned', 'actual_return_date' => date('Y-m-d')]);
                }
            }
            if (!empty($gr['inventory_id'])) dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'available']);
            dbUpdateRequest((int)$gr['id'], ['status' => 'completed']);
        }
        clearDataCache('borrow_records');
        redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Items returned and request completed.', 'success');

    } elseif ($action_type === 'mark_completed') {
        foreach ($group_reqs as $gr) {
            if (!empty($gr['inventory_id'])) dbUpdateInventory((int)$gr['inventory_id'], ['status' => 'available']);
            dbUpdateRequest((int)$gr['id'], ['status' => 'completed']);
        }
        redirectWithMessage('requests.php?action=view&' . $redirect_param, 'Request marked as completed.', 'success');
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
displayMessage();

// Get all requests
$all_requests   = getRequests();
$users_data     = getUsers();
$inventory_data = getInventory();

// Build a request_id → [request_items] map for display (avoids per-row queries)
$_all_req_items = getRequestItems();
$_req_items_map = [];
foreach ($_all_req_items as $_ri) {
    $_req_items_map[(int)$_ri['request_id']][] = $_ri;
}

// Helper: get first item name for a request (falls back to inventory then 'Unknown Item')
function _reqFirstItemName(array $req_items_map, int $req_id, array $inventory_data): string {
    $ri = $req_items_map[$req_id][0] ?? null;
    if ($ri && !empty($ri['item_name'])) return $ri['item_name'];
    if ($ri && !empty($ri['inventory_id'])) {
        $inv = findById($inventory_data, (int)$ri['inventory_id']);
        if ($inv) return $inv['item_name'];
    }
    return 'Unknown Item';
}
function _reqFirstQR(array $req_items_map, int $req_id): string {
    $ri = $req_items_map[$req_id][0] ?? null;
    return ($ri && !empty($ri['qr_code_id'])) ? $ri['qr_code_id'] : 'N/A';
}

// Apply filters
$filtered_requests = $all_requests;

if ($status_filter) {
    $filtered_requests = filterByColumn($filtered_requests, 'status', $status_filter);
}

if ($type_filter) {
    $filtered_requests = filterByColumn($filtered_requests, 'request_type', $type_filter);
}

// Sort by created_at descending
usort($filtered_requests, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

// Group filtered requests by group_id (or use id as fallback key)
$grouped_filtered = [];
$seen_group_keys  = [];
foreach ($filtered_requests as $req) {
    $gkey = !empty($req['group_id']) ? 'gid:' . $req['group_id'] : 'id:' . $req['id'];
    if (!isset($grouped_filtered[$gkey])) {
        $grouped_filtered[$gkey] = ['rows' => [], 'first' => $req];
    }
    $grouped_filtered[$gkey]['rows'][] = $req;
}
$grouped_filtered = array_values($grouped_filtered);

// Paginate on groups, not individual rows
$total      = count($grouped_filtered);
$total_pages = max(1, ceil($total / ITEMS_PER_PAGE));
$offset     = ($page - 1) * ITEMS_PER_PAGE;

$requests = [];
foreach (array_slice($grouped_filtered, $offset, ITEMS_PER_PAGE) as $grp) {
    $req   = $grp['first'];
    $rows  = $grp['rows'];
    $user  = findById($users_data, $req['user_id']);

    // Collect item names across all rows in the group
    $grp_names = [];
    foreach ($rows as $_r) {
        $_inv = !empty($_r['inventory_id']) ? findById($inventory_data, (int)$_r['inventory_id']) : null;
        $n = $_inv['item_name']
          ?? _reqFirstItemName($_req_items_map, (int)$_r['id'], $inventory_data);
        if ($n && $n !== 'Unknown Item') $grp_names[] = $n;
    }
    $grp_names     = array_values(array_unique($grp_names));
    $grp_name_str  = !empty($grp_names) ? implode(', ', array_slice($grp_names, 0, 3)) . (count($grp_names) > 3 ? '…' : '') : 'N/A';

    $requests[] = array_merge($req, [
        'full_name'   => $user['full_name']  ?? 'Unknown',
        'email'       => $user['email']      ?? 'N/A',
        'campus_id'   => $user['campus_id']  ?? null,
        'college_id'  => $user['college_id'] ?? null,
        'item_name'   => $grp_name_str,
        'qr_code_id'  => $req['qr_code_id'] ?? _reqFirstQR($_req_items_map, (int)$req['id']),
        'unit_count'  => count($rows),
        'group_id'    => $req['group_id'] ?? null,
    ]);
}
?>


<style>
/* ===== ADMIN REQUESTS ===== */
.ar-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:22px 24px; margin-bottom:20px;
}
.ar-card-title { font-size:1.05rem; font-weight:800; color:#1a1d23; margin-bottom:4px; }
.ar-card-sub   { font-size:0.81rem; color:#999; margin-bottom:18px; }
.ar-section-label {
    font-size:0.69rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.5px; color:#999;
    margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #e5e7eb;
}
.ar-info-row { display:flex; flex-direction:column; gap:8px; }
.ar-info-row p { margin:0; font-size:0.87rem; color:#374151; }
.ar-info-row p strong { color:#1a1d23; margin-right:6px; }
.ar-desc-box {
    background:#f7f7f7; border:1px solid #e5e7eb;
    border-radius:6px; padding:14px 16px; font-size:0.88rem;
    color:#374151; line-height:1.6;
}
.ar-action-tabs { display:flex; gap:0; border-bottom:2px solid #e5e7eb; margin-bottom:20px; }
.ar-tab-btn {
    background:none; border:none; font-size:0.87rem; font-weight:700;
    color:#999; padding:9px 20px;
    border-bottom:2px solid transparent; margin-bottom:-2px; cursor:pointer;
    transition:all 0.15s;
}
.ar-tab-btn.active { color:#8B0000; border-bottom-color:#8B0000; }
.ar-tab-pane { display:none; }
.ar-tab-pane.active { display:block; }

/* Filter card */
.ar-filter-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    padding:16px 20px; margin-bottom:16px;
    display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;
}
.ar-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#999; margin-bottom:5px; }

/* Buttons */
.ar-btn-primary {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
    transition:opacity 0.15s !important;
}
.ar-btn-primary:hover { color:#fff !important; opacity:0.88 !important; }
.ar-btn-secondary {
    background:#f7f7f7 !important; border:1px solid #e5e7eb !important;
    border-radius:6px !important; font-weight:600 !important; color:#555 !important;
    padding:9px 16px !important; font-size:0.87rem !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:6px;
}
.ar-btn-success {
    background:#166534 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
}
.ar-btn-danger {
    background:#991b1b !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
}
.ar-btn-view {
    background:rgba(139,0,0,0.09); color:#8B0000;
    border:none; border-radius:6px; font-size:0.79rem; font-weight:700;
    padding:5px 12px; cursor:pointer; text-decoration:none;
    display:inline-flex; align-items:center; gap:5px;
    transition:background 0.13s;
}
.ar-btn-view:hover { background:rgba(139,0,0,0.16); color:#8B0000; }

/* Table */
.ar-table-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); overflow:hidden;
}
.ar-table { width:100%; border-collapse:collapse; }
.ar-table th {
    font-size:0.69rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.5px; color:#999;
    padding:12px 16px; border-bottom:1px solid #e5e7eb;
    background:#f7f7f7;
}
.ar-table td {
    padding:12px 16px; border-bottom:1px solid #e5e7eb;
    font-size:0.87rem; color:#374151; vertical-align:middle;
}
.ar-table tr:last-child td { border-bottom:none; }
.ar-table tr:hover td { background:#f7f7f7; }

.ar-badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:4px; font-size:0.74rem; font-weight:700;
}
.ar-badge-success   { background:rgba(34,197,94,0.12);  color:#15803d; }
.ar-badge-warning   { background:rgba(245,158,11,0.12); color:#b45309; }
.ar-badge-danger    { background:rgba(239,68,68,0.12);  color:#dc2626; }
.ar-badge-info      { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.ar-badge-secondary { background:rgba(0,0,0,0.07);       color:#555; }
.ar-badge-primary   { background:rgba(139,0,0,0.10);     color:#8B0000; }

.ar-req-id { font-family:monospace; font-size:0.76rem; font-weight:600; background:rgba(139,0,0,0.07); color:#8B0000; border-radius:4px; padding:2px 7px; }
.ar-empty  { padding:48px 24px; text-align:center; color:#999; }
.ar-empty i { font-size:2.5rem; margin-bottom:12px; display:block; opacity:0.3; }

/* ── Request Tracker Stepper ── */
.ar-stepper-wrap {
    padding: 22px 24px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    margin-bottom: 20px;
}
.ar-stepper-title {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: #999; margin-bottom: 20px;
}
.ar-steps { display: flex; align-items: flex-start; position: relative; }
.ar-step  { display: flex; flex-direction: column; align-items: center; flex: 1; position: relative; }
.ar-step-dot {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 700;
    border: 2px solid #e5e7eb;
    background: #fff; color: #999;
    position: relative; z-index: 2; transition: all 0.2s;
}
.ar-step-dot.s-done     { background: #15803d; border-color:#15803d; color:#fff; }
.ar-step-dot.s-active   { background: #8B0000; border-color:#8B0000; color:#fff; }
.ar-step-dot.s-pending  { background: rgba(245,158,11,.12); border-color:#f59e0b; color:#b45309; }
.ar-step-dot.s-rejected { background: #991b1b; border-color:#991b1b; color:#fff; }
.ar-step-lbl {
    font-size: 0.68rem; font-weight: 700; text-align: center;
    margin-top: 8px; color: #999; line-height: 1.3; max-width: 72px;
}
.ar-step-lbl.l-done     { color: #15803d; }
.ar-step-lbl.l-active   { color: #8B0000; }
.ar-step-lbl.l-pending  { color: #b45309; }
.ar-step-lbl.l-rejected { color: #b91c1c; }
.ar-step-line {
    flex: 1; height: 2px; background: #e5e7eb;
    margin: 0 -2px; margin-top: -19px; position: relative; z-index: 1;
}
.ar-step-line.l-done { background: #22c55e; }

/* Tracker card view */
.ar-tracker-card {
    background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    margin-bottom: 14px; overflow: hidden;
    transition: border-color .18s;
}
.ar-tracker-card:hover { border-color: rgba(139,0,0,.20); }
.ar-tracker-head {
    padding: 14px 20px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #e5e7eb; gap: 12px; flex-wrap: wrap;
}
.ar-tracker-body { padding: 16px 20px; }
.ar-view-toggle {
    display: inline-flex; background: #f7f7f7; border-radius: 6px; padding: 3px;
    gap: 2px; margin-bottom: 16px;
}
.ar-vt-btn {
    padding: 6px 14px; border-radius: 4px; border: none; background: transparent;
    font-size: .8rem; font-weight: 700; color: #555;
    cursor: pointer; transition: all .2s;
}
.ar-vt-btn.active { background: #fff; color: #8B0000; border: 1px solid #e5e7eb; }

/* Request type tabs */
.ar-type-tabs {
    display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 18px;
}
.ar-type-tab {
    background: none; border: none; font-size: 0.87rem; font-weight: 700;
    color: #999; padding: 10px 20px;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    cursor: pointer; transition: all 0.15s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 6px;
}
.ar-type-tab:hover { color: #555; }
.ar-type-tab.active { color: #8B0000; border-bottom-color: #8B0000; }
.ar-type-tab .ar-tab-count {
    font-size: 0.70rem; background: rgba(0,0,0,0.07); color: #555;
    border-radius: 4px; padding: 1px 7px; font-weight: 700;
}
.ar-type-tab.active .ar-tab-count { background: rgba(139,0,0,0.12); color: #8B0000; }

/* ── QR Sticker Sheet ── */
.ar-sticker-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: 6px;
    border: 1px solid rgba(139,0,0,.25);
    background: rgba(139,0,0,.06); color: #8B0000;
    font-size: .82rem; font-weight: 700; cursor: pointer;
    transition: background .18s; text-decoration: none;
}
.ar-sticker-btn:hover { background: rgba(139,0,0,.13); }
.ar-sticker-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.55); z-index: 9990;
    align-items: flex-start; justify-content: center;
    padding: 32px 16px; overflow-y: auto;
}
.ar-sticker-overlay.open { display: flex; }
.ar-sticker-sheet-wrap {
    background: #f3f3f3; border-radius: 8px;
    padding: 28px; width: 100%; max-width: 700px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.ar-sticker-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
@media(max-width:700px){ .ar-sticker-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px){ .ar-sticker-grid { grid-template-columns: 1fr; } }
/* ── individual label ── */
.ar-sticker {
    background: #fff; border: 1.5px solid #ccc;
    border-radius: 6px; overflow: hidden;
    font-family: Arial, Helvetica, sans-serif;
    page-break-inside: avoid; break-inside: avoid;
}
.ar-sticker-top {
    background: #8B0000;
    color: #fff; text-align: center;
    padding: 8px 6px 5px; font-size: 8px; font-weight: 800;
    letter-spacing: .5px; text-transform: uppercase;
}
.ar-sticker-top span { display:block; font-size:6.5px; font-weight:400; opacity:.82; margin-top:1px; }
.ar-sticker-qr { padding: 10px 6px 4px; text-align: center; }
.ar-sticker-qr img { width: 90px; height: 90px; display: block; margin: 0 auto; }
.ar-sticker-body { padding: 0 8px 8px; }
.ar-sticker-name {
    font-size: 8.5px; font-weight: 800; text-align: center; color: #0f172a;
    margin-bottom: 5px; border-bottom: 1px solid rgba(0,0,0,.07);
    padding-bottom: 5px; line-height: 1.35;
}
.ar-sticker-row { display:flex; gap:4px; font-size:6.5px; margin-bottom:2px; color:#333; }
.ar-sticker-row strong { color:#8B0000; min-width:46px; flex-shrink:0; }
.ar-sticker-code {
    margin-top:5px; background:#f7f7f7; border-radius:4px;
    padding:3px 5px; text-align:center; font-family:monospace;
    font-size:7px; color:#8B0000; font-weight:700; letter-spacing:.3px;
}
.ar-sticker-foot {
    background:rgba(139,0,0,.05); border-top:1px solid rgba(139,0,0,.1);
    padding:3px 6px; text-align:center; font-size:6px; color:rgba(0,0,0,.38);
}
</style>

<div class="container-fluid mt-4 pb-4">

    <?php if ($action === 'view'): ?>
        <!-- View Request Details -->
        <?php
        $view_group_id  = sanitizeInput($_GET['group_id'] ?? '');
        $view_single_id = (int)sanitizeInput($_GET['id'] ?? 0);

        // Resolve the group of requests to display
        $all_reqs_for_view = getRequests();
        if ($view_group_id) {
            $group_view_reqs = array_values(array_filter($all_reqs_for_view, fn($r) => ($r['group_id'] ?? '') === $view_group_id));
            $request = $group_view_reqs[0] ?? null;
        } elseif ($view_single_id) {
            $request = findById($all_reqs_for_view, $view_single_id);
            $group_view_reqs = $request && !empty($request['group_id'])
                ? array_values(array_filter($all_reqs_for_view, fn($r) => ($r['group_id'] ?? '') === $request['group_id']))
                : ($request ? [$request] : []);
        } else {
            $request = null;
            $group_view_reqs = [];
        }

        if (!$request) {
            die('<div class="alert alert-danger">Request not found</div>');
        }

        $user = findById(getUsers(), $request['user_id']);

        // Resolve item info: for grouped requests, build from each request row directly
        $sticker_units = [];
        foreach ($group_view_reqs as $_gr) {
            $_gr_inv = !empty($_gr['inventory_id']) ? findById(getInventory(), (int)$_gr['inventory_id']) : null;
            $_gr_qr  = $_gr['qr_code_id'] ?? ($_gr_inv['qr_code_id'] ?? null);
            // Also check legacy request_items for this row
            if (!$_gr_qr) {
                $leg_ri = $_req_items_map[(int)$_gr['id']][0] ?? null;
                $_gr_qr = $leg_ri['qr_code_id'] ?? null;
            }
            if (!$_gr_qr) continue;
            $sticker_units[] = [
                'qr'        => $_gr_qr,
                'item_name' => $_gr_inv['item_name'] ?? ($_gr['service_description'] ?? 'N/A'),
                'condition' => $_gr_inv['condition'] ?? 'N/A',
                'location'  => $_gr_inv['location']  ?? 'N/A',
                'req_num'   => $_gr['request_number'] ?? '',
            ];
        }
        // Fallback: try legacy request_items
        if (empty($sticker_units)) {
            foreach (getRequestItems($request['id']) as $ri) {
                $ri_qr = $ri['qr_code_id'] ?? null;
                if (!$ri_qr) continue;
                $ri_inv = !empty($ri['inventory_id']) ? findById(getInventory(), (int)$ri['inventory_id']) : null;
                $sticker_units[] = [
                    'qr'        => $ri_qr,
                    'item_name' => $ri['item_name'] ?? ($ri_inv['item_name'] ?? 'N/A'),
                    'condition' => $ri_inv['condition'] ?? 'N/A',
                    'location'  => $ri_inv['location']  ?? 'N/A',
                    'req_num'   => $request['request_number'] ?? '',
                ];
            }
        }

        // Build display-level item info from first unit
        $first_unit_inv = !empty($request['inventory_id']) ? findById(getInventory(), (int)$request['inventory_id']) : null;
        $first_ri_leg   = $_req_items_map[(int)$request['id']][0] ?? null;
        $fallback_item_name = $first_unit_inv['item_name']
                           ?? $first_ri_leg['item_name']
                           ?? (trim($request['service_description'] ?? '') ?: 'Not specified');
        $fallback_qr = $request['qr_code_id']
                    ?? $first_unit_inv['qr_code_id']
                    ?? $first_ri_leg['qr_code_id']
                    ?? $request['request_number']
                    ?? 'N/A';
        $item = $first_unit_inv;

        $request = array_merge($request, [
            'full_name'          => $user['full_name'] ?? 'Unknown',
            'email'              => $user['email'] ?? 'N/A',
            'phone'              => $user['phone'] ?? 'N/A',
            'campus_id'          => $user['campus_id'] ?? null,
            'college_id'         => $user['college_id'] ?? null,
            'item_name'          => $fallback_item_name,
            'qr_code_id'         => $fallback_qr,
            'item_location'      => $item['location']   ?? 'N/A',
            'item_category'      => $item['category']   ?? ucfirst($request['request_type'] ?? 'Request'),
            'item_condition'     => $item['condition']  ?? 'N/A',
            'quantity_requested' => count($group_view_reqs) ?: ($request['quantity_requested'] ?? 1),
            'request_number'     => $request['request_number'] ?? 'REQ-' . str_pad($request['id'], 5, '0', STR_PAD_LEFT),
        ]);

        $status_colors = ['pending'=>'warning','approved'=>'success','disapproved'=>'danger','delivered'=>'info','returned'=>'primary','completed'=>'secondary'];
        $urgency_colors = ['low'=>'info','medium'=>'warning','high'=>'danger','critical'=>'danger'];
        $type_labels = ['item'=>'Item Request','borrow'=>'Borrow Request','service'=>'Service Request'];
        ?>

        <!-- Header row -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <div style="font-size:1.1rem;font-weight:800;color:#1a1d23;">Request <?php echo htmlspecialchars($request['request_number']); ?></div>
                <div style="font-size:0.81rem;color:rgba(0,0,0,0.42);">Submitted <?php echo formatDate($request['created_at']); ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span class="ar-badge ar-badge-<?php echo $status_colors[$request['status']] ?? 'secondary'; ?>" style="font-size:0.85rem;padding:6px 16px;"><?php echo ucfirst($request['status']); ?></span>
                <button class="ar-sticker-btn" onclick="openStickerSheet()"><i class="fas fa-qrcode me-1"></i>Print QR Sticker</button>
            </div>
        </div>

        <!-- Tracker Stepper -->
        <?php
        $rstatus   = $request['status'];
        $is_service = ($request['request_type'] === 'service');
        $rdel      = $request['delivery_status'] ?? null;

        // Compute per-step state
        $sd = [1=>'s-done',2=>'',3=>'',4=>'',5=>''];
        $ld = [1=>'l-done',2=>'',3=>'',4=>'',5=>''];
        $ln = [1=>'',2=>'',3=>'',4=>''];

        if ($is_service) {
            // Service request steps: Submitted → Under Review → Approved → In Progress → Completed
            $si = [1=>'fas fa-check',2=>'fas fa-search',3=>'fas fa-check',4=>'fas fa-wrench',5=>'fas fa-flag-checkered'];
            $sl = [1=>'Submitted',2=>'Under Review',3=>'Approved',4=>'In Progress',5=>'Completed'];
            if ($rstatus === 'pending') {
                $sd[2]='s-pending'; $ld[2]='l-pending';
                $ln[1]='l-done';
            } elseif ($rstatus === 'disapproved') {
                $sd[2]='s-done'; $ld[2]='l-done';
                $sd[3]='s-rejected'; $ld[3]='l-rejected'; $sl[3]='Disapproved';
                $si[3]='fas fa-times';
                $ln[1]='l-done';
            } elseif ($rstatus === 'completed') {
                foreach([2,3,4,5] as $x){$sd[$x]='s-done';$ld[$x]='l-done';}
                foreach([1,2,3,4] as $x){$ln[$x]='l-done';}
            } elseif ($rstatus === 'approved') {
                $sd[2]='s-done'; $ld[2]='l-done';
                $sd[3]='s-done'; $ld[3]='l-done';
                $sd[4]='s-active'; $ld[4]='l-active';
                $ln[1]='l-done'; $ln[2]='l-done'; $ln[3]='l-done';
            }
        } else {
            // Borrow/Item steps: Submitted → Under Review → Approved → Out for Delivery → Delivered
            $si = [1=>'fas fa-check',2=>'fas fa-search',3=>'fas fa-check',4=>'fas fa-truck',5=>'fas fa-flag-checkered'];
            $sl = [1=>'Submitted',2=>'Under Review',3=>'Approved',4=>'Out for Delivery',5=>'Delivered'];
            if ($rstatus === 'pending') {
                $sd[2]='s-pending'; $ld[2]='l-pending';
                $ln[1]='l-done';
            } elseif ($rstatus === 'disapproved') {
                $sd[2]='s-done'; $ld[2]='l-done';
                $sd[3]='s-rejected'; $ld[3]='l-rejected'; $sl[3]='Disapproved';
                $si[3]='fas fa-times';
                $ln[1]='l-done';
            } elseif ($rdel === 'delivered') {
                foreach([2,3,4,5] as $x){$sd[$x]='s-done';$ld[$x]='l-done';}
                foreach([1,2,3,4] as $x){$ln[$x]='l-done';}
            } elseif ($rdel === 'out_for_delivery') {
                $sd[2]='s-done'; $ld[2]='l-done';
                $sd[3]='s-done'; $ld[3]='l-done';
                $sd[4]='s-active'; $ld[4]='l-active';
                $ln[1]='l-done'; $ln[2]='l-done'; $ln[3]='l-done';
            } else {
                $sd[2]='s-done'; $ld[2]='l-done';
                $sd[3]='s-done'; $ld[3]='l-done';
                $sd[4]='s-pending'; $ld[4]='l-pending';
                $ln[1]='l-done'; $ln[2]='l-done';
            }
        }
        ?>
        <div class="ar-stepper-wrap">
            <div class="ar-stepper-title"><i class="fas fa-map-signs me-2" style="color:#8B0000;"></i>Request Progress Tracker</div>
            <div class="ar-steps">
                <?php for($i=1;$i<=5;$i++): ?>
                <div class="ar-step">
                    <div class="ar-step-dot <?php echo $sd[$i]; ?>">
                        <i class="<?php echo $si[$i]; ?>"></i>
                    </div>
                    <div class="ar-step-lbl <?php echo $ld[$i]; ?>"><?php echo $sl[$i]; ?></div>
                </div>
                <?php if($i<5): ?><div class="ar-step-line <?php echo $ln[$i]; ?>"></div><?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="ar-card h-100">
                    <div class="ar-section-label">Requester Information</div>
                    <div class="ar-info-row">
                        <p><strong>Name:</strong><?php echo htmlspecialchars($request['full_name']); ?></p>
                        <p><strong>Email:</strong><?php echo htmlspecialchars($request['email']); ?></p>
                        <p><strong>Phone:</strong><?php echo htmlspecialchars($request['phone']); ?></p>
                        <?php if (!empty($request['college_id'])): ?>
                        <p><strong>Department:</strong>
                            <span style="display:inline-flex;align-items:center;gap:4px;background:rgba(59,130,246,0.12);color:#1d4ed8;border-radius:4px;padding:2px 10px;font-size:0.78rem;font-weight:700;">
                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($request['college_id']); ?>
                            </span>
                        </p>
                        <?php elseif (!empty($request['campus_id'])): ?>
                        <?php
                        $detail_campus = 'Unknown Campus';
                        foreach (getCampuses() as $c) { if ($c['id'] == $request['campus_id']) { $detail_campus = $c['name']; break; } }
                        ?>
                        <p><strong>Campus:</strong><?php echo htmlspecialchars($detail_campus); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ar-card h-100">
                    <div class="ar-section-label">Request Information</div>
                    <div class="ar-info-row">
                        <p><strong>Type:</strong><?php echo $type_labels[$request['request_type']] ?? ucfirst($request['request_type']); ?></p>
                        <p><strong>Urgency:</strong><span class="ar-badge ar-badge-<?php echo $urgency_colors[$request['urgency']] ?? 'secondary'; ?>" style="margin-left:4px;"><?php echo ucfirst($request['urgency']); ?></span></p>
                        <p><strong>Date:</strong><?php echo formatDate($request['created_at'], 'M d, Y'); ?></p>
                        <?php if (!empty($request['group_id'])): ?>
                        <p><strong>Group ID:</strong><span style="font-family:monospace;background:rgba(107,114,128,0.10);color:#374151;border-radius:5px;padding:1px 7px;font-size:0.77rem;"><?php echo htmlspecialchars($request['group_id']); ?></span></p>
                        <?php endif; ?>
                        <?php if (!empty($request['receiving_method'])): ?>
                        <?php $rm = $request['receiving_method']; ?>
                        <p style="align-items:flex-start;flex-direction:column;gap:6px;">
                            <strong>Receiving Method:</strong>
                            <?php if ($request['status'] === 'pending'): ?>
                            <div style="display:flex;gap:8px;margin-top:4px;">
                                <form method="POST" action="requests.php?action=view&id=<?php echo $request['id']; ?>">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="action" value="change_receiving_method">
                                    <input type="hidden" name="receiving_method" value="delivery">
                                    <button type="submit" style="
                                        display:inline-flex;align-items:center;gap:6px;
                                        padding:6px 14px;border-radius:6px;font-size:0.80rem;font-weight:700;
                                        border:2px solid <?php echo $rm === 'delivery' ? '#1d4ed8' : 'rgba(0,0,0,0.12)'; ?>;
                                        background:<?php echo $rm === 'delivery' ? 'rgba(59,130,246,0.12)' : 'transparent'; ?>;
                                        color:<?php echo $rm === 'delivery' ? '#1d4ed8' : 'rgba(0,0,0,0.40)'; ?>;
                                        cursor:pointer;transition:all 0.15s;">
                                        <i class="fas fa-truck"></i> Delivery
                                    </button>
                                </form>
                                <form method="POST" action="requests.php?action=view&id=<?php echo $request['id']; ?>">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <input type="hidden" name="action" value="change_receiving_method">
                                    <input type="hidden" name="receiving_method" value="pickup">
                                    <button type="submit" style="
                                        display:inline-flex;align-items:center;gap:6px;
                                        padding:6px 14px;border-radius:6px;font-size:0.80rem;font-weight:700;
                                        border:2px solid <?php echo $rm === 'pickup' ? '#15803d' : 'rgba(0,0,0,0.12)'; ?>;
                                        background:<?php echo $rm === 'pickup' ? 'rgba(34,197,94,0.12)' : 'transparent'; ?>;
                                        color:<?php echo $rm === 'pickup' ? '#15803d' : 'rgba(0,0,0,0.40)'; ?>;
                                        cursor:pointer;transition:all 0.15s;">
                                        <i class="fas fa-walking"></i> Pickup
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <?php
                            $rm_icon  = $rm === 'delivery' ? 'fa-truck'   : 'fa-walking';
                            $rm_color = $rm === 'delivery' ? '#1d4ed8'    : '#15803d';
                            $rm_bg    = $rm === 'delivery' ? 'rgba(59,130,246,0.12)' : 'rgba(34,197,94,0.12)';
                            ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo $rm_bg; ?>;color:<?php echo $rm_color; ?>;border-radius:4px;padding:3px 12px;font-size:0.80rem;font-weight:700;margin-top:4px;">
                                <i class="fas <?php echo $rm_icon; ?>"></i> <?php echo ucfirst($rm); ?>
                            </span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Build detail units: prefer new model (each requests row = 1 unit), fall back to request_items
        $detail_units = [];
        if (count($group_view_reqs) > 0) {
            foreach ($group_view_reqs as $_du) {
                $_du_inv = !empty($_du['inventory_id']) ? findById(getInventory(), (int)$_du['inventory_id']) : null;
                $detail_units[] = [
                    'item_name'    => $_du_inv['item_name'] ?? ($_du['service_description'] ?? 'N/A'),
                    'qr_code_id'   => $_du['qr_code_id'] ?? ($_du_inv['qr_code_id'] ?? null),
                    'inventory_id' => $_du['inventory_id'] ?? null,
                    'req_num'      => $_du['request_number'] ?? '',
                ];
            }
        }
        // Fallback to legacy request_items if no new-model units found with QR
        if (empty(array_filter($detail_units, fn($u) => !empty($u['qr_code_id'])))) {
            $leg_items = getRequestItems($request['id']);
            if (!empty($leg_items)) {
                $detail_units = array_map(fn($ri) => [
                    'item_name'    => $ri['item_name'],
                    'qr_code_id'   => $ri['qr_code_id'] ?? null,
                    'inventory_id' => $ri['inventory_id'] ?? null,
                    'req_num'      => '',
                ], $leg_items);
            }
        }
        if (!empty($detail_units)):
        ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label"><?php echo ucfirst($request['request_type']); ?> Units <span style="font-weight:400;color:#bbb;">(<?php echo count($detail_units); ?> unit<?php echo count($detail_units) !== 1 ? 's' : ''; ?>)</span></div>
            <?php if ($request['request_type'] === 'borrow' && !empty($request['expected_return_date'])): ?>
            <p style="font-size:.85rem;color:#555;margin-bottom:12px;"><strong>Expected Return:</strong> <?php echo formatDate($request['expected_return_date']); ?></p>
            <?php endif; ?>
            <table style="width:100%;border-collapse:collapse;font-size:.83rem;">
                <thead>
                    <tr style="background:#f7f7f7;">
                        <th style="padding:7px 10px;text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#999;border-bottom:1px solid #e5e7eb;">#</th>
                        <th style="padding:7px 10px;text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#999;border-bottom:1px solid #e5e7eb;">Item</th>
                        <th style="padding:7px 10px;text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#999;border-bottom:1px solid #e5e7eb;">QR Code</th>
                        <th style="padding:7px 10px;text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#999;border-bottom:1px solid #e5e7eb;">Inventory ID</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detail_units as $di_idx => $di): ?>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <td style="padding:8px 10px;color:#999;font-size:.75rem;"><?php echo $di_idx + 1; ?></td>
                        <td style="padding:8px 10px;font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($di['item_name']); ?></td>
                        <td style="padding:8px 10px;">
                            <?php if (!empty($di['qr_code_id'])): ?>
                            <span style="font-family:monospace;background:rgba(139,0,0,0.07);color:#8B0000;border-radius:5px;padding:2px 7px;font-size:.77rem;"><?php echo htmlspecialchars($di['qr_code_id']); ?></span>
                            <?php else: ?>
                            <span style="color:#bbb;font-size:.77rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 10px;color:#777;font-size:.77rem;"><?php echo $di['inventory_id'] ? '#' . $di['inventory_id'] : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($request['request_type'] === 'borrow'): ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label">Borrow Details</div>
            <div class="ar-info-row">
                <p><strong>Item:</strong><?php echo htmlspecialchars($request['item_name']); ?></p>
                <p><strong>QR Code:</strong><span style="font-family:monospace;background:rgba(139,0,0,0.07);color:#8B0000;border-radius:5px;padding:1px 6px;font-size:0.8rem;"><?php echo htmlspecialchars($request['qr_code_id']); ?></span></p>
                <p><strong>Quantity:</strong><span style="font-weight:800;color:#8B0000;"><?php echo (int)$request['quantity_requested']; ?></span> <span style="font-size:.75rem;color:rgba(0,0,0,.4);">unit(s)</span></p>
                <?php if (!empty($request['expected_return_date'])): ?>
                <p><strong>Expected Return:</strong><?php echo formatDate($request['expected_return_date']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="ar-card mb-3">
            <div class="ar-section-label">Request Description</div>
            <div class="ar-desc-box"><?php echo nl2br(htmlspecialchars($request['service_description'] ?? $request['reason_for_request'] ?? 'No description provided')); ?></div>
        </div>

        <?php if ($request['status'] === 'pending'): ?>
        <div class="ar-card">
            <div class="ar-section-label">Action</div>
            <div class="ar-action-tabs" id="actionTabs">
                <button class="ar-tab-btn active" onclick="switchTab('approve-tab',this)">Approve</button>
                <button class="ar-tab-btn" onclick="switchTab('disapprove-tab',this)">Disapprove</button>
            </div>
            <div class="ar-tab-pane active" id="approve-tab">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn ar-btn-success"><i class="fas fa-check me-1"></i> Approve Request</button>
                </form>
            </div>
            <div class="ar-tab-pane" id="disapprove-tab">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="disapprove">
                    <button type="submit" class="btn ar-btn-danger"><i class="fas fa-times me-1"></i> Disapprove Request</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Delivery action button — shown for approved borrow/item requests, only before dispatched
        $rdel_action  = $request['delivery_status'] ?? null;
        $recv_m       = $request['receiving_method'] ?? 'delivery';
        $is_borrow_item = in_array($request['request_type'], ['borrow', 'item']);
        if ($request['status'] === 'approved' && $is_borrow_item && $rdel_action !== 'out_for_delivery' && $rdel_action !== 'delivered'):
            $step4_label = ($recv_m === 'pickup') ? 'Ready for Pickup' : 'Out for Delivery';
            $step4_icon  = ($recv_m === 'pickup') ? 'fa-store' : 'fa-truck';
        ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label">Delivery Actions</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <form method="POST" action="requests.php?action=view&id=<?php echo $request['id']; ?>">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="mark_out_for_delivery">
                    <button type="submit" class="btn ar-btn-primary" style="display:inline-flex;align-items:center;gap:7px;">
                        <i class="fas <?php echo $step4_icon; ?>"></i> Proceed to <?php echo $step4_label; ?>
                        <span style="font-size:0.72rem;opacity:0.80;font-weight:500;">&amp; Email User</span>
                    </button>
                </form>
            </div>
            <div style="margin-top:10px;font-size:0.76rem;color:rgba(0,0,0,0.40);">
                <i class="fas fa-envelope me-1"></i>An email notification will be automatically sent to <strong><?php echo htmlspecialchars($request['email']); ?></strong> when you proceed.
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Mark Returned — borrow requests that have been delivered
        if ($request['request_type'] === 'borrow' && $request['status'] === 'delivered'):
        ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label">Return</div>
            <form method="POST" action="requests.php?action=view&id=<?php echo $request['id']; ?>">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                <input type="hidden" name="action" value="mark_returned">
                <button type="submit" class="btn ar-btn-success" onclick="return confirm('Confirm item has been returned?');">
                    <i class="fas fa-undo me-1"></i> Mark as Returned
                </button>
            </form>
            <div style="margin-top:8px;font-size:0.76rem;color:rgba(0,0,0,0.40);">This will mark the borrow record as returned and set the item back to available.</div>
        </div>
        <?php endif; ?>

        <?php
        // Mark Completed — service requests after approved, item requests after delivered
        $show_complete = ($request['request_type'] === 'service' && $request['status'] === 'approved')
                      || ($request['request_type'] === 'item'    && $request['status'] === 'delivered');
        if ($show_complete):
        ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label">Complete</div>
            <form method="POST" action="requests.php?action=view&id=<?php echo $request['id']; ?>">
                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                <input type="hidden" name="action" value="mark_completed">
                <button type="submit" class="btn ar-btn-success" onclick="return confirm('Mark this request as completed?');">
                    <i class="fas fa-check-double me-1"></i> Mark as Completed
                </button>
            </form>
            <?php if ($request['request_type'] === 'service'): ?>
            <div style="margin-top:8px;font-size:0.76rem;color:rgba(0,0,0,0.40);">This will restore the item status to available.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="mt-3">
            <a href="requests.php" class="btn ar-btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>

        <!-- QR Sticker Overlay -->
        <div class="ar-sticker-overlay" id="arStickerPrint">
            <div class="ar-sticker-sheet-wrap">
                <div class="ar-sticker-sheet-hdr" style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;">
                    <div>
                        <div style="font-weight:800;font-size:1rem;color:#0f172a;"><i class="fas fa-qrcode me-2" style="color:#8B0000;"></i>Print QR Sticker Sheet</div>
                        <div style="font-size:.78rem;color:rgba(0,0,0,.44);margin-top:2px;"><?php echo max(1, count($sticker_units)); ?> sticker(s) &mdash; one per unit with its unique QR code &mdash; print on label paper (A4 / Letter)</div>
                    </div>
                    <button onclick="document.getElementById('arStickerPrint').classList.remove('open')" style="border:none;background:none;font-size:1.3rem;cursor:pointer;color:rgba(0,0,0,.4);line-height:1;padding:2px 6px;">&times;</button>
                </div>
                <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:16px;gap:8px;">
                    <div class="ar-sticker-actions" style="display:flex;gap:8px;">
                        <button class="ar-sticker-btn" onclick="printStickerWindow()"><i class="fas fa-print me-1"></i>Print</button>
                        <button class="ar-sticker-btn" onclick="document.getElementById('arStickerPrint').classList.remove('open')" style="opacity:.6;"><i class="fas fa-times me-1"></i>Close</button>
                    </div>
                </div>
                <div class="ar-sticker-grid" id="stickerGrid"></div>
            </div>
        </div>

        <script>
        function switchTab(tabId, btn) {
            document.querySelectorAll('.ar-tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.ar-tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }

        var _sd = {
            institution: 'Pampanga State University',
            short: 'PSU',
            item:      <?php echo json_encode($request['item_name']); ?>,
            category:  <?php echo json_encode($request['item_category']); ?>,
            reqno:     <?php echo json_encode($request['request_number']); ?>,
            requester: <?php echo json_encode($request['full_name']); ?>
        };
        var _unitQRs = <?php echo json_encode(array_values($sticker_units)); ?>;

        function _buildSticker(unit, unitNum, totalUnits) {
            var qr       = unit ? unit.qr        : <?php echo json_encode($request['qr_code_id']); ?>;
            var loc      = unit ? unit.location   : <?php echo json_encode($request['item_location']); ?>;
            var cond     = unit ? unit.condition  : <?php echo json_encode($request['item_condition']); ?>;
            var itemName = (unit && unit.item_name) ? unit.item_name : _sd.item;
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(qr);
            var unitLabel = totalUnits > 1 ? ' (Unit ' + unitNum + ' of ' + totalUnits + ')' : '';
            return '<div class="ar-sticker">'
                + '<div class="ar-sticker-top">' + _sd.short + ' &mdash; Asset Label<span>' + _sd.institution + '</span></div>'
                + '<div class="ar-sticker-qr"><img src="' + qrUrl + '" alt="QR" loading="eager" width="90" height="90"></div>'
                + '<div class="ar-sticker-body">'
                +   '<div class="ar-sticker-name">' + _esc(itemName) + _esc(unitLabel) + '</div>'
                +   '<div class="ar-sticker-row"><strong>Location:</strong><span>' + _esc(loc) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Category:</strong><span>' + _esc(_sd.category) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Condition:</strong><span>' + _esc(cond) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Issued To:</strong><span>' + _esc(_sd.requester) + '</span></div>'
                +   '<div class="ar-sticker-code">' + _esc(qr) + '</div>'
                + '</div>'
                + '<div class="ar-sticker-foot">Req: ' + _esc(_sd.reqno) + ' &bull; ManageMo</div>'
                + '</div>';
        }
        function _esc(str) {
            var d = document.createElement('div');
            d.textContent = str || '';
            return d.innerHTML;
        }
        function _renderStickers() {
            var units = _unitQRs.length > 0 ? _unitQRs : [null];
            var h = '';
            units.forEach(function(unit, i) { h += _buildSticker(unit, i + 1, units.length); });
            document.getElementById('stickerGrid').innerHTML = h;
        }
        function openStickerSheet() {
            _renderStickers();
            document.getElementById('arStickerPrint').classList.add('open');
        }
        function printStickerWindow() {
            var grid = document.getElementById('stickerGrid').innerHTML;
            var win = window.open('', '_blank', 'width=850,height=700,scrollbars=yes');
            win.document.write('<!DOCTYPE html><html><head>'
                + '<meta charset="UTF-8">'
                + '<title>QR Stickers &mdash; ' + _esc(_sd.reqno) + '</title>'
                + '<style>'
                + '*{box-sizing:border-box;margin:0;padding:0;}'
                + 'body{background:#fff;padding:8mm;font-family:Arial,Helvetica,sans-serif;}'
                + '.ar-sticker-grid{display:grid;grid-template-columns:repeat(3,60mm);gap:4mm;}'
                + '.ar-sticker{background:#fff;border:1.5px solid #999;border-radius:6px;overflow:hidden;page-break-inside:avoid;break-inside:avoid;}'
                + '.ar-sticker-top{background:#8B0000;color:#fff;text-align:center;padding:5px 4px 4px;font-size:7px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;}'
                + '.ar-sticker-top span{display:block;font-size:6px;font-weight:400;opacity:.82;margin-top:1px;}'
                + '.ar-sticker-qr{padding:8px 4px 3px;text-align:center;}'
                + '.ar-sticker-qr img{width:80px;height:80px;display:block;margin:0 auto;}'
                + '.ar-sticker-body{padding:0 6px 6px;}'
                + '.ar-sticker-name{font-size:8px;font-weight:800;text-align:center;color:#0f172a;margin-bottom:4px;border-bottom:1px solid rgba(0,0,0,.07);padding-bottom:4px;line-height:1.3;}'
                + '.ar-sticker-row{display:flex;gap:3px;font-size:6.5px;margin-bottom:2px;color:#333;}'
                + '.ar-sticker-row strong{color:#8B0000;min-width:44px;flex-shrink:0;}'
                + '.ar-sticker-code{margin-top:4px;background:#f7f7f7;border-radius:3px;padding:2px 4px;text-align:center;font-family:monospace;font-size:6.5px;color:#8B0000;font-weight:700;letter-spacing:.3px;}'
                + '.ar-sticker-foot{background:rgba(139,0,0,.05);border-top:1px solid rgba(139,0,0,.1);padding:2px 5px;text-align:center;font-size:6px;color:rgba(0,0,0,.38);}'
                + '@media print{body{padding:6mm;}@page{margin:6mm;size:A4;}}'
                + '</style></head><body>'
                + '<div class="ar-sticker-grid">' + grid + '</div>'
                + '<script>window.onload=function(){window.print();};<\/script>'
                + '</body></html>');
            win.document.close();
        }
        </script>

    <?php else: ?>
        <!-- List Requests -->
        <?php
        $view_mode   = $_GET['view'] ?? 'table';
        $active_tab  = $_GET['tab']  ?? 'all';
        // Count per tab (using all requests, not paginated)
        $all_reqs_raw  = getRequests();
        $count_all     = count($all_reqs_raw);
        $count_item    = count(array_filter($all_reqs_raw, fn($r) => $r['request_type'] === 'item'));
        $count_borrow  = count(array_filter($all_reqs_raw, fn($r) => $r['request_type'] === 'borrow'));
        $count_service = count(array_filter($all_reqs_raw, fn($r) => $r['request_type'] === 'service'));
        ?>
        <!-- Type tabs -->
        <div class="ar-type-tabs">
            <a class="ar-type-tab <?php echo $active_tab==='all'?'active':''; ?>" href="requests.php?tab=all<?php echo $status_filter?'&status='.$status_filter:''; ?>">
                <i class="fas fa-list"></i> All Requests <span class="ar-tab-count"><?php echo $count_all; ?></span>
            </a>
            <a class="ar-type-tab <?php echo $active_tab==='item'?'active':''; ?>" href="requests.php?tab=item<?php echo $status_filter?'&status='.$status_filter:''; ?>">
                <i class="fas fa-box"></i> Item Requests <span class="ar-tab-count"><?php echo $count_item; ?></span>
            </a>
            <a class="ar-type-tab <?php echo $active_tab==='borrow'?'active':''; ?>" href="requests.php?tab=borrow<?php echo $status_filter?'&status='.$status_filter:''; ?>">
                <i class="fas fa-hand-holding"></i> Borrow Requests <span class="ar-tab-count"><?php echo $count_borrow; ?></span>
            </a>
            <a class="ar-type-tab <?php echo $active_tab==='service'?'active':''; ?>" href="requests.php?tab=service<?php echo $status_filter?'&status='.$status_filter:''; ?>">
                <i class="fas fa-tools"></i> Service Requests <span class="ar-tab-count"><?php echo $count_service; ?></span>
            </a>
        </div>
        <?php
        // Re-apply filters with tab-forced type
        $filtered_requests = $all_reqs_raw;
        if ($status_filter) {
            $filtered_requests = filterByColumn($filtered_requests, 'status', $status_filter);
        }
        if ($active_tab === 'item') {
            $filtered_requests = array_values(array_filter($filtered_requests, fn($r) => $r['request_type'] === 'item'));
        } elseif ($active_tab === 'borrow') {
            $filtered_requests = array_values(array_filter($filtered_requests, fn($r) => $r['request_type'] === 'borrow'));
        } elseif ($active_tab === 'service') {
            $filtered_requests = array_values(array_filter($filtered_requests, fn($r) => $r['request_type'] === 'service'));
        }
        // Group then paginate
        $tab_grouped = [];
        foreach ($filtered_requests as $rr) {
            $gk = !empty($rr['group_id']) ? 'gid:'.$rr['group_id'] : 'id:'.$rr['id'];
            if (!isset($tab_grouped[$gk])) $tab_grouped[$gk] = ['rows'=>[],'first'=>$rr];
            $tab_grouped[$gk]['rows'][] = $rr;
        }
        $tab_grouped = array_values($tab_grouped);
        $total       = count($tab_grouped);
        $total_pages = max(1, ceil($total / ITEMS_PER_PAGE));
        $offset      = ($page - 1) * ITEMS_PER_PAGE;
        $requests    = [];
        foreach (array_slice($tab_grouped, $offset, ITEMS_PER_PAGE) as $grp) {
            $rr   = $grp['first'];
            $rows = $grp['rows'];
            $u    = findById($users_data, $rr['user_id']);
            $grp_names = [];
            foreach ($rows as $_r) {
                $_inv = !empty($_r['inventory_id']) ? findById($inventory_data, (int)$_r['inventory_id']) : null;
                $n = $_inv['item_name'] ?? _reqFirstItemName($_req_items_map, (int)$_r['id'], $inventory_data);
                if ($n && $n !== 'Unknown Item') $grp_names[] = $n;
            }
            $grp_names = array_values(array_unique($grp_names));
            $requests[] = array_merge($rr, [
                'full_name'  => $u['full_name']  ?? 'Unknown',
                'email'      => $u['email']      ?? 'N/A',
                'campus_id'  => $u['campus_id']  ?? null,
                'college_id' => $u['college_id'] ?? null,
                'item_name'  => !empty($grp_names) ? implode(', ', array_slice($grp_names,0,3)).(count($grp_names)>3?'…':'') : 'N/A',
                'qr_code_id' => $rr['qr_code_id'] ?? _reqFirstQR($_req_items_map, (int)$rr['id']),
                'unit_count' => count($rows),
                'group_id'   => $rr['group_id'] ?? null,
            ]);
        }
        ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <?php if ($active_tab !== 'service' && $active_tab !== 'item'): ?>
            <div class="ar-view-toggle">
                <button class="ar-vt-btn <?php echo $view_mode==='table'?'active':''; ?>" onclick="setView('table')"><i class="fas fa-table me-1"></i>Table</button>
                <button class="ar-vt-btn <?php echo $view_mode==='tracker'?'active':''; ?>" onclick="setView('tracker')"><i class="fas fa-route me-1"></i>Tracker</button>
            </div>
            <?php else: ?>
            <div></div>
            <?php endif; ?>
        </div>
        <div class="ar-filter-card">
            <form method="GET" action="" class="d-flex align-items-end flex-wrap gap-3 w-100">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                <div>
                    <div class="ar-filter-label">Status</div>
                    <select class="form-select" name="status" onchange="this.form.submit()" style="min-width:150px;">
                        <option value="">All Status</option>
                        <?php foreach (['pending','approved','disapproved','delivered'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $status_filter===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($active_tab === 'all'): ?>
                <div>
                    <div class="ar-filter-label">Type</div>
                    <select class="form-select" name="type" onchange="this.form.submit()" style="min-width:160px;">
                        <option value="">All Types</option>
                        <option value="item"    <?php echo $type_filter==='item'?   'selected':''; ?>>Item Request</option>
                        <option value="borrow"  <?php echo $type_filter==='borrow'? 'selected':''; ?>>Borrow Request</option>
                        <option value="service" <?php echo $type_filter==='service'?'selected':''; ?>>Service Request</option>
                    </select>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <?php
        $status_colors  = ['pending'=>'warning','approved'=>'success','disapproved'=>'danger','delivered'=>'info','returned'=>'primary','completed'=>'success'];
        $urgency_colors = ['low'=>'info','medium'=>'warning','high'=>'danger','critical'=>'danger'];
        $type_labels    = ['item'=>'Item Request','borrow'=>'Borrow Request','service'=>'Service Request'];
        ?>
        <?php if ($active_tab === 'item'): ?>
        <!-- Item Requests dedicated table -->
        <div class="ar-table-card">
            <table class="ar-table">
                <thead><tr>
                    <th>Request ID</th><th>Requester</th><th>Department</th><th>Item Requested</th><th>Urgency</th><th>Status</th><th>Date</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (count($requests) > 0): foreach ($requests as $req): ?>
                <tr>
                    <td><span class="ar-req-id"><?php echo htmlspecialchars($req['request_number']); ?></span></td>
                    <td>
                        <div style="font-weight:700;font-size:0.87rem;"><?php echo htmlspecialchars($req['full_name']); ?></div>
                        <div style="font-size:0.76rem;color:rgba(0,0,0,0.45);"><?php echo htmlspecialchars($req['email']); ?></div>
                    </td>
                    <td>
                        <?php if (!empty($req['college_id'])): ?>
                        <span class="ar-badge" style="background:rgba(59,130,246,0.12);color:#1d4ed8;border:1px solid rgba(59,130,246,0.20);font-size:0.73rem;">
                            <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($req['college_id']); ?>
                        </span>
                        <?php elseif (!empty($req['campus_id'])): ?>
                        <?php $req_campus='Unknown'; foreach(getCampuses() as $c){if($c['id']==$req['campus_id']){$req_campus=$c['name'];break;}} ?>
                        <span style="font-size:0.78rem;color:rgba(0,0,0,0.45);"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($req_campus); ?></span>
                        <?php else: ?><span style="font-size:0.78rem;color:rgba(0,0,0,0.30);">—</span><?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size:0.87rem;font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($req['item_name']); ?></div>
                        <?php if (!empty($req['qr_code_id']) && $req['qr_code_id'] !== 'N/A'): ?>
                        <div style="font-size:0.72rem;font-family:monospace;color:rgba(139,0,0,0.70);margin-top:2px;"><?php echo htmlspecialchars($req['qr_code_id']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="ar-badge ar-badge-<?php echo $urgency_colors[$req['urgency']] ?? 'secondary'; ?>"><?php echo ucfirst($req['urgency']); ?></span></td>
                    <td><span class="ar-badge ar-badge-<?php echo $status_colors[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                    <td style="color:rgba(0,0,0,0.50);font-size:0.81rem;"><?php echo formatDate($req['created_at'], 'M d, Y'); ?></td>
                    <td>
                        <?php $view_href_item = !empty($req['group_id']) ? 'requests.php?action=view&group_id='.urlencode($req['group_id']).'&tab=item' : 'requests.php?action=view&id='.$req['id'].'&tab=item'; ?>
                        <a href="<?php echo $view_href_item; ?>" class="ar-btn-view"><i class="fas fa-eye"></i> View<?php if ($req['unit_count'] > 1): ?> <span style="font-size:.70rem;background:rgba(139,0,0,.13);color:#8B0000;border-radius:3px;padding:0 5px;"><?php echo $req['unit_count']; ?></span><?php endif; ?></a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8"><div class="ar-empty"><i class="fas fa-box-open"></i>No item requests found</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($active_tab === 'service'): ?>
        <!-- Service Requests dedicated table -->
        <div class="ar-table-card">
            <table class="ar-table">
                <thead><tr>
                    <th>Request ID</th><th>Requester</th><th>Department</th><th>Service Description</th><th>Urgency</th><th>Status</th><th>Date</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (count($requests) > 0): foreach ($requests as $req): ?>
                <tr>
                    <td><span class="ar-req-id"><?php echo htmlspecialchars($req['request_number']); ?></span></td>
                    <td>
                        <div style="font-weight:700;font-size:0.87rem;"><?php echo htmlspecialchars($req['full_name']); ?></div>
                        <div style="font-size:0.76rem;color:rgba(0,0,0,0.45);"><?php echo htmlspecialchars($req['email']); ?></div>
                    </td>
                    <td>
                        <?php if (!empty($req['college_id'])): ?>
                        <span class="ar-badge" style="background:rgba(59,130,246,0.12);color:#1d4ed8;border:1px solid rgba(59,130,246,0.20);font-size:0.73rem;">
                            <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($req['college_id']); ?>
                        </span>
                        <?php elseif (!empty($req['campus_id'])): ?>
                        <?php $req_campus='Unknown'; foreach(getCampuses() as $c){if($c['id']==$req['campus_id']){$req_campus=$c['name'];break;}} ?>
                        <span style="font-size:0.78rem;color:rgba(0,0,0,0.45);"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($req_campus); ?></span>
                        <?php else: ?><span style="font-size:0.78rem;color:rgba(0,0,0,0.30);">—</span><?php endif; ?>
                    </td>
                    <td style="max-width:260px;">
                        <div style="font-size:0.84rem;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;" title="<?php echo htmlspecialchars($req['service_description'] ?? $req['reason_for_request'] ?? ''); ?>">
                            <?php echo htmlspecialchars(mb_strimwidth($req['service_description'] ?? $req['reason_for_request'] ?? 'No description', 0, 70, '…')); ?>
                        </div>
                    </td>
                    <td><span class="ar-badge ar-badge-<?php echo $urgency_colors[$req['urgency']] ?? 'secondary'; ?>"><?php echo ucfirst($req['urgency']); ?></span></td>
                    <td><span class="ar-badge ar-badge-<?php echo $status_colors[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                    <td style="color:rgba(0,0,0,0.50);font-size:0.81rem;"><?php echo formatDate($req['created_at'], 'M d, Y'); ?></td>
                    <td><a href="requests.php?action=view&id=<?php echo $req['id']; ?>&tab=service" class="ar-btn-view"><i class="fas fa-eye"></i> View</a></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8"><div class="ar-empty"><i class="fas fa-tools"></i>No service requests found</div></td></tr>

                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div id="tableView"><div class="ar-table-card">
            <table class="ar-table">
                <thead><tr>
                    <th>Request ID</th><th>Requester</th><th>Department</th><th>Type</th><th>Urgency</th><th>Status</th><th>Date</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php if (count($requests) > 0):
                    foreach ($requests as $req): ?>
                <tr>
                    <td><span class="ar-req-id"><?php echo htmlspecialchars($req['request_number']); ?></span></td>
                    <td>
                        <div style="font-weight:700;font-size:0.87rem;"><?php echo htmlspecialchars($req['full_name']); ?></div>
                        <div style="font-size:0.76rem;color:rgba(0,0,0,0.45);"><?php echo htmlspecialchars($req['email']); ?></div>
                    </td>
                    <td>
                        <?php if (!empty($req['college_id'])): ?>
                        <span class="ar-badge" style="background:rgba(59,130,246,0.12);color:#1d4ed8;border:1px solid rgba(59,130,246,0.20);font-size:0.73rem;">
                            <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($req['college_id']); ?>
                        </span>
                        <?php elseif (!empty($req['campus_id'])): ?>
                        <?php
                        $req_campus = 'Unknown Campus';
                        foreach (getCampuses() as $c) { if ($c['id'] == $req['campus_id']) { $req_campus = $c['name']; break; } }
                        ?>
                        <span style="font-size:0.78rem;color:rgba(0,0,0,0.45);"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($req_campus); ?></span>
                        <?php else: ?>
                        <span style="font-size:0.78rem;color:rgba(0,0,0,0.30);">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:rgba(0,0,0,0.55);"><?php echo $type_labels[$req['request_type']] ?? ucfirst($req['request_type']); ?></td>
                    <td><span class="ar-badge ar-badge-<?php echo $urgency_colors[$req['urgency']] ?? 'secondary'; ?>"><?php echo ucfirst($req['urgency']); ?></span></td>
                    <td><span class="ar-badge ar-badge-<?php echo $status_colors[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                    <td style="color:rgba(0,0,0,0.50);font-size:0.81rem;"><?php echo formatDate($req['created_at'], 'M d, Y'); ?></td>
                    <td>
                        <?php $view_href_all = !empty($req['group_id']) ? 'requests.php?action=view&group_id='.urlencode($req['group_id']) : 'requests.php?action=view&id='.$req['id']; ?>
                        <a href="<?php echo $view_href_all; ?>" class="ar-btn-view"><i class="fas fa-eye"></i> View<?php if ($req['unit_count'] > 1): ?> <span style="font-size:.70rem;background:rgba(139,0,0,.13);color:#8B0000;border-radius:3px;padding:0 5px;"><?php echo $req['unit_count']; ?></span><?php endif; ?></a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8"><div class="ar-empty"><i class="fas fa-inbox"></i>No requests found</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <!-- hidden by default if tracker mode -->
        </div>
        <?php endif; ?>

        <!-- Tracker card view -->
        <?php if ($active_tab !== 'service' && $active_tab !== 'item'): ?>
        <div id="trackerView" style="display:none;">
        <?php
        $type_labels_t = ['item'=>'Item Request','borrow'=>'Borrow','service'=>'Service'];
        $status_colors_t = ['pending'=>'ar-badge-warning','approved'=>'ar-badge-success','disapproved'=>'ar-badge-danger'];
        if(count($requests)>0): foreach($requests as $req):
            $rs = $req['status'];
            $rd = $req['delivery_status'] ?? null;
            $is_svc = ($req['request_type'] === 'service');
            // Steps
            $tsd=[1=>'s-done',2=>'',3=>'',4=>'',5=>'']; $tld=[1=>'l-done',2=>'',3=>'',4=>'',5=>'']; $tln=[1=>'',2=>'',3=>'',4=>''];
            if ($is_svc) {
                $tsi=[1=>'fa-check',2=>'fa-search',3=>'fa-check',4=>'fa-wrench',5=>'fa-flag-checkered'];
                $tsl=[1=>'Submitted',2=>'Under Review',3=>'Approved',4=>'In Progress',5=>'Completed'];
                if($rs==='pending'){$tsd[2]='s-pending';$tld[2]='l-pending';$tln[1]='l-done';}
                elseif($rs==='disapproved'){$tsd[2]='s-done';$tld[2]='l-done';$tsd[3]='s-rejected';$tld[3]='l-rejected';$tsl[3]='Disapproved';$tsi[3]='fa-times';$tln[1]='l-done';}
                elseif($rs==='completed'){foreach([2,3,4,5] as $x){$tsd[$x]='s-done';$tld[$x]='l-done';}foreach([1,2,3,4] as $x){$tln[$x]='l-done';}}
                elseif($rs==='approved'){$tsd[2]='s-done';$tld[2]='l-done';$tsd[3]='s-done';$tld[3]='l-done';$tsd[4]='s-active';$tld[4]='l-active';$tln[1]='l-done';$tln[2]='l-done';$tln[3]='l-done';}
            } else {
                $tsi=[1=>'fa-check',2=>'fa-search',3=>'fa-check',4=>'fa-truck',5=>'fa-flag-checkered'];
                $tsl=[1=>'Submitted',2=>'Under Review',3=>'Approved',4=>'Out for Delivery',5=>'Delivered'];
                if($rs==='pending'){$tsd[2]='s-pending';$tld[2]='l-pending';$tln[1]='l-done';}
                elseif($rs==='disapproved'){$tsd[2]='s-done';$tld[2]='l-done';$tsd[3]='s-rejected';$tld[3]='l-rejected';$tsl[3]='Disapproved';$tsi[3]='fa-times';$tln[1]='l-done';}
                elseif($rd==='delivered'){foreach([2,3,4,5] as $x){$tsd[$x]='s-done';$tld[$x]='l-done';}foreach([1,2,3,4] as $x){$tln[$x]='l-done';}}
                elseif($rd==='out_for_delivery'){$tsd[2]='s-done';$tld[2]='l-done';$tsd[3]='s-done';$tld[3]='l-done';$tsd[4]='s-active';$tld[4]='l-active';$tln[1]='l-done';$tln[2]='l-done';$tln[3]='l-done';}
                else{$tsd[2]='s-done';$tld[2]='l-done';$tsd[3]='s-done';$tld[3]='l-done';$tsd[4]='s-pending';$tld[4]='l-pending';$tln[1]='l-done';$tln[2]='l-done';}
            }
            $initials = strtoupper(substr($req['full_name'],0,1));
            $cols=['#8B0000','#1d4ed8','#15803d','#b45309','#7c3aed'];
            $col=$cols[crc32($req['user_id'])%count($cols)];
        ?>
        <div class="ar-tracker-card">
            <div class="ar-tracker-head">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:6px;background:<?php echo $col;?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.85rem;flex-shrink:0;"><?php echo $initials;?></div>
                    <div>
                        <div style="font-weight:700;font-size:.88rem;color:#0f172a;"><?php echo htmlspecialchars($req['full_name']);?></div>
                        <div style="font-size:.74rem;color:#94a3b8;margin-top:1px;">
                            <code style="background:rgba(139,0,0,.07);color:#8B0000;border-radius:4px;padding:1px 6px;font-size:.71rem;"><?php echo $req['request_number'];?></code>
                            &nbsp;<?php echo $type_labels_t[$req['request_type']]??ucfirst($req['request_type']);?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="ar-badge <?php echo $status_colors_t[$rs]??'ar-badge-secondary';?>"><?php echo ucfirst($rs);?></span>
                    <?php $trk_href = !empty($req['group_id']) ? 'requests.php?action=view&group_id='.urlencode($req['group_id']) : 'requests.php?action=view&id='.$req['id']; ?>
                    <a href="<?php echo $trk_href;?>" class="ar-btn-view" style="font-size:.77rem;"><i class="fas fa-eye"></i> View<?php if ($req['unit_count'] > 1): ?> <span style="font-size:.70rem;background:rgba(139,0,0,.13);color:#8B0000;border-radius:3px;padding:0 5px;"><?php echo $req['unit_count'];?></span><?php endif; ?></a>
                </div>
            </div>
            <div class="ar-tracker-body">
                <div class="ar-steps">
                    <?php for($i=1;$i<=5;$i++): ?>
                    <div class="ar-step">
                        <div class="ar-step-dot <?php echo $tsd[$i];?>"><i class="fas <?php echo $tsi[$i];?>"></i></div>
                        <div class="ar-step-lbl <?php echo $tld[$i];?>"><?php echo $tsl[$i];?></div>
                    </div>
                    <?php if($i<5): ?><div class="ar-step-line <?php echo $tln[$i];?>"></div><?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ar-empty"><i class="fas fa-inbox"></i>No requests found</div>
        <?php endif; ?>
        </div>
        <?php endif; // end tracker tab guard ?>

        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i===(int)$page?'active':''; ?>">
                    <a class="page-link" href="requests.php?page=<?php echo $i; ?>&tab=<?php echo htmlspecialchars($active_tab); ?><?php echo $status_filter?'&status='.$status_filter:''; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<script>
function setView(mode) {
    document.getElementById('tableView').style.display  = mode==='table'   ? 'block' : 'none';
    document.getElementById('trackerView').style.display = mode==='tracker' ? 'block' : 'none';
    document.querySelectorAll('.ar-vt-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
(function(){
    var mode = '<?php echo $view_mode ?? 'table'; ?>';
    if(mode==='tracker'){
        document.getElementById('tableView').style.display='none';
        document.getElementById('trackerView').style.display='block';
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
