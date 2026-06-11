<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === ROLE_ADMIN) {
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'user/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email and password are required';
    } else {
        $users = getUsers();
        $user  = null;
        foreach ($users as $u) {
            if ($u['email'] === $email && $u['is_active'] == 1) { $user = $u; break; }
        }
        if ($user && verifyPassword($password, $user['password'])) {
            startSession();
            $_SESSION['user_id'] = $user['id'];
            header('Location: ' . BASE_URL . ($user['role'] === ROLE_ADMIN ? 'admin/dashboard.php' : 'user/dashboard.php'));
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}

$forgot_error   = '';
$forgot_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_submit'])) {
    $forgot_email = sanitizeInput($_POST['forgot_email'] ?? '');
    if (!$forgot_email) {
        $forgot_error = 'Email address is required';
    } elseif (!filter_var($forgot_email, FILTER_VALIDATE_EMAIL)) {
        $forgot_error = 'Invalid email address';
    } else {
        $users = getUsers();
        $found = false;
        foreach ($users as $u) { if ($u['email'] === $forgot_email && $u['is_active'] == 1) { $found = true; break; } }
        $forgot_success = 'If an account exists with this email, you will receive reset instructions shortly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManageMo | Sign In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: #1a1a1a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            inset: -20px;
            background: url('<?php echo BASE_URL; ?>assets/pics/storagebg.jpg') center/cover no-repeat;
            filter: blur(12px);
            transform: scale(1.05);
            z-index: 0;
        }

        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.45) 100%);
            z-index: 0;
        }

        /* ── Outer card ── */
        .login-card {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            max-width: 1600px;
            min-height: 680px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.45);
        }

        /* ── Left panel ── */
        .login-left {
            flex: 1;
            position: relative;
            background: url('<?php echo BASE_URL; ?>assets/pics/storagebg.jpg') center/cover no-repeat;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 32px;
            min-width: 0;
        }

        .login-left::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(160deg, rgba(107,0,0,0.72) 0%, rgba(139,0,0,0.60) 45%, rgba(61,0,0,0.80) 100%);
        }

        .login-left-top,
        .login-left-bottom {
            position: relative;
            z-index: 1;
        }

        .login-left-tag {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.65);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-left-tag::after {
            content: '';
            display: block;
            width: 40px;
            height: 1px;
            background: rgba(255,255,255,0.45);
        }

        .login-left-heading {
            font-size: 2.6rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .login-left-sub {
            font-size: 0.84rem;
            color: rgba(255,255,255,0.65);
            line-height: 1.6;
            max-width: 320px;
        }

        /* ── Right panel ── */
        .login-right {
            width: 640px;
            flex-shrink: 0;
            background: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 56px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 36px;
        }

        .login-logo img {
            height: 32px;
            width: auto;
            object-fit: contain;
        }

        .login-logo-name {
            font-size: 1.05rem;
            font-weight: 800;
            color: #111;
            letter-spacing: -0.2px;
        }

        .login-heading {
            font-size: 1.85rem;
            font-weight: 800;
            color: #111;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .login-sub {
            font-size: 0.84rem;
            color: #888;
            margin-bottom: 28px;
        }

        /* Form */
        .login-label {
            font-size: 0.80rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 6px;
            display: block;
        }

        .login-input {
            width: 100%;
            background: #f5f6f7;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 11px 14px;
            font-size: 0.88rem;
            color: #111;
            transition: border-color 0.15s, background 0.15s;
            outline: none;
        }

        .login-input::placeholder { color: #bbb; }

        .login-input:focus {
            background: #fff;
            border-color: #8B0000;
        }

        .login-input-wrap {
            position: relative;
        }

        .login-eye {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #bbb;
            cursor: pointer;
            font-size: 0.82rem;
            padding: 0;
            line-height: 1;
        }

        .login-eye:hover { color: #888; }

        .login-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 12px 0 22px;
            font-size: 0.80rem;
        }

        .login-remember {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #555;
            cursor: pointer;
        }

        .login-remember input[type=checkbox] {
            width: 14px; height: 14px;
            accent-color: #8B0000;
            cursor: pointer;
        }

        .login-forgot {
            color: #555;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-size: 0.80rem;
        }

        .login-forgot:hover { color: #8B0000; text-decoration: underline; }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: #8B0000;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s;
            letter-spacing: 0.2px;
        }

        .login-btn:hover { background: #6B0000; }

        .login-footer {
            margin-top: 22px;
            text-align: center;
            font-size: 0.80rem;
            color: #888;
        }

        .login-footer a, .login-footer button {
            color: #111;
            font-weight: 700;
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            font-size: 0.80rem;
        }

        .login-footer a:hover, .login-footer button:hover { color: #8B0000; }

        .login-alert {
            padding: 10px 13px;
            border-radius: 6px;
            font-size: 0.82rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .login-alert-danger  { background: rgba(220,38,38,0.07); border: 1px solid rgba(220,38,38,0.25); color: #b91c1c; }
        .login-alert-success { background: rgba(22,163,74,0.07);  border: 1px solid rgba(22,163,74,0.25);  color: #15803d; }

        .login-alert-close {
            background: none; border: none; cursor: pointer;
            color: inherit; opacity: 0.6; font-size: 1rem; padding: 0; line-height: 1;
        }
        .login-alert-close:hover { opacity: 1; }

        .login-field { margin-bottom: 16px; }

        /* Forgot form */
        .login-back {
            display: inline-flex; align-items: center; gap: 6px;
            background: none; border: none; cursor: pointer;
            color: #8B0000; font-size: 0.80rem; font-weight: 600;
            padding: 0; margin-top: 14px;
        }
        .login-back:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            .login-left { display: none; }
            .login-right { width: 100%; padding: 36px 28px; }
            .login-card  { max-width: 420px; min-height: auto; border-radius: 12px; }
        }
    </style>
</head>
<body>
    <div class="login-card">

        <!-- Left: image panel -->
        <div class="login-left">
            <div class="login-left-top">
                <span class="login-left-tag">Asset Management</span>
            </div>
            <div class="login-left-bottom">
                <div class="login-left-heading">Pampanga State<br>University</div>
                <p class="login-left-sub">ManageMo tracks and manages all university assets across 8 PSU campuses with real-time updates and QR code generation.</p>
            </div>
        </div>

        <!-- Right: form panel -->
        <div class="login-right">

            <div class="login-logo">
                <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo">
                <span class="login-logo-name">ManageMo</span>
            </div>

            <!-- Login Form -->
            <div id="loginPanel">
                <div class="login-heading">Welcome Back</div>
                <p class="login-sub">Enter your email and password to access your account</p>

                <?php if ($error && isset($_POST['login_submit'])): ?>
                <div class="login-alert login-alert-danger">
                    <span><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error); ?></span>
                    <button class="login-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="login-field">
                        <label class="login-label">Email</label>
                        <input type="email" name="email" class="login-input" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    <div class="login-field">
                        <label class="login-label">Password</label>
                        <div class="login-input-wrap">
                            <input type="password" name="password" id="loginPassword" class="login-input" placeholder="Enter your password" required style="padding-right:36px;">
                            <button type="button" class="login-eye" onclick="togglePw('loginPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="login-row">
                        <label class="login-remember">
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <button type="button" class="login-forgot" onclick="showForgot()">Forgot Password</button>
                    </div>
                    <button type="submit" name="login_submit" class="login-btn">Sign In</button>
                </form>
            </div>

            <!-- Forgot Password Form -->
            <div id="forgotPanel" style="display:none;">
                <div class="login-heading">Reset Password</div>
                <p class="login-sub">Enter your email to receive reset instructions</p>

                <?php if ($forgot_success): ?>
                <div class="login-alert login-alert-success">
                    <span><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($forgot_success); ?></span>
                    <button class="login-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <?php if ($forgot_error): ?>
                <div class="login-alert login-alert-danger">
                    <span><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($forgot_error); ?></span>
                    <button class="login-alert-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="login-field">
                        <label class="login-label">Email Address</label>
                        <input type="email" name="forgot_email" class="login-input" placeholder="Enter your email" required
                               value="<?php echo isset($_POST['forgot_email']) ? htmlspecialchars($_POST['forgot_email']) : ''; ?>">
                    </div>
                    <p style="font-size:0.78rem;color:#999;margin-bottom:20px;">
                        We'll send you a link to reset your password.
                    </p>
                    <button type="submit" name="forgot_submit" class="login-btn">Send Reset Link</button>
                </form>

                <button class="login-back" onclick="showLogin()">
                    <i class="fas fa-arrow-left"></i> Back to Sign In
                </button>
            </div>

        </div><!-- /login-right -->
    </div><!-- /login-card -->

    <script>
        function togglePw(id, btn) {
            var input = document.getElementById(id);
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.innerHTML = showing ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        }

        function showForgot() {
            document.getElementById('loginPanel').style.display  = 'none';
            document.getElementById('forgotPanel').style.display = 'block';
        }

        function showLogin() {
            document.getElementById('forgotPanel').style.display = 'none';
            document.getElementById('loginPanel').style.display  = 'block';
        }

        <?php if ($forgot_success || $forgot_error): ?>
        document.addEventListener('DOMContentLoaded', showForgot);
        <?php endif; ?>
    </script>
</body>
</html>
