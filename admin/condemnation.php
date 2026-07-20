<?php
$page_title = 'Condemnation & Disposal';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id     = (int)sanitizeInput($_POST['item_id'] ?? 0);
    $action_type = sanitizeInput($_POST['action'] ?? '');

    if ($action_type === 'condemn' && $item_id > 0) {
        $condemn_reason = sanitizeInput($_POST['condemn_reason'] ?? '');
        dbUpdateInventory($item_id, [
            'status'                => 'condemned',
            'condemnation_reason'   => $condemn_reason,
            'condemned_at'          => date('Y-m-d H:i:s'),
        ]);
        logActivity($current_user['id'], 'CONDEMN', "Condemned inventory item #$item_id", 'inventory', $item_id);
        redirectWithMessage('condemnation.php?tab=condemned', 'Item condemned successfully.', 'success');

    } elseif ($action_type === 'dispose' && $item_id > 0) {
        $dispose_notes = sanitizeInput($_POST['dispose_notes'] ?? '');
        dbUpdateInventory($item_id, [
            'status'        => 'disposed',
            'disposal_notes'=> $dispose_notes,
            'disposed_at'   => date('Y-m-d H:i:s'),
        ]);
        logActivity($current_user['id'], 'DISPOSE', "Disposed inventory item #$item_id", 'inventory', $item_id);
        redirectWithMessage('condemnation.php?tab=disposed', 'Item marked as disposed.', 'success');

    } elseif ($action_type === 'restore' && $item_id > 0) {
        dbUpdateInventory($item_id, [
            'status'              => 'damaged',
            'condemnation_reason' => null,
            'condemned_at'        => null,
            'disposal_notes'      => null,
            'disposed_at'         => null,
        ]);
        logActivity($current_user['id'], 'RESTORE', "Restored inventory item #$item_id from condemnation", 'inventory', $item_id);
        redirectWithMessage('condemnation.php?tab=evaluate', 'Item restored to evaluation list.', 'info');
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
displayMessage();

// Load data
$all_inventory = getInventory();
$all_campuses  = getAllCampuses();

// Build tab lists (re-index with array_values for clean iteration)
$evaluate_items  = array_values(array_filter($all_inventory, fn($i) => in_array($i['status'], ['damaged', 'maintenance'])));
$condemned_items = array_values(array_filter($all_inventory, fn($i) => $i['status'] === 'condemned'));
$disposed_items  = array_values(array_filter($all_inventory, fn($i) => $i['status'] === 'disposed'));

// Active tab
$active_tab = $_GET['tab'] ?? 'evaluate';
if (!in_array($active_tab, ['evaluate', 'condemned', 'disposed'])) {
    $active_tab = 'evaluate';
}

// KPI values
$evaluate_count   = count($evaluate_items);
$condemned_count  = count($condemned_items);
$disposed_count   = count($disposed_items);
$value_at_risk    = array_sum(array_column($evaluate_items, 'cost'));

// Filters
$filter_campus   = $_GET['campus']   ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_search   = trim($_GET['search'] ?? '');

// Collect all categories from relevant items
$all_categories = [];
foreach ($all_inventory as $inv) {
    if (!empty($inv['category']) && !in_array($inv['category'], $all_categories)) {
        $all_categories[] = $inv['category'];
    }
}
sort($all_categories);

// Apply filters to the active tab's list
function cdApplyFilters(array $items, string $campus, string $category, string $search): array {
    return array_values(array_filter($items, function($item) use ($campus, $category, $search) {
        if ($campus   && (string)($item['campus_id'] ?? '') !== $campus)                           return false;
        if ($category && ($item['category'] ?? '') !== $category)                                   return false;
        if ($search) {
            $hay = strtolower(($item['item_name'] ?? '') . ' ' . ($item['qr_code_id'] ?? '') . ' ' . ($item['category'] ?? ''));
            if (strpos($hay, strtolower($search)) === false) return false;
        }
        return true;
    }));
}

if ($active_tab === 'evaluate') {
    $display_items = cdApplyFilters($evaluate_items, $filter_campus, $filter_category, $filter_search);
} elseif ($active_tab === 'condemned') {
    $display_items = cdApplyFilters($condemned_items, $filter_campus, $filter_category, $filter_search);
} else {
    $display_items = cdApplyFilters($disposed_items, $filter_campus, $filter_category, $filter_search);
}

// Campus name lookup helper
$campus_map = [];
foreach ($all_campuses as $c) {
    $campus_map[$c['id']] = $c['name'];
}
?>

<style>
/* ===== CONDEMNATION PAGE ===== */
.cd-card {
    background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    padding: 22px 24px; margin-bottom: 20px;
}
.cd-card-title { font-size: 1.05rem; font-weight: 800; color: #1a1d23; margin-bottom: 4px; }
.cd-section-label {
    font-size: 0.69rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: #999;
    margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb;
}

/* KPI Row */
.cd-kpi-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.cd-kpi-card {
    flex: 1; min-width: 180px;
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    padding: 18px 20px;
    display: flex; align-items: center; gap: 14px;
}
.cd-kpi-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.15rem; flex-shrink: 0;
}
.cd-kpi-val  { font-size: 1.45rem; font-weight: 900; line-height: 1; margin-bottom: 2px; }
.cd-kpi-lbl  { font-size: 0.73rem; font-weight: 600; color: #999; text-transform: uppercase; letter-spacing: 0.4px; }

/* Tab Nav */
.cd-tab-nav {
    display: flex; gap: 0;
    border-bottom: 2px solid #e5e7eb; margin-bottom: 0;
}
.cd-tab-btn {
    background: none; border: none; font-size: 0.87rem; font-weight: 700;
    color: #999; padding: 10px 22px;
    border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer;
    transition: all 0.15s; display: inline-flex; align-items: center; gap: 7px;
}
.cd-tab-btn:hover { color: #555; }
.cd-tab-btn.active { color: #8B0000; border-bottom-color: #8B0000; }
.cd-tab-count {
    font-size: 0.70rem; background: rgba(0,0,0,0.07); color: #555;
    border-radius: 4px; padding: 1px 7px; font-weight: 700;
}
.cd-tab-btn.active .cd-tab-count { background: rgba(139,0,0,0.12); color: #8B0000; }
.cd-tab-pane { display: none; }
.cd-tab-pane.active { display: block; }

/* Filter Card */
.cd-filter-card {
    background: #fff;
    border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px;
    border-top: none;
    padding: 14px 20px 14px;
    margin-bottom: 16px;
    display: flex; align-items: flex-end; flex-wrap: wrap; gap: 12px;
}
.cd-filter-label { font-size: 0.71rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 5px; }

/* Table */
.cd-table-card {
    background: #fff;
    border: 1px solid #e5e7eb; border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06); overflow: hidden;
}
.cd-table { width: 100%; border-collapse: collapse; }
.cd-table th {
    font-size: 0.69rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.5px; color: #999;
    padding: 12px 16px; border-bottom: 1px solid #e5e7eb;
    background: #f7f7f7; white-space: nowrap;
}
.cd-table td {
    padding: 11px 16px; border-bottom: 1px solid #e5e7eb;
    font-size: 0.87rem; color: #374151; vertical-align: middle;
}
.cd-table tr:last-child td { border-bottom: none; }
.cd-table tr:hover td { background: #f7f7f7; }

/* Badges */
.cd-badge {
    display: inline-flex; align-items: center;
    padding: 3px 10px; border-radius: 4px; font-size: 0.74rem; font-weight: 700;
}
.cd-badge-evaluate  { background: rgba(245,158,11,0.12); color: #b45309; }
.cd-badge-condemned { background: rgba(139,0,0,0.12);    color: #8B0000; }
.cd-badge-disposed  { background: rgba(0,0,0,0.07);       color: #555; }
.cd-badge-damaged   { background: rgba(239,68,68,0.12);   color: #dc2626; }
.cd-badge-maintenance { background: rgba(245,158,11,0.12); color: #b45309; }

/* Condition badges */
.cd-badge-excellent { background: rgba(34,197,94,0.12);  color: #15803d; }
.cd-badge-good      { background: rgba(59,130,246,0.12); color: #1d4ed8; }
.cd-badge-fair      { background: rgba(245,158,11,0.12); color: #b45309; }
.cd-badge-poor      { background: rgba(239,68,68,0.12);  color: #dc2626; }

/* Action Buttons */
.cd-btn-condemn {
    background: #8B0000 !important; border: none !important;
    border-radius: 6px !important; font-weight: 700 !important;
    color: #fff !important; padding: 5px 13px !important;
    font-size: 0.79rem !important; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
    transition: opacity 0.15s !important;
}
.cd-btn-condemn:hover { opacity: 0.88 !important; }
.cd-btn-dispose {
    background: #450000 !important; border: none !important;
    border-radius: 6px !important; font-weight: 700 !important;
    color: #fff !important; padding: 5px 13px !important;
    font-size: 0.79rem !important; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
    transition: opacity 0.15s !important;
}
.cd-btn-dispose:hover { opacity: 0.85 !important; }
.cd-btn-restore {
    background: #f7f7f7 !important; border: 1px solid #e5e7eb !important;
    border-radius: 6px !important; font-weight: 700 !important;
    color: #555 !important; padding: 5px 12px !important;
    font-size: 0.79rem !important; cursor: pointer;
    display: inline-flex; align-items: center; gap: 5px;
    transition: background 0.13s !important;
}
.cd-btn-restore:hover { background: #efefef !important; }

/* Empty State */
.cd-empty-state {
    padding: 52px 24px; text-align: center; color: #bbb;
}
.cd-empty-state i { font-size: 2.6rem; margin-bottom: 12px; display: block; opacity: 0.30; }
.cd-empty-state p { font-size: 0.90rem; font-weight: 600; margin: 0; }
.cd-empty-state small { font-size: 0.78rem; color: #ccc; }

/* Modal */
.cd-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.50); z-index: 9990;
    align-items: center; justify-content: center; padding: 20px;
}
.cd-modal-overlay.open { display: flex; }
.cd-modal {
    background: #fff; border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    width: 100%; max-width: 460px;
    padding: 28px 30px;
}
.cd-modal-title {
    font-size: 1.02rem; font-weight: 800; color: #1a1d23;
    margin-bottom: 4px;
}
.cd-modal-sub {
    font-size: 0.80rem; color: #999; margin-bottom: 18px;
}
.cd-modal-item-name {
    background: #f7f7f7; border: 1px solid #e5e7eb;
    border-radius: 6px; padding: 10px 14px;
    font-size: 0.88rem; font-weight: 700; color: #1a1d23;
    margin-bottom: 16px;
}
.cd-modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; }
</style>

<div class="container-fluid mt-4 pb-4">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <div style="font-size:1.15rem;font-weight:800;color:#1a1d23;">
                <i class="fas fa-ban me-2" style="color:#8B0000;"></i>Condemnation &amp; Disposal
            </div>
            <div style="font-size:0.81rem;color:rgba(0,0,0,0.42);">
                Review damaged and under-maintenance items for condemnation or disposal
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="cd-kpi-row">
        <div class="cd-kpi-card">
            <div class="cd-kpi-icon" style="background:rgba(245,158,11,0.12);">
                <i class="fas fa-exclamation-triangle" style="color:#b45309;"></i>
            </div>
            <div>
                <div class="cd-kpi-val" style="color:#b45309;"><?php echo $evaluate_count; ?></div>
                <div class="cd-kpi-lbl">For Evaluation</div>
            </div>
        </div>
        <div class="cd-kpi-card">
            <div class="cd-kpi-icon" style="background:rgba(139,0,0,0.10);">
                <i class="fas fa-ban" style="color:#8B0000;"></i>
            </div>
            <div>
                <div class="cd-kpi-val" style="color:#8B0000;"><?php echo $condemned_count; ?></div>
                <div class="cd-kpi-lbl">Condemned</div>
            </div>
        </div>
        <div class="cd-kpi-card">
            <div class="cd-kpi-icon" style="background:rgba(0,0,0,0.06);">
                <i class="fas fa-trash-alt" style="color:#6b7280;"></i>
            </div>
            <div>
                <div class="cd-kpi-val" style="color:#6b7280;"><?php echo $disposed_count; ?></div>
                <div class="cd-kpi-lbl">Disposed</div>
            </div>
        </div>
        <div class="cd-kpi-card">
            <div class="cd-kpi-icon" style="background:rgba(239,68,68,0.10);">
                <i class="fas fa-peso-sign" style="color:#dc2626;"></i>
            </div>
            <div>
                <div class="cd-kpi-val" style="color:#dc2626;font-size:1.15rem;">
                    &#8369;<?php echo number_format($value_at_risk, 2); ?>
                </div>
                <div class="cd-kpi-lbl">Est. Value at Risk</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="cd-tab-nav" style="border-bottom:2px solid #e5e7eb;">
        <a href="condemnation.php?tab=evaluate" class="cd-tab-btn <?php echo $active_tab==='evaluate'?'active':''; ?>">
            <i class="fas fa-search"></i> For Evaluation
            <span class="cd-tab-count"><?php echo $evaluate_count; ?></span>
        </a>
        <a href="condemnation.php?tab=condemned" class="cd-tab-btn <?php echo $active_tab==='condemned'?'active':''; ?>">
            <i class="fas fa-ban"></i> Condemned
            <span class="cd-tab-count"><?php echo $condemned_count; ?></span>
        </a>
        <a href="condemnation.php?tab=disposed" class="cd-tab-btn <?php echo $active_tab==='disposed'?'active':''; ?>">
            <i class="fas fa-trash-alt"></i> Disposed
            <span class="cd-tab-count"><?php echo $disposed_count; ?></span>
        </a>
    </div>

    <!-- Filter Bar (attached below tab nav) -->
    <form method="GET" action="condemnation.php">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
        <div class="cd-filter-card">
            <div>
                <div class="cd-filter-label">Campus</div>
                <select class="form-select" name="campus" onchange="this.form.submit()" style="min-width:160px;">
                    <option value="">All Campuses</option>
                    <?php foreach ($all_campuses as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo (string)$filter_campus===(string)$c['id']?'selected':''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <div class="cd-filter-label">Category</div>
                <select class="form-select" name="category" onchange="this.form.submit()" style="min-width:150px;">
                    <option value="">All Categories</option>
                    <?php foreach ($all_categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category===$cat?'selected':''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:200px;">
                <div class="cd-filter-label">Search</div>
                <div class="input-group">
                    <span class="input-group-text" style="background:#f7f7f7;border-color:#e5e7eb;">
                        <i class="fas fa-search" style="color:#aaa;font-size:0.80rem;"></i>
                    </span>
                    <input type="text" class="form-control" name="search"
                           value="<?php echo htmlspecialchars($filter_search); ?>"
                           placeholder="Item name, QR code…"
                           style="border-left:none;">
                </div>
            </div>
            <?php if ($filter_campus || $filter_category || $filter_search): ?>
            <div style="align-self:flex-end;">
                <a href="condemnation.php?tab=<?php echo htmlspecialchars($active_tab); ?>"
                   class="btn" style="background:#f7f7f7;border:1px solid #e5e7eb;font-size:0.82rem;font-weight:600;color:#555;">
                    <i class="fas fa-times me-1"></i>Clear
                </a>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Item Table -->
    <div class="cd-table-card">
        <?php if (count($display_items) > 0): ?>
        <div style="overflow-x:auto;">
        <table class="cd-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Campus</th>
                    <th>Qty</th>
                    <th>Condition</th>
                    <th>Purchase Date</th>
                    <th>Cost</th>
                    <th>Status</th>
                    <?php if ($active_tab !== 'disposed'): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($display_items as $row):
                $campus_name = $campus_map[$row['campus_id']] ?? 'Unknown Campus';

                $condition_badge = match($row['condition'] ?? '') {
                    'excellent' => 'cd-badge-excellent',
                    'good'      => 'cd-badge-good',
                    'fair'      => 'cd-badge-fair',
                    'poor'      => 'cd-badge-poor',
                    default     => 'cd-badge-fair',
                };

                $status_badge = match($row['status'] ?? '') {
                    'condemned'   => 'cd-badge-condemned',
                    'disposed'    => 'cd-badge-disposed',
                    'damaged'     => 'cd-badge-damaged',
                    'maintenance' => 'cd-badge-maintenance',
                    default       => 'cd-badge-evaluate',
                };
            ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:0.88rem;color:#1a1d23;">
                            <?php echo htmlspecialchars($row['item_name']); ?>
                        </div>
                        <?php if (!empty($row['qr_code_id'])): ?>
                        <div style="font-size:0.72rem;font-family:monospace;color:rgba(139,0,0,0.65);margin-top:2px;">
                            <?php echo htmlspecialchars($row['qr_code_id']); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="cd-badge" style="background:rgba(59,130,246,0.09);color:#1d4ed8;">
                            <?php echo htmlspecialchars($row['category'] ?? '—'); ?>
                        </span>
                    </td>
                    <td style="font-size:0.83rem;color:rgba(0,0,0,0.55);">
                        <i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.45);"></i>
                        <?php echo htmlspecialchars($campus_name); ?>
                    </td>
                    <td style="font-weight:700;color:#1a1d23;">
                        <?php echo (int)($row['quantity'] ?? 1); ?>
                    </td>
                    <td>
                        <span class="cd-badge <?php echo $condition_badge; ?>">
                            <?php echo ucfirst($row['condition'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td style="font-size:0.83rem;color:rgba(0,0,0,0.55);">
                        <?php echo !empty($row['purchase_date']) ? formatDate($row['purchase_date'], 'M d, Y') : '—'; ?>
                    </td>
                    <td style="font-weight:700;color:#1a1d23;">
                        &#8369;<?php echo number_format((float)($row['cost'] ?? 0), 2); ?>
                    </td>
                    <td>
                        <span class="cd-badge <?php echo $status_badge; ?>">
                            <?php echo ucfirst($row['status'] ?? ''); ?>
                        </span>
                        <?php if ($active_tab === 'condemned' && !empty($row['condemned_at'])): ?>
                        <div style="font-size:0.70rem;color:#aaa;margin-top:3px;">
                            <?php echo htmlspecialchars(formatDate($row['condemned_at'], 'M d, Y')); ?>
                        </div>
                        <?php elseif ($active_tab === 'disposed' && !empty($row['disposed_at'])): ?>
                        <div style="font-size:0.70rem;color:#aaa;margin-top:3px;">
                            <?php echo htmlspecialchars(formatDate($row['disposed_at'], 'M d, Y')); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <?php if ($active_tab === 'evaluate'): ?>
                    <td>
                        <button type="button" class="cd-btn-condemn"
                            onclick="openCondemnModal(<?php echo $row['id']; ?>, <?php echo htmlspecialchars(json_encode($row['item_name'])); ?>)">
                            <i class="fas fa-ban"></i> Condemn
                        </button>
                    </td>
                    <?php elseif ($active_tab === 'condemned'): ?>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button type="button" class="cd-btn-dispose"
                            onclick="openDisposeModal(<?php echo $row['id']; ?>, <?php echo htmlspecialchars(json_encode($row['item_name'])); ?>)">
                            <i class="fas fa-trash-alt"></i> Dispose
                        </button>
                        <form method="POST" action="condemnation.php" style="display:inline;">
                            <input type="hidden" name="item_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="action" value="restore">
                            <button type="submit" class="cd-btn-restore"
                                onclick="return confirm('Restore this item back to the evaluation list?')">
                                <i class="fas fa-undo"></i> Restore
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
            <div class="cd-empty-state">
                <?php if ($active_tab === 'evaluate'): ?>
                <i class="fas fa-check-circle"></i>
                <p>No items pending evaluation</p>
                <small>Items with <strong>Damaged</strong> or <strong>Maintenance</strong> status will appear here</small>
                <?php elseif ($active_tab === 'condemned'): ?>
                <i class="fas fa-ban"></i>
                <p>No condemned items</p>
                <small>Items you condemn from the Evaluation tab will appear here</small>
                <?php else: ?>
                <i class="fas fa-trash-alt"></i>
                <p>No disposed items</p>
                <small>Items disposed from the Condemned tab will appear here</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /.container-fluid -->
</div><!-- /.main-wrapper -->

<!-- ===== CONDEMN MODAL ===== -->
<div class="cd-modal-overlay" id="condemnModal">
    <div class="cd-modal">
        <div class="cd-modal-title"><i class="fas fa-ban me-2" style="color:#8B0000;"></i>Condemn Item</div>
        <div class="cd-modal-sub">This will flag the item as condemned and remove it from active inventory.</div>
        <div class="cd-modal-item-name" id="condemnItemName">—</div>
        <form method="POST" action="condemnation.php" id="condemnForm">
            <input type="hidden" name="action" value="condemn">
            <input type="hidden" name="item_id" id="condemnItemId" value="">
            <div class="mb-3">
                <label class="form-label" style="font-size:0.85rem;font-weight:700;">
                    Reason for Condemnation <span style="color:#dc2626;">*</span>
                </label>
                <textarea class="form-control" name="condemn_reason" id="condemnReason" rows="3"
                    placeholder="Describe why this item should be condemned…"
                    required style="font-size:0.87rem;resize:vertical;"></textarea>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="btn"
                    style="background:#f7f7f7;border:1px solid #e5e7eb;font-size:0.85rem;font-weight:600;color:#555;"
                    onclick="closeModal('condemnModal')">
                    Cancel
                </button>
                <button type="submit" class="btn cd-btn-condemn" style="padding:8px 20px !important;font-size:0.87rem !important;">
                    <i class="fas fa-ban me-1"></i> Confirm Condemnation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== DISPOSE MODAL ===== -->
<div class="cd-modal-overlay" id="disposeModal">
    <div class="cd-modal">
        <div class="cd-modal-title"><i class="fas fa-trash-alt me-2" style="color:#450000;"></i>Dispose Item</div>
        <div class="cd-modal-sub">This action marks the item as permanently disposed. It cannot be undone through normal workflow.</div>
        <div class="cd-modal-item-name" id="disposeItemName">—</div>
        <form method="POST" action="condemnation.php" id="disposeForm">
            <input type="hidden" name="action" value="dispose">
            <input type="hidden" name="item_id" id="disposeItemId" value="">
            <div class="mb-3">
                <label class="form-label" style="font-size:0.85rem;font-weight:700;">
                    Disposal Notes <span style="font-size:0.75rem;font-weight:400;color:#999;">(optional)</span>
                </label>
                <textarea class="form-control" name="dispose_notes" id="disposeNotes" rows="3"
                    placeholder="e.g. Sold for scrap, donated, landfilled…"
                    style="font-size:0.87rem;resize:vertical;"></textarea>
            </div>
            <div class="cd-modal-footer">
                <button type="button" class="btn"
                    style="background:#f7f7f7;border:1px solid #e5e7eb;font-size:0.85rem;font-weight:600;color:#555;"
                    onclick="closeModal('disposeModal')">
                    Cancel
                </button>
                <button type="submit" class="btn cd-btn-dispose" style="padding:8px 20px !important;font-size:0.87rem !important;">
                    <i class="fas fa-trash-alt me-1"></i> Confirm Disposal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCondemnModal(itemId, itemName) {
    document.getElementById('condemnItemId').value = itemId;
    document.getElementById('condemnItemName').textContent = itemName;
    document.getElementById('condemnReason').value = '';
    document.getElementById('condemnModal').classList.add('open');
}
function openDisposeModal(itemId, itemName) {
    document.getElementById('disposeItemId').value = itemId;
    document.getElementById('disposeItemName').textContent = itemName;
    document.getElementById('disposeNotes').value = '';
    document.getElementById('disposeModal').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
// Close modal on overlay click
document.getElementById('condemnModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('condemnModal');
});
document.getElementById('disposeModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('disposeModal');
});
// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('condemnModal');
        closeModal('disposeModal');
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
