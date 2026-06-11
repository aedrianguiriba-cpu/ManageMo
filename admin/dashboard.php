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

<style>
/* =====================================================
   ADMIN DASHBOARD — PREMIUM REDESIGN
   ===================================================== */

/* Animations */
@keyframes slideUp   { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
@keyframes fadeIn    { from { opacity:0; } to { opacity:1; } }
@keyframes iconBounce{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-4px); } }
@keyframes iconWiggle{ 0%,100%{ transform:rotate(0deg); } 25%{ transform:rotate(-3deg); } 75%{ transform:rotate(3deg); } }
@keyframes pulseGlow { 0%,100%{ box-shadow:0 0 0 0 rgba(139,0,0,.4); } 50%{ box-shadow:0 0 0 12px rgba(139,0,0,0); } }

/* ── Page header ── */
.adash-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 36px; gap: 16px;
    animation: slideUp .5s ease;
    padding-bottom: 16px; border-bottom:2px solid rgba(139,0,0,.08);
}
.adash-header-left h1 {
    font-size: 2rem; font-weight: 950; color: #0f172a;
    margin: 0 0 6px; letter-spacing: -.8px;
}
.adash-header-left p { margin:0; font-size:.95rem; color:#64748b; font-weight:500; }
.adash-header-right {
    display: flex; align-items: center; gap: 12px;
    background: linear-gradient(135deg,#fff 0%,#f9fafb 100%);
    border: 1.5px solid rgba(139,0,0,.12);
    border-radius: 16px; padding: 12px 22px;
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
}
.adash-header-right .date-day {
    font-size: 1.5rem; font-weight: 950; color: #0f172a; line-height:1;
}
.adash-header-right .date-rest { font-size:.78rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }

/* ── KPI grid ── */
.adash-kpi-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px; margin-bottom: 32px;
}
@media(max-width:1400px){ .adash-kpi-grid{ grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); } }
@media(max-width:1000px){ .adash-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:600px) { .adash-kpi-grid{ grid-template-columns:1fr; } }

.adash-kpi {
    background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
    border-radius: 24px;
    border: 1.5px solid rgba(0,0,0,.08);
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    padding: 26px 24px 24px;
    display: flex; align-items: flex-start; gap: 18px;
    transition: all .3s cubic-bezier(.4, 0, .2, 1);
    animation: slideUp .5s ease both;
    position: relative; overflow: hidden;
}
.adash-kpi::before {
    content:''; position:absolute; top:-50%; right:-50%; width:200px; height:200px;
    background: radial-gradient(circle, var(--kpi-accent, #8B0000) 0%, transparent 70%);
    opacity: .06; border-radius:50%; transition: all .4s ease;
}
.adash-kpi:nth-child(1){ animation-delay:.05s; }
.adash-kpi:nth-child(2){ animation-delay:.10s; }
.adash-kpi:nth-child(3){ animation-delay:.15s; }
.adash-kpi:nth-child(4){ animation-delay:.20s; }
.adash-kpi:hover { 
    transform: translateY(-8px); 
    box-shadow: 0 12px 32px rgba(0,0,0,.12);
    border-color: var(--kpi-accent, #8B0000);
}
.adash-kpi:hover::before {
    top:-30%; right:-30%; opacity:.12;
}

.adash-kpi-icon {
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
    color: var(--kpi-color, #8B0000);
}
.adash-kpi-body { flex: 1; min-width: 0; }
.adash-kpi-val  { font-size: 2.4rem; font-weight: 950; color: #0f172a; line-height:1; letter-spacing:-1px; }
.adash-kpi-label{ font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-top:8px; }
.adash-kpi-sub  { font-size:.8rem; color:#cbd5e1; margin-top:6px; font-weight:500; }
.adash-kpi-sub strong { color: var(--kpi-color, #8B0000); font-weight:750; }

/* ── Generic card ── */
.adash-card {
    background: #fff; border-radius: 24px;
    border: 1.5px solid rgba(0,0,0,.08);
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    padding: 28px; margin-bottom: 24px;
    transition: all .3s cubic-bezier(.4, 0, .2, 1);
    animation: slideUp .5s ease both;
    position: relative; overflow: hidden;
}
.adash-card::before {
    content:''; position:absolute; top:0; right:0; width:1px; height:100%;
    background: linear-gradient(to bottom, rgba(139,0,0,.2), transparent);
}
.adash-card:hover { 
    box-shadow: 0 8px 32px rgba(0,0,0,.12);
    border-color: rgba(139,0,0,.2);
    transform: translateY(-2px);
}
.adash-card-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px; gap: 12px; padding-bottom: 16px;
    border-bottom: 1.5px solid #f1f5f9;
}
.adash-card-title {
    display: flex; align-items: center; gap: 14px;
    font-size: 1.05rem; font-weight: 800; color: #0f172a; letter-spacing:-.3px;
}
.adash-card-icon {
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; color: #8B0000; flex-shrink:0;
}

/* ── Table ── */
.adash-table { width:100%; border-collapse:collapse; }
.adash-table th {
    padding: 12px 16px; font-size:.7rem; font-weight:750;
    text-transform:uppercase; letter-spacing:.6px; color:#94a3b8;
    border-bottom: 2px solid #f1f5f9; background:#fafbfc;
    white-space: nowrap;
}
.adash-table td {
    padding: 14px 16px; font-size:.9rem; color:#374151;
    border-bottom: 1px solid #f1f5f9; vertical-align:middle;
    transition: all .2s ease;
}
.adash-table tr:last-child td { border-bottom:none; }
.adash-table tbody tr { 
    transition: all .2s ease;
}
.adash-table tbody tr:nth-child(odd) { background:#fafbfc; }
.adash-table tbody tr:hover { 
    background: linear-gradient(90deg, rgba(139,0,0,.04) 0%, transparent 100%);
}
.adash-table tbody tr:hover td {
    color: #0f172a;
}

/* ── Badges ── */
.adash-badge {
    display:inline-flex; align-items:center; gap:6px;
    padding:6px 13px; border-radius:8px;
    font-size:.73rem; font-weight:750; text-transform:uppercase; letter-spacing:.4px;
    border: 1.2px solid;
    transition: all .2s ease;
}
.adash-badge i { font-size:.65rem; }
.b-green  { background:rgba(34,197,94,.12);  color:#15803d; border-color:rgba(34,197,94,.3); }
.b-amber  { background:rgba(245,158,11,.12); color:#b45309; border-color:rgba(245,158,11,.3); }
.b-red    { background:rgba(239,68,68,.12);  color:#dc2626; border-color:rgba(239,68,68,.3); }
.b-blue   { background:rgba(59,130,246,.12); color:#1d4ed8; border-color:rgba(59,130,246,.3); }
.b-gray   { background:rgba(0,0,0,.08);      color:#64748b; border-color:rgba(0,0,0,.12); }

/* ── Request summary bars ── */
.req-stat-item {
    display:flex; align-items:center; gap:16px;
    padding:16px 18px; border-radius:14px; background:linear-gradient(135deg,#fafbfc,#f9fafb);
    border: 1.2px solid #f1f5f9; margin-bottom:12px; 
    transition: all .3s cubic-bezier(.4, 0, .2, 1);
    position:relative; overflow:hidden;
}
.req-stat-item::before {
    content:''; position:absolute; left:0; top:0; bottom:0; width:3px;
    background: var(--dot-color, #8B0000); transition: width .3s ease;
}
.req-stat-item:last-child { margin-bottom:0; }
.req-stat-item:hover { 
    background: linear-gradient(135deg,#fff,#f9fafb);
    border-color: var(--dot-color, #8B0000);
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.req-stat-item:hover::before { width:6px; }
.req-stat-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; box-shadow: 0 0 8px currentColor; }
.req-stat-label { flex:1; font-size:.9rem; font-weight:600; color:#374151; }
.req-stat-count { font-size:1.35rem; font-weight:950; color:#0f172a; }

/* ── Quick action buttons ── */
.qa-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.qa-btn {
    display:flex; align-items:center; gap:14px; padding:16px 18px;
    border-radius:16px; border:1.5px solid #f1f5f9; 
    background: linear-gradient(135deg,#fff,#f9fafb);
    text-decoration:none; color:#374151; font-size:.9rem; font-weight:600;
    transition: all .3s cubic-bezier(.4, 0, .2, 1); 
    position:relative; overflow:hidden;
}
.qa-btn::before {
    content:''; position:absolute; inset:0;
    background: radial-gradient(circle at var(--x, 0) var(--y, 0), rgba(139,0,0,.1) 0%, transparent 50%);
    opacity:0; transition: opacity .3s ease;
}
.qa-btn::after { 
    content:'\f054'; font-family:'Font Awesome 6 Free'; font-weight:900;
    position:absolute; right:18px; font-size:.7rem; color:#cbd5e1; 
    transition: transform .3s cubic-bezier(.4, 0, .2, 1), color .3s ease;
}
.qa-btn:hover { 
    border-color:rgba(139,0,0,.25); 
    background: linear-gradient(135deg,#fff,#fef9f9);
    color:#0f172a;
    box-shadow: 0 8px 24px rgba(139,0,0,.12); 
    transform:translateY(-4px); 
}
.qa-btn:hover::before { opacity:1; }
.qa-btn:hover::after { color:#8B0000; transform:translateX(4px); }
.qa-btn-icon { 
    width:42px; height:42px; border-radius:12px;
    display:flex; align-items:center; justify-content:center; 
    font-size:.95rem; flex-shrink:0;
    transition: all .3s ease;
}
.qa-btn:hover .qa-btn-icon { transform: scale(1.1) rotate(5deg); }

/* ── View-all link ── */
.adash-viewall {
    display:inline-flex; align-items:center; gap:7px;
    font-size:.82rem; font-weight:750; color:#8B0000; text-decoration:none;
    padding:8px 14px; border-radius:10px; border:1.5px solid rgba(139,0,0,.2);
    transition: all .3s ease;
    position: relative; overflow:hidden;
}
.adash-viewall::before {
    content:''; position:absolute; inset:0;
    background: linear-gradient(90deg,transparent,rgba(139,0,0,.1),transparent);
    transform: translateX(-100%); transition: transform .3s ease;
}
.adash-viewall:hover { 
    background:rgba(139,0,0,.08); 
    color:#8B0000;
    border-color: rgba(139,0,0,.4);
    box-shadow: 0 4px 12px rgba(139,0,0,.1);
}
.adash-viewall:hover::before { transform: translateX(100%); }

/* ── Activity item (recent lists) ── */
.act-item {
    display:flex; align-items:center; gap:14px;
    padding:14px 0; border-bottom:1px solid #f1f5f9;
    transition: all .2s ease;
}
.act-item:hover { 
    background: rgba(139,0,0,.02);
    padding-left: 8px;
    padding-right: -8px;
}
.act-item:last-child { border-bottom:none; padding-bottom:0; }
.act-avatar {
    width:42px; height:42px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:.8rem; font-weight:850; color:#fff;
    background: linear-gradient(135deg,#8B0000,#b91c1c);
    box-shadow: 0 4px 12px rgba(139,0,0,.25);
    transition: all .3s ease;
}
.act-item:hover .act-avatar { transform: scale(1.08); box-shadow: 0 6px 16px rgba(139,0,0,.3); }
.act-body { flex:1; min-width:0; }
.act-name { font-size:.9rem; font-weight:750; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.act-sub  { font-size:.78rem; color:#94a3b8; margin-top:2px; }
.act-right{ text-align:right; flex-shrink:0; }
.act-date { font-size:.76rem; color:#cbd5e1; font-weight:700; white-space:nowrap; }

@media(max-width:768px){
    .adash { padding:24px 18px 60px; }
    .adash-header { flex-direction:column; align-items:flex-start; border:none; padding-bottom:0; margin-bottom:24px; }
    .qa-grid { grid-template-columns:1fr; }
    .adash-kpi-grid { gap:12px; }
    .adash-kpi { padding:20px; gap:14px; }
}
</style>

<div class="main-wrapper">

    <!-- ── KPI Cards ── -->
    <div class="adash-kpi-grid">
        <div class="adash-kpi" style="--kpi-accent:#8B0000;--kpi-color:#b91c1c;">
            <div class="adash-kpi-icon"><i class="fas fa-warehouse"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $total_items; ?></div>
                <div class="adash-kpi-label">Total Items</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-accent:#22c55e;--kpi-color:#15c649;">
            <div class="adash-kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $available_items; ?></div>
                <div class="adash-kpi-label">Available</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-accent:#f59e0b;--kpi-color:#d97706;">
            <div class="adash-kpi-icon"><i class="fas fa-share-alt"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $borrowed_items; ?></div>
                <div class="adash-kpi-label">Borrowed</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-accent:#3b82f6;--kpi-color:#2563eb;">
            <div class="adash-kpi-icon"><i class="fas fa-tools"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $total_items - $available_items - $borrowed_items; ?></div>
                <div class="adash-kpi-label">Maintenance</div>
            </div>
        </div>
        <div class="adash-kpi" style="--kpi-accent:#8b5cf6;--kpi-color:#7c3aed;">
            <div class="adash-kpi-icon"><i class="fas fa-user-check"></i></div>
            <div class="adash-kpi-body">
                <div class="adash-kpi-val"><?php echo $total_owned_items; ?></div>
                <div class="adash-kpi-label">User-Owned Items</div>
            </div>
        </div>
    </div>

    <!-- ── Campus Summary ── -->
    <div class="adash-card" style="animation-delay:.05s;">
        <div class="adash-card-head">
            <div class="adash-card-title">
                <span class="adash-card-icon"><i class="fas fa-building"></i></span>
                Campus Inventory Summary
            </div>
            <a href="inventory-campus.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> View All</a>
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
                    <?php foreach ($campus_stats as $campus):
                        $util = $campus['stats']['total'] > 0 ? round(($campus['stats']['borrowed'] / $campus['stats']['total']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($campus['name']); ?></div>
                        </td>
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

    <!-- ── Request Summary + Quick Actions ── -->
    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="adash-card h-100" style="margin-bottom:0;animation-delay:.10s;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-tasks"></i></span>
                        Request Summary
                    </div>
                    <a href="requests.php" class="adash-viewall"><i class="fas fa-arrow-right"></i> Manage</a>
                </div>
                <div>
                    <div class="req-stat-item" style="--dot-color:#b45309;">
                        <div class="req-stat-dot" style="background:#b45309;"></div>
                        <span class="req-stat-label"><i class="fas fa-hourglass-half me-2" style="color:#b45309;"></i>Pending Review</span>
                        <span class="req-stat-count"><?php echo $pending; ?></span>
                    </div>
                    <div class="req-stat-item" style="--dot-color:#15803d;">
                        <div class="req-stat-dot" style="background:#15803d;"></div>
                        <span class="req-stat-label"><i class="fas fa-check-circle me-2" style="color:#15803d;"></i>Approved</span>
                        <span class="req-stat-count"><?php echo $approved; ?></span>
                    </div>
                    <div class="req-stat-item" style="--dot-color:#dc2626;">
                        <div class="req-stat-dot" style="background:#dc2626;"></div>
                        <span class="req-stat-label"><i class="fas fa-times-circle me-2" style="color:#dc2626;"></i>Disapproved</span>
                        <span class="req-stat-count"><?php echo $disapproved; ?></span>
                    </div>
                </div>
                <a href="requests.php" class="adash-viewall mt-3 w-100 justify-content-center d-flex"
                   style="background:linear-gradient(135deg,#8B0000,#b91c1c);color:#fff;border-color:transparent;padding:13px 18px;border-radius:14px;font-size:.88rem;box-shadow:0 4px 12px rgba(139,0,0,.25);">
                    <i class="fas fa-list-check me-2"></i> Review All Requests
                </a>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="adash-card h-100" style="margin-bottom:0;animation-delay:.15s;">
                <div class="adash-card-head">
                    <div class="adash-card-title">
                        <span class="adash-card-icon"><i class="fas fa-bolt"></i></span>
                        Quick Actions
                    </div>
                </div>
                <div class="qa-grid">
                    <a href="inventory.php" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(139,0,0,.15),rgba(139,0,0,.08));color:#8B0000;"><i class="fas fa-warehouse"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">Manage Inventory</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">View all items</div></div>
                    </a>
                    <a href="inventory.php?action=add" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(34,197,94,.15),rgba(34,197,94,.08));color:#15803d;"><i class="fas fa-plus-circle"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">Add New Item</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">Register asset</div></div>
                    </a>
                    <a href="requests.php" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(245,158,11,.15),rgba(245,158,11,.08));color:#b45309;"><i class="fas fa-clipboard-check"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">Review Requests</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;"><?php echo $pending; ?> pending</div></div>
                    </a>
                    <a href="analytics.php" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(59,130,246,.15),rgba(59,130,246,.08));color:#1d4ed8;"><i class="fas fa-chart-bar"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">Analytics</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">Reports &amp; insights</div></div>
                    </a>
                    <a href="inventory-campus.php" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(168,85,247,.15),rgba(168,85,247,.08));color:#7c3aed;"><i class="fas fa-map-marked-alt"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">By Campus</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">Campus breakdown</div></div>
                    </a>
                    <a href="settings.php" class="qa-btn">
                        <div class="qa-btn-icon" style="background:linear-gradient(135deg,rgba(100,116,139,.15),rgba(100,116,139,.08));color:#64748b;"><i class="fas fa-cog"></i></div>
                        <div><div style="font-weight:700;font-size:.88rem;color:#0f172a;">Settings</div><div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">System config</div></div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Recent Requests + Recent Items ── -->
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="adash-card" style="margin-bottom:0;animation-delay:.20s;">
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
                    $initials = strtoupper(substr($req['full_name'], 0, 1));
                    $colors = ['#8B0000','#1d4ed8','#15803d','#b45309','#7c3aed'];
                    $col = $colors[crc32($req['user_id']) % count($colors)];
                ?>
                <div class="act-item">
                    <div class="act-avatar" style="background:<?php echo $col; ?>;"><?php echo $initials; ?></div>
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
            <div class="adash-card" style="margin-bottom:0;animation-delay:.25s;">
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
                    <div class="act-avatar" style="background:linear-gradient(135deg,#64748b,#475569);">
                        <i class="fas <?php echo $iconf; ?>" style="font-size:.85rem;"></i>
                    </div>
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
    background: linear-gradient(135deg, #fff 0%, #f9fafb 100%);
    border-bottom: 1.5px solid #f1f5f9;
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
    font-size: .85rem;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #94a3b8;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid #f1f5f9;
}

.campus-stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.campus-stat-box {
    background: linear-gradient(135deg, #fafbfc, #f9fafb);
    border: 1.2px solid #f1f5f9;
    border-radius: 12px;
    padding: 14px;
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
    background: linear-gradient(135deg, #fafbfc, #f9fafb);
    border: 1.2px solid #f1f5f9;
    border-radius: 12px;
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
    background: linear-gradient(135deg, #fafbfc, #f9fafb);
    border: 1.2px solid #f1f5f9;
    border-radius: 10px;
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
    background: linear-gradient(135deg, #fafbfc, #f9fafb);
    border: 1.2px solid #f1f5f9;
    border-radius: 10px;
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
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
