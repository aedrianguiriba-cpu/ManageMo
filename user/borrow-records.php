<?php
$page_title = 'My Records';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();
$user_id = $current_user['id'];
$active_tab    = $_GET['tab']    ?? 'borrow';
$status_filter = $_GET['status'] ?? '';

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
$all_borrows    = getBorrowRecords();
$user_borrows   = filterByColumn($all_borrows, 'user_id', $user_id);
$all_inventory  = getInventory();

$borrow_records = [];
foreach ($user_borrows as $borrow) {
    $item   = findById($all_inventory, $borrow['inventory_id']);
    $record = array_merge($borrow, [
        'item_name'  => $item['item_name']  ?? $borrow['item_name']  ?? 'Unknown',
        'qr_code_id' => $item['qr_code_id'] ?? $borrow['qr_code_id'] ?? 'N/A',
    ]);
    if (!$status_filter || $active_tab !== 'borrow' || $record['status'] === $status_filter) {
        $borrow_records[] = $record;
    }
}
usort($borrow_records, fn($a, $b) => strcmp($b['borrow_date'], $a['borrow_date']));

/* ── Item & service requests ── */
$all_requests = getRequests();
$item_requests    = [];
$service_requests = [];
foreach ($all_requests as $req) {
    if ($req['user_id'] != $user_id) continue;
    $item = findById($all_inventory, $req['inventory_id']);
    $req['item_name'] = $req['item_name'] ?? ($item['item_name'] ?? null);
    if ($req['request_type'] === 'item') {
        if (!$status_filter || $active_tab !== 'item' || $req['status'] === $status_filter) {
            $item_requests[] = $req;
        }
    } elseif ($req['request_type'] === 'service') {
        if (!$status_filter || $active_tab !== 'service' || $req['status'] === $status_filter) {
            $service_requests[] = $req;
        }
    }
}
usort($item_requests,    fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
usort($service_requests, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

/* ── Stats ── */
$stat_borrow_active   = count(filterByColumn($user_borrows, 'status', 'active'));
$stat_borrow_returned = count(filterByColumn($user_borrows, 'status', 'returned'));
$stat_borrow_overdue  = count(filterByColumn($user_borrows, 'status', 'overdue'));

$all_mine_item    = array_filter(getRequests(), fn($r) => $r['user_id'] == $user_id && $r['request_type'] === 'item');
$all_mine_service = array_filter(getRequests(), fn($r) => $r['user_id'] == $user_id && $r['request_type'] === 'service');

displayMessage();
?>

<style>
/* ===== MY RECORDS PAGE ===== */
.br-title {
    font-size:1.35rem; font-weight:800; color:#1a1d23; margin:0;
    display:flex; align-items:center; gap:10px;
}
.br-title-icon {
    width:40px; height:40px;
    background:#8B0000;
    border-radius:8px; display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1rem; flex-shrink:0;
}
/* Tabs */
.br-tabs {
    display:flex; gap:6px; flex-wrap:wrap;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px; padding:6px; width:fit-content;
}
.br-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 18px; border-radius:6px; font-size:0.85rem; font-weight:700;
    color:#555; background:transparent; border:none; cursor:pointer;
    text-decoration:none; transition:background 0.15s, color 0.15s; white-space:nowrap;
}
.br-tab:hover { background:#f7f7f7; color:#111; text-decoration:none; }
.br-tab.active { background:#8B0000; color:#fff; }
.br-tab .br-tab-count { background:rgba(255,255,255,0.25); color:#fff; border-radius:4px; padding:1px 7px; font-size:0.68rem; }
.br-tab:not(.active) .br-tab-count { background:#e5e7eb; color:#555; }
/* Stat cards */
.br-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.br-stat-card {
    flex:1; min-width:130px;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:14px 16px; display:flex; align-items:center; gap:12px;
}
.br-stat-icon { width:36px; height:36px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
.br-stat-val { font-size:1.55rem; font-weight:800; color:#111; line-height:1; }
.br-stat-lbl { font-size:0.73rem; color:#555; font-weight:600; margin-top:2px; }
/* Filter */
.br-filter {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    padding:12px 16px; margin-bottom:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;
}
.br-filter-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#999; white-space:nowrap; }
.br-filter .form-select { border-radius:6px !important; font-size:0.87rem !important; max-width:220px; }
/* Table card */
.br-table-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); overflow:hidden;
}
.br-table-card table { margin:0; }
.br-table-card thead th {
    font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
    color:rgba(0,0,0,0.42) !important; background:rgba(0,0,0,0.03) !important;
    padding:12px 16px; border:none !important;
}
.br-table-card tbody td { padding:12px 16px; vertical-align:middle; color:#374151 !important; border-color:rgba(0,0,0,0.05) !important; font-size:0.87rem; }
.br-table-card tbody tr:hover td { background:rgba(0,0,0,0.02) !important; }
.br-table-card tbody tr:last-child td { border-bottom:none !important; }
.br-item-name { font-weight:700; color:#1a1d23; font-size:0.90rem; }
.br-qr-chip { font-size:0.70rem; font-family:monospace; background:rgba(0,0,0,0.06); border:1px solid rgba(0,0,0,0.09); border-radius:6px; padding:2px 7px; color:#374151; white-space:nowrap; }
.br-date { font-size:0.83rem; color:#374151; white-space:nowrap; }
.br-date-warn { color:#b45309; font-weight:600; }
.br-desc-cell { max-width:240px; font-size:0.82rem; color:rgba(0,0,0,0.55); }
.br-badge { display:inline-flex; align-items:center; gap:5px; font-size:0.72rem; font-weight:700; padding:4px 11px; border-radius:20px; white-space:nowrap; }
.br-badge-active      { background:rgba(59,130,246,0.12);  color:#1d4ed8; border:1px solid rgba(59,130,246,0.22); }
.br-badge-returned    { background:rgba(34,197,94,0.12);   color:#15803d; border:1px solid rgba(34,197,94,0.22); }
.br-badge-overdue     { background:rgba(239,68,68,0.12);   color:#b91c1c; border:1px solid rgba(239,68,68,0.22); }
.br-badge-pending     { background:rgba(245,158,11,0.12);  color:#b45309; border:1px solid rgba(245,158,11,0.22); }
.br-badge-approved    { background:rgba(34,197,94,0.12);   color:#15803d; border:1px solid rgba(34,197,94,0.22); }
.br-badge-disapproved { background:rgba(239,68,68,0.10);   color:#b91c1c; border:1px solid rgba(239,68,68,0.18); }
.br-notes { font-size:0.80rem; color:rgba(0,0,0,0.45); }
.br-empty { text-align:center; padding:48px 20px; color:rgba(0,0,0,0.38); }
.br-empty i { font-size:2rem; margin-bottom:10px; display:block; }
/* Selection — comma-grouped ::selection rules are silently ignored by browsers; each must be its own rule */
.br-table-card *::selection  { background:#8B0000; color:#fff; }
.br-badge::selection         { background:#8B0000; color:#fff; }
.br-badge *::selection       { background:#8B0000; color:#fff; }
.br-tab::selection           { background:#1a1d23; color:#fff; }
.br-tab *::selection         { background:#1a1d23; color:#fff; }
.br-tab.active::selection    { background:#fff;    color:#8B0000; }
.br-tab.active *::selection  { background:#fff;    color:#8B0000; }
/* Print button */
.br-print-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 13px; border-radius:9px; font-size:0.80rem; font-weight:700;
    background:rgba(0,0,0,0.06); border:1px solid rgba(0,0,0,0.10); color:#374151;
    cursor:pointer; transition:background 0.15s; white-space:nowrap;
}
.br-print-btn:hover { background:rgba(0,0,0,0.12); }
@media print {
    .sidebar, .sidebar-overlay, .sidebar-toggle-btn, .topbar { display:none !important; }
    .br-tabs, .br-stats, .br-filter, .alert, .br-print-btn { display:none !important; }
    body, .main-wrapper, .container-fluid { background:#fff !important; padding:0 !important; margin:0 !important; }
    .br-table-card { box-shadow:none !important; border:1px solid #ccc !important; border-radius:4px !important; }
    .br-table-card thead th { background:#f3f4f6 !important; color:#000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .br-table-card tbody td { color:#000 !important; border-color:#dee2e6 !important; }
    .br-badge { background:#eee !important; color:#000 !important; border-color:#999 !important; }
    .br-item-name, .br-date, .br-notes, .br-qr-chip, .br-desc-cell { color:#000 !important; }
}
</style>

<div class="container-fluid mt-4 pb-4">

    <!-- Tabs -->
    <div class="br-tabs mb-4">
        <a href="borrow-records.php?tab=borrow" class="br-tab <?php echo $active_tab === 'borrow' ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding"></i> Borrow Records
            <span class="br-tab-count"><?php echo count($user_borrows); ?></span>
        </a>
        <a href="borrow-records.php?tab=item" class="br-tab <?php echo $active_tab === 'item' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i> Item Requests
            <span class="br-tab-count"><?php echo count($all_mine_item); ?></span>
        </a>
        <a href="borrow-records.php?tab=service" class="br-tab <?php echo $active_tab === 'service' ? 'active' : ''; ?>">
            <i class="fas fa-tools"></i> Service Requests
            <span class="br-tab-count"><?php echo count($all_mine_service); ?></span>
        </a>
    </div>

    <?php if ($active_tab === 'borrow'): ?>
    <!-- ══ BORROW RECORDS ══ -->

    <?php if ($stat_borrow_overdue > 0): ?>
    <div class="alert alert-danger mb-4" style="border-radius:14px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Overdue!</strong> You have <?php echo $stat_borrow_overdue; ?> overdue item<?php echo $stat_borrow_overdue > 1 ? 's' : ''; ?>. Please return them as soon as possible.
    </div>
    <?php endif; ?>

    <div class="br-stats">
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#1d4ed8;"><i class="fas fa-hand-holding"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_borrow_active; ?></div><div class="br-stat-lbl">Active</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#15803d;"><i class="fas fa-check-circle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_borrow_returned; ?></div><div class="br-stat-lbl">Returned</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#b91c1c;"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_borrow_overdue; ?></div><div class="br-stat-lbl">Overdue</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#4b5563;"><i class="fas fa-list"></i></div>
            <div><div class="br-stat-val"><?php echo count($user_borrows); ?></div><div class="br-stat-lbl">Total</div></div>
        </div>
    </div>

    <div class="br-filter">
        <span class="br-filter-label"><i class="fas fa-filter me-1"></i>Status</span>
        <form method="GET">
            <input type="hidden" name="tab" value="borrow">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All Records</option>
                <option value="active"   <?php echo $status_filter === 'active'   ? 'selected' : ''; ?>>Active</option>
                <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                <option value="overdue"  <?php echo $status_filter === 'overdue'  ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </form>
        <span style="font-size:0.80rem;color:rgba(0,0,0,0.38);margin-left:auto;"><?php echo count($borrow_records); ?> record<?php echo count($borrow_records) !== 1 ? 's' : ''; ?></span>
        <button class="br-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="br-table-card">
        <div class="table-responsive">
            <table class="table">
                <thead><tr>
                    <th>Item</th><th>QR Code</th><th>Borrowed</th>
                    <th>Expected Return</th><th>Returned On</th><th>Status</th><th>Notes</th>
                </tr></thead>
                <tbody>
                <?php if (count($borrow_records) > 0):
                    foreach ($borrow_records as $rec):
                        $days_overdue = 0;
                        if ($rec['status'] === 'active' && strtotime($rec['expected_return_date']) < time()) {
                            $days_overdue = floor((time() - strtotime($rec['expected_return_date'])) / 86400);
                        }
                        $is_overdue_active = $days_overdue > 0 && $rec['status'] === 'active';
                        $bmap  = ['active'=>'br-badge-active','returned'=>'br-badge-returned','overdue'=>'br-badge-overdue'];
                        $bimap = ['active'=>'fa-circle-dot','returned'=>'fa-check','overdue'=>'fa-clock'];
                ?>
                <tr>
                    <td><span class="br-item-name"><?php echo htmlspecialchars($rec['item_name']); ?></span></td>
                    <td><span class="br-qr-chip"><?php echo htmlspecialchars($rec['qr_code_id']); ?></span></td>
                    <td><span class="br-date"><?php echo formatDate($rec['borrow_date'], 'M d, Y'); ?></span></td>
                    <td>
                        <span class="br-date <?php echo $is_overdue_active ? 'br-date-warn' : ''; ?>">
                            <?php echo formatDate($rec['expected_return_date'], 'M d, Y'); ?>
                            <?php if ($is_overdue_active): ?><br><small>(<?php echo $days_overdue; ?>d overdue)</small><?php endif; ?>
                        </span>
                    </td>
                    <td><span class="br-date"><?php echo $rec['actual_return_date'] ? formatDate($rec['actual_return_date'], 'M d, Y') : '—'; ?></span></td>
                    <td>
                        <span class="br-badge <?php echo $bmap[$rec['status']] ?? 'br-badge-active'; ?>">
                            <i class="fas <?php echo $bimap[$rec['status']] ?? 'fa-circle'; ?>" style="font-size:0.65rem;"></i>
                            <?php echo ucfirst($rec['status']); ?>
                        </span>
                    </td>
                    <td><span class="br-notes"><?php echo $rec['notes'] ? htmlspecialchars($rec['notes']) : '—'; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">
                    <div class="br-empty"><i class="fas fa-box-open"></i><p>No borrow records found.</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php elseif ($active_tab === 'item'): ?>
    <!-- ══ ITEM REQUESTS ══ -->
    <?php
    $stat_item_pending     = count(array_filter($all_mine_item, fn($r) => $r['status'] === 'pending'));
    $stat_item_approved    = count(array_filter($all_mine_item, fn($r) => $r['status'] === 'approved'));
    $stat_item_disapproved = count(array_filter($all_mine_item, fn($r) => $r['status'] === 'disapproved'));
    ?>
    <div class="br-stats">
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#b45309;"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_item_pending; ?></div><div class="br-stat-lbl">Pending</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#15803d;"><i class="fas fa-check-circle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_item_approved; ?></div><div class="br-stat-lbl">Approved</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#b91c1c;"><i class="fas fa-times-circle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_item_disapproved; ?></div><div class="br-stat-lbl">Disapproved</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#4b5563;"><i class="fas fa-list"></i></div>
            <div><div class="br-stat-val"><?php echo count($all_mine_item); ?></div><div class="br-stat-lbl">Total</div></div>
        </div>
    </div>

    <div class="br-filter">
        <span class="br-filter-label"><i class="fas fa-filter me-1"></i>Status</span>
        <form method="GET">
            <input type="hidden" name="tab" value="item">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="pending"     <?php echo $status_filter === 'pending'     ? 'selected' : ''; ?>>Pending</option>
                <option value="approved"    <?php echo $status_filter === 'approved'    ? 'selected' : ''; ?>>Approved</option>
                <option value="disapproved" <?php echo $status_filter === 'disapproved' ? 'selected' : ''; ?>>Disapproved</option>
            </select>
        </form>
        <span style="font-size:0.80rem;color:rgba(0,0,0,0.38);margin-left:auto;"><?php echo count($item_requests); ?> record<?php echo count($item_requests) !== 1 ? 's' : ''; ?></span>
        <button class="br-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="br-table-card">
        <div class="table-responsive">
            <table class="table">
                <thead><tr>
                    <th>Request #</th><th>Item / Description</th><th>Urgency</th>
                    <th>Reason</th><th>Submitted</th><th>Status</th><th>Admin Notes</th>
                </tr></thead>
                <tbody>
                <?php if (count($item_requests) > 0):
                    foreach ($item_requests as $req):
                        $smap  = ['pending'=>'br-badge-pending','approved'=>'br-badge-approved','disapproved'=>'br-badge-disapproved'];
                        $simap = ['pending'=>'fa-hourglass-half','approved'=>'fa-check','disapproved'=>'fa-times'];
                        $urg_color = ['low'=>'#15803d','medium'=>'#b45309','high'=>'#b91c1c','critical'=>'#7c0000'];
                        $urg_bg    = ['low'=>'rgba(34,197,94,0.09)','medium'=>'rgba(245,158,11,0.10)','high'=>'rgba(239,68,68,0.10)','critical'=>'rgba(139,0,0,0.12)'];
                        $urgency   = $req['urgency'] ?? 'medium';
                        $desc      = $req['service_description'] ?? '';
                        $display   = $req['item_name'] ?: ($desc ? mb_substr($desc, 0, 60) . (mb_strlen($desc) > 60 ? '…' : '') : '—');
                ?>
                <tr>
                    <td><span class="br-qr-chip"><?php echo htmlspecialchars($req['request_number']); ?></span></td>
                    <td><span class="br-item-name"><?php echo htmlspecialchars($display); ?></span></td>
                    <td>
                        <span class="br-badge" style="background:<?php echo $urg_bg[$urgency] ?? $urg_bg['medium']; ?>;color:<?php echo $urg_color[$urgency] ?? $urg_color['medium']; ?>;border:1px solid <?php echo $urg_color[$urgency] ?? $urg_color['medium']; ?>33;">
                            <?php echo ucfirst($urgency); ?>
                        </span>
                    </td>
                    <td><span class="br-desc-cell"><?php echo $req['reason_for_request'] ? htmlspecialchars(mb_substr($req['reason_for_request'], 0, 60)) : '—'; ?></span></td>
                    <td><span class="br-date"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></span></td>
                    <td>
                        <span class="br-badge <?php echo $smap[$req['status']] ?? 'br-badge-pending'; ?>">
                            <i class="fas <?php echo $simap[$req['status']] ?? 'fa-clock'; ?>" style="font-size:0.65rem;"></i>
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                    </td>
                    <td><span class="br-notes"><?php echo $req['approval_notes'] ? htmlspecialchars($req['approval_notes']) : '—'; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">
                    <div class="br-empty"><i class="fas fa-shopping-cart"></i><p>No item requests found.</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <!-- ══ SERVICE REQUESTS ══ -->
    <?php
    $stat_svc_pending     = count(array_filter($all_mine_service, fn($r) => $r['status'] === 'pending'));
    $stat_svc_approved    = count(array_filter($all_mine_service, fn($r) => $r['status'] === 'approved'));
    $stat_svc_disapproved = count(array_filter($all_mine_service, fn($r) => $r['status'] === 'disapproved'));
    ?>
    <div class="br-stats">
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#b45309;"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_svc_pending; ?></div><div class="br-stat-lbl">Pending</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#15803d;"><i class="fas fa-check-circle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_svc_approved; ?></div><div class="br-stat-lbl">Approved</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#b91c1c;"><i class="fas fa-times-circle"></i></div>
            <div><div class="br-stat-val"><?php echo $stat_svc_disapproved; ?></div><div class="br-stat-lbl">Disapproved</div></div>
        </div>
        <div class="br-stat-card">
            <div class="br-stat-icon" style="color:#4b5563;"><i class="fas fa-list"></i></div>
            <div><div class="br-stat-val"><?php echo count($all_mine_service); ?></div><div class="br-stat-lbl">Total</div></div>
        </div>
    </div>

    <div class="br-filter">
        <span class="br-filter-label"><i class="fas fa-filter me-1"></i>Status</span>
        <form method="GET">
            <input type="hidden" name="tab" value="service">
            <select class="form-select" name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="pending"     <?php echo $status_filter === 'pending'     ? 'selected' : ''; ?>>Pending</option>
                <option value="approved"    <?php echo $status_filter === 'approved'    ? 'selected' : ''; ?>>Approved</option>
                <option value="disapproved" <?php echo $status_filter === 'disapproved' ? 'selected' : ''; ?>>Disapproved</option>
            </select>
        </form>
        <span style="font-size:0.80rem;color:rgba(0,0,0,0.38);margin-left:auto;"><?php echo count($service_requests); ?> record<?php echo count($service_requests) !== 1 ? 's' : ''; ?></span>
        <button class="br-print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="br-table-card">
        <div class="table-responsive">
            <table class="table">
                <thead><tr>
                    <th>Request #</th><th>Item</th><th>Service Type</th>
                    <th>Description</th><th>Urgency</th><th>Submitted</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php if (count($service_requests) > 0):
                    foreach ($service_requests as $req):
                        $smap  = ['pending'=>'br-badge-pending','approved'=>'br-badge-approved','disapproved'=>'br-badge-disapproved'];
                        $simap = ['pending'=>'fa-hourglass-half','approved'=>'fa-check','disapproved'=>'fa-times'];
                        $urg_color = ['low'=>'#15803d','medium'=>'#b45309','high'=>'#b91c1c','critical'=>'#7c0000'];
                        $urg_bg    = ['low'=>'rgba(34,197,94,0.09)','medium'=>'rgba(245,158,11,0.10)','high'=>'rgba(239,68,68,0.10)','critical'=>'rgba(139,0,0,0.12)'];
                        $urgency  = $req['urgency'] ?? 'medium';
                        $svc_type = $req['service_type'] ?? null;
                        $desc     = $req['service_description'] ?? '';
                ?>
                <tr>
                    <td><span class="br-qr-chip"><?php echo htmlspecialchars($req['request_number']); ?></span></td>
                    <td><span class="br-item-name"><?php echo htmlspecialchars($req['item_name'] ?? '—'); ?></span></td>
                    <td>
                        <?php if ($svc_type): ?>
                        <span class="br-badge" style="background:rgba(245,158,11,0.10);color:#b45309;border:1px solid rgba(245,158,11,0.25);">
                            <i class="fas fa-wrench" style="font-size:0.65rem;"></i> <?php echo ucfirst($svc_type); ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><span class="br-desc-cell"><?php echo $desc ? htmlspecialchars(mb_substr($desc, 0, 60)) . (mb_strlen($desc) > 60 ? '…' : '') : '—'; ?></span></td>
                    <td>
                        <span class="br-badge" style="background:<?php echo $urg_bg[$urgency] ?? $urg_bg['medium']; ?>;color:<?php echo $urg_color[$urgency] ?? $urg_color['medium']; ?>;border:1px solid <?php echo $urg_color[$urgency] ?? $urg_color['medium']; ?>33;">
                            <?php echo ucfirst($urgency); ?>
                        </span>
                    </td>
                    <td><span class="br-date"><?php echo date('M d, Y', strtotime($req['created_at'])); ?></span></td>
                    <td>
                        <span class="br-badge <?php echo $smap[$req['status']] ?? 'br-badge-pending'; ?>">
                            <i class="fas <?php echo $simap[$req['status']] ?? 'fa-clock'; ?>" style="font-size:0.65rem;"></i>
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">
                    <div class="br-empty"><i class="fas fa-tools"></i><p>No service requests found.</p></div>
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>

</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>

