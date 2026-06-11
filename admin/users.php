<?php
$page_title = 'Manage Users';
require_once dirname(__DIR__) . '/config/functions.php';

requireAdmin();

$current_user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    startSession();
    $post_action = sanitizeInput($_POST['action'] ?? '');

    if ($post_action === 'add_user') {
        $full_name  = sanitizeInput($_POST['full_name'] ?? '');
        $email      = sanitizeInput($_POST['email'] ?? '');
        $phone      = sanitizeInput($_POST['phone'] ?? '');
        $role       = sanitizeInput($_POST['role'] ?? 'user');
        $campus_id  = (int)($_POST['campus_id'] ?? 1);
        $college_id = sanitizeInput($_POST['college_id'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';

        $errors = [];
        if (!$full_name) $errors[] = 'Full name is required.';
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        // Check duplicate email in mock data
        foreach (getUsers() as $u) {
            if (strtolower($u['email']) === strtolower($email)) {
                $errors[] = 'Email already exists.';
                break;
            }
        }
        // Also check session-added users
        foreach ($_SESSION['added_users'] ?? [] as $u) {
            if (strtolower($u['email']) === strtolower($email)) {
                $errors[] = 'Email already exists.';
                break;
            }
        }

        if (empty($errors)) {
            $all_existing = array_merge(getUsers(), $_SESSION['added_users'] ?? []);
            $new_id = max(array_column($all_existing, 'id')) + 1;
            $_SESSION['added_users'][] = [
                'id'         => $new_id,
                'email'      => $email,
                'password'   => hashPassword($password),
                'full_name'  => $full_name,
                'role'       => in_array($role, ['admin','user']) ? $role : 'user',
                'campus_id'  => $campus_id,
                'college_id' => $college_id ?: null,
                'phone'      => $phone,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            logActivity($current_user['id'], 'CREATE', "Added new user: $email", 'users', $new_id);
            redirectWithMessage('users.php', 'User added successfully!', 'success');
        } else {
            $_SESSION['user_form_errors'] = $errors;
            $_SESSION['user_form_data']   = $_POST;
            redirectWithMessage('users.php?action=add', implode(' ', $errors), 'danger');
        }

    } elseif ($post_action === 'toggle_status') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!isset($_SESSION['user_status_overrides'])) $_SESSION['user_status_overrides'] = [];
        $all_users = array_merge(getUsers(), $_SESSION['added_users'] ?? []);
        $target = null;
        foreach ($all_users as $u) { if ($u['id'] === $uid) { $target = $u; break; } }
        if ($target) {
            $current_active = $_SESSION['user_status_overrides'][$uid] ?? $target['is_active'];
            $_SESSION['user_status_overrides'][$uid] = $current_active ? 0 : 1;
            $label = $current_active ? 'deactivated' : 'activated';
            logActivity($current_user['id'], 'UPDATE', "User #$uid $label", 'users', $uid);
            redirectWithMessage('users.php', 'User ' . $label . '.', 'success');
        }
    }
}

// Merge mock users + session-added users
startSession();
$all_users = array_merge(getUsers(), $_SESSION['added_users'] ?? []);
// Apply status overrides
foreach ($all_users as &$u) {
    if (isset($_SESSION['user_status_overrides'][$u['id']])) {
        $u['is_active'] = $_SESSION['user_status_overrides'][$u['id']];
    }
}
unset($u);

$campuses    = getCampuses();
$colleges    = getMainCampusColleges();
$offices     = getMainCampusOffices();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php displayMessage(); ?>

<style>
/* ===== USER MANAGEMENT ===== */
:root { --um-red:#8B0000; --um-red2:#b91c1c; }

/* Stats row */
.um-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
@media(max-width:576px){ .um-stats { grid-template-columns:1fr; } }
.um-stat-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:18px 20px; display:flex; align-items:center; gap:14px;
}
.um-stat-icon {
    width:36px; height:36px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; font-size:1.1rem;
}
.um-stat-val { font-size:1.55rem; font-weight:900; line-height:1; color:#1a1d23; }
.um-stat-lbl { font-size:0.74rem; font-weight:700; color:rgba(0,0,0,0.40); text-transform:uppercase; letter-spacing:0.4px; margin-top:2px; }

/* Header bar */
.um-header {
    display:flex; align-items:center; justify-content:space-between;
    flex-wrap:wrap; gap:12px; margin-bottom:20px;
}
.um-header-title { font-size:1.05rem; font-weight:800; color:#1a1d23; }
.um-header-sub   { font-size:0.78rem; color:rgba(0,0,0,0.40); margin-top:1px; }

/* User card grid */
.um-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }

.um-user-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:20px; display:flex; flex-direction:column; gap:14px;
}
.um-user-card:hover { border-color:#d1d5db; }

.um-card-top { display:flex; align-items:flex-start; gap:13px; }
.um-avatar {
    width:46px; height:46px; border-radius:8px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-weight:900; font-size:1.05rem; color:#fff;
}
.um-card-name { font-size:0.93rem; font-weight:800; color:#1a1d23; line-height:1.2; }
.um-card-email { font-size:0.76rem; color:rgba(0,0,0,0.45); margin-top:2px; word-break:break-all; }
.um-card-phone { font-size:0.75rem; color:rgba(0,0,0,0.38); margin-top:1px; }

.um-card-meta {
    display:flex; flex-wrap:wrap; gap:6px;
    padding:10px 12px; background:rgba(0,0,0,0.025);
    border-radius:10px;
}
.um-card-meta-item {
    display:flex; align-items:center; gap:5px;
    font-size:0.75rem; color:rgba(0,0,0,0.50);
}
.um-card-meta-item i { width:13px; text-align:center; color:rgba(0,0,0,0.30); }

.um-card-footer {
    display:flex; align-items:center; justify-content:space-between;
    padding-top:12px; border-top:1px solid rgba(0,0,0,0.06);
}
.um-card-joined { font-size:0.73rem; color:rgba(0,0,0,0.35); }

/* Badges */
.um-badge {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:4px;
    font-size:0.72rem; font-weight:700;
}
.um-badge-admin   { background:rgba(139,0,0,0.10); color:#8B0000; }
.um-badge-user    { background:rgba(59,130,246,0.12); color:#1d4ed8; }
.um-badge-active  { background:rgba(34,197,94,0.12);  color:#15803d; }
.um-badge-inactive{ background:rgba(0,0,0,0.07);       color:rgba(0,0,0,0.45); }

/* Buttons */
.um-btn {
    display:inline-flex; align-items:center; gap:5px;
    padding:6px 14px; border-radius:6px; border:none;
    font-size:0.78rem; font-weight:700; cursor:pointer;
    text-decoration:none; transition:background 0.15s;
}
.um-btn-primary {
    background:#8B0000;
    color:#fff !important;
}
.um-btn-primary:hover { color:#fff !important; background:#6b0000; }
.um-btn-secondary {
    background:rgba(0,0,0,0.06); color:rgba(0,0,0,0.55) !important;
    border:1px solid rgba(0,0,0,0.09);
}
.um-btn-secondary:hover { background:rgba(0,0,0,0.10); color:rgba(0,0,0,0.70) !important; }
.um-btn-sm { padding:4px 11px; font-size:0.74rem; }
.um-btn-toggle-on  { background:rgba(239,68,68,0.09); color:#dc2626 !important; border:1px solid rgba(239,68,68,0.18); }
.um-btn-toggle-on:hover  { background:rgba(239,68,68,0.16); }
.um-btn-toggle-off { background:rgba(34,197,94,0.09);  color:#15803d !important; border:1px solid rgba(34,197,94,0.18); }
.um-btn-toggle-off:hover { background:rgba(34,197,94,0.16); }

/* Form */
.um-form-card {
    background:#fff;
    border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:26px 28px; margin-bottom:16px;
}
.um-form-section {
    font-size:0.69rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.5px; color:rgba(0,0,0,0.35);
    margin-bottom:16px; padding-bottom:9px;
    border-bottom:1px solid rgba(0,0,0,0.07);
    display:flex; align-items:center; gap:7px;
}
.um-form-section i { color:var(--um-red); font-size:0.80rem; }
.um-form-label {
    font-size:0.79rem; font-weight:700; color:#374151; margin-bottom:5px; display:block;
}
.um-form-req { color:#dc2626; margin-left:2px; }
.um-tip-card {
    background:#f7f7f7;
    border:1px solid #e5e7eb; border-radius:8px;
    padding:18px 20px;
}
.um-tip-item { display:flex; gap:10px; margin-bottom:12px; font-size:0.80rem; color:#374151; }
.um-tip-item:last-child { margin-bottom:0; }
.um-tip-dot { width:22px; height:22px; border-radius:4px; background:rgba(139,0,0,0.08); color:var(--um-red); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:0.72rem; margin-top:1px; }
</style>

<div class="container-fluid mt-4 pb-4">

<?php if ($action === 'add'): ?>
    <!-- Add User Form -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="users.php" class="um-btn um-btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <div>
            <div style="font-size:1.05rem;font-weight:800;color:#1a1d23;">Add New User</div>
            <div style="font-size:0.78rem;color:rgba(0,0,0,0.42);">Create a new system account</div>
        </div>
    </div>
    <div class="row g-4">
    <div class="col-lg-8">
    <div class="um-form-card">
        <div class="um-form-section"><i class="fas fa-id-card"></i> Personal Information</div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="add_user">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="um-form-label">Full Name <span class="um-form-req">*</span></label>
                    <input type="text" class="form-control" name="full_name" required
                        value="<?php echo htmlspecialchars($_SESSION['user_form_data']['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="um-form-label">Email Address <span class="um-form-req">*</span></label>
                    <input type="email" class="form-control" name="email" required
                        value="<?php echo htmlspecialchars($_SESSION['user_form_data']['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="um-form-label">Phone Number</label>
                    <input type="text" class="form-control" name="phone" placeholder="e.g. 09171234567"
                        value="<?php echo htmlspecialchars($_SESSION['user_form_data']['phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="um-form-label">Role <span class="um-form-req">*</span></label>
                    <select class="form-select" name="role" id="roleSelect" onchange="toggleCollegeField()">
                        <option value="user"  <?php echo (($_SESSION['user_form_data']['role'] ?? 'user') === 'user')  ? 'selected' : ''; ?>>Faculty / Staff</option>
                        <option value="admin" <?php echo (($_SESSION['user_form_data']['role'] ?? '') === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                    </select>
                </div>
            </div>

            <div class="um-form-section mt-4"><i class="fas fa-building"></i> Campus & Department</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="um-form-label">Campus <span class="um-form-req">*</span></label>
                    <select class="form-select" name="campus_id" id="campusSelect" onchange="toggleCollegeField()">
                        <?php foreach ($campuses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"
                            <?php echo ((int)($_SESSION['user_form_data']['campus_id'] ?? 1) === $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="collegeFieldWrap">
                    <label class="um-form-label">Department / Office</label>
                    <select class="form-select" name="college_id">
                        <option value="">— None —</option>
                        <optgroup label="Colleges">
                            <?php foreach ($colleges as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>"
                                <?php echo (($_SESSION['user_form_data']['college_id'] ?? '') === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Offices">
                            <?php foreach ($offices as $code => $name): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>"
                                <?php echo (($_SESSION['user_form_data']['college_id'] ?? '') === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="um-form-section mt-4"><i class="fas fa-lock"></i> Account Security</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="um-form-label">Password <span class="um-form-req">*</span></label>
                    <input type="password" class="form-control" name="password" required minlength="6" placeholder="Min. 6 characters">
                </div>
                <div class="col-md-6">
                    <label class="um-form-label">Confirm Password <span class="um-form-req">*</span></label>
                    <input type="password" class="form-control" name="confirm_password" required minlength="6">
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="um-btn um-btn-primary" style="padding:10px 24px;font-size:0.87rem;">
                    <i class="fas fa-user-plus"></i> Create User
                </button>
                <a href="users.php" class="um-btn um-btn-secondary" style="padding:10px 20px;font-size:0.87rem;">Cancel</a>
            </div>
        </form>
    </div>
    </div>

    <!-- Tips sidebar -->
    <div class="col-lg-4">
        <div class="um-tip-card">
            <div style="font-size:0.78rem;font-weight:800;color:#8B0000;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px;">
                <i class="fas fa-lightbulb me-1"></i> Guidelines
            </div>
            <div class="um-tip-item">
                <div class="um-tip-dot"><i class="fas fa-envelope"></i></div>
                <div>Use the user's official <strong>university email</strong> address for their account.</div>
            </div>
            <div class="um-tip-item">
                <div class="um-tip-dot"><i class="fas fa-lock"></i></div>
                <div>Passwords must be <strong>at least 6 characters</strong>. Share credentials securely.</div>
            </div>
            <div class="um-tip-item">
                <div class="um-tip-dot"><i class="fas fa-user-shield"></i></div>
                <div><strong>Admin</strong> accounts have full system access. Assign with care.</div>
            </div>
            <div class="um-tip-item">
                <div class="um-tip-dot"><i class="fas fa-building"></i></div>
                <div>Department only applies to <strong>Main Campus</strong> users.</div>
            </div>
        </div>
    </div>
    </div>
    <?php unset($_SESSION['user_form_errors'], $_SESSION['user_form_data']); ?>

<?php else: ?>
    <?php
    $count_total  = count($all_users);
    $count_active = count(array_filter($all_users, fn($u) => $u['is_active']));
    $count_admin  = count(array_filter($all_users, fn($u) => $u['role'] === 'admin'));
    $avatar_colors = ['#8B0000','#1d4ed8','#15803d','#b45309','#7c3aed','#0e7490'];
    $all_depts = array_merge($colleges, $offices);
    ?>

    <!-- Stats -->
    <div class="um-stats">
        <div class="um-stat-card">
            <div class="um-stat-icon" style="background:rgba(139,0,0,0.10);color:#8B0000;"><i class="fas fa-users"></i></div>
            <div><div class="um-stat-val"><?php echo $count_total; ?></div><div class="um-stat-lbl">Total Users</div></div>
        </div>
        <div class="um-stat-card">
            <div class="um-stat-icon" style="background:rgba(34,197,94,0.12);color:#15803d;"><i class="fas fa-user-check"></i></div>
            <div><div class="um-stat-val"><?php echo $count_active; ?></div><div class="um-stat-lbl">Active</div></div>
        </div>
        <div class="um-stat-card">
            <div class="um-stat-icon" style="background:rgba(245,158,11,0.12);color:#b45309;"><i class="fas fa-user-shield"></i></div>
            <div><div class="um-stat-val"><?php echo $count_admin; ?></div><div class="um-stat-lbl">Administrators</div></div>
        </div>
    </div>

    <!-- Header -->
    <div class="um-header">
        <div>
            <div class="um-header-title">All Accounts</div>
            <div class="um-header-sub"><?php echo $count_total; ?> registered user<?php echo $count_total !== 1 ? 's' : ''; ?> in the system</div>
        </div>
        <a href="users.php?action=add" class="um-btn um-btn-primary">
            <i class="fas fa-user-plus"></i> Add New User
        </a>
    </div>

    <!-- Card Grid -->
    <div class="um-grid">
    <?php foreach ($all_users as $u):
        $col      = $avatar_colors[($u['id'] - 1) % count($avatar_colors)];
        $initials = strtoupper(substr($u['full_name'], 0, 1));
        $campus_name = '';
        foreach ($campuses as $c) { if ($c['id'] == $u['campus_id']) { $campus_name = $c['name']; break; } }
        $dept_name = (!empty($u['college_id']) && isset($all_depts[$u['college_id']])) ? $u['college_id'] : '';
        $is_me = ($u['id'] === $current_user['id']);
    ?>
    <div class="um-user-card">
        <!-- Top: avatar + name -->
        <div class="um-card-top">
            <div class="um-avatar" style="background:<?php echo $col; ?>;"><?php echo $initials; ?></div>
            <div style="min-width:0;">
                <div class="um-card-name"><?php echo htmlspecialchars($u['full_name']); ?><?php if ($is_me): ?> <span style="font-size:0.68rem;font-weight:600;color:rgba(0,0,0,0.35);">(You)</span><?php endif; ?></div>
                <div class="um-card-email"><?php echo htmlspecialchars($u['email']); ?></div>
                <?php if (!empty($u['phone'])): ?>
                <div class="um-card-phone"><i class="fas fa-phone" style="font-size:0.65rem;margin-right:3px;"></i><?php echo htmlspecialchars($u['phone']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Badges row -->
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <span class="um-badge um-badge-<?php echo $u['role']; ?>">
                <i class="fas <?php echo $u['role'] === 'admin' ? 'fa-user-shield' : 'fa-user'; ?>"></i>
                <?php echo $u['role'] === 'admin' ? 'Administrator' : 'Faculty / Staff'; ?>
            </span>
            <span class="um-badge <?php echo $u['is_active'] ? 'um-badge-active' : 'um-badge-inactive'; ?>">
                <i class="fas fa-circle" style="font-size:0.45rem;"></i>
                <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
            </span>
        </div>

        <!-- Meta info -->
        <div class="um-card-meta">
            <div class="um-card-meta-item" style="width:100%;">
                <i class="fas fa-building"></i>
                <span><?php echo htmlspecialchars($campus_name ?: '—'); ?></span>
            </div>
            <?php if ($dept_name): ?>
            <div class="um-card-meta-item" style="width:100%;">
                <i class="fas fa-sitemap"></i>
                <span><?php echo htmlspecialchars($dept_name); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer: joined + action -->
        <div class="um-card-footer">
            <div class="um-card-joined"><i class="fas fa-calendar-alt" style="margin-right:4px;opacity:0.5;"></i>Joined <?php echo date('M d, Y', strtotime($u['created_at'])); ?></div>
            <?php if (!$is_me): ?>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                <button type="submit" class="um-btn um-btn-sm <?php echo $u['is_active'] ? 'um-btn-toggle-on' : 'um-btn-toggle-off'; ?>">
                    <i class="fas <?php echo $u['is_active'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                    <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>
</div>

<script>
function toggleCollegeField() {
    var campus = document.getElementById('campusSelect').value;
    var wrap   = document.getElementById('collegeFieldWrap');
    wrap.style.display = (campus === '1') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleCollegeField);
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
