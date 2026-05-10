<?php
$page_title = 'Settings';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

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
<?php
$campus = getCampus($current_user['campus_id']);
$user_id = $current_user['id'];

$all_requests        = getRequests();
$user_requests       = filterByColumn($all_requests, 'user_id', $user_id);
$total_requests      = count($user_requests);
$all_borrows         = getBorrowRecords();
$user_active_borrows = filterByColumns($all_borrows, ['user_id' => $user_id, 'status' => 'active']);
$active_borrows      = count($user_active_borrows);
$user_ret_borrows    = filterByColumns($all_borrows, ['user_id' => $user_id, 'status' => 'returned']);
$completed_borrows   = count($user_ret_borrows);

displayMessage();
?>

<style>
/* ===== SETTINGS PAGE ===== */

/* Sidebar profile card */
.st-profile-card {
    background:linear-gradient(135deg,#8B0000,#b91c1c);
    border-radius:18px; padding:24px 16px 20px;
    text-align:center; margin-bottom:10px;
    box-shadow:0 6px 24px rgba(139,0,0,0.28);
    position:relative; overflow:hidden;
}
.st-profile-card::before {
    content:''; position:absolute; top:-30px; right:-30px;
    width:100px; height:100px; border-radius:50%;
    background:rgba(255,255,255,0.08);
}
.st-profile-card::after {
    content:''; position:absolute; bottom:-20px; left:-20px;
    width:80px; height:80px; border-radius:50%;
    background:rgba(255,255,255,0.06);
}
.st-avatar {
    width:72px; height:72px; border-radius:50%;
    background:rgba(255,255,255,0.22);
    border:3px solid rgba(255,255,255,0.40);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1.8rem; font-weight:800;
    margin:0 auto 12px; position:relative; z-index:1;
}
.st-profile-name {
    font-size:0.97rem; font-weight:800; color:#fff;
    margin-bottom:3px; position:relative; z-index:1;
}
.st-profile-email {
    font-size:0.76rem; color:rgba(255,255,255,0.65);
    margin-bottom:10px; position:relative; z-index:1;
    word-break:break-all;
}
.st-profile-role-pill {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(255,255,255,0.18); border:1px solid rgba(255,255,255,0.30);
    border-radius:20px; padding:3px 12px;
    font-size:0.76rem; font-weight:700; color:#fff;
    position:relative; z-index:1;
}

/* Sidebar nav */
.st-nav {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07);
    overflow:hidden; padding:8px;
}
.st-nav-link {
    display:flex; align-items:center; gap:10px;
    padding:11px 14px; border-radius:12px;
    font-size:0.88rem; font-weight:600; color:rgba(0,0,0,0.52);
    text-decoration:none !important;
    transition:background 0.15s, color 0.15s;
    border:none;
    width:100%; background:transparent; cursor:pointer; text-align:left;
}
.st-nav-link:hover { background:rgba(0,0,0,0.04); color:#1a1d23; }
.st-nav-link.active {
    background:rgba(139,0,0,0.07); color:#8B0000 !important;
}
.st-nav-link i { width:18px; text-align:center; }
.st-nav-link .st-nav-arrow {
    margin-left:auto; font-size:0.7rem; opacity:0.4; transition:opacity 0.15s;
}
.st-nav-link.active .st-nav-arrow,
.st-nav-link:hover .st-nav-arrow { opacity:0.8; }

/* Card */
.st-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:1px solid rgba(0,0,0,0.07); border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.07);
    padding:26px 28px;
}
.st-card-title {
    font-size:1.05rem; font-weight:800; color:#1a1d23;
    margin-bottom:4px;
}
.st-card-sub {
    font-size:0.81rem; color:rgba(0,0,0,0.42);
    margin-bottom:20px;
}
.st-section-title {
    font-size:0.71rem; font-weight:700; text-transform:uppercase;
    letter-spacing:0.6px; color:rgba(0,0,0,0.36);
    margin-bottom:14px; padding-bottom:8px;
    border-bottom:1px solid rgba(0,0,0,0.07);
}
.st-divider { border-color:rgba(0,0,0,0.07); margin:22px 0; }

/* Input with icon */
.st-input-wrap { position:relative; }
.st-input-wrap .st-input-icon {
    position:absolute; left:13px; top:50%; transform:translateY(-50%);
    color:rgba(0,0,0,0.32); font-size:0.85rem; pointer-events:none;
}
.st-input-wrap .form-control { padding-left:36px; }
.st-input-wrap .st-eye-btn {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; color:rgba(0,0,0,0.35);
    cursor:pointer; padding:2px 4px; font-size:0.85rem;
    transition:color 0.15s;
}
.st-input-wrap .st-eye-btn:hover { color:#1a1d23; }

/* Password strength */
.st-strength-bar {
    height:4px; border-radius:2px; background:rgba(0,0,0,0.08);
    margin-top:6px; overflow:hidden;
}
.st-strength-fill {
    height:100%; border-radius:2px; width:0%;
    transition:width 0.3s, background 0.3s;
}
.st-strength-text { font-size:0.75rem; color:rgba(0,0,0,0.40); margin-top:4px; }

/* Info row */
.st-info-row {
    display:flex; align-items:center; gap:14px;
    padding:13px 14px; border-radius:13px;
    background:rgba(0,0,0,0.025); margin-bottom:9px;
    transition:background 0.15s;
}
.st-info-row:hover { background:rgba(0,0,0,0.04); }
.st-info-icon {
    width:36px; height:36px; flex-shrink:0; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:0.85rem;
}
.st-info-label { font-size:0.73rem; color:rgba(0,0,0,0.38); font-weight:600; margin-bottom:2px; text-transform:uppercase; letter-spacing:0.4px; }
.st-info-value { font-size:0.9rem; font-weight:700; color:#1a1d23; }

/* Activity stat cards */
.st-stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
@media(max-width:576px){ .st-stat-grid { grid-template-columns:1fr; } }
.st-stat-card {
    background:rgba(0,0,0,0.025); border-radius:14px;
    padding:16px 12px; text-align:center;
    border:1px solid rgba(0,0,0,0.05);
    transition:background 0.15s;
}
.st-stat-card:hover { background:rgba(0,0,0,0.04); }
.st-stat-num {
    font-size:1.7rem; font-weight:900; color:#8B0000;
    line-height:1; margin-bottom:5px;
}
.st-stat-label {
    font-size:0.74rem; font-weight:600; color:rgba(0,0,0,0.42);
    text-transform:uppercase; letter-spacing:0.4px;
}

/* Buttons */
.st-save-btn {
    background:linear-gradient(135deg,#8B0000,#b91c1c) !important;
    border:none !important; border-radius:12px !important;
    font-weight:700 !important; color:#fff !important;
    padding:11px 26px !important;
    box-shadow:0 4px 14px rgba(139,0,0,0.25) !important;
    transition:transform 0.15s, box-shadow 0.15s !important;
}
.st-save-btn:hover {
    transform:translateY(-1px) !important;
    box-shadow:0 7px 20px rgba(139,0,0,0.32) !important;
}

/* Badge */
.st-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:4px 12px; border-radius:20px;
    font-size:0.79rem; font-weight:700;
}
.st-badge-active   { background:rgba(34,197,94,0.12);  color:#15803d; }
.st-badge-inactive { background:rgba(239,68,68,0.12);  color:#dc2626; }
.st-badge-role     { background:rgba(139,0,0,0.10);    color:#8B0000; }
</style>

<div class="container-fluid mt-4 pb-4">

    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-md-3">
            <!-- Profile summary card -->
            <div class="st-profile-card mb-3">
                <div class="st-avatar"><?php echo strtoupper(substr($current_user['full_name'], 0, 1)); ?></div>
                <div class="st-profile-name"><?php echo htmlspecialchars($current_user['full_name']); ?></div>
                <div class="st-profile-email"><?php echo htmlspecialchars($current_user['email']); ?></div>
                <span class="st-profile-role-pill">
                    <i class="fas fa-user-tag"></i> <?php echo ucfirst($current_user['role']); ?>
                </span>
                <?php if (!empty($current_user['college_id'])): ?>
                <span class="st-profile-role-pill" style="margin-top:6px;background:rgba(59,130,246,0.13);color:#1d4ed8;border-color:rgba(59,130,246,0.18);">
                    <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($current_user['college_id']); ?>
                </span>
                <?php endif; ?>
            </div>
            <!-- Nav -->
            <div class="st-nav">
                <button class="st-nav-link active" onclick="switchTab(this,'profile-tab')">
                    <i class="fas fa-user"></i> Profile
                    <i class="fas fa-chevron-right st-nav-arrow"></i>
                </button>
                <button class="st-nav-link" onclick="switchTab(this,'password-tab')">
                    <i class="fas fa-lock"></i> Change Password
                    <i class="fas fa-chevron-right st-nav-arrow"></i>
                </button>
                <button class="st-nav-link" onclick="switchTab(this,'info-tab')">
                    <i class="fas fa-info-circle"></i> Account Info
                    <i class="fas fa-chevron-right st-nav-arrow"></i>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="col-md-9">
            <div class="tab-content">

                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile-tab">
                    <div class="st-card">
                        <div class="st-card-title">Edit Profile</div>
                        <div class="st-card-sub">Update your personal information</div>
                        <hr class="st-divider mt-0">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <div class="st-input-wrap">
                                        <i class="fas fa-user st-input-icon"></i>
                                        <input type="text" class="form-control" name="full_name"
                                               value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <div class="st-input-wrap">
                                        <i class="fas fa-phone st-input-icon"></i>
                                        <input type="tel" class="form-control" name="phone"
                                               value="<?php echo htmlspecialchars($current_user['phone']); ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Email Address</label>
                                <div class="st-input-wrap">
                                    <i class="fas fa-envelope st-input-icon"></i>
                                    <input type="email" class="form-control" name="email"
                                           value="<?php echo htmlspecialchars($current_user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Department</label>
                                <div class="st-input-wrap">
                                    <i class="fas fa-graduation-cap st-input-icon"></i>
                                    <select class="form-control" name="college_id" style="padding-left:34px;">
                                        <option value="">-- Select Department / Office --</option>
                                        <optgroup label="Colleges">
                                        <?php foreach (getMainCampusColleges() as $abbr => $name): ?>
                                        <option value="<?php echo htmlspecialchars($abbr); ?>"
                                            <?php echo (($current_user['college_id'] ?? '') === $abbr) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Offices">
                                        <?php foreach (getMainCampusOffices() as $abbr => $name): ?>
                                        <option value="<?php echo htmlspecialchars($abbr); ?>"
                                            <?php echo (($current_user['college_id'] ?? '') === $abbr) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                        <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn st-save-btn">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Tab -->
                <div class="tab-pane fade" id="password-tab">
                    <div class="st-card">
                        <div class="st-card-title">Change Password</div>
                        <div class="st-card-sub">Choose a strong password to protect your account</div>
                        <hr class="st-divider mt-0">
                        <?php if (isset($password_error)): ?>
                            <div class="alert alert-danger mb-4" style="border-radius:12px;">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $password_error; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <div class="st-input-wrap">
                                    <i class="fas fa-lock st-input-icon"></i>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button type="button" class="st-eye-btn" onclick="togglePw('current_password',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <div class="st-input-wrap">
                                    <i class="fas fa-key st-input-icon"></i>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required oninput="checkStrength(this.value)">
                                    <button type="button" class="st-eye-btn" onclick="togglePw('new_password',this)"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="st-strength-bar"><div class="st-strength-fill" id="strengthFill"></div></div>
                                <div class="st-strength-text" id="strengthText">Enter a new password</div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm New Password</label>
                                <div class="st-input-wrap">
                                    <i class="fas fa-check-circle st-input-icon"></i>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="st-eye-btn" onclick="togglePw('confirm_password',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn st-save-btn">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Info Tab -->
                <div class="tab-pane fade" id="info-tab">
                    <div class="st-card">
                        <div class="st-card-title">Account Information</div>
                        <div class="st-card-sub">Your account details and activity overview</div>
                        <hr class="st-divider mt-0">

                        <div class="st-section-title"><i class="fas fa-id-card me-1"></i>Account Details</div>

                        <div class="st-info-row">
                            <div class="st-info-icon" style="background:rgba(139,0,0,0.10);color:#8B0000;">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <div>
                                <div class="st-info-label">Role</div>
                                <span class="st-badge st-badge-role"><?php echo ucfirst($current_user['role']); ?></span>
                            </div>
                        </div>

                        <div class="st-info-row">
                            <div class="st-info-icon" style="background:rgba(59,130,246,0.10);color:#1d4ed8;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <div class="st-info-label">Campus</div>
                                <div class="st-info-value"><?php echo htmlspecialchars($campus['name']); ?></div>
                                <div style="font-size:0.79rem;color:rgba(0,0,0,0.40);"><?php echo htmlspecialchars($campus['location']); ?></div>
                            </div>
                        </div>

                        <div class="st-info-row">
                            <div class="st-info-icon" style="background:rgba(34,197,94,0.10);color:#15803d;">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div>
                                <div class="st-info-label">Account Status</div>
                                <?php echo $current_user['is_active']
                                    ? '<span class="st-badge st-badge-active"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Active</span>'
                                    : '<span class="st-badge st-badge-inactive"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Inactive</span>'; ?>
                            </div>
                        </div>

                        <div class="st-info-row">
                            <div class="st-info-icon" style="background:rgba(245,158,11,0.10);color:#b45309;">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div>
                                <div class="st-info-label">Member Since</div>
                                <div class="st-info-value"><?php echo formatDate($current_user['created_at'], 'F d, Y'); ?></div>
                            </div>
                        </div>

                        <hr class="st-divider">
                        <div class="st-section-title"><i class="fas fa-chart-bar me-1"></i>Your Activity</div>

                        <div class="st-stat-grid">
                            <div class="st-stat-card">
                                <div class="st-stat-num"><?php echo $total_requests; ?></div>
                                <div class="st-stat-label">Requests<br>Submitted</div>
                            </div>
                            <div class="st-stat-card">
                                <div class="st-stat-num"><?php echo $active_borrows; ?></div>
                                <div class="st-stat-label">Active<br>Borrows</div>
                            </div>
                            <div class="st-stat-card">
                                <div class="st-stat-num"><?php echo $completed_borrows; ?></div>
                                <div class="st-stat-label">Completed<br>Borrows</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
</div>

<script>
function switchTab(btn, tabId) {
    document.querySelectorAll('.st-nav-link').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.tab-pane').forEach(p => { p.classList.remove('show','active'); });
    var pane = document.getElementById(tabId);
    if (pane) { pane.classList.add('show','active'); }
}

function togglePw(inputId, btn) {
    var inp = document.getElementById(inputId);
    var icon = btn.querySelector('i');
    if (inp.type === 'password') {
        inp.type = 'text'; icon.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password'; icon.className = 'fas fa-eye';
    }
}
function checkStrength(val) {
    var fill = document.getElementById('strengthFill');
    var text = document.getElementById('strengthText');
    if (!val) { fill.style.width='0%'; text.textContent='Enter a new password'; text.style.color=''; return; }
    var score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    var levels = [
        {w:'15%', c:'#ef4444', t:'Very weak'},
        {w:'30%', c:'#f97316', t:'Weak'},
        {w:'55%', c:'#f59e0b', t:'Fair'},
        {w:'78%', c:'#22c55e', t:'Good'},
        {w:'100%',c:'#15803d', t:'Strong'},
    ];
    var l = levels[Math.min(score, 4)];
    fill.style.width = l.w; fill.style.background = l.c;
    text.textContent = l.t; text.style.color = l.c;
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
