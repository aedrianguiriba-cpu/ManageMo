<?php
$page_title = 'Submit Request';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();
$campus_id = $current_user['campus_id'];

// Check if item_id is passed from inventory page
$auto_fill_item = null;
$auto_fill_item_id = null;
if (isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    $all_items = getInventory();
    foreach ($all_items as $item) {
        if ($item['id'] === $item_id && $item['status'] === 'available') {
            $auto_fill_item = $item['item_name'];
            $auto_fill_item_id = $item['id'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type     = sanitizeInput($_POST['request_type']);
    $urgency          = in_array($_POST['urgency'] ?? '', ['low','medium','high','critical']) ? $_POST['urgency'] : 'medium';
    $receiving_method = in_array($_POST['receiving_method'] ?? '', ['delivery','pickup']) ? $_POST['receiving_method'] : null;
    $safe_type        = in_array($request_type, ['borrow','item','service']) ? $request_type : 'borrow';

    $cart_items = json_decode($_POST['items_json'] ?? '[]', true);
    if (!is_array($cart_items) || empty($cart_items)) {
        $submit_error = 'No items in your request. Please add at least one item.';
    } else {
        $errors = [];
        foreach ($cart_items as $entry) {
            $request_number = dbNextRequestNumber();
            $payload = [
                'request_number'   => $request_number,
                'user_id'          => $current_user['id'],
                'request_type'     => $safe_type,
                'urgency'          => $urgency,
                'receiving_method' => $receiving_method,
                'status'           => 'pending',
            ];

            if ($safe_type === 'borrow') {
                $inv_id = !empty($entry['inventory_id']) ? (int)$entry['inventory_id'] : null;
                $payload['inventory_id']         = $inv_id;
                $payload['reason_for_request']   = sanitizeInput($entry['reason'] ?? '');
                $payload['expected_return_date']  = !empty($entry['return_date']) ? $entry['return_date'] : null;
                $payload['quantity_requested']   = max(1, (int)($entry['qty'] ?? 1));
            } elseif ($safe_type === 'item') {
                $name = sanitizeInput($entry['name'] ?? '');
                $qty  = max(1, (int)($entry['qty'] ?? 1));
                $payload['reason_for_request']  = sanitizeInput($entry['reason'] ?? '');
                $payload['service_description'] = $name . ($qty > 1 ? ' - Qty: ' . $qty : '');
                $payload['quantity_requested']  = $qty;
            } elseif ($safe_type === 'service') {
                $svc_type = sanitizeInput($entry['service_type'] ?? '');
                $svc_desc = sanitizeInput($entry['description'] ?? '');
                $payload['inventory_id']        = !empty($entry['item_id']) ? (int)$entry['item_id'] : null;
                $payload['service_description'] = $svc_type ? "[$svc_type] $svc_desc" : $svc_desc;
                $payload['quantity_requested']  = 1;
            }

            $result = dbCreateRequest($payload);
            if (!$result['success']) {
                $errors[] = $result['error'];
            } else {
                logActivity($current_user['id'], 'CREATE', "Submitted $safe_type request", 'requests', $result['row']['id'] ?? 0);
            }
        }

        if (empty($errors)) {
            $count = count($cart_items);
            redirectWithMessage('my-requests.php', ($count > 1 ? $count . ' requests' : 'Request') . ' submitted successfully!', 'success');
        } else {
            $submit_error = 'Some requests failed to save: ' . implode('; ', $errors);
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
$all_inventory  = getInventory();
$inventory_items = filterByColumn($all_inventory, 'campus_id', $campus_id);
usort($inventory_items, function($a, $b) { return strcmp($a['item_name'], $b['item_name']); });

// Item request catalog — all inventory items regardless of status
$all_sorted = $all_inventory;
usort($all_sorted, function($a, $b) { return strcmp($a['item_name'], $b['item_name']); });

$catalog_by_category = [];
foreach ($all_sorted as $item) {
    $category = $item['category'] ?? 'Other';
    $catalog_by_category[$category][] = $item;
}

// Borrow catalog includes available + borrowed items so users can see upcoming availability
$borrow_records_all = getBorrowRecords();
$item_avail_data = [];
foreach ($borrow_records_all as $br) {
    if (in_array($br['status'], ['active', 'overdue']) && empty($br['actual_return_date'])) {
        $iid = $br['inventory_id'];
        if (!isset($item_avail_data[$iid])) $item_avail_data[$iid] = [];
        $item_avail_data[$iid][] = [
            'return_date' => $br['expected_return_date'],
            'is_overdue'  => $br['status'] === 'overdue',
        ];
    }
}
$item_avail_json = json_encode($item_avail_data);

$borrow_catalog = [];
$borrowable = array_filter($all_inventory, function($item) {
    return in_array($item['status'], ['available', 'borrowed']);
});
usort($borrowable, function($a, $b) { return strcmp($a['item_name'], $b['item_name']); });
foreach ($borrowable as $item) {
    $cat = $item['category'] ?? 'Other';
    if (!isset($borrow_catalog[$cat])) $borrow_catalog[$cat] = [];
    $borrow_catalog[$cat][] = $item;
}

displayMessage();
if (!empty($submit_error)): ?>
<div style="background:rgba(220,38,38,0.07);border:1px solid rgba(220,38,38,0.30);border-radius:8px;padding:14px 18px;margin-bottom:18px;color:#dc2626;font-size:0.88rem;font-weight:600;display:flex;align-items:center;gap:10px;">
    <i class="fas fa-exclamation-circle"></i>
    <span><?php echo htmlspecialchars($submit_error); ?></span>
</div>
<?php endif;
?>

<style>
/* ===== SUBMIT REQUEST — REDESIGN ===== */
:root { --red:#8B0000; --red2:#b91c1c; }

/* ── Step indicator ── */
.rq-steps {
    display:flex; align-items:center; justify-content:center;
    gap:0; margin-bottom:28px;
}
.rq-step-item { display:flex; flex-direction:column; align-items:center; flex:1; max-width:140px; position:relative; }
.rq-step-dot {
    width:34px; height:34px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:0.78rem; font-weight:800; z-index:2;
    background:rgba(0,0,0,0.06); color:rgba(0,0,0,0.30);
    border:2px solid rgba(0,0,0,0.10);
    transition:all 0.25s;
}
.rq-step-dot.active {
    background:var(--red);
    color:#fff; border-color:var(--red);
}
.rq-step-dot.done { background:#22c55e; border-color:#22c55e; color:#fff; }
.rq-step-lbl {
    font-size:0.67rem; font-weight:700; margin-top:5px; text-align:center;
    color:rgba(0,0,0,0.35); text-transform:uppercase; letter-spacing:0.3px;
}
.rq-step-lbl.active { color:var(--red); }
.rq-step-lbl.done   { color:#15803d; }
.rq-step-connector {
    flex:1; height:2px; background:rgba(0,0,0,0.08);
    margin-top:-22px; position:relative; z-index:1;
    max-width:80px;
}
.rq-step-connector.done { background:#22c55e; }

/* ── Type cards ── */
.rq-type-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:14px;
    margin-bottom:0;
}
@media(max-width:600px){ .rq-type-grid { grid-template-columns:1fr; } }
@media(max-width:600px){ #receiving_method_grid { grid-template-columns:1fr !important; } }
.rq-type-card {
    background:#fff; border:2px solid #e5e7eb; border-radius:8px;
    padding:20px 18px 16px; cursor:pointer;
    transition:border-color 0.15s; user-select:none; position:relative;
    overflow:hidden;
}
.rq-type-card:hover { border-color:rgba(139,0,0,0.30); }
.rq-type-card.selected {
    border-color:var(--red) !important;
    background:#fff !important;
}
.rq-type-check {
    position:absolute; top:12px; right:12px;
    width:20px; height:20px; border-radius:50%;
    background:var(--red); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:0.60rem; opacity:0; transform:scale(0.5);
    transition:all 0.20s;
}
.rq-type-card.selected .rq-type-check { opacity:1; transform:scale(1); }
.rq-type-card input[type=radio] { display:none; }
.rq-type-icon {
    width:36px; height:36px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; margin-bottom:12px;
}
.rq-type-card h6 { font-size:0.93rem; font-weight:800; color:#1a1d23; margin:0 0 5px; }
.rq-type-card p  { font-size:0.76rem; color:rgba(0,0,0,0.42); margin:0; line-height:1.4; }

/* ── Layout wrapper ── */
.rq-layout { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
@media(max-width:900px){ .rq-layout { grid-template-columns:1fr; } }

/* ── Form card ── */
.rq-form-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    overflow:hidden;
}
.rq-form-head {
    padding:18px 24px 16px;
    border-bottom:1px solid rgba(0,0,0,0.06);
    display:flex; align-items:center; gap:10px;
}
.rq-form-head-icon {
    width:30px; height:30px;
    display:flex; align-items:center; justify-content:center;
    font-size:0.95rem; flex-shrink:0;
}
.rq-form-head-title { font-size:0.95rem; font-weight:800; color:#1a1d23; margin:0; }
.rq-form-head-sub   { font-size:0.75rem; color:rgba(0,0,0,0.40); margin:1px 0 0; }
.rq-form-body { padding:22px 24px; }

.rq-section-title {
    font-size:0.70rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.7px; color:rgba(0,0,0,0.36);
    margin-bottom:14px; display:flex; align-items:center; gap:6px;
}
.rq-section-title::after {
    content:''; flex:1; height:1px; background:rgba(0,0,0,0.07);
}
.rq-divider { border-color:rgba(0,0,0,0.07); margin:20px 0; }

/* Improved form controls */
.rq-field { margin-bottom:18px; }
.rq-field label {
    font-size:0.80rem; font-weight:700; color:rgba(0,0,0,0.60);
    margin-bottom:6px; display:flex; align-items:center; gap:5px;
}
.rq-field label .rq-req { color:var(--red); font-size:0.75rem; }
.rq-input-wrap { position:relative; }
.rq-input-icon {
    position:absolute; left:12px; top:50%; transform:translateY(-50%);
    color:rgba(0,0,0,0.30); font-size:0.80rem; pointer-events:none;
}
.rq-input-icon-ta { top:14px; transform:none; }
.rq-input-wrap .form-control,
.rq-input-wrap .form-select {
    border-radius:6px !important; border:1px solid #e5e7eb !important;
    font-size:0.88rem !important; padding:10px 12px 10px 36px !important;
    background:#fff !important;
    transition:border-color 0.15s !important;
}
.rq-input-wrap .form-control:focus,
.rq-input-wrap .form-select:focus {
    border-color:var(--red) !important;
    box-shadow:none !important; outline:none !important;
}
/* fields without icon */
.form-control.rq-no-icon, .form-select.rq-no-icon {
    border-radius:6px !important; border:1px solid #e5e7eb !important;
    font-size:0.88rem !important; background:#fff !important;
    transition:border-color 0.15s !important;
}
.form-control.rq-no-icon:focus, .form-select.rq-no-icon:focus {
    border-color:var(--red) !important;
    box-shadow:none !important; outline:none !important;
}
.rq-input-wrap textarea.form-control { padding-top:10px !important; }

/* Urgency cards */
.urgency-group { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
@media(max-width:500px){ .urgency-group { grid-template-columns:repeat(2,1fr); } }
.urgency-pill {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:5px; padding:10px 8px; border-radius:6px;
    border:1px solid #e5e7eb;
    background:#fff; cursor:pointer;
    font-size:0.78rem; font-weight:700; color:#555;
    transition:border-color 0.15s; user-select:none; text-align:center;
}
.urgency-pill input[type=radio] { display:none; }
.urgency-pill i { font-size:1rem; }
.urgency-pill:hover { border-color:#aaa; color:#111; }
.urgency-pill.selected-low      { background:rgba(34,197,94,0.12);  border-color:#22c55e; color:#15803d; }
.urgency-pill.selected-medium   { background:rgba(59,130,246,0.12); border-color:#3b82f6; color:#1d4ed8; }
.urgency-pill.selected-high     { background:rgba(245,158,11,0.12); border-color:#f59e0b; color:#b45309; }
.urgency-pill.selected-critical { background:rgba(239,68,68,0.12);  border-color:#ef4444; color:#b91c1c; }

/* ── Summary sidebar ── */
.rq-summary-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    overflow:hidden; position:sticky; top:88px;
}
.rq-summary-head {
    background:#8B0000;
    padding:16px 18px; color:#fff;
}
.rq-summary-head-title { font-size:0.88rem; font-weight:800; margin:0; }
.rq-summary-head-sub   { font-size:0.72rem; opacity:0.80; margin:2px 0 0; }
.rq-summary-body { padding:16px 18px; }
.rq-summary-row {
    display:flex; align-items:flex-start; gap:8px;
    padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.06);
    font-size:0.80rem;
}
.rq-summary-row:last-child { border-bottom:none; }
.rq-summary-icon {
    width:26px; height:26px; border-radius:7px;
    background:rgba(139,0,0,0.08); color:var(--red);
    display:flex; align-items:center; justify-content:center;
    font-size:0.68rem; flex-shrink:0; margin-top:1px;
}
.rq-summary-label { font-size:0.67rem; font-weight:700; color:rgba(0,0,0,0.38); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:2px; }
.rq-summary-val   { font-weight:600; color:#1a1d23; }
.rq-summary-empty { font-size:0.75rem; color:rgba(0,0,0,0.30); font-style:italic; }
.rq-summary-tip {
    font-size:0.74rem; color:rgba(0,0,0,0.42); line-height:1.5;
    background:rgba(0,0,0,0.03); border-radius:10px; padding:10px 12px;
    margin-top:12px;
}

/* Buttons */
.rq-submit-btn {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; font-size:0.90rem !important;
    color:#fff !important; padding:12px 28px !important;
    transition:background 0.15s !important;
}
.rq-submit-btn:hover {
    background:#7a0000 !important;
}
.rq-cancel-btn {
    background:#f7f7f7 !important; border:1px solid #e5e7eb !important;
    border-radius:6px !important; font-weight:600 !important;
    color:#555 !important; padding:12px 22px !important;
    transition:background 0.15s !important;
}
.rq-cancel-btn:hover { background:#e5e7eb !important; color:#111 !important; }

/* Info chip for read-only desc */
.rq-desc-chip {
    background:rgba(139,0,0,0.05); border:1px solid rgba(139,0,0,0.12);
    border-radius:10px; padding:9px 14px;
    font-size:0.82rem; color:rgba(0,0,0,0.55); line-height:1.45;
    display:flex; align-items:flex-start; gap:7px;
}
/* ── Cart ── */
.rq-cart-area { margin-top:6px; }
.rq-cart-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.rq-cart-badge { background:rgba(139,0,0,0.10); color:#8B0000; border-radius:20px; padding:2px 10px; font-size:0.72rem; font-weight:800; }
.rq-cart-empty { text-align:center; padding:20px 12px; color:rgba(0,0,0,0.30); font-size:0.81rem; background:rgba(0,0,0,0.02); border:1.5px dashed rgba(0,0,0,0.10); border-radius:12px; }
.rq-cart-item { display:flex; align-items:flex-start; gap:10px; background:rgba(255,255,255,0.85); border:1px solid rgba(0,0,0,0.08); border-radius:12px; padding:11px 13px; margin-bottom:8px; }
.rq-cart-item-num { width:24px; height:24px; border-radius:4px; flex-shrink:0; background:#8B0000; color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.68rem; font-weight:800; }
.rq-cart-item-body { flex:1; min-width:0; }
.rq-cart-item-name { font-weight:700; color:#1a1d23; font-size:0.87rem; margin-bottom:3px; }
.rq-cart-item-meta { font-size:0.75rem; color:rgba(0,0,0,0.44); display:flex; flex-wrap:wrap; gap:6px; }
.rq-cart-remove { padding:3px 9px; border-radius:7px; border:none; background:rgba(239,68,68,0.09); color:#dc2626; font-size:0.72rem; font-weight:700; cursor:pointer; flex-shrink:0; }
.rq-cart-remove:hover { background:rgba(239,68,68,0.20); }
.rq-add-btn { display:flex; align-items:center; justify-content:center; gap:7px; width:100%; padding:10px; border-radius:11px; margin-top:10px; border:2px dashed rgba(139,0,0,0.25); background:rgba(139,0,0,0.03); color:#8B0000; font-size:0.84rem; font-weight:700; cursor:pointer; transition:all 0.15s; }
.rq-add-btn:hover { background:rgba(139,0,0,0.08); border-color:rgba(139,0,0,0.50); }
.rq-cart-error { display:none; color:#dc2626; font-size:0.80rem; font-weight:600; margin-top:8px; padding:8px 12px; background:rgba(239,68,68,0.08); border-radius:9px; }
.rq-sum-preview-item { display:flex; align-items:flex-start; gap:7px; padding:5px 0; }
.rq-sum-preview-num { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:5px; background:rgba(139,0,0,0.12); color:#8B0000; font-size:0.60rem; font-weight:800; flex-shrink:0; margin-top:1px; }

/* ── Item Availability Calendar ── */
.iac-outer {
    border-top: 1px solid #e5e7eb;
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    background: #fafafa;
}
.iac-bar {
    padding: 11px 16px;
    font-size: 0.80rem;
    display: flex;
    flex-direction: column;
    gap: 7px;
    line-height: 1.5;
}
.iac-bar-top {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
}
.iac-bar-chips {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
}
.iac-bar-chips-label {
    font-size: 0.70rem;
    color: #a16207;
    font-weight: 600;
    margin-right: 2px;
}
.iac-ret-chip {
    font-size: 0.70rem;
    font-weight: 600;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fbbf24;
    border-radius: 20px;
    padding: 2px 9px;
    white-space: nowrap;
}
.iac-bar-borrowed {
    background: linear-gradient(135deg, #fff8ed 0%, #fff3e0 100%);
    color: #7c4a00;
    border-bottom: 1px solid #fde68a;
}
.iac-cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px 0;
}
.iac-cal-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #8B0000;
    display: flex;
    align-items: center;
    gap: 6px;
}
.iac-cal-nav {
    display: flex;
    align-items: center;
    gap: 6px;
}
.iac-cal-nav span {
    font-weight: 700;
    font-size: 0.85rem;
    color: #111;
    min-width: 100px;
    text-align: center;
}
.iac-cal-nav button {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: #555; font-size: 0.65rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    transition: background 0.15s, border-color 0.15s;
}
.iac-cal-nav button:hover { background: #f3f4f6; border-color: #d1d5db; }
.iac-cal-panel { padding: 10px 14px 6px; }
.iac-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.iac-dow {
    text-align: center;
    font-size: 0.58rem;
    font-weight: 700;
    color: #9ca3af;
    padding: 4px 0 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.iac-cell {
    text-align: center;
    font-size: 0.73rem;
    padding: 6px 2px 5px;
    border-radius: 7px;
    line-height: 1.2;
    color: #374151;
    transition: transform 0.1s;
}
.iac-cell:not(:empty):hover { transform: scale(1.12); }
.iac-cell.iac-past { color: #d1d5db; }
.iac-cell.iac-today {
    font-weight: 800;
    outline: 2px solid #8B0000;
    outline-offset: -2px;
    border-radius: 7px;
}
.iac-cell.iac-unavail {
    background: #fee2e2;
    color: #b91c1c;
    text-decoration: line-through;
    text-decoration-color: #f87171;
}
.iac-cell.iac-today.iac-unavail {
    background: #fca5a5;
    color: #7f1d1d;
    outline-color: #dc2626;
}
.iac-cell.iac-returns {
    background: linear-gradient(135deg, #fef9c3, #fef3c7);
    color: #92400e;
    font-weight: 700;
    border: 1px solid #fbbf24;
    box-shadow: 0 1px 3px rgba(251,191,36,0.25);
    position: relative;
}
.iac-cell.iac-returns::after {
    content: '↵';
    display: block;
    font-size: 0.52rem;
    line-height: 1;
    color: #d97706;
    margin-top: 1px;
}
.iac-cell.iac-avail {
    background: #dcfce7;
    color: #15803d;
    font-weight: 600;
}
.iac-cell.iac-today.iac-avail {
    background: #8B0000;
    color: #fff;
    outline-color: #8B0000;
}
.iac-cell.iac-today:not(.iac-unavail):not(.iac-returns):not(.iac-avail) {
    background: #8B0000;
    color: #fff;
    outline-color: #8B0000;
}
.iac-legend {
    display: flex;
    gap: 8px;
    padding: 8px 14px 12px;
    border-top: 1px solid #f0f0f0;
    flex-wrap: wrap;
    justify-content: center;
}
.iac-leg-item {
    font-size: 0.68rem;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 5px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 3px 9px;
}
.iac-leg-dot {
    width: 9px; height: 9px;
    border-radius: 3px;
    flex-shrink: 0;
    display: inline-block;
}

/* ── Borrow Item Shop ── */
.bshop-wrap { display:flex; flex-direction:column; gap:10px; }

.bshop-controls {
    display: flex;
    gap: 8px;
    align-items: center;
}
.bshop-search-wrap {
    position: relative;
    flex: 1;
}
.bshop-search-wrap input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.83rem;
    outline: none;
    background: #fafafa;
    transition: border-color 0.15s, background 0.15s;
    color: #1a1d23;
}
.bshop-search-wrap input:focus { border-color: #8B0000; background: #fff; }
.bshop-search-icon {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: #aaa; font-size: 0.75rem; pointer-events: none;
}

.bshop-cats {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.bshop-cat {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #555;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
}
.bshop-cat:hover { border-color: #8B0000; color: #8B0000; }
.bshop-cat.active { background: #8B0000; border-color: #8B0000; color: #fff; }

.bshop-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    max-height: 460px;
    overflow-y: auto;
    padding-right: 2px;
}
@media(max-width:900px){ .bshop-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:560px){ .bshop-grid { grid-template-columns: 1fr; } }
.bshop-grid::-webkit-scrollbar { width: 5px; }
.bshop-grid::-webkit-scrollbar-track { background: transparent; }
.bshop-grid::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 4px; }

.bshop-card {
    position: relative;
    background: #fff;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 12px 10px;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
    display: flex;
    flex-direction: column;
    gap: 4px;
    user-select: none;
}
.bshop-card:hover {
    border-color: rgba(139,0,0,0.35);
    box-shadow: 0 2px 8px rgba(139,0,0,0.08);
    transform: translateY(-1px);
}
.bshop-card.bshop-selected {
    border-color: #8B0000;
    border-width: 2px;
    background: rgba(139,0,0,0.025);
    box-shadow: 0 0 0 3px rgba(139,0,0,0.10);
}

.bshop-check {
    position: absolute;
    top: 8px; right: 8px;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #8B0000;
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.55rem;
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.18s;
    z-index: 2;
}
.bshop-card.bshop-selected .bshop-check { opacity: 1; transform: scale(1); }

.bshop-avail-badge {
    position: absolute;
    top: 8px; left: 8px;
    font-size: 0.62rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    line-height: 1.6;
}
.bshop-avail-ok   { background: #dcfce7; color: #15803d; }
.bshop-avail-none { background: #fee2e2; color: #991b1b; }

.bshop-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem;
    margin-top: 20px;
    flex-shrink: 0;
}
.bshop-icon-custom { background: #f3f4f6; color: #6b7280; }

.bshop-name {
    font-size: 0.84rem;
    font-weight: 800;
    color: #1a1d23;
    line-height: 1.3;
    margin-top: 6px;
}
.bshop-desc {
    font-size: 0.71rem;
    color: #9ca3af;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.bshop-loc {
    font-size: 0.68rem;
    color: #bbb;
    margin-top: 2px;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bshop-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #f3f4f6;
}
.bshop-price {
    font-size: 0.80rem;
    font-weight: 700;
    color: #374151;
    font-variant-numeric: tabular-nums;
}
.bshop-status-pill {
    font-size: 0.63rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}
.bshop-pill-avail    { background: #dcfce7; color: #15803d; }
.bshop-pill-borrowed { background: #fef3c7; color: #92400e; }
.bshop-pill-maint    { background: #e0f2fe; color: #0369a1; }
.bshop-pill-damaged  { background: #fee2e2; color: #991b1b; }
.bshop-avail-maint   { background: #e0f2fe; color: #0369a1; }
.bshop-pill-custom   { background: #f3f4f6; color: #6b7280; }

.bshop-card-custom {
    border-style: dashed;
    justify-content: center;
    align-items: center;
    text-align: center;
    min-height: 120px;
}
.bshop-card-custom .bshop-icon { margin-top: 0; }
.bshop-card-custom .bshop-foot { border-top: none; padding-top: 0; margin-top: 4px; justify-content: center; }

.bshop-empty {
    grid-column: 1/-1;
    justify-content: center;
    align-items: center;
    gap: 8px;
    color: #bbb;
    font-size: 0.82rem;
    padding: 24px;
    display: flex;
}
</style>

<div class="container-fluid mt-4 pb-4">

    <?php if (isset($error)): ?>
    <div class="alert alert-danger mb-4" style="border-radius:14px;">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Step Indicator -->
    <div class="rq-steps mb-4">
        <div class="rq-step-item">
            <div class="rq-step-dot done" id="sdot1"><i class="fas fa-check" style="font-size:0.65rem;"></i></div>
            <div class="rq-step-lbl done" id="slbl1">Request Type</div>
        </div>
        <div class="rq-step-connector done" id="scon1"></div>
        <div class="rq-step-item">
            <div class="rq-step-dot active" id="sdot2">2</div>
            <div class="rq-step-lbl active" id="slbl2">Urgency</div>
        </div>
        <div class="rq-step-connector" id="scon2"></div>
        <div class="rq-step-item">
            <div class="rq-step-dot" id="sdot3">3</div>
            <div class="rq-step-lbl" id="slbl3">Details</div>
        </div>
    </div>

    <!-- Step 1 — Type Selection -->
    <div id="step1_section">
        <div class="rq-type-grid mb-4">
            <label class="rq-type-card selected" onclick="selectType(this,'borrow')">
                <input type="radio" name="_type_vis" value="borrow" checked>
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="color:#1d4ed8;">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <h6>Borrow Item</h6>
                <p>Temporarily borrow an item from campus inventory</p>
            </label>
            <label class="rq-type-card" onclick="selectType(this,'item')">
                <input type="radio" name="_type_vis" value="item">
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="color:#15803d;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6>Request Item</h6>
                <p>Request a new item to be procured or purchased</p>
            </label>
            <label class="rq-type-card" onclick="selectType(this,'service')">
                <input type="radio" name="_type_vis" value="service">
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="color:#b45309;">
                    <i class="fas fa-tools"></i>
                </div>
                <h6>Request Service</h6>
                <p>Request maintenance, repair or inspection</p>
            </label>
        </div>
    </div>

    <!-- Form + Sidebar -->
    <div class="rq-layout">

        <!-- Form Card -->
        <div class="rq-form-card">
            <!-- Dynamic head -->
            <div class="rq-form-head" id="formHead">
                <div class="rq-form-head-icon" id="formHeadIcon"
                     style="color:#1d4ed8;">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div>
                    <div class="rq-form-head-title" id="formHeadTitle">Borrow Item</div>
                    <div class="rq-form-head-sub"  id="formHeadSub">Fill in the details for your borrow request</div>
                </div>
            </div>

            <div class="rq-form-body">
            <form method="POST" action="" id="requestForm" novalidate enctype="multipart/form-data">
                <input type="hidden" id="request_type_hidden" name="request_type" value="borrow">

                <!-- Urgency -->
                <div class="rq-section-title"><i class="fas fa-bolt"></i> Priority Level</div>
                <div class="urgency-group mb-4">
                    <?php
                    $urgencies = [
                        'low'      => ['label' => 'Low',      'icon' => 'fa-arrow-down',   'class' => 'selected-low'],
                        'medium'   => ['label' => 'Medium',   'icon' => 'fa-minus',        'class' => 'selected-medium'],
                        'high'     => ['label' => 'High',     'icon' => 'fa-arrow-up',     'class' => 'selected-high'],
                        'critical' => ['label' => 'Critical', 'icon' => 'fa-exclamation',  'class' => 'selected-critical'],
                    ];
                    foreach ($urgencies as $val => $u): ?>
                    <label class="urgency-pill <?php echo $val === 'medium' ? 'selected-medium' : ''; ?>"
                           data-urgency="<?php echo $val; ?>"
                           onclick="selectUrgency(this,'<?php echo $u['class']; ?>')">
                        <input type="radio" name="urgency" value="<?php echo $val; ?>" <?php echo $val === 'medium' ? 'checked' : ''; ?>>
                        <i class="fas <?php echo $u['icon']; ?>"></i>
                        <?php echo $u['label']; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <hr class="rq-divider">

                <!-- BORROW FIELDS -->
                <div id="borrow_fields">
                    <div class="rq-section-title"><i class="fas fa-hand-holding"></i> Borrow Details</div>

                    <?php
                    // Category icon + color map
                    $catMeta = [
                        'Electronics'      => ['icon'=>'fa-laptop',              'color'=>'#1e40af','bg'=>'#dbeafe'],
                        'Furniture'        => ['icon'=>'fa-chair',               'color'=>'#92400e','bg'=>'#fef3c7'],
                        'Equipment'        => ['icon'=>'fa-screwdriver-wrench',  'color'=>'#166534','bg'=>'#dcfce7'],
                        'Supplies'         => ['icon'=>'fa-boxes-stacked',       'color'=>'#6b21a8','bg'=>'#f3e8ff'],
                        'Appliances'       => ['icon'=>'fa-plug',                'color'=>'#0f766e','bg'=>'#ccfbf1'],
                        'Security'         => ['icon'=>'fa-shield-halved',       'color'=>'#9f1239','bg'=>'#ffe4e6'],
                        'Office Equipment' => ['icon'=>'fa-print',               'color'=>'#0369a1','bg'=>'#e0f2fe'],
                    ];
                    $defaultMeta = ['icon'=>'fa-box','color'=>'#6b7280','bg'=>'#f3f4f6'];
                    ?>

                    <div class="rq-field">
                        <label>Item to Borrow <span class="rq-req">*</span><?php if ($auto_fill_item): ?> <span style="font-size:0.65rem;font-weight:700;background:rgba(34,197,94,0.12);color:#15803d;padding:2px 8px;border-radius:12px;border:1px solid rgba(34,197,94,0.22);margin-left:6px;"><i class="fas fa-check-circle me-1"></i>Pre-selected</span><?php endif; ?></label>

                        <!-- Hidden select (drives existing JS logic unchanged) -->
                        <select id="borrow_catalog_select" name="borrow_item_name" style="display:none;" required>
                            <option value="">— Select an item —</option>
                            <?php foreach ($borrow_catalog as $category => $items):
                                if (!count($items)) continue; ?>
                            <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                <?php foreach ($items as $ci):
                                    $qty = $ci['quantity'] ?? 0;
                                    $borrowedRecords = $item_avail_data[$ci['id']] ?? [];
                                    $borrowedCount = count($borrowedRecords);
                                    $availableQty = max(0, $qty - $borrowedCount);
                                ?>
                                <option value="<?php echo htmlspecialchars($ci['item_name']); ?>"
                                        data-item-id="<?php echo $ci['id']; ?>"
                                        data-status="<?php echo $ci['status']; ?>"
                                        data-quantity="<?php echo $qty; ?>"
                                        data-available="<?php echo $availableQty; ?>"
                                        <?php if ($auto_fill_item && $ci['item_name'] === $auto_fill_item) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($ci['item_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                            <option value="__custom__">Other (custom)</option>
                        </select>

                        <!-- Shop UI -->
                        <div class="bshop-wrap">
                            <!-- Search -->
                            <div class="bshop-controls">
                                <div class="bshop-search-wrap">
                                    <i class="fas fa-search bshop-search-icon"></i>
                                    <input type="text" id="bshop-search" placeholder="Search items…" oninput="filterShopItems()">
                                </div>
                            </div>
                            <!-- Category pills -->
                            <div class="bshop-cats" id="bshop-cats">
                                <button type="button" class="bshop-cat active" data-cat="" onclick="filterShopItems(this)">All</button>
                                <?php foreach (array_keys($borrow_catalog) as $cat): ?>
                                <button type="button" class="bshop-cat" data-cat="<?php echo htmlspecialchars($cat); ?>" onclick="filterShopItems(this)"><?php echo htmlspecialchars($cat); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <!-- Card grid -->
                            <div class="bshop-grid" id="bshop-grid">
                                <?php foreach ($borrow_catalog as $category => $items):
                                    foreach ($items as $ci):
                                        $isBorrowed = $ci['status'] === 'borrowed';
                                        $qty = $ci['quantity'] ?? 0;
                                        $borrowedRecords = $item_avail_data[$ci['id']] ?? [];
                                        $borrowedCount = count($borrowedRecords);
                                        $availableQty = max(0, $qty - $borrowedCount);
                                        $retDates = [];
                                        if ($borrowedCount > 0) {
                                            foreach ($borrowedRecords as $r) $retDates[] = date('M j', strtotime($r['return_date']));
                                            sort($retDates);
                                        }
                                        $meta = $catMeta[$category] ?? $defaultMeta;
                                        $isPreSelected = ($auto_fill_item && $ci['item_name'] === $auto_fill_item);
                                ?>
                                <div class="bshop-card<?php echo $isPreSelected ? ' bshop-selected' : ''; ?>"
                                     data-value="<?php echo htmlspecialchars($ci['item_name']); ?>"
                                     data-item-id="<?php echo $ci['id']; ?>"
                                     data-status="<?php echo $ci['status']; ?>"
                                     data-category="<?php echo htmlspecialchars($category); ?>"
                                     data-available="<?php echo $availableQty; ?>"
                                     data-quantity="<?php echo $qty; ?>"
                                     onclick="selectBorrowCard(this)">
                                    <div class="bshop-check"><i class="fas fa-check"></i></div>
                                    <div class="bshop-avail-badge <?php echo $availableQty > 0 ? 'bshop-avail-ok' : 'bshop-avail-none'; ?>">
                                        <?php echo $availableQty > 0 ? $availableQty . ' avail.' : 'Fully borrowed'; ?>
                                    </div>
                                    <div class="bshop-icon" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                                        <i class="fas <?php echo $meta['icon']; ?>"></i>
                                    </div>
                                    <div class="bshop-name"><?php echo htmlspecialchars($ci['item_name']); ?></div>
                                    <div class="bshop-desc"><?php echo htmlspecialchars($ci['description'] ?? ''); ?></div>
                                    <div class="bshop-loc"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($ci['location'] ?? ''); ?></div>
                                    <div class="bshop-foot">
                                        <div class="bshop-price">₱<?php echo number_format($ci['cost'] ?? 0, 2); ?></div>
                                        <div class="bshop-status-pill <?php echo $isBorrowed ? 'bshop-pill-borrowed' : 'bshop-pill-avail'; ?>">
                                            <?php if ($isBorrowed && count($retDates) > 0): ?>
                                                Returns <?php echo $retDates[0]; ?>
                                            <?php elseif ($isBorrowed): ?>
                                                Borrowed
                                            <?php else: ?>
                                                Available
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endforeach; ?>
                                <!-- Custom item card -->
                                <div class="bshop-card bshop-card-custom"
                                     data-value="__custom__" data-item-id="" data-status="available"
                                     data-category="" data-available="999" data-quantity="999"
                                     onclick="selectBorrowCard(this)">
                                    <div class="bshop-check"><i class="fas fa-check"></i></div>
                                    <div class="bshop-icon bshop-icon-custom"><i class="fas fa-pen"></i></div>
                                    <div class="bshop-name">Other / Custom</div>
                                    <div class="bshop-desc">Specify an item not in the list</div>
                                    <div class="bshop-foot"><div class="bshop-status-pill bshop-pill-custom">Custom</div></div>
                                </div>
                                <div id="bshop-empty" class="bshop-empty" style="display:none;">
                                    <i class="fas fa-search"></i><span>No items match</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rq-field" id="custom_item_wrap" style="display:none;">
                        <label>Custom Item Name <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-pen rq-input-icon"></i>
                            <input type="text" class="form-control" id="custom_item_name"
                                   name="custom_item_name" placeholder="e.g., Portable Bluetooth Speaker"
                                   oninput="updateSummary()">
                        </div>
                        <div class="form-text mt-1" style="font-size:0.73rem;">Be as specific as possible.</div>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-sm-6">
                            <div class="rq-field mb-0">
                                <label>Expected Return Date <span class="rq-req">*</span></label>
                                <div class="rq-input-wrap">
                                    <i class="fas fa-calendar rq-input-icon"></i>
                                    <input type="date" class="form-control" id="expected_return_date"
                                           name="expected_return_date" required onchange="updateSummary()">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="rq-field mb-0">
                                <label>Quantity <span class="rq-req">*</span></label>
                                <div class="rq-input-wrap">
                                    <i class="fas fa-hashtag rq-input-icon"></i>
                                    <input type="number" class="form-control" id="borrow_quantity"
                                           name="borrow_quantity" value="1" min="1" required
                                           oninput="updateSummary()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rq-field mt-3">
                        <label>Reason for Borrowing</label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-comment rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="reason" name="reason" rows="3"
                                      placeholder="Briefly explain why you need this item"
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ITEM REQUEST FIELDS -->
                <div id="item_fields" style="display:none;">
                    <div class="rq-section-title"><i class="fas fa-shopping-cart"></i> Item Request Details</div>

                    <div class="rq-field">
                        <label>Item Name <span class="rq-req">*</span></label>

                        <!-- Hidden select (drives handleItemCatalogChange) -->
                        <select id="item_description" name="item_description" style="display:none;">
                            <option value="">— Select an item —</option>
                            <?php foreach ($catalog_by_category as $category => $items): ?>
                            <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                <?php foreach ($items as $ci): ?>
                                <option value="<?php echo htmlspecialchars($ci['item_name']); ?>"
                                        data-desc="<?php echo htmlspecialchars($ci['description'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($ci['item_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                            <option value="__custom__">Other (custom)</option>
                        </select>

                        <!-- Shop grid -->
                        <div class="bshop-wrap">
                            <div class="bshop-controls">
                                <div class="bshop-search-wrap">
                                    <i class="fas fa-search bshop-search-icon"></i>
                                    <input type="text" id="ishop-search" placeholder="Search items…" oninput="filterItemShop()">
                                </div>
                            </div>
                            <div class="bshop-cats" id="ishop-cats">
                                <button type="button" class="bshop-cat active" data-cat="" onclick="filterItemShop(this)">All</button>
                                <?php foreach (array_keys($catalog_by_category) as $cat): ?>
                                <button type="button" class="bshop-cat" data-cat="<?php echo htmlspecialchars($cat); ?>" onclick="filterItemShop(this)"><?php echo htmlspecialchars($cat); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="bshop-grid" id="ishop-grid">
                                <?php foreach ($catalog_by_category as $category => $items):
                                    foreach ($items as $ci):
                                        $meta = $catMeta[$category] ?? $defaultMeta;
                                ?>
                                <div class="bshop-card"
                                     data-value="<?php echo htmlspecialchars($ci['item_name']); ?>"
                                     data-category="<?php echo htmlspecialchars($category); ?>"
                                     data-desc="<?php echo htmlspecialchars($ci['description'] ?? ''); ?>"
                                     onclick="selectItemReqCard(this)">
                                    <div class="bshop-check"><i class="fas fa-check"></i></div>
                                    <div class="bshop-avail-badge bshop-avail-ok">Available</div>
                                    <div class="bshop-icon" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                                        <i class="fas <?php echo $meta['icon']; ?>"></i>
                                    </div>
                                    <div class="bshop-name"><?php echo htmlspecialchars($ci['item_name']); ?></div>
                                    <div class="bshop-desc"><?php echo htmlspecialchars($ci['description'] ?? ''); ?></div>
                                    <div class="bshop-loc"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($ci['location'] ?? ''); ?></div>
                                    <div class="bshop-foot">
                                        <div class="bshop-price">₱<?php echo number_format($ci['cost'] ?? 0, 2); ?></div>
                                        <div class="bshop-status-pill bshop-pill-avail"><?php echo htmlspecialchars($category); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; endforeach; ?>
                                <!-- Custom card -->
                                <div class="bshop-card bshop-card-custom"
                                     data-value="__custom__" data-category="" data-desc=""
                                     onclick="selectItemReqCard(this)">
                                    <div class="bshop-check"><i class="fas fa-check"></i></div>
                                    <div class="bshop-icon bshop-icon-custom"><i class="fas fa-pen"></i></div>
                                    <div class="bshop-name">Other / Custom</div>
                                    <div class="bshop-desc">Specify an item not in the list</div>
                                    <div class="bshop-foot"><div class="bshop-status-pill bshop-pill-custom">Custom</div></div>
                                </div>
                                <div id="ishop-empty" class="bshop-empty" style="display:none;">
                                    <i class="fas fa-search"></i><span>No items match</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rq-field" id="custom_item_req_wrap" style="display:none;">
                        <label>Custom Item Name <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-pen rq-input-icon"></i>
                            <input type="text" class="form-control" id="custom_item_req_name"
                                   name="custom_item_req_name"
                                   placeholder="e.g., Canon Projector, Dell Desktop Computer"
                                   oninput="updateSummary()">
                        </div>
                    </div>

                    <div class="rq-field" id="item_desc_display_wrap" style="display:none;">
                        <label>Item Description</label>
                        <div class="rq-desc-chip" id="item_desc_chip">
                            <i class="fas fa-info-circle" style="color:var(--red);flex-shrink:0;margin-top:2px;"></i>
                            <span id="item_desc_display_text"></span>
                        </div>
                        <input type="hidden" id="item_desc_display" name="item_desc_detail">
                    </div>

                    <div class="rq-field" style="max-width:160px;">
                        <label>Quantity <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-hashtag rq-input-icon"></i>
                            <input type="number" class="form-control" id="quantity" name="quantity"
                                   value="1" min="1" oninput="updateSummary()">
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Reason for Request <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-comment rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="item_reason" name="reason" rows="3"
                                      placeholder="Explain why this item is needed, where it will be used, etc."
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SERVICE FIELDS -->
                <div id="service_fields" style="display:none;">
                    <div class="rq-section-title"><i class="fas fa-tools"></i> Service Request Details</div>

                    <div class="rq-field">
                        <label>Item Requiring Service <span class="rq-req">*</span></label>

                        <!-- Hidden select (used by addToCart for item name + id) -->
                        <select id="item_id" name="item_id" style="display:none;" required>
                            <option value="">— Select an item —</option>
                            <?php foreach ($inventory_items as $item): ?>
                            <option value="<?php echo $item['id']; ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <!-- Shop grid -->
                        <?php
                        $svcStatusMeta = [
                            'available'   => ['label'=>'Available',   'cls'=>'bshop-pill-avail',    'badge'=>'bshop-avail-ok'],
                            'borrowed'    => ['label'=>'Borrowed',    'cls'=>'bshop-pill-borrowed',  'badge'=>'bshop-avail-none'],
                            'maintenance' => ['label'=>'Maintenance', 'cls'=>'bshop-pill-maint',    'badge'=>'bshop-avail-maint'],
                            'damaged'     => ['label'=>'Damaged',     'cls'=>'bshop-pill-damaged',  'badge'=>'bshop-avail-none'],
                            'requested'   => ['label'=>'Requested',   'cls'=>'bshop-pill-borrowed', 'badge'=>'bshop-avail-none'],
                        ];
                        // Group inventory items by category for filter
                        $svc_by_cat = [];
                        foreach ($inventory_items as $item) {
                            $c = $item['category'] ?? 'Other';
                            if (!isset($svc_by_cat[$c])) $svc_by_cat[$c] = [];
                            $svc_by_cat[$c][] = $item;
                        }
                        ?>
                        <div class="bshop-wrap">
                            <div class="bshop-controls">
                                <div class="bshop-search-wrap">
                                    <i class="fas fa-search bshop-search-icon"></i>
                                    <input type="text" id="sshop-search" placeholder="Search items…" oninput="filterServiceShop()">
                                </div>
                            </div>
                            <div class="bshop-cats" id="sshop-cats">
                                <button type="button" class="bshop-cat active" data-cat="" onclick="filterServiceShop(this)">All</button>
                                <?php foreach (array_keys($svc_by_cat) as $cat): ?>
                                <button type="button" class="bshop-cat" data-cat="<?php echo htmlspecialchars($cat); ?>" onclick="filterServiceShop(this)"><?php echo htmlspecialchars($cat); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="bshop-grid" id="sshop-grid">
                                <?php foreach ($inventory_items as $item):
                                    $category = $item['category'] ?? 'Other';
                                    $meta = $catMeta[$category] ?? $defaultMeta;
                                    $sm = $svcStatusMeta[$item['status']] ?? ['label'=>ucfirst($item['status']),'cls'=>'bshop-pill-custom','badge'=>'bshop-avail-none'];
                                ?>
                                <div class="bshop-card"
                                     data-value="<?php echo $item['id']; ?>"
                                     data-category="<?php echo htmlspecialchars($category); ?>"
                                     onclick="selectServiceCard(this)">
                                    <div class="bshop-check"><i class="fas fa-check"></i></div>
                                    <div class="bshop-avail-badge <?php echo $sm['badge']; ?>"><?php echo $sm['label']; ?></div>
                                    <div class="bshop-icon" style="background:<?php echo $meta['bg']; ?>;color:<?php echo $meta['color']; ?>;">
                                        <i class="fas <?php echo $meta['icon']; ?>"></i>
                                    </div>
                                    <div class="bshop-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                    <div class="bshop-desc"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                                    <div class="bshop-loc"><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($item['location'] ?? ''); ?></div>
                                    <div class="bshop-foot">
                                        <div class="bshop-price">₱<?php echo number_format($item['cost'] ?? 0, 2); ?></div>
                                        <div class="bshop-status-pill <?php echo $sm['cls']; ?>"><?php echo $sm['label']; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <div id="sshop-empty" class="bshop-empty" style="display:none;">
                                    <i class="fas fa-search"></i><span>No items match</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Type of Service <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-wrench rq-input-icon"></i>
                            <select class="form-select" id="service_type" name="service_type"
                                    onchange="updateSummary()">
                                <option value="">— Select service type —</option>
                                <option value="repair">🔧 Repair</option>
                                <option value="maintenance">🛠️ Maintenance</option>
                                <option value="inspection">🔍 Inspection</option>
                                <option value="cleaning">🧹 Cleaning</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Service Description <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-align-left rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="service_description"
                                      name="service_description" rows="3"
                                      placeholder="Describe the issue or service needed in detail."
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ─── CART / ITEMS LIST ─── -->
                <div id="rq-cart-area" class="rq-cart-area">
                    <div class="rq-cart-header">
                        <div class="rq-section-title" style="margin-bottom:0;flex:1;"><i class="fas fa-list-ul"></i> Items in This Request</div>
                        <span id="cart-count-badge" class="rq-cart-badge">0</span>
                    </div>
                    <div id="rq-cart-list">
                        <div class="rq-cart-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.22;"></i>No items added yet. Fill in the details above and click <strong>Add to List</strong>.</div>
                    </div>
                    <div id="cart-error" class="rq-cart-error"></div>
                    <button type="button" class="rq-add-btn" onclick="addToCart()">
                        <i class="fas fa-plus-circle"></i> Add to List
                    </button>
                </div>

                <hr class="rq-divider">

                <!-- RECEIVING METHOD -->
                <div id="receiving_method_section">
                    <div class="rq-section-title"><i class="fas fa-truck"></i> Receiving Method</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px;" id="receiving_method_grid">
                        <label class="rq-type-card selected" id="rm-delivery" onclick="selectReceivingMethod('delivery',this)" style="cursor:pointer;">
                            <input type="radio" name="receiving_method" value="delivery" checked>
                            <div class="rq-type-check"><i class="fas fa-check"></i></div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="rq-type-icon" style="color:#1d4ed8;width:32px;height:32px;min-width:32px;font-size:1rem;margin-bottom:0;">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div>
                                    <h6 style="margin:0 0 2px;font-size:0.88rem;">Delivery</h6>
                                    <p style="margin:0;font-size:0.74rem;color:rgba(0,0,0,0.42);">Item delivered to your location</p>
                                </div>
                            </div>
                        </label>
                        <label class="rq-type-card" id="rm-pickup" onclick="selectReceivingMethod('pickup',this)" style="cursor:pointer;">
                            <input type="radio" name="receiving_method" value="pickup">
                            <div class="rq-type-check"><i class="fas fa-check"></i></div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="rq-type-icon" style="color:#15803d;width:32px;height:32px;min-width:32px;font-size:1rem;margin-bottom:0;">
                                    <i class="fas fa-walking"></i>
                                </div>
                                <div>
                                    <h6 style="margin:0 0 2px;font-size:0.88rem;">Pickup</h6>
                                    <p style="margin:0;font-size:0.74rem;color:rgba(0,0,0,0.42);">Pick up the item yourself</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <hr class="rq-divider">
                <input type="hidden" id="items_json" name="items_json">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn rq-submit-btn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <a href="dashboard.php" class="btn rq-cancel-btn">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
            </div><!-- /.rq-form-body -->
        </div><!-- /.rq-form-card -->

        <!-- Summary Sidebar -->
        <div class="rq-summary-card">
            <div class="rq-summary-head">
                <div class="rq-summary-head-title"><i class="fas fa-clipboard-list me-2"></i>Request Summary</div>
                <div class="rq-summary-head-sub">Preview before submitting</div>
            </div>
            <div class="rq-summary-body">
                <div class="rq-summary-row">
                    <div class="rq-summary-icon"><i class="fas fa-tag"></i></div>
                    <div>
                        <div class="rq-summary-label">Type</div>
                        <div class="rq-summary-val" id="sum_type">Borrow Item</div>
                    </div>
                </div>
                <div class="rq-summary-row">
                    <div class="rq-summary-icon"><i class="fas fa-bolt"></i></div>
                    <div>
                        <div class="rq-summary-label">Priority</div>
                        <div class="rq-summary-val" id="sum_urgency">Medium</div>
                    </div>
                </div>
                <div class="rq-summary-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <div style="display:flex;align-items:center;gap:6px;width:100%;">
                        <div class="rq-summary-icon"><i class="fas fa-list-ul"></i></div>
                        <div style="flex:1;">
                            <div class="rq-summary-label">Items</div>
                            <div class="rq-summary-val" id="sum_items_count"><span class="rq-summary-empty">None</span></div>
                        </div>
                    </div>
                    <div id="sum_items_list" style="width:100%;padding-left:34px;"></div>
                </div>
                <div class="rq-summary-tip">
                    <i class="fas fa-info-circle me-1" style="color:var(--red);"></i>
                    Requests are reviewed by the admin. You will be notified once a decision is made.
                </div>
            </div>

            <!-- Item Availability Calendar -->
            <div id="iac-wrap" class="iac-outer" style="display:none;">
                <div id="iac-status-bar" class="iac-bar"></div>
                <div class="iac-cal-header">
                    <div class="iac-cal-title">
                        <i class="fas fa-calendar-days"></i> Availability
                    </div>
                    <div class="iac-cal-nav" id="iac-nav"></div>
                </div>
                <div class="iac-cal-panel">
                    <div id="iac-cal-grid"></div>
                </div>
                <div class="iac-legend">
                    <span class="iac-leg-item"><span class="iac-leg-dot" style="background:#fee2e2;border:1px solid #fca5a5;"></span>Unavailable</span>
                    <span class="iac-leg-item"><span class="iac-leg-dot" style="background:#fef3c7;border:1px solid #fbbf24;"></span>Returns</span>
                    <span class="iac-leg-item"><span class="iac-leg-dot" style="background:#dcfce7;border:1px solid #86efac;"></span>Available</span>
                </div>
            </div>
        </div>

    </div><!-- /.rq-layout -->
</div>
</div>

<script>
/* ── Type selection ── */
const typeConfig = {
    borrow:  { title:'Borrow Item',      sub:'Fill in the details for your borrow request',       icon:'fa-hand-holding', bg:'rgba(59,130,246,0.12)',  color:'#1d4ed8', label:'Borrow Item'      },
    item:    { title:'Request Item',     sub:'Specify the item you need procured',                 icon:'fa-shopping-cart',bg:'rgba(34,197,94,0.12)',   color:'#15803d', label:'Request Item'     },
    service: { title:'Request Service',  sub:'Describe the maintenance or repair needed',          icon:'fa-tools',        bg:'rgba(245,158,11,0.12)',  color:'#b45309', label:'Request Service'  },
};

function selectType(card, type) {
    if (cart.length > 0) { cart = []; renderCart(); hideCartError(); }
    document.querySelectorAll('.rq-type-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('request_type_hidden').value = type;
    const cfg = typeConfig[type];
    document.getElementById('formHeadIcon').style.background = '';
    document.getElementById('formHeadIcon').style.color      = cfg.color;
    document.getElementById('formHeadIcon').innerHTML = '<i class="fas ' + cfg.icon + '"></i>';
    document.getElementById('formHeadTitle').textContent = cfg.title;
    document.getElementById('formHeadSub').textContent   = cfg.sub;
    updateRequestType(type);
    updateSummary();
}

function selectUrgency(pill, selectedClass) {
    document.querySelectorAll('.urgency-pill').forEach(p => {
        p.classList.remove('selected-low','selected-medium','selected-high','selected-critical');
    });
    pill.classList.add(selectedClass);
    pill.querySelector('input[type=radio]').checked = true;
    updateSummary();
}

function updateRequestType(type) {
    ['borrow_fields','item_fields','service_fields'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    // Hide availability calendar whenever request type changes
    var iacWrap = document.getElementById('iac-wrap');
    if (iacWrap) iacWrap.style.display = 'none';
    ['borrow_catalog_select','expected_return_date','borrow_quantity','item_description','quantity','item_id','service_type','service_description'].forEach(id => {
        document.getElementById(id).removeAttribute('required');
    });
    // Hide receiving method for service requests (not applicable)
    document.getElementById('receiving_method_section').style.display = type === 'service' ? 'none' : 'block';
    if (type === 'borrow') {
        document.getElementById('borrow_fields').style.display = 'block';
        document.getElementById('borrow_catalog_select').setAttribute('required','required');
        document.getElementById('expected_return_date').setAttribute('required','required');
        document.getElementById('borrow_quantity').setAttribute('required','required');
    } else if (type === 'item') {
        document.getElementById('item_fields').style.display = 'block';
        document.getElementById('item_description').setAttribute('required','required');
        document.getElementById('quantity').setAttribute('required','required');
    } else if (type === 'service') {
        document.getElementById('service_fields').style.display = 'block';
        document.getElementById('item_id').setAttribute('required','required');
        document.getElementById('service_type').setAttribute('required','required');
        document.getElementById('service_description').setAttribute('required','required');
    }
}

function selectReceivingMethod(method, el) {
    document.getElementById('rm-delivery').classList.remove('selected');
    document.getElementById('rm-pickup').classList.remove('selected');
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}

/* ── Catalog selects ── */
/* ── Borrow item shop ── */
function selectBorrowCard(card) {
    document.querySelectorAll('.bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
    card.classList.add('bshop-selected');
    var select = document.getElementById('borrow_catalog_select');
    select.value = card.getAttribute('data-value');
    handleCatalogChange(select);
    updateSummary();
}

function filterShopItems(btn) {
    if (btn) {
        document.querySelectorAll('.bshop-cat').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
    }
    var activeCat = '';
    var activeBtn = document.querySelector('.bshop-cat.active');
    if (activeBtn) activeCat = activeBtn.getAttribute('data-cat') || '';
    var search = (document.getElementById('bshop-search').value || '').toLowerCase().trim();
    var visible = 0;
    document.querySelectorAll('.bshop-grid .bshop-card').forEach(function(card) {
        var cat = card.getAttribute('data-category') || '';
        var name = (card.querySelector('.bshop-name') ? card.querySelector('.bshop-name').textContent : '').toLowerCase();
        var desc = (card.querySelector('.bshop-desc') ? card.querySelector('.bshop-desc').textContent : '').toLowerCase();
        var isCustom = card.classList.contains('bshop-card-custom');
        var catMatch = !activeCat || cat === activeCat || isCustom;
        var searchMatch = !search || name.indexOf(search) !== -1 || desc.indexOf(search) !== -1;
        var show = catMatch && searchMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var emptyEl = document.getElementById('bshop-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'flex' : 'none';
}

function handleCatalogChange(select) {
    var wrap  = document.getElementById('custom_item_wrap');
    var input = document.getElementById('custom_item_name');
    if (select.value === '__custom__') {
        wrap.style.display = 'block'; input.setAttribute('required','required');
        select.removeAttribute('required');
    } else {
        wrap.style.display = 'none'; input.removeAttribute('required'); input.value = '';
        select.setAttribute('required','required');
    }

    // Cap quantity input to available stock
    var qtyInput = document.getElementById('borrow_quantity');
    var opt = select.options[select.selectedIndex];
    var available = parseInt(opt && opt.getAttribute('data-available')) || 1;
    if (select.value && select.value !== '__custom__') {
        qtyInput.max = available;
        qtyInput.title = 'Maximum available: ' + available;
        if (parseInt(qtyInput.value) > available) qtyInput.value = available;
        if (available < 1) qtyInput.value = 0;
    } else {
        qtyInput.removeAttribute('max');
        qtyInput.removeAttribute('title');
    }

    renderItemAvailCal(select);
}

/* ── Item Availability Calendar ── */
var itemAvailData   = <?php echo $item_avail_json; ?>;
var iacCurrentId    = null;
var iacViewDate     = new Date(); iacViewDate.setDate(1);
var IAC_MONTHS      = ['January','February','March','April','May','June','July','August','September','October','November','December'];
var IAC_DAYS        = ['S','M','T','W','T','F','S'];

function iacPad(n) { return String(n).padStart(2,'0'); }
function iacDS(y, m, d) { return y + '-' + iacPad(m+1) + '-' + iacPad(d); }

function renderItemAvailCal(select) {
    var wrap = document.getElementById('iac-wrap');
    if (!select.value || select.value === '__custom__') {
        wrap.style.display = 'none';
        iacCurrentId = null;
        return;
    }
    var opt      = select.options[select.selectedIndex];
    var itemId   = parseInt(opt.getAttribute('data-item-id'));
    var status   = opt.getAttribute('data-status');
    var totalQty = parseInt(opt.getAttribute('data-quantity')) || 0;
    if (!itemId || status !== 'borrowed') {
        wrap.style.display = 'none';
        iacCurrentId = null;
        return;
    }
    iacCurrentId = itemId;

    var records = itemAvailData[itemId] || [];
    var bar     = document.getElementById('iac-status-bar');

    // Sort records by return date ascending
    records = records.slice().sort(function(a, b) { return a.return_date.localeCompare(b.return_date); });

    var retChips = records.map(function(r) {
        var d = new Date(r.return_date + 'T00:00:00');
        var lbl = d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
        return '<span class="iac-ret-chip">' + lbl + '</span>';
    }).join('');
    bar.innerHTML = '<div class="iac-bar-top">'
                  + '<i class="fas fa-clock"></i>'
                  + '<span><strong>' + records.length + ' of ' + totalQty + ' units currently borrowed</strong></span>'
                  + '</div>'
                  + '<div class="iac-bar-chips"><span class="iac-bar-chips-label">Expected returns:</span>' + retChips + '</div>';
    bar.className = 'iac-bar iac-bar-borrowed';

    // Navigate to the month of the earliest return
    var earliest = new Date(records[0].return_date + 'T00:00:00');
    iacViewDate  = new Date(earliest.getFullYear(), earliest.getMonth(), 1);

    wrap.style.display = 'block';
    renderIacGrid(records);
}

function renderIacGrid(records) {
    var y  = iacViewDate.getFullYear(), m = iacViewDate.getMonth();
    var now = new Date();
    var todayStr = iacDS(now.getFullYear(), now.getMonth(), now.getDate());
    var todayMid = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var firstDay = new Date(y, m, 1).getDay();
    var daysInMo = new Date(y, m+1, 0).getDate();

    // Build a Set of all return date strings
    var retStrSet = {};
    var lastRetDate = null;
    (records || []).forEach(function(r) {
        retStrSet[r.return_date] = true;
        var d = new Date(r.return_date + 'T00:00:00');
        if (!lastRetDate || d > lastRetDate) lastRetDate = d;
    });

    document.getElementById('iac-nav').innerHTML =
        '<button onclick="iacNav(-1)"><i class="fas fa-chevron-left"></i></button>' +
        '<span>' + IAC_MONTHS[m] + ' ' + y + '</span>' +
        '<button onclick="iacNav(1)"><i class="fas fa-chevron-right"></i></button>';

    var h = '<div class="iac-grid">';
    IAC_DAYS.forEach(function(d) { h += '<div class="iac-dow">' + d + '</div>'; });
    for (var i = 0; i < firstDay; i++) h += '<div class="iac-cell"></div>';

    for (var d = 1; d <= daysInMo; d++) {
        var ds       = iacDS(y, m, d);
        var cellDate = new Date(y, m, d);
        var isToday  = ds === todayStr;
        var isPast   = cellDate < todayMid;
        var cls      = 'iac-cell';

        if (retStrSet[ds]) {
            cls += ' iac-returns';
        } else if (isPast) {
            cls += ' iac-past';
        } else if (lastRetDate && cellDate < lastRetDate) {
            cls += ' iac-unavail';
        } else if (lastRetDate && cellDate > lastRetDate) {
            cls += ' iac-avail';
        }
        if (isToday) cls += ' iac-today';

        h += '<div class="' + cls + '">' + d + '</div>';
    }
    h += '</div>';

    document.getElementById('iac-cal-grid').innerHTML = h;
}

window.iacNav = function(dir) {
    iacViewDate = new Date(iacViewDate.getFullYear(), iacViewDate.getMonth() + dir, 1);
    renderIacGrid(iacCurrentId ? (itemAvailData[iacCurrentId] || []) : []);
};
function selectItemReqCard(card) {
    document.querySelectorAll('#ishop-grid .bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
    card.classList.add('bshop-selected');
    var select = document.getElementById('item_description');
    select.value = card.getAttribute('data-value');
    handleItemCatalogChange(select);
    updateSummary();
}

function filterItemShop(btn) {
    if (btn) {
        document.querySelectorAll('#ishop-cats .bshop-cat').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
    }
    var activeCat = '';
    var activeBtn = document.querySelector('#ishop-cats .bshop-cat.active');
    if (activeBtn) activeCat = activeBtn.getAttribute('data-cat') || '';
    var search = (document.getElementById('ishop-search').value || '').toLowerCase().trim();
    var visible = 0;
    document.querySelectorAll('#ishop-grid .bshop-card').forEach(function(card) {
        var cat = card.getAttribute('data-category') || '';
        var name = (card.querySelector('.bshop-name') ? card.querySelector('.bshop-name').textContent : '').toLowerCase();
        var isCustom = card.classList.contains('bshop-card-custom');
        var catMatch = !activeCat || cat === activeCat || isCustom;
        var searchMatch = !search || name.indexOf(search) !== -1;
        var show = catMatch && searchMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var emptyEl = document.getElementById('ishop-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'flex' : 'none';
}

function selectServiceCard(card) {
    document.querySelectorAll('#sshop-grid .bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
    card.classList.add('bshop-selected');
    var select = document.getElementById('item_id');
    select.value = card.getAttribute('data-value');
    updateSummary();
}

function filterServiceShop(btn) {
    if (btn) {
        document.querySelectorAll('#sshop-cats .bshop-cat').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
    }
    var activeCat = '';
    var activeBtn = document.querySelector('#sshop-cats .bshop-cat.active');
    if (activeBtn) activeCat = activeBtn.getAttribute('data-cat') || '';
    var search = (document.getElementById('sshop-search').value || '').toLowerCase().trim();
    var visible = 0;
    document.querySelectorAll('#sshop-grid .bshop-card').forEach(function(card) {
        var cat = card.getAttribute('data-category') || '';
        var name = (card.querySelector('.bshop-name') ? card.querySelector('.bshop-name').textContent : '').toLowerCase();
        var catMatch = !activeCat || cat === activeCat;
        var searchMatch = !search || name.indexOf(search) !== -1;
        var show = catMatch && searchMatch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    var emptyEl = document.getElementById('sshop-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'flex' : 'none';
}

function handleItemCatalogChange(select) {
    var customWrap  = document.getElementById('custom_item_req_wrap');
    var customInput = document.getElementById('custom_item_req_name');
    var descWrap    = document.getElementById('item_desc_display_wrap');
    var descInput   = document.getElementById('item_desc_display');
    var descText    = document.getElementById('item_desc_display_text');
    if (select.value === '__custom__') {
        customWrap.style.display = 'block'; customInput.setAttribute('required','required');
        descWrap.style.display = 'none'; descInput.value = '';
        select.removeAttribute('required');
    } else if (select.value !== '') {
        customWrap.style.display = 'none'; customInput.removeAttribute('required'); customInput.value = '';
        select.setAttribute('required','required');
        var desc = select.options[select.selectedIndex].getAttribute('data-desc');
        if (desc) { descInput.value = desc; descText.textContent = desc; descWrap.style.display = 'block'; }
        else       { descWrap.style.display = 'none'; descInput.value = ''; }
    } else {
        customWrap.style.display = 'none'; customInput.removeAttribute('required'); customInput.value = '';
        descWrap.style.display = 'none'; descInput.value = '';
    }
}

/* ── Cart ── */
var cart = [];

function addToCart() {
    var type = document.getElementById('request_type_hidden').value;
    var entry = {};
    if (type === 'borrow') {
        var sel = document.getElementById('borrow_catalog_select');
        var name = (sel.value && sel.value !== '__custom__') ? sel.value : document.getElementById('custom_item_name').value.trim();
        if (!name) { showCartError('Please select or enter an item to borrow.'); return; }
        var rd = document.getElementById('expected_return_date').value;
        if (!rd) { showCartError('Please enter the expected return date.'); return; }
        var qty = parseInt(document.getElementById('borrow_quantity').value) || 1;
        var reason = document.getElementById('reason').value.trim();
        var borrowCard = document.querySelector('#bshop-grid .bshop-card.bshop-selected');
        var inventoryId = borrowCard ? (borrowCard.getAttribute('data-item-id') || '') : '';
        entry = { type:'borrow', name:name, inventory_id:inventoryId, qty:qty, return_date:rd, reason:reason };
    } else if (type === 'item') {
        var sel2 = document.getElementById('item_description');
        var name2 = (sel2.value && sel2.value !== '__custom__') ? sel2.value : document.getElementById('custom_item_req_name').value.trim();
        if (!name2) { showCartError('Please select or enter an item name.'); return; }
        var qty2 = parseInt(document.getElementById('quantity').value) || 1;
        var reason2 = document.getElementById('item_reason').value.trim();
        entry = { type:'item', name:name2, qty:qty2, reason:reason2 };
    } else {
        var itemSel = document.getElementById('item_id');
        if (!itemSel.value) { showCartError('Please select an item requiring service.'); return; }
        var svcType = document.getElementById('service_type').value;
        if (!svcType) { showCartError('Please select a service type.'); return; }
        var svcDesc = document.getElementById('service_description').value.trim();
        if (!svcDesc) { showCartError('Please describe the service needed.'); return; }
        entry = { type:'service', name:itemSel.options[itemSel.selectedIndex].text, item_id:itemSel.value, service_type:svcType, description:svcDesc };
    }
    cart.push(entry);
    renderCart();
    resetStaging(type);
    updateSummary();
    hideCartError();
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
    updateSummary();
}

function renderCart() {
    var listEl = document.getElementById('rq-cart-list');
    var badge  = document.getElementById('cart-count-badge');
    if (badge) badge.textContent = cart.length;
    if (cart.length === 0) {
        listEl.innerHTML = '<div class="rq-cart-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.22;"></i>No items added yet. Fill in the details above and click <strong>Add to List</strong>.</div>';
        return;
    }
    var svcLabels = { repair:'Repair', maintenance:'Maintenance', inspection:'Inspection', cleaning:'Cleaning', other:'Other' };
    var html = '';
    cart.forEach(function(e, i) {
        var meta = [];
        if (e.qty && e.qty > 1) meta.push('Qty: ' + e.qty);
        if (e.return_date) meta.push('Return: ' + new Date(e.return_date + 'T00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
        if (e.service_type) meta.push(svcLabels[e.service_type] || e.service_type);
        if (e.description)  meta.push(e.description.substring(0,40) + (e.description.length > 40 ? '…' : ''));
        if (e.reason)       meta.push(e.reason.substring(0,40) + (e.reason.length > 40 ? '…' : ''));
        html += '<div class="rq-cart-item">' +
            '<div class="rq-cart-item-num">' + (i+1) + '</div>' +
            '<div class="rq-cart-item-body">' +
                '<div class="rq-cart-item-name">' + escHtml(e.name) + '</div>' +
                (meta.length ? '<div class="rq-cart-item-meta">' + meta.map(function(m){ return '<span>' + escHtml(m) + '</span>'; }).join(' · ') + '</div>' : '') +
            '</div>' +
            '<button type="button" class="rq-cart-remove" onclick="removeFromCart(' + i + ')"><i class="fas fa-times"></i></button>' +
        '</div>';
    });
    listEl.innerHTML = html;
}

function resetStaging(type) {
    if (type === 'borrow') {
        document.getElementById('borrow_catalog_select').value = '';
        document.querySelectorAll('.bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
        document.getElementById('custom_item_name').value = '';
        document.getElementById('custom_item_wrap').style.display = 'none';
        document.getElementById('expected_return_date').value = '';
        document.getElementById('borrow_quantity').value = 1;
        document.getElementById('reason').value = '';
        var iacWrap = document.getElementById('iac-wrap');
        if (iacWrap) iacWrap.style.display = 'none';
    } else if (type === 'item') {
        document.getElementById('item_description').value = '';
        document.querySelectorAll('#ishop-grid .bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
        document.getElementById('custom_item_req_name').value = '';
        document.getElementById('custom_item_req_wrap').style.display = 'none';
        document.getElementById('item_desc_display_wrap').style.display = 'none';
        document.getElementById('quantity').value = 1;
        document.getElementById('item_reason').value = '';
    } else {
        document.getElementById('item_id').value = '';
        document.querySelectorAll('#sshop-grid .bshop-card').forEach(function(c) { c.classList.remove('bshop-selected'); });
        document.getElementById('service_type').value = '';
        document.getElementById('service_description').value = '';
    }
}

function showCartError(msg) {
    var el = document.getElementById('cart-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; el.scrollIntoView({behavior:'smooth',block:'nearest'}); }
}
function hideCartError() {
    var el = document.getElementById('cart-error');
    if (el) el.style.display = 'none';
}
function escHtml(str) {
    var d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
}

/* ── Live summary ── */
function updateSummary() {
    var type    = document.getElementById('request_type_hidden').value;
    var urgency = document.querySelector('input[name=urgency]:checked');
    document.getElementById('sum_type').textContent    = typeConfig[type]?.label || '—';
    document.getElementById('sum_urgency').textContent = urgency ? urgency.value.charAt(0).toUpperCase() + urgency.value.slice(1) : '—';

    var sumItemsEl = document.getElementById('sum_items_list');
    var sumCountEl = document.getElementById('sum_items_count');
    if (sumCountEl) sumCountEl.textContent = cart.length ? cart.length + ' item' + (cart.length !== 1 ? 's' : '') : '—';
    if (!sumItemsEl) return;

    if (cart.length === 0) {
        var stagingName = '';
        if (type === 'borrow') {
            var sel = document.getElementById('borrow_catalog_select');
            stagingName = (sel.value && sel.value !== '__custom__') ? sel.value : document.getElementById('custom_item_name').value;
        } else if (type === 'item') {
            var sel2 = document.getElementById('item_description');
            stagingName = (sel2.value && sel2.value !== '__custom__') ? sel2.value : document.getElementById('custom_item_req_name').value;
        } else {
            var sel3 = document.getElementById('item_id');
            stagingName = sel3.value ? sel3.options[sel3.selectedIndex].text : '';
        }
        sumItemsEl.innerHTML = stagingName
            ? '<div class="rq-sum-preview-item"><span class="rq-sum-preview-num">?</span><span style="font-size:0.82rem;font-weight:600;">' + escHtml(stagingName) + '</span> <span style="font-size:0.72rem;color:rgba(0,0,0,0.35);">(not added)</span></div>'
            : '<div style="font-size:0.75rem;color:rgba(0,0,0,0.28);font-style:italic;padding:2px 0;">Nothing staged yet</div>';
    } else {
        var previewHtml = '';
        cart.forEach(function(e, i) {
            previewHtml += '<div class="rq-sum-preview-item">' +
                '<span class="rq-sum-preview-num">' + (i+1) + '</span>' +
                '<span style="font-size:0.82rem;font-weight:600;">' + escHtml(e.name) + '</span>' +
                (e.qty > 1 ? ' <span style="font-size:0.72rem;color:rgba(0,0,0,0.40);">×' + e.qty + '</span>' : '') +
            '</div>';
        });
        sumItemsEl.innerHTML = previewHtml;
    }
}

/* ── Form submit ── */
document.getElementById('requestForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        showCartError('Please add at least one item to your request before submitting.');
        document.getElementById('rq-cart-area').scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }
    document.getElementById('items_json').value = JSON.stringify(cart);
});

// Init
renderCart();
updateSummary();

// Pre-select item from inventory page
<?php if ($auto_fill_item): ?>
    (function() {
        var autoVal = '<?php echo htmlspecialchars($auto_fill_item, ENT_QUOTES); ?>';
        // Select the matching shop card
        var matchCard = null;
        document.querySelectorAll('.bshop-card').forEach(function(c) {
            if (c.getAttribute('data-value') === autoVal) matchCard = c;
        });
        if (matchCard) {
            matchCard.classList.add('bshop-selected');
            // Scroll card into view inside the grid
            setTimeout(function() { matchCard.scrollIntoView({ block: 'nearest' }); }, 100);
        }
        // Sync hidden select and trigger logic
        var selectElement = document.getElementById('borrow_catalog_select');
        if (selectElement) {
            selectElement.value = autoVal;
            handleCatalogChange(selectElement);
            var returnDateInput = document.getElementById('expected_return_date');
            if (returnDateInput && !returnDateInput.value) {
                var d = new Date(); d.setDate(d.getDate() + 7);
                returnDateInput.value = d.getFullYear() + '-' +
                    String(d.getMonth()+1).padStart(2,'0') + '-' +
                    String(d.getDate()).padStart(2,'0');
            }
            updateSummary();
            setTimeout(function() {
                selectElement.closest('.rq-form-card').scrollIntoView({ behavior:'smooth', block:'center' });
            }, 150);
        }
    })();
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
