<?php
$page_title = 'Inventory Management';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();
$action = $_GET['action'] ?? 'list';
$campus_filter = $_GET['campus_id'] ?? '';
$status_filter = $_GET['status'] ?? ''; // Default to show all items
$page = $_GET['page'] ?? 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $qr_code_id = generateQRCodeId();
        $item_name = sanitizeInput($_POST['item_name']);
        $category = sanitizeInput($_POST['category']);
        $description = sanitizeInput($_POST['description']);
        $campus_id = sanitizeInput($_POST['campus_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $location = sanitizeInput($_POST['location']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);
        $cost = sanitizeInput($_POST['cost']);
        $condition = sanitizeInput($_POST['condition']);

        // In hardcoded mode, we just log and redirect
        logActivity($current_user['id'], 'CREATE', "Added new inventory item: $item_name", 'inventory', rand(100, 999));
        redirectWithMessage('inventory.php', 'Item added successfully!', 'success');
    } elseif ($action === 'add_owned') {
        $user_id = sanitizeInput($_POST['user_id']);
        $item_name = sanitizeInput($_POST['item_name']);
        $category = sanitizeInput($_POST['category']);
        $description = sanitizeInput($_POST['description']);
        $campus_id = sanitizeInput($_POST['campus_id']);
        $year_owned = sanitizeInput($_POST['year_owned']);
        $quantity = sanitizeInput($_POST['quantity']);
        $condition = sanitizeInput($_POST['condition']);
        $notes = sanitizeInput($_POST['notes']);
        $purchase_date = sanitizeInput($_POST['purchase_date']);

        logActivity($current_user['id'], 'CREATE', "Added user owned item: $item_name (Qty: $quantity) for user_id: $user_id", 'inventory', rand(100, 999));
        redirectWithMessage('inventory.php?tab=owned', 'User-owned item added successfully!', 'success');
    } elseif ($action === 'edit') {
        $inventory_id = sanitizeInput($_GET['id']);
        $item_name = sanitizeInput($_POST['item_name']);
        $category = sanitizeInput($_POST['category']);
        $description = sanitizeInput($_POST['description']);
        $campus_id = sanitizeInput($_POST['campus_id']);
        $quantity = sanitizeInput($_POST['quantity']);
        $location = sanitizeInput($_POST['location']);
        $condition = sanitizeInput($_POST['condition']);
        $status = sanitizeInput($_POST['status']);

        $query = "UPDATE inventory SET item_name = '$item_name', category = '$category', description = '$description', 
                  campus_id = '$campus_id', quantity = '$quantity', location = '$location', condition = '$condition', status = '$status'
                  WHERE id = '$inventory_id'";

        // In hardcoded mode, just redirect
        logActivity($current_user['id'], 'UPDATE', "Updated inventory item: $item_name", 'inventory', $inventory_id);
        redirectWithMessage('inventory.php', 'Item updated successfully!', 'success');
    } elseif ($action === 'delete') {
        $inventory_id = sanitizeInput($_GET['id']);
        // In hardcoded mode, just redirect
        logActivity($current_user['id'], 'DELETE', "Deleted inventory item", 'inventory', $inventory_id);
        redirectWithMessage('inventory.php', 'Item deleted successfully!', 'success');
    }
}

// Get campuses for dropdown
$campuses = getAllCampuses();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
displayMessage();
?>

<style>
/* ===== ADMIN INVENTORY ===== */
.ai-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07);
    padding:22px 24px; margin-bottom:20px;
}
.ai-card-title {
    font-size:1.05rem; font-weight:800; color:#1a1d23;
    margin-bottom:4px;
}
.ai-card-sub { font-size:0.81rem; color:rgba(0,0,0,0.42); margin-bottom:18px; }
.ai-section-title {
    font-size:0.71rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.6px; color:rgba(0,0,0,0.36);
    margin-bottom:14px; padding-bottom:8px;
    border-bottom:1px solid rgba(0,0,0,0.07);
}
.ai-divider { border-color:rgba(0,0,0,0.07); margin:18px 0; }

/* Toolbar */
.ai-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
.ai-filter-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:16px;
    padding:16px 20px; margin-bottom:16px;
    display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;
}
.ai-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(0,0,0,0.36); margin-bottom:5px; }

/* Buttons */
.ai-btn-primary {
    background:linear-gradient(135deg,#8B0000,#b91c1c) !important;
    border:none !important; border-radius:11px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
    box-shadow:0 4px 12px rgba(139,0,0,0.22) !important;
    transition:transform 0.15s, box-shadow 0.15s !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:7px;
}
.ai-btn-primary:hover { color:#fff !important; transform:translateY(-1px) !important; box-shadow:0 6px 18px rgba(139,0,0,0.30) !important; }
.ai-btn-secondary {
    background:rgba(0,0,0,0.06) !important; border:1px solid rgba(0,0,0,0.10) !important;
    border-radius:11px !important; font-weight:600 !important; color:rgba(0,0,0,0.55) !important;
    padding:9px 16px !important; font-size:0.87rem !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:7px;
}
.ai-btn-secondary:hover { color:#1a1d23 !important; background:rgba(0,0,0,0.09) !important; }

.ai-btn-sm {
    padding:5px 11px; font-size:0.78rem; border-radius:8px;
    border:none; font-weight:600; cursor:pointer;
    text-decoration:none; display:inline-flex; align-items:center; gap:5px;
    transition:all 0.13s;
}
.ai-btn-edit    { background:rgba(245,158,11,0.12); color:#b45309; }
.ai-btn-edit:hover { background:rgba(245,158,11,0.22); color:#b45309; }
.ai-btn-delete  { background:rgba(239,68,68,0.12);  color:#dc2626; }
.ai-btn-delete:hover { background:rgba(239,68,68,0.22); color:#dc2626; }
.ai-btn-info    { background:rgba(59,130,246,0.10);  color:#1d4ed8; }
.ai-btn-info:hover { background:rgba(59,130,246,0.18); color:#1d4ed8; }

/* Table */
.ai-table-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07);
    overflow:hidden;
}
.ai-table { width:100%; border-collapse:collapse; }
.ai-table th {
    font-size:0.69rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.5px; color:rgba(0,0,0,0.36);
    padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.07);
    background:rgba(0,0,0,0.015);
}
.ai-table td {
    padding:12px 16px; border-bottom:1px solid rgba(0,0,0,0.05);
    font-size:0.87rem; color:#374151; vertical-align:middle;
}
.ai-table tr:last-child td { border-bottom:none; }
.ai-table tr:hover td { background:rgba(0,0,0,0.015); }

.ai-badge {
    display:inline-flex; align-items:center;
    padding:3px 10px; border-radius:20px; font-size:0.74rem; font-weight:700;
}
.ai-badge-success    { background:rgba(34,197,94,0.12);  color:#15803d; }
.ai-badge-warning    { background:rgba(245,158,11,0.12); color:#b45309; }
.ai-badge-danger     { background:rgba(239,68,68,0.12);  color:#dc2626; }
.ai-badge-info       { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.ai-badge-secondary  { background:rgba(0,0,0,0.07);       color:rgba(0,0,0,0.50); }

.ai-empty { padding:48px 24px; text-align:center; color:rgba(0,0,0,0.35); }
.ai-empty i { font-size:2.5rem; margin-bottom:12px; display:block; opacity:0.3; }

/* QR chip */
.ai-qr-chip {
    font-family:monospace; font-size:0.76rem; font-weight:600;
    background:rgba(139,0,0,0.07); color:#8B0000;
    border-radius:6px; padding:2px 7px;
}
</style>

<div class="container-fluid mt-4 pb-4">

    <?php if ($action === 'add'): ?>
    <!-- Add Item Form -->
    <div class="ai-card">
        <div class="ai-card-title">Add New Inventory Item</div>
        <div class="ai-card-sub">Fill in the details for the new item</div>
        <hr class="ai-divider mt-0">
        <form method="POST" action="">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category *</label>
                    <input type="text" class="form-control" name="category" placeholder="e.g., Furniture, Electronics" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus *</label>
                    <select class="form-select" name="campus_id" required>
                        <option value="">Select Campus</option>
                        <?php foreach ($campuses as $campus): ?>
                            <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quantity *</label>
                    <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Condition *</label>
                    <select class="form-select" name="condition" required>
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cost</label>
                    <input type="number" class="form-control" name="cost" step="0.01" placeholder="0.00">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Location / Building</label>
                    <input type="text" class="form-control" name="location">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="inventory.php" class="btn ai-btn-secondary">Cancel</a>
                <button type="submit" class="btn ai-btn-primary"><i class="fas fa-plus"></i> Add Item</button>
            </div>
        </form>
    </div>

    <?php elseif ($action === 'edit'): ?>
    <!-- Edit Item Form -->
    <?php
    $inventory_id = sanitizeInput($_GET['id']);
    $item = findById(getInventory(), (int)$inventory_id);
    if (!$item) { die('<div class="alert alert-danger">Item not found</div>'); }
    ?>
    <div class="ai-card">
        <div class="ai-card-title">Edit Inventory Item</div>
        <div class="ai-card-sub">Update the details for this item</div>
        <hr class="ai-divider mt-0">
        <form method="POST" action="">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category *</label>
                    <input type="text" class="form-control" name="category" value="<?php echo htmlspecialchars($item['category']); ?>" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus *</label>
                    <select class="form-select" name="campus_id" required>
                        <?php foreach ($campuses as $campus): ?>
                            <option value="<?php echo $campus['id']; ?>" <?php echo $campus['id'] == $item['campus_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campus['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quantity *</label>
                    <input type="number" class="form-control" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Condition *</label>
                    <select class="form-select" name="condition" required>
                        <?php foreach (['excellent','good','fair','poor'] as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $item['condition']===$c?'selected':''; ?>><?php echo ucfirst($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status *</label>
                    <select class="form-select" name="status" required>
                        <?php foreach (['available','requested','borrowed','maintenance'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $item['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Location / Building</label>
                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($item['location']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Cost</label>
                    <input type="number" class="form-control" name="cost" value="<?php echo $item['cost']; ?>" step="0.01">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
            </div>
            <div class="mb-4 px-3 py-2" style="background:rgba(139,0,0,0.05);border-radius:10px;">
                <?php $edit_unit_qrs = getItemUnitQRCodes($item); ?>
                <small class="text-muted">QR Codes (<?php echo count($edit_unit_qrs); ?> unit<?php echo count($edit_unit_qrs) > 1 ? 's' : ''; ?>): </small>
                <div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px;">
                    <?php foreach ($edit_unit_qrs as $uqr): ?>
                    <span class="ai-qr-chip"><?php echo htmlspecialchars($uqr); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="inventory.php" class="btn ai-btn-secondary">Cancel</a>
                <button type="submit" class="btn ai-btn-primary"><i class="fas fa-save"></i> Update Item</button>
            </div>
        </form>
    </div>

    <?php elseif ($action === 'add_owned'): ?>
    <!-- Add User-Owned Item Form -->
    <div class="ai-card">
        <div class="ai-card-title">Add User-Owned Item</div>
        <div class="ai-card-sub">Record items owned by users from past years for tracking purposes</div>
        <hr class="ai-divider mt-0">
        <form method="POST" action="?action=add_owned">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">User *</label>
                    <select class="form-select" name="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year Owned *</label>
                    <input type="number" class="form-control" name="year_owned" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" placeholder="e.g., Laptop, Printer" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Quantity *</label>
                    <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Category *</label>
                    <input type="text" class="form-control" name="category" placeholder="e.g., Electronics, Furniture" required>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus *</label>
                    <select class="form-select" name="campus_id" required>
                        <option value="">Select Campus</option>
                        <?php foreach ($campuses as $campus): ?>
                            <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Condition *</label>
                    <select class="form-select" name="condition" required>
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date">
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="2" placeholder="Item details, specifications, etc."></textarea>
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <label class="form-label">Notes / Return Condition</label>
                    <textarea class="form-control" name="notes" rows="3" placeholder="e.g., Returned in good condition, minor scratches on casing, etc."></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="inventory.php?tab=owned" class="btn ai-btn-secondary">Cancel</a>
                <button type="submit" class="btn ai-btn-primary"><i class="fas fa-check"></i> Record Item</button>
            </div>
        </form>
    </div>

    <?php else: ?>

    <?php
    $all_items = getInventory();
    
    // Separate items by status
    $available_items = filterByColumn($all_items, 'status', 'available');
    $requested_items = filterByColumn($all_items, 'status', 'requested');
    $maintenance_items = filterByColumn($all_items, 'status', 'maintenance');
    $borrowed_items = filterByColumn($all_items, 'status', 'borrowed');
    
    // Get user owned items
    $owned_items = getUserOwnedItems();
    $users = getUsers();
    
    usort($available_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($requested_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($maintenance_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($borrowed_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($owned_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    
    $status_colors = ['available'=>'success','requested'=>'info','borrowed'=>'warning','maintenance'=>'info'];
    
    // Pagination settings
    $items_per_page = 6;
    $current_page_available = isset($_GET['page_available']) ? (int)$_GET['page_available'] : 1;
    $current_page_requested = isset($_GET['page_requested']) ? (int)$_GET['page_requested'] : 1;
    $current_page_maintenance = isset($_GET['page_maintenance']) ? (int)$_GET['page_maintenance'] : 1;
    $current_page_borrowed = isset($_GET['page_borrowed']) ? (int)$_GET['page_borrowed'] : 1;
    $current_page_owned = isset($_GET['page_owned']) ? (int)$_GET['page_owned'] : 1;
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'available';
    
    // Calculate pagination
    $total_available = count($available_items);
    $total_requested = count($requested_items);
    $total_maintenance = count($maintenance_items);
    $total_borrowed = count($borrowed_items);
    $total_owned = count($owned_items);
    
    $pages_available = ceil($total_available / $items_per_page);
    $pages_requested = ceil($total_requested / $items_per_page);
    $pages_maintenance = ceil($total_maintenance / $items_per_page);
    $pages_borrowed = ceil($total_borrowed / $items_per_page);
    $pages_owned = ceil($total_owned / $items_per_page);
    
    $offset_available = ($current_page_available - 1) * $items_per_page;
    $offset_requested = ($current_page_requested - 1) * $items_per_page;
    $offset_maintenance = ($current_page_maintenance - 1) * $items_per_page;
    $offset_borrowed = ($current_page_borrowed - 1) * $items_per_page;
    $offset_owned = ($current_page_owned - 1) * $items_per_page;
    
    $available_items_page = array_slice($available_items, $offset_available, $items_per_page);
    $requested_items_page = array_slice($requested_items, $offset_requested, $items_per_page);
    $maintenance_items_page = array_slice($maintenance_items, $offset_maintenance, $items_per_page);
    $borrowed_items_page = array_slice($borrowed_items, $offset_borrowed, $items_per_page);
    $owned_items_page = array_slice($owned_items, $offset_owned, $items_per_page);
    ?>

    <!-- TAB NAVIGATION -->
    <div class="ai-tabs-container">
        <div style="display: flex; gap: 8px; flex: 1; flex-wrap: wrap;">
            <a href="inventory.php?tab=available" class="ai-tab <?php echo $current_tab === 'available' ? 'ai-tab-active' : ''; ?>" onclick="setTab('available'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-boxes-stacked"></i></span>
                <span class="ai-tab-label">Available</span>
                <span class="ai-tab-badge"><?php echo $total_available; ?></span>
            </a>
            <a href="inventory.php?tab=requested" class="ai-tab <?php echo $current_tab === 'requested' ? 'ai-tab-active' : ''; ?>" onclick="setTab('requested'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-clipboard-list"></i></span>
                <span class="ai-tab-label">Requested</span>
                <span class="ai-tab-badge"><?php echo $total_requested; ?></span>
            </a>
            <a href="inventory.php?tab=borrowed" class="ai-tab <?php echo $current_tab === 'borrowed' ? 'ai-tab-active' : ''; ?>" onclick="setTab('borrowed'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-handshake"></i></span>
                <span class="ai-tab-label">Borrowed</span>
                <span class="ai-tab-badge"><?php echo $total_borrowed; ?></span>
            </a>
            <a href="inventory.php?tab=maintenance" class="ai-tab <?php echo $current_tab === 'maintenance' ? 'ai-tab-active' : ''; ?>" onclick="setTab('maintenance'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-tools"></i></span>
                <span class="ai-tab-label">Maintenance</span>
                <span class="ai-tab-badge"><?php echo $total_maintenance; ?></span>
            </a>
            <a href="inventory.php?tab=owned" class="ai-tab <?php echo $current_tab === 'owned' ? 'ai-tab-active' : ''; ?>" onclick="setTab('owned'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-user-check"></i></span>
                <span class="ai-tab-label">User-Owned</span>
                <span class="ai-tab-badge"><?php echo $total_owned; ?></span>
            </a>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="inventory.php?action=add" class="btn ai-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;"><i class="fas fa-plus"></i> Add Item</a>
            <a href="inventory.php?action=add_owned&tab=owned" class="btn ai-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;"><i class="fas fa-user-plus"></i> Add User Item</a>
        </div>
    </div>

    <!-- AVAILABLE ITEMS TAB -->
    <div id="tab-available" style="display: <?php echo $current_tab === 'available' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($available_items_page) > 0):
                foreach ($available_items_page as $item):
                    $ic = getCampus($item['campus_id']);
                    $unit_qrs = getItemUnitQRCodes($item);
        ?>
        <div class="ai-item-card" style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.07); transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #1a1d23; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                </div>
                <span class="ai-badge ai-badge-success" style="font-size: 0.7rem; padding: 4px 8px;"><i class="fas fa-check-circle"></i></span>
            </div>
            
            <div style="border-top: 1px solid rgba(0,0,0,0.07); border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 0; margin: 12px 0; font-size: 0.9rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Quantity</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo $item['quantity']; ?> units</div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Condition</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo ucfirst($item['condition']); ?></div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: space-between;">
                <button type="button" class="ai-btn-sm" style="background: rgba(59,130,246,0.10); color: #1d4ed8; flex: 1; border: none; border-radius: 8px;" onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($unit_qrs)); ?>)">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <a href="inventory.php?action=edit&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-edit" style="flex: 1; text-align: center;"><i class="fas fa-edit"></i></a>
                <a href="inventory.php?action=delete&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-delete delete-btn" style="flex: 1; text-align: center;"><i class="fas fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column: 1 / -1;"><i class="fas fa-box-open"></i>No available items</div>
        <?php endif; ?>
        </div>
        
        <!-- Pagination for Available Items -->
        <?php if ($pages_available > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_available; $i++): ?>
                <a href="inventory.php?tab=available&page_available=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $current_page_available ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- REQUESTED ITEMS TAB -->
    <div id="tab-requested" style="display: <?php echo $current_tab === 'requested' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($requested_items_page) > 0):
                foreach ($requested_items_page as $item):
                    $ic = getCampus($item['campus_id']);
                    $unit_qrs = getItemUnitQRCodes($item);
        ?>
        <div class="ai-item-card" style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.07); transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #1a1d23; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                </div>
                <span class="ai-badge ai-badge-info" style="font-size: 0.7rem; padding: 4px 8px;"><i class="fas fa-list-check"></i></span>
            </div>
            
            <div style="border-top: 1px solid rgba(0,0,0,0.07); border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 0; margin: 12px 0; font-size: 0.9rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Campus</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Quantity</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo $item['quantity']; ?> units</div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Condition</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo ucfirst($item['condition']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Department</div>
                        <div style="font-weight: 600; color: #1a1d23; font-size: 0.9rem;"><?php 
                            if ($item['campus_id'] == 1) {
                                $dept = isset($item['college_id']) && $item['college_id'] ? $item['college_id'] : 'Admin';
                                $depts = array_merge(getMainCampusColleges(), getMainCampusOffices());
                                echo isset($depts[$dept]) ? htmlspecialchars(substr(explode(' (', $depts[$dept])[0], 0, 18)) : htmlspecialchars($dept);
                            }
                        ?></div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: space-between;">
                <button type="button" class="ai-btn-sm" style="background: rgba(59,130,246,0.10); color: #1d4ed8; flex: 1; border: none; border-radius: 8px;" onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($unit_qrs)); ?>)">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <a href="inventory.php?action=edit&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-edit" style="flex: 1; text-align: center;"><i class="fas fa-edit"></i></a>
                <a href="inventory.php?action=delete&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-delete delete-btn" style="flex: 1; text-align: center;"><i class="fas fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column: 1 / -1;"><i class="fas fa-inbox"></i>No requested items</div>
        <?php endif; ?>
        </div>
        
        <!-- Pagination for Requested Items -->
        <?php if ($pages_requested > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_requested; $i++): ?>
                <a href="inventory.php?tab=requested&page_requested=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $current_page_requested ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- BORROWED ITEMS TAB -->
    <div id="tab-borrowed" style="display: <?php echo $current_tab === 'borrowed' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($borrowed_items_page) > 0):
                foreach ($borrowed_items_page as $item):
                    $ic = getCampus($item['campus_id']);
                    $unit_qrs = getItemUnitQRCodes($item);
        ?>
        <div class="ai-item-card" style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.07); transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #1a1d23; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                </div>
                <span class="ai-badge ai-badge-warning" style="font-size: 0.7rem; padding: 4px 8px;"><i class="fas fa-hand-holding-heart"></i></span>
            </div>
            
            <div style="border-top: 1px solid rgba(0,0,0,0.07); border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 0; margin: 12px 0; font-size: 0.9rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Campus</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Quantity</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo $item['quantity']; ?> units</div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Condition</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo ucfirst($item['condition']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Department</div>
                        <div style="font-weight: 600; color: #1a1d23; font-size: 0.9rem;"><?php 
                            if ($item['campus_id'] == 1) {
                                $dept = isset($item['college_id']) && $item['college_id'] ? $item['college_id'] : 'Admin';
                                $depts = array_merge(getMainCampusColleges(), getMainCampusOffices());
                                echo isset($depts[$dept]) ? htmlspecialchars(substr(explode(' (', $depts[$dept])[0], 0, 18)) : htmlspecialchars($dept);
                            }
                        ?></div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: space-between;">
                <button type="button" class="ai-btn-sm" style="background: rgba(59,130,246,0.10); color: #1d4ed8; flex: 1; border: none; border-radius: 8px;" onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($unit_qrs)); ?>)">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <a href="inventory.php?action=edit&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-edit" style="flex: 1; text-align: center;"><i class="fas fa-edit"></i></a>
                <a href="inventory.php?action=delete&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-delete delete-btn" style="flex: 1; text-align: center;"><i class="fas fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column: 1 / -1;"><i class="fas fa-hand-holding-heart"></i>No borrowed items</div>
        <?php endif; ?>
        </div>
        
        <!-- Pagination for Borrowed Items -->
        <?php if ($pages_borrowed > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_borrowed; $i++): ?>
                <a href="inventory.php?tab=borrowed&page_borrowed=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $current_page_borrowed ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- MAINTENANCE ITEMS TAB -->
    <div id="tab-maintenance" style="display: <?php echo $current_tab === 'maintenance' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($maintenance_items_page) > 0):
                foreach ($maintenance_items_page as $item):
                    $ic = getCampus($item['campus_id']);
                    $unit_qrs = getItemUnitQRCodes($item);
        ?>
        <div class="ai-item-card" style="background: rgba(255,255,255,0.72); backdrop-filter: blur(16px); border: 1px solid rgba(0,0,0,0.07); border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.07); transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #1a1d23; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                </div>
                <span class="ai-badge ai-badge-warning" style="font-size: 0.7rem; padding: 4px 8px;"><i class="fas fa-wrench"></i></span>
            </div>
            
            <div style="border-top: 1px solid rgba(0,0,0,0.07); border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 0; margin: 12px 0; font-size: 0.9rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Campus</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Quantity</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo $item['quantity']; ?> units</div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Condition</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo ucfirst($item['condition']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Department</div>
                        <div style="font-weight: 600; color: #1a1d23; font-size: 0.9rem;"><?php 
                            if ($item['campus_id'] == 1) {
                                $dept = isset($item['college_id']) && $item['college_id'] ? $item['college_id'] : 'Admin';
                                $depts = array_merge(getMainCampusColleges(), getMainCampusOffices());
                                echo isset($depts[$dept]) ? htmlspecialchars(substr(explode(' (', $depts[$dept])[0], 0, 18)) : htmlspecialchars($dept);
                            }
                        ?></div>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: space-between;">
                <button type="button" class="ai-btn-sm" style="background: rgba(59,130,246,0.10); color: #1d4ed8; flex: 1; border: none; border-radius: 8px;" onclick="openDetailModal(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($unit_qrs)); ?>)">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <a href="inventory.php?action=edit&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-edit" style="flex: 1; text-align: center;"><i class="fas fa-edit"></i></a>
                <a href="inventory.php?action=delete&id=<?php echo $item['id']; ?>" class="ai-btn-sm ai-btn-delete delete-btn" style="flex: 1; text-align: center;"><i class="fas fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column: 1 / -1;"><i class="fas fa-tools"></i>No items in maintenance</div>
        <?php endif; ?>
        </div>
        
        <!-- Pagination for Maintenance Items -->
        <?php if ($pages_maintenance > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_maintenance; $i++): ?>
                <a href="inventory.php?tab=maintenance&page_maintenance=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $current_page_maintenance ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- USER-OWNED ITEMS TAB -->
    <div id="tab-owned" style="display: <?php echo $current_tab === 'owned' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($owned_items_page) > 0):
                foreach ($owned_items_page as $item):
                    $owner_user = findById($users, $item['user_id']);
                    $owner_name = $owner_user ? htmlspecialchars($owner_user['full_name']) : 'Unknown User';
                    $campus_info = getCampus($item['campus_id']);
        ?>
        <div class="ai-item-card" style="background: linear-gradient(135deg, rgba(59,130,246,0.08) 0%, rgba(255,255,255,0.72) 100%); backdrop-filter: blur(16px); border: 1px solid rgba(59,130,246,0.15); border-radius: 16px; padding: 20px; box-shadow: 0 4px 12px rgba(59,130,246,0.10); transition: all 0.2s;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                <div>
                    <div style="font-weight: 800; font-size: 1rem; color: #1a1d23; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($item['item_name']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo htmlspecialchars($item['category']); ?>
                    </div>
                </div>
                <span class="ai-badge" style="background: rgba(59,130,246,0.15); color: #1d4ed8; font-size: 0.7rem; padding: 4px 8px;"><i class="fas fa-user-check me-1"></i><?php echo $item['year_owned']; ?></span>
            </div>
            
            <div style="border-top: 1px solid rgba(0,0,0,0.07); border-bottom: 1px solid rgba(0,0,0,0.07); padding: 12px 0; margin: 12px 0; font-size: 0.9rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 8px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Owner</div>
                        <div style="font-weight: 600; color: #1a1d23; font-size: 0.9rem;"><i class="fas fa-user-circle me-1" style="color: #3b82f6;"></i><?php echo $owner_name; ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Quantity</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo $item['quantity']; ?> unit<?php echo $item['quantity'] > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Campus</div>
                        <div style="font-weight: 600; color: #1a1d23; font-size: 0.9rem;"><?php echo htmlspecialchars($campus_info['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase;">Condition</div>
                        <div style="font-weight: 600; color: #1a1d23;"><?php echo ucfirst($item['condition']); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($item['notes'])): ?>
            <div style="background: rgba(34,197,94,0.08); border-left: 3px solid #22c55e; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 0.85rem; color: #374151;">
                <div style="font-weight: 600; color: #15803d; margin-bottom: 4px; font-size: 0.75rem; text-transform: uppercase;">Notes</div>
                <?php echo htmlspecialchars($item['notes']); ?>
            </div>
            <?php endif; ?>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="ai-btn-sm" style="background: rgba(59,130,246,0.10); color: #1d4ed8; flex: 1; border: none; border-radius: 8px; cursor: pointer;" data-bs-toggle="modal" data-bs-target="#ownedItemModal" onclick="showOwnedItemDetails(<?php echo htmlspecialchars(json_encode($item)); ?>, <?php echo htmlspecialchars(json_encode($owner_name)); ?>)">
                    <i class="fas fa-info-circle"></i> Details
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column: 1 / -1;">
            <i class="fas fa-user-circle"></i>
            <p>No user-owned items recorded yet</p>
            <small style="color: rgba(0,0,0,0.40);">Start tracking user-owned items from past years</small>
        </div>
        <?php endif; ?>
        </div>
        
        <!-- Pagination for Owned Items -->
        <?php if ($pages_owned > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_owned; $i++): ?>
                <a href="inventory.php?tab=owned&page_owned=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $current_page_owned ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Item Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="border-radius:18px; border:1px solid rgba(0,0,0,0.07);">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,0.07);">
                <div>
                    <h5 class="modal-title" id="detailModalTitle" style="font-size:1.1rem; font-weight:700; margin-bottom: 4px;"></h5>
                    <small id="detailModalCategory" style="color: rgba(0,0,0,0.50);"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Item Information -->
                <div style="background: rgba(0,0,0,0.03); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Campus</div>
                            <div id="detailCampus" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Quantity</div>
                            <div id="detailQuantity" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Condition</div>
                            <div id="detailCondition" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Cost</div>
                            <div id="detailCost" style="font-weight: 600; font-size: 0.95rem;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Location</div>
                        <div id="detailLocation" style="font-weight: 600; font-size: 0.95rem;"></div>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.50); margin-bottom: 8px;">Description</div>
                    <div id="detailDescription" style="font-size: 0.9rem; color: #374151; line-height: 1.5;"></div>
                </div>

                <!-- QR Codes Section -->
                <div style="border-top: 1px solid rgba(0,0,0,0.07); padding-top: 20px;">
                    <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.50); margin-bottom: 12px;"><i class="fas fa-qrcode me-2" style="color: #8B0000;"></i>QR Codes</div>
                    <div id="qrCodesGrid" class="row g-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ai-qr-units-btn {
    background: rgba(139,0,0,0.08); border: 1px solid rgba(139,0,0,0.18);
    border-radius: 8px; color: #8B0000; font-size: 0.70rem; font-weight: 700;
    padding: 2px 7px; cursor: pointer; margin-left: 4px;
    transition: background 0.15s;
}
.ai-qr-units-btn:hover { background: rgba(139,0,0,0.16); }

.ai-tabs-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 32px;
    padding-bottom: 0;
    border-bottom: 1px solid rgba(0,0,0,0.08);
    background: linear-gradient(135deg, rgba(255,255,255,0.5) 0%, rgba(59,130,246,0.02) 100%);
    border-radius: 16px 16px 0 0;
    padding: 8px;
    flex-wrap: wrap;
}

.ai-tab {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    color: rgba(0,0,0,0.55);
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    border-bottom: none;
    border-radius: 12px 12px 0 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    white-space: nowrap;
    position: relative;
    background: transparent;
}

.ai-tab-icon {
    display: flex;
    align-items: center;
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.ai-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    background: rgba(0,0,0,0.08);
    color: rgba(0,0,0,0.70);
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 700;
    margin-left: 4px;
    transition: all 0.3s ease;
}

.ai-tab:hover {
    color: rgba(0,0,0,0.75);
    background: rgba(0,0,0,0.06);
}

.ai-tab:hover .ai-tab-icon {
    transform: scale(1.15);
}

.ai-tab:hover .ai-tab-badge {
    background: rgba(0,0,0,0.12);
}

.ai-tab-active {
    color: #1d4ed8;
    background: linear-gradient(135deg, rgba(29,78,216,0.08) 0%, rgba(59,130,246,0.06) 100%);
    border-bottom: 3px solid #1d4ed8;
    box-shadow: 0 4px 12px rgba(29,78,216,0.12);
}

.ai-tab-active .ai-tab-icon {
    color: #1d4ed8;
    transform: scale(1.1);
}

.ai-tab-active .ai-tab-badge {
    background: rgba(29,78,216,0.15);
    color: #1d4ed8;
    font-weight: 800;
}
</style>

<script>
function openDetailModal(item, units) {
    // Populate header
    document.getElementById('detailModalTitle').textContent = item.item_name;
    document.getElementById('detailModalCategory').textContent = item.category + ' • ' + item.created_at;
    
    // Populate info
    var campuses = <?php echo json_encode(getAllCampuses()); ?>;
    var campus = campuses.find(c => c.id == item.campus_id);
    document.getElementById('detailCampus').textContent = campus ? campus.name : 'Unknown';
    document.getElementById('detailQuantity').textContent = item.quantity + ' units';
    document.getElementById('detailCondition').textContent = item.condition.charAt(0).toUpperCase() + item.condition.slice(1);
    document.getElementById('detailCost').textContent = item.cost ? '₱' + parseFloat(item.cost).toFixed(2) : 'N/A';
    document.getElementById('detailLocation').textContent = item.location;
    document.getElementById('detailDescription').textContent = item.description || 'No description available';
    
    // Generate QR codes grid
    var grid = document.getElementById('qrCodesGrid');
    grid.innerHTML = '';
    var apiBase = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=';
    
    units.forEach(function(qr, idx) {
        var col = document.createElement('div');
        col.className = 'col-6 col-md-4 text-center';
        col.innerHTML = '<div style="background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:12px;padding:16px;transition: all 0.2s;">'
            + '<img src="' + apiBase + encodeURIComponent(qr) + '" alt="' + qr + '" style="width:120px;height:120px;border-radius:6px; object-fit: contain; margin-bottom: 8px;">'
            + '<div style="font-size:0.7rem;font-family:monospace;word-break:break-all;margin:8px 0;color:rgba(0,0,0,0.50);background:rgba(0,0,0,0.03);padding:6px;border-radius:4px;">' + qr + '</div>'
            + '<div style="font-size:0.8rem;font-weight:700;color:#8B0000;margin-top:4px;">Unit ' + (idx+1) + '</div>'
            + '</div>';
        grid.appendChild(col);
    });
    
    // Show modal
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

function openQRModal(itemId, itemName, units) {
    document.getElementById('qrUnitsModalTitle').innerHTML = '<i class="fas fa-qrcode me-2 text-danger"></i>' + itemName + ' &mdash; ' + units.length + ' Units';
    var grid = document.getElementById('qrUnitsGrid');
    grid.innerHTML = '';
    var apiBase = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=';
    units.forEach(function(qr, idx) {
        var col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3 text-center';
        col.innerHTML = '<div style="background:#fff;border:1px solid rgba(0,0,0,0.08);border-radius:12px;padding:12px;">'
            + '<img src="' + apiBase + encodeURIComponent(qr) + '" alt="' + qr + '" style="width:100px;height:100px;border-radius:6px;">'
            + '<div style="font-size:0.65rem;font-family:monospace;word-break:break-all;margin-top:6px;color:rgba(0,0,0,0.50);">' + qr + '</div>'
            + '<div style="font-size:0.70rem;font-weight:700;color:#8B0000;margin-top:3px;">Unit ' + (idx+1) + '</div>'
            + '</div>';
        grid.appendChild(col);
    });
    new bootstrap.Modal(document.getElementById('qrUnitsModal')).show();
}

// Toggle tabs
function setTab(tabName) {
    // Remove active class from all tab buttons
    var tabButtons = document.querySelectorAll('.ai-tabs-container .ai-tab');
    tabButtons.forEach(function(btn) {
        btn.classList.remove('ai-tab-active');
    });
    
    // Add active class to clicked tab button
    var activeTab = document.querySelector('a[href="inventory.php?tab=' + tabName + '"]');
    if (activeTab) {
        activeTab.classList.add('ai-tab-active');
    }
    
    // Hide all content tabs
    document.getElementById('tab-available').style.display = 'none';
    document.getElementById('tab-requested').style.display = 'none';
    document.getElementById('tab-borrowed').style.display = 'none';
    document.getElementById('tab-maintenance').style.display = 'none';
    document.getElementById('tab-owned').style.display = 'none';
    
    // Show selected content tab
    document.getElementById('tab-' + tabName).style.display = 'block';
    
    // Update URL without reload
    window.history.pushState({tab: tabName}, '', 'inventory.php?tab=' + tabName);
}

function showOwnedItemDetails(item, ownerName) {
    var modalElement = document.getElementById('ownedItemModal');
    var modal = new bootstrap.Modal(modalElement);
    
    // Populate modal
    document.getElementById('ownedItemModalTitle').textContent = item.item_name;
    document.getElementById('ownedItemModalCategory').textContent = item.category + ' • Owned in ' + item.year_owned;
    
    document.getElementById('ownedItemOwner').textContent = ownerName;
    document.getElementById('ownedItemYear').textContent = item.year_owned;
    document.getElementById('ownedItemQuantity').textContent = item.quantity + ' unit' + (item.quantity > 1 ? 's' : '');
    document.getElementById('ownedItemCondition').textContent = item.condition.charAt(0).toUpperCase() + item.condition.slice(1);
    
    var campuses = <?php echo json_encode(getAllCampuses()); ?>;
    var campus = campuses.find(c => c.id == item.campus_id);
    document.getElementById('ownedItemCampus').textContent = campus ? campus.name : 'Unknown Campus';
    
    document.getElementById('ownedItemDescription').textContent = item.description || 'No description provided';
    document.getElementById('ownedItemNotes').parentElement.style.display = item.notes ? 'block' : 'none';
    document.getElementById('ownedItemNotes').textContent = item.notes || '';
    
    if (item.purchase_date) {
        document.getElementById('ownedItemPurchaseDate').textContent = new Date(item.purchase_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        document.getElementById('ownedItemPurchaseDateRow').style.display = 'block';
    } else {
        document.getElementById('ownedItemPurchaseDateRow').style.display = 'none';
    }
    
    // Handle proper modal cleanup on close
    modalElement.addEventListener('hidden.bs.modal', function() {
        // Remove modal backdrop if it exists
        var backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        // Remove modal-open class from body
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }, { once: true });
    
    modal.show();
}
</script>

<!-- User-Owned Item Detail Modal -->
<div class="modal fade" id="ownedItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:18px; border:1px solid rgba(0,0,0,0.07);">
            <div class="modal-header" style="border-bottom:1px solid rgba(0,0,0,0.07); background: linear-gradient(135deg, rgba(59,130,246,0.05) 0%, rgba(255,255,255,0.5) 100%);">
                <div>
                    <h5 class="modal-title" id="ownedItemModalTitle" style="font-size:1.15rem; font-weight:700; margin-bottom: 4px;"></h5>
                    <small id="ownedItemModalCategory" style="color: rgba(0,0,0,0.50);"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 28px;">
                <!-- Owner Info -->
                <div style="background: rgba(59,130,246,0.08); border-left: 4px solid #3b82f6; padding: 16px; border-radius: 10px; margin-bottom: 24px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.5px;">Owner</div>
                            <div id="ownedItemOwner" style="font-weight: 700; font-size: 1rem; color: #1a1d23;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.5px;">Year Owned</div>
                            <div id="ownedItemYear" style="font-weight: 700; font-size: 1rem; color: #1a1d23;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Item Details Section -->
                <div style="margin-bottom: 24px;">
                    <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.50); margin-bottom: 12px;">Item Details</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px;">
                        <div>
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; margin-bottom: 4px;">Quantity</div>
                            <div id="ownedItemQuantity" style="font-weight: 600; color: #1a1d23;"></div>
                        </div>
                        <div>
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; margin-bottom: 4px;">Condition</div>
                            <div id="ownedItemCondition" style="font-weight: 600; color: #1a1d23;"></div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; margin-bottom: 4px;">Campus</div>
                            <div id="ownedItemCampus" style="font-weight: 600; color: #1a1d23;"></div>
                        </div>
                        <div id="ownedItemPurchaseDateRow">
                            <div style="font-size: 0.7rem; color: rgba(0,0,0,0.50); text-transform: uppercase; margin-bottom: 4px;">Purchase Date</div>
                            <div id="ownedItemPurchaseDate" style="font-weight: 600; color: #1a1d23;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Description -->
                <div style="margin-bottom: 20px;">
                    <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(0,0,0,0.50); margin-bottom: 8px;">Description</div>
                    <div id="ownedItemDescription" style="font-size: 0.9rem; color: #374151; line-height: 1.6; background: rgba(0,0,0,0.03); padding: 12px; border-radius: 8px;"></div>
                </div>
                
                <!-- Notes Section -->
                <div style="background: rgba(34,197,94,0.08); border-left: 4px solid #22c55e; padding: 14px; border-radius: 8px;">
                    <div style="font-size: 0.7rem; color: #15803d; text-transform: uppercase; font-weight: 700; margin-bottom: 6px; letter-spacing: 0.5px;"><i class="fas fa-sticky-note me-2"></i>Notes</div>
                    <div id="ownedItemNotes" style="font-size: 0.9rem; color: #374151; line-height: 1.6;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
