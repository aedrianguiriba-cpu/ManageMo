<?php
$page_title = 'Analytics';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$campus_id = $_GET['campus_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
$campuses = getAllCampuses();
?>

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
            <div class="an-filter-label">Campus</div>
            <select class="form-select" name="campus_id" style="min-width:160px;">
                <option value="">All Campuses</option>
                <?php foreach ($campuses as $campus): ?>
                <option value="<?php echo $campus['id']; ?>" <?php echo $campus_id==$campus['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($campus['name']); ?>
                </option>
                <?php endforeach; ?>
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
$filtered_inventory = $campus_id ? filterByColumn($all_inventory,'campus_id',(int)$campus_id) : $all_inventory;
$filtered_requests = array_filter($all_requests, function($r) use ($date_from,$date_to){
    $d = substr($r['created_at'],0,10); return $d >= $date_from && $d <= $date_to;
});

$inv_total       = count($filtered_inventory);
$inv_available   = count(filterByColumn($filtered_inventory,'status','available'));
$inv_borrowed    = count(filterByColumn($filtered_inventory,'status','borrowed'));
$inv_requested   = count(filterByColumn($filtered_inventory,'status','requested'));
$inv_maintenance = count(filterByColumn($filtered_inventory,'status','maintenance'));
$inv_value       = array_sum(array_column($filtered_inventory,'cost'));

$req_total       = count($filtered_requests);
$req_pending     = count(filterByColumn(array_values($filtered_requests),'status','pending'));
$req_approved    = count(filterByColumn(array_values($filtered_requests),'status','approved'));
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
?>

<!-- Stat cards -->
<div class="an-stat-grid">
    <div class="an-stat-card">
        <div class="an-stat-icon" style="color:#8B0000;">
            <i class="fas fa-warehouse"></i>
        </div>
        <div><div class="an-stat-value"><?php echo $inv_total; ?></div><div class="an-stat-label">Total Items</div></div>
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
        <div class="an-card">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-star"></i></div>
                Most Requested Items
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Item</th><th style="text-align:right;">Requests</th></tr></thead>
                <tbody>
                <?php if (!empty($top_items_data)): foreach ($top_items_data as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-primary"><?php echo $item['req_count']; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="2" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No data available</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Distribution -->
    <div class="col-md-6">
        <div class="an-card">
            <div class="an-card-title">
                <div class="an-card-icon"><i class="fas fa-tags"></i></div>
                Item Categories
            </div>
            <table class="an-mini-table">
                <thead><tr><th>Category</th><th style="text-align:right;">Count</th></tr></thead>
                <tbody>
                <?php if (!empty($category_counts)): foreach ($category_counts as $cat_name => $cat_count): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat_name); ?></td>
                    <td style="text-align:right;"><span class="an-badge an-badge-info"><?php echo $cat_count; ?></span></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="2" style="text-align:center;color:rgba(0,0,0,0.35);padding:24px;">No data available</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
