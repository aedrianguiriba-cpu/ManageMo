<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    $u = getCurrentUser();
    header('Location: ' . ($u['role'] === ROLE_ADMIN ? 'admin/dashboard.php' : 'user/dashboard.php'));
    exit;
}

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = '';

// ── Look up the token in the DB ──────────────────────────────────────────────
$matched_user = null;
if ($token) {
    foreach (getUsers() as $u) {
        if (
            !empty($u['reset_token'])
            && hash_equals($u['reset_token'], $token)
            && !empty($u['reset_token_expires'])
            && strtotime($u['reset_token_expires']) > time()
        ) {
            $matched_user = $u;
            break;
        }
    }
}

$invalid_token = (!$token || !$matched_user);

// ── Handle form submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$invalid_token) {
    $password  = $_POST['password']         ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $error = 'Passwords do not match.';
    } else {
        dbUpdateUser((int)$matched_user['id'], [
            'password'            => hashPassword($password),
            'reset_token'         => null,
            'reset_token_expires' => null,
        ]);
        $success = 'Your password has been updated. You can now sign in with your new password.';
        $invalid_token = true; // hide the form after success
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ManageMo | Pampanga State University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            min-height:100vh; background:#FFFFFF;
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
        }
        .rp-wrapper {
            display:grid; grid-template-columns:1fr 1fr; min-height:100vh;
        }
        .rp-left {
            background:#FFFFFF; display:flex; align-items:center;
            justify-content:center; padding:60px 50px;
        }
        .rp-card { width:100%; max-width:400px; }
        .rp-logo {
            width:110px; height:65px; object-fit:contain;
            margin-bottom:30px; display:block; transition:transform 0.3s;
        }
        .rp-logo:hover { transform:scale(1.05); }
        .rp-header { margin-bottom:32px; }
        .rp-header h1 { color:#1a1a1a; font-size:36px; font-weight:800; margin-bottom:8px; letter-spacing:-0.5px; }
        .rp-header p  { color:#7F8C8D; font-size:15px; margin:0; line-height:1.5; }

        .form-group { margin-bottom:20px; }
        .form-group label {
            display:block; color:#2C3E50; font-size:13px; font-weight:700;
            margin-bottom:8px; letter-spacing:0.3px;
        }
        .input-wrap { position:relative; }
        .form-control {
            background:#FFFFFF; border:1px solid #e5e7eb; padding:13px 40px 13px 14px;
            border-radius:6px; font-size:14px; color:#2C3E50; width:100%;
            transition:border-color 0.2s;
        }
        .form-control::placeholder { color:#BDBDBD; }
        .form-control:focus { outline:none; border-color:#8B0000; box-shadow:none; }
        .toggle-pw {
            position:absolute; right:13px; top:50%; transform:translateY(-50%);
            background:none; border:none; color:#9ca3af; cursor:pointer; font-size:14px;
            padding:0; transition:color 0.2s;
        }
        .toggle-pw:hover { color:#8B0000; }

        .pw-strength { margin-top:6px; }
        .pw-strength-bar {
            height:4px; border-radius:2px; background:#e5e7eb;
            overflow:hidden; margin-bottom:4px;
        }
        .pw-strength-fill { height:100%; border-radius:2px; transition:width 0.3s,background 0.3s; width:0; }
        .pw-strength-label { font-size:11px; color:#9ca3af; }

        .btn-reset {
            width:100%; padding:13px 20px; background:#8B0000;
            border:none; color:white; font-weight:700; border-radius:6px;
            cursor:pointer; font-size:15px; transition:background 0.2s;
            margin-top:8px; letter-spacing:0.3px;
        }
        .btn-reset:hover { background:#6B0000; }
        .btn-reset:disabled { background:#c0c0c0; cursor:not-allowed; }

        .back-link { text-align:center; margin-top:22px; }
        .back-link a { color:#7F8C8D; text-decoration:none; font-size:14px; transition:color 0.2s; }
        .back-link a strong, .back-link a i { color:#8B0000; font-weight:700; }
        .back-link a:hover { color:#2C3E50; }

        .alert {
            padding:12px 14px; border-radius:6px; margin-bottom:20px;
            font-size:13px; display:flex; align-items:flex-start; gap:10px;
        }
        .alert-danger  { background:rgba(231,76,60,0.1); border:1px solid #E74C3C; color:#C0392B; }
        .alert-success { background:rgba(39,174,96,0.1); border:1px solid #27AE60;  color:#1E8449; }
        .alert-warning { background:rgba(245,158,11,0.1); border:1px solid #F59E0B; color:#92400e; }

        /* Right panel */
        .rp-right {
            background:#8B0000; display:flex; align-items:center;
            justify-content:center; padding:60px 40px; color:white; position:relative; overflow:hidden;
        }
        .rp-right-content { max-width:380px; z-index:1; }
        .rp-right-icon { font-size:60px; margin-bottom:22px; }
        .rp-right h2 { font-size:24px; font-weight:800; margin-bottom:14px; line-height:1.3; }
        .rp-right p  { font-size:14px; opacity:0.88; line-height:1.65; margin-bottom:24px; }
        .rp-tip {
            background:rgba(255,255,255,0.09); border-radius:8px;
            padding:20px 22px; border:1px solid rgba(255,255,255,0.13);
        }
        .rp-tip-item { display:flex; gap:12px; margin-bottom:14px; font-size:13px; }
        .rp-tip-item:last-child { margin-bottom:0; }
        .rp-tip-icon { font-size:16px; flex-shrink:0; margin-top:1px; }

        @media(max-width:992px){
            .rp-wrapper { grid-template-columns:1fr; }
            .rp-right   { display:none; }
            .rp-left    { min-height:100vh; }
        }
    </style>
</head>
<body>
<div class="rp-wrapper">
    <div class="rp-left">
        <div class="rp-card">
            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="rp-logo">

            <?php if ($success): ?>
            <!-- ── Success state ── -->
            <div class="rp-header">
                <h1>Password Updated!</h1>
                <p>Your new password has been saved. Sign in to continue.</p>
            </div>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
            <a href="index.php" class="btn-reset" style="display:block;text-align:center;text-decoration:none;margin-top:4px;">
                <i class="fas fa-sign-in-alt me-2"></i>Go to Sign In
            </a>

            <?php elseif ($invalid_token): ?>
            <!-- ── Invalid / expired token state ── -->
            <div class="rp-header">
                <h1>Link Expired</h1>
                <p>This password reset link is invalid or has expired.</p>
            </div>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle" style="margin-top:2px;flex-shrink:0;"></i>
                <span>Reset links are only valid for <strong>1 hour</strong>. Please request a new one.</span>
            </div>
            <a href="forgot-password.php" class="btn-reset" style="display:block;text-align:center;text-decoration:none;">
                <i class="fas fa-paper-plane me-2"></i>Request New Reset Link
            </a>
            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> <strong>Back to Sign In</strong></a>
            </div>

            <?php else: ?>
            <!-- ── Password form ── -->
            <div class="rp-header">
                <h1>Set New Password</h1>
                <p>Choose a strong new password for your account,<br>
                   <strong><?php echo htmlspecialchars($matched_user['full_name']); ?></strong>.</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle" style="margin-top:2px;flex-shrink:0;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="?token=<?php echo urlencode($token); ?>" id="rp-form">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <div class="input-wrap">
                        <input type="password" class="form-control" id="password" name="password"
                               required minlength="8" placeholder="At least 8 characters"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-pw" onclick="togglePw('password',this)" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="pw-strength">
                        <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-bar"></div></div>
                        <div class="pw-strength-label" id="pw-label">Enter a password</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm Password</label>
                    <div class="input-wrap">
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                               required minlength="8" placeholder="Repeat your new password">
                        <button type="button" class="toggle-pw" onclick="togglePw('password_confirm',this)" tabindex="-1">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-reset" id="rp-submit">
                    <i class="fas fa-lock me-2"></i>Save New Password
                </button>
            </form>
            <div class="back-link">
                <a href="index.php"><i class="fas fa-arrow-left"></i> <strong>Back to Sign In</strong></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="rp-right">
        <div class="rp-right-content">
            <div class="rp-right-icon"><i class="fas fa-shield-halved"></i></div>
            <h2>Create a Strong Password</h2>
            <p>A strong password helps keep your ManageMo account and institutional data safe.</p>
            <div class="rp-tip">
                <div class="rp-tip-item">
                    <div class="rp-tip-icon">✅</div>
                    <div>Use at least <strong>8 characters</strong> — longer is stronger</div>
                </div>
                <div class="rp-tip-item">
                    <div class="rp-tip-icon">✅</div>
                    <div>Mix <strong>uppercase, lowercase, numbers</strong> and symbols</div>
                </div>
                <div class="rp-tip-item">
                    <div class="rp-tip-icon">🚫</div>
                    <div>Avoid using your <strong>name, birthday</strong>, or common words</div>
                </div>
                <div class="rp-tip-item">
                    <div class="rp-tip-icon">🚫</div>
                    <div>Don't reuse passwords from other sites</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePw(fieldId, btn) {
    var f = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        f.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function checkStrength(val) {
    var bar   = document.getElementById('pw-bar');
    var label = document.getElementById('pw-label');
    if (!val) {
        bar.style.width = '0'; bar.style.background = '#e5e7eb';
        label.textContent = 'Enter a password'; label.style.color = '#9ca3af';
        return;
    }
    var score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    var levels = [
        { pct:'20%', color:'#ef4444', text:'Very weak' },
        { pct:'40%', color:'#f97316', text:'Weak' },
        { pct:'60%', color:'#eab308', text:'Fair' },
        { pct:'80%', color:'#84cc16', text:'Good' },
        { pct:'100%',color:'#22c55e', text:'Strong' },
    ];
    var lv = levels[Math.min(score, 4)];
    bar.style.width      = lv.pct;
    bar.style.background = lv.color;
    label.textContent    = lv.text;
    label.style.color    = lv.color;
}
</script>
</body>
</html>
