<?php
$page_title = 'Reports';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

// Filters
$report_type = sanitizeInput($_GET['type']   ?? 'inventory');
// One combined "department" filter covers Campuses, Colleges, and Offices
// together: value is "c:<campus id>" or "d:<college/office abbr>".
$dept_id     = sanitizeInput($_GET['dept_id'] ?? '');
$campus_id   = str_starts_with($dept_id, 'c:') ? substr($dept_id, 2) : '';
$college_id  = str_starts_with($dept_id, 'd:') ? substr($dept_id, 2) : '';
$date_from   = sanitizeInput($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$date_to     = sanitizeInput($_GET['date_to']   ?? date('Y-m-d'));
$status_f    = sanitizeInput($_GET['status']    ?? '');
$page        = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page    = 15;

// Data
$all_inventory = getInventory();
$all_requests  = getRequests();
$all_users = getUsers();
$all_campuses = getAllCampuses();
$colleges  = getMainCampusColleges();
$offices   = getMainCampusOffices();
$all_depts = array_merge($colleges, $offices);

// --- Filtered inventory ---
// A campus "owns" inventory the same way a college/office does — via its
// abbreviation stored in inventory.college_id (the legacy numeric campus_id
// column is always coalesced to 1, so filtering on it matched everything or
// nothing). Resolve the picked campus to its abbreviation first.
$campus_abbr = '';
if ($campus_id) {
    $campus_row  = findById(getDepartmentCampuses(), (int)$campus_id);
    $campus_abbr = $campus_row['abbreviation'] ?? '';
}
$owner_code = $college_id ?: $campus_abbr;
$all_dept_names = getAllDepartmentNames(); // colleges, offices and campuses

// Condemned/disposed items are out of service (they have their own report on
// the Condemnation page), so they don't count toward the inventory totals.
$inv_scope = array_values(array_filter($all_inventory, fn($i) =>
    !in_array($i['status'], ['condemned', 'disposed'])
    && (!$owner_code || ($i['college_id'] ?? '') === $owner_code)));

// User-owned items live in their own table (user_owned_items). Map them onto
// the inventory row shape so they list and count alongside inventory.
$owned_scope = [];
foreach (getUserOwnedItems() as $o) {
    if ($owner_code && ($o['college_id'] ?? '') !== $owner_code) continue;
    $owner = findById($all_users, (int)$o['user_id']);
    $owned_scope[] = [
        'qr_code_id'    => $o['qr_code_id'] ?? '',
        'item_name'     => $o['item_name'],
        'category'      => $o['category'] ?? '',
        'college_id'    => $o['college_id'] ?? '',
        'location'      => $owner ? 'Owner: ' . $owner['full_name'] : '',
        'quantity'      => (int)($o['quantity'] ?? 1),
        'condition'     => $o['condition'] ?? '',
        'status'        => 'owned',
        'cost'          => null,
        'purchase_date' => $o['purchase_date'] ?? null,
    ];
}

if ($status_f === 'owned')  $inv_data = $owned_scope;
elseif ($status_f)          $inv_data = filterByColumn($inv_scope, 'status', $status_f);
else                        $inv_data = array_merge($inv_scope, $owned_scope);
$inv_value = array_sum(array_map(fn($i) => (float)($i['cost'] ?? 0), $inv_data));

// --- Filtered requests ---
$req_data = array_values(array_filter($all_requests, function($r) use ($date_from, $date_to) {
    $d = substr($r['created_at'], 0, 10);
    return $d >= $date_from && $d <= $date_to;
}));
if ($status_f) $req_data = array_values(filterByColumn($req_data, 'status', $status_f));
if ($college_id) {
    // Filter by requester's college/office
    $dept_user_ids = array_column(filterByColumn($all_users, 'college_id', $college_id), 'id');
    $req_data = array_values(array_filter($req_data, fn($r) => in_array($r['user_id'], $dept_user_ids)));
}
if ($campus_id) {
    // Filter by requester's campus
    $campus_user_ids = array_column(filterByColumn($all_users, 'campus_id', (int)$campus_id), 'id');
    $req_data = array_values(array_filter($req_data, fn($r) => in_array($r['user_id'], $campus_user_ids)));
}
// --- Filtered users ---
$usr_data = $college_id ? filterByColumn($all_users, 'college_id', $college_id) : $all_users;
if ($campus_id) $usr_data = filterByColumn($usr_data, 'campus_id', (int)$campus_id);
if ($status_f === 'active')   $usr_data = array_values(array_filter($usr_data, fn($u) => $u['is_active']));
if ($status_f === 'inactive') $usr_data = array_values(array_filter($usr_data, fn($u) => !$u['is_active']));

// Helpers
function deptName($all_depts, $code) {
    if (!$code) return '—';
    return $all_depts[$code] ?? $code;
}
function reqUserName($all_users, $uid) {
    foreach ($all_users as $u) { if ($u['id'] == $uid) return $u['full_name']; }
    return 'Unknown';
}
// Shared row markup for the Inventory Report table — used once for the
// on-screen paginated view and once (unpaginated, full dataset) for print,
// so "Print Report" doesn't just print whichever page happens to be on screen.
function rpInventoryRow($item, $rownum, $all_depts) {
    ?>
    <tr>
        <td style="color:rgba(0,0,0,0.35);font-size:0.75rem;"><?php echo $rownum; ?></td>
        <td><span style="font-family:monospace;font-size:0.76rem;color:#8B0000;background:rgba(139,0,0,0.06);border-radius:4px;padding:1px 5px;"><?php echo htmlspecialchars($item['qr_code_id']); ?></span></td>
        <td style="font-weight:700;"><?php echo htmlspecialchars($item['item_name']); ?></td>
        <td><?php echo htmlspecialchars($item['category']); ?></td>
        <td><?php echo htmlspecialchars(deptName(getAllDepartmentNames(), $item['college_id'] ?? '')); ?></td>
        <td style="font-size:0.80rem;color:rgba(0,0,0,0.55);"><?php echo htmlspecialchars($item['location'] ?? ''); ?></td>
        <td style="text-align:center;font-weight:700;"><?php echo (int)$item['quantity']; ?></td>
        <td><?php echo ucfirst(htmlspecialchars($item['condition'] ?? '')); ?></td>
        <td><span class="rp-badge rp-badge-<?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
        <td style="text-align:right;"><?php echo $item['cost'] !== null ? number_format((float)$item['cost'], 2) : '—'; ?></td>
        <td style="font-size:0.79rem;color:rgba(0,0,0,0.50);"><?php echo $item['purchase_date'] ? date('M d, Y', strtotime($item['purchase_date'])) : '—'; ?></td>
    </tr>
    <?php
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
.rp-badge-owned      { background:rgba(124,58,237,0.12); color:#7c3aed; }
.rp-badge-borrowed   { background:rgba(245,158,11,0.12); color:#b45309; }
.rp-badge-requested  { background:rgba(34,197,94,0.12);  color:#22c55e; }
.rp-badge-maintenance{ background:rgba(59,130,246,0.12); color:#1d4ed8; }
.rp-badge-damaged    { background:rgba(239,68,68,0.12);  color:#dc2626; }
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

/* --- Print styles: formal document layout (Republic-letterhead / grid table / signatories) --- */
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
        flex-direction:column; align-items:center; text-align:center;
        gap:2px;
        padding-bottom:8px; margin-bottom:4px;
    }
    .rp-print-header-logo { width:60px; height:60px; margin-bottom:4px; }
    .rp-print-header-republic { font-size:10pt; font-style:italic; margin:0; }
    .rp-print-header-text h2 {
        font-size:14pt; font-weight:700; letter-spacing:0.5px;
        text-transform:uppercase; margin:0; color:#000;
    }
    .rp-print-header-text p { font-size:8.5pt; color:#000; margin:1px 0 0; }
    .rp-print-header-rule {
        display:block !important;
        border:none; border-top:2.5pt solid #000; border-bottom:0.75pt solid #000;
        height:4pt; margin:6px 0 14px;
    }
    .rp-print-title {
        display:block !important;
        text-align:center; font-size:12pt; font-weight:700;
        text-decoration:underline; text-underline-offset:3px;
        text-transform:uppercase; letter-spacing:0.5px; margin-bottom:10px;
    }
    .rp-print-meta {
        display:block !important;
        font-size:9pt; color:#000; margin-bottom:16px;
    }
    .rp-print-meta div { margin-bottom:2px; }
    .rp-print-meta strong { display:inline-block; min-width:150px; }

    .rp-card { box-shadow:none !important; border:none !important; border-radius:0 !important; }
    .rp-card-head { display:none !important; }

    /* Grid-ruled table, like an official tabulated report */
    .rp-table { border-collapse:collapse !important; width:100% !important; }
    .rp-table th, .rp-table td { border:0.75pt solid #000 !important; }
    .rp-table th {
        background:#e5e5e5 !important; color:#000 !important;
        font-size:7.5pt !important; padding:5px 8px !important;
        text-transform:uppercase; text-align:center !important;
    }
    .rp-table td { font-size:8pt !important; color:#000 !important; padding:5px 8px !important; }
    .rp-badge {
        font-size:7pt !important; padding:1px 6px !important;
        border:0.75pt solid #000 !important; border-radius:0 !important;
        background:transparent !important; color:#000 !important; font-weight:600 !important;
    }

    .rp-summary-grid { border:0.75pt solid #000 !important; }
    .rp-summary-val { font-size:14pt !important; color:#000 !important; }
    .rp-summary-lbl { font-size:7pt !important; color:#000 !important; }

    /* Signature block — Prepared by / Certified correct / Noted by */
    .rp-print-signatures {
        display:flex !important;
        justify-content:space-between; gap:20px;
        margin-top:56px; page-break-inside:avoid;
    }
    .rp-print-sig { flex:1; text-align:center; font-size:8.5pt; }
    .rp-print-sig-line {
        border-top:0.75pt solid #000; margin-bottom:4px; padding-top:4px;
        font-weight:700; text-transform:uppercase;
    }
    .rp-print-sig-role { color:#333; }

    .rp-print-footer {
        display:block !important;
        margin-top:20px; padding-top:8px; border-top:0.5pt solid #999;
        font-size:7pt; color:#555; text-align:center;
    }

    /* The on-screen table only shows the current pagination page — printing
       it as-is would silently cut the report down to whatever page happened
       to be open. Swap in the print-only table, which always holds the full
       filtered dataset, and hide the pagination controls entirely. */
    .rp-screen-only { display:none !important; }
    .rp-print-only  { display:block !important; }

    /* These wrappers give the table a horizontal scrollbar on screen when it's
       wider than its container — but a scrollbar means nothing on paper, so
       "overflow-x:auto" just silently clips (crops) every column past the
       fold instead. Let the table spill onto the page in full during print. */
    .rp-table-scroll { overflow-x:visible !important; overflow:visible !important; }
    .rp-table { table-layout:auto !important; width:100% !important; }

    body, .rp-table, .rp-print-header, .rp-print-meta, .rp-print-title, .rp-print-signatures {
        font-family: "Times New Roman", Times, serif !important;
    }
    @page { size: landscape; margin: 15mm; }
}

/* Hide print-only elements on screen */
.rp-print-header, .rp-print-header-rule, .rp-print-title, .rp-print-meta,
.rp-print-footer, .rp-print-only, .rp-print-signatures { display:none; }
</style>

<div class="container-fluid mt-4 pb-5">

    <!-- Printable wrapper -->
    <div class="rp-printable">

        <!-- Letterhead (print only) -->
        <div class="rp-print-header">
            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" class="rp-print-header-logo" alt="PSU Logo">
            <p class="rp-print-header-republic">Republic of the Philippines</p>
            <div class="rp-print-header-text">
                <h2>Pampanga State University</h2>
                <p>ManageMo — Inventory &amp; Asset Management System</p>
            </div>
        </div>
        <hr class="rp-print-header-rule">

        <div class="rp-print-title">
            <?php echo ['inventory'=>'Inventory Report','requests'=>'Requests Report','users'=>'User Accounts Report'][$report_type] ?? 'Report'; ?>
        </div>

        <div class="rp-print-meta">
            <div><strong>Campus / College / Office:</strong> <?php
                if ($campus_id) echo htmlspecialchars(deptName(array_column($all_campuses, 'name', 'id'), (int)$campus_id));
                elseif ($college_id) echo htmlspecialchars(deptName($all_depts, $college_id));
                else echo 'All';
            ?></div>
            <?php if ($report_type !== 'inventory'): ?>
            <div><strong>Period Covered:</strong> <?php echo date('F d, Y', strtotime($date_from)); ?> to <?php echo date('F d, Y', strtotime($date_to)); ?></div>
            <?php endif; ?>
            <div><strong>Date Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
            <div><strong>Prepared by:</strong> <?php echo htmlspecialchars($current_user['full_name']); ?></div>
        </div>

        <!-- === SCREEN: type tabs + filters === -->
        <div class="rp-no-print">
            <!-- Type tabs -->
            <div class="rp-type-tabs">
                <a href="?type=inventory&dept_id=<?php echo urlencode($dept_id); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='inventory'?'active':''; ?>">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
                <a href="?type=requests&dept_id=<?php echo urlencode($dept_id); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='requests'?'active':''; ?>">
                    <i class="fas fa-clipboard-list"></i> Requests
                </a>
                <a href="?type=users&dept_id=<?php echo urlencode($dept_id); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_f; ?>"
                   class="rp-type-tab <?php echo $report_type==='users'?'active':''; ?>">
                    <i class="fas fa-users"></i> Users
                </a>
            </div>

            <!-- Filters -->
            <div class="rp-filter-card">
                <form method="GET" class="d-flex align-items-end flex-wrap gap-3">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                    <div>
                        <div class="rp-filter-label">Campus / College / Office</div>
                        <select class="form-select" name="dept_id" style="min-width:180px;">
                            <option value="">All</option>
                            <optgroup label="Campuses">
                            <?php foreach ($all_campuses as $c): ?>
                            <option value="c:<?php echo $c['id']; ?>" <?php echo $dept_id==='c:'.$c['id']?'selected':''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Colleges/Offices">
                            <?php foreach ($all_depts as $code => $name): ?>
                            <option value="d:<?php echo htmlspecialchars($code); ?>" <?php echo $dept_id==='d:'.$code?'selected':''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                            <?php endforeach; ?>
                            </optgroup>
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
                            <option value="owned"       <?php echo $status_f==='owned'      ?'selected':''; ?>>User-Owned</option>
                            <option value="available"   <?php echo $status_f==='available'  ?'selected':''; ?>>Available</option>
                            <option value="borrowed"    <?php echo $status_f==='borrowed'   ?'selected':''; ?>>Borrowed</option>
                            <option value="maintenance" <?php echo $status_f==='maintenance'?'selected':''; ?>>Maintenance</option>
                            <option value="requested"   <?php echo $status_f==='requested'  ?'selected':''; ?>>Requested</option>
                            <?php elseif($report_type === 'requests'): ?>
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
                    <button type="button" class="rp-btn" style="background:#15803d;color:#fff !important;" onclick="downloadReportPDF()">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                </form>
            </div>
        </div><!-- /.rp-no-print -->


        <?php /* ======== INVENTORY REPORT ======== */ if ($report_type === 'inventory'): ?>
        <?php
        $display_inv = $inv_data;

        // Summary tiles always describe the whole scope (department filter only),
        // using each item's current status.
        $inv_owned     = count($owned_scope);
        $inv_total_all = count($inv_scope) + $inv_owned;
        $inv_avail     = count(filterByColumn($inv_scope,'status','available'));
        $inv_bor       = count(filterByColumn($inv_scope,'status','borrowed'));
        $inv_requested = count(filterByColumn($inv_scope,'status','requested'));
        $inv_maint     = count(filterByColumn($inv_scope,'status','maintenance'));

        // Pagination
        $total_inv = count($display_inv);
        $total_pages = (int)ceil($total_inv / $per_page);
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
                    <div class="rp-summary-val"><?php echo $inv_total_all; ?></div>
                    <div class="rp-summary-lbl">Total Items</div>
                </div>
                <div class="rp-summary-item">
                    <div class="rp-summary-val" style="color:#7c3aed;"><?php echo $inv_owned; ?></div>
                    <div class="rp-summary-lbl">User-Owned</div>
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
            <!-- Table (on-screen: current page only) -->
            <div style="overflow-x:auto;" class="rp-screen-only rp-table-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>QR Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>College/Office</th>
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
                    <?php rpInventoryRow($item, $offset + $i + 1, $all_depts); ?>
                <?php endforeach; ?>
                <?php if (empty($paginated_inv)): ?>
                <tr><td colspan="11" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No inventory items match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <!-- Table (print: full filtered dataset, not just the on-screen page) -->
            <div class="rp-print-only rp-table-scroll" style="overflow-x:auto;">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>QR Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>College/Office</th>
                        <th>Location</th>
                        <th>Qty</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Value (₱)</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($display_inv as $i => $item): ?>
                    <?php rpInventoryRow($item, $i + 1, $all_depts); ?>
                <?php endforeach; ?>
                <?php if (empty($display_inv)): ?>
                <tr><td colspan="11" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No inventory items match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="rp-no-print" style="display:flex; align-items:center; justify-content:space-between; margin-top:24px; padding-top:16px; border-top:1px solid rgba(0,0,0,0.08);">
                <div style="font-size:0.85rem; color:rgba(0,0,0,0.55);">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_inv); ?> of <?php echo $total_inv; ?> items
                </div>
                <div style="display:flex; gap:8px;">
                    <?php
                    // Sliding window around the current page (plus first/last), so
                    // every page is reachable — not just 1–5 and the last one.
                    $__rp_qs = '?type=' . urlencode($report_type) . '&dept_id=' . urlencode($dept_id) . '&date_from=' . urlencode($date_from) . '&date_to=' . urlencode($date_to) . '&status=' . urlencode($status_f) . '&page=';
                    $__rp_pages = array_unique(array_merge([1], range(max(1, $page - 2), min($total_pages, $page + 2)), [$total_pages]));
                    sort($__rp_pages);
                    $__rp_prev = 0;
                    foreach ($__rp_pages as $p):
                        if ($p - $__rp_prev > 1): ?>
                    <span style="padding:0 4px; color:rgba(0,0,0,0.35); align-self:center;">...</span>
                    <?php endif; $__rp_prev = $p; ?>
                    <a href="<?php echo $__rp_qs . $p; ?>"
                       style="display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 6px; border-radius:6px; font-size:0.85rem; font-weight:700; text-decoration:none;
                              background:<?php echo $p === $page ? '#8B0000' : '#f7f7f7'; ?>;
                              color:<?php echo $p === $page ? '#fff' : '#555'; ?>;
                              border:<?php echo $p === $page ? 'none' : '1px solid #e5e7eb'; ?>;">
                        <?php echo $p; ?>
                    </a>
                    <?php endforeach; ?>
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
            <div style="overflow-x:auto;" class="rp-table-scroll">
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
            <div style="overflow-x:auto;" class="rp-table-scroll">
            <table class="rp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usr_data as $i => $u):
                    $dname = deptName($all_depts, $u['college_id'] ?? '');
                ?>
                <tr>
                    <td style="color:rgba(0,0,0,0.35);font-size:0.75rem;"><?php echo $i+1; ?></td>
                    <td style="font-weight:700;"><?php echo htmlspecialchars($u['full_name']); ?></td>
                    <td style="font-size:0.82rem;"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td style="font-size:0.82rem;color:rgba(0,0,0,0.55);"><?php echo htmlspecialchars($u['phone'] ?? '—'); ?></td>
                    <td><span class="rp-badge rp-badge-<?php echo $u['role']; ?>"><?php echo $u['role'] === 'admin' ? 'Administrator' : 'Faculty/Staff'; ?></span></td>
                    <td><?php echo htmlspecialchars($dname); ?></td>
                    <td><span class="rp-badge <?php echo $u['is_active'] ? 'rp-badge-active' : 'rp-badge-inactive'; ?>"><?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                    <td style="font-size:0.79rem;color:rgba(0,0,0,0.50);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($usr_data)): ?>
                <tr><td colspan="8" style="text-align:center;padding:28px;color:rgba(0,0,0,0.35);">No users match the selected filters.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signature block (print only) -->
        <div class="rp-print-signatures">
            <div class="rp-print-sig">
                <div class="rp-print-sig-line"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                <div class="rp-print-sig-role">Prepared by</div>
            </div>
            <div class="rp-print-sig">
                <div class="rp-print-sig-line">&nbsp;</div>
                <div class="rp-print-sig-role">Certified Correct</div>
            </div>
            <div class="rp-print-sig">
                <div class="rp-print-sig-line">&nbsp;</div>
                <div class="rp-print-sig-role">Noted by</div>
            </div>
        </div>

        <!-- Print footer -->
        <div class="rp-print-footer">
            ManageMo &mdash; Pampanga State University &mdash; Report printed on <?php echo date('F d, Y h:i A'); ?>
            &nbsp;|&nbsp; Generated by <?php echo htmlspecialchars($current_user['full_name']); ?>
        </div>

    </div><!-- /.rp-printable -->
</div>

<script>
// Opens a clean, standalone copy of the current report — no filters, no tabs,
// no app chrome — in a new window, with an explicit "Save as PDF" button
// (kept separate from the "Print Report" button, which prints the live page).
function downloadReportPDF() {
    var source = document.querySelector('.rp-printable');
    if (!source) return;
    var clone = source.cloneNode(true);
    clone.querySelectorAll('.rp-no-print, .rp-screen-only').forEach(function(el) { el.remove(); });

    var win = window.open('', '_blank', 'width=1000,height=750,scrollbars=yes');
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8">'
        + '<title>Report</title>'
        + '<style>'
        + 'body{font-family:"Times New Roman",Times,Georgia,serif;background:#fff;margin:0;padding:24px;color:#000;}'
        + '.rp-print-header,.rp-print-header-rule,.rp-print-title,.rp-print-meta,.rp-print-only,.rp-print-signatures,.rp-print-footer{display:block;}'
        + '.rp-print-header{display:flex;flex-direction:column;align-items:center;text-align:center;gap:2px;padding-bottom:8px;margin-bottom:4px;}'
        + '.rp-print-header-logo{width:60px;height:60px;margin-bottom:4px;}'
        + '.rp-print-header-republic{font-size:10pt;font-style:italic;margin:0;}'
        + '.rp-print-header-text h2{font-size:14pt;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin:0;color:#000;}'
        + '.rp-print-header-text p{font-size:8.5pt;color:#000;margin:1px 0 0;}'
        + '.rp-print-header-rule{border:none;border-top:2.5pt solid #000;border-bottom:.75pt solid #000;height:4pt;margin:6px 0 14px;}'
        + '.rp-print-title{text-align:center;font-size:12pt;font-weight:700;text-decoration:underline;text-underline-offset:3px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;}'
        + '.rp-print-meta{font-size:9pt;color:#000;margin-bottom:16px;}'
        + '.rp-print-meta div{margin-bottom:2px;}'
        + '.rp-print-meta strong{display:inline-block;min-width:150px;}'
        + '.rp-card{box-shadow:none;border:none;}'
        + '.rp-card-head{display:none;}'
        + '.rp-table{border-collapse:collapse;width:100%;table-layout:auto;}'
        + '.rp-table th,.rp-table td{border:.75pt solid #000;padding:5px 8px;}'
        + '.rp-table th{background:#e5e5e5;font-size:7.5pt;text-transform:uppercase;text-align:center;}'
        + '.rp-table td{font-size:8pt;}'
        + '.rp-badge{font-size:7pt;padding:1px 6px;border:.75pt solid #000;background:transparent;color:#000;font-weight:600;}'
        + '.rp-summary-grid{border:.75pt solid #000;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;padding:16px 20px;}'
        + '.rp-summary-item{text-align:center;}'
        + '.rp-summary-val{font-size:14pt;color:#000;font-weight:900;}'
        + '.rp-summary-lbl{font-size:7pt;color:#000;text-transform:uppercase;}'
        + '.rp-print-signatures{display:flex;justify-content:space-between;gap:20px;margin-top:56px;}'
        + '.rp-print-sig{flex:1;text-align:center;font-size:8.5pt;}'
        + '.rp-print-sig-line{border-top:.75pt solid #000;margin-bottom:4px;padding-top:4px;font-weight:700;text-transform:uppercase;}'
        + '.rp-print-sig-role{color:#333;}'
        + '.rp-print-footer{margin-top:20px;padding-top:8px;border-top:.5pt solid #999;font-size:7pt;color:#555;text-align:center;}'
        + '.rp-download-toolbar{text-align:center;margin-bottom:20px;font-family:Arial,sans-serif;}'
        + '.rp-download-toolbar button{background:#8B0000;color:#fff;border:none;border-radius:6px;padding:10px 22px;font-size:14px;font-weight:700;cursor:pointer;}'
        + '@media print{.rp-download-toolbar{display:none !important;}@page{size:landscape;margin:15mm;}}'
        + '</style></head><body>'
        + '<div class="rp-download-toolbar"><button onclick="window.print()">Save as PDF / Print</button></div>'
        + clone.innerHTML
        + '</body></html>');
    win.document.close();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
