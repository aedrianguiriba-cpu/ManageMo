<?php
$page_title = 'Analytics';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

// One combined "department" filter covers Campuses, Colleges, and Offices
// together: value is "c:<campus id>" or "d:<college/office abbr>".
$dept_id    = $_GET['dept_id'] ?? '';
$campus_id  = str_starts_with($dept_id, 'c:') ? substr($dept_id, 2) : '';
$college_id = str_starts_with($dept_id, 'd:') ? substr($dept_id, 2) : '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">

<style>
/* ===== ADMIN ANALYTICS ===== */
.an-stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
@media(max-width:900px){ .an-stat-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:540px){ .an-stat-grid{ grid-template-columns:1fr; } }

.an-stat-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); padding:20px;
    display:flex; align-items:center; gap:16px;
}
.an-stat-icon {
    width:36px; height:36px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; flex-shrink:0;
}
.an-stat-value { font-size:1.75rem; font-weight:900; color:#1a1d23; line-height:1; }
.an-stat-label { font-size:0.76rem; font-weight:600; color:rgba(0,0,0,0.42); margin-top:4px; }

.an-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); padding:22px 24px; margin-bottom:20px;
}
.an-card-title {
    font-size:0.93rem; font-weight:800; color:#1a1d23;
    margin-bottom:16px; display:flex; align-items:center; gap:10px;
}
.an-card-icon {
    display:flex; align-items:center; justify-content:center;
    color:#8B0000; font-size:1rem; flex-shrink:0;
}

/* Filter card */
.an-filter-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    padding:16px 20px; margin-bottom:20px;
    display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;
}
.an-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(0,0,0,0.36); margin-bottom:5px; }
.an-btn-primary {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
}

/* Stat rows */
.an-stat-row { display:flex; align-items:center; gap:14px; padding:10px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
.an-stat-row:last-child { border-bottom:none; }
.an-stat-row-label { flex:1; font-size:0.87rem; color:#374151; }
.an-stat-row-value { font-weight:700; font-size:0.93rem; color:#1a1d23; min-width:36px; text-align:right; }
.an-progress-bar  { flex:0 0 100px; height:7px; border-radius:99px; background:rgba(0,0,0,0.07); overflow:hidden; }
.an-progress-fill { height:100%; border-radius:99px; }

/* Mini table */
.an-mini-table { width:100%; border-collapse:collapse; }
.an-mini-table th { font-size:0.69rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(0,0,0,0.36); padding:8px 12px; border-bottom:1px solid rgba(0,0,0,0.07); }
.an-mini-table td { padding:9px 12px; border-bottom:1px solid rgba(0,0,0,0.05); font-size:0.86rem; color:#374151; }
.an-mini-table tr:last-child td { border-bottom:none; }
.an-mini-table tr:hover td { background:rgba(0,0,0,0.015); }

/* Mini table pager — shared by every paginated box on this page so they all
   look and behave the same, and stay a consistent height regardless of how
   many rows a given list happens to have. */
.an-mini-pager { display:flex; justify-content:center; gap:6px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,0.05); }
.an-mini-pager a {
    min-width:26px; text-align:center; border-radius:5px; padding:3px 0;
    font-size:0.78rem; font-weight:700; text-decoration:none;
    background:#f7f7f7; color:#555; border:1px solid #e5e7eb;
}
.an-mini-pager a.active { background:#8B0000; color:#fff; border-color:#8B0000; }

/* Equal-height cards within a row, with the pager always pinned to the
   bottom — otherwise a card with fewer rows just looks shorter than its
   neighbors instead of lining up with them. */
.row.g-3 > [class*="col-"] { display:flex; }
.an-card { display:flex; flex-direction:column; width:100%; }
.an-card > table { flex:0 0 auto; }
.an-card > .an-mini-pager { margin-top:auto; }

.an-badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:4px; font-size:0.74rem; font-weight:700;
}
.an-badge-success   { background:rgba(34,197,94,0.12);  color:#15803d; }
.an-badge-warning   { background:rgba(245,158,11,0.12); color:#b45309; }
.an-badge-danger    { background:rgba(239,68,68,0.12);  color:#dc2626; }
.an-badge-info      { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.an-badge-primary   { background:rgba(139,0,0,0.10);     color:#8B0000; }
.an-badge-secondary { background:rgba(0,0,0,0.07);       color:rgba(0,0,0,0.50); }

.an-value-row { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#f7f7f7; border-radius:6px; margin-top:14px; }
.an-value-row span:first-child { font-size:0.87rem; color:rgba(0,0,0,0.55); font-weight:600; }
.an-value-row span:last-child  { font-size:1rem;    color:#8B0000; font-weight:800; }
</style>

<div class="container-fluid mt-4 pb-4">

<!-- Filter -->
<div class="an-filter-card">
    <form method="GET" class="d-flex align-items-end flex-wrap gap-3 w-100">
        <div>
            <div class="an-filter-label">Campus / College / Office</div>
            <select class="form-select" name="dept_id" style="min-width:180px;">
                <option value="">All</option>
                <optgroup label="Campuses">
                <?php foreach (getAllCampuses() as $__c): ?>
                <option value="c:<?php echo $__c['id']; ?>" <?php echo $dept_id==='c:'.$__c['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($__c['name']); ?>
                </option>
                <?php endforeach; ?>
                </optgroup>
                <optgroup label="Colleges/Offices">
                <?php foreach (getMainCampusDepartments() as $abbr => $fullname): ?>
                <option value="d:<?php echo htmlspecialchars($abbr); ?>" <?php echo $dept_id==='d:'.$abbr?'selected':''; ?>>
                    <?php echo htmlspecialchars($fullname); ?>
                </option>
                <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div>
            <div class="an-filter-label">Date From</div>
            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" style="min-width:140px;">
        </div>
        <div>
            <div class="an-filter-label">Date To</div>
            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" style="min-width:140px;">
        </div>
        <button type="submit" class="btn an-btn-primary"><i class="fas fa-search me-1"></i> Apply Filter</button>
    </form>
</div>

<?php
$all_inventory = getInventory();
$all_requests  = getRequests();

// A campus "owns" inventory the same way a college/office does — via its
// abbreviation stored in inventory.college_id (the legacy numeric campus_id
// column is no longer populated), so resolve the picked campus id to its
// abbreviation before filtering.
$campus_abbr = '';
if ($campus_id) {
    $campus_row  = findById(getDepartmentCampuses(), (int)$campus_id);
    $campus_abbr = $campus_row['abbreviation'] ?? '';
}
$owner_code = $college_id ?: $campus_abbr;
$filtered_inventory = $owner_code ? filterByColumn($all_inventory,'college_id',$owner_code) : $all_inventory;
// Condemned/disposed items are out of service — keep them out of the totals.
$filtered_inventory = array_values(array_filter($filtered_inventory, fn($i) => !in_array($i['status'], ['condemned', 'disposed'])));
$filtered_requests = array_filter($all_requests, function($r) use ($date_from,$date_to){
    $d = substr($r['created_at'],0,10); return $d >= $date_from && $d <= $date_to;
});

$inv_total       = count($filtered_inventory);
// User-owned items live in their own table; scoped by the same department filter.
$all_owned_items = getUserOwnedItems();
$owned_total     = count($owner_code ? filterByColumn($all_owned_items, 'college_id', $owner_code) : $all_owned_items);
$inv_available   = count(filterByColumn($filtered_inventory,'status','available'));
$inv_borrowed    = count(filterByColumn($filtered_inventory,'status','borrowed'));
$inv_requested   = count(filterByColumn($filtered_inventory,'status','requested'));
$inv_maintenance = count(filterByColumn($filtered_inventory,'status','maintenance'));
$inv_value       = array_sum(array_column($filtered_inventory,'cost'));

$req_total       = count($filtered_requests);
$req_pending     = count(filterByColumn(array_values($filtered_requests),'status','pending'));
// A request that was approved doesn't stay at status='approved' — it moves on
// to 'delivered' then 'completed' (or just 'completed' for a service ticket).
// Counting the literal 'approved' status alone missed every request that had
// already progressed past that point, which in practice is almost all of
// them — this showed 0 approved even with a full pipeline of approved,
// fulfilled requests. 'Was approved' means it's anywhere past that gate and
// wasn't disapproved, i.e. any status other than pending/disapproved.
$req_approved    = count(array_filter($filtered_requests, fn($r) => in_array($r['status'], ['approved','delivered','completed'], true)));
$req_disapproved = count(filterByColumn(array_values($filtered_requests),'status','disapproved'));
$req_critical    = count(filterByColumn(array_values($filtered_requests),'urgency','critical'));

$request_counts = [];
foreach ($filtered_requests as $r) {
    if ($r['inventory_id']) $request_counts[$r['inventory_id']] = ($request_counts[$r['inventory_id']] ?? 0) + 1;
}
arsort($request_counts);
$top_items_data = [];
foreach (array_slice($request_counts,0,10,true) as $inv_id => $cnt) {
    $inv_item = findById($filtered_inventory,$inv_id) ?? findById($all_inventory,$inv_id);
    if ($inv_item) $top_items_data[] = ['item_name'=>$inv_item['item_name'],'req_count'=>$cnt];
}

$category_counts = [];
foreach ($filtered_inventory as $inv) $category_counts[$inv['category']] = ($category_counts[$inv['category']] ?? 0) + 1;
arsort($category_counts);

// Re-shape into the same [{'name','total'}] row shape anPaginate()/anPageUrl()
// (defined below — PHP hoists top-level function declarations) expect, so
// every mini-table on this page paginates the same way.
$category_rows = [];
foreach ($category_counts as $cat_name => $cat_count) $category_rows[] = ['name' => $cat_name, 'total' => $cat_count];

// These breakdowns only scanned the inventory table — but an inventory item's
// college_id is only ever set while it's actively borrowed/requested (see
// "Available = no owner"), and clears back to null the moment it's returned.
// User-owned items (acquired/custom items permanently transferred to someone)
// live entirely in the separate user_owned_items table instead, each carrying
// its own college_id — none of that ever got counted here, so a department
// could genuinely own dozens of items and still show "1" or nothing at all.
// user_owned_items has no cost column, so owned items add to Items but not Value.
$__owned_for_breakdown = getUserOwnedItems();

// Per-college breakdown
$college_breakdown = [];
foreach (getMainCampusColleges() as $abbr => $fullname) {
    $co_items = array_values(array_filter($all_inventory, fn($i) => ($i['college_id'] ?? '') === $abbr));
    $co_owned = array_values(array_filter($__owned_for_breakdown, fn($i) => ($i['college_id'] ?? '') === $abbr));
    if (empty($co_items) && empty($co_owned)) continue;
    $college_breakdown[] = [
        'name'  => $fullname,
        'total' => count($co_items) + count($co_owned),
        'value' => array_sum(array_column($co_items, 'cost')),
    ];
}
usort($college_breakdown, fn($a, $b) => $b['total'] <=> $a['total']);

// Per-office breakdown
$office_breakdown = [];
foreach (getMainCampusOffices() as $abbr => $fullname) {
    $of_items = array_values(array_filter($all_inventory, fn($i) => ($i['college_id'] ?? '') === $abbr));
    $of_owned = array_values(array_filter($__owned_for_breakdown, fn($i) => ($i['college_id'] ?? '') === $abbr));
    if (empty($of_items) && empty($of_owned)) continue;
    $office_breakdown[] = [
        'name'  => $fullname,
        'total' => count($of_items) + count($of_owned),
        'value' => array_sum(array_column($of_items, 'cost')),
    ];
}
usort($office_breakdown, fn($a, $b) => $b['total'] <=> $a['total']);

// Per-campus breakdown
$campus_breakdown = [];
foreach (getDepartmentCampuses() as $campus) {
    if ($campus['abbreviation'] === '') continue;
    $ca_items = array_values(array_filter($all_inventory, fn($i) => ($i['college_id'] ?? '') === $campus['abbreviation']));
    $ca_owned = array_values(array_filter($__owned_for_breakdown, fn($i) => ($i['college_id'] ?? '') === $campus['abbreviation']));
    if (empty($ca_items) && empty($ca_owned)) continue;
    $campus_breakdown[] = [
        'name'  => $campus['name'],
        'total' => count($ca_items) + count($ca_owned),
        'value' => array_sum(array_column($ca_items, 'cost')),
    ];
}
usort($campus_breakdown, fn($a, $b) => $b['total'] <=> $a['total']);

// Pagination for the three breakdown tables — each paginates independently
// since they're unrelated lists, same pattern as the Department Summary table
// on the dashboard.
$__breakdown_page_size = 5;
function anPaginate(array $rows, string $get_key, int $page_size): array {
    $page        = max(1, (int)($_GET[$get_key] ?? 1));
    $total_pages = max(1, (int)ceil(count($rows) / $page_size));
    $page        = min($page, $total_pages);
    $offset      = ($page - 1) * $page_size;
    return ['page' => $page, 'total_pages' => $total_pages, 'rows' => array_slice($rows, $offset, $page_size)];
}
$__college_pg  = anPaginate($college_breakdown, 'college_page',  $__breakdown_page_size);
$__office_pg   = anPaginate($office_breakdown,  'office_page',   $__breakdown_page_size);
$__campus_pg   = anPaginate($campus_breakdown,  'campus_page',   $__breakdown_page_size);
$__topitems_pg = anPaginate($top_items_data,    'topitems_page', $__breakdown_page_size);
$__category_pg = anPaginate($category_rows,     'category_page', $__breakdown_page_size);

// Preserves the page's other filters (dept_id/date range) when switching pages
// on one of these tables, even though the tables themselves aren't date-filtered.
function anPageUrl(string $get_key, int $page): string {
    global $dept_id, $date_from, $date_to;
    $qs = $_GET;
    $qs[$get_key] = $page;
    $qs['dept_id']    = $dept_id;
    $qs['date_from']  = $date_from;
    $qs['date_to']    = $date_to;
    return 'analytics.php?' . http_build_query($qs);
}
?>

<!-- Stat cards -->
<div class="an-stat-grid">
    <div class="an-stat-card">
        <div class="an-stat-icon" style="color:#8B0000;">
            <i class="fas fa-warehouse"></i>
        </div>
        <div><div class="an-stat-value"><?php echo $inv_total + $owned_total; ?></div><div class="an-stat-label">Total Items</div></div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="color:#15803d;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div><div class="an-stat-value"><?php echo $inv_available; ?></div><div class="an-stat-label">Available</div></div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="color:#b45309;">
            <i class="fas fa-share-alt"></i>
        </div>
        <div><div class="an-stat-value"><?php echo $inv_borrowed; ?></div><div class="an-stat-label">Borrowed</div></div>
    </div>
    <div class="an-stat-card">
        <div class="an-stat-icon" style="color:#dc2626;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div><div class="an-stat-value"><?php echo $req_critical; ?></div><div class="an-stat-label">Critical Requests</div></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Inventory Status -->
    <div class="col-md-6">
        <div class="an-card">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-chart-pie"></i></div>
                Inventory Status Distribution
            </div>
            <?php
            $status_dist = [
                ['label'=>'Available',   'val'=>$inv_available,   'color'=>'#15803d', 'bg'=>'rgba(34,197,94,0.7)'],
                ['label'=>'Borrowed',    'val'=>$inv_borrowed,    'color'=>'#b45309', 'bg'=>'rgba(245,158,11,0.7)'],
                ['label'=>'Requested',   'val'=>$inv_requested,   'color'=>'#7c3aed', 'bg'=>'rgba(168,85,247,0.7)'],
                ['label'=>'Maintenance', 'val'=>$inv_maintenance, 'color'=>'#1d4ed8', 'bg'=>'rgba(59,130,246,0.7)'],
            ];
            foreach ($status_dist as $s):
                $pct = $inv_total > 0 ? round($s['val'] / $inv_total * 100) : 0;
            ?>
            <div class="an-stat-row">
                <div class="an-stat-row-label" style="color:<?php echo $s['color']; ?>;font-weight:600;"><?php echo $s['label']; ?></div>
                <div class="an-stat-row-value"><?php echo $s['val']; ?></div>
                <div class="an-progress-bar"><div class="an-progress-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $s['bg']; ?>;"></div></div>
                <div style="font-size:0.74rem;color:rgba(0,0,0,0.40);min-width:30px;text-align:right;"><?php echo $pct; ?>%</div>
            </div>
            <?php endforeach; ?>
            <div class="an-value-row">
                <span>Total Asset Value</span>
                <span>&#8369;<?php echo number_format($inv_value,2); ?></span>
            </div>
        </div>
    </div>

    <!-- Request Stats -->
    <div class="col-md-6">
        <div class="an-card">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-clipboard-list"></i></div>
                Request Statistics
                <span style="font-size:0.72rem;font-weight:500;color:rgba(0,0,0,0.40);margin-left:auto;"><?php echo $date_from; ?> — <?php echo $date_to; ?></span>
            </div>
            <div class="an-stat-row">
                <div class="an-stat-row-label">Total Requests</div>
                <div class="an-stat-row-value"><?php echo $req_total; ?></div>
            </div>
            <div class="an-stat-row">
                <div class="an-stat-row-label">Pending</div>
                <span class="an-badge an-badge-warning"><?php echo $req_pending; ?></span>
            </div>
            <div class="an-stat-row">
                <div class="an-stat-row-label">Approved</div>
                <span class="an-badge an-badge-success"><?php echo $req_approved; ?></span>
            </div>
            <div class="an-stat-row">
                <div class="an-stat-row-label">Disapproved</div>
                <span class="an-badge an-badge-danger"><?php echo $req_disapproved; ?></span>
            </div>
            <div class="an-stat-row" style="background:rgba(139,0,0,0.04);border-radius:8px;padding:10px 12px;margin-top:4px;border:none;">
                <div class="an-stat-row-label" style="font-weight:700;color:#8B0000;">Critical Issues</div>
                <div class="an-stat-row-value" style="color:#8B0000;"><?php echo $req_critical; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Most Requested Items -->
    <div class="col-md-6">
        <div class="an-card" id="top-items">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-star"></i></div>
                Most Requested Items
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Item</th><th style="text-align:right;">Requests</th></tr></thead>
                <tbody>
                <?php if (!empty($__topitems_pg['rows'])): foreach ($__topitems_pg['rows'] as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-primary"><?php echo $item['req_count']; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="2" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No data available</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($__topitems_pg['total_pages'] > 1): ?>
            <div class="an-mini-pager">
                <?php for ($i = 1; $i <= $__topitems_pg['total_pages']; $i++): ?>
                <a href="<?php echo anPageUrl('topitems_page', $i); ?>#top-items" class="<?php echo $i === $__topitems_pg['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-md-6">
        <div class="an-card" id="category-breakdown">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-tags"></i></div>
                Item Categories
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Category</th><th style="text-align:right;">Count</th></tr></thead>
                <tbody>
                <?php if (!empty($__category_pg['rows'])): foreach ($__category_pg['rows'] as $cat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-info"><?php echo $cat['total']; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="2" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No data available</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($__category_pg['total_pages'] > 1): ?>
            <div class="an-mini-pager">
                <?php for ($i = 1; $i <= $__category_pg['total_pages']; $i++): ?>
                <a href="<?php echo anPageUrl('category_page', $i); ?>#category-breakdown" class="<?php echo $i === $__category_pg['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Per-College / Per-Office / Per-Campus Breakdown -->
<div class="row g-3 mt-1">
    <div class="col-md-4">
        <div class="an-card" id="college-breakdown">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-building-columns"></i></div>
                Inventory by College <span style="font-weight:400;color:rgba(0,0,0,0.35);font-size:0.78rem;">(overall — not date filtered)</span>
            </div>
            <table class="an-mini-table">
                <thead><tr><th>College</th><th style="text-align:right;">Items</th><th style="text-align:right;">Value</th></tr></thead>
                <tbody>
                <?php if (!empty($__college_pg['rows'])): foreach ($__college_pg['rows'] as $co): ?>
                <tr>
                    <td><?php echo htmlspecialchars($co['name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-primary"><?php echo $co['total']; ?></span></td>
                    <td style="text-align:right;">&#8369;<?php echo number_format($co['value'], 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No items tagged with a college yet — set this when adding/editing an item.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($__college_pg['total_pages'] > 1): ?>
            <div class="an-mini-pager">
                <?php for ($i = 1; $i <= $__college_pg['total_pages']; $i++): ?>
                <a href="<?php echo anPageUrl('college_page', $i); ?>#college-breakdown" class="<?php echo $i === $__college_pg['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="an-card" id="office-breakdown">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-building"></i></div>
                Inventory by Office <span style="font-weight:400;color:rgba(0,0,0,0.35);font-size:0.78rem;">(overall — not date filtered)</span>
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Office</th><th style="text-align:right;">Items</th><th style="text-align:right;">Value</th></tr></thead>
                <tbody>
                <?php if (!empty($__office_pg['rows'])): foreach ($__office_pg['rows'] as $of): ?>
                <tr>
                    <td><?php echo htmlspecialchars($of['name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-info"><?php echo $of['total']; ?></span></td>
                    <td style="text-align:right;">&#8369;<?php echo number_format($of['value'], 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No items tagged with an office yet — set this when adding/editing an item.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($__office_pg['total_pages'] > 1): ?>
            <div class="an-mini-pager">
                <?php for ($i = 1; $i <= $__office_pg['total_pages']; $i++): ?>
                <a href="<?php echo anPageUrl('office_page', $i); ?>#office-breakdown" class="<?php echo $i === $__office_pg['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-md-4">
        <div class="an-card" id="campus-breakdown">
            <div class="an-card-title">
                <div class="an-card-icon" style="color:#7c3aed;"><i class="fas fa-map-marker-alt"></i></div>
                Inventory by Campus <span style="font-weight:400;color:rgba(0,0,0,0.35);font-size:0.78rem;">(overall — not date filtered)</span>
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Campus</th><th style="text-align:right;">Items</th><th style="text-align:right;">Value</th></tr></thead>
                <tbody>
                <?php if (!empty($__campus_pg['rows'])): foreach ($__campus_pg['rows'] as $ca): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ca['name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge" style="background:rgba(124,58,237,.10);color:#7c3aed;"><?php echo $ca['total']; ?></span></td>
                    <td style="text-align:right;">&#8369;<?php echo number_format($ca['value'], 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="3" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No items tagged with a campus yet — set this when adding/editing an item.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
            <?php if ($__campus_pg['total_pages'] > 1): ?>
            <div class="an-mini-pager">
                <?php for ($i = 1; $i <= $__campus_pg['total_pages']; $i++): ?>
                <a href="<?php echo anPageUrl('campus_page', $i); ?>#campus-breakdown" class="<?php echo $i === $__campus_pg['page'] ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
