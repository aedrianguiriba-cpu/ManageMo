<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user['role'] === ROLE_ADMIN) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
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
        // Check if email already exists (in mock data)
        $users = getUsers();
        foreach ($users as $u) {
            if ($u['email'] === $email) {
                $error = 'Email is already registered';
                break;
            }
        }
        
        if (!$error) {
            // In a real system, we would save to database here
            // For now, just show success message
            $success = 'Account created successfully! Please log in with your credentials.';
        }
    }
}

// Get all campuses for dropdown
$campuses = getAllCampuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ManageMo | Pampanga State University</title>
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
        }
        .signup-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }
        .signup-left {
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
        }
        .signup-card {
            width: 100%;
            max-width: 400px;
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
            transition: none;
        }
        .signup-header {
            margin-bottom: 35px;
            text-align: left;
        }
        .signup-logo {
            width: 110px;
            height: 65px;
            object-fit: contain;
            margin-bottom: 30px;
            display: block;
            filter: none;
            transition: transform 0.3s;
        }
        .signup-logo:hover {
            transform: scale(1.05);
        }
        .signup-header h1 {
            color: #1a1a1a;
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .signup-header p {
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
        .form-control:-webkit-autofill {
            -webkit-box-shadow: inset 0 0 0 1000px #FFFFFF;
            -webkit-text-fill-color: #2C3E50;
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
        .btn-signup {
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
        .btn-signup:hover {
            background: #6B0000;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-link a {
            color: #7F8C8D;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
        }
        .login-link a strong {
            color: #8B0000;
            font-weight: 700;
            transition: color 0.3s;
        }
        .login-link a:hover {
            color: #2C3E50;
        }
        .login-link a:hover strong {
            color: #6B0000;
            text-decoration: underline;
        }
        .signup-right {
            background: #8B0000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .signup-right::before {
            content: none;
        }
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
        @media (max-width: 992px) {
            .signup-wrapper {
                grid-template-columns: 1fr;
            }
            .signup-right {
                display: none;
            }
            .signup-left {
                min-height: 100vh;
            }
        }
    </style>
</head>
<body>
    <div class="signup-wrapper">
        <div class="signup-left">
            <div class="signup-card">
                <div class="signup-header">
                    <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="signup-logo">
                    <h1>Create Account</h1>
                    <p>Join ManageMo and manage your campus assets</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <span><i class="fas fa-check-circle"></i> <?php echo $success; ?></span>
                        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <span><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></span>
                        <button type="button" class="btn-close" onclick="this.parentElement.style.display='none';">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required 
                               placeholder="John Doe">
                    </div>

                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required 
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
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required 
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

                    <button type="submit" class="btn-signup">
                        Create Account
                    </button>

                    <div class="login-link">
                        <a href="index.php">Already have an account? <strong>Sign In</strong></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="signup-right">
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
        function updatePasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('strengthBar');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.length >= 8) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            // Color the bar
            if (strength <= 25) strengthBar.style.background = '#E74C3C'; // Red
            else if (strength <= 50) strengthBar.style.background = '#F39C12'; // Orange
            else if (strength <= 75) strengthBar.style.background = '#F1C40F'; // Yellow
            else strengthBar.style.background = '#27AE60'; // Green
        }
        
        function validateForm() {
            const password = document.getElementById('password').value;
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
