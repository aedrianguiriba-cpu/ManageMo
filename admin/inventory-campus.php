<?php
$page_title = 'Campus Inventory';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php $campuses = getAllCampuses(); ?>

<style>
/* ===== CAMPUS INVENTORY ===== */
.ic-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:18px; }
@media(max-width:768px){ .ic-grid{ grid-template-columns:1fr; } }

.ic-campus-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07); padding:22px 24px;
    display:flex; flex-direction:column; gap:16px;
}
.ic-campus-header {}
.ic-campus-name { font-size:1rem; font-weight:800; color:#1a1d23; margin-bottom:2px; }
.ic-campus-loc  { font-size:0.78rem; color:rgba(0,0,0,0.42); }

.ic-stat-row { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
.ic-stat-box {
    border-radius:11px; padding:12px 14px; text-align:center;
}
.ic-stat-val { font-size:1.6rem; font-weight:900; line-height:1; }
.ic-stat-lbl { font-size:0.72rem; font-weight:600; margin-top:3px; }

.ic-status-list { border-top:1px solid rgba(0,0,0,0.06); padding-top:12px; display:flex; flex-direction:column; gap:6px; }
.ic-status-row  { display:flex; align-items:center; justify-content:space-between; font-size:0.84rem; }
.ic-status-lbl  { color:rgba(0,0,0,0.55); font-weight:600; }

.ic-badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:20px; font-size:0.74rem; font-weight:700; }
.ic-badge-warning   { background:rgba(245,158,11,0.12); color:#b45309; }
.ic-badge-danger    { background:rgba(239,68,68,0.12);  color:#dc2626; }
.ic-badge-info      { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.ic-badge-secondary { background:rgba(0,0,0,0.07);       color:rgba(0,0,0,0.50); }

.ic-btn-view {
    background:linear-gradient(135deg,#8B0000,#b91c1c) !important;
    border:none !important; border-radius:11px !important;
    font-weight:700 !important; color:#fff !important;
    padding:10px 18px !important; font-size:0.87rem !important;
    box-shadow:0 4px 12px rgba(139,0,0,0.22) !important;
    text-decoration:none; display:flex; align-items:center; justify-content:center; gap:7px;
    transition:transform 0.15s !important;
}
.ic-btn-view:hover { color:#fff !important; transform:translateY(-1px) !important; }

.ic-main-campus { grid-column: 1 / -1; }
.ic-college-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
@media(max-width:992px){ .ic-college-grid{ grid-template-columns:repeat(4,1fr); } }
@media(max-width:768px){ .ic-college-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .ic-college-grid{ grid-template-columns:repeat(1,1fr); } }
.ic-college-card {
    background:rgba(139,0,0,0.04); border:1px solid rgba(139,0,0,0.09);
    border-radius:12px; padding:14px 16px; transition:background 0.15s;
}
.ic-college-card:hover { background:rgba(139,0,0,0.07); }
.ic-college-abbr { font-size:1.1rem; font-weight:900; color:#8B0000; line-height:1; }
.ic-college-full { font-size:0.70rem; color:rgba(0,0,0,0.42); margin-top:3px; line-height:1.3; margin-bottom:10px; }
.ic-college-stats { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.ic-college-total { font-size:0.82rem; font-weight:800; color:#1a1d23; }
.ic-college-avail { font-size:0.73rem; font-weight:600; color:#15803d; background:rgba(34,197,94,0.12); border-radius:8px; padding:1px 8px; }
.ic-college-status-list { display:flex; flex-direction:column; gap:4px; }
.ic-college-status-row { display:flex; align-items:center; justify-content:space-between; font-size:0.75rem; color:rgba(0,0,0,0.50); font-weight:600; }

/* Modal Styles */
.ic-modal {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);
    z-index:1000; align-items:center; justify-content:center;
}
.ic-modal.active { display:flex; }
.ic-modal-content {
    background:rgba(255,255,255,0.95); backdrop-filter:blur(16px);
    border-radius:20px; border:1px solid rgba(0,0,0,0.07);
    box-shadow:0 20px 60px rgba(0,0,0,0.15);
    max-width:900px; width:90%; max-height:85vh; overflow-y:auto;
    animation:slideUp 0.3s ease-out;
}
@keyframes slideUp {
    from { transform:translateY(30px); opacity:0; }
    to { transform:translateY(0); opacity:1; }
}
.ic-modal-header {
    padding:24px; border-bottom:1px solid rgba(0,0,0,0.07);
    display:flex; align-items:center; justify-content:space-between;
}
.ic-modal-title { font-size:1.3rem; font-weight:800; color:#1a1d23; }
.ic-modal-close {
    background:none; border:none; font-size:1.8rem; cursor:pointer;
    color:rgba(0,0,0,0.5); transition:color 0.15s;
}
.ic-modal-close:hover { color:#1a1d23; }
.ic-modal-body { padding:24px; }
.ic-items-table {
    width:100%; border-collapse:collapse; font-size:0.87rem;
}
.ic-items-table th {
    text-align:left; padding:12px 0; border-bottom:2px solid rgba(0,0,0,0.07);
    font-weight:700; color:rgba(0,0,0,0.55); text-transform:uppercase; font-size:0.75rem;
    letter-spacing:0.5px;
}
.ic-items-table td { padding:12px 0; border-bottom:1px solid rgba(0,0,0,0.05); }
.ic-items-table tr:hover td { background:rgba(0,0,0,0.015); }
.ic-modal-empty {
    text-align:center; padding:40px 24px; color:rgba(0,0,0,0.35);
}
.ic-modal-empty i { font-size:2.5rem; margin-bottom:12px; display:block; opacity:0.3; }

/* Owned Items Card Styles */
.ic-items-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.ic-item-card {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.08), rgba(34, 197, 94, 0.02));
    border: 1px solid rgba(34, 197, 94, 0.2);
    border-radius: 14px;
    padding: 18px;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ic-item-card:hover {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.12), rgba(34, 197, 94, 0.05));
    border-color: rgba(34, 197, 94, 0.4);
    box-shadow: 0 8px 24px rgba(34, 197, 94, 0.12);
    transform: translateY(-2px);
}
.ic-item-card-name {
    font-size: 1rem;
    font-weight: 800;
    color: #1a1d23;
    line-height: 1.4;
}
.ic-item-card-category {
    font-size: 0.73rem;
    font-weight: 600;
    color: #15803d;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.ic-item-card-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 10px 0;
    border-top: 1px solid rgba(34, 197, 94, 0.1);
    border-bottom: 1px solid rgba(34, 197, 94, 0.1);
}
.ic-item-card-stat {
    text-align: center;
}
.ic-item-card-stat-val {
    font-size: 1.4rem;
    font-weight: 900;
    color: #15803d;
    line-height: 1;
}
.ic-item-card-stat-lbl {
    font-size: 0.68rem;
    color: rgba(0, 0, 0, 0.45);
    font-weight: 600;
    margin-top: 4px;
}
.ic-item-card-condition {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8rem;
}
.ic-item-card-condition-lbl {
    color: rgba(0, 0, 0, 0.5);
    font-weight: 600;
}
.ic-item-card-condition-val {
    color: #1a1d23;
    font-weight: 700;
}
.ic-item-card-qr {
    background: rgba(0, 0, 0, 0.03);
    border-radius: 8px;
    padding: 8px;
    text-align: center;
    font-size: 0.72rem;
    font-family: 'Courier New', monospace;
    color: rgba(0, 0, 0, 0.6);
    word-break: break-all;
}
</style>

<div class="container-fluid mt-4 pb-4">
    <div class="ic-grid">
        <?php foreach ($campuses as $campus):
            $campus_inv   = filterByColumn(getInventory(), 'campus_id', $campus['id']);
            $status_counts = countByStatus($campus_inv);
        ?>
        <div class="ic-campus-card<?php echo $campus['id'] == 1 ? ' ic-main-campus' : ''; ?>">
            <div class="ic-campus-header">
                <div class="ic-campus-name"><?php echo htmlspecialchars($campus['name']); ?></div>
                <div class="ic-campus-loc"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($campus['location']); ?></div>
            </div>

            <?php if ($campus['id'] == 1 && !empty($campus['colleges'])): ?>
            <div>
                <div style="font-size:0.70rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(0,0,0,0.36);margin-bottom:10px;">Colleges</div>
                <?php
                $college_stats = [];
                foreach (getMainCampusColleges() as $abbr => $fullname) {
                    $col_items  = array_values(array_filter($campus_inv, fn($i) => ($i['college_id'] ?? '') === $abbr));
                    $col_status = countByStatus($col_items);
                    $college_stats[] = [
                        'abbr'        => $abbr,
                        'name'        => $fullname,
                        'owned'       => $col_status['available']   ?? 0,
                        'borrowed'    => $col_status['borrowed']    ?? 0,
                        'maintenance' => $col_status['maintenance'] ?? 0,
                        'requested'   => $col_status['requested']   ?? 0,
                    ];
                }
                ?>
                <div class="ic-college-grid">
                    <?php foreach ($college_stats as $cs): ?>
                    <div class="ic-college-card">
                        <div class="ic-college-abbr"><?php echo htmlspecialchars($cs['abbr']); ?></div>
                        <div class="ic-college-full"><?php echo htmlspecialchars($cs['name']); ?></div>
                        <div class="ic-college-status-list">
                            <div class="ic-college-status-row"><span>Owned</span><span class="ic-badge ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $cs['owned']; ?></span></div>
                            <div class="ic-college-status-row"><span>Borrowed</span><span class="ic-badge ic-badge-warning"><?php echo $cs['borrowed']; ?></span></div>
                            <div class="ic-college-status-row"><span>Maintenance</span><span class="ic-badge ic-badge-info"><?php echo $cs['maintenance']; ?></span></div>
                            <div class="ic-college-status-row"><span>Requested</span><span class="ic-badge ic-badge-secondary"><?php echo $cs['requested'] ?? 0; ?></span></div>
                        </div>
                        <button onclick="openInventoryModal(<?php echo $campus['id']; ?>, '<?php echo htmlspecialchars($cs['abbr']); ?>', '<?php echo htmlspecialchars($cs['name']); ?>')" style="background:rgba(139,0,0,0.12); border:1px solid rgba(139,0,0,0.2); border-radius:8px; padding:6px 12px; font-size:0.75rem; font-weight:600; color:#8B0000; cursor:pointer; transition:all 0.15s; margin-top:8px;" onmouseover="this.style.background='rgba(139,0,0,0.20)'" onmouseout="this.style.background='rgba(139,0,0,0.12)'"><i class="fas fa-eye" style="margin-right:4px;"></i>View</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:16px;">
                <div style="font-size:0.70rem;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:rgba(0,0,0,0.36);margin-bottom:10px;">Offices</div>
                <?php
                $office_stats = [];
                foreach (getMainCampusOffices() as $abbr => $fullname) {
                    $off_items  = array_values(array_filter($campus_inv, fn($i) => ($i['college_id'] ?? '') === $abbr));
                    $off_status = countByStatus($off_items);
                    $office_stats[] = [
                        'abbr'        => $abbr,
                        'name'        => $fullname,
                        'owned'       => $off_status['available']   ?? 0,
                        'borrowed'    => $off_status['borrowed']    ?? 0,
                        'maintenance' => $off_status['maintenance'] ?? 0,
                        'requested'   => $off_status['requested']   ?? 0,
                    ];
                }
                ?>
                <div class="ic-college-grid">
                    <?php foreach ($office_stats as $os): ?>
                    <div class="ic-college-card">
                        <div class="ic-college-abbr"><?php echo htmlspecialchars($os['abbr']); ?></div>
                        <div class="ic-college-full"><?php echo htmlspecialchars($os['name']); ?></div>
                        <div class="ic-college-status-list">
                            <div class="ic-college-status-row"><span>Owned</span><span class="ic-badge ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $os['owned']; ?></span></div>
                            <div class="ic-college-status-row"><span>Borrowed</span><span class="ic-badge ic-badge-warning"><?php echo $os['borrowed']; ?></span></div>
                            <div class="ic-college-status-row"><span>Maintenance</span><span class="ic-badge ic-badge-info"><?php echo $os['maintenance']; ?></span></div>
                            <div class="ic-college-status-row"><span>Requested</span><span class="ic-badge ic-badge-secondary"><?php echo $os['requested'] ?? 0; ?></span></div>
                        </div>
                        <button onclick="openInventoryModal(<?php echo $campus['id']; ?>, '<?php echo htmlspecialchars($os['abbr']); ?>', '<?php echo htmlspecialchars($os['name']); ?>')" style="background:rgba(139,0,0,0.12); border:1px solid rgba(139,0,0,0.2); border-radius:8px; padding:6px 12px; font-size:0.75rem; font-weight:600; color:#8B0000; cursor:pointer; transition:all 0.15s; margin-top:8px;" onmouseover="this.style.background='rgba(139,0,0,0.20)'" onmouseout="this.style.background='rgba(139,0,0,0.12)'"><i class="fas fa-eye" style="margin-right:4px;"></i>View</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="ic-status-list">
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Owned</span>
                    <span class="ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $status_counts['available'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Borrowed</span>
                    <span class="ic-badge ic-badge-warning"><?php echo $status_counts['borrowed'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Maintenance</span>
                    <span class="ic-badge ic-badge-info"><?php echo $status_counts['maintenance'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Requested</span>
                    <span class="ic-badge ic-badge-secondary"><?php echo $status_counts['requested'] ?? 0; ?></span>
                </div>
            </div>

            <button onclick="openInventoryModal(<?php echo $campus['id']; ?>, '', '<?php echo htmlspecialchars($campus['name']); ?>')" class="ic-btn-view" <?php echo $campus['id'] == 1 ? 'style="display:none;"' : ''; ?>>
                <i class="fas fa-eye"></i> View Inventory
            </button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</div>

<!-- Inventory Modal -->
<div id="inventoryModal" class="ic-modal">
    <div class="ic-modal-content">
        <div class="ic-modal-header">
            <div class="ic-modal-title" id="modalTitle">View Inventory</div>
            <button class="ic-modal-close" onclick="closeInventoryModal()">&times;</button>
        </div>
        <div class="ic-modal-body">
            <div id="modalContent">
                <div class="ic-modal-empty">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading inventory items...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openInventoryModal(campusId, filterCode, filterName) {
    const modal = document.getElementById('inventoryModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');
    
    modalTitle.textContent = filterName + ' - Inventory Items';
    
    // Loading state
    modalContent.innerHTML = '<div class="ic-modal-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading inventory items...</p></div>';
    modal.classList.add('active');
    
    // Fetch inventory items for campus and filter by college/office if provided
    const inventory = <?php echo json_encode(getInventory()); ?>;
    let allCampusItems = inventory.filter(item => item.campus_id == campusId);
    
    // If filterCode is provided (for main campus colleges/offices), further filter by college_id
    if (filterCode) {
        allCampusItems = allCampusItems.filter(item => item.college_id === filterCode);
    }
    
    // Separate items by status: owned (available) and others
    const ownedItems = allCampusItems.filter(item => item.status === 'available');
    const otherItems = allCampusItems.filter(item => item.status !== 'available');
    
    if (ownedItems.length === 0 && otherItems.length === 0) {
        modalContent.innerHTML = '<div class="ic-modal-empty"><i class="fas fa-inbox"></i><p>No inventory items found for this campus.</p></div>';
        return;
    }
    
    let html = '';
    
    // Display Owned Items as Cards
    if (ownedItems.length > 0) {
        html += '<div style="margin-bottom: 28px;">';
        html += '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">';
        html += '<h5 style="font-size: 0.9rem; font-weight: 800; color: #1a1d23; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;"><i class="fas fa-check-circle" style="color: #15803d; margin-right: 6px;"></i>Owned Items</h5>';
        html += '<span style="background: linear-gradient(135deg, #15803d, #059669); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">' + ownedItems.length + ' item' + (ownedItems.length !== 1 ? 's' : '') + '</span>';
        html += '</div>';
        html += '<div class="ic-items-cards-grid">';
        
        ownedItems.forEach(item => {
            html += '<div class="ic-item-card">';
            html += '<div class="ic-item-card-name">' + htmlEscape(item.item_name) + '</div>';
            html += '<div class="ic-item-card-category">' + htmlEscape(item.category) + '</div>';
            html += '<div class="ic-item-card-info">';
            html += '<div class="ic-item-card-stat"><div class="ic-item-card-stat-val">' + item.quantity + '</div><div class="ic-item-card-stat-lbl">Quantity</div></div>';
            html += '<div class="ic-item-card-stat"><div class="ic-item-card-stat-val" style="color: #15803d;"><i class="fas fa-check"></i></div><div class="ic-item-card-stat-lbl">Available</div></div>';
            html += '</div>';
            html += '<div class="ic-item-card-condition">';
            html += '<span class="ic-item-card-condition-lbl">Condition:</span>';
            html += '<span class="ic-item-card-condition-val">' + htmlEscape(item.condition || 'Good') + '</span>';
            html += '</div>';
            html += '<div class="ic-item-card-qr">QR: ' + htmlEscape(item.qr_code_id) + '</div>';
            html += '</div>';
        });
        
        html += '</div></div>';
    }
    
    // Display Other Items as Table
    if (otherItems.length > 0) {
        html += '<div style="margin-top: 28px;">';
        html += '<h5 style="font-size: 0.9rem; font-weight: 800; color: #1a1d23; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-info-circle" style="color: #1d4ed8; margin-right: 6px;"></i>Active Status Items (' + otherItems.length + ')</h5>';
        html += '<table class="ic-items-table"><thead><tr>';
        html += '<th>Item Name</th><th>Category</th><th>Quantity</th><th>Status</th><th>Condition</th><th>QR Code</th>';
        html += '</tr></thead><tbody>';
        
        otherItems.forEach(item => {
            const statusClass = getStatusBadgeClass(item.status);
            html += '<tr>';
            html += '<td><strong>' + htmlEscape(item.item_name) + '</strong></td>';
            html += '<td>' + htmlEscape(item.category) + '</td>';
            html += '<td style="text-align:center; font-weight:600;">' + item.quantity + '</td>';
            html += '<td><span class="ic-badge ' + statusClass + '">' + item.status.charAt(0).toUpperCase() + item.status.slice(1) + '</span></td>';
            html += '<td>' + htmlEscape(item.condition || 'N/A') + '</td>';
            html += '<td><code style="font-size:0.75rem;">' + htmlEscape(item.qr_code_id) + '</code></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        html += '</div>';
    }
    
    modalContent.innerHTML = html;
}

function closeInventoryModal() {
    document.getElementById('inventoryModal').classList.remove('active');
}

function getStatusBadgeClass(status) {
    switch(status) {
        case 'available': return 'ic-badge-success';
        case 'borrowed': return 'ic-badge-warning';
        case 'maintenance': return 'ic-badge-info';
        case 'requested': return 'ic-badge-secondary';
        case 'damaged': return 'ic-badge-danger';
        default: return 'ic-badge-secondary';
    }
}

function htmlEscape(text) {
    const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Close modal when clicking outside
document.getElementById('inventoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeInventoryModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('inventoryModal').classList.contains('active')) {
        closeInventoryModal();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
