<?php
$page_title = 'Reports';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

// Filters
$report_type = sanitizeInput($_GET['type']   ?? 'inventory');
$campus_id   = isset($_GET['campus_id']) ? (int)$_GET['campus_id'] : 0;
$date_from   = sanitizeInput($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$date_to     = sanitizeInput($_GET['date_to']   ?? date('Y-m-d'));
$status_f    = sanitizeInput($_GET['status']    ?? '');
$page        = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page    = 15;

// Data
$all_inventory = getInventory();
$all_requests  = getRequests();
$all_users     = array_merge(getUsers(), $_SESSION['added_users'] ?? []);
// Apply status overrides
foreach ($all_users as &$u) {
    if (isset($_SESSION['user_status_overrides'][$u['id']])) $u['is_active'] = $_SESSION['user_status_overrides'][$u['id']];
}
unset($u);
$campuses  = getCampuses();
$colleges  = getMainCampusColleges();
$offices   = getMainCampusOffices();
$all_depts = array_merge($colleges, $offices);

// --- Filtered inventory ---
$inv_data = $campus_id ? filterByColumn($all_inventory, 'campus_id', $campus_id) : $all_inventory;
if ($status_f) $inv_data = filterByColumn($inv_data, 'status', $status_f);
$inv_value = array_sum(array_column($inv_data, 'cost'));

// --- Filtered requests ---
$req_data = array_values(array_filter($all_requests, function($r) use ($date_from, $date_to) {
    $d = substr($r['created_at'], 0, 10);
    return $d >= $date_from && $d <= $date_to;
}));
if ($status_f) $req_data = array_values(filterByColumn($req_data, 'status', $status_f));
if ($campus_id) {
    // Filter by requester campus
    $campus_user_ids = array_column(filterByColumn($all_users, 'campus_id', $campus_id), 'id');
    $req_data = array_values(array_filter($req_data, fn($r) => in_array($r['user_id'], $campus_user_ids)));
}
// Apply session overrides
foreach ($req_data as &$r) {
    if (!empty($_SESSION['request_overrides'][$r['id']])) {
        $r = array_merge($r, $_SESSION['request_overrides'][$r['id']]);
    }
}
unset($r);

// --- Filtered users ---
$usr_data = $campus_id ? filterByColumn($all_users, 'campus_id', $campus_id) : $all_users;
if ($status_f === 'active')   $usr_data = array_values(array_filter($usr_data, fn($u) => $u['is_active']));
if ($status_f === 'inactive') $usr_data = array_values(array_filter($usr_data, fn($u) => !$u['is_active']));

// Helpers
function campusName($campuses, $id) {
    foreach ($campuses as $c) { if ($c['id'] == $id) return $c['name']; }
    return '—';
}
function reqUserName($all_users, $uid) {
    foreach ($all_users as $u) { if ($u['id'] == $uid) return $u['full_name']; }
    return 'Unknown';
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php displayMessage(); ?>

<style>
/* ===== REPORTS ===== */
:root { --rp-red:#8B0000; --rp-red2:#b91c1c; }

/* --- Screen styles --- */
.rp-filter-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    padding:16px 20px; margin-bottom:18px;
}
.rp-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(0,0,0,0.36); margin-bottom:5px; }
.rp-btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 18px; border-radius:6px; border:none;
    font-size:0.83rem; font-weight:700; cursor:pointer;
    text-decoration:none; transition:background 0.15s;
}
.rp-btn-primary { background:#8B0000; color:#fff !important; }
.rp-btn-primary:hover { background:#6b0000; }
.rp-btn-outline { background:transparent; color:var(--rp-red) !important; border:1px solid var(--rp-red); }
.rp-btn-outline:hover { background:rgba(139,0,0,0.06); }
.rp-btn-print { background:#1d4ed8; color:#fff !important; }
.rp-btn-print:hover { background:#1e40af; }

.rp-type-tabs { display:flex; gap:8px; margin-bottom:18px; flex-wrap:wrap; }
.rp-type-tab {
    padding:7px 18px; border-radius:6px; font-size:0.82rem; font-weight:700;
    border:1px solid #e5e7eb; background:#fff;
    color:#555; cursor:pointer; text-decoration:none;
    transition:border-color 0.15s, color 0.15s; display:inline-flex; align-items:center; gap:6px;
}
.rp-type-tab:hover { border-color:var(--rp-red); color:var(--rp-red); }
.rp-type-tab.active { border-color:var(--rp-red); background:#fff; color:#111; font-weight:800; }

.rp-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:0; overflow:hidden; margin-bottom:18px;
}
.rp-card-head {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:10px;
    padding:16px 20px; border-bottom:1px solid rgba(0,0,0,0.06);
}
.rp-card-title { font-size:0.93rem; font-weight:800; color:#1a1d23; display:flex; align-items:center; gap:8px; }
.rp-card-icon {
    display:flex; align-items:center; justify-content:center;
    color:#8B0000; font-size:1rem;
}
.rp-record-count { font-size:0.75rem; font-weight:700; color:rgba(0,0,0,0.38); }

.rp-table { width:100%; border-collapse:collapse; }
.rp-table th {
    font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
    color:rgba(0,0,0,0.36); padding:10px 16px; border-bottom:1px solid rgba(0,0,0,0.07);
    background:rgba(0,0,0,0.015); white-space:nowrap;
}
.rp-table td {
    padding:11px 16px; border-bottom:1px solid rgba(0,0,0,0.05);
    font-size:0.84rem; color:#374151; vertical-align:middle;
}
.rp-table tr:last-child td { border-bottom:none; }
.rp-table tr:hover td { background:rgba(0,0,0,0.011); }
.rp-badge {
    display:inline-flex; padding:2px 9px; border-radius:4px;
    font-size:0.71rem; font-weight:700;
}
.rp-badge-available  { background:rgba(34,197,94,0.12);  color:#15803d; }
.rp-badge-owned      { background:rgba(34,197,94,0.12);  color:#15803d; }
.rp-badge-borrowed   { background:rgba(245,158,11,0.12); color:#b45309; }
.rp-badge-requested  { background:rgba(34,197,94,0.12);  color:#22c55e; }
.rp-badge-maintenance{ background:rgba(59,130,246,0.12); color:#1d4ed8; }
.rp-badge-pending    { background:rgba(245,158,11,0.12); color:#b45309; }
.rp-badge-approved   { background:rgba(34,197,94,0.12);  color:#15803d; }
.rp-badge-disapproved{ background:rgba(239,68,68,0.12);  color:#dc2626; }
.rp-badge-active     { background:rgba(34,197,94,0.12);  color:#15803d; }
.rp-badge-inactive   { background:rgba(0,0,0,0.07);       color:rgba(0,0,0,0.45); }
.rp-badge-admin      { background:rgba(139,0,0,0.10);     color:#8B0000; }
.rp-badge-user       { background:rgba(59,130,246,0.12); color:#1d4ed8; }

.rp-summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; padding:16px 20px; border-bottom:1px solid rgba(0,0,0,0.06); }
.rp-summary-item { text-align:center; }
.rp-summary-val { font-size:1.4rem; font-weight:900; color:#1a1d23; }
.rp-summary-lbl { font-size:0.70rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:rgba(0,0,0,0.38); margin-top:2px; }

/* --- Print styles --- */
@media print {
    /* Hide everything but the report */
    .sidebar, .sidebar-toggle-btn, .rp-filter-card, .rp-type-tabs,
    .rp-card-actions, .rp-no-print, .topbar, nav,
    .main-wrapper > .container-fluid > *:not(.rp-printable) { display:none !important; }

    .main-wrapper { padding:0 !important; margin:0 !important; }
    .container-fluid { padding:0 !important; }

    .rp-printable { display:block !important; }

    /* Letterhead */
    .rp-print-header {
        display:flex !important;
        align-items:center; gap:18px;
        padding-bottom:14px; margin-bottom:18px;
        border-bottom:3px solid #8B0000;
    }
    .rp-print-header-logo {
        width:54px; height:54px;
    }
    .rp-print-header-text h2 {
        font-size:14pt; font-weight:900; color:#8B0000; margin:0 0 2px;
    }
    .rp-print-header-text p {
        font-size:8pt; color:#555; margin:0;
    }
    .rp-print-meta {
        display:flex !important;
        font-size:8pt; color:#666; gap:24px; margin-bottom:14px;
    }
    .rp-print-meta span strong { color:#111; }

    .rp-card { box-shadow:none !important; border:1px solid #ddd !important; border-radius:0 !important; }
    .rp-card-head { border-bottom:1px solid #ddd !important; background:#f9f9f9 !important; }
    .rp-card-title { font-size:10pt !important; }
    .rp-card-icon { display:none !important; }

    .rp-table th { background:#f3f3f3 !important; color:#555 !important; font-size:7.5pt !important; padding:6px 10px !important; }
    .rp-table td { font-size:8pt !important; color:#222 !important; padding:6px 10px !important; }
    .rp-badge { font-size:7pt !important; padding:1px 6px !important; border:1px solid currentColor !important; background:transparent !important; }

    .rp-summary-val { font-size:16pt !important; }
    .rp-summary-lbl { font-size:7pt !important; }

    .rp-print-footer {
        display:block !important;
        margin-top:24px; padding-top:10px; border-top:1px solid #ddd;
        font-size:7.5pt; color:#888; text-align:center;
    }

    body { font-family: Arial, sans-serif !important; }
    @page { margin: 18mm 15mm; }
}

/* Hide print-only elements on screen */
.rp-print-header, .rp-print-meta, .rp-print-footer { display:none; }
</style>

<div class="container-fluid mt-4 pb-5">

    <!-- Printable wrapper -->
    <div class="rp-printable">

        <!-- Letterhead (print only) -->
        <div class="rp-print-header">
            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" class="rp-print-header-logo" alt="PSU Logo">
            <div class="rp-print-header-text">
                <h2>Pampanga State University</h2>
                <p>ManageMo — Inventory & Asset Management System</p>
                <p>Report generated by: <?php echo htmlspecialchars($current_user['full_name']); ?></p>
            </div>
        </div>
        <div class="rp-print-meta">
            <span><strong>Report Type:</strong>
                <?php echo ['inventory'=>'Inventory Report','requests'=>'Requests Report','users'=>'User Accounts Report'][$report_type] ?? 'Report'; ?>
            </span>
            <span><strong>Campus:</strong> <?php echo $campus_id ? htmlspecialchars(campusName($campuses, $campus_id)) : 'All Campuses'; ?></span>
            <?php if ($report_type !== 'inventory'): ?>
            <span><strong>Period:</strong> <?php echo $date_from; ?> to <?php echo $date_to; ?></span>
            <?php endif; ?>
            <span><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></span>
        </div>

        <!-- === SCREEN: type tabs + filters === -->
        <div class="rp-no-print">
            <!-- Type tabs -->
            <div class="rp-type-tabs">
                <a href="?type=inventory&campus_id=<?php echo $campus_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='inventory'?'active':''; ?>">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <a href="?type=requests&campus_id=<?php echo $campus_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='requests'?'active':''; ?>">
                    <i class="fas fa-clipboard-list"></i> Requests
                </a>
                <a href="?type=users&campus_id=<?php echo $campus_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='users'?'active':''; ?>">
                    <i class="fas fa-users"></i> Users
                </a>
            </div>

            <!-- Filters -->
            <div class="rp-filter-card">
                <form method="GET" class="d-flex align-items-end flex-wrap gap-3">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    <div>
                        <div class="rp-filter-label">Campus</div>
                        <select class="form-select" name="campus_id" style="min-width:160px;">
                            <option value="0">All Campuses</option>
                            <?php foreach ($campuses as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $campus_id==$c['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($report_type !== 'inventory'): ?>
                    <div>
                        <div class="rp-filter-label">Date From</div>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" style="min-width:140px;">
                    </div>
                    <div>
                        <div class="rp-filter-label">Date To</div>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" style="min-width:140px;">
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
                    <input type="hidden" name="date_to"   value="<?php echo $date_to; ?>">
                    <?php endif; ?>
                    <div>
                        <div class="rp-filter-label">Status</div>
                        <select class="form-select" name="status" style="min-width:140px;">
                            <option value="">All</option>
                            <?php if ($report_type === 'inventory'): ?>
                            <option value="available"   <?php echo $status_f==='available'  ?'selected':''; ?>>Owned</option>
                            <option value="available"   <?php echo $status_f==='available'  ?'selected':''; ?>>Available</option>
                            <option value="borrowed"    <?php echo $status_f==='borrowed'   ?'selected':''; ?>>Borrowed</option>
                            <option value="maintenance" <?php echo $status_f==='maintenance'?'selected':''; ?>>Maintenance</option>
                            <option value="requested"   <?php echo $status_f==='requested'  ?'selected':''; ?>>Requested</option>
                            <?php elseif ($report_type === 'requests'): ?>
                            <option value="pending"     <?php echo $status_f==='pending'    ?'selected':''; ?>>Pending</option>
                            <option value="approved"    <?php echo $status_f==='approved'   ?'selected':''; ?>>Approved</option>
                            <option value="disapproved" <?php echo $status_f==='disapproved'?'selected':''; ?>>Disapproved</option>
                            <?php else: ?>
                            <option value="active"   <?php echo $status_f==='active'  ?'selected':''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_f==='inactive'?'selected':''; ?>>Inactive</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="rp-btn rp-btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <button type="button" class="rp-btn rp-btn-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </form>
            </div>
        </div><!-- /.rp-no-print -->


        <?php /* ======== INVENTORY REPORT ======== */ if ($report_type === 'inventory'): ?>
        <?php
        // If requested status filter, show only items with requests
        $display_inv = $inv_data;
        if ($status_f === 'requested') {
            $requested_inv_ids = array_unique(array_column($all_requests, 'inventory_id'));
            $display_inv = array_values(array_filter($inv_data, fn($i) => in_array($i['id'], $requested_inv_ids)));
        }
        
        $inv_avail = count(filterByColumn($inv_data,'status','available'));
        $inv_bor   = count(filterByColumn($inv_data,'status','borrowed'));
        $inv_maint = count(filterByColumn($inv_data,'status','maintenance'));
        
        // Count items with requests
        $requested_inv_ids = array_unique(array_column($all_requests, 'inventory_id'));
        $inv_requested = count(array_filter($inv_data, fn($i) => in_array($i['id'], $requested_inv_ids)));
        
        // Pagination
        $total_inv = count($display_inv);
        $total_pages = ceil($total_inv / $per_page);
        $page = min($page, $total_pages) ?: 1;
        $offset = ($page - 1) * $per_page;
        $paginated_inv = array_slice($display_inv, $offset, $per_page);
        ?>
        <div class="rp-card">
            <div class="rp-card-head">
                <div class="rp-card-title">
                    <div class="rp-card-icon"><i class="fas fa-warehouse"></i></div>
                    Inventory Report
                    <span class="rp-record-count"><?php echo $total_inv; ?> item(s)</span>
                </div>
            </div>
            <!-- Summary row -->
            <div class="rp-summary-grid">
                <div class="rp-summary-item">
                    <div class="rp-summary-val"><?php echo count($inv_data); ?></div>
                    <div class="rp-summary-lbl">Total Items</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#15803d;"><?php echo $inv_avail; ?></div>
                    <div class="rp-summary-lbl">Owned</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#15803d;"><?php echo $inv_avail; ?></div>
                    <div class="rp-summary-lbl">Available</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#b45309;"><?php echo $inv_bor; ?></div>
                    <div class="rp-summary-lbl">Borrowed</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#22c55e;"><?php echo $inv_requested; ?></div>
                    <div class="rp-summary-lbl">Requested</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#1d4ed8;"><?php echo $inv_maint; ?></div>
                    <div class="rp-summary-lbl">Maintenance</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#8B0000;">&#8369;<?php echo number_format($inv_value, 0); ?></div>
                    <div class="rp-summary-lbl">Total Value</div>
                </div>
            </div>
            <!-- Table -->
            <div style="overflow-x:auto;">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>QR Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Campus</th>
                        <th>Location</th>
                        <th>Qty</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Value (₱)</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($paginated_inv as $i => $item): ?>
                <tr>
                    <td style="color:rgba(0,0,0,0.35);font-size:0.75rem;"><?php echo $offset + $i + 1; ?></td>
                    <td><span style="font-family:monospace;font-size:0.76rem;color:#8B0000;background:rgba(139,0,0,0.06);border-radius:4px;padding:1px 5px;"><?php echo htmlspecialchars($item['qr_code_id']); ?></span></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td><?php echo htmlspecialchars(campusName($campuses, $item['campus_id'])); ?></td>
                    <td style="font-size:0.80rem;color:rgba(0,0,0,0.55);"><?php echo htmlspecialchars($item['location']); ?></td>
                    <td style="text-align:center;font-weight:700;"><?php echo (int)$item['quantity']; ?></td>
                    <td><?php echo ucfirst(htmlspecialchars($item['condition'])); ?></td>
                    <td><span class="rp-badge rp-badge-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                    <td style="text-align:right;"><?php echo number_format($item['cost'], 2); ?></td>
                    <td style="font-size:0.79rem;color:rgba(0,0,0,0.50);"><?php echo $item['purchase_date'] ? date('M d, Y', strtotime($item['purchase_date'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($paginated_inv)): ?>
                <tr><td colspan="11" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No inventory items match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div style="display:flex; align-items:center; justify-content:space-between; margin-top:24px; padding-top:16px; border-top:1px solid rgba(0,0,0,0.08);">
                <div style="font-size:0.85rem; color:rgba(0,0,0,0.55);">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_inv); ?> of <?php echo $total_inv; ?> items
                </div>
                <div style="display:flex; gap:8px;">
                    <?php for ($p = 1; $p <= min($total_pages, 5); $p++): ?>
                    <a href="?type=<?php echo $report_type; ?>&campus_id=<?php echo $campus_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>&page=<?php echo $p; ?>"
                       style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; font-size:0.85rem; font-weight:700; text-decoration:none; 
                              background:<?php echo $p === $page ? '#8B0000' : '#f7f7f7'; ?>;
                              color:<?php echo $p === $page ? '#fff' : '#555'; ?>;
                              border:<?php echo $p === $page ? 'none' : '1px solid #e5e7eb'; ?>;">
                        <?php echo $p; ?>
                    </a>
                    <?php endfor; ?>
                    <?php if ($total_pages > 5): ?>
                    <span style="padding:0 8px; color:rgba(0,0,0,0.35);">...</span>
                    <a href="?type=<?php echo $report_type; ?>&campus_id=<?php echo $campus_id; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>&page=<?php echo $total_pages; ?>"
                       style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:6px; font-size:0.85rem; font-weight:700; text-decoration:none; background:#f7f7f7; color:#555; border:1px solid #e5e7eb;">
                        <?php echo $total_pages; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>


        <?php /* ======== REQUESTS REPORT ======== */ elseif ($report_type === 'requests'): ?>
        <?php
        $rq_pend  = count(filterByColumn($req_data,'status','pending'));
        $rq_appr  = count(filterByColumn($req_data,'status','approved'));
        $rq_disap = count(filterByColumn($req_data,'status','disapproved'));
        ?>
        <div class="rp-card">
            <div class="rp-card-head">
                <div class="rp-card-title">
                    <div class="rp-card-icon"><i class="fas fa-clipboard-list"></i></div>
                    Requests Report
                    <span class="rp-record-count"><?php echo count($req_data); ?> request(s) &nbsp;·&nbsp; <?php echo $date_from; ?> – <?php echo $date_to; ?></span>
                </div>
            </div>
            <div class="rp-summary-grid">
                <div class="rp-summary-item">
                    <div class="rp-summary-val"><?php echo count($req_data); ?></div>
                    <div class="rp-summary-lbl">Total</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#b45309;"><?php echo $rq_pend; ?></div>
                    <div class="rp-summary-lbl">Pending</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#15803d;"><?php echo $rq_appr; ?></div>
                    <div class="rp-summary-lbl">Approved</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#dc2626;"><?php echo $rq_disap; ?></div>
                    <div class="rp-summary-lbl">Disapproved</div>
                </div>
            </div>
            <div style="overflow-x:auto;">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Request No.</th>
                        <th>Requester</th>
                        <th>Type</th>
                        <th>Item / Description</th>
                        <th>Qty</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Return Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $type_labels = ['borrow'=>'Borrow','item'=>'Item Req.','service'=>'Service'];
                $urgency_colors = ['low'=>'rp-badge-available','medium'=>'rp-badge-borrowed','high'=>'rp-badge-damaged','critical'=>'rp-badge-damaged'];
                foreach ($req_data as $i => $r):
                    $uname = reqUserName($all_users, $r['user_id']);
                    $item_label = '';
                    if ($r['request_type'] === 'borrow')   $item_label = $r['item_name'] ?? '—';
                    elseif ($r['request_type'] === 'item')    $item_label = $r['item_description'] ?? $r['item_name'] ?? '—';
                    elseif ($r['request_type'] === 'service') $item_label = mb_strimwidth($r['service_description'] ?? '—', 0, 60, '…');
                ?>
                <tr>
                    <td style="color:rgba(0,0,0,0.35);font-size:0.75rem;"><?php echo $i+1; ?></td>
                    <td style="font-family:monospace;font-size:0.78rem;color:#8B0000;"><?php echo htmlspecialchars($r['request_number']); ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($uname); ?></td>
                    <td><?php echo htmlspecialchars($type_labels[$r['request_type']] ?? $r['request_type']); ?></td>
                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($item_label); ?></td>
                    <td style="text-align:center;"><?php echo (int)($r['quantity_requested'] ?? 1); ?></td>
                    <td><span class="rp-badge <?php echo $urgency_colors[$r['urgency']] ?? ''; ?>"><?php echo ucfirst($r['urgency']); ?></span></td>
                    <td><span class="rp-badge rp-badge-<?php echo $r['status']; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                    <td style="font-size:0.78rem;color:rgba(0,0,0,0.50);"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                    <td style="font-size:0.78rem;color:rgba(0,0,0,0.50);"><?php echo $r['expected_return_date'] ? date('M d, Y', strtotime($r['expected_return_date'])) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($req_data)): ?>
                <tr><td colspan="10" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No requests match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>


        <?php /* ======== USERS REPORT ======== */ else: ?>
        <?php
        $usr_active = count(array_filter($usr_data, fn($u) => $u['is_active']));
        $usr_admin  = count(array_filter($usr_data, fn($u) => $u['role'] === 'admin'));
        ?>
        <div class="rp-card">
            <div class="rp-card-head">
                <div class="rp-card-title">
                    <div class="rp-card-icon"><i class="fas fa-users"></i></div>
                    User Accounts Report
                    <span class="rp-record-count"><?php echo count($usr_data); ?> account(s)</span>
                </div>
            </div>
            <div class="rp-summary-grid">
                <div class="rp-summary-item">
                    <div class="rp-summary-val"><?php echo count($usr_data); ?></div>
                    <div class="rp-summary-lbl">Total Accounts</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#15803d;"><?php echo $usr_active; ?></div>
                    <div class="rp-summary-lbl">Active</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#8B0000;"><?php echo $usr_admin; ?></div>
                    <div class="rp-summary-lbl">Admins</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#1d4ed8;"><?php echo count($usr_data) - $usr_admin; ?></div>
                    <div class="rp-summary-lbl">Faculty / Staff</div>
                </div>
            </div>
            <div style="overflow-x:auto;">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Campus</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usr_data as $i => $u):
                    $cname = campusName($campuses, $u['campus_id']);
                    $dname = (!empty($u['college_id']) && isset($all_depts[$u['college_id']])) ? $u['college_id'] : '—';
                ?>
                <tr>
                    <td style="color:rgba(0,0,0,0.35);font-size:0.75rem;"><?php echo $i+1; ?></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="font-size:0.82rem;color:rgba(0,0,0,0.55);"><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                    <td><span class="rp-badge rp-badge-<?php echo $u['role']; ?>"><?php echo $u['role'] === 'admin' ? 'Administrator' : 'Faculty/Staff'; ?></span></td>
                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($cname); ?></td>
                    <td><?php echo htmlspecialchars($dname); ?></td>
                    <td><span class="rp-badge <?php echo $u['is_active'] ? 'rp-badge-active' : 'rp-badge-inactive'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td style="font-size:0.79rem;color:rgba(0,0,0,0.50);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($usr_data)): ?>
                <tr><td colspan="9" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No users match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Print footer -->
        <div class="rp-print-footer">
            ManageMo &mdash; Pampanga State University &mdash; Report printed on <?php echo date('F d, Y h:i A'); ?>
            &nbsp;|&nbsp; Generated by <?php echo htmlspecialchars($current_user['full_name']); ?>
        </div>

    </div><!-- /.rp-printable -->
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
