<?php
$page_title = 'Settings';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitizeInput($_POST['action']);

    if ($action === 'update_profile') {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);

        // In hardcoded mode, just redirect
        redirectWithMessage('settings.php', 'Profile updated successfully!', 'success');
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $password_error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $password_error = 'Password must be at least 6 characters';
        } elseif (!verifyPassword($current_password, $current_user['password'])) {
            $password_error = 'Current password is incorrect';
        } else {
            // In hardcoded mode, just redirect
            redirectWithMessage('settings.php', 'Password changed successfully!', 'success');
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php displayMessage(); ?>

<style>
/* ===== ADMIN SETTINGS ===== */
.as-layout { display:flex; gap:28px; align-items:flex-start; }
@media(max-width:768px){ .as-layout{ flex-direction:column; gap:16px; } }

/* Sidebar */
.as-sidebar { flex:0 0 280px; }
.as-profile-card {
    background:#8B0000;
    border-radius:8px; padding:32px 26px 26px; text-align:center;
    margin-bottom:18px; color:#fff;
    border:1px solid #6b0000;
}
.as-avatar {
    width:90px; height:90px; border-radius:50%;
    background:rgba(255,255,255,0.15);
    border:2px solid rgba(255,255,255,0.35);
    display:flex; align-items:center; justify-content:center;
    font-size:2.2rem; font-weight:900; margin:0 auto 18px; color:#fff;
}
.as-profile-name   { font-size:1.12rem; font-weight:900; margin-bottom:6px; letter-spacing:-0.3px; text-shadow:0 2px 4px rgba(0,0,0,0.15); }
.as-profile-email  { font-size:0.75rem; opacity:0.80; margin-bottom:16px; word-break:break-all; font-weight:500; }
.as-profile-divider { border:none; border-top:1.5px solid rgba(255,255,255,0.18); margin:0 0 16px; }
.as-profile-meta { display:flex; flex-direction:column; gap:10px; }
.as-profile-meta-row {
    display:flex; align-items:center; gap:10px;
    background:rgba(255,255,255,0.14); border-radius:6px;
    padding:10px 14px; font-size:0.79rem; font-weight:600; text-align:left;
    border:1px solid rgba(255,255,255,0.10);
}
.as-profile-meta-row:hover {
    background:rgba(255,255,255,0.20);
}
.as-profile-meta-row i { width:16px; text-align:center; opacity:0.90; }
.as-role-pill { display:inline-flex; align-items:center; padding:6px 16px; border-radius:4px; font-size:0.76rem; font-weight:700; background:rgba(255,255,255,0.25); color:#fff; margin-bottom:0; border:1px solid rgba(255,255,255,0.20); }

.as-nav {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
}
.as-nav-link {
    display:flex; align-items:center; gap:12px;
    padding:15px 18px; font-size:0.88rem; font-weight:650;
    color:rgba(0,0,0,0.55); text-decoration:none;
    border:none; cursor:pointer;
    transition:all 0.2s ease;
    background:transparent;
    width:100%; text-align:left;
    position:relative;
    border-bottom:1px solid rgba(0,0,0,0.06);
}
.as-nav-link:last-child { border-bottom:none; }
.as-nav-link:focus { outline:none; }
.as-nav-link.active {
    color:#111;
    background:#f7f7f7;
    font-weight:700;
    border-left:3px solid #8B0000;
}
.as-nav-link:not(.active):hover {
    background:rgba(139,0,0,0.10);
    color:#8B0000;
}
.as-nav-link:not(.active):active {
    background:rgba(139,0,0,0.15);
}
.as-nav-link i:first-child { 
    width:18px; text-align:center; transition:all 0.3s ease;
    font-size:1rem;
}
.as-nav-arrow {
    margin-left:auto;
    opacity:0.28;
    font-size:0.65rem;
    transition:all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.as-nav-link.active .as-nav-arrow {
    opacity:0.90;
    color:#fff;
    transform:translateX(3px);
}

/* Content */
.as-content { flex:1; min-width:0; }
.as-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:32px; margin-bottom:24px;
}
.as-card-title {
    font-size:1.2rem; font-weight:900; color:#1a1d23; margin-bottom:6px;
    letter-spacing:-0.3px; text-shadow:0 1px 2px rgba(0,0,0,0.02);
}
.as-card-sub   { font-size:0.82rem; color:rgba(0,0,0,0.45); margin-bottom:24px; font-weight:500; }
.as-card-divider { border:none; border-top:1.5px solid rgba(0,0,0,0.08); margin:0 0 24px; }
.as-section-label {
    font-size:0.70rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.6px; color:rgba(0,0,0,0.40);
    margin-bottom:14px; padding-bottom:8px; border-bottom:1.5px solid rgba(0,0,0,0.08);
}
.as-tab-pane { display:none; opacity:0; transition:opacity 0.3s ease; }
.as-tab-pane.active { display:block; opacity:1; }

/* Form fields */
.as-field-group { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
@media(max-width:576px){ .as-field-group{ grid-template-columns:1fr; } }
.as-field label {
    display:block; font-size:0.79rem; font-weight:700; color:rgba(0,0,0,0.58);
    margin-bottom:8px; text-transform:uppercase; letter-spacing:0.4px;
}
.as-input-wrap {
    position:relative;
}
.as-input-wrap .form-control {
    border-radius:6px; border:1px solid #e5e7eb;
    background:#fff; padding:12px 16px 12px 42px;
    font-size:0.91rem; font-weight:500; transition:border-color 0.15s;
    height:auto; color:#111;
}
.as-input-wrap .form-control:focus {
    border-color:#8B0000; box-shadow:0 0 0 3px rgba(139,0,0,0.08);
    background:#fff;
}
.as-input-wrap .form-control::placeholder { color:rgba(0,0,0,0.30); font-weight:500; }
.as-input-icon {
    position:absolute; left:14px; top:50%; transform:translateY(-50%);
    color:rgba(0,0,0,0.32); font-size:0.88rem; pointer-events:none;
    transition:color 0.2s ease;
}
.as-input-wrap .form-control:focus ~ .as-input-icon {
    color:#8B0000;
}

/* Password strength */
.as-strength-bar {
    height:6px; border-radius:4px; background:rgba(0,0,0,0.08);
    overflow:hidden; margin-top:8px;
}
.as-strength-fill {
    height:100%; border-radius:4px; transition:width 0.3s ease, background 0.3s ease;
    width:0%;
}
.as-strength-label {
    font-size:0.74rem; margin-top:6px; font-weight:700;
    text-transform:uppercase; letter-spacing:0.3px;
}
.as-eye-btn {
    position:absolute; right:14px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:rgba(0,0,0,0.35);
    cursor:pointer; padding:0; font-size:0.98rem;
    transition:all 0.2s ease;
}
.as-eye-btn:hover { color:#8B0000; transform:translateY(-50%) scale(1.15); }

/* Buttons */
.as-btn-primary {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:12px 28px !important; font-size:0.89rem !important;
    transition:background 0.15s !important;
    text-transform:uppercase !important;
    letter-spacing:0.3px !important;
}
.as-btn-primary:hover {
    color:#fff !important;
    background:#6b0000 !important;
}

/* Catalog items */
.as-catalog-item {
    display:flex; align-items:center; gap:14px;
    padding:14px 16px; border-radius:6px; margin-bottom:8px;
    background:#f7f7f7; border:1px solid #e5e7eb;
    font-size:0.88rem;
}
.as-catalog-item:hover {
    background:#f0f0f0;
    border-color:#d1d5db;
}
.as-catalog-item-id {
    font-size:0.70rem; font-weight:800; background:rgba(139,0,0,0.08);
    color:#8B0000; border-radius:4px; padding:4px 10px; flex-shrink:0;
    text-transform:uppercase; letter-spacing:0.3px;
}
.as-catalog-item-name { font-weight:700; color:#1a1d23; }
.as-catalog-item-desc { font-size:0.79rem; color:rgba(0,0,0,0.48); font-weight:500; }
.as-cat-label {
    font-size:0.75rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.4px; color:rgba(0,0,0,0.40);
    margin-bottom:12px; margin-top:18px;
    padding-bottom:8px; border-bottom:1.5px solid rgba(0,0,0,0.08);
}

/* System info rows */
.as-info-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:14px 0; border-bottom:1.5px solid rgba(0,0,0,0.06);
    font-size:0.88rem; transition:all 0.2s ease;
}
.as-info-row:hover {
    background:rgba(139,0,0,0.02);
    padding:14px 12px;
    border-radius:8px;
}
.as-info-row:last-child { border-bottom:none; }
.as-info-label { color:rgba(0,0,0,0.48); font-weight:600; }
.as-info-value {
    font-weight:800; color:#1a1d23;
    background:rgba(139,0,0,0.06); padding:4px 12px;
    border-radius:8px; font-size:0.85rem;
}

/* Table */
.as-table { width:100%; border-collapse:collapse; margin-top:14px; }
.as-table th {
    font-size:0.70rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.5px; color:rgba(0,0,0,0.42);
    padding:12px 14px; border-bottom:1.5px solid rgba(0,0,0,0.10);
    background:rgba(139,0,0,0.03);
}
.as-table td {
    padding:12px 14px; border-bottom:1px solid rgba(0,0,0,0.06);
    font-size:0.88rem; color:#374151; font-weight:500;
}
.as-table tr:hover td {
    background:rgba(139,0,0,0.02);
}
.as-table tr:last-child td { border-bottom:none; }
.as-badge {
    display:inline-flex; align-items:center; padding:4px 12px;
    border-radius:4px; font-size:0.75rem; font-weight:800;
    text-transform:uppercase; letter-spacing:0.3px;
}
.as-badge-primary {
    background:rgba(139,0,0,0.08);
    color:#8B0000;
}
.as-note {
    background:#f0f4ff;
    border:1px solid rgba(59,130,246,0.22); border-radius:6px;
    padding:14px 18px; font-size:0.84rem; color:#1d4ed8; margin-top:18px;
    font-weight:500;
}
</style>

<div class="container-fluid mt-4 pb-4">
<div class="as-layout">

    <!-- Sidebar -->
    <div class="as-sidebar">
        <?php
        $initial = strtoupper(substr($current_user['full_name'], 0, 1));
        ?>
        <div class="as-profile-card">
            <div class="as-avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="as-profile-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
            <div class="as-profile-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
            <hr class="as-profile-divider">
            <?php
            $admin_campus_name = '';
            if (!empty($current_user['campus_id'])) {
                foreach (getCampuses() as $c) {
                    if ($c['id'] == $current_user['campus_id']) { $admin_campus_name = $c['name']; break; }
                }
            }
            ?>
            <div class="as-profile-meta">
                <div class="as-profile-meta-row">
                    <i class="fas fa-shield-alt"></i>
                    <span><?php echo ucfirst($current_user['role']); ?></span>
                </div>
                <?php if (!empty($current_user['phone'])): ?>
                <div class="as-profile-meta-row">
                    <i class="fas fa-phone"></i>
                    <span><?php echo htmlspecialchars($current_user['phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($admin_campus_name): ?>
                <div class="as-profile-meta-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($admin_campus_name); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <nav class="as-nav" id="asNav">
            <button class="as-nav-link active" onclick="switchAsTab('profile-tab',this)">
                <i class="fas fa-user"></i> Profile
                <i class="fas fa-chevron-right as-nav-arrow"></i>
            </button>
            <button class="as-nav-link" onclick="switchAsTab('password-tab',this)">
                <i class="fas fa-lock"></i> Change Password
                <i class="fas fa-chevron-right as-nav-arrow"></i>
            </button>
            <button class="as-nav-link" onclick="switchAsTab('catalog-tab',this)">
                <i class="fas fa-list-ul"></i> Borrow Catalog
                <i class="fas fa-chevron-right as-nav-arrow"></i>
            </button>
            <button class="as-nav-link" onclick="switchAsTab('system-tab',this)">
                <i class="fas fa-sliders-h"></i> System Settings
                <i class="fas fa-chevron-right as-nav-arrow"></i>
            </button>
        </nav>
    </div>

    <!-- Tab content -->
    <div class="as-content">

        <!-- Profile Tab -->
        <div class="as-tab-pane active" id="profile-tab">
            <div class="as-card">
                <div class="as-card-title"><i class="fas fa-user-edit me-2" style="opacity:0.8;"></i>Edit Profile</div>
                <div class="as-card-sub">Manage your personal account information</div>
                <hr class="as-card-divider">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="as-field-group">
                        <div class="as-field">
                            <label>Full Name</label>
                            <div class="as-input-wrap">
                                <i class="fas fa-user as-input-icon"></i>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                            </div>
                        </div>
                        <div class="as-field">
                            <label>Phone Number</label>
                            <div class="as-input-wrap">
                                <i class="fas fa-phone as-input-icon"></i>
                                <input type="tel" class="form-control" name="phone" placeholder="(555) 123-4567" value="<?php echo htmlspecialchars($current_user['phone']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="as-field mb-4">
                        <label>Email Address</label>
                        <div class="as-input-wrap">
                            <i class="fas fa-envelope as-input-icon"></i>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px;">
                        <button type="submit" class="btn as-btn-primary"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Password Tab -->
        <div class="as-tab-pane" id="password-tab">
            <div class="as-card">
                <div class="as-card-title"><i class="fas fa-lock me-2" style="opacity:0.8;"></i>Change Password</div>
                <div class="as-card-sub">Use a strong password to protect your admin account</div>
                <?php if (isset($password_error)): ?>
                <div style="background:rgba(220,38,38,0.06); border:1px solid rgba(220,38,38,0.22); border-radius:6px; padding:14px 18px; margin-bottom:22px; font-size:0.84rem; color:#dc2626; font-weight:600; display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-exclamation-circle" style="font-size:1rem;"></i>
                    <span><?php echo htmlspecialchars($password_error); ?></span>
                </div>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-4">
                        <label class="form-label">Current Password</label>
                        <div class="as-input-wrap">
                            <i class="fas fa-key as-input-icon"></i>
                            <input type="password" class="form-control" name="current_password" id="asCurPw" required>
                            <button type="button" class="as-eye-btn" onclick="toggleAsPw('asCurPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">New Password</label>
                        <div class="as-input-wrap">
                            <i class="fas fa-shield-alt as-input-icon"></i>
                            <input type="password" class="form-control" name="new_password" id="asNewPw" oninput="checkAsStrength(this.value)" required>
                            <button type="button" class="as-eye-btn" onclick="toggleAsPw('asNewPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        <div class="as-strength-bar"><div class="as-strength-fill" id="asStrBar"></div></div>
                        <div class="as-strength-label" id="asStrLabel" style="color:rgba(0,0,0,0.35);">Enter a password</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirm New Password</label>
                        <div class="as-input-wrap">
                            <i class="fas fa-check-circle as-input-icon"></i>
                            <input type="password" class="form-control" name="confirm_password" id="asConPw" required>
                            <button type="button" class="as-eye-btn" onclick="toggleAsPw('asConPw',this)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:28px;">
                        <button type="submit" class="btn as-btn-primary"><i class="fas fa-check me-2"></i>Update Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Catalog Tab -->
        <div class="as-tab-pane" id="catalog-tab">
            <div class="as-card">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                    <div class="as-card-title mb-0"><i class="fas fa-list-ul me-2" style="opacity:0.8;"></i>Available Items for Borrowing</div>
                    <span class="as-badge as-badge-primary"><i class="fas fa-box me-1"></i><?php $available_items = filterByColumn(getInventory(), 'status', 'available'); echo count($available_items); ?> available</span>
                </div>
                <div class="as-card-sub">Inventory items available for user borrow requests</div>
                <?php
                // Get all available inventory items
                $available_items = filterByColumn(getInventory(), 'status', 'available');
                $borrow_catalog = getBorrowCatalog();
                $catalog_ids = array_column($borrow_catalog, 'id');
                
                // Group by category
                $items_by_category = [];
                foreach ($available_items as $item) {
                    $cat = $item['category'];
                    if (!isset($items_by_category[$cat])) {
                        $items_by_category[$cat] = [];
                    }
                    $items_by_category[$cat][] = $item;
                }
                ksort($items_by_category);
                ?>
                <?php if (empty($available_items)): ?>
                <div style="background:rgba(139,0,0,0.05); border:1.5px dashed rgba(139,0,0,0.20); border-radius:12px; padding:24px; text-align:center; color:rgba(0,0,0,0.45); font-size:0.85rem;">
                    <i class="fas fa-inbox me-2" style="font-size:1.8rem; opacity:0.6;"></i><br>
                    No available items in inventory at this time.
                </div>
                <?php else: ?>
                <?php foreach ($items_by_category as $category => $items): ?>
                <div class="as-cat-label"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($category); ?></div>
                <?php foreach ($items as $item): ?>
                <div class="as-catalog-item">
                    <span class="as-catalog-item-id">#<?php echo $item['id']; ?></span>
                    <div style="flex:1;">
                        <div class="as-catalog-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="as-catalog-item-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:0.72rem; font-weight:700; background:rgba(34,197,94,0.10); color:#15803d; padding:3px 10px; border-radius:4px; border:1px solid rgba(34,197,94,0.22);">
                            <i class="fas fa-check-circle me-1"></i>Available
                        </span>
                        <?php if (in_array($item['id'], $catalog_ids)): ?>
                        <span style="font-size:0.72rem; font-weight:700; background:rgba(139,0,0,0.08); color:#8B0000; padding:3px 10px; border-radius:4px; border:1px solid rgba(139,0,0,0.18);">
                            <i class="fas fa-star me-1"></i>Borrowable
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="as-note mt-4"><i class="fas fa-lightbulb me-2"></i><strong>Info:</strong> This shows all available inventory items. Items marked "Borrowable" are in the borrow catalog. Update the <code style="background:rgba(0,0,0,0.10); padding:2px 6px; border-radius:4px; font-weight:600;">getBorrowCatalog()</code> function in <code style="background:rgba(0,0,0,0.10); padding:2px 6px; border-radius:4px; font-weight:600;">config/data.php</code> to control which items can be borrowed.</div>
            </div>
        </div>

        <!-- System Settings Tab -->
        <div class="as-tab-pane" id="system-tab">
            <div class="as-card">
                <div class="as-card-title"><i class="fas fa-server me-2" style="opacity:0.8;"></i>System Information</div>
                <div class="as-card-sub">Pampanga State University — PSU Asset Management</div>
                <div class="as-info-row">
                    <span class="as-info-label"><i class="fas fa-university me-2"></i>Institution</span>
                    <span class="as-info-value">Pampanga State University (PSU)</span>
                </div>
                <div class="as-info-row">
                    <span class="as-info-label"><i class="fas fa-code-branch me-2"></i>Application Version</span>
                    <span class="as-info-value">1.0.0</span>
                </div>
                <div class="as-info-row">
                    <span class="as-info-label"><i class="fas fa-database me-2"></i>Data Mode</span>
                    <span class="as-info-value"><i class="fas fa-check me-1" style="color:#15803d;"></i>Hardcoded (no database)</span>
                </div>
                <div class="as-info-row">
                    <span class="as-info-label"><i class="fas fa-clock me-2"></i>Current Date &amp; Time</span>
                    <span class="as-info-value"><?php echo date('F d, Y — g:i A'); ?></span>
                </div>
            </div>
            <div class="as-card">
                <div class="as-card-title"><i class="fas fa-map-marker-alt me-2" style="opacity:0.8;"></i>Registered Campuses</div>
                <div class="as-card-sub">All institution campuses in the system</div>
                <?php $campuses = getAllCampuses(); ?>
                <table class="as-table">
                    <thead><tr><th><i class="fas fa-building me-1"></i>Campus Name</th><th><i class="fas fa-map-pin me-1"></i>Location</th><th style="text-align:right;"><i class="fas fa-boxes me-1"></i>Items</th></tr></thead>
                    <tbody>
                    <?php foreach ($campuses as $campus): $item_count = getInventoryCount($campus['id']); ?>
                    <tr>
                        <td style="font-weight:700; color:#1a1d23;"><?php echo htmlspecialchars($campus['name']); ?></td>
                        <td style="color:rgba(0,0,0,0.50);"><?php echo htmlspecialchars($campus['location']); ?></td>
                        <td style="text-align:right;"><span class="as-badge as-badge-primary"><i class="fas fa-cube me-1"></i><?php echo $item_count; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- .as-content -->
</div><!-- .as-layout -->
</div><!-- container -->
</div><!-- main-wrapper -->

<script>
function switchAsTab(tabId, btn) {
    document.querySelectorAll('.as-tab-pane').forEach(p => {
        p.classList.remove('active');
    });
    document.querySelectorAll('.as-nav-link').forEach(b => {
        b.classList.remove('active');
    });
    const targetTab = document.getElementById(tabId);
    if (targetTab) {
        targetTab.classList.add('active');
        btn.classList.add('active');
        window.location.hash = '#' + tabId;
    }
}

function toggleAsPw(fieldId, btn) {
    const f = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
        f.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
    }
}

function checkAsStrength(pw) {
    const bar = document.getElementById('asStrBar');
    const lbl = document.getElementById('asStrLabel');
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    
    const levels = [
        {w:'0%',   c:'rgba(0,0,0,0.08)', t:'Too short',    tc:'rgba(0,0,0,0.35)'},
        {w:'25%',  c:'#dc2626',           t:'Weak',         tc:'#dc2626'},
        {w:'50%',  c:'#b45309',           t:'Fair',         tc:'#b45309'},
        {w:'75%',  c:'#0369a1',           t:'Good',         tc:'#0369a1'},
        {w:'100%', c:'#15803d',           t:'Strong',       tc:'#15803d'},
    ];
    
    const l = pw.length === 0 ? levels[0] : levels[Math.min(score, 4)];
    bar.style.width = l.w;
    bar.style.background = l.c;
    lbl.textContent = l.t;
    lbl.style.color = l.tc;
}

// Auto-activate tab if hash is present
(function() {
    const hash = window.location.hash;
    if (hash && hash.includes('-tab')) {
        const tabId = hash.slice(1);
        const btn = document.querySelector(`[onclick*="${tabId}"]`);
        if (btn) switchAsTab(tabId, btn);
    }
})();

// Add smooth scrolling for mobile
if (window.innerWidth <= 768) {
    document.querySelectorAll('.as-nav-link').forEach(link => {
        link.addEventListener('click', function() {
            setTimeout(() => {
                const contentTop = document.querySelector('.as-content').getBoundingClientRect().top + window.scrollY - 20;
                window.scrollTo({ top: contentTop, behavior: 'smooth' });
            }, 100);
        });
    });
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
