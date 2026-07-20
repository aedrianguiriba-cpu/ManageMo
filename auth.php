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

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        $error = 'Email and password are required';
    } else {
        $users = getUsers();
        $user = null;

        // ── TEMP DEBUG ──────────────────────────────────────────────────────────
        if (isset($_POST['debug_login'])) {
            $matched = null;
            foreach ($users as $u) { if ($u['email'] === $email) { $matched = $u; break; } }
            echo '<pre style="background:#111;color:#0f0;padding:20px;font-size:13px;">';
            echo 'SUPABASE_URL = ' . SUPABASE_URL . "\n";
            echo 'SUPABASE_KEY = ' . substr(SUPABASE_KEY, 0, 20) . "...\n";
            echo 'getUsers() count = ' . count($users) . "\n";
            echo 'email match = ' . ($matched ? 'YES' : 'NO') . "\n";
            if ($matched) {
                echo 'is_active = ' . var_export($matched['is_active'], true) . "\n";
                echo 'password hash = ' . $matched['password'] . "\n";
                echo 'password_verify = ' . var_export(password_verify($password, $matched['password']), true) . "\n";
            }
            echo '</pre>'; exit;
        }
        // ── END DEBUG ───────────────────────────────────────────────────────────

        foreach ($users as $u) {
            if ($u['email'] === $email && $u['is_active'] == 1) {
                $user = $u;
                break;
            }
        }

        if ($user && verifyPassword($password, $user['password'])) {
            startSession();
            $_SESSION['user_id'] = $user['id'];
            
            if ($user['role'] === ROLE_ADMIN) {
                header('Location: ' . BASE_URL . 'admin/dashboard.php');
            } else {
                header('Location: ' . BASE_URL . 'user/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email_signup'] ?? '');
    $password = $_POST['password_signup'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $campus_id = sanitizeInput($_POST['campus_id'] ?? '');
    
    if (!$full_name || !$email || !$password || !$confirm_password || !$campus_id) {
        $error = 'All fields are required';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $users = getUsers();
        foreach ($users as $u) {
            if ($u['email'] === $email) {
                $error = 'Email is already registered';
                break;
            }
        }
        
        if (!$error) {
            $new_user = dbCreateUser([
                'email'     => $email,
                'password'  => hashPassword($password),
                'full_name' => $full_name,
                'role'      => 'user',
                'campus_id' => (int)$campus_id,
                'is_active' => 1,
            ]);
            $success = $new_user ? 'Account created successfully! Please log in with your credentials.' : 'Registration failed. Please try again.';
        }
    }
}

$campuses = getAllCampuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManageMo | Pampanga State University</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            min-height: 100vh;
            background: #FFFFFF;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            overflow: hidden;
        }
        .auth-container {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
        }
        
        /* Left section (forms) */
        .auth-left {
            position: absolute;
            left: 0;
            top: 0;
            width: 50%;
            height: 100vh;
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
            z-index: 2;
            transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .auth-left.signup-active {
            transform: translateX(100%);
        }
        
        /* Right section (promo) */
        .auth-right {
            position: absolute;
            right: 0;
            top: 0;
            width: 50%;
            height: 100vh;
            background: #8B0000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            color: white;
            overflow: hidden;
            transition: all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 1;
        }
        
        .auth-right.signup-active {
            z-index: 3;
            transform: translateX(-100%);
        }
        
        .auth-right::before {
            content: none;
        }
        
        /* Forms container */
        .auth-forms-wrapper {
            position: relative;
            width: 100%;
            max-width: 400px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .auth-form {
            position: absolute;
            width: 100%;
            opacity: 1;
            visibility: visible;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        
        .auth-form.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        /* Card styles */
        .auth-card {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }
        
        .auth-header {
            margin-bottom: 35px;
            text-align: left;
        }
        
        .auth-logo {
            width: 110px;
            height: 65px;
            object-fit: contain;
            margin-bottom: 30px;
            display: block;
            filter: none;
            transition: transform 0.3s;
        }
        
        .auth-logo:hover {
            transform: scale(1.05);
        }
        
        .auth-header h1 {
            color: #1a1a1a;
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .auth-header p {
            color: #7F8C8D;
            font-size: 15px;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 18px;
            clear: both;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: #2C3E50;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        
        .form-control,
        .form-select {
            background: #FFFFFF;
            border: 1px solid #e5e7eb;
            padding: 12px 14px;
            border-radius: 6px;
            font-size: 14px;
            color: #2C3E50;
            width: 100%;
            transition: border-color 0.2s;
        }
        
        .form-control::placeholder {
            color: #BDBDBD;
        }
        
        .form-control:hover,
        .form-select:hover {
            border-color: #CCCCCC;
        }
        
        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: #8B0000;
            box-shadow: none;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .password-strength {
            height: 4px;
            background: #E0E0E0;
            border-radius: 3px;
            margin-top: 6px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            border-radius: 3px;
            transition: all 0.3s;
        }
        
        .password-help {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .password-help a {
            color: #8B0000;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .password-help a:hover {
            color: #6B0000;
            text-decoration: underline;
        }
        
        .btn-auth {
            width: 100%;
            padding: 13px 20px;
            background: #8B0000;
            border: none;
            color: white;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.2s;
            box-shadow: none;
            letter-spacing: 0.3px;
            margin-top: 10px;
        }

        .btn-auth:hover {
            background: #6B0000;
        }
        
        .auth-toggle {
            text-align: center;
            margin-top: 20px;
        }
        
        .auth-toggle a {
            color: #7F8C8D;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
            cursor: pointer;
        }
        
        .auth-toggle a strong {
            color: #8B0000;
            font-weight: 700;
            transition: color 0.3s;
        }
        
        .auth-toggle a:hover,
        .auth-toggle a:hover strong {
            color: #6B0000;
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.1);
            border: 1px solid #E74C3C;
            color: #C0392B;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27AE60;
            color: #1E8449;
        }
        
        .alert i {
            margin-right: 8px;
        }
        
        .btn-close {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.7;
            padding: 0;
            width: 20px;
            height: 20px;
        }
        
        .btn-close:hover {
            opacity: 1;
        }
        
        /* Promo content */
        .promo-content {
            max-width: 400px;
            z-index: 1;
            text-align: center;
        }
        
        .promo-heading {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .promo-text {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        
        .features-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 40px;
        }
        
        .feature-item {
            display: flex;
            gap: 16px;
            padding: 18px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .feature-icon {
            font-size: 24px;
            min-width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .feature-icon i {
            color: white;
        }
        
        .feature-text h4 {
            margin: 0 0 6px 0;
            font-size: 15px;
            font-weight: 700;
            color: white;
            text-align: left;
        }
        
        .feature-text p {
            margin: 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.85);
            text-align: left;
        }
        
        @media (max-width: 992px) {
            .auth-left,
            .auth-right {
                width: 100%;
                position: relative;
            }
            
            .auth-left.signup-active {
                transform: none;
            }
            
            .auth-right {
                display: none;
            }
            
            .auth-right.signup-active {
                transform: none;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Forms Section -->
        <div class="auth-left" id="authLeft">
            <div class="auth-forms-wrapper">
                
                <!-- Login Form -->
                <div class="auth-form" id="loginForm">
                    <div class="auth-card">
                        <div class="auth-header">
                            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="auth-logo">
                            <h1>Sign in</h1>
                        </div>

                        <?php if ($error && isset($_POST['login_submit'])): ?>
                            <div class="alert alert-danger">
                                <span><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></span>
                                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none';">&times;</button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="email_login">E-mail</label>
                                <input type="email" class="form-control" id="email_login" name="email" required 
                                       placeholder="your@email.com">
                            </div>

                            <div class="form-group">
                                <label for="password_login">Password</label>
                                <input type="password" class="form-control" id="password_login" name="password" required 
                                       placeholder="••••••••">
                            </div>

                            <div class="password-help">
                                <a href="#">Forgot password?</a>
                            </div>

                            <input type="hidden" name="debug_login" value="1">
                            <button type="submit" name="login_submit" class="btn-auth">
                                Sign In
                            </button>

                            <div class="auth-toggle">
                                <a onclick="toggleForm()">Don't have an account? <strong>Sign Up</strong></a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Signup Form -->
                <div class="auth-form hidden" id="signupForm">
                    <div class="auth-card">
                        <div class="auth-header">
                            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="auth-logo">
                            <h1>Create Account</h1>
                            <p>Join ManageMo and manage your campus assets</p>
                        </div>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <span><i class="fas fa-check-circle"></i> <?php echo $success; ?></span>
                                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none';">&times;</button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error && isset($_POST['signup_submit'])): ?>
                            <div class="alert alert-danger">
                                <span><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></span>
                                <button type="button" class="btn-close" onclick="this.parentElement.style.display='none';">&times;</button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" onsubmit="return validateSignupForm()">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required 
                                       placeholder="John Doe">
                            </div>

                            <div class="form-group">
                                <label for="email_signup">E-mail</label>
                                <input type="email" class="form-control" id="email_signup" name="email_signup" required 
                                       placeholder="your@email.com">
                            </div>

                            <div class="form-group">
                                <label for="campus_id">Campus</label>
                                <select class="form-select" id="campus_id" name="campus_id" required>
                                    <option value="">Select your campus</option>
                                    <?php foreach ($campuses as $campus): ?>
                                    <option value="<?php echo $campus['id']; ?>">
                                        <?php echo htmlspecialchars($campus['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password_signup">Password</label>
                                    <input type="password" class="form-control" id="password_signup" name="password_signup" required 
                                           placeholder="••••••••" oninput="updatePasswordStrength()">
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required 
                                           placeholder="••••••••">
                                </div>
                            </div>

                            <button type="submit" name="signup_submit" class="btn-auth">
                                Create Account
                            </button>

                            <div class="auth-toggle">
                                <a onclick="toggleForm()">Already have an account? <strong>Sign In</strong></a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promo Section -->
        <div class="auth-right" id="authRight">
            <div class="promo-content">
                <h2 class="promo-heading">Pampanga State University</h2>
                <p class="promo-text">ManageMo tracks and manages all university assets across 8 PSU campuses with real-time updates, QR code generation, and comprehensive reporting.</p>
                
                <div class="features-list">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Asset Tracking</h4>
                            <p>Monitor 2,500+ items</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="feature-text">
                            <h4>QR Codes</h4>
                            <p>Quick identification</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Multi-Campus</h4>
                            <p>8 PSU campuses</p>
                        </div>
                    </div>
                    
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Analytics</h4>
                            <p>Real-time insights</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleForm() {
            const loginForm = document.getElementById('loginForm');
            const signupForm = document.getElementById('signupForm');
            const authLeft = document.getElementById('authLeft');
            const authRight = document.getElementById('authRight');
            
            loginForm.classList.toggle('hidden');
            signupForm.classList.toggle('hidden');
            authLeft.classList.toggle('signup-active');
            authRight.classList.toggle('signup-active');
        }
        
        function updatePasswordStrength() {
            const password = document.getElementById('password_signup').value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength <= 25) strengthBar.style.background = '#E74C3C';
            else if (strength <= 50) strengthBar.style.background = '#F39C12';
            else if (strength <= 75) strengthBar.style.background = '#F1C40F';
            else strengthBar.style.background = '#27AE60';
        }
        
        function validateSignupForm() {
            const password = document.getElementById('password_signup').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
