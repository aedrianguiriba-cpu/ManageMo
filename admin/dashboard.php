<?php
$page_title = 'Admin Dashboard';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<?php
requireAdmin();

$current_user = getCurrentUser();
$campus_stats = [];
$total_items = 0;
$available_items = 0;
$borrowed_items = 0;
$pending_requests = 0;

// Get all campuses with stats
$campuses = getAllCampuses();
$all_inventory = getInventory();
$all_requests = getRequests();

$campus_stats = [];
$total_items = 0;
$available_items = 0;
$borrowed_items = 0;

foreach ($campuses as $campus) {
    $campus_id = $campus['id'];
    
    // Get inventory for this campus
    $campus_inventory = filterByColumn($all_inventory, 'campus_id', $campus_id);
    $status_counts = countByStatus($campus_inventory);
    
    // Count requested items for this campus
    $campus_inventory_ids = array_column($campus_inventory, 'id');
    $campus_requests = filterByColumn($all_requests, 'inventory_id', $campus_inventory_ids[0] ?? null);
    $requested_count = 0;
    foreach ($campus_inventory_ids as $inv_id) {
        $requested_count += count(filterByColumn($all_requests, 'inventory_id', $inv_id));
    }
    
    $campus['stats'] = [
        'total' => count($campus_inventory),
        'borrowed' => $status_counts['borrowed'] ?? 0,
        'requested' => $requested_count,
        'maintenance' => $status_counts['maintenance'] ?? 0,
    ];
    
    $campus_stats[] = $campus;
    $total_items += $campus['stats']['total'];
    $borrowed_items += $campus['stats']['borrowed'];
}

// Get request statistics
$pending = count(filterByColumn($all_requests, 'status', 'pending'));
$approved = count(filterByColumn($all_requests, 'status', 'approved'));
$disapproved = count(filterByColumn($all_requests, 'status', 'disapproved'));
$pending_requests = $pending;

// Get recent requests
$recent_requests = [];
$result_requests = $all_requests;
usort($result_requests, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});

foreach (array_slice($result_requests, 0, 5) as $req) {
    $user = findById(getUsers(), $req['user_id']);
    $item = findById($all_inventory, $req['inventory_id']);
    
    $recent_requests[] = array_merge($req, [
        'full_name' => $user['full_name'] ?? 'Unknown',
        'item_name' => $item['item_name'] ?? 'Unknown Item'
    ]);
}

// Get recent inventory additions
$recent_inventory = getInventory();
// Sort by created_at descending
usort($recent_inventory, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
$recent_inventory = array_slice($recent_inventory, 0, 5);

// Get user-owned items stats
$user_owned_items = getUserOwnedItems();
$total_owned_items = count($user_owned_items);
$owned_by_user = array_reduce($user_owned_items, function($carry, $item) {
    return $carry + $item['quantity'];
}, 0);

// Prepare campus data for modal
$modal_campuses_json = json_encode($campus_stats);
$all_inventory_json = json_encode($all_inventory);
$all_requests_json = json_encode($all_requests);
?>

<?php
// Compute per-campus maintenance totals for charts
$campus_names_js   = [];
$campus_totals_js  = [];
$campus_borrowed_js = [];
$campus_maint_js   = [];
foreach ($campus_stats as $cs) {
    $campus_names_js[]    = $cs['name'];
    $campus_totals_js[]   = $cs['stats']['total'];
    $campus_borrowed_js[] = $cs['stats']['borrowed'];
    $campus_maint_js[]    = $cs['stats']['maintenance'];
}
$maintenance_total = array_sum($campus_maint_js);
$computed_available = $total_items - $borrowed_items - $maintenance_total;
?>
<style>
/* ── KPI grid ── */
.adash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px; margin-bottom: 24px;
}
@media(max-width:1000px){ .adash-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:600px) { .adash-kpi-grid{ grid-template-columns:1fr; } }

.adash-kpi {
    background: #fff;
    border-radius: 8px;
    border: 1.5px solid var(--kpi-color, #8B0000);
    padding: 20px;
    display: flex; align-items: flex-start; gap: 14px;
}
.adash-kpi-icon {
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
    color: var(--kpi-color, #8B0000);
}
.adash-kpi-body { flex: 1; min-width: 0; }
.adash-kpi-val   { font-size: 2rem; font-weight: 800; color: #111; line-height:1; letter-spacing:-1px; }
.adash-kpi-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#999; margin-top:6px; }

/* ── Generic card ── */
.adash-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 22px;
    margin-bottom: 18px;
}
.adash-card-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 18px; gap: 12px; padding-bottom: 14px;
    border-bottom: 1px solid #f0f0f0;
}
.adash-card-title {
    display: flex; align-items: center; gap: 10px;
    font-size: 0.92rem; font-weight: 700; color: #111;
}
.adash-card-icon {
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem; color: #8B0000; flex-shrink:0;
}

/* ── Table ── */
.adash-table { width:100%; border-collapse:collapse; }
.adash-table th {
    padding: 10px 14px; font-size:.69rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.4px; color:#999;
    border-bottom: 1px solid #e5e7eb; background:#f7f7f7;
    white-space: nowrap;
}
.adash-table td {
    padding: 12px 14px; font-size:.875rem; color:#555;
    border-bottom: 1px solid #f0f0f0; vertical-align:middle;
}
.adash-table tr:last-child td { border-bottom:none; }
.adash-table tbody tr:hover td { background:#f7f7f7; color:#111; }

/* ── Badges ── */
.adash-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 9px; border-radius:4px;
    font-size:.70rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px;
}
.adash-badge i { font-size:.60rem; }
.b-green  { background:rgba(22,163,74,.10);  color:#15803d; }
.b-amber  { background:rgba(217,119,6,.10);  color:#b45309; }
.b-red    { background:rgba(220,38,38,.10);  color:#dc2626; }
.b-blue   { background:rgba(37,99,235,.10);  color:#1d4ed8; }
.b-gray   { background:#f0f0f0;              color:#555; }

/* ── Chart legend ── */
.chart-legend { display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:14px; }
.chart-legend-item { display:flex; align-items:center; gap:7px; font-size:.78rem; font-weight:600; color:#555; }
.chart-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

/* ── Donut center label ── */
.donut-wrap { position:relative; }
.donut-center {
    position:absolute; inset:0;
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    pointer-events:none;
}
.donut-center-val  { font-size:1.6rem; font-weight:800; color:#111; line-height:1; }
.donut-center-lbl  { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#999; margin-top:3px; }

/* ── Request stat rows ── */
.req-stat-item {
    display:flex; align-items:center; gap:14px;
    padding:10px 12px;
    border: 1px solid #f0f0f0;
    border-radius: 6px;
    margin-bottom:8px;
    position:relative;
    background: #fff;
}
.req-stat-item::before {
    content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
    background: var(--dot-color, #8B0000);
    border-radius: 6px 0 0 6px;
}
.req-stat-item:last-child { margin-bottom:0; }
.req-stat-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; background: var(--dot-color, #8B0000); }
.req-stat-label { flex:1; font-size:.82rem; font-weight:600; color:#555; }
.req-stat-count { font-size:1.1rem; font-weight:800; color:#111; }

/* ── Quick action buttons ── */
.qa-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.qa-btn {
    display:flex; align-items:center; gap:12px; padding:14px 16px;
    border-radius:6px; border:1px solid #e5e7eb;
    background: #fff;
    text-decoration:none; color:#555; font-size:.875rem; font-weight:600;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.qa-btn:hover { background:#f7f7f7; border-color:#bbb; color:#111; text-decoration:none; }
.qa-btn-icon { font-size:.9rem; flex-shrink:0; width:18px; text-align:center; }

/* ── View-all link ── */
.adash-viewall {
    display:inline-flex; align-items:center; gap:6px;
    font-size:.80rem; font-weight:600; color:#555; text-decoration:none;
    padding:6px 12px; border-radius:5px; border:1px solid #e5e7eb;
    transition: background 0.15s, color 0.15s;
}
.adash-viewall:hover { background:#f7f7f7; color:#111; text-decoration:none; }

/* ── Activity item ── */
.act-item {
    display:flex; align-items:center; gap:12px;
    padding:12px 0; border-bottom:1px solid #f0f0f0;
}
.act-item:last-child { border-bottom:none; padding-bottom:0; }
.act-avatar { font-size:.82rem; flex-shrink:0; color:#999; width:18px; text-align:center; }
.act-body { flex:1; min-width:0; }
.act-name { font-size:.875rem; font-weight:700; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.act-sub  { font-size:.76rem; color:#999; margin-top:2px; }
.act-right{ text-align:right; flex-shrink:0; }
.act-date { font-size:.74rem; color:#bbb; font-weight:600; white-space:nowrap; }

@media(max-width:768px){
    .qa-grid { grid-template-columns:1fr; }
    .adash-kpi-grid { gap:10px; }
}
</style>

<div class="main-wrapper">

    <!-- ── KPI Cards ── -->
    <div class="adash-kpi-grid">
        <div class="adash-kpi" style="--kpi-color:#b91c1c;">
            <div class="adash-kpi-icon"><i class="fas fa-warehouse"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $total_items; ?></div>
                <div class="adash-kpi-label">Total Items</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-color:#15803d;">
            <div class="adash-kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $computed_available; ?></div>
                <div class="adash-kpi-label">Available</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-color:#d97706;">
            <div class="adash-kpi-icon"><i class="fas fa-share-alt"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $borrowed_items; ?></div>
                <div class="adash-kpi-label">Borrowed</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-color:#2563eb;">
            <div class="adash-kpi-icon"><i class="fas fa-tools"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $maintenance_total; ?></div>
                <div class="adash-kpi-label">Maintenance</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-color:#7c3aed;">
            <div class="adash-kpi-icon"><i class="fas fa-user-check"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $total_owned_items; ?></div>
                <div class="adash-kpi-label">User-Owned Items</div>
            </div>
        </div>
    </div>

    <!-- ── Campus Inventory Bar Chart ── -->
    <div class="adash-card" style="margin-bottom:18px;">
        <div class="adash-card-head">
            <div class="adash-card-title">
                <span class="adash-card-icon"><i class="fas fa-building"></i></span>
                Campus Inventory Overview
            </div>
            <a href="inventory-campus.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> View All</a>
        </div>
        <canvas id="campusBar" height="80"></canvas>
    </div>

    <!-- ── Campus Summary Table ── -->
    <div class="adash-card" style="margin-bottom:18px;">
        <div class="adash-card-head">
            <div class="adash-card-title">
                <span class="adash-card-icon"><i class="fas fa-table"></i></span>
                Campus Summary
            </div>
        </div>
        <div class="table-responsive">
            <table class="adash-table">
                <thead><tr>
                    <th>Campus</th>
                    <th>Total</th>
                    <th>Borrowed</th>
                    <th>Requested</th>
                    <th>Maintenance</th>
                    <th></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($campus_stats as $campus): ?>
                    <tr>
                        <td><div style="font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($campus['name']); ?></div></td>
                        <td><span style="font-weight:800;color:#0f172a;font-size:.95rem;"><?php echo $campus['stats']['total']; ?></span></td>
                        <td><span class="adash-badge b-amber"><i class="fas fa-circle"></i><?php echo $campus['stats']['borrowed']; ?></span></td>
                        <td><span class="adash-badge b-green"><i class="fas fa-circle"></i><?php echo $campus['stats']['requested']; ?></span></td>
                        <td><span class="adash-badge b-blue"><i class="fas fa-circle"></i><?php echo $campus['stats']['maintenance']; ?></span></td>
                        <td><button onclick="openCampusModal(<?php echo $campus['id']; ?>)" class="adash-viewall" style="padding:5px 10px;font-size:.75rem;background:none;border:none;cursor:pointer;"><i class="fas fa-eye"></i> View</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Charts Row ── -->
    <div class="row g-4 mb-4">

        <!-- Item Status Doughnut -->
        <div class="col-lg-4">
            <div class="adash-card h-100" style="margin-bottom:0;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-chart-pie"></i></span>
                        Item Status
                    </div>
                </div>
                <div class="donut-wrap" style="max-width:200px;margin:0 auto;">
                    <canvas id="statusDonut" height="200"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val"><?php echo $total_items; ?></div>
                        <div class="donut-center-lbl">Total</div>
                    </div>
                </div>
                <div class="chart-legend justify-content-center">
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#15803d;"></div>Available</div>
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#d97706;"></div>Borrowed</div>
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#2563eb;"></div>Maintenance</div>
                </div>
            </div>
        </div>

        <!-- Request Status Doughnut -->
        <div class="col-lg-4">
            <div class="adash-card h-100" style="margin-bottom:0;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-tasks"></i></span>
                        Request Status
                    </div>
                    <a href="requests.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> Manage</a>
                </div>
                <div class="donut-wrap" style="max-width:200px;margin:0 auto;">
                    <canvas id="requestDonut" height="200"></canvas>
                    <div class="donut-center">
                        <div class="donut-center-val"><?php echo $pending + $approved + $disapproved; ?></div>
                        <div class="donut-center-lbl">Total</div>
                    </div>
                </div>
                <div class="chart-legend justify-content-center">
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#b45309;"></div>Pending</div>
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#15803d;"></div>Approved</div>
                    <div class="chart-legend-item"><div class="chart-legend-dot" style="background:#dc2626;"></div>Disapproved</div>
                </div>
                <div class="mt-3">
                    <div class="req-stat-item" style="--dot-color:#b45309;">
                        <div class="req-stat-dot" style="background:#b45309;"></div>
                        <span class="req-stat-label">Pending Review</span>
                        <span class="req-stat-count"><?php echo $pending; ?></span>
                    </div>
                    <div class="req-stat-item" style="--dot-color:#15803d;">
                        <div class="req-stat-dot" style="background:#15803d;"></div>
                        <span class="req-stat-label">Approved</span>
                        <span class="req-stat-count"><?php echo $approved; ?></span>
                    </div>
                    <div class="req-stat-item" style="--dot-color:#dc2626;">
                        <div class="req-stat-dot" style="background:#dc2626;"></div>
                        <span class="req-stat-label">Disapproved</span>
                        <span class="req-stat-count"><?php echo $disapproved; ?></span>
                    </div>
                </div>
                <a href="requests.php" class="adash-viewall mt-3 w-100 justify-content-center d-flex"
                   style="background:#8B0000;color:#fff;border-color:#8B0000;padding:10px 18px;font-size:.84rem;">
                    <i class="fas fa-list-check me-2"></i> Review All Requests
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="adash-card h-100" style="margin-bottom:0;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-bolt"></i></span>
                        Quick Actions
                    </div>
                </div>
                <div class="qa-grid">
                    <a href="inventory.php" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#8B0000;"><i class="fas fa-warehouse"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">Manage Inventory</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">View all items</div></div>
                    </a>
                    <a href="inventory.php?action=add" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#15803d;"><i class="fas fa-plus-circle"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">Add New Item</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">Register asset</div></div>
                    </a>
                    <a href="requests.php" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#b45309;"><i class="fas fa-clipboard-check"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">Review Requests</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;"><?php echo $pending; ?> pending</div></div>
                    </a>
                    <a href="analytics.php" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#1d4ed8;"><i class="fas fa-chart-bar"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">Analytics</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">Reports &amp; insights</div></div>
                    </a>
                    <a href="inventory-campus.php" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#7c3aed;"><i class="fas fa-map-marked-alt"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">By Campus</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">Campus breakdown</div></div>
                    </a>
                    <a href="settings.php" class="qa-btn">
                        <div class="qa-btn-icon" style="color:#64748b;"><i class="fas fa-cog"></i></div>
                        <div><div style="font-weight:700;font-size:.82rem;color:#0f172a;">Settings</div><div style="font-size:.72rem;color:#94a3b8;margin-top:2px;">System config</div></div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Recent Requests + Recent Items ── -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="adash-card" style="margin-bottom:0;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-clock"></i></span>
                        Recent Requests
                    </div>
                    <a href="requests.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> All</a>
                </div>
                <?php foreach ($recent_requests as $req):
                    $sc_map = ['pending'=>'b-amber','approved'=>'b-green','disapproved'=>'b-red','delivered'=>'b-blue'];
                    $sc = $sc_map[$req['status']] ?? 'b-gray';
                ?>
                <div class="act-item">
                    <div class="act-avatar"><i class="fas fa-user"></i></div>
                    <div class="act-body">
                        <div class="act-name"><?php echo htmlspecialchars($req['full_name']); ?></div>
                        <div class="act-sub">
                            <code style="font-size:.73rem;color:#8B0000;background:rgba(139,0,0,.06);padding:1px 5px;border-radius:4px;"><?php echo $req['request_number']; ?></code>
                            &nbsp;<?php echo ucfirst($req['request_type']); ?>
                        </div>
                    </div>
                    <div class="act-right">
                        <span class="adash-badge <?php echo $sc; ?>"><?php echo ucfirst($req['status']); ?></span>
                        <div class="act-date mt-1"><?php echo formatDate($req['created_at'], 'M d'); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="adash-card" style="margin-bottom:0;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-plus-circle"></i></span>
                        Recently Added Items
                    </div>
                    <a href="inventory.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> All</a>
                </div>
                <?php foreach ($recent_inventory as $item):
                    $ic = getCampus($item['campus_id']);
                    $cat_icons = ['Electronics'=>'fa-laptop','Furniture'=>'fa-chair','Equipment'=>'fa-tools','Office'=>'fa-briefcase'];
                    $iconf = $cat_icons[$item['category']] ?? 'fa-box';
                    $status_class = $item['status'] === 'available' ? 'b-green' : ($item['status'] === 'borrowed' ? 'b-amber' : 'b-blue');
                ?>
                <div class="act-item">
                    <div class="act-avatar"><i class="fas <?php echo $iconf; ?>"></i></div>
                    <div class="act-body">
                        <div class="act-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="act-sub"><?php echo htmlspecialchars($ic['name']); ?> &bull; <?php echo htmlspecialchars($item['category']); ?></div>
                    </div>
                    <div class="act-right">
                        <span class="adash-badge <?php echo $status_class; ?>"><?php echo ucfirst($item['status']); ?></span>
                        <div class="act-date mt-1"><?php echo formatDate($item['created_at'], 'M d'); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- Campus Detail Modal -->
<style>
.campus-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 999;
    animation: fadeIn .3s ease;
}

.campus-modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.campus-modal {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 700px;
    width: 90%;
    max-height: 85vh;
    overflow-y: auto;
    animation: slideUp .4s cubic-bezier(.4, 0, .2, 1);
    position: relative;
}

.campus-modal-header {
    position: sticky;
    top: 0;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    padding: 28px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.campus-modal-title h2 {
    font-size: 1.4rem;
    font-weight: 950;
    color: #0f172a;
    margin: 0 0 6px;
    letter-spacing: -.5px;
}

.campus-modal-title p {
    margin: 0;
    font-size: .85rem;
    color: #64748b;
    font-weight: 500;
}

.campus-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    background: rgba(139, 0, 0, .1);
    color: #8B0000;
    font-size: 1.2rem;
    cursor: pointer;
    transition: all .2s ease;
    flex-shrink: 0;
}

.campus-modal-close:hover {
    background: rgba(139, 0, 0, .2);
    transform: rotate(90deg);
}

.campus-modal-body {
    padding: 28px;
}

.campus-section {
    margin-bottom: 24px;
}

.campus-section:last-child {
    margin-bottom: 0;
}

.campus-section-title {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #999;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid #f0f0f0;
}

.campus-stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 14px;
}

.campus-stat-box {
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    text-align: center;
}

.campus-stat-val {
    font-size: 1.6rem;
    font-weight: 950;
    color: #0f172a;
    line-height: 1;
}

.campus-stat-lbl {
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: #94a3b8;
    margin-top: 4px;
}

.campus-info {
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 14px;
    font-size: .9rem;
    line-height: 1.6;
    color: #374151;
}

.campus-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.campus-list-item {
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px;
    font-size: .8rem;
    color: #374151;
    transition: all .2s ease;
}

.campus-list-item:hover {
    border-color: rgba(139, 0, 0, .2);
    background: #fff;
}

.campus-list-name {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 2px;
}

.campus-list-sub {
    font-size: .7rem;
    color: #94a3b8;
}

.campus-items-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.campus-item {
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 12px 14px;
    font-size: .85rem;
    color: #374151;
    transition: all .2s ease;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.campus-item:hover {
    border-color: rgba(139, 0, 0, .2);
    background: #fff;
}

.campus-item-text {
    flex: 1;
}

.campus-item-name {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
}

.campus-item-detail {
    font-size: .75rem;
    color: #94a3b8;
}

.campus-item-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 9px;
    border-radius: 6px;
    font-size: .65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
    flex-shrink: 0;
}

.campus-empty {
    padding: 20px;
    text-align: center;
    color: #94a3b8;
    font-size: .85rem;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}

@media(max-width: 600px) {
    .campus-modal {
        width: 95%;
        max-height: 90vh;
    }
    .campus-modal-header {
        padding: 20px;
    }
    .campus-modal-body {
        padding: 20px;
    }
    .campus-stat-row {
        grid-template-columns: repeat(2, 1fr);
    }
    .campus-list {
        grid-template-columns: 1fr;
    }
}
</style>

<div id="campusModal" class="campus-modal-overlay">
    <div class="campus-modal">
        <div class="campus-modal-header">
            <div class="campus-modal-title">
                <h2 id="modalCampusName">Campus Name</h2>
                <p id="modalCampusLocation">Location</p>
            </div>
            <button class="campus-modal-close" onclick="closeCampusModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="campus-modal-body">
            <!-- Info Section -->
            <div class="campus-section">
                <div class="campus-section-title">Overview</div>
                <div class="campus-info" id="modalCampusInfo"></div>
            </div>

            <!-- Borrowed Items Section -->
            <div class="campus-section">
                <div class="campus-section-title">Borrowed Items</div>
                <div id="modalBorrowedItems" class="campus-items-list"></div>
            </div>

            <!-- Maintenance Items Section -->
            <div class="campus-section">
                <div class="campus-section-title">Maintenance Items</div>
                <div id="modalMaintenanceItems" class="campus-items-list"></div>
            </div>

            <!-- Requested Items Section -->
            <div class="campus-section">
                <div class="campus-section-title">Requested Items</div>
                <div id="modalRequestedItems" class="campus-items-list"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const campusesData = <?php echo $modal_campuses_json; ?>;
const inventoryData = <?php echo $all_inventory_json; ?>;
const requestsData = <?php echo $all_requests_json; ?>;

function openCampusModal(campusId) {
    const campus = campusesData.find(c => c.id === campusId);
    if (!campus) return;

    // Update header
    document.getElementById('modalCampusName').textContent = campus.name;
    document.getElementById('modalCampusLocation').textContent = campus.location;
    
    // Update info
    document.getElementById('modalCampusInfo').textContent = campus.description;
    
    // Get campus inventory
    const campusInventory = inventoryData.filter(item => item.campus_id === campusId);
    
    // Filter borrowed items
    const borrowedItems = campusInventory.filter(item => item.status === 'borrowed');
    const borrowedHtml = borrowedItems.length > 0
        ? borrowedItems.map(item => `
            <div class="campus-item">
                <div class="campus-item-text">
                    <div class="campus-item-name">${item.item_name}</div>
                    <div class="campus-item-detail">Qty: ${item.quantity} • ${item.category}</div>
                </div>
                <div class="campus-item-badge" style="background:rgba(245,158,11,.12);color:#b45309;">Borrowed</div>
            </div>
        `).join('')
        : '<div class="campus-empty">No borrowed items</div>';
    document.getElementById('modalBorrowedItems').innerHTML = borrowedHtml;
    
    // Filter maintenance items
    const maintenanceItems = campusInventory.filter(item => item.status === 'maintenance');
    const maintenanceHtml = maintenanceItems.length > 0
        ? maintenanceItems.map(item => `
            <div class="campus-item">
                <div class="campus-item-text">
                    <div class="campus-item-name">${item.item_name}</div>
                    <div class="campus-item-detail">Qty: ${item.quantity} • ${item.category}</div>
                </div>
                <div class="campus-item-badge" style="background:rgba(59,130,246,.12);color:#1d4ed8;">Maintenance</div>
            </div>
        `).join('')
        : '<div class="campus-empty">No maintenance items</div>';
    document.getElementById('modalMaintenanceItems').innerHTML = maintenanceHtml;
    
    // Filter requested items for this campus
    const requestedItems = requestsData.filter(req => {
        const item = inventoryData.find(inv => inv.id === req.inventory_id);
        return item && item.campus_id === campusId;
    });
    const requestedHtml = requestedItems.length > 0
        ? requestedItems.map(req => {
            const item = inventoryData.find(inv => inv.id === req.inventory_id);
            const statusColors = {
                'pending': 'rgba(245,158,11,.12);color:#b45309;',
                'approved': 'rgba(34,197,94,.12);color:#15803d;',
                'disapproved': 'rgba(239,68,68,.12);color:#dc2626;'
            };
            return `
                <div class="campus-item">
                    <div class="campus-item-text">
                        <div class="campus-item-name">${item.item_name}</div>
                        <div class="campus-item-detail">Request #${req.request_number} • ${req.request_type}</div>
                    </div>
                    <div class="campus-item-badge" style="background:${statusColors[req.status] || 'rgba(0,0,0,.06);color:#64748b;'}">${req.status}</div>
                </div>
            `;
        }).join('')
        : '<div class="campus-empty">No requested items</div>';
    document.getElementById('modalRequestedItems').innerHTML = requestedHtml;

    // Show modal
    document.getElementById('campusModal').classList.add('active');
}

function closeCampusModal() {
    document.getElementById('campusModal').classList.remove('active');
}

// Close modal on outside click
document.getElementById('campusModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeCampusModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCampusModal();
    }
});

// ── Charts ──
document.addEventListener('DOMContentLoaded', function () {
    const chartDefaults = {
        plugins: { legend: { display: false }, tooltip: { callbacks: {} } },
        animation: { duration: 600 }
    };

    // Item Status Doughnut
    new Chart(document.getElementById('statusDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Available', 'Borrowed', 'Maintenance'],
            datasets: [{
                data: [<?php echo $computed_available; ?>, <?php echo $borrowed_items; ?>, <?php echo $maintenance_total; ?>],
                backgroundColor: ['#15803d', '#d97706', '#2563eb'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '72%',
            plugins: { legend: { display: false } },
            animation: { duration: 600 }
        }
    });

    // Request Status Doughnut
    new Chart(document.getElementById('requestDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Disapproved'],
            datasets: [{
                data: [<?php echo $pending; ?>, <?php echo $approved; ?>, <?php echo $disapproved; ?>],
                backgroundColor: ['#d97706', '#15803d', '#dc2626'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '72%',
            plugins: { legend: { display: false } },
            animation: { duration: 600 }
        }
    });

    // Campus Inventory Bar Chart
    new Chart(document.getElementById('campusBar'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($campus_names_js); ?>,
            datasets: [
                {
                    label: 'Total',
                    data: <?php echo json_encode($campus_totals_js); ?>,
                    backgroundColor: 'rgba(139,0,0,0.15)',
                    borderColor: '#8B0000',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Borrowed',
                    data: <?php echo json_encode($campus_borrowed_js); ?>,
                    backgroundColor: 'rgba(217,119,6,0.15)',
                    borderColor: '#d97706',
                    borderWidth: 1.5,
                    borderRadius: 4
                },
                {
                    label: 'Maintenance',
                    data: <?php echo json_encode($campus_maint_js); ?>,
                    backgroundColor: 'rgba(37,99,235,0.15)',
                    borderColor: '#2563eb',
                    borderWidth: 1.5,
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { size: 11, weight: '600' }, color: '#555', boxWidth: 10, padding: 16 }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#888' }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f0f0f0' },
                    ticks: { font: { size: 11 }, color: '#888', stepSize: 1 }
                }
            },
            animation: { duration: 600 }
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
