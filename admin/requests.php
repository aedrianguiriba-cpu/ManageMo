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
    $request_id = sanitizeInput($_POST['request_id']);
    $action_type = sanitizeInput($_POST['action']);

    startSession();
    if ($action_type === 'approve') {
        $approval_notes = sanitizeInput($_POST['approval_notes']);
        $_SESSION['request_overrides'][(int)$request_id]['status'] = 'approved';
        if ($approval_notes) $_SESSION['request_overrides'][(int)$request_id]['approval_notes'] = $approval_notes;
        logActivity($current_user['id'], 'APPROVE', "Approved request #$request_id", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&id=' . $request_id, 'Request approved successfully!', 'success');
    } elseif ($action_type === 'disapprove') {
        $approval_notes = sanitizeInput($_POST['approval_notes']);
        $_SESSION['request_overrides'][(int)$request_id]['status'] = 'disapproved';
        if ($approval_notes) $_SESSION['request_overrides'][(int)$request_id]['approval_notes'] = $approval_notes;
        logActivity($current_user['id'], 'DISAPPROVE', "Disapproved request #$request_id", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&id=' . $request_id, 'Request disapproved.', 'info');
    } elseif ($action_type === 'change_receiving_method') {
        $new_method = sanitizeInput($_POST['receiving_method'] ?? '');
        if (in_array($new_method, ['delivery', 'pickup'])) {
            $_SESSION['request_overrides'][(int)$request_id]['receiving_method'] = $new_method;
            logActivity($current_user['id'], 'UPDATE', "Changed receiving method of request #$request_id to $new_method", 'requests', $request_id);
            redirectWithMessage('requests.php?action=view&id=' . $request_id, 'Receiving method updated to ' . ucfirst($new_method) . '.', 'success');
        }
    } elseif ($action_type === 'mark_out_for_delivery') {
        $_SESSION['request_overrides'][(int)$request_id]['delivery_status'] = 'out_for_delivery';
        // Send email notification
        $req_data  = findById(getRequests(), (int)$request_id);
        $req_over  = $_SESSION['request_overrides'][(int)$request_id] ?? [];
        $req_data  = array_merge($req_data ?? [], $req_over);
        $notif_user = findById(getUsers(), $req_data['user_id'] ?? 0);
        $req_num   = $req_data['request_number'] ?? 'REQ-' . str_pad($request_id, 5, '0', STR_PAD_LEFT);
        $recv_method = $req_data['receiving_method'] ?? 'delivery';
        if ($notif_user) {
            $stage = ($recv_method === 'pickup') ? 'pickup_ready' : 'out_for_delivery';
            sendDeliveryEmail($notif_user['email'], $notif_user['full_name'], $req_num, $stage);
        }
        logActivity($current_user['id'], 'UPDATE', "Marked request #$request_id as out for delivery", 'requests', $request_id);
        $label = ($recv_method === 'pickup') ? 'Marked as Ready for Pickup. Email notification sent.' : 'Marked as Out for Delivery. Email notification sent.';
        redirectWithMessage('requests.php?action=view&id=' . $request_id, $label, 'success');
    } elseif ($action_type === 'mark_delivered') {
        $_SESSION['request_overrides'][(int)$request_id]['delivery_status'] = 'delivered';
        $_SESSION['request_overrides'][(int)$request_id]['status'] = 'delivered';
        logActivity($current_user['id'], 'UPDATE', "Marked request #$request_id as delivered", 'requests', $request_id);
        redirectWithMessage('requests.php?action=view&id=' . $request_id, 'Request marked as Delivered.', 'success');
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
displayMessage();

// Get all requests from hardcoded data
$all_requests = getRequests();
$users_data = getUsers();
$inventory_data = getInventory();

// Apply session-stored status overrides to the list
startSession();
if (!empty($_SESSION['request_overrides'])) {
    foreach ($all_requests as &$_req) {
        if (!empty($_SESSION['request_overrides'][(int)$_req['id']])) {
            $_req = array_merge($_req, $_SESSION['request_overrides'][(int)$_req['id']]);
        }
    }
    unset($_req);
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

// Calculate pagination
$total = count($filtered_requests);
$total_pages = ceil($total / ITEMS_PER_PAGE);

// Get requests for current page
$offset = ($page - 1) * ITEMS_PER_PAGE;
$requests = [];

foreach (array_slice($filtered_requests, $offset, ITEMS_PER_PAGE) as $req) {
    $user = findById($users_data, $req['user_id']);
    $item = findById($inventory_data, $req['inventory_id']);
    
    $requests[] = array_merge($req, [
        'full_name'  => $user['full_name']  ?? 'Unknown',
        'email'      => $user['email']      ?? 'N/A',
        'campus_id'  => $user['campus_id']  ?? NULL,
        'college_id' => $user['college_id'] ?? NULL,
        'item_name'  => $item['item_name']  ?? 'Unknown Item',
        'qr_code_id' => $item['qr_code_id'] ?? 'N/A'
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
        $request_id = sanitizeInput($_GET['id']);
        $request = findById(getRequests(), (int)$request_id);

        if (!$request) {
            die('<div class="alert alert-danger">Request not found</div>');
        }
        
        $user = findById(getUsers(), $request['user_id']);
        $item = $request['inventory_id'] ? findById(getInventory(), $request['inventory_id']) : null;
        // For item/service requests with no inventory link, derive item name from description
        $fallback_item_name = $item['item_name'] ?? (trim($request['service_description'] ?? '') ?: 'Not specified');
        $fallback_qr        = $item['qr_code_id'] ?? ($request['request_number'] ?? 'N/A');
        
        $request = array_merge($request, [
            'full_name' => $user['full_name'] ?? 'Unknown',
            'email' => $user['email'] ?? 'N/A',
            'phone' => $user['phone'] ?? 'N/A',
            'campus_id' => $user['campus_id'] ?? null,
            'college_id' => $user['college_id'] ?? null,
            'item_name'          => $fallback_item_name,
            'qr_code_id'         => $fallback_qr,
            'item_location'      => $item['location']   ?? 'N/A',
            'item_category'      => $item['category']   ?? ucfirst($request['request_type'] ?? 'Request'),
            'item_condition'     => $item['condition']  ?? 'N/A',
            'quantity_requested' => $request['quantity_requested'] ?? 1,
            'request_number' => $request['request_number'] ?? 'REQ-' . str_pad($request['id'], 5, '0', STR_PAD_LEFT),
        ]);

        // Apply any session-stored overrides (status changes, receiving method changes)
        startSession();
        if (!empty($_SESSION['request_overrides'][(int)$request['id']])) {
            $request = array_merge($request, $_SESSION['request_overrides'][(int)$request['id']]);
        }

        $status_colors = ['pending'=>'warning','approved'=>'success','disapproved'=>'danger','delivered'=>'info','returned'=>'primary','completed'=>'success'];
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

        <?php if ($request['request_type'] === 'borrow'): ?>
        <div class="ar-card mb-3">
            <div class="ar-section-label">Borrow Details</div>
            <div class="row">
                <div class="col-md-6 ar-info-row">
                    <p><strong>Item:</strong><?php echo htmlspecialchars($request['item_name']); ?></p>
                    <p><strong>QR Code:</strong><span style="font-family:monospace;background:rgba(139,0,0,0.07);color:#8B0000;border-radius:5px;padding:1px 6px;font-size:0.8rem;"><?php echo htmlspecialchars($request['qr_code_id']); ?></span></p>
                    <p><strong>Quantity:</strong><span style="font-weight:800;color:#8B0000;"><?php echo (int)$request['quantity_requested']; ?></span> <span style="font-size:.75rem;color:rgba(0,0,0,.4);">unit(s)</span></p>
                </div>
                <div class="col-md-6 ar-info-row">
                    <p><strong>Expected Return:</strong><?php echo $request['expected_return_date'] ? formatDate($request['expected_return_date']) : 'Not specified'; ?></p>
                </div>
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
                    <div class="mb-3">
                        <label class="form-label">Approval Notes <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" name="approval_notes" rows="3" placeholder="Notes for the requester"></textarea>
                    </div>
                    <button type="submit" class="btn ar-btn-success"><i class="fas fa-check me-1"></i> Approve Request</button>
                </form>
            </div>
            <div class="ar-tab-pane" id="disapprove-tab">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                    <input type="hidden" name="action" value="disapprove">
                    <div class="mb-3">
                        <label class="form-label">Reason for Disapproval *</label>
                        <textarea class="form-control" name="approval_notes" rows="3" placeholder="Explain why this request is being disapproved" required></textarea>
                    </div>
                    <button type="submit" class="btn ar-btn-danger"><i class="fas fa-times me-1"></i> Disapprove Request</button>
                </form>
            </div>
        </div>
        <?php elseif (!empty($request['approval_notes'])): ?>
        <div class="ar-card">
            <div class="ar-section-label">Admin Notes</div>
            <div class="ar-desc-box"><?php echo nl2br(htmlspecialchars($request['approval_notes'])); ?></div>
            <?php $approver = findById(getUsers(), $request['approved_by'] ?? 0); ?>
            <?php if ($approver): ?><small style="color:rgba(0,0,0,0.40);font-size:0.76rem;">By: <?php echo htmlspecialchars($approver['full_name']); ?></small><?php endif; ?>
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

        <div class="mt-3">
            <a href="requests.php" class="btn ar-btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>

        <!-- QR Sticker Overlay -->
        <div class="ar-sticker-overlay" id="arStickerPrint">
            <div class="ar-sticker-sheet-wrap">
                <div class="ar-sticker-sheet-hdr" style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:18px;">
                    <div>
                        <div style="font-weight:800;font-size:1rem;color:#0f172a;"><i class="fas fa-qrcode me-2" style="color:#8B0000;"></i>Print QR Sticker Sheet</div>
                        <div style="font-size:.78rem;color:rgba(0,0,0,.44);margin-top:2px;"><?php echo (int)$request['quantity_requested']; ?> sticker(s) &mdash; based on requested quantity &mdash; print on label paper (A4 / Letter)</div>
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
            qr:        <?php echo json_encode($request['qr_code_id']); ?>,
            location:  <?php echo json_encode($request['item_location']); ?>,
            category:  <?php echo json_encode($request['item_category']); ?>,
            condition: <?php echo json_encode($request['item_condition']); ?>,
            reqno:     <?php echo json_encode($request['request_number']); ?>,
            requester: <?php echo json_encode($request['full_name']); ?>
        };
        var _sqty = <?php echo (int)$request['quantity_requested']; ?>;

        function _buildSticker() {
            var qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(_sd.qr);
            return '<div class="ar-sticker">'
                + '<div class="ar-sticker-top">' + _sd.short + ' &mdash; Asset Label<span>' + _sd.institution + '</span></div>'
                + '<div class="ar-sticker-qr"><img src="' + qrUrl + '" alt="QR" loading="eager" width="90" height="90"></div>'
                + '<div class="ar-sticker-body">'
                +   '<div class="ar-sticker-name">' + _esc(_sd.item) + '</div>'
                +   '<div class="ar-sticker-row"><strong>Location:</strong><span>' + _esc(_sd.location) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Category:</strong><span>' + _esc(_sd.category) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Condition:</strong><span>' + _esc(_sd.condition) + '</span></div>'
                +   '<div class="ar-sticker-row"><strong>Issued To:</strong><span>' + _esc(_sd.requester) + '</span></div>'
                +   '<div class="ar-sticker-code">' + _esc(_sd.qr) + '</div>'
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
            var h = '';
            for (var i = 0; i < _sqty; i++) h += _buildSticker();
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
        $count_all     = count(getRequests());
        $all_reqs_raw  = getRequests();
        // Apply session overrides for counts
        foreach ($all_reqs_raw as &$_rc) {
            if (!empty($_SESSION['request_overrides'][(int)$_rc['id']])) {
                $_rc = array_merge($_rc, $_SESSION['request_overrides'][(int)$_rc['id']]);
            }
        } unset($_rc);
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
        // Re-paginate
        $total       = count($filtered_requests);
        $total_pages = ceil($total / ITEMS_PER_PAGE);
        $offset      = ($page - 1) * ITEMS_PER_PAGE;
        $requests    = [];
        foreach (array_slice($filtered_requests, $offset, ITEMS_PER_PAGE) as $rr) {
            $u = findById($users_data, $rr['user_id']);
            $it = findById($inventory_data, $rr['inventory_id']);
            $requests[] = array_merge($rr, [
                'full_name'  => $u['full_name']  ?? 'Unknown',
                'email'      => $u['email']      ?? 'N/A',
                'campus_id'  => $u['campus_id']  ?? null,
                'college_id' => $u['college_id'] ?? null,
                'item_name'  => $it['item_name'] ?? 'Unknown Item',
                'qr_code_id' => $it['qr_code_id'] ?? 'N/A',
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
                    <td><a href="requests.php?action=view&id=<?php echo $req['id']; ?>&tab=item" class="ar-btn-view"><i class="fas fa-eye"></i> View</a></td>
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
                    <td><a href="requests.php?action=view&id=<?php echo $req['id']; ?>" class="ar-btn-view"><i class="fas fa-eye"></i> View</a></td>
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
                    <a href="requests.php?action=view&id=<?php echo $req['id'];?>" class="ar-btn-view" style="font-size:.77rem;"><i class="fas fa-eye"></i> View</a>
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
