<?php
$page_title = 'Campus Details';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();
$campus_id = $_GET['campus_id'] ?? 1;

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';

// Get campus info
$campuses = getAllCampuses();
$campus = findById($campuses, $campus_id);

// Get inventory for this campus
$all_inventory = getInventory();
$campus_inventory = filterByColumn($all_inventory, 'campus_id', $campus_id);
$status_counts = countByStatus($campus_inventory);

// Get colleges and offices for Main Campus
$colleges = [];
$offices = [];
if ($campus_id == 1) {
    $colleges = getMainCampusColleges();
    $offices = getMainCampusOffices();
}
?>
<style>
/* Campus Detail Page */
.cd-wrapper { padding: 40px 32px 60px; background: #f7f7f7; min-height: 100vh; }

.cd-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 32px; gap: 20px;
    padding-bottom: 20px; border-bottom: 2px solid #e5e7eb;
}
.cd-header-left h1 {
    font-size: 2.2rem; font-weight: 950; color: #0f172a;
    margin: 0 0 8px; letter-spacing: -.8px;
}
.cd-header-left p {
    margin: 0; font-size: .95rem; color: #555; font-weight: 500;
}
.cd-breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: .85rem; color: #999;
}
.cd-breadcrumb a {
    color: #8B0000; text-decoration: none; font-weight: 600;
    transition: color .2s ease;
}
.cd-breadcrumb a:hover { color: #b91c1c; }

.cd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
@media(max-width:1024px) { .cd-grid { grid-template-columns: 1fr; } }

.cd-card {
    background: #fff; border-radius: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    padding: 28px;
    position: relative; overflow: hidden;
}

.cd-card-head {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 24px; padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}
.cd-card-icon {
    font-size: .85rem; color: #8B0000; flex-shrink: 0;
}
.cd-card-title {
    font-size: 1.1rem; font-weight: 800; color: #0f172a; letter-spacing: -.3px;
}

.cd-stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
.cd-stat-box {
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 16px;
    text-align: center;
    transition: border-color .2s ease;
}
.cd-stat-box:hover {
    border-color: rgba(139,0,0,.3);
}
.cd-stat-val { font-size: 2rem; font-weight: 950; color: #0f172a; line-height: 1; }
.cd-stat-lbl { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #999; margin-top: 6px; }

.cd-list { display: flex; flex-direction: column; gap: 8px; }
.cd-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; border-radius: 6px;
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    font-size: .9rem; color: #374151;
    transition: border-color .2s ease;
    cursor: default;
}
.cd-list-item:hover {
    border-color: rgba(139,0,0,.2);
}
.cd-list-icon {
    font-size: .85rem; color: #8B0000; flex-shrink: 0;
}
.cd-list-text { flex: 1; min-width: 0; }
.cd-list-name { font-weight: 700; color: #0f172a; margin-bottom: 2px; }
.cd-list-sub { font-size: .75rem; color: #999; }

.cd-section {
    margin-bottom: 32px;
}
.cd-section-title {
    font-size: 1.2rem; font-weight: 850; color: #0f172a;
    margin-bottom: 16px; padding-bottom: 12px;
    border-bottom: 2px solid #e5e7eb;
    display: flex; align-items: center; gap: 10px;
}
.cd-section-icon {
    font-size: .9rem; color: #8B0000; flex-shrink: 0;
}

.cd-inventory-summary {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}
.cd-inv-badge {
    display: flex; flex-direction: column; align-items: center;
    padding: 16px 14px; border-radius: 6px;
    background: #f7f7f7;
    border: 1px solid #e5e7eb;
    text-align: center;
    transition: border-color .2s ease;
}
.cd-inv-badge:hover {
    border-color: rgba(139,0,0,.2);
}
.cd-inv-val { font-size: 1.8rem; font-weight: 950; color: #0f172a; line-height: 1; }
.cd-inv-lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #999; margin-top: 6px; }

@media(max-width:768px) {
    .cd-wrapper { padding: 24px 18px 60px; }
    .cd-header { flex-direction: column; align-items: flex-start; }
    .cd-grid { grid-template-columns: 1fr; }
    .cd-stat-grid { grid-template-columns: 1fr 1fr; }
    .cd-inventory-summary { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="main-wrapper">
<div class="cd-wrapper">

    <!-- Header -->
    <div class="cd-header">
        <div class="cd-header-left">
            <div class="cd-breadcrumb">
                <a href="dashboard.php">Dashboard</a>&nbsp;/&nbsp;Campus Details
            </div>
            <h1><?php echo htmlspecialchars($campus['name']); ?></h1>
            <p><?php echo htmlspecialchars($campus['location']); ?></p>
        </div>
    </div>

    <!-- Campus Overview -->
    <div class="cd-grid">
        <!-- Campus Info -->
        <div class="cd-card" style="animation-delay:.05s;">
            <div class="cd-card-head">
                <div class="cd-card-icon"><i class="fas fa-building"></i></div>
                <div class="cd-card-title">Campus Information</div>
            </div>
            <div style="color:#374151; line-height:1.6;">
                <p style="margin:0 0 12px; font-size:.95rem;">
                    <?php echo htmlspecialchars($campus['description']); ?>
                </p>
                <div style="padding-top:12px; border-top:1px solid #f1f5f9;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                        <span style="font-size:.85rem; color:#64748b;">Campus ID:</span>
                        <span style="font-weight:700;"><?php echo $campus['id']; ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="font-size:.85rem; color:#64748b;">Type:</span>
                        <span style="font-weight:700;"><?php echo $campus_id == 1 ? 'Main Campus' : 'Extension Campus'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Stats -->
        <div class="cd-card" style="animation-delay:.10s;">
            <div class="cd-card-head">
                <div class="cd-card-icon"><i class="fas fa-chart-bar"></i></div>
                <div class="cd-card-title">Inventory Summary</div>
            </div>
            <div class="cd-inventory-summary">
                <div class="cd-inv-badge">
                    <div class="cd-inv-val"><?php echo count($campus_inventory); ?></div>
                    <div class="cd-inv-lbl">Total Items</div>
                </div>
                <div class="cd-inv-badge">
                    <div class="cd-inv-val" style="color:#15803d;"><?php echo $status_counts['available'] ?? 0; ?></div>
                    <div class="cd-inv-lbl">Available</div>
                </div>
                <div class="cd-inv-badge">
                    <div class="cd-inv-val" style="color:#b45309;"><?php echo $status_counts['borrowed'] ?? 0; ?></div>
                    <div class="cd-inv-lbl">Borrowed</div>
                </div>
                <div class="cd-inv-badge">
                    <div class="cd-inv-val" style="color:#1d4ed8;"><?php echo $status_counts['maintenance'] ?? 0; ?></div>
                    <div class="cd-inv-lbl">Maintenance</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Colleges Section (Main Campus Only) -->
    <?php if ($campus_id == 1 && count($colleges) > 0): ?>
    <div class="cd-section">
        <div class="cd-section-title">
            <div class="cd-section-icon"><i class="fas fa-graduation-cap"></i></div>
            Colleges (<?php echo count($colleges); ?>)
        </div>
        <div class="cd-card" style="animation-delay:.15s;">
            <div class="cd-list">
                <?php foreach ($colleges as $code => $name): ?>
                <div class="cd-list-item">
                    <div class="cd-list-icon"><i class="fas fa-book"></i></div>
                    <div class="cd-list-text">
                        <div class="cd-list-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="cd-list-sub">Code: <?php echo htmlspecialchars($code); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Offices Section (Main Campus Only) -->
    <?php if ($campus_id == 1 && count($offices) > 0): ?>
    <div class="cd-section">
        <div class="cd-section-title">
            <div class="cd-section-icon"><i class="fas fa-briefcase"></i></div>
            Administrative Offices (<?php echo count($offices); ?>)
        </div>
        <div class="cd-card" style="animation-delay:.20s;">
            <div class="cd-list">
                <?php foreach ($offices as $code => $name): ?>
                <div class="cd-list-item">
                    <div class="cd-list-icon"><i class="fas fa-door-open"></i></div>
                    <div class="cd-list-text">
                        <div class="cd-list-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="cd-list-sub">Code: <?php echo htmlspecialchars($code); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Inventory Items -->
    <div class="cd-section">
        <div class="cd-section-title">
            <div class="cd-section-icon"><i class="fas fa-warehouse"></i></div>
            Campus Inventory Items (<?php echo count($campus_inventory); ?>)
        </div>
        <div class="cd-card" style="animation-delay:.25s;">
            <?php if (count($campus_inventory) > 0): ?>
            <div class="table-responsive">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid #f1f5f9;">
                            <th style="padding:12px 16px; text-align:left; font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8;">Item Name</th>
                            <th style="padding:12px 16px; text-align:left; font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8;">Category</th>
                            <th style="padding:12px 16px; text-align:center; font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8;">Qty</th>
                            <th style="padding:12px 16px; text-align:center; font-size:.7rem; font-weight:750; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($campus_inventory, 0, 10) as $item): ?>
                        <tr style="border-bottom:1px solid #f1f5f9; transition: all .2s ease;">
                            <td style="padding:14px 16px; color:#374151; font-weight:600;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td style="padding:14px 16px; color:#94a3b8; font-size:.9rem;"><?php echo htmlspecialchars($item['category']); ?></td>
                            <td style="padding:14px 16px; text-align:center; font-weight:700; color:#0f172a;"><?php echo $item['quantity']; ?></td>
                            <td style="padding:14px 16px; text-align:center;">
                                <span style="display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:6px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px;
                                    background:<?php echo $item['status'] === 'available' ? 'rgba(34,197,94,.12)' : ($item['status'] === 'borrowed' ? 'rgba(245,158,11,.12)' : 'rgba(59,130,246,.12)'); ?>;
                                    color:<?php echo $item['status'] === 'available' ? '#15803d' : ($item['status'] === 'borrowed' ? '#b45309' : '#1d4ed8'); ?>;">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($campus_inventory) > 10): ?>
            <div style="margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9; text-align:center;">
                <a href="inventory.php?campus_id=<?php echo $campus_id; ?>" style="color:#8B0000; font-weight:700; text-decoration:none;">View all items (<?php echo count($campus_inventory); ?>)</a>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div style="padding:32px; text-align:center; color:#94a3b8;">
                <i class="fas fa-inbox" style="font-size:2.5rem; margin-bottom:12px; display:block; opacity:.5;"></i>
                <p style="margin:0; font-size:.95rem;">No inventory items for this campus yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
