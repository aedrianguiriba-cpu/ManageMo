<?php
$page_title = 'Inventory Management';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();
$action = $_GET['action'] ?? 'list';
$status_filter = $_GET['status'] ?? ''; // Default to show all items
$page = $_GET['page'] ?? 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $item_name    = sanitizeInput($_POST['item_name']);
        $qty          = max(1, (int)$_POST['quantity']);
        $acq_mode     = sanitizeInput($_POST['acquisition_mode'] ?? 'borrow');
        $base_data    = [
            'item_name'        => $item_name,
            'category'         => sanitizeInput($_POST['category']),
            'description'      => sanitizeInput($_POST['description']),
            // New items always start life as Available, so no department is tagged yet
            // (see the "Available = no owner" rule). campus_id no longer exists as a
            // column at all — don't send it.
            'college_id'       => null,
            'quantity'         => 1,
            'location'         => sanitizeInput($_POST['location']),
            'purchase_date'    => sanitizeInput($_POST['purchase_date']) ?: null,
            'cost'             => is_numeric($_POST['cost'] ?? '') ? (float)$_POST['cost'] : null,
            'condition'        => sanitizeInput($_POST['condition']),
            'status'           => 'available',
            'group_id'         => generateGroupId(),
            'acquisition_mode' => in_array($acq_mode, ['borrow', 'request', 'both']) ? $acq_mode : 'borrow',
            'model'            => sanitizeInput($_POST['model'] ?? '') ?: null,
            'serial_number'    => sanitizeInput($_POST['serial_number'] ?? '') ?: null,
        ];
        $base_serial = $base_data['serial_number'];
        $first_id = null;
        for ($u = 0; $u < $qty; $u++) {
            $unit_overrides = ['qr_code_id' => generateQRCodeId()];
            // With more than 1 unit, each gets its own name ("Acer Laptop Unit 1", "...Unit 2", …)
            // and its own serial (base serial suffixed per unit) — a single row's fields never
            // repeat identically across units of the same add.
            if ($qty > 1) {
                $unit_overrides['item_name'] = $item_name . ' Unit ' . ($u + 1);
                if ($base_serial) $unit_overrides['serial_number'] = $base_serial . '-' . str_pad($u + 1, 2, '0', STR_PAD_LEFT);
            }
            $row = dbCreateInventory(array_merge($base_data, $unit_overrides));
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
        // Strip a previously-applied " Unit N" suffix to get the plain product name for renumbering.
        $base_item_name = preg_replace('/\s+Unit\s+\d+$/i', '', $ref_item['item_name']);
        $group_id_for_units = !empty($ref_item['group_id']) ? $ref_item['group_id'] : generateGroupId();
        $existing_unit_count = !empty($ref_item['group_id'])
            ? count(filterByColumn(getInventory(), 'group_id', $group_id_for_units))
            : 1;
        // No serial field on the Add Units modal today — only suffix if one is ever posted.
        $base_serial = sanitizeInput($_POST['serial_number'] ?? '') ?: null;

        $base_data = [
            'item_name'     => $base_item_name,
            'category'      => $ref_item['category'],
            'description'   => $ref_item['description'],
            'college_id'    => $ref_item['college_id'] ?? null,
            'quantity'      => 1,
            'location'      => $ref_item['location'],
            'purchase_date' => !empty($_POST['purchase_date']) ? sanitizeInput($_POST['purchase_date']) : ($ref_item['purchase_date'] ?? null),
            'cost'          => $ref_item['cost'],
            'condition'     => sanitizeInput($_POST['condition']),
            'status'        => 'available',
            'acquisition_mode' => $ref_item['acquisition_mode'] ?? 'borrow',
            'model'         => $ref_item['model'] ?? null,
            'group_id'      => $group_id_for_units,
        ];
        // Continues the existing "<name> Unit N" numbering — total units after this add is
        // always > 1, so every unit (old and new) is uniquely named/serialed.
        for ($u = 0; $u < $qty; $u++) {
            $unit_num = $existing_unit_count + $u + 1;
            $unit_overrides = [
                'qr_code_id' => generateQRCodeId(),
                'item_name'  => $base_item_name . ' Unit ' . $unit_num,
            ];
            if ($base_serial) $unit_overrides['serial_number'] = $base_serial . '-' . str_pad($unit_num, 2, '0', STR_PAD_LEFT);
            dbCreateInventory(array_merge($base_data, $unit_overrides));
        }
        $item_name = $base_item_name;
        logActivity($current_user['id'], 'CREATE', "Added $qty unit(s) to existing item: $item_name", 'inventory', $ref_id);
        redirectWithMessage('inventory.php', "$qty unit(s) added to '$item_name' successfully!", 'success');

    } elseif ($action === 'add_owned') {
        $item_name = sanitizeInput($_POST['item_name']);
        $user_id   = (int)$_POST['user_id'];
        $qty       = max(1, (int)($_POST['quantity'] ?? 1));
        // Guard against user_id being missing/tampered/0 — without this, the row silently
        // saves with no real owner and later renders as "Unknown User".
        if (!$user_id || !findById(getUsers(), $user_id)) {
            redirectWithMessage('inventory.php?action=add_owned&tab=owned', 'Please select a valid user before saving.', 'danger');
        }
        $base_owned = [
            'user_id'       => $user_id,
            'item_name'     => $item_name,
            'category'      => sanitizeInput($_POST['category']),
            'description'   => sanitizeInput($_POST['description']),
            // The merged Campus/College/Office picker writes its value to college_id.
            // campus_id no longer exists as a column at all — don't send it.
            'college_id'    => sanitizeInput($_POST['college_id'] ?? '') ?: null,
            'year_owned'    => (int)$_POST['year_owned'] ?: null,
            'quantity'      => 1,
            'condition'     => sanitizeInput($_POST['condition']),
            'notes'         => sanitizeInput($_POST['notes']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']) ?: null,
            'group_id'      => generateGroupId(),
        ];
        for ($u = 0; $u < $qty; $u++) {
            dbCreateUserOwnedItem(array_merge($base_owned, ['qr_code_id' => generateQRCodeId()]));
        }
        logActivity($current_user['id'], 'CREATE', "Added $qty unit(s) of user owned item: $item_name for user_id: $user_id", 'user_owned_items', $user_id);
        redirectWithMessage('inventory.php?tab=owned', "$qty unit(s) of '$item_name' recorded successfully!", 'success');

    } elseif ($action === 'edit_owned') {
        $owned_id      = (int)$_GET['id'];
        $item_name     = sanitizeInput($_POST['item_name']);
        $qty           = max(1, (int)$_POST['quantity']);
        $existing_item = findById(getUserOwnedItems(), $owned_id);
        $user_id       = $existing_item['user_id'] ?? 0;
        $group_id      = !empty($existing_item['group_id']) ? $existing_item['group_id'] : generateGroupId();
        $unit_data     = [
            'item_name'     => $item_name,
            'category'      => sanitizeInput($_POST['category']),
            'description'   => sanitizeInput($_POST['description']),
            // The merged Campus/College/Office picker writes its value to college_id.
            // campus_id no longer exists as a column at all — don't send it.
            'college_id'    => sanitizeInput($_POST['college_id'] ?? '') ?: null,
            'year_owned'    => (int)$_POST['year_owned'] ?: null,
            'quantity'      => 1,
            'condition'     => sanitizeInput($_POST['condition']),
            'notes'         => sanitizeInput($_POST['notes']),
            'purchase_date' => sanitizeInput($_POST['purchase_date']) ?: null,
            'group_id'      => $group_id,
        ];
        // Update the existing unit row (preserve existing QR code; only stamp group_id if missing)
        $existing_qr = $existing_item['qr_code_id'] ?? null;
        dbUpdateUserOwnedItem($owned_id, array_merge($unit_data, [
            'qr_code_id' => $existing_qr ?: generateQRCodeId(),
        ]));
        // Create additional rows for extra units, each with its own QR code
        for ($u = 1; $u < $qty; $u++) {
            dbCreateUserOwnedItem(array_merge($unit_data, ['user_id' => $user_id, 'qr_code_id' => generateQRCodeId()]));
        }
        $msg = $qty > 1 ? "Unit updated and " . ($qty - 1) . " new unit(s) added for '$item_name'." : "Item updated successfully!";
        logActivity($current_user['id'], 'UPDATE', "Updated user-owned item: $item_name (qty: $qty)", 'user_owned_items', $owned_id);
        redirectWithMessage('inventory.php?tab=owned', $msg, 'success');

    } elseif ($action === 'edit') {
        $inventory_id = (int)$_GET['id'];
        $item_name    = sanitizeInput($_POST['item_name']);
        $acq_mode     = sanitizeInput($_POST['acquisition_mode'] ?? 'borrow');
        $item_status  = sanitizeInput($_POST['status']);
        // Items still Available haven't been claimed by any department/campus/college yet.
        $college_id   = $item_status === 'available' ? null : (sanitizeInput($_POST['college_id'] ?? '') ?: null);
        dbUpdateInventory($inventory_id, [
            'item_name'        => $item_name,
            'category'         => sanitizeInput($_POST['category']),
            'description'      => sanitizeInput($_POST['description']),
            // The single merged Campus/College/Office picker writes its value to
            // college_id, cleared above whenever status is Available. campus_id no
            // longer exists as a column at all — don't send it.
            'college_id'       => $college_id,
            'quantity'         => max(0, (int)$_POST['quantity']),
            'location'         => sanitizeInput($_POST['location']),
            'condition'        => sanitizeInput($_POST['condition']),
            'status'           => $item_status,
            'acquisition_mode' => in_array($acq_mode, ['borrow', 'request', 'both']) ? $acq_mode : 'borrow',
            'model'            => sanitizeInput($_POST['model'] ?? '') ?: null,
            'serial_number'    => sanitizeInput($_POST['serial_number'] ?? '') ?: null,
        ]);
        logActivity($current_user['id'], 'UPDATE', "Updated inventory item: $item_name", 'inventory', $inventory_id);
        redirectWithMessage('inventory.php', 'Item updated successfully!', 'success');

    }
}

// Load shared data needed by multiple form views
$users = filterByColumn(getUsers(), 'role', ROLE_USER);
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
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Item Name *</label>
                    <input type="text" class="form-control" name="item_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Campus / College / Office</label>
                    <select class="form-select" id="addItemCollegeId" disabled>
                        <option>— None / Not applicable —</option>
                        <?php renderDepartmentOptionGroups(); ?>
                    </select>
                    <div class="form-text">New items start as Available, so no department owner is tagged yet — assign one after the item's status changes.</div>
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
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Acquisition Mode *</label>
                    <select class="form-select" name="acquisition_mode" required>
                        <option value="borrow" selected>Borrowable</option>
                        <option value="request">Request / Acquire Only</option>
                        <option value="both">Both (Borrowable &amp; Acquirable)</option>
                    </select>
                    <div class="form-text">Borrowable items appear in the Borrow catalog; request-only items are removed from it; "Both" appears in both catalogs.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Model</label>
                    <input type="text" class="form-control" name="model" placeholder="e.g. Dell Inspiron 15">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Serial Number</label>
                    <input type="text" class="form-control" name="serial_number" placeholder="Manufacturer serial no.">
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
                    <select class="form-select" name="status" id="editItemStatus" required>
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
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Campus / College / Office</label>
                    <select class="form-select" name="college_id" id="editItemCollegeId">
                        <option value="">— None / Not applicable —</option>
                        <?php renderDepartmentOptionGroups($item['college_id'] ?? null); ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Acquisition Mode *</label>
                    <select class="form-select" name="acquisition_mode" required>
                        <option value="borrow"  <?php echo ($item['acquisition_mode'] ?? 'borrow') === 'borrow'  ? 'selected' : ''; ?>>Borrowable</option>
                        <option value="request" <?php echo ($item['acquisition_mode'] ?? 'borrow') === 'request' ? 'selected' : ''; ?>>Request / Acquire Only</option>
                        <option value="both"    <?php echo ($item['acquisition_mode'] ?? 'borrow') === 'both'    ? 'selected' : ''; ?>>Both (Borrowable &amp; Acquirable)</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Model</label>
                    <input type="text" class="form-control" name="model" value="<?php echo htmlspecialchars($item['model'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Serial Number</label>
                    <input type="text" class="form-control" name="serial_number" value="<?php echo htmlspecialchars($item['serial_number'] ?? ''); ?>">
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
    <script>
    (function() {
        var statusSelect = document.getElementById('editItemStatus');
        var collegeSelect = document.getElementById('editItemCollegeId');
        if (!statusSelect || !collegeSelect) return;
        function syncCollegeField() {
            var isAvailable = statusSelect.value === 'available';
            collegeSelect.disabled = isAvailable;
            if (isAvailable) collegeSelect.value = '';
        }
        statusSelect.addEventListener('change', syncCollegeField);
        syncCollegeField();
    })();
    </script>

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
                    <label class="form-label">Campus / College / Office</label>
                    <select class="form-select" name="college_id">
                        <option value="">— None / Not applicable —</option>
                        <?php renderDepartmentOptionGroups($owned_item['college_id'] ?? null); ?>
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
        <!-- User-to-department map for auto-fill, from each user's own college_id -->
        <!-- (campus_id no longer exists — college_id is the only affiliation field left). -->
        <script>
        var userDeptMap = <?php
            $dmap = [];
            foreach ($users as $u) {
                $dmap[$u['id']] = $u['college_id'] ?? '';
            }
            echo json_encode($dmap);
        ?>;
        </script>
        <form method="POST" action="?action=add_owned">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">User *</label>
                    <select class="form-select" name="user_id" id="ownedUserId" required onchange="fillUserFields(this.value)">
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
                    <label class="form-label">Campus / College / Office</label>
                    <select class="form-select" name="college_id" id="ownedCollegeId">
                        <option value="">— None / Not applicable —</option>
                        <?php renderDepartmentOptionGroups(); ?>
                    </select>
                    <div class="form-text">Auto-filled from the selected user's department — change it if this item belongs elsewhere.</div>
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
    function fillUserFields(userId) {
        document.getElementById('ownedCollegeId').value = userDeptMap[userId] || '';
    }
    </script>

    <?php else: ?>

    <?php
    $all_items = getInventory();

    // Shared filter/search — applies to the All Items and Available tabs.
    // One combined "department" filter (fdept) covers Campuses, Colleges, and
    // Offices together — inventory items only ever carry a single owner
    // abbreviation in college_id (campus_id is no longer relied on), so both
    // "c:" and "d:" prefixes resolve to the same college_id comparison.
    $filter_search   = trim($_GET['search'] ?? '');
    $filter_dept     = $_GET['fdept'] ?? '';
    $filter_college_id = (str_starts_with($filter_dept, 'c:') || str_starts_with($filter_dept, 'd:')) ? substr($filter_dept, 2) : '';
    $filter_category   = $_GET['fcategory'] ?? '';
    // Acquisition-mode sub-tabs (All Items only): '', 'borrow', or 'request'.
    $filter_acq = $_GET['facq'] ?? '';
    if (!in_array($filter_acq, ['', 'borrow', 'request'])) $filter_acq = '';
    $all_categories    = array_values(array_unique(array_filter(array_column($all_items, 'category'))));
    sort($all_categories);

    $applyInventoryFilters = function(array $items) use ($filter_search, $filter_college_id, $filter_category, $filter_acq): array {
        return array_values(array_filter($items, function($i) use ($filter_search, $filter_college_id, $filter_category, $filter_acq) {
            if ($filter_college_id !== '' && ($i['college_id'] ?? '') !== $filter_college_id) return false;
            if ($filter_category !== '' && $i['category'] !== $filter_category) return false;
            if ($filter_acq !== '' && ($i['acquisition_mode'] ?? 'borrow') !== $filter_acq) return false;
            if ($filter_search !== '') {
                $hay = strtolower($i['item_name'] . ' ' . ($i['qr_code_id'] ?? '') . ' ' . ($i['category'] ?? ''));
                if (strpos($hay, strtolower($filter_search)) === false) return false;
            }
            return true;
        }));
    };

    // Separate items by status
    $all_active_items  = array_values(array_filter($all_items, fn($i) => !in_array($i['status'], ['condemned','disposed'])));
    $available_items   = filterByColumn($all_items, 'status', 'available');
    $requested_items   = filterByColumn($all_items, 'status', 'requested');
    $borrowed_items     = filterByColumn($all_items, 'status', 'borrowed');

    $all_active_items = $applyInventoryFilters($all_active_items);
    $available_items  = $applyInventoryFilters($available_items);

    // Get user owned items
    $owned_items = getUserOwnedItems();

    // Maintenance tab: service requests are pure free-text tickets with no catalog
    // item attached (see user/requests.php), so there's no inventory row to filter
    // by status here. Instead, list the open service tickets themselves.
    $all_users_by_id = array_column(getUsers(), null, 'id');
    $maintenance_requests = array_values(array_filter(getRequests(), function($r) {
        return $r['request_type'] === 'service' && in_array($r['status'], ['pending', 'approved']);
    }));
    usort($maintenance_requests, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });

    usort($all_active_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($available_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($requested_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($borrowed_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });
    usort($owned_items, function($a, $b){ return strcmp($b['created_at'], $a['created_at']); });

    $status_colors = ['available'=>'success','requested'=>'info','borrowed'=>'warning','maintenance'=>'info'];

    // Group same-name items into cards; each group contains individual unit rows
    $grouped_all         = groupInventoryItems($all_active_items);
    $grouped_available   = groupInventoryItems($available_items);
    $grouped_requested   = groupInventoryItems($requested_items);
    $grouped_borrowed    = groupInventoryItems($borrowed_items);
    $grouped_owned       = groupOwnedItems($owned_items);

    // Pagination settings
    $items_per_page = 6;
    $current_page_all = isset($_GET['page_all']) ? (int)$_GET['page_all'] : 1;
    $current_page_available = isset($_GET['page_available']) ? (int)$_GET['page_available'] : 1;
    $current_page_requested = isset($_GET['page_requested']) ? (int)$_GET['page_requested'] : 1;
    $current_page_maintenance = isset($_GET['page_maintenance']) ? (int)$_GET['page_maintenance'] : 1;
    $current_page_borrowed = isset($_GET['page_borrowed']) ? (int)$_GET['page_borrowed'] : 1;
    $current_page_owned = isset($_GET['page_owned']) ? (int)$_GET['page_owned'] : 1;
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

    // Tab badges count units; pagination is over groups
    $total_all = count($all_active_items);
    $total_available = count($available_items);
    $total_requested = count($requested_items);
    $total_maintenance = count($maintenance_requests);
    $total_borrowed = count($borrowed_items);
    $total_owned = count($owned_items);

    $pages_all = ceil(count($grouped_all) / $items_per_page);
    $pages_available = ceil(count($grouped_available) / $items_per_page);
    $pages_requested = ceil(count($grouped_requested) / $items_per_page);
    $pages_maintenance = ceil(count($maintenance_requests) / $items_per_page);
    $pages_borrowed = ceil(count($grouped_borrowed) / $items_per_page);
    $pages_owned = ceil(count($grouped_owned) / $items_per_page);

    $offset_all = ($current_page_all - 1) * $items_per_page;
    $offset_available = ($current_page_available - 1) * $items_per_page;
    $offset_requested = ($current_page_requested - 1) * $items_per_page;
    $offset_maintenance = ($current_page_maintenance - 1) * $items_per_page;
    $offset_borrowed = ($current_page_borrowed - 1) * $items_per_page;
    $offset_owned = ($current_page_owned - 1) * $items_per_page;

    $all_items_page          = array_slice($grouped_all, $offset_all, $items_per_page);
    $available_items_page   = array_slice($grouped_available, $offset_available, $items_per_page);
    $requested_items_page   = array_slice($grouped_requested, $offset_requested, $items_per_page);
    $maintenance_items_page = array_slice($maintenance_requests, $offset_maintenance, $items_per_page);
    $borrowed_items_page    = array_slice($grouped_borrowed, $offset_borrowed, $items_per_page);
    $owned_items_page       = array_slice($grouped_owned, $offset_owned, $items_per_page);
    ?>

    <!-- FILTER / SEARCH BAR (applies to All Items and Available tabs) -->
    <form method="GET" action="inventory.php" class="ai-filter-card">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($current_tab); ?>">
        <input type="hidden" name="facq" value="<?php echo htmlspecialchars($filter_acq); ?>">
        <div style="flex:1;min-width:180px;">
            <div class="ai-filter-label">Search</div>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Item name, QR code, category…" value="<?php echo htmlspecialchars($filter_search); ?>">
        </div>
        <div style="min-width:180px;">
            <div class="ai-filter-label">Campus / College / Office</div>
            <select name="fdept" class="form-select form-select-sm">
                <option value="">All</option>
                <optgroup label="Campuses">
                <?php foreach ($campuses as $campus): ?>
                <option value="c:<?php echo htmlspecialchars($campus['abbreviation']); ?>" <?php echo $filter_dept === 'c:' . $campus['abbreviation'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($campus['name']); ?></option>
                <?php endforeach; ?>
                </optgroup>
                <optgroup label="Colleges/Offices">
                <?php foreach (getMainCampusDepartments() as $abbr => $fullname): ?>
                <option value="d:<?php echo htmlspecialchars($abbr); ?>" <?php echo $filter_dept === 'd:' . $abbr ? 'selected' : ''; ?>><?php echo htmlspecialchars($fullname); ?></option>
                <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
        <div style="min-width:160px;">
            <div class="ai-filter-label">Category</div>
            <select name="fcategory" class="form-select form-select-sm">
                <option value="">All Categories</option>
                <?php foreach ($all_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn ai-btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($filter_search !== '' || $filter_college_id !== '' || $filter_category !== '' || $filter_acq !== ''): ?>
            <a href="inventory.php?tab=<?php echo htmlspecialchars($current_tab); ?>" class="btn ai-btn-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- TAB NAVIGATION -->
    <div class="ai-tabs-container">
        <div style="display: flex; gap: 8px; flex: 1; flex-wrap: wrap;">
            <a href="inventory.php?tab=all" class="ai-tab <?php echo $current_tab === 'all' ? 'ai-tab-active' : ''; ?>" onclick="setTab('all'); return false;">
                <span class="ai-tab-icon"><i class="fas fa-layer-group"></i></span>
                <span class="ai-tab-label">All Items</span>
                <span class="ai-tab-badge"><?php echo $total_all; ?></span>
            </a>
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

    <!-- ALL ITEMS TAB — every non-condemned/disposed item, any status; where items get edited or sent to condemn -->
    <div id="tab-all" style="display: <?php echo $current_tab === 'all' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($all_items_page) > 0):
                foreach ($all_items_page as $group):
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
                    $statuses = array_unique(array_column($group['units'], 'status'));
                    $status_label = count($statuses) === 1 ? ucfirst($statuses[0]) : 'Mixed';
                    $status_color = count($statuses) === 1 ? ($status_colors[$statuses[0]] ?? 'secondary') : 'secondary';
                    $__all_grp_depts = getAllDepartmentNames();
                    $__all_grp_dept_name = ($group['college_id'] ?? null) && isset($__all_grp_depts[$group['college_id']])
                        ? $__all_grp_depts[$group['college_id']] : null;
                    // Requested/borrowed units are mid-use and condemnation.php itself
                    // refuses to condemn them — so only offer the button when at least
                    // one unit in this group is actually condemnable, and point it at
                    // that unit rather than blindly at units[0].
                    $__all_condemnable_unit = null;
                    foreach ($group['units'] as $__u) {
                        if (!in_array($__u['status'], ['requested', 'borrowed'])) { $__all_condemnable_unit = $__u; break; }
                    }
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($group['item_name']); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($group['category']); ?>
                    </div>
                </div>
                <span class="ai-badge ai-badge-<?php echo $status_color; ?>"><?php echo htmlspecialchars($status_label); ?></span>
            </div>
            <?php $__all_acq = acquisitionModeBadge($group['units'][0]['acquisition_mode'] ?? 'borrow'); ?>
            <span style="display:inline-block;background:<?php echo $__all_acq['bg']; ?>;color:<?php echo $__all_acq['fg']; ?>;font-weight:700;font-size:0.72rem;padding:2px 9px;border-radius:8px;margin-bottom:8px;">
                <?php echo htmlspecialchars($__all_acq['label']); ?>
            </span>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__all_grp_dept_name ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Units</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $unit_count; ?> (<?php echo $cond_label; ?>)</div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:auto;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
                    <i class="fas fa-eye"></i> View &amp; Manage
                </button>
                <?php if ($__all_condemnable_unit): ?>
                <a href="condemnation.php?tab=evaluate&condemn=<?php echo $__all_condemnable_unit['id']; ?>" class="ai-btn-sm" style="background:rgba(139,0,0,0.10);color:#8B0000;border:none;border-radius:8px;white-space:nowrap;" title="Condemn this unit">
                    <i class="fas fa-ban"></i> Condemn
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-box-open"></i>No items match this filter</div>
        <?php endif; ?>
        </div>

        <!-- Pagination for All Items -->
        <?php if ($pages_all > 1): ?>
        <nav style="display: flex; justify-content: center; gap: 8px;">
            <?php for ($i = 1; $i <= $pages_all; $i++): ?>
                <a href="inventory.php?tab=all&page_all=<?php echo $i; ?>&search=<?php echo urlencode($filter_search); ?>&fdept=<?php echo urlencode($filter_dept); ?>&fcategory=<?php echo urlencode($filter_category); ?>" class="btn btn-sm <?php echo $i === $current_page_all ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </div>

    <!-- AVAILABLE ITEMS TAB -->
    <div id="tab-available" style="display: <?php echo $current_tab === 'available' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <?php
        // Acquisition-mode sub-tabs: keep every other active filter, just swap facq.
        $__acq_base_qs = 'tab=available&search=' . urlencode($filter_search) . '&fdept=' . urlencode($filter_dept) . '&fcategory=' . urlencode($filter_category);
        $__acq_tabs = ['' => 'All', 'borrow' => 'Borrowable', 'request' => 'Acquire Only'];
        ?>
        <div style="display:flex;gap:6px;margin-bottom:16px;background:rgba(0,0,0,0.04);border-radius:8px;padding:5px;max-width:360px;">
            <?php foreach ($__acq_tabs as $__acq_val => $__acq_label): ?>
            <a href="inventory.php?<?php echo $__acq_base_qs; ?>&facq=<?php echo $__acq_val; ?>"
               style="flex:1;text-align:center;padding:7px 0;border-radius:6px;font-size:.82rem;font-weight:700;text-decoration:none;
                      background:<?php echo $filter_acq === $__acq_val ? '#fff' : 'transparent'; ?>;
                      color:<?php echo $filter_acq === $__acq_val ? '#8B0000' : '#555'; ?>;
                      box-shadow:<?php echo $filter_acq === $__acq_val ? '0 1px 4px rgba(0,0,0,.10)' : 'none'; ?>;">
                <?php echo $__acq_label; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($available_items_page) > 0):
                foreach ($available_items_page as $group):
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
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
            <?php $__avail_acq = acquisitionModeBadge($group['units'][0]['acquisition_mode'] ?? 'borrow'); ?>
            <span style="display:inline-block;background:<?php echo $__avail_acq['bg']; ?>;color:<?php echo $__avail_acq['fg']; ?>;font-weight:700;font-size:0.72rem;padding:2px 9px;border-radius:8px;margin-bottom:8px;">
                <?php echo htmlspecialchars($__avail_acq['label']); ?>
            </span>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <?php
                            $__grp_depts = getAllDepartmentNames();
                            $__grp_dept_name = ($group['college_id'] ?? null) && isset($__grp_depts[$group['college_id']])
                                ? $__grp_depts[$group['college_id']] : null;
                        ?>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__grp_dept_name ?? '—'); ?></div>
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
            <div style="display:flex;gap:8px;margin-top:auto;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;"
                    onclick="openGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>)">
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
                <a href="inventory.php?tab=available&page_available=<?php echo $i; ?>&search=<?php echo urlencode($filter_search); ?>&fdept=<?php echo urlencode($filter_dept); ?>&fcategory=<?php echo urlencode($filter_category); ?>&facq=<?php echo urlencode($filter_acq); ?>" class="btn btn-sm <?php echo $i === $current_page_available ? 'ai-btn-primary' : 'ai-btn-secondary'; ?>" style="min-width: 40px;">
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
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
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
                        <?php
                            $__grp_depts = getAllDepartmentNames();
                            $__grp_dept_name = ($group['college_id'] ?? null) && isset($__grp_depts[$group['college_id']])
                                ? $__grp_depts[$group['college_id']] : null;
                        ?>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__grp_dept_name ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:7px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:6px;padding:8px 12px;font-size:0.8rem;color:#b45309;font-weight:600;margin-top:auto;">
                <i class="fas fa-hourglass-half"></i> Pending Approval — reviewed in Requests
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
                    $unit_count = count($group['units']);
                    $conditions = array_unique(array_column($group['units'], 'condition'));
                    $cond_label = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
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
                        <?php
                            $__grp_depts = getAllDepartmentNames();
                            $__grp_dept_name = ($group['college_id'] ?? null) && isset($__grp_depts[$group['college_id']])
                                ? $__grp_depts[$group['college_id']] : null;
                        ?>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__grp_dept_name ?? '—'); ?></div>
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
            <div style="display:flex;align-items:center;gap:7px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:6px;padding:8px 12px;font-size:0.8rem;color:#b45309;font-weight:600;margin-top:auto;">
                <i class="fas fa-hand-holding-heart"></i> Currently Borrowed
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

    <!-- MAINTENANCE TICKETS TAB -->
    <!-- Service requests are free-text maintenance tickets with no catalog item
         attached (see user/requests.php), so this lists the open tickets
         themselves rather than filtering inventory by status. -->
    <div id="tab-maintenance" style="display: <?php echo $current_tab === 'maintenance' ? 'block' : 'none'; ?>; margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <?php if (count($maintenance_items_page) > 0):
                foreach ($maintenance_items_page as $req):
                    $__requester = $all_users_by_id[$req['user_id']] ?? null;
                    $__depts = getAllDepartmentNames();
                    $__dept_name = ($__requester['college_id'] ?? null) && isset($__depts[$__requester['college_id']])
                        ? $__depts[$__requester['college_id']] : null;
                    $__is_approved = $req['status'] === 'approved';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
                <div>
                    <div style="font-weight:800;font-size:1rem;color:#1a1d23;margin-bottom:4px;">
                        <?php echo htmlspecialchars($req['item_name'] ?? 'Service Request'); ?>
                    </div>
                    <div style="font-size:0.75rem;color:rgba(0,0,0,0.50);text-transform:uppercase;letter-spacing:0.5px;">
                        <?php echo htmlspecialchars($req['request_number'] ?? ''); ?>
                    </div>
                </div>
                <span style="background:<?php echo $__is_approved ? 'rgba(37,99,235,0.12)' : 'rgba(245,158,11,0.12)'; ?>;color:<?php echo $__is_approved ? '#1d4ed8' : '#b45309'; ?>;font-weight:700;font-size:0.76rem;padding:3px 10px;border-radius:10px;">
                    <?php echo ucfirst($req['status']); ?>
                </span>
            </div>
            <div style="border-top:1px solid rgba(0,0,0,0.07);border-bottom:1px solid rgba(0,0,0,0.07);padding:12px 0;margin:12px 0;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Requested By</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__requester['full_name'] ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo htmlspecialchars($__dept_name ?? '—'); ?></div>
                    </div>
                </div>
            </div>
            <?php if (!empty($req['service_description'])): ?>
            <div style="font-size:0.8rem;color:rgba(0,0,0,0.65);margin-bottom:10px;line-height:1.4;">
                <?php echo htmlspecialchars($req['service_description']); ?>
            </div>
            <?php endif; ?>
            <div style="display:flex;align-items:center;gap:7px;background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:6px;padding:8px 12px;font-size:0.8rem;color:#b45309;font-weight:600;margin-top:auto;">
                <i class="fas fa-tools"></i>
                <a href="requests.php?action=view&id=<?php echo (int)$req['id']; ?>" style="color:inherit;text-decoration:underline;">View request</a>
            </div>
        </div>
        <?php endforeach; else: ?>
        <div class="ai-empty" style="grid-column:1/-1;"><i class="fas fa-tools"></i>No open maintenance tickets</div>
        <?php endif; ?>
        </div>

        <!-- Pagination for Maintenance Tickets -->
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
                    $__owned_depts = getAllDepartmentNames();
                    $__owned_dept_name = ($group['college_id'] ?? null) && isset($__owned_depts[$group['college_id']])
                        ? $__owned_depts[$group['college_id']] : null;
                    $unit_count  = count($group['units']);
                    $conditions  = array_unique(array_column($group['units'], 'condition'));
                    $cond_label  = count($conditions) === 1 ? ucfirst($conditions[0]) : 'Mixed';
        ?>
        <div class="ai-item-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,0.06);display:flex;flex-direction:column;height:100%;">
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
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">College/Office</div>
                        <div style="font-weight:600;color:#1a1d23;font-size:0.88rem;"><?php echo htmlspecialchars($__owned_dept_name ?? '—'); ?></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;">Condition</div>
                        <div style="font-weight:600;color:#1a1d23;"><?php echo $cond_label; ?></div>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:auto;">
                <button type="button" class="ai-btn-sm" style="background:rgba(59,130,246,0.10);color:#1d4ed8;flex:1;border:none;border-radius:8px;cursor:pointer;"
                    onclick="openOwnedGroupModal(<?php echo htmlspecialchars(json_encode($group)); ?>, '<?php echo $owner_name; ?>')">
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
                            <div style="font-size: 0.75rem; color: rgba(0,0,0,0.50); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">College/Office</div>
                            <div id="detailCollege" style="font-weight: 600; font-size: 0.95rem;"></div>
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
var icDeptMap = <?php echo json_encode(getAllDepartmentNames()); ?>;
function openGroupModal(group) {
    // A group's stored item_name is just whichever unit happened to be created first, which
    // may already carry a " Unit N" suffix — strip it so we always have the clean product name
    // to build per-unit labels ("Acer Laptop #1", "Acer Laptop #2", …) from.
    var baseName = (group.item_name || '').replace(/\s+Unit\s+\d+$/i, '');
    var collegeName = group.college_id ? (icDeptMap[group.college_id] || group.college_id) : null;
    document.getElementById('detailModalTitle').textContent = baseName;
    document.getElementById('detailModalCategory').textContent =
        group.category + ' · ' + (collegeName || '—') + ' · ' + group.units.length + ' unit(s)';

    document.getElementById('detailCollege').textContent = collegeName || '—';
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
            + '<div style="font-size:0.78rem;font-weight:700;color:#1a1d23;margin-bottom:4px;">' + baseName + ' #' + (idx + 1) + '</div>'
            + '<span class="ai-badge ai-badge-' + sc + '" style="font-size:0.7rem;margin-bottom:8px;">' + unit.status + '</span>'
            + '<div style="display:flex;gap:4px;justify-content:center;margin-top:6px;">'
            + '<a href="inventory.php?action=edit&id=' + unit.id + '" class="ai-btn-sm ai-btn-edit" title="Edit"><i class="fas fa-edit"></i></a>'
            + (['condemned', 'disposed', 'requested', 'borrowed'].indexOf(unit.status) === -1
                ? '<a href="condemnation.php?tab=evaluate&condemn=' + unit.id + '" class="ai-btn-sm" style="background:rgba(139,0,0,0.10);color:#8B0000;" title="Condemn this unit"><i class="fas fa-ban"></i></a>'
                : '')
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
    document.getElementById('tab-all').style.display = 'none';
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

function openOwnedGroupModal(group, ownerName) {
    document.getElementById('ownedItemModalTitle').textContent = group.item_name;
    document.getElementById('ownedItemModalCategory').textContent =
        group.category + ' · ' + ownerName + ' · ' + group.units.length + ' unit(s)';

    document.getElementById('ownedItemOwner').textContent = ownerName;
    document.getElementById('ownedItemYear').textContent = group.year_owned || '—';
    document.getElementById('ownedItemCollege').textContent = group.college_id ? (icDeptMap[group.college_id] || group.college_id) : '—';

    var condSet = [...new Set(group.units.map(function(u){ return u.condition; }).filter(Boolean))];
    document.getElementById('ownedItemCondition').textContent =
        condSet.length === 1 ? condSet[0].charAt(0).toUpperCase() + condSet[0].slice(1) : 'Mixed';

    document.getElementById('ownedItemDescription').textContent = group.description || 'No description provided';
    var notesEl = document.getElementById('ownedItemNotes');
    notesEl.parentElement.style.display = group.notes ? 'block' : 'none';
    notesEl.textContent = group.notes || '';

    var grid = document.getElementById('ownedUnitsGrid');
    grid.innerHTML = '';
    var qrApiBase = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=';
    group.units.forEach(function(unit, idx) {
        var cond = unit.condition
            ? unit.condition.charAt(0).toUpperCase() + unit.condition.slice(1)
            : '—';
        var qr = unit.qr_code_id || '';
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:#f7f7f7;border-radius:6px;font-size:0.88rem;gap:10px;';
        row.innerHTML =
            (qr ? '<img src="' + qrApiBase + encodeURIComponent(qr) + '" alt="QR" style="width:48px;height:48px;border-radius:4px;flex-shrink:0;">' : '<div style="width:48px;height:48px;background:#e5e7eb;border-radius:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;"><i class="fas fa-qrcode" style="color:#9ca3af;font-size:1.1rem;"></i></div>')
            + '<div style="flex:1;min-width:0;">'
            + '<span style="font-weight:700;color:#1a1d23;">' + group.item_name + ' #' + (idx + 1) + '</span>'
            + '<span style="color:rgba(0,0,0,0.45);font-size:0.78rem;margin-left:8px;">' + cond + '</span>'
            + (qr ? '<div style="font-family:monospace;font-size:0.65rem;color:rgba(139,0,0,0.7);background:rgba(139,0,0,0.06);padding:2px 5px;border-radius:3px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + qr + '</div>' : '')
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
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;margin-bottom:4px;">Condition</div>
                        <div id="ownedItemCondition" style="font-weight:600;color:#1a1d23;font-size:0.9rem;"></div>
                    </div>
                    <div>
                        <div style="font-size:0.7rem;color:rgba(0,0,0,0.50);text-transform:uppercase;margin-bottom:4px;">College / Office</div>
                        <div id="ownedItemCollege" style="font-weight:600;color:#1a1d23;font-size:0.9rem;"></div>
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
