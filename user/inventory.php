<?php
$page_title = 'Inventory';
require_once dirname(__DIR__) . '/config/functions.php';
require_once dirname(__DIR__) . '/lib/qrcode.php';

requireUser();

$current_user = getCurrentUser();
$campus_id = $current_user['campus_id'];
$current_tab = $_GET['tab'] ?? 'available';
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
// Get campus info
$campus = getCampus($campus_id);

// Build where clause
$where = "campus_id = '$campus_id'";
if ($search) {
    $search_safe = sanitizeInput($search);
    $where .= " AND (item_name LIKE '%$search_safe%' OR category LIKE '%$search_safe%' OR description LIKE '%$search_safe%')";
}
if ($category_filter) {
    $category_filter_safe = sanitizeInput($category_filter);
    $where .= " AND category = '$category_filter_safe'";
}
if ($status_filter) {
    $status_filter_safe = sanitizeInput($status_filter);
    $where .= " AND status = '$status_filter_safe'";
}

// Get all inventory for the user's campus
$all_campus_inventory = filterByColumn(getInventory(), 'campus_id', $campus_id);

// Stats
$inv_total     = count($all_campus_inventory);
$inv_borrowed  = count(filterByColumn($all_campus_inventory, 'status', 'borrowed'));
$inv_maint     = count(filterByColumn($all_campus_inventory, 'status', 'maintenance'));

// Count items with active requests
$all_requests = getRequests();
$requested_inv_ids = array_unique(array_column($all_requests, 'inventory_id'));
$inv_requested = count(array_filter($all_campus_inventory, fn($i) => in_array($i['id'], $requested_inv_ids)));

// Get user's owned items
$all_owned_items = getUserOwnedItems();
$user_owned_items = filterByColumn($all_owned_items, 'user_id', $current_user['id']);
$owned_items_count = count($user_owned_items);
$owned_items_total = array_reduce($user_owned_items, fn($sum, $item) => $sum + ($item['quantity'] ?? 1), 0);


// Get categories from the inventory
$categories = [];
foreach ($all_campus_inventory as $item) {
    if (!in_array($item['category'], $categories)) {
        $categories[] = $item['category'];
    }
}
sort($categories);

// Handle owned status filter - switch to owned tab
if ($status_filter === 'owned') {
    $current_tab = 'owned';
}

// Auto-filter by tab if not using custom status filter
if (!$status_filter && $current_tab === 'borrowed') {
    $status_filter = 'borrowed';
}

// Apply filters
$filtered_items = $all_campus_inventory;

if (!empty($status_filter) && $status_filter !== 'owned') {
    if ($status_filter === 'requested') {
        // Filter items with active requests
        $filtered_items = array_values(array_filter($filtered_items, fn($item) => in_array($item['id'], $requested_inv_ids)));
    } else {
        $filtered_items = filterByColumn($filtered_items, 'status', $status_filter);
    }
}

if (!empty($category_filter)) {
    $filtered_items = filterByColumn($filtered_items, 'category', $category_filter);
}
if (!empty($search)) {
    $s = strtolower($search);
    $filtered_items = array_values(array_filter($filtered_items, function($item) use ($s) {
        return strpos(strtolower($item['item_name']), $s) !== false ||
               strpos(strtolower($item['category']), $s) !== false;
    }));
}

// Sort by item name
usort($filtered_items, function($a, $b) {
    return strcmp($a['item_name'], $b['item_name']);
});

// Group into display groups, then paginate over groups
$grouped_inventory = groupInventoryItems($filtered_items);
$grouped_owned     = groupOwnedItems($user_owned_items);

$total       = count($grouped_inventory);
$total_pages = ceil($total / ITEMS_PER_PAGE);
$offset      = ($page - 1) * ITEMS_PER_PAGE;
$items       = array_slice($grouped_inventory, $offset, ITEMS_PER_PAGE);

// Pre-load borrow records once
$all_borrows = getBorrowRecords();

// Current user's active borrow inventory_ids — for quick group-level lookup
$user_borrowed_item_ids = [];
foreach ($all_borrows as $br) {
    if ((int)$br['user_id'] === (int)$current_user['id'] && $br['status'] === 'active') {
        $user_borrowed_item_ids[] = (int)$br['inventory_id'];
    }
}
?>

<style>
/* ===== INVENTORY PAGE ===== */
.inv-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.inv-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #1a1d23;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.inv-title-icon {
    width: 40px; height: 40px;
    background: #8B0000;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1rem;
    flex-shrink: 0;
}
.inv-campus-badge {
    font-size: 0.78rem;
    font-weight: 600;
    color: rgba(0,0,0,0.45);
    background: rgba(0,0,0,0.06);
    border-radius: 20px;
    padding: 4px 12px;
    border: 1px solid rgba(0,0,0,0.08);
}

/* Stats strip */
.inv-stats-strip {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.inv-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 700;
    background: #fff;
    border: 1px solid #e5e7eb;
    color: #111;
}
.inv-stat-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Filter card */
.inv-filter-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 18px 20px;
    margin-bottom: 22px;
}
.inv-filter-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(0,0,0,0.40);
    margin-bottom: 5px;
    display: block;
}
.inv-filter-card .form-control,
.inv-filter-card .form-select {
    border-radius: 6px !important;
    font-size: 0.87rem !important;
    height: 40px;
}
.inv-search-btn {
    height: 40px;
    background: #8B0000 !important;
    border: none !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    font-size: 0.87rem !important;
    color: #fff !important;
    transition: background 0.15s !important;
}
.inv-search-btn:hover {
    background: #7a0000 !important;
}
.inv-reset-btn {
    height: 40px;
    background: #f7f7f7 !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    font-size: 0.87rem !important;
    color: #555 !important;
}
.inv-reset-btn:hover {
    background: #e5e7eb !important;
    color: #111 !important;
}
.inv-results-count {
    font-size: 0.80rem;
    color: rgba(0,0,0,0.42);
    margin-top: 10px;
}

/* Item cards */
.inv-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    overflow: hidden;
    transition: border-color 0.15s;
    height: 100%;
    display: flex;
    flex-direction: column;
}
.inv-card:hover {
    border-color: rgba(139,0,0,0.25);
}
.inv-card-top {
    padding: 16px 16px 12px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.inv-card-icon {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    color: #8B0000;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.inv-card-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1a1d23;
    margin: 0 0 5px;
    line-height: 1.3;
}
.inv-card-category {
    font-size: 0.72rem;
    font-weight: 600;
    color: rgba(0,0,0,0.45);
    background: rgba(0,0,0,0.05);
    border-radius: 20px;
    padding: 2px 9px;
    display: inline-block;
}
.inv-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
    margin-top: 5px;
    white-space: nowrap;
}
.inv-status-available  { background: rgba(34,197,94,0.12);  color: #15803d; border: 1px solid rgba(34,197,94,0.22); }
.inv-status-borrowed   { background: rgba(245,158,11,0.12); color: #b45309; border: 1px solid rgba(245,158,11,0.22); }
.inv-status-maintenance{ background: rgba(59,130,246,0.12); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.22); }
.inv-status-damaged    { background: rgba(239,68,68,0.12);  color: #b91c1c; border: 1px solid rgba(239,68,68,0.22); }
.inv-status-retired    { background: rgba(107,114,128,0.12);color: #4b5563; border: 1px solid rgba(107,114,128,0.22); }

.inv-card-body {
    padding: 12px 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.inv-info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    font-size: 0.83rem;
}
.inv-info-row:last-of-type { border-bottom: none; }
.inv-info-icon {
    width: 18px; height: 18px;
    display: flex; align-items: center; justify-content: center;
    color: #999;
    font-size: 0.65rem;
    flex-shrink: 0;
}
.inv-info-label { color: rgba(0,0,0,0.40); font-weight: 600; width: 72px; flex-shrink: 0; }
.inv-info-val   { color: #374151; flex: 1; }
.inv-description {
    font-size: 0.78rem;
    color: rgba(0,0,0,0.42);
    margin-top: 6px;
    line-height: 1.45;
    flex: 1;
}

.inv-card-qr {
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(0,0,0,0.02);
    border-top: 1px solid rgba(0,0,0,0.05);
}
.inv-qr-img {
    width: 52px; height: 52px;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.08);
    background: #fff;
    padding: 2px;
    flex-shrink: 0;
}
.inv-qr-code {
    font-size: 0.70rem;
    color: rgba(0,0,0,0.38);
    font-family: monospace;
    word-break: break-all;
    line-height: 1.4;
}

.inv-card-footer {
    padding: 12px 16px;
    border-top: 1px solid rgba(0,0,0,0.06);
}
.inv-borrow-btn {
    width: 100%;
    padding: 9px 16px !important;
    border-radius: 6px !important;
    font-weight: 700 !important;
    font-size: 0.85rem !important;
    background: #8B0000 !important;
    border: none !important;
    color: #fff !important;
    transition: background 0.15s !important;
    text-decoration: none !important;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.inv-borrow-btn:hover {
    background: #7a0000 !important;
    color: #fff !important;
    text-decoration: none !important;
}
.inv-disabled-btn {
    width: 100%;
    padding: 9px 16px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.85rem;
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    color: #999;
    text-align: center;
    cursor: default;
    display: flex; align-items: center; justify-content: center; gap: 7px;
}
.inv-already-badge {
    font-size: 0.75rem;
    font-weight: 600;
    color: #b45309;
    background: rgba(245,158,11,0.10);
    border: 1px solid rgba(245,158,11,0.20);
    border-radius: 8px;
    padding: 5px 10px;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 8px;
}

/* Empty state */
.inv-empty {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #999;
}
.inv-empty-icon {
    width: 56px; height: 56px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem;
    margin: 0 auto 16px;
    color: #999;
}
.inv-empty h5 { color: rgba(0,0,0,0.45); font-size: 1rem; margin: 0 0 6px; }
.inv-empty p  { font-size: 0.85rem; margin: 0; }

/* Per-unit QR toggle */
.inv-qr-toggle-btn {
    background: none; border: none; padding: 0;
    font-size: 0.70rem; font-weight: 700;
    color: #8B0000; cursor: pointer; margin-top: 3px;
    text-decoration: underline dotted;
}
.inv-qr-toggle-btn:hover { color: #b91c1c; }

/* Mobile: stack inline 2-col detail grids */
@media(max-width:576px) {
    [style*="grid-template-columns: 1fr 1fr"],
    [style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<div class="container-fluid mt-4 pb-4">

    <!-- Stats Strip -->
    <div class="inv-stats-strip">
        <div class="inv-stat-pill">
            <span class="inv-stat-dot" style="background:#6b7280;"></span>
            <?php echo $inv_total; ?> Total
        </div>
        <div class="inv-stat-pill">
            <span class="inv-stat-dot" style="background:#f59e0b;"></span>
            <?php echo $inv_borrowed; ?> Borrowed
        </div>
        <div class="inv-stat-pill">
            <span class="inv-stat-dot" style="background:#22c55e;"></span>
            <?php echo $inv_requested; ?> Requested
        </div>
        <div class="inv-stat-pill">
            <span class="inv-stat-dot" style="background:#3b82f6;"></span>
            <?php echo $inv_maint; ?> Maintenance
        </div>
        <div class="inv-stat-pill">
            <span class="inv-stat-dot" style="background:#8b5cf6;"></span>
            <?php echo $owned_items_count; ?> Owned
        </div>
    </div>

    <!-- TAB NAVIGATION -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; border-bottom: 2px solid rgba(0,0,0,0.08); padding-bottom: 0;">
        <a href="inventory.php?tab=available" class="inv-tab-link <?php echo $current_tab === 'available' ? 'inv-tab-active' : ''; ?>" onclick="setInvTab('available'); return false;" style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-weight: 600; font-size: 0.9rem; color: rgba(0,0,0,0.50); border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; transition: all 0.2s;">
            <i class="fas fa-boxes-stacked"></i> Available
        </a>
        <a href="inventory.php?tab=borrowed" class="inv-tab-link <?php echo $current_tab === 'borrowed' ? 'inv-tab-active' : ''; ?>" onclick="setInvTab('borrowed'); return false;" style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-weight: 600; font-size: 0.9rem; color: rgba(0,0,0,0.50); border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; transition: all 0.2s;">
            <i class="fas fa-hand-holding"></i> Borrowed
        </a>
        <a href="inventory.php?tab=owned" class="inv-tab-link <?php echo $current_tab === 'owned' ? 'inv-tab-active' : ''; ?>" onclick="setInvTab('owned'); return false;" style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-weight: 600; font-size: 0.9rem; color: rgba(0,0,0,0.50); border-bottom: 3px solid transparent; cursor: pointer; text-decoration: none; transition: all 0.2s;">
            <i class="fas fa-user-check"></i> My Owned Items
        </a>
    </div>

    <style>
        .inv-tab-link {
            color: rgba(0,0,0,0.50) !important;
            border-bottom: 3px solid transparent !important;
        }
        .inv-tab-link:hover {
            color: rgba(0,0,0,0.70) !important;
        }
        .inv-tab-active {
            color: #8B0000 !important;
            border-bottom-color: #8B0000 !important;
        }
    </style>

    <!-- Filter Card (Available & Borrowed Tabs Only) -->
    <div class="inv-filter-card" id="inv-filter-section" style="display: <?php echo in_array($current_tab, ['available', 'borrowed']) ? 'block' : 'none'; ?>;">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
            <div class="col-md-4">
                <label class="inv-filter-label"><i class="fas fa-search me-1"></i>Search</label>
                <input type="text" class="form-control" name="search"
                       placeholder="Item name, category..."
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="inv-filter-label"><i class="fas fa-tag me-1"></i>Category</label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="inv-filter-label"><i class="fas fa-circle me-1"></i>Status</label>
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="available"   <?php echo $status_filter === 'available'    ? 'selected' : ''; ?>>Available</option>
                    <option value="borrowed"    <?php echo $status_filter === 'borrowed'     ? 'selected' : ''; ?>>Borrowed</option>
                    <option value="requested"   <?php echo $status_filter === 'requested'    ? 'selected' : ''; ?>>Requested</option>
                    <option value="maintenance" <?php echo $status_filter === 'maintenance'  ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="owned"       <?php echo $status_filter === 'owned'        ? 'selected' : ''; ?>>My Owned Items</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn inv-search-btn flex-fill">
                    <i class="fas fa-search me-1"></i> Search
                </button>
                <a href="inventory.php?tab=<?php echo htmlspecialchars($current_tab); ?>" class="btn inv-reset-btn px-3">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
        <?php if ($total > 0): ?>
        <div class="inv-results-count">
            Showing <?php echo count($items); ?> of <?php echo $total; ?> group<?php echo $total !== 1 ? 's' : ''; ?>
            <?php if ($search || $category_filter || $status_filter): ?>
                — filtered results
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- AVAILABLE/BORROWED ITEMS TAB -->
    <div id="tab-campus-inventory" style="display: <?php echo in_array($current_tab, ['available', 'borrowed']) ? 'block' : 'none'; ?>;">
    <!-- Inventory Grid (grouped) -->
    <?php if (count($items) > 0): ?>
    <div class="row g-3">
        <?php
        $cat_icon_map = [
            'Electronics'       => 'fa-laptop',
            'Furniture'         => 'fa-chair',
            'Tools & Hardware'  => 'fa-tools',
            'Sports & Recreation'=> 'fa-futbol',
            'Books & Publications'=> 'fa-book',
            'Audio/Visual'      => 'fa-video',
            'Office Supplies'   => 'fa-pen',
            'Equipment'         => 'fa-cogs',
            'Cleaning Supplies' => 'fa-broom',
            'Medical & Safety'  => 'fa-first-aid',
            'Laboratory Supplies'=> 'fa-flask',
            'Electrical Supplies'=> 'fa-bolt',
        ];
        foreach ($items as $group):
            $unit_count       = count($group['units']);
            $available_units  = array_values(array_filter($group['units'], fn($u) => $u['status'] === 'available'));
            $available_count  = count($available_units);
            $first_available  = $available_units[0] ?? null;
            $unit_ids         = array_column($group['units'], 'id');
            $user_has_borrow  = !empty(array_intersect($unit_ids, $user_borrowed_item_ids));
            $conditions       = array_unique(array_filter(array_column($group['units'], 'condition')));
            $cond_label       = count($conditions) === 1 ? ucfirst(reset($conditions)) : 'Mixed';
            $cond_color_map   = ['excellent' => '#15803d', 'good' => '#15803d', 'fair' => '#b45309', 'poor' => '#b91c1c'];
            $cond_color       = count($conditions) === 1 ? ($cond_color_map[strtolower(reset($conditions))] ?? '#6b7280') : '#6b7280';
            $location         = $group['units'][0]['location'] ?? 'N/A';
            $cicon            = $cat_icon_map[$group['category']] ?? 'fa-box';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="inv-card">
                <!-- Top -->
                <div class="inv-card-top">
                    <div class="inv-card-icon">
                        <i class="fas <?php echo $cicon; ?>"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div class="inv-card-name"><?php echo htmlspecialchars($group['item_name']); ?></div>
                        <span class="inv-card-category"><?php echo htmlspecialchars($group['category']); ?></span>
                    </div>
                    <span style="background:rgba(59,130,246,0.12);color:#1d4ed8;font-weight:700;font-size:0.74rem;padding:3px 10px;border-radius:10px;white-space:nowrap;flex-shrink:0;">
                        <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                    </span>
                </div>

                <!-- Info Rows -->
                <div class="inv-card-body">
                    <div class="inv-info-row">
                        <span class="inv-info-icon"><i class="fas fa-check-circle"></i></span>
                        <span class="inv-info-label">Available</span>
                        <span class="inv-info-val" style="color:<?php echo $available_count > 0 ? '#15803d' : '#b91c1c'; ?>;font-weight:700;">
                            <?php echo $available_count; ?> of <?php echo $unit_count; ?>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-icon"><i class="fas fa-star"></i></span>
                        <span class="inv-info-label">Condition</span>
                        <span class="inv-info-val" style="color:<?php echo $cond_color; ?>;font-weight:600;">
                            <?php echo $cond_label; ?>
                        </span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <span class="inv-info-label">Location</span>
                        <span class="inv-info-val"><?php echo htmlspecialchars($location); ?></span>
                    </div>
                    <?php if (!empty($group['description'])): ?>
                    <p class="inv-description"><?php echo htmlspecialchars(mb_strimwidth($group['description'], 0, 80, '…')); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Action -->
                <div class="inv-card-footer">
                    <?php if ($user_has_borrow): ?>
                        <div class="inv-already-badge">
                            <i class="fas fa-info-circle"></i> You have a unit of this item borrowed
                        </div>
                        <div class="inv-disabled-btn">
                            <i class="fas fa-check"></i> Already Borrowed
                        </div>
                    <?php elseif ($first_available): ?>
                        <a href="requests.php?item_id=<?php echo $first_available['id']; ?>&type=borrow" class="inv-borrow-btn">
                            <i class="fas fa-hand-paper"></i> Borrow Item
                        </a>
                    <?php else: ?>
                        <div class="inv-disabled-btn">
                            <i class="fas fa-ban"></i> Not Available
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="inv-empty">
        <div class="inv-empty-icon"><i class="fas fa-box-open"></i></div>
        <h5>No items found</h5>
        <p>Try adjusting your search or filters.</p>
        <?php if ($search || $category_filter || $status_filter): ?>
            <a href="inventory.php?tab=<?php echo htmlspecialchars($current_tab); ?>" class="btn inv-reset-btn mt-3" style="display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i === (int)$page ? 'active' : ''; ?>">
                    <a class="page-link" href="inventory.php?tab=<?php echo htmlspecialchars($current_tab); ?>&page=<?php echo $i;
                        echo $search          ? '&search='   . urlencode($search)          : '';
                        echo $category_filter ? '&category=' . urlencode($category_filter) : '';
                        echo $status_filter   ? '&status='   . urlencode($status_filter)   : '';
                    ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
    </div>

    <!-- USER-OWNED ITEMS TAB -->
    <div id="tab-owned-items" style="display: <?php echo $current_tab === 'owned' ? 'block' : 'none'; ?>;">
        <?php if (count($grouped_owned) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php foreach ($grouped_owned as $group):
                $unit_count    = count($group['units']);
                $conditions    = array_unique(array_filter(array_column($group['units'], 'condition')));
                $cond_label    = count($conditions) === 1 ? ucfirst(reset($conditions)) : 'Mixed';
                $years         = array_unique(array_filter(array_column($group['units'], 'year_owned')));
                $year_label    = count($years) === 1 ? reset($years) : (count($years) === 0 ? '—' : 'Various');
            ?>
            <div class="inv-card" style="border-color: rgba(139,0,0,0.15);">
                <!-- Top -->
                <div class="inv-card-top">
                    <div class="inv-card-icon" style="background: rgba(139,0,0,0.10); color: #8B0000;">
                        <i class="fas fa-box"></i>
                    </div>
                    <div style="flex:1; min-width:0;">
                        <div class="inv-card-name"><?php echo htmlspecialchars($group['item_name']); ?></div>
                        <span class="inv-card-category"><?php echo htmlspecialchars($group['category']); ?></span>
                    </div>
                    <span style="background:rgba(139,0,0,0.10);color:#8B0000;font-weight:700;font-size:0.74rem;padding:3px 10px;border-radius:10px;white-space:nowrap;flex-shrink:0;">
                        <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                    </span>
                </div>

                <!-- Info Rows -->
                <div class="inv-card-body">
                    <div class="inv-info-row">
                        <span class="inv-info-icon"><i class="fas fa-calendar"></i></span>
                        <span class="inv-info-label">Year</span>
                        <span class="inv-info-val"><?php echo htmlspecialchars($year_label); ?></span>
                    </div>
                    <div class="inv-info-row">
                        <span class="inv-info-icon"><i class="fas fa-star"></i></span>
                        <span class="inv-info-label">Condition</span>
                        <span class="inv-info-val" style="color:#8B0000;font-weight:600;"><?php echo $cond_label; ?></span>
                    </div>
                    <?php if (!empty($group['description'])): ?>
                    <p class="inv-description"><?php echo htmlspecialchars(mb_strimwidth($group['description'], 0, 80, '…')); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Action -->
                <div class="inv-card-footer">
                    <button type="button" class="btn" style="width:100%;background:rgba(139,0,0,0.10);color:#8B0000;border:none;border-radius:10px;font-weight:600;font-size:0.85rem;"
                        data-bs-toggle="modal" data-bs-target="#ownedItemDetailModal"
                        onclick="showOwnedGroup(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                        <i class="fas fa-info-circle"></i> View Details
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="inv-empty">
            <div class="inv-empty-icon"><i class="fas fa-user-circle"></i></div>
            <h5>No owned items recorded</h5>
            <p>You don't have any items recorded in your ownership history.</p>
            <p style="font-size: 0.85rem; color: rgba(0,0,0,0.35);">Contact your admin to add items you own or have owned.</p>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Owned Item Detail Modal -->
<div class="modal fade" id="ownedItemDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:8px; border:1px solid #e5e7eb;">
            <div class="modal-header" style="background: #8B0000; color: #fff; border:none; border-radius: 8px 8px 0 0;">
                <div>
                    <h5 class="modal-title" id="ownedModalTitle" style="font-size:1.1rem; font-weight:700; margin-bottom: 4px; color: #fff;"></h5>
                    <small id="ownedModalCategory" style="color: rgba(255,255,255,0.80);"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Description -->
                <div id="ownedModalDescSection" style="margin-bottom: 20px; display: none;">
                    <div style="font-size: 0.8rem; color: rgba(0,0,0,0.50); text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Description</div>
                    <div id="ownedModalDesc" style="background: rgba(0,0,0,0.03); border-radius: 8px; padding: 12px; line-height: 1.5; color: #374151; font-size: 0.95rem;"></div>
                </div>

                <!-- Individual Units -->
                <div>
                    <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(0,0,0,0.50);margin-bottom:10px;">Units</div>
                    <div id="ownedModalUnitsGrid" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setInvTab(tabName) {
    document.getElementById('tab-campus-inventory').style.display = tabName !== 'owned' ? 'block' : 'none';
    document.getElementById('tab-owned-items').style.display = tabName === 'owned' ? 'block' : 'none';
    document.getElementById('inv-filter-section').style.display = tabName !== 'owned' ? 'block' : 'none';
    document.querySelectorAll('.inv-tab-link').forEach(function(tab) {
        tab.classList.remove('inv-tab-active');
    });
    event.target.closest('a').classList.add('inv-tab-active');
}

function showOwnedGroup(group) {
    document.getElementById('ownedModalTitle').textContent = group.item_name;
    document.getElementById('ownedModalCategory').textContent = group.category + ' · ' + group.units.length + ' unit(s)';

    var descEl = document.getElementById('ownedModalDescSection');
    if (group.description) {
        descEl.style.display = 'block';
        document.getElementById('ownedModalDesc').textContent = group.description;
    } else {
        descEl.style.display = 'none';
    }

    var grid = document.getElementById('ownedModalUnitsGrid');
    grid.innerHTML = '';
    group.units.forEach(function(unit, idx) {
        var cond = unit.condition ? unit.condition.charAt(0).toUpperCase() + unit.condition.slice(1) : '—';
        var year = unit.year_owned || '—';
        var row  = document.createElement('div');
        row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f7f7f7;border-radius:6px;font-size:0.88rem;';
        row.innerHTML =
            '<div style="display:flex;flex-direction:column;gap:2px;">'
            + '<span style="font-weight:700;color:#1a1d23;">Unit ' + (idx + 1)
            +   ' <span style="font-weight:400;color:rgba(0,0,0,0.50);font-size:0.78rem;">· ' + cond + '</span></span>'
            + '<span style="color:rgba(0,0,0,0.55);font-size:0.78rem;"><i class="fas fa-calendar" style="margin-right:4px;"></i>Year: ' + year + '</span>'
            + '</div>';
        grid.appendChild(row);
    });

    var modal = document.getElementById('ownedItemDetailModal');
    modal.addEventListener('hidden.bs.modal', function() {
        var backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) { backdrop.remove(); }
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }, { once: true });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
