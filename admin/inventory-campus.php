<?php
$page_title = 'Campus Inventory';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

// ── Department CRUD ──
$dept_msg = '';
$dept_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dept_action'])) {
    $custom = _loadCustomDepartments();
    $action = $_POST['dept_action'];

    if ($action === 'add') {
        $type = $_POST['dept_type'] ?? '';

        if ($type === 'campuses') {
            $name     = trim($_POST['campus_name'] ?? '');
            $location = trim($_POST['campus_location'] ?? '');
            $desc     = trim($_POST['campus_desc'] ?? '');
            if (!$name || !$location) {
                $dept_err = 'Campus name and location are required.';
            } else {
                $all_ids = array_column(getCampuses(), 'id');
                $new_id  = max($all_ids) + 1;
                $custom['campuses'][] = [
                    'id'          => $new_id,
                    'name'        => $name,
                    'location'    => $location,
                    'description' => $desc,
                    'colleges'    => [],
                ];
                saveCustomDepartments($custom);
                $dept_msg = "Campus \"$name\" added successfully.";
            }
        } else {
            $abbr = strtoupper(trim($_POST['dept_abbr'] ?? ''));
            $name = trim($_POST['dept_name'] ?? '');
            if (!in_array($type, ['colleges', 'offices'])) {
                $dept_err = 'Invalid department type.';
            } elseif (!$abbr || !$name) {
                $dept_err = 'Abbreviation and full name are required.';
            } elseif (isset(getMainCampusColleges()[$abbr]) || isset(getMainCampusOffices()[$abbr])) {
                $dept_err = "Abbreviation \"$abbr\" already exists.";
            } else {
                $custom[$type][$abbr] = $name;
                saveCustomDepartments($custom);
                $dept_msg = ucfirst(rtrim($type, 's')) . " \"$abbr\" added successfully.";
            }
        }

    } elseif ($action === 'delete') {
        $type = $_POST['dept_type'] ?? '';

        if ($type === 'campuses') {
            $del_id = (int)($_POST['campus_id'] ?? 0);
            $default_campus_ids = [1,2,3,4,5,6,7,8];
            if (in_array($del_id, $default_campus_ids)) {
                $dept_err = "Default campuses cannot be deleted.";
            } else {
                $custom['campuses'] = array_values(array_filter($custom['campuses'], fn($c) => $c['id'] !== $del_id));
                saveCustomDepartments($custom);
                $dept_msg = "Campus removed successfully.";
            }
        } else {
            $abbr = $_POST['dept_abbr'] ?? '';
            $defaults_colleges = ['CEA','COE','CCS','CBS','CAS','CIT','CHTM','CSSP'];
            $defaults_offices  = ['OUP','OVPAA','OVPAF','OVPRDE','OUR','OSAS','HRMO','ICTO','FBO','PMO','PPMO','ULib','GCC','PDO'];
            $is_default = ($type === 'colleges' && in_array($abbr, $defaults_colleges))
                       || ($type === 'offices'  && in_array($abbr, $defaults_offices));
            if ($is_default) {
                $dept_err = "Default entries cannot be deleted.";
            } elseif (isset($custom[$type][$abbr])) {
                unset($custom[$type][$abbr]);
                saveCustomDepartments($custom);
                $dept_msg = "\"$abbr\" removed successfully.";
            }
        }
    }
    // Redirect to avoid resubmit
    $qs = $dept_msg ? '?msg=' . urlencode($dept_msg) : ($dept_err ? '?err=' . urlencode($dept_err) : '');
    header('Location: inventory-campus.php' . $qs);
    exit;
}

if (isset($_GET['msg'])) $dept_msg = htmlspecialchars($_GET['msg']);
if (isset($_GET['err'])) $dept_err = htmlspecialchars($_GET['err']);

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
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06); padding:22px 24px;
    display:flex; flex-direction:column; gap:16px;
}
.ic-campus-header {}
.ic-campus-name { font-size:1rem; font-weight:800; color:#1a1d23; margin-bottom:2px; }
.ic-campus-loc  { font-size:0.78rem; color:#999; }

.ic-stat-row { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; }
.ic-stat-box {
    border-radius:6px; padding:12px 14px; text-align:center;
}
.ic-stat-val { font-size:1.6rem; font-weight:900; line-height:1; }
.ic-stat-lbl { font-size:0.72rem; font-weight:600; margin-top:3px; }

.ic-status-list { border-top:1px solid #e5e7eb; padding-top:12px; display:flex; flex-direction:column; gap:6px; }
.ic-status-row  { display:flex; align-items:center; justify-content:space-between; font-size:0.84rem; }
.ic-status-lbl  { color:#555; font-weight:600; }

.ic-badge { display:inline-flex; align-items:center; padding:2px 10px; border-radius:4px; font-size:0.74rem; font-weight:700; }
.ic-badge-warning   { background:rgba(245,158,11,0.12); color:#b45309; }
.ic-badge-danger    { background:rgba(239,68,68,0.12);  color:#dc2626; }
.ic-badge-info      { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.ic-badge-secondary { background:rgba(0,0,0,0.07);       color:#555; }

.ic-btn-view {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; color:#fff !important;
    padding:10px 18px !important; font-size:0.87rem !important;
    text-decoration:none; display:flex; align-items:center; justify-content:center; gap:7px;
    transition:opacity 0.15s !important;
}
.ic-btn-view:hover { color:#fff !important; opacity:0.88 !important; }

.ic-main-campus { grid-column: 1 / -1; }
.ic-college-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
@media(max-width:992px){ .ic-college-grid{ grid-template-columns:repeat(4,1fr); } }
@media(max-width:768px){ .ic-college-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:480px){ .ic-college-grid{ grid-template-columns:repeat(1,1fr); } }
.ic-college-card {
    background:#fff; border:1px solid #e5e7eb;
    border-radius:8px; padding:14px 16px; transition:border-color 0.15s;
}
.ic-college-card:hover { border-color:rgba(139,0,0,0.20); }
.ic-college-abbr { font-size:1.1rem; font-weight:900; color:#8B0000; line-height:1; }
.ic-college-full { font-size:0.70rem; color:#999; margin-top:3px; line-height:1.3; margin-bottom:10px; }
.ic-college-stats { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.ic-college-total { font-size:0.82rem; font-weight:800; color:#1a1d23; }
.ic-college-avail { font-size:0.73rem; font-weight:600; color:#15803d; background:rgba(34,197,94,0.12); border-radius:4px; padding:1px 8px; }
.ic-college-status-list { display:flex; flex-direction:column; gap:4px; }
.ic-college-status-row { display:flex; align-items:center; justify-content:space-between; font-size:0.75rem; color:#555; font-weight:600; }

/* Modal Styles */
.ic-modal {
    display:none; position:fixed; top:0; left:0; right:0; bottom:0;
    background:rgba(0,0,0,0.5);
    z-index:1000; align-items:center; justify-content:center;
}
.ic-modal.active { display:flex; }
.ic-modal-content {
    background:#fff;
    border-radius:8px; border:1px solid #e5e7eb;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    max-width:900px; width:90%; max-height:85vh; overflow-y:auto;
}
.ic-modal-header {
    padding:24px; border-bottom:1px solid #e5e7eb;
    display:flex; align-items:center; justify-content:space-between;
}
.ic-modal-title { font-size:1.3rem; font-weight:800; color:#1a1d23; }
.ic-modal-close {
    background:none; border:none; font-size:1.8rem; cursor:pointer;
    color:#555; transition:color 0.15s;
}
.ic-modal-close:hover { color:#111; }
.ic-modal-body { padding:24px; }
.ic-items-table {
    width:100%; border-collapse:collapse; font-size:0.87rem;
}
.ic-items-table th {
    text-align:left; padding:12px 0; border-bottom:2px solid #e5e7eb;
    font-weight:700; color:#555; text-transform:uppercase; font-size:0.75rem;
    letter-spacing:0.5px;
}
.ic-items-table td { padding:12px 0; border-bottom:1px solid #e5e7eb; }
.ic-items-table tr:hover td { background:#f7f7f7; }
.ic-modal-empty {
    text-align:center; padding:40px 24px; color:#999;
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
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 18px;
    transition: border-color 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.ic-item-card:hover {
    border-color: rgba(34, 197, 94, 0.4);
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
    border-top: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
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
    color: #999;
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
    color: #555;
    font-weight: 600;
}
.ic-item-card-condition-val {
    color: #1a1d23;
    font-weight: 700;
}
.ic-item-card-qr {
    background: #f7f7f7;
    border-radius: 6px;
    padding: 8px;
    text-align: center;
    font-size: 0.72rem;
    font-family: 'Courier New', monospace;
    color: #555;
    word-break: break-all;
}
</style>

<div class="container-fluid mt-4 pb-4">

    <!-- Flash messages -->
    <?php if ($dept_msg): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3" role="alert" style="border-radius:6px;font-size:.88rem;">
        <i class="fas fa-check-circle me-2"></i><?php echo $dept_msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($dept_err): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert" style="border-radius:6px;font-size:.88rem;">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $dept_err; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
        <div>
            <h5 style="font-weight:800;color:#111;margin:0;font-size:1.05rem;">Campus Inventory</h5>
            <div style="font-size:.78rem;color:#999;margin-top:2px;">Overview of all PSU campus assets</div>
        </div>
        <button onclick="document.getElementById('deptModal').classList.add('active')"
                style="background:#8B0000;color:#fff;border:none;border-radius:6px;padding:9px 18px;font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-plus"></i> Manage Departments
        </button>
    </div>

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

<!-- Manage Departments Modal -->
<div id="deptModal" class="ic-modal">
    <div class="ic-modal-content" style="max-width:680px;">
        <div class="ic-modal-header">
            <div class="ic-modal-title">Manage Departments</div>
            <button class="ic-modal-close" onclick="document.getElementById('deptModal').classList.remove('active')">&times;</button>
        </div>
        <div class="ic-modal-body">

            <!-- Tabs -->
            <div style="display:flex;gap:6px;margin-bottom:6px;background:rgba(0,0,0,0.04);border-radius:8px;padding:5px;">
                <button onclick="deptTab('colleges')" id="tab-colleges" class="dept-tab dept-tab-active" style="flex:1;padding:7px 0;border:none;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;background:#fff;color:#8B0000;box-shadow:0 1px 4px rgba(0,0,0,.10);">Colleges</button>
                <button onclick="deptTab('offices')"  id="tab-offices"  class="dept-tab"              style="flex:1;padding:7px 0;border:none;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;background:transparent;color:#555;">Offices</button>
                <button onclick="deptTab('campuses')" id="tab-campuses" class="dept-tab"              style="flex:1;padding:7px 0;border:none;border-radius:6px;font-size:.82rem;font-weight:700;cursor:pointer;background:transparent;color:#555;">Campuses</button>
            </div>
            <div id="main-campus-note" style="font-size:.76rem;color:#b45309;background:rgba(217,119,6,.08);border:1px solid rgba(217,119,6,.2);border-radius:5px;padding:6px 12px;margin-bottom:16px;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-info-circle"></i> Colleges and Offices belong to <strong>Main Campus</strong> only.
            </div>
            <div id="campuses-note" style="display:none;font-size:.76rem;color:#555;background:#f7f7f7;border:1px solid #e5e7eb;border-radius:5px;padding:6px 12px;margin-bottom:16px;">
                <i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i> Manage all PSU campuses here.
            </div>

            <!-- ── Colleges panel ── -->
            <div id="panel-colleges">
                <form method="POST" style="background:#f7f7f7;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:18px;">
                    <input type="hidden" name="dept_action" value="add">
                    <input type="hidden" name="dept_type"   value="colleges">
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999;margin-bottom:10px;">Add College</div>
                    <div style="display:grid;grid-template-columns:120px 1fr 110px;gap:10px;align-items:end;">
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Abbreviation</label>
                            <input type="text" name="dept_abbr" required placeholder="e.g. CON" oninput="this.value=this.value.toUpperCase()"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Full Name</label>
                            <input type="text" name="dept_name" required placeholder="e.g. College of Nursing (CON)"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                        <div>
                            <button type="submit" style="width:100%;padding:9px 0;background:#8B0000;color:#fff;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;">
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
                <?php
                $default_college_keys = ['CEA','COE','CCS','CBS','CAS','CIT','CHTM','CSSP'];
                foreach (getMainCampusColleges() as $abbr => $name):
                    $is_default = in_array($abbr, $default_college_keys);
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;background:#fff;">
                    <div>
                        <span style="font-weight:700;color:#8B0000;font-size:.88rem;"><?php echo htmlspecialchars($abbr); ?></span>
                        <span style="font-size:.82rem;color:#555;margin-left:8px;"><?php echo htmlspecialchars($name); ?></span>
                        <?php if ($is_default): ?><span style="font-size:.68rem;background:#f0f0f0;color:#999;border-radius:4px;padding:1px 6px;margin-left:6px;">default</span><?php endif; ?>
                    </div>
                    <?php if (!$is_default): ?>
                    <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($abbr); ?>?')" style="margin:0;">
                        <input type="hidden" name="dept_action" value="delete">
                        <input type="hidden" name="dept_type" value="colleges">
                        <input type="hidden" name="dept_abbr" value="<?php echo htmlspecialchars($abbr); ?>">
                        <button type="submit" style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);color:#dc2626;border-radius:5px;padding:4px 10px;font-size:.75rem;cursor:pointer;"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Offices panel ── -->
            <div id="panel-offices" style="display:none;">
                <form method="POST" style="background:#f7f7f7;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:18px;">
                    <input type="hidden" name="dept_action" value="add">
                    <input type="hidden" name="dept_type"   value="offices">
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999;margin-bottom:10px;">Add Office</div>
                    <div style="display:grid;grid-template-columns:120px 1fr 110px;gap:10px;align-items:end;">
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Abbreviation</label>
                            <input type="text" name="dept_abbr" required placeholder="e.g. OAR" oninput="this.value=this.value.toUpperCase()"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Full Name</label>
                            <input type="text" name="dept_name" required placeholder="e.g. Office of Alumni Relations (OAR)"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                        <div>
                            <button type="submit" style="width:100%;padding:9px 0;background:#8B0000;color:#fff;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;">
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
                <?php
                $default_office_keys = ['OUP','OVPAA','OVPAF','OVPRDE','OUR','OSAS','HRMO','ICTO','FBO','PMO','PPMO','ULib','GCC','PDO'];
                foreach (getMainCampusOffices() as $abbr => $name):
                    $is_default = in_array($abbr, $default_office_keys);
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;background:#fff;">
                    <div>
                        <span style="font-weight:700;color:#8B0000;font-size:.88rem;"><?php echo htmlspecialchars($abbr); ?></span>
                        <span style="font-size:.82rem;color:#555;margin-left:8px;"><?php echo htmlspecialchars($name); ?></span>
                        <?php if ($is_default): ?><span style="font-size:.68rem;background:#f0f0f0;color:#999;border-radius:4px;padding:1px 6px;margin-left:6px;">default</span><?php endif; ?>
                    </div>
                    <?php if (!$is_default): ?>
                    <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($abbr); ?>?')" style="margin:0;">
                        <input type="hidden" name="dept_action" value="delete">
                        <input type="hidden" name="dept_type" value="offices">
                        <input type="hidden" name="dept_abbr" value="<?php echo htmlspecialchars($abbr); ?>">
                        <button type="submit" style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);color:#dc2626;border-radius:5px;padding:4px 10px;font-size:.75rem;cursor:pointer;"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Campuses panel ── -->
            <div id="panel-campuses" style="display:none;">
                <form method="POST" style="background:#f7f7f7;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-bottom:18px;">
                    <input type="hidden" name="dept_action" value="add">
                    <input type="hidden" name="dept_type"   value="campuses">
                    <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#999;margin-bottom:10px;">Add Campus</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Campus Name</label>
                            <input type="text" name="campus_name" required placeholder="e.g. Magalang Campus"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                        <div>
                            <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Location</label>
                            <input type="text" name="campus_location" required placeholder="e.g. Magalang, Pampanga"
                                   style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                        </div>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:.78rem;font-weight:700;color:#333;display:block;margin-bottom:5px;">Description <span style="font-weight:400;color:#aaa;">(optional)</span></label>
                        <input type="text" name="campus_desc" placeholder="Brief description of the campus"
                               style="width:100%;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;color:#111;">
                    </div>
                    <button type="submit" style="padding:9px 22px;background:#8B0000;color:#fff;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;">
                        <i class="fas fa-plus me-1"></i> Add Campus
                    </button>
                </form>
                <?php
                $default_campus_ids = [1,2,3,4,5,6,7,8];
                foreach (getAllCampuses() as $campus):
                    $is_default = in_array($campus['id'], $default_campus_ids);
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;background:#fff;">
                    <div>
                        <div style="font-weight:700;color:#111;font-size:.88rem;"><?php echo htmlspecialchars($campus['name']); ?></div>
                        <div style="font-size:.76rem;color:#999;margin-top:1px;"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($campus['location']); ?></div>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <?php if ($is_default): ?>
                        <span style="font-size:.68rem;background:#f0f0f0;color:#999;border-radius:4px;padding:2px 8px;">default</span>
                        <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Delete this campus?')" style="margin:0;">
                            <input type="hidden" name="dept_action" value="delete">
                            <input type="hidden" name="dept_type"   value="campuses">
                            <input type="hidden" name="campus_id"   value="<?php echo $campus['id']; ?>">
                            <button type="submit" style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);color:#dc2626;border-radius:5px;padding:4px 10px;font-size:.75rem;cursor:pointer;"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

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
        html += '<span style="background: rgba(34,197,94,0.12); color: #15803d; padding: 4px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 700;">' + ownedItems.length + ' item' + (ownedItems.length !== 1 ? 's' : '') + '</span>';
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

// Dept modal tabs
function deptTab(tab) {
    ['colleges','offices','campuses'].forEach(t => {
        document.getElementById('panel-' + t).style.display = t === tab ? 'block' : 'none';
        var btn = document.getElementById('tab-' + t);
        if (t === tab) {
            btn.style.background = '#fff';
            btn.style.color = '#8B0000';
            btn.style.boxShadow = '0 1px 4px rgba(0,0,0,.10)';
        } else {
            btn.style.background = 'transparent';
            btn.style.color = '#555';
            btn.style.boxShadow = 'none';
        }
    });
    document.getElementById('main-campus-note').style.display = (tab === 'campuses') ? 'none' : 'flex';
    document.getElementById('campuses-note').style.display    = (tab === 'campuses') ? 'flex' : 'none';
}

// Close dept modal on outside click
document.getElementById('deptModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});

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
