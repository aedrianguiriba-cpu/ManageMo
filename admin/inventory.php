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
        $item_name    = sanitizeInput($_POST['item_name']);
        $qty          = max(1, (int)$_POST['quantity']);
        $base_data    = [
            'item_name'     => $item_name,
            'category'      => sanitizeInput($_POST['category']),
            'description'   => sanitizeInput($_POST['description']),
            'campus_id'     => (int)$_POST['campus_id'],
            'quantity'      => 1,
            'location'      => sanitizeInput($_POST['location']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']) ?: null,
            'cost'          => is_numeric($_POST['cost'] ?? '') ? (float)$_POST['cost'] : null,
            'condition'     => sanitizeInput($_POST['condition']),
            'status'        => 'available',
        ];
        $first_id = null;
        for ($u = 0; $u < $qty; $u++) {
            $row = dbCreateInventory(array_merge($base_data, ['qr_code_id' => generateQRCodeId()]));
            if ($u === 0) $first_id = $row['id'] ?? 0;
        }
        logActivity($current_user['id'], 'CREATE', "Added $qty unit(s) of inventory item: $item_name", 'inventory', $first_id);
        redirectWithMessage('inventory.php', "$qty unit(s) of '$item_name' added successfully!", 'success');

    } elseif ($action === 'add_units') {
        $ref_id = (int)$_POST['ref_id'];
        $qty    = max(1, (int)$_POST['quantity']);
        $ref_item = findById(getInventory(), $ref_id);
        if (!$ref_item) {
            redirectWithMessage('inventory.php', 'Reference item not found.', 'danger');
        }
        $base_data = [
            'item_name'     => $ref_item['item_name'],
            'category'      => $ref_item['category'],
            'description'   => $ref_item['description'],
            'campus_id'     => $ref_item['campus_id'],
            'quantity'      => 1,
            'location'      => $ref_item['location'],
            'purchase_date' => !empty($_POST['purchase_date']) ? sanitizeInput($_POST['purchase_date']) : ($ref_item['purchase_date'] ?? null),
            'cost'          => $ref_item['cost'],
            'condition'     => sanitizeInput($_POST['condition']),
            'status'        => 'available',
        ];
        for ($u = 0; $u < $qty; $u++) {
            dbCreateInventory(array_merge($base_data, ['qr_code_id' => generateQRCodeId()]));
        }
        $item_name = $ref_item['item_name'];
        logActivity($current_user['id'], 'CREATE', "Added $qty unit(s) to existing item: $item_name", 'inventory', $ref_id);
        redirectWithMessage('inventory.php', "$qty unit(s) added to '$item_name' successfully!", 'success');

    } elseif ($action === 'add_owned') {
        $item_name = sanitizeInput($_POST['item_name']);
        $user_id   = (int)$_POST['user_id'];
        $qty       = max(1, (int)($_POST['quantity'] ?? 1));
        $base_owned = [
            'user_id'       => $user_id,
            'item_name'     => $item_name,
            'category'      => sanitizeInput($_POST['category']),
            'description'   => sanitizeInput($_POST['description']),
            'campus_id'     => (int)$_POST['campus_id'],
            'year_owned'    => (int)$_POST['year_owned'] ?: null,
            'quantity'      => 1,
            'condition'     => sanitizeInput($_POST['condition']),
            'notes'         => sanitizeInput($_POST['notes']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']) ?: null,
        ];
        for ($u = 0; $u < $qty; $u++) {
            dbCreateUserOwnedItem($base_owned);
        }
        logActivity($current_user['id'], 'CREATE', "Added $qty unit(s) of user owned item: $item_name for user_id: $user_id", 'user_owned_items', $user_id);
        redirectWithMessage('inventory.php?tab=owned', "$qty unit(s) of '$item_name' recorded successfully!", 'success');

    } elseif ($action === 'edit_owned') {
        $owned_id  = (int)$_GET['id'];
        $item_name = sanitizeInput($_POST['item_name']);
        $qty       = max(1, (int)$_POST['quantity']);
        $unit_data = [
            'item_name'     => $item_name,
            'category'      => sanitizeInput($_POST['category']),
            'description'   => sanitizeInput($_POST['description']),
            'campus_id'     => (int)$_POST['campus_id'],
            'year_owned'    => (int)$_POST['year_owned'] ?: null,
            'quantity'      => 1,
            'condition'     => sanitizeInput($_POST['condition']),
            'notes'         => sanitizeInput($_POST['notes']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']) ?: null,
        ];
        // Update the existing unit row
        dbUpdateUserOwnedItem($owned_id, $unit_data);
        // Create additional rows for extra units
        $existing_item = findById(getUserOwnedItems(), $owned_id);
        $user_id = $existing_item['user_id'] ?? 0;
        for ($u = 1; $u < $qty; $u++) {
            dbCreateUserOwnedItem(array_merge($unit_data, ['user_id' => $user_id]));
        }
        $msg = $qty > 1 ? "Unit updated and " . ($qty - 1) . " new unit(s) added for '$item_name'." : "Item updated successfully!";
        logActivity($current_user['id'], 'UPDATE', "Updated user-owned item: $item_name (qty: $qty)", 'user_owned_items', $owned_id);
        redirectWithMessage('inventory.php?tab=owned', $msg, 'success');

    } elseif ($action === 'edit') {
        $inventory_id = (int)$_GET['id'];
        $item_name    = sanitizeInput($_POST['item_name']);
        dbUpdateInventory($inventory_id, [
            'item_name'   => $item_name,
            'category'    => sanitizeInput($_POST['category']),
            'description' => sanitizeInput($_POST['description']),
            'campus_id'   => (int)$_POST['campus_id'],
            'quantity'    => max(0, (int)$_POST['quantity']),
            'location'    => sanitizeInput($_POST['location']),
            'condition'   => sanitizeInput($_POST['condition']),
            'status'      => sanitizeInput($_POST['status']),
        ]);
        logActivity($current_user['id'], 'UPDATE', "Updated inventory item: $item_name", 'inventory', $inventory_id);
        redirectWithMessage('inventory.php', 'Item updated successfully!', 'success');

    } elseif ($action === 'delete') {
        $inventory_id = (int)$_GET['id'];
        dbDeleteInventory($inventory_id);
        logActivity($current_user['id'], 'DELETE', "Deleted inventory item #$inventory_id", 'inventory', $inventory_id);
        redirectWithMessage('inventory.php', 'Item deleted successfully!', 'success');
    }
}

// Load shared data needed by multiple form views
$campuses = getAllCampuses();
$users    = filterByColumn(getUsers(), 'role', ROLE_USER);

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
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
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
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    padding:16px 20px; margin-bottom:16px;
    display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;
}
.ai-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(0,0,0,0.36); margin-bottom:5px; }

/* Buttons */
.ai-btn-primary {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:9px 18px !important; font-size:0.87rem !important;
    box-shadow:none !important;
    transition:background 0.15s !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:7px;
}
.ai-btn-primary:hover { color:#fff !important; background:#6B0000 !important; }
.ai-btn-secondary {
    background:#f7f7f7 !important; border:1px solid #e5e7eb !important;
    border-radius:6px !important; font-weight:600 !important; color:#555 !important;
    padding:9px 16px !important; font-size:0.87rem !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:7px;
}
.ai-btn-secondary:hover { color:#111 !important; background:#eee !important; }

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
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
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
    padding:3px 8px; border-radius:4px; font-size:0.74rem; font-weight:700;
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

/* Mobile: stack inline 2-col grids inside cards */
@media(max-width:576px) {
    [style*="grid-template-columns: 1fr 1fr"],
    [style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
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
            <!-- campus_id is always 1 — all inventory originates from the supply office -->
            <input type="hidden" name="campus_id" value="1">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category *</label>
                    <select class="form-select" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Cleaning Supplies">Cleaning Supplies</option>
                        <option value="Medical &amp; Safety">Medical &amp; Safety</option>
                        <option value="Sports &amp; Recreation">Sports &amp; Recreation</option>
                        <option value="Tools &amp; Hardware">Tools &amp; Hardware</option>
                        <option value="Books &amp; Publications">Books &amp; Publications</option>
                        <option value="Laboratory Supplies">Laboratory Supplies</option>
                        <option value="Electrical Supplies">Electrical Supplies</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Quantity *</label>
                    <input type="number" class="form-control" name="quantity" value="1" min="1" required>
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
                    <label class="form-label">Cost</label>
                    <input type="number" class="form-control" name="cost" step="0.01" placeholder="0.00">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Location / Building</label>
                    <input type="text" class="form-control" name="location">
                </div>
            </div>
            <div class="row g-3 mb-3">
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
            <div class="mb-4 px-3 py-2" style="background:rgba(139,0,0,0.05);border-radius:6px;border:1px solid rgba(139,0,0,0.08);">
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

    <?php elseif ($action === 'edit_owned'): ?>
    <!-- Edit User-Owned Item Form -->
    <?php
        $owned_id   = (int)sanitizeInput($_GET['id']);
        $owned_item = findById(getUserOwnedItems(), $owned_id);
        if (!$owned_item) { die('<div class="alert alert-danger">Item not found.</div>'); }
        $owned_owner = findById($users, $owned_item['user_id']);
    ?>
    <div class="ai-card">
        <div class="ai-card-title">Edit User-Owned Item</div>
        <div class="ai-card-sub">
            Unit owned by <strong><?php echo htmlspecialchars($owned_owner['full_name'] ?? 'Unknown'); ?></strong>
        </div>
        <hr class="ai-divider mt-0">
        <form method="POST" action="?action=edit_owned&id=<?php echo $owned_id; ?>">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" value="<?php echo htmlspecialchars($owned_item['item_name']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category *</label>
                    <select class="form-select" name="category" required>
                        <option value="">Select Category</option>
                        <?php
                        $cats = ['Office Supplies','Furniture','Electronics','Equipment','Cleaning Supplies','Medical & Safety','Sports & Recreation','Tools & Hardware','Books & Publications','Laboratory Supplies','Electrical Supplies','Other'];
                        foreach ($cats as $cat):
                        ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $owned_item['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Year Owned *</label>
                    <input type="number" class="form-control" name="year_owned" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo $owned_item['year_owned']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantity *</label>
                    <input type="number" class="form-control" name="quantity" min="1" value="<?php echo (int)$owned_item['quantity']; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Condition *</label>
                    <select class="form-select" name="condition" required>
                        <?php foreach (['excellent','good','fair','poor'] as $c): ?>
                        <option value="<?php echo $c; ?>" <?php echo $owned_item['condition'] === $c ? 'selected' : ''; ?>><?php echo ucfirst($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus *</label>
                    <select class="form-select" name="campus_id" required>
                        <?php foreach ($campuses as $campus): ?>
                        <option value="<?php echo $campus['id']; ?>" <?php echo $campus['id'] == $owned_item['campus_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($campus['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" name="purchase_date" value="<?php echo htmlspecialchars($owned_item['purchase_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($owned_item['description'] ?? ''); ?></textarea>
            </div>
            <div class="mb-4">
                <label class="form-label">Notes / Return Condition</label>
                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($owned_item['notes'] ?? ''); ?></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="inventory.php?tab=owned" class="btn ai-btn-secondary">Cancel</a>
                <button type="submit" class="btn ai-btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <?php elseif ($action === 'add_owned'): ?>
    <!-- Add User-Owned Item Form -->
    <div class="ai-card">
        <div class="ai-card-title">Add User-Owned Item</div>
        <div class="ai-card-sub">Record items owned by users from past years for tracking purposes</div>
        <hr class="ai-divider mt-0">
        <!-- User-to-campus map for auto-fill -->
        <script>
        var userCampusMap = <?php
            $map = [];
            foreach ($users as $u) $map[$u['id']] = $u['campus_id'];
            echo json_encode($map);
        ?>;
        </script>
        <form method="POST" action="?action=add_owned">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">User *</label>
                    <select class="form-select" name="user_id" id="ownedUserId" required onchange="fillUserCampus(this.value)">
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
                    <select class="form-select" name="category" required>
                        <option value="">Select Category</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Cleaning Supplies">Cleaning Supplies</option>
                        <option value="Medical &amp; Safety">Medical &amp; Safety</option>
                        <option value="Sports &amp; Recreation">Sports &amp; Recreation</option>
                        <option value="Tools &amp; Hardware">Tools &amp; Hardware</option>
                        <option value="Books &amp; Publications">Books &amp; Publications</option>
                        <option value="Laboratory Supplies">Laboratory Supplies</option>
                        <option value="Electrical Supplies">Electrical Supplies</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus *</label>
                    <select class="form-select" name="campus_id" id="ownedCampusId" required>
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
    <script>
    function fillUserCampus(userId) {
        var campusId = userCampusMap[userId];
        if (campusId) {
            document.getElementById('ownedCampusId').value = campusId;
        } else {
            document.getElementById('ownedCampusId').value = '';
        }
    }
    </script>

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
    
    usort($available_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($requested_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($maintenance_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($borrowed_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($owned_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });

    $status_colors = ['available'=>'success','requested'=>'info','borrowed'=>'warning','maintenance'=>'info'];

    // Group same-name items into cards; each group contains individual unit rows
    $grouped_available   = groupInventoryItems($available_items);
    $grouped_requested   = groupInventoryItems($requested_items);
    $grouped_maintenance = groupInventoryItems($maintenance_items);
    $grouped_borrowed    = groupInventoryItems($borrowed_items);
    $grouped_owned       = groupOwnedItems($owned_items);

    // Pagination settings
    $items_per_page = 6;
    $current_page_available = isset($_GET['page_available']) ? (int)$_GET['page_available'] : 1;
    $current_page_requested = isset($_GET['page_requested']) ? (int)$_GET['page_requested'] : 1;
    $current_page_maintenance = isset($_GET['page_maintenance']) ? (int)$_GET['page_maintenance'] : 1;
    $current_page_borrowed = isset($_GET['page_borrowed']) ? (int)$_GET['page_borrowed'] : 1;
    $current_page_owned = isset($_GET['page_owned']) ? (int)$_GET['page_owned'] : 1;
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'available';

    // Tab badges count units; pagination is over groups
    $total_available = count($available_items);
    $total_requested = count($requested_items);
    $total_maintenance = count($maintenance_items);
    $total_borrowed = count($borrowed_items);
    $total_owned = count($owned_items);

    $pages_available = ceil(count($grouped_available) / $items_per_page);
    $pages_requested = ceil(count($grouped_requested) / $items_per_page);
    $pages_maintenance = ceil(count($grouped_maintenance) / $items_per_page);
    $pages_borrowed = ceil(count($grouped_borrowed) / $items_per_page);
    $pages_owned = ceil(count($grouped_owned) / $items_per_page);

    $offset_available = ($current_page_available - 1) * $items_per_page;
    $offset_requested = ($current_page_requested - 1) * $items_per_page;
    $offset_maintenance = ($current_page_maintenance - 1) * $items_per_page;
    $offset_borrowed = ($current_page_borrowed - 1) * $items_per_page;
    $offset_owned = ($current_page_owned - 1) * $items_per_page;

    $available_items_page   = array_slice($grouped_available, $offset_available, $items_per_page);
    $requested_items_page   = array_slice($grouped_requested, $offset_requested, $items_per_page);
    $maintenance_items_page = array_slice($grouped_maintenance, $offset_maintenance, $items_per_page);
    $borrowed_items_page    = array_slice($grouped_borrowed, $offset_borrowed, $items_per_page);
    $owned_items_page       = array_slice($grouped_owned, $offset_owned, $items_per_page);
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
                foreach ($available_items_page as $group):
                    $ic = getCampus($group['campus_id']);
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span style="background:rgba(34,197,94,0.12);color:#15803d;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Campus</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px;min-height:22px;">
                <?php foreach (array_slice($group['units'], 0, 2) as $u): ?>
                <span class="ai-qr-chip" style="font-size:0.68rem;"><?php echo htmlspecialchars($u['qr_code_id']); ?></span>
                <?php endforeach; ?>
                <?php if ($unit_count > 2): ?><span style="font-size:0.7rem;color:rgba(0,0,0,0.40);align-self:center;">+<?php echo $unit_count - 2; ?> more</span><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, <?php echo htmlspecialchars(json_encode($ic)); ?>)">
                    <i class="fas fa-eye"></i> View &amp; Manage
                </button>
                <button type="button" class="ai-btn-sm" style="background:rgba(34,197,94,0.10);color:#15803d;border:none;border-radius:8px;white-space:nowrap;"
                    onclick="openAddUnitsModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                    <i class="fas fa-plus"></i> Add Units
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-box-open"></i>No available items</div>
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
                foreach ($requested_items_page as $group):
                    $ic = getCampus($group['campus_id']);
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span style="background:rgba(59,130,246,0.12);color:#1d4ed8;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Campus</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px;min-height:22px;">
                <?php foreach (array_slice($group['units'], 0, 2) as $u): ?>
                <span class="ai-qr-chip" style="font-size:0.68rem;"><?php echo htmlspecialchars($u['qr_code_id']); ?></span>
                <?php endforeach; ?>
                <?php if ($unit_count > 2): ?><span style="font-size:0.7rem;color:rgba(0,0,0,0.40);align-self:center;">+<?php echo $unit_count - 2; ?> more</span><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, <?php echo htmlspecialchars(json_encode($ic)); ?>)">
                    <i class="fas fa-eye"></i> View &amp; Manage
                </button>
                <button type="button" class="ai-btn-sm" style="background:rgba(34,197,94,0.10);color:#15803d;border:none;border-radius:8px;white-space:nowrap;"
                    onclick="openAddUnitsModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                    <i class="fas fa-plus"></i> Add Units
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-inbox"></i>No requested items</div>
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
                foreach ($borrowed_items_page as $group):
                    $ic = getCampus($group['campus_id']);
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span style="background:rgba(245,158,11,0.12);color:#b45309;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Campus</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px;min-height:22px;">
                <?php foreach (array_slice($group['units'], 0, 2) as $u): ?>
                <span class="ai-qr-chip" style="font-size:0.68rem;"><?php echo htmlspecialchars($u['qr_code_id']); ?></span>
                <?php endforeach; ?>
                <?php if ($unit_count > 2): ?><span style="font-size:0.7rem;color:rgba(0,0,0,0.40);align-self:center;">+<?php echo $unit_count - 2; ?> more</span><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, <?php echo htmlspecialchars(json_encode($ic)); ?>)">
                    <i class="fas fa-eye"></i> View &amp; Manage
                </button>
                <button type="button" class="ai-btn-sm" style="background:rgba(34,197,94,0.10);color:#15803d;border:none;border-radius:8px;white-space:nowrap;"
                    onclick="openAddUnitsModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                    <i class="fas fa-plus"></i> Add Units
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-hand-holding-heart"></i>No borrowed items</div>
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
                foreach ($maintenance_items_page as $group):
                    $ic = getCampus($group['campus_id']);
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span style="background:rgba(245,158,11,0.12);color:#b45309;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Campus</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($ic['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:4px;min-height:22px;">
                <?php foreach (array_slice($group['units'], 0, 2) as $u): ?>
                <span class="ai-qr-chip" style="font-size:0.68rem;"><?php echo htmlspecialchars($u['qr_code_id']); ?></span>
                <?php endforeach; ?>
                <?php if ($unit_count > 2): ?><span style="font-size:0.7rem;color:rgba(0,0,0,0.40);align-self:center;">+<?php echo $unit_count - 2; ?> more</span><?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, <?php echo htmlspecialchars(json_encode($ic)); ?>)">
                    <i class="fas fa-eye"></i> View &amp; Manage
                </button>
                <button type="button" class="ai-btn-sm" style="background:rgba(34,197,94,0.10);color:#15803d;border:none;border-radius:8px;white-space:nowrap;"
                    onclick="openAddUnitsModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                    <i class="fas fa-plus"></i> Add Units
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-tools"></i>No items in maintenance</div>
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
                foreach ($owned_items_page as $group):
                    $owner_user  = findById($users, $group['user_id']);
                    $owner_name  = $owner_user ? htmlspecialchars($owner_user['full_name']) : 'Unknown User';
                    $campus_info = getCampus($group['campus_id']);
                    $unit_count  = count($group['units']);
                    $conditions  = array_unique(array_column($group['units'], 'condition'));
                    $cond_label  = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span style="background:rgba(59,130,246,0.12);color:#1d4ed8;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo $unit_count; ?> unit<?php echo $unit_count > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:8px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Owner</div>
                        <div style="font-weight:600;color:#1a1d23;font-size:0.88rem;"><i class="fas fa-user-circle me-1" style="color:#3b82f6;"></i><?php echo $owner_name; ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Year Owned</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $group['year_owned'] ?? '—'; ?></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Campus</div>
                        <div style="font-weight:600;color:#1a1d23;font-size:0.88rem;"><?php echo htmlspecialchars($campus_info['name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;cursor:pointer;"
                    onclick="openOwnedGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, '<?php echo $owner_name; ?>', <?php echo htmlspecialchars(json_encode($campus_info)); ?>)">
                    <i class="fas fa-info-circle"></i> View Units
                </button>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;">
            <i class="fas fa-user-circle"></i>
            <p>No user-owned items recorded yet</p>
            <small style="color:rgba(0,0,0,0.40);">Start tracking user-owned items from past years</small>
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
        <div class="modal-content" style="border-radius:8px; border:1px solid #e5e7eb;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                <div>
                    <h5 class="modal-title" id="detailModalTitle" style="font-size:1.1rem; font-weight:700; margin-bottom: 4px;"></h5>
                    <small id="detailModalCategory" style="color: rgba(0,0,0,0.50);"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Item Information -->
                <div style="background: #f7f7f7; border-radius: 6px; border: 1px solid #e5e7eb; padding: 16px; margin-bottom: 20px;">
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
    gap: 12px;
    margin-bottom: 24px;
    padding: 6px;
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    flex-wrap: wrap;
}

.ai-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    color: rgba(0,0,0,0.50);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 600;
    border-radius: 6px;
    transition: all 0.15s;
    cursor: pointer;
    white-space: nowrap;
    background: transparent;
}

.ai-tab-icon {
    display: flex;
    align-items: center;
    font-size: 0.78rem;
}

.ai-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    background: rgba(0,0,0,0.08);
    color: rgba(0,0,0,0.55);
    border-radius: 4px;
    font-size: 0.72rem;
    font-weight: 700;
}

.ai-tab:hover {
    color: rgba(0,0,0,0.75);
    background: #fff;
}

.ai-tab-active {
    color: #8B0000;
    background: #fff;
    box-shadow: none;
    border: 1px solid #e5e7eb;
}

.ai-tab-active .ai-tab-badge {
    background: rgba(139,0,0,0.10);
    color: #8B0000;
}
</style>

<script>
function openGroupModal(group, campus) {
    document.getElementById('detailModalTitle').textContent = group.item_name;
    document.getElementById('detailModalCategory').textContent =
        group.category + ' · ' + campus.name + ' · ' + group.units.length + ' unit(s)';

    document.getElementById('detailCampus').textContent = campus ? campus.name : 'Unknown';
    document.getElementById('detailQuantity').textContent = group.units.length + ' unit(s)';

    var condSet = [...new Set(group.units.map(function(u){ return u.condition; }))];
    document.getElementById('detailCondition').textContent =
        condSet.length === 1 ? condSet[0].charAt(0).toUpperCase() + condSet[0].slice(1) : 'Mixed';

    document.getElementById('detailCost').textContent = group.cost ? '₱' + parseFloat(group.cost).toFixed(2) : 'N/A';
    document.getElementById('detailLocation').textContent = group.location || 'N/A';
    document.getElementById('detailDescription').textContent = group.description || 'No description available';

    var grid = document.getElementById('qrCodesGrid');
    grid.innerHTML = '';
    var apiBase = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=';
    var statusColors = { available:'success', borrowed:'warning', requested:'info', maintenance:'warning' };

    group.units.forEach(function(unit, idx) {
        var sc = statusColors[unit.status] || 'secondary';
        var col = document.createElement('div');
        col.className = 'col-6 col-md-4';
        col.innerHTML =
            '<div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:12px;text-align:center;">'
            + '<img src="' + apiBase + encodeURIComponent(unit.qr_code_id) + '" alt="QR" style="width:100px;height:100px;border-radius:6px;margin-bottom:8px;">'
            + '<div style="font-family:monospace;font-size:0.67rem;word-break:break-all;margin-bottom:6px;color:rgba(0,0,0,0.55);background:rgba(0,0,0,0.03);padding:4px;border-radius:4px;">' + unit.qr_code_id + '</div>'
            + '<div style="font-size:0.78rem;font-weight:700;color:#1a1d23;margin-bottom:4px;">Unit ' + (idx + 1) + '</div>'
            + '<span class="ai-badge ai-badge-' + sc + '" style="font-size:0.7rem;margin-bottom:8px;">' + unit.status + '</span>'
            + '<div style="display:flex;gap:4px;justify-content:center;margin-top:6px;">'
            + '<a href="inventory.php?action=edit&id=' + unit.id + '" class="ai-btn-sm ai-btn-edit" title="Edit"><i class="fas fa-edit"></i></a>'
            + '<a href="inventory.php?action=delete&id=' + unit.id + '" class="ai-btn-sm ai-btn-delete delete-btn" title="Delete"><i class="fas fa-trash"></i></a>'
            + '</div>'
            + '</div>';
        grid.appendChild(col);
    });

    new bootstrap.Modal(document.getElementById('detailModal')).show();
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

function openOwnedGroupModal(group, ownerName, campus) {
    document.getElementById('ownedItemModalTitle').textContent = group.item_name;
    document.getElementById('ownedItemModalCategory').textContent =
        group.category + ' · ' + ownerName + ' · ' + group.units.length + ' unit(s)';

    document.getElementById('ownedItemOwner').textContent = ownerName;
    document.getElementById('ownedItemYear').textContent = group.year_owned || '—';
    document.getElementById('ownedItemCampus').textContent = campus ? campus.name : 'Unknown';

    var condSet = [...new Set(group.units.map(function(u){ return u.condition; }).filter(Boolean))];
    document.getElementById('ownedItemCondition').textContent =
        condSet.length === 1 ? condSet[0].charAt(0).toUpperCase() + condSet[0].slice(1) : 'Mixed';

    document.getElementById('ownedItemDescription').textContent = group.description || 'No description provided';
    var notesEl = document.getElementById('ownedItemNotes');
    notesEl.parentElement.style.display = group.notes ? 'block' : 'none';
    notesEl.textContent = group.notes || '';

    var grid = document.getElementById('ownedUnitsGrid');
    grid.innerHTML = '';
    group.units.forEach(function(unit, idx) {
        var cond = unit.condition
            ? unit.condition.charAt(0).toUpperCase() + unit.condition.slice(1)
            : '—';
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f7f7f7;border-radius:6px;font-size:0.88rem;';
        row.innerHTML =
            '<div>'
            + '<span style="font-weight:700;color:#1a1d23;">Unit ' + (idx + 1) + '</span>'
            + '<span style="color:rgba(0,0,0,0.45);font-size:0.78rem;margin-left:8px;">' + cond + '</span>'
            + '</div>'
            + '<a href="inventory.php?action=edit_owned&id=' + unit.id + '" class="ai-btn-sm ai-btn-edit" title="Edit unit"><i class="fas fa-edit"></i> Edit</a>';
        grid.appendChild(row);
    });

    new bootstrap.Modal(document.getElementById('ownedItemModal')).show();
}
</script>

<!-- User-Owned Item Detail Modal -->
<div class="modal fade" id="ownedItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:8px; border:1px solid #e5e7eb;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb; background: #fff;">
                <div>
                    <h5 class="modal-title" id="ownedItemModalTitle" style="font-size:1.15rem; font-weight:700; margin-bottom: 4px;"></h5>
                    <small id="ownedItemModalCategory" style="color: rgba(0,0,0,0.50);"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <!-- Owner Info -->
                <div style="background:#f0f5ff;border-left:3px solid #3b82f6;padding:14px 16px;border-radius:6px;margin-bottom:20px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;font-weight:700;margin-bottom:5px;letter-spacing:0.5px;">Owner</div>
                            <div id="ownedItemOwner" style="font-weight:700;font-size:0.95rem;color:#1a1d23;"></div>
                        </div>
                        <div>
                            <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;font-weight:700;margin-bottom:5px;letter-spacing:0.5px;">Year Owned</div>
                            <div id="ownedItemYear" style="font-weight:700;font-size:0.95rem;color:#1a1d23;"></div>
                        </div>
                    </div>
                </div>

                <!-- Item Details -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;margin-bottom:4px;">Campus</div>
                        <div id="ownedItemCampus" style="font-weight:600;color:#1a1d23;font-size:0.9rem;"></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;margin-bottom:4px;">Condition</div>
                        <div id="ownedItemCondition" style="font-weight:600;color:#1a1d23;font-size:0.9rem;"></div>
                    </div>
                </div>

                <!-- Description -->
                <div style="margin-bottom:20px;">
                    <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(0,0,0,0.50);margin-bottom:8px;">Description</div>
                    <div id="ownedItemDescription" style="font-size:0.88rem;color:#374151;line-height:1.6;background:rgba(0,0,0,0.03);padding:10px;border-radius:6px;"></div>
                </div>

                <!-- Notes -->
                <div style="background:rgba(34,197,94,0.08);border-left:3px solid #22c55e;padding:12px 14px;border-radius:6px;margin-bottom:20px;">
                    <div style="font-size:0.7rem;color:#15803d;text-transform:uppercase;font-weight:700;margin-bottom:5px;letter-spacing:0.5px;"><i class="fas fa-sticky-note me-1"></i>Notes</div>
                    <div id="ownedItemNotes" style="font-size:0.88rem;color:#374151;line-height:1.6;"></div>
                </div>

                <!-- Units list -->
                <div>
                    <div style="font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(0,0,0,0.50);margin-bottom:10px;">Individual Units</div>
                    <div id="ownedUnitsGrid" style="display:flex;flex-direction:column;gap:6px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Units to Existing Item Modal -->
<div class="modal fade" id="addUnitsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:8px;border:1px solid #e5e7eb;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                <div>
                    <h5 class="modal-title" id="addUnitsModalTitle" style="font-size:1.05rem;font-weight:700;margin-bottom:3px;"></h5>
                    <small id="addUnitsModalSub" style="color:rgba(0,0,0,0.45);font-size:0.8rem;"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="?action=add_units">
                <div class="modal-body" style="padding:24px;">
                    <input type="hidden" name="ref_id" id="addUnitsRefId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Units to add *</label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                        <div class="form-text">Each unit gets its own unique QR code.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Condition *</label>
                        <select class="form-select" name="condition" required>
                            <option value="excellent">Excellent</option>
                            <option value="good" selected>Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Purchase Date <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="date" class="form-control" name="purchase_date">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn ai-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn ai-btn-primary"><i class="fas fa-plus"></i> Add Units</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddUnitsModal(group) {
    var firstUnit = group.units[0];
    document.getElementById('addUnitsRefId').value = firstUnit.id;
    document.getElementById('addUnitsModalTitle').textContent = 'Add Units — ' + group.item_name;
    document.getElementById('addUnitsModalSub').textContent =
        group.category + ' · ' + group.units.length + ' existing unit(s)';
    // Reset form fields
    document.querySelector('#addUnitsModal input[name="quantity"]').value = 1;
    document.querySelector('#addUnitsModal select[name="condition"]').value = 'good';
    document.querySelector('#addUnitsModal input[name="purchase_date"]').value = '';
    new bootstrap.Modal(document.getElementById('addUnitsModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
