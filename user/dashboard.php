<?php
$page_title = 'User Dashboard';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();
$user_id = $current_user['id'];
$campus_id = $current_user['campus_id'];

// Get campus information
$campus = getCampus($campus_id);

// Get all inventory for user's campus
$all_inventory = getInventory();
$campus_inventory = filterByColumn($all_inventory, 'campus_id', $campus_id);

// Calculate inventory stats
$inventory_result = [
    'total' => count($campus_inventory),
    'available' => count(filterByColumn($campus_inventory, 'status', 'available')),
    'borrowed' => count(filterByColumn($campus_inventory, 'status', 'borrowed')),
];

// Get user's requests
$all_requests = getRequests();
$user_requests = filterByColumn($all_requests, 'user_id', $user_id);

// Calculate request stats
$pending_requests = filterByColumn($user_requests, 'status', 'pending');
$approved_requests = filterByColumn($user_requests, 'status', 'approved');
$disapproved_requests = filterByColumn($user_requests, 'status', 'disapproved');

$requests_result = [
    'total' => count($user_requests),
    'pending' => count($pending_requests),
    'approved' => count($approved_requests),
    'disapproved' => count($disapproved_requests),
];

// Get active borrow records
$all_borrow = getBorrowRecords();
$user_borrows = filterByColumns($all_borrow, ['user_id' => $user_id, 'status' => 'active']);

$borrow_result = [
    'active' => count($user_borrows),
];

// Get recent requests (limit 5, sorted by date DESC)
$recent_requests = [];
foreach ($user_requests as $req) {
    // Find inventory item
    $item = null;
    foreach ($all_inventory as $inv) {
        if ($inv['id'] == $req['inventory_id']) {
            $item = $inv;
            break;
        }
    }
    $recent_requests[] = array_merge($req, ['item_name' => $item['item_name'] ?? 'Unknown Item']);
}

// Sort by created_at descending
usort($recent_requests, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
$recent_requests = array_slice($recent_requests, 0, 5);

// Get recent inventory items for this campus
$recent_inventory = [];
foreach ($all_inventory as $item) {
    if ($item['campus_id'] == $campus_id) {
        $recent_inventory[] = $item;
    }
}
// Sort by created_at descending
usort($recent_inventory, function($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
$recent_inventory = array_slice($recent_inventory, 0, 5);

// Get user's owned items
$all_owned_items = getUserOwnedItems();
$user_owned_items = filterByColumn($all_owned_items, 'user_id', $user_id);
$owned_items_count = count($user_owned_items);
$owned_items_total = array_reduce($user_owned_items, function($carry, $item) {
    return $carry + $item['quantity'];
}, 0);

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper ud-page">
<?php displayMessage(); ?>

<div class="container-fluid">

    <!-- Welcome Banner -->
    <div class="ud-welcome-banner mb-4">
        <div class="ud-welcome-left">
            <div class="ud-welcome-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div>
                <h4 class="ud-welcome-name">Welcome back, <?php echo htmlspecialchars(explode(' ', $current_user['full_name'])[0]); ?>!</h4>
                <p class="ud-welcome-sub">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    <?php echo htmlspecialchars($campus['name'] ?? 'Unknown Campus'); ?>
                    &nbsp;&middot;&nbsp;
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('F j, Y'); ?>
                </p>
            </div>
        </div>
        <a href="requests.php" class="ud-new-request-btn">
            <span class="ud-new-request-icon"><i class="fas fa-plus"></i></span>
            <span>New Request</span>
        </a>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4 align-items-stretch">
        <div class="col min-width-0">
            <div class="ud-stat-card ud-stat-blue h-100">
                <div class="ud-stat-icon"><i class="fas fa-boxes"></i></div>
                <div class="ud-stat-value"><?php echo $inventory_result['total']; ?></div>
                <div class="ud-stat-label">Campus Items</div>
            </div>
        </div>
        <div class="col min-width-0">
            <div class="ud-stat-card ud-stat-green h-100">
                <div class="ud-stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="ud-stat-value"><?php echo $inventory_result['available']; ?></div>
                <div class="ud-stat-label">Available</div>
            </div>
        </div>
        <div class="col min-width-0">
            <div class="ud-stat-card ud-stat-orange h-100">
                <div class="ud-stat-icon"><i class="fas fa-hand-holding"></i></div>
                <div class="ud-stat-value"><?php echo $borrow_result['active']; ?></div>
                <div class="ud-stat-label">Active Borrows</div>
            </div>
        </div>
        <div class="col min-width-0">
            <div class="ud-stat-card ud-stat-red h-100">
                <div class="ud-stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="ud-stat-value"><?php echo $requests_result['pending']; ?></div>
                <div class="ud-stat-label">Pending</div>
            </div>
        </div>
        <div class="col min-width-0">
            <div class="ud-stat-card h-100" style="--ud-kpi-color:#7c3aed;">
                <div class="ud-stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="ud-stat-value"><?php echo $owned_items_count; ?></div>
                <div class="ud-stat-label">My Owned Items</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="ud-card h-100">
                <div class="ud-card-header">
                    <i class="fas fa-bolt ud-card-icon" style="color:rgba(139,0,0,0.80);"></i>
                    <span>Quick Actions</span>
                </div>
                <div class="ud-card-body">
                    <a href="inventory.php" class="ud-action-btn">
                        <span class="ud-action-icon" style="color:#1a73e8;"><i class="fas fa-warehouse"></i></span>
                        <span class="ud-action-text">
                            <strong>Browse Inventory</strong>
                            <small>View available campus items</small>
                        </span>
                        <i class="fas fa-chevron-right ud-action-arrow"></i>
                    </a>
                    <a href="requests.php" class="ud-action-btn">
                        <span class="ud-action-icon" style="color:#34a853;"><i class="fas fa-paper-plane"></i></span>
                        <span class="ud-action-text">
                            <strong>Submit Request</strong>
                            <small>Borrow, service, or item request</small>
                        </span>
                        <i class="fas fa-chevron-right ud-action-arrow"></i>
                    </a>
                    <a href="borrow-records.php" class="ud-action-btn">
                        <span class="ud-action-icon" style="color:#f9ab00;"><i class="fas fa-history"></i></span>
                        <span class="ud-action-text">
                            <strong>Borrow Records</strong>
                            <small>Check your borrowing history</small>
                        </span>
                        <i class="fas fa-chevron-right ud-action-arrow"></i>
                    </a>
                    <a href="settings.php" class="ud-action-btn">
                        <span class="ud-action-icon" style="color:#e91e63;"><i class="fas fa-cog"></i></span>
                        <span class="ud-action-text">
                            <strong>Settings</strong>
                            <small>Manage your profile</small>
                        </span>
                        <i class="fas fa-chevron-right ud-action-arrow"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Request Summary -->
        <div class="col-lg-4">
            <div class="ud-card h-100">
                <div class="ud-card-header">
                    <i class="fas fa-chart-pie ud-card-icon" style="color:rgba(139,0,0,0.80);"></i>
                    <span>Request Summary</span>
                </div>
                <div class="ud-card-body">
                    <div class="ud-summary-row">
                        <span class="ud-summary-label"><i class="fas fa-list-ul me-2 text-muted"></i>Total Submitted</span>
                        <span class="ud-summary-val"><?php echo $requests_result['total']; ?></span>
                    </div>
                    <div class="ud-summary-row">
                        <span class="ud-summary-label"><i class="fas fa-clock me-2" style="color:#f9ab00;"></i>Pending</span>
                        <span class="ud-summary-badge" style="background:#fef3c7;color:#92400e;"><?php echo $requests_result['pending']; ?></span>
                    </div>
                    <div class="ud-summary-row">
                        <span class="ud-summary-label"><i class="fas fa-check-circle me-2" style="color:#34a853;"></i>Approved</span>
                        <span class="ud-summary-badge" style="background:#d1fae5;color:#065f46;"><?php echo $requests_result['approved']; ?></span>
                    </div>
                    <div class="ud-summary-row">
                        <span class="ud-summary-label"><i class="fas fa-times-circle me-2" style="color:#e53e3e;"></i>Disapproved</span>
                        <span class="ud-summary-badge" style="background:#fee2e2;color:#991b1b;"><?php echo $requests_result['disapproved']; ?></span>
                    </div>
                    <?php
                    $total = $requests_result['total'];
                    $approved_pct = $total > 0 ? round($requests_result['approved'] / $total * 100) : 0;
                    $pending_pct  = $total > 0 ? round($requests_result['pending']  / $total * 100) : 0;
                    $denied_pct   = $total > 0 ? round($requests_result['disapproved'] / $total * 100) : 0;
                    ?>
                    <?php if ($total > 0): ?>
                    <div class="ud-progress-bar-wrap mt-3">
                        <div class="ud-progress-bar">
                            <div class="ud-progress-seg" style="width:<?php echo $approved_pct; ?>%;background:#34a853;" title="Approved <?php echo $approved_pct; ?>%"></div>
                            <div class="ud-progress-seg" style="width:<?php echo $pending_pct; ?>%;background:#f9ab00;" title="Pending <?php echo $pending_pct; ?>%"></div>
                            <div class="ud-progress-seg" style="width:<?php echo $denied_pct; ?>%;background:#e53e3e;" title="Disapproved <?php echo $denied_pct; ?>%"></div>
                        </div>
                        <div class="ud-progress-legend">
                            <span><span class="ud-dot" style="background:#34a853;"></span>Approved</span>
                            <span><span class="ud-dot" style="background:#f9ab00;"></span>Pending</span>
                            <span><span class="ud-dot" style="background:#e53e3e;"></span>Denied</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Campus Inventory Breakdown -->
        <div class="col-lg-4">
            <div class="ud-card h-100">
                <div class="ud-card-header">
                    <i class="fas fa-building ud-card-icon" style="color:rgba(139,0,0,0.80);"></i>
                    <span>Campus Inventory</span>
                </div>
                <div class="ud-card-body">
                    <?php
                    // Count items with requests
                    $requested_inv_ids = array_unique(array_column($all_requests, 'inventory_id'));
                    $inv_requested = count(array_filter($campus_inventory, fn($i) => in_array($i['id'], $requested_inv_ids)));
                    
                    $inv_borrowed    = count(filterByColumn($campus_inventory, 'status', 'borrowed'));
                    $inv_maintenance = count(filterByColumn($campus_inventory, 'status', 'maintenance'));
                    $status_rows = [
                        ['label' => 'Available',    'count' => $inventory_result['available'], 'color' => '#34a853', 'bg' => '#d1fae5', 'icon' => 'fa-check-circle'],
                        ['label' => 'Borrowed',     'count' => $inv_borrowed,                 'color' => '#1a73e8', 'bg' => '#e8f0fe', 'icon' => 'fa-hand-holding'],
                        ['label' => 'Requested',    'count' => $inv_requested,                'color' => '#ea8c55', 'bg' => '#fef3c7', 'icon' => 'fa-clipboard-list'],
                        ['label' => 'Maintenance',  'count' => $inv_maintenance,              'color' => '#f9ab00', 'bg' => '#fef3c7', 'icon' => 'fa-tools'],
                    ];
                    foreach ($status_rows as $row): ?>
                    <div class="ud-inv-row">
                        <span class="ud-inv-icon" style="color:<?php echo $row['color']; ?>;"><i class="fas <?php echo $row['icon']; ?>"></i></span>
                        <span class="ud-inv-label"><?php echo $row['label']; ?></span>
                        <div class="ud-inv-bar-wrap">
                            <div class="ud-inv-bar" style="width:<?php echo ($inventory_result['total'] > 0 ? round($row['count']/$inventory_result['total']*100) : 0); ?>%;background:<?php echo $row['color']; ?>;"></div>
                        </div>
                        <span class="ud-inv-count"><?php echo $row['count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="mt-2 text-center">
                        <a href="inventory.php" class="btn btn-sm" style="background:#f7f7f7;color:#374151;border:1px solid #e5e7eb;border-radius:6px;font-size:0.8rem;">
                            <i class="fas fa-eye me-1"></i> View Full Inventory
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Requests & Recent Inventory -->
    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="ud-card h-100">
                <div class="ud-card-header">
                    <i class="fas fa-clock ud-card-icon" style="color:rgba(139,0,0,0.80);"></i>
                    <span>Recent Requests</span>
                    <a href="requests.php" class="ud-card-link ms-auto">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="ud-card-body p-0">
                    <?php if (count($recent_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm ud-table mb-0">
                            <thead>
                                <tr>
                                    <th>Request #</th>
                                    <th>Type</th>
                                    <th>Urgency</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_requests as $req):
                                $status_styles = [
                                    'pending'     => 'background:#fef3c7;color:#92400e;',
                                    'approved'    => 'background:#d1fae5;color:#065f46;',
                                    'disapproved' => 'background:#fee2e2;color:#991b1b;',
                                    'delivered'   => 'background:#e8f0fe;color:#1558b0;',
                                ];
                                $urgency_styles = [
                                    'low'      => 'background:#f3f4f8;color:#6b7280;',
                                    'medium'   => 'background:#fef3c7;color:#92400e;',
                                    'high'     => 'background:#fee2e2;color:#991b1b;',
                                    'critical' => 'background:#7f1d1d;color:#fff;',
                                ];
                                $sstyle = $status_styles[$req['status']] ?? 'background:#f3f4f8;color:#374151;';
                                $ustyle = $urgency_styles[$req['urgency'] ?? 'low'] ?? 'background:#f3f4f8;color:#6b7280;';
                            ?>
                            <tr>
                                <td><code class="ud-code"><?php echo htmlspecialchars($req['request_number']); ?></code></td>
                                <td><?php echo ucfirst($req['request_type']); ?></td>
                                <td><span class="ud-pill" style="<?php echo $ustyle; ?>"><?php echo ucfirst($req['urgency'] ?? 'low'); ?></span></td>
                                <td><span class="ud-pill" style="<?php echo $sstyle; ?>"><?php echo ucfirst($req['status']); ?></span></td>
                                <td style="font-size:0.8rem;color:rgba(0,0,0,0.42)!important;"><?php echo formatDate($req['created_at'], 'M d, Y'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="ud-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No requests submitted yet</p>
                        <a href="requests.php" class="btn btn-sm btn-primary">Submit your first request</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ud-card h-100">
                <div class="ud-card-header">
                    <i class="fas fa-boxes ud-card-icon" style="color:rgba(139,0,0,0.80);"></i>
                    <span>Campus Items</span>
                    <a href="inventory.php" class="ud-card-link ms-auto">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="ud-card-body p-0">
                    <?php if (count($recent_inventory) > 0): ?>
                    <ul class="ud-item-list">
                        <?php foreach ($recent_inventory as $item):
                            $item_status_styles = [
                                'available'   => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                'borrowed'    => ['bg' => '#e8f0fe', 'color' => '#1558b0'],
                                'damaged'     => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                                'maintenance' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                            ];
                            $cat_icons = [
                                'Electronics' => 'fa-laptop',
                                'Furniture'   => 'fa-chair',
                                'Equipment'   => 'fa-tools',
                                'Supplies'    => 'fa-box',
                                'Appliances'  => 'fa-plug',
                                'Security'    => 'fa-shield-alt',
                            ];
                            $ist = $item_status_styles[$item['status']] ?? ['bg' => '#f3f4f8', 'color' => '#374151'];
                            $iicon = $cat_icons[$item['category']] ?? 'fa-cube';
                        ?>
                        <li class="ud-item-row">
                            <span class="ud-item-cat-icon"><i class="fas <?php echo $iicon; ?>"></i></span>
                            <span class="ud-item-info">
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <small><?php echo htmlspecialchars($item['category']); ?></small>
                            </span>
                            <span class="ud-pill" style="background:<?php echo $ist['bg']; ?>;color:<?php echo $ist['color']; ?>;"><?php echo ucfirst($item['status']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="ud-empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No inventory items found for your campus</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Owned Items -->
    <div class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="ud-card">
                <div class="ud-card-header">
                    <i class="fas fa-user-check ud-card-icon" style="color:#8b5cf6;"></i>
                    <span>My Owned Items</span>
                    <a href="inventory.php?tab=owned" class="ud-card-link ms-auto">View all <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="ud-card-body p-0">
                    <?php if (count($user_owned_items) > 0): ?>
                    <ul class="ud-item-list">
                        <?php foreach (array_slice($user_owned_items, 0, 5) as $item):
                            $cat_icons = [
                                'Electronics' => 'fa-laptop',
                                'Furniture'   => 'fa-chair',
                                'Equipment'   => 'fa-tools',
                                'Supplies'    => 'fa-box',
                                'Appliances'  => 'fa-plug',
                                'Security'    => 'fa-shield-alt',
                            ];
                            $condition_styles = [
                                'excellent' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                'good'      => ['bg' => '#bfdbfe', 'color' => '#1e40af'],
                                'fair'      => ['bg' => '#fef3c7', 'color' => '#92400e'],
                                'poor'      => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                            ];
                            $iicon = $cat_icons[$item['category']] ?? 'fa-cube';
                            $cstyle = $condition_styles[$item['condition']] ?? ['bg' => '#f3f4f8', 'color' => '#374151'];
                        ?>
                        <li class="ud-item-row">
                            <span class="ud-item-cat-icon"><i class="fas <?php echo $iicon; ?>" style="color:#8b5cf6;"></i></span>
                            <span class="ud-item-info">
                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                <small><?php echo htmlspecialchars($item['category']); ?> • Year: <?php echo $item['year_owned']; ?> • Qty: <?php echo $item['quantity']; ?></small>
                            </span>
                            <span class="ud-pill" style="background:<?php echo $cstyle['bg']; ?>;color:<?php echo $cstyle['color']; ?>;"><?php echo ucfirst($item['condition']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <div class="ud-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No owned items recorded</p>
                        <small style="color:rgba(0,0,0,0.50);">Contact your admin to record items you own or have owned</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<style>
/* ================================================
   USER DASHBOARD — FLAT/MINIMAL
   ================================================ */

.main-wrapper.ud-page {
    background: transparent;
    min-height: 100vh;
}

/* ---- Welcome Banner ---- */
.ud-welcome-banner {
    background: #8B0000;
    border: 1px solid #7a0000;
    border-radius: 8px;
    padding: 22px 26px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    color: #fff;
}
.ud-welcome-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.ud-welcome-avatar {
    width: 52px;
    height: 52px;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.30);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.ud-welcome-name {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0 0 4px;
    color: #ffffff !important;
}
.ud-welcome-sub {
    font-size: 0.82rem;
    margin: 0;
    color: rgba(255,255,255,0.82);
}
.ud-new-request-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(255,255,255,0.15);
    border: 1.5px solid rgba(255,255,255,0.35);
    color: #fff !important;
    border-radius: 6px;
    padding: 10px 22px 10px 10px;
    font-size: 0.88rem;
    font-weight: 700;
    text-decoration: none !important;
    white-space: nowrap;
    transition: background 0.15s, border-color 0.15s;
    letter-spacing: 0.2px;
}
.ud-new-request-icon {
    width: 32px;
    height: 32px;
    background: rgba(255,255,255,0.18);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.ud-new-request-btn:hover {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.50);
    color: #fff !important;
    text-decoration: none !important;
}

/* ---- Stat Cards ---- */
.ud-stat-card {
    border-radius: 8px;
    padding: 22px 20px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    position: relative;
    overflow: hidden;
    background: #fff;
    border: 1.5px solid var(--ud-kpi-color, #8B0000);
    color: #111;
    height: 100%;
    min-height: 130px;
    justify-content: space-between;
}
.ud-stat-blue   { --ud-kpi-color: #1a73e8; }
.ud-stat-green  { --ud-kpi-color: #34a853; }
.ud-stat-orange { --ud-kpi-color: #e65100; }
.ud-stat-red    { --ud-kpi-color: #c62828; }
.ud-stat-icon   { font-size: 1.5rem; color: var(--ud-kpi-color, #8B0000); }
.ud-stat-value  { font-size: 2.1rem; font-weight: 800; line-height: 1; color: #111; }
.ud-stat-label  { font-size: 0.78rem; font-weight: 500; color: #999; }

/* ---- Cards ---- */
.ud-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
}
.ud-card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 15px 18px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.93rem;
    font-weight: 700;
    color: #1a1d23;
}
.ud-card-icon { font-size: 1rem; }
.ud-card-link {
    font-size: 0.78rem;
    font-weight: 500;
    color: #8B0000;
    text-decoration: none;
}
.ud-card-link:hover { color: #7f0000; text-decoration: underline; }
.ud-card-body { padding: 14px 18px; }

/* ---- Quick Actions ---- */
.ud-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 8px;
    border-radius: 6px;
    text-decoration: none;
    color: #374151;
    transition: background 0.15s;
    border-bottom: 1px solid #e5e7eb;
}
.ud-action-btn:last-child { border-bottom: none; }
.ud-action-btn:hover {
    background: #f7f7f7;
    text-decoration: none;
    color: #111;
}
.ud-action-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}
.ud-action-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    font-size: 0.88rem;
    line-height: 1.3;
}
.ud-action-text strong { color: #111; }
.ud-action-text small  { color: #555; font-size: 0.75rem; }
.ud-action-arrow { color: #999; font-size: 0.75rem; }

/* ---- Summary rows ---- */
.ud-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.88rem;
}
.ud-summary-row:last-of-type { border-bottom: none; }
.ud-summary-label { color: #555; display: flex; align-items: center; }
.ud-summary-val   { font-weight: 700; font-size: 1rem; color: #111; }
.ud-summary-badge {
    font-weight: 700;
    font-size: 0.82rem;
    padding: 3px 10px;
    border-radius: 4px;
}

/* ---- Progress bar ---- */
.ud-progress-bar-wrap { margin-top: 6px; }
.ud-progress-bar {
    display: flex;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    background: #e5e7eb;
    gap: 2px;
}
.ud-progress-seg { border-radius: 4px; transition: width 0.4s; }
.ud-progress-legend {
    display: flex;
    gap: 12px;
    margin-top: 6px;
    font-size: 0.72rem;
    color: #999;
}
.ud-dot {
    display: inline-block;
    width: 8px; height: 8px;
    border-radius: 50%;
    margin-right: 3px;
}

/* ---- Inventory breakdown ---- */
.ud-inv-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}
.ud-inv-row:last-of-type { border-bottom: none; }
.ud-inv-icon {
    width: 24px; height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
    color: #555;
}
.ud-inv-label    { font-size: 0.84rem; color: #555; width: 90px; flex-shrink: 0; }
.ud-inv-bar-wrap { flex: 1; background: #e5e7eb; border-radius: 4px; height: 6px; overflow: hidden; }
.ud-inv-bar      { height: 100%; border-radius: 4px; transition: width 0.4s; min-width: 2px; }
.ud-inv-count    { font-weight: 700; font-size: 0.85rem; color: #111; width: 24px; text-align: right; flex-shrink: 0; }

/* ---- Table — override Bootstrap defaults ---- */
.ud-table,
.ud-table > thead,
.ud-table > tbody,
.ud-table > thead > tr > th,
.ud-table > tbody > tr > td,
.ud-table > tbody > tr {
    background: transparent !important;
    color: inherit;
    border-color: transparent;
}
.table-responsive { background: transparent !important; }
.ud-table thead tr {
    border-bottom: 1px solid #e5e7eb !important;
}
.ud-table thead th {
    font-size: 0.78rem;
    font-weight: 600;
    color: #999 !important;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    padding: 10px 14px;
    background: #f7f7f7 !important;
    border: none !important;
}
.ud-table tbody td {
    padding: 10px 14px;
    font-size: 0.86rem;
    border-bottom: 1px solid #e5e7eb !important;
    border-top: none !important;
    vertical-align: middle;
    color: #374151 !important;
    background: transparent !important;
}
.ud-table tbody tr:last-child td { border-bottom: none !important; }
.ud-table tbody tr:hover td { background: #f7f7f7 !important; }
.ud-code {
    background: #f7f7f7;
    color: #111;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 0.78rem;
    border: 1px solid #e5e7eb;
}
.ud-pill {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}
.ud-table .ud-pill,
.ud-summary-badge {
    border: 1px solid rgba(0,0,0,0.08);
}

/* ---- Item list ---- */
.ud-item-list { list-style: none; margin: 0; padding: 0; }
.ud-item-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 18px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.86rem;
}
.ud-item-row:last-child { border-bottom: none; }
.ud-item-row:hover { background: #f7f7f7; }
.ud-item-cat-icon {
    width: 28px; height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #555;
    font-size: 0.85rem;
    flex-shrink: 0;
}
.ud-item-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    line-height: 1.3;
    overflow: hidden;
}
.ud-item-info strong {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 0.87rem;
    color: #111;
}
.ud-item-info small { color: #555; font-size: 0.75rem; }

/* ---- Empty state ---- */
.ud-empty-state {
    text-align: center;
    padding: 32px 16px;
    color: #999;
}
.ud-empty-state i   { font-size: 2rem; margin-bottom: 8px; display: block; }
.ud-empty-state p   { font-size: 0.88rem; margin: 0 0 12px; }

/* view-inventory button inside card */
.ud-card .btn {
    background: #f7f7f7;
    color: #555;
    border: 1px solid #e5e7eb;
}
.ud-card .btn:hover {
    background: #e5e7eb;
    color: #111;
}

@media (max-width: 576px) {
    .ud-welcome-banner { flex-direction: column; align-items: flex-start; }
    .ud-new-request-btn { width: 100%; text-align: center; }
    .ud-stat-value { font-size: 1.6rem; }
}
</style>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
