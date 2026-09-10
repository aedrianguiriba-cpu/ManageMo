<?php
$page_title = 'Department Inventory';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();

// ── Department CRUD ──
$dept_msg = '';
$dept_err = '';

// Plural UI type -> singular DB type. Colleges, offices, and campuses are all
// rows in the same `departments` table now, distinguished by `type`.
$dept_type_map = ['colleges' => 'college', 'offices' => 'office', 'campuses' => 'campus'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dept_action'])) {
    $action = $_POST['dept_action'];
    $type   = $_POST['dept_type'] ?? '';
    $dbtype = $dept_type_map[$type] ?? '';

    if ($action === 'add') {
        if ($dbtype === '') {
            $dept_err = 'Invalid department type.';

        } elseif ($dbtype === 'campus') {
            $name     = trim($_POST['campus_name'] ?? '');
            $location = trim($_POST['campus_location'] ?? '');
            $desc     = trim($_POST['campus_desc'] ?? '');
            if (!$name || !$location) {
                $dept_err = 'Campus name and location are required.';
            } else {
                $ok = dbAddCustomDepartment('campus', ['name' => $name, 'location' => $location, 'description' => $desc]);
                $dept_msg = $ok ? "Campus \"$name\" added successfully." : '';
                if (!$ok) $dept_err = 'Failed to add campus. Please try again.';
            }

        } else {
            $abbr = strtoupper(trim($_POST['dept_abbr'] ?? ''));
            $name = trim($_POST['dept_name'] ?? '');
            if (!$abbr || !$name) {
                $dept_err = 'Abbreviation and full name are required.';
            } elseif (isset(getMainCampusColleges()[$abbr]) || isset(getMainCampusOffices()[$abbr])) {
                $dept_err = "Abbreviation \"$abbr\" already exists.";
            } else {
                $ok = dbAddCustomDepartment($dbtype, ['abbreviation' => $abbr, 'full_name' => $name]);
                $dept_msg = $ok ? ucfirst($dbtype) . " \"$abbr\" added successfully." : '';
                if (!$ok) $dept_err = 'Failed to add entry. Please try again.';
            }
        }

    } elseif ($action === 'delete') {
        if ($dbtype === 'campus') {
            $abbr = $_POST['dept_abbr'] ?? '';
            $ok = dbDeleteCustomDepartment('campus', $abbr);
            $dept_msg = $ok ? 'Campus removed successfully.' : '';
            if (!$ok) $dept_err = 'This campus cannot be deleted.';
        } else {
            $abbr = $_POST['dept_abbr'] ?? '';
            $ok = dbDeleteCustomDepartment($dbtype, $abbr);
            if ($ok) {
                $dept_msg = "\"$abbr\" removed successfully.";
            } else {
                $dept_err = "Default entries cannot be deleted.";
            }
        }
    }
    // Redirect to avoid resubmit — keep the modal open after the change.
    $qs = $dept_msg ? '?msg=' . urlencode($dept_msg) : ($dept_err ? '?err=' . urlencode($dept_err) : '?');
    $qs .= ($qs === '?' ? '' : '&') . 'openDeptModal=1';
    header('Location: inventory-campus.php' . $qs);
    exit;
}

if (isset($_GET['msg'])) $dept_msg = htmlspecialchars($_GET['msg']);
if (isset($_GET['err'])) $dept_err = htmlspecialchars($_GET['err']);

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">

<style>
/* ===== DEPARTMENT INVENTORY ===== */
.ic-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:18px; }
@media(max-width:768px){ .ic-grid{ grid-template-columns:1fr; } }

.ic-dept-grid { grid-template-columns:repeat(3,1fr); }
@media(max-width:992px){ .ic-dept-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:768px){ .ic-dept-grid{ grid-template-columns:1fr; } }

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
            <h5 style="font-weight:800;color:#111;margin:0;font-size:1.05rem;">Department Inventory</h5>
            <div style="font-size:.78rem;color:#999;margin-top:2px;">Overview of assets by college and office, and the university's campuses</div>
        </div>
        <button onclick="document.getElementById('deptModal').classList.add('active')"
                style="background:#8B0000;color:#fff;border:none;border-radius:6px;padding:9px 18px;font-size:.84rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-plus"></i> Manage Departments
        </button>
    </div>

    <!-- Overview tabs: Colleges, Offices, Campuses — three independent lists,
         each sourced straight from the departments table. -->
    <div style="display:flex;gap:6px;margin-bottom:18px;background:rgba(0,0,0,0.04);border-radius:8px;padding:5px;max-width:420px;">
        <button onclick="ovTab('colleges')" id="ov-tab-colleges" class="ov-tab ov-tab-active" style="flex:1;padding:8px 0;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;background:#fff;color:#8B0000;box-shadow:0 1px 4px rgba(0,0,0,.10);">Colleges</button>
        <button onclick="ovTab('offices')" id="ov-tab-offices" class="ov-tab" style="flex:1;padding:8px 0;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;background:transparent;color:#555;">Offices</button>
        <button onclick="ovTab('campuses')" id="ov-tab-campuses" class="ov-tab" style="flex:1;padding:8px 0;border:none;border-radius:6px;font-size:.84rem;font-weight:700;cursor:pointer;background:transparent;color:#555;">Campuses</button>
    </div>

    <!-- ── Colleges ── -->
    <div id="ov-panel-colleges" class="ic-grid ic-dept-grid">
        <?php foreach (getMainCampusColleges() as $abbr => $fullname):
            $dept_items  = array_values(array_filter(getInventory(), fn($i) => ($i['college_id'] ?? '') === $abbr));
            $dept_status = countByStatus($dept_items);
        ?>
        <div class="ic-campus-card">
            <div class="ic-campus-header">
                <div class="ic-campus-name"><?php echo htmlspecialchars($abbr); ?></div>
                <div class="ic-campus-loc"><?php echo htmlspecialchars($fullname); ?></div>
            </div>
            <div class="ic-status-list">
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Owned</span>
                    <span class="ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $dept_status['available'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Borrowed</span>
                    <span class="ic-badge ic-badge-warning"><?php echo $dept_status['borrowed'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Maintenance</span>
                    <span class="ic-badge ic-badge-info"><?php echo $dept_status['maintenance'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Requested</span>
                    <span class="ic-badge ic-badge-secondary"><?php echo $dept_status['requested'] ?? 0; ?></span>
                </div>
            </div>
            <button onclick="openDeptInventoryModal('<?php echo htmlspecialchars($abbr); ?>', '<?php echo htmlspecialchars($abbr . ' — ' . $fullname); ?>')" class="ic-btn-view">
                <i class="fas fa-eye"></i> View Inventory
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Offices ── -->
    <div id="ov-panel-offices" class="ic-grid ic-dept-grid" style="display:none;">
        <?php foreach (getMainCampusOffices() as $abbr => $fullname):
            $dept_items  = array_values(array_filter(getInventory(), fn($i) => ($i['college_id'] ?? '') === $abbr));
            $dept_status = countByStatus($dept_items);
        ?>
        <div class="ic-campus-card">
            <div class="ic-campus-header">
                <div class="ic-campus-name"><?php echo htmlspecialchars($abbr); ?></div>
                <div class="ic-campus-loc"><?php echo htmlspecialchars($fullname); ?></div>
            </div>
            <div class="ic-status-list">
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Owned</span>
                    <span class="ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $dept_status['available'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Borrowed</span>
                    <span class="ic-badge ic-badge-warning"><?php echo $dept_status['borrowed'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Maintenance</span>
                    <span class="ic-badge ic-badge-info"><?php echo $dept_status['maintenance'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Requested</span>
                    <span class="ic-badge ic-badge-secondary"><?php echo $dept_status['requested'] ?? 0; ?></span>
                </div>
            </div>
            <button onclick="openDeptInventoryModal('<?php echo htmlspecialchars($abbr); ?>', '<?php echo htmlspecialchars($abbr . ' — ' . $fullname); ?>')" class="ic-btn-view">
                <i class="fas fa-eye"></i> View Inventory
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Campuses ── -->
    <div id="ov-panel-campuses" class="ic-grid ic-dept-grid" style="display:none;">
        <?php foreach (getDepartmentCampuses() as $c):
            // Inventory no longer relies on campus_id — a campus "owns" an item the
            // same way a college/office does, via its abbreviation in college_id.
            $camp_items  = array_values(array_filter(getInventory(), fn($i) => ($i['college_id'] ?? '') === $c['abbreviation']));
            $camp_status = countByStatus($camp_items);
        ?>
        <div class="ic-campus-card">
            <div class="ic-campus-header">
                <div class="ic-campus-name"><?php echo htmlspecialchars($c['name']); ?></div>
                <div class="ic-campus-loc"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($c['location']); ?></div>
            </div>
            <?php if ($c['description']): ?>
            <div style="font-size:.82rem;color:#555;line-height:1.5;"><?php echo htmlspecialchars($c['description']); ?></div>
            <?php endif; ?>
            <div class="ic-status-list">
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Owned</span>
                    <span class="ic-badge" style="background:rgba(34,197,94,0.12); color:#15803d;"><?php echo $camp_status['available'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Borrowed</span>
                    <span class="ic-badge ic-badge-warning"><?php echo $camp_status['borrowed'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Maintenance</span>
                    <span class="ic-badge ic-badge-info"><?php echo $camp_status['maintenance'] ?? 0; ?></span>
                </div>
                <div class="ic-status-row">
                    <span class="ic-status-lbl">Requested</span>
                    <span class="ic-badge ic-badge-secondary"><?php echo $camp_status['requested'] ?? 0; ?></span>
                </div>
            </div>
            <button onclick="openInventoryModal('<?php echo htmlspecialchars($c['abbreviation']); ?>', '', '<?php echo htmlspecialchars($c['name']); ?>')" class="ic-btn-view">
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
            <div style="font-size:.76rem;color:#555;background:#f7f7f7;border:1px solid #e5e7eb;border-radius:5px;padding:6px 12px;margin-bottom:16px;">
                <i class="fas fa-info-circle me-1" style="color:rgba(139,0,0,0.5);"></i> Colleges, offices, and campuses are managed as three independent lists in the same departments table.
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
                <?php foreach (getDepartmentCampuses() as $c): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:6px;background:#fff;">
                    <div>
                        <div style="font-weight:700;color:#111;font-size:.88rem;"><?php echo htmlspecialchars($c['name']); ?>
                            <?php if ($c['is_default']): ?><span style="font-size:.68rem;background:#f0f0f0;color:#999;border-radius:4px;padding:1px 6px;margin-left:6px;">default</span><?php endif; ?>
                        </div>
                        <div style="font-size:.76rem;color:#999;margin-top:1px;"><i class="fas fa-map-marker-alt me-1" style="color:rgba(139,0,0,0.5);"></i><?php echo htmlspecialchars($c['location']); ?></div>
                    </div>
                    <?php if (!$c['is_default']): ?>
                    <form method="POST" onsubmit="return confirm('Delete <?php echo htmlspecialchars($c['name']); ?>?')" style="margin:0;">
                        <input type="hidden" name="dept_action" value="delete">
                        <input type="hidden" name="dept_type"   value="campuses">
                        <input type="hidden" name="dept_abbr"   value="<?php echo htmlspecialchars($c['abbreviation']); ?>">
                        <button type="submit" style="background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.2);color:#dc2626;border-radius:5px;padding:4px 10px;font-size:.75rem;cursor:pointer;"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
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
var icAllInventory = <?php echo json_encode(getInventory()); ?>;
var icModalItems = [];      // this campus/college's raw item rows
var icModalSortBy = 'name'; // 'name' | 'quantity' | 'status'

// Overview tabs: Colleges, Offices — two independent lists.
function ovTab(tab) {
    ['colleges', 'offices', 'campuses'].forEach(function(t) {
        document.getElementById('ov-panel-' + t).style.display = t === tab ? 'grid' : 'none';
        var btn = document.getElementById('ov-tab-' + t);
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
}

// Collapse individual unit rows into one entry per item_name (one row = one
// physical unit in this model), so 5 "Network Server" rows show as a single
// "Network Server — 5 units" group instead of 5 separate jumbled entries.
function icGroupItems(items) {
    var groups = {};
    items.forEach(function(item) {
        var key = item.item_name.toLowerCase();
        if (!groups[key]) {
            groups[key] = { item_name: item.item_name, category: item.category, units: [] };
        }
        groups[key].units.push(item);
    });
    return Object.values(groups);
}

// Colleges/offices are global — this filters inventory by department abbreviation.
function openDeptInventoryModal(deptCode, deptName) {
    const modal = document.getElementById('inventoryModal');
    const modalTitle = document.getElementById('modalTitle');

    modalTitle.textContent = deptName + ' - Inventory Items';
    modal.classList.add('active');

    icModalItems = icAllInventory.filter(item => item.college_id === deptCode);
    icModalSortBy = 'name';
    renderInventoryModal('');
}

// Campuses tab — campus_id is no longer relied on; a campus owns an item the
// same way a college/office does, via its abbreviation in college_id.
function openInventoryModal(campusAbbr, filterCode, campusName) {
    const modal = document.getElementById('inventoryModal');
    const modalTitle = document.getElementById('modalTitle');

    modalTitle.textContent = campusName + ' - Inventory Items';
    modal.classList.add('active');

    icModalItems = icAllInventory.filter(item => item.college_id === campusAbbr);
    icModalSortBy = 'name';
    renderInventoryModal('');
}

function renderInventoryModal(searchTerm) {
    const modalContent = document.getElementById('modalContent');
    const term = (searchTerm || '').toLowerCase().trim();

    let items = icModalItems;
    if (term) {
        items = items.filter(function(item) {
            return item.item_name.toLowerCase().indexOf(term) !== -1
                || (item.category || '').toLowerCase().indexOf(term) !== -1
                || (item.qr_code_id || '').toLowerCase().indexOf(term) !== -1;
        });
    }

    let groups = icGroupItems(items);
    groups.sort(function(a, b) {
        if (icModalSortBy === 'quantity') return b.units.length - a.units.length;
        if (icModalSortBy === 'status') {
            var as = a.units[0] ? a.units[0].status : '', bs = b.units[0] ? b.units[0].status : '';
            return as.localeCompare(bs);
        }
        return a.item_name.localeCompare(b.item_name);
    });

    // Search + sort toolbar, always shown (re-render keeps the box focused/typed value).
    let html = '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">';
    html += '<input type="text" id="icModalSearch" placeholder="Search items…" value="' + htmlEscape(searchTerm || '') + '" oninput="renderInventoryModal(this.value)" ' +
            'style="flex:1;min-width:180px;padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;outline:none;">';
    html += '<select onchange="icModalSortBy=this.value; renderInventoryModal(document.getElementById(\'icModalSearch\').value);" style="padding:9px 12px;border:1px solid #e5e7eb;border-radius:6px;font-size:.85rem;">';
    html += '<option value="name"' + (icModalSortBy === 'name' ? ' selected' : '') + '>Sort: Name</option>';
    html += '<option value="quantity"' + (icModalSortBy === 'quantity' ? ' selected' : '') + '>Sort: Quantity</option>';
    html += '<option value="status"' + (icModalSortBy === 'status' ? ' selected' : '') + '>Sort: Status</option>';
    html += '</select>';
    html += '</div>';

    if (groups.length === 0) {
        html += '<div class="ic-modal-empty"><i class="fas fa-inbox"></i><p>No inventory items match.</p></div>';
        modalContent.innerHTML = html;
        return;
    }

    html += '<table class="ic-items-table"><thead><tr>';
    html += '<th>Item Name</th><th>Category</th><th>Units</th><th>Status</th><th>Condition</th>';
    html += '</tr></thead><tbody>';

    groups.forEach(function(group) {
        var statuses = [...new Set(group.units.map(function(u) { return u.status; }))];
        var statusLabel = statuses.length === 1 ? statuses[0] : 'mixed';
        var statusClass = statuses.length === 1 ? getStatusBadgeClass(statuses[0]) : 'ic-badge-secondary';
        var conditions = [...new Set(group.units.map(function(u) { return u.condition || 'N/A'; }))];
        var condLabel = conditions.length === 1 ? conditions[0] : 'Mixed';
        html += '<tr>';
        html += '<td><strong>' + htmlEscape(group.item_name) + '</strong></td>';
        html += '<td>' + htmlEscape(group.category || '') + '</td>';
        html += '<td style="text-align:center; font-weight:600;">' + group.units.length + '</td>';
        html += '<td><span class="ic-badge ' + statusClass + '">' + statusLabel.charAt(0).toUpperCase() + statusLabel.slice(1) + '</span></td>';
        html += '<td>' + htmlEscape(condLabel) + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table>';
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
}

// Close dept modal on outside click
document.getElementById('deptModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});

// Reopen the dept modal after an add / delete redirect
<?php if (isset($_GET['openDeptModal'])): ?>
document.getElementById('deptModal').classList.add('active');
<?php endif; ?>

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
