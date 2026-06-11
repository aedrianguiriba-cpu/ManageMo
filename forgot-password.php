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
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (!$email) {
        $error = 'Email address is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        // Check if email exists in system
        $users = getUsers();
        $user_exists = false;
        
        foreach ($users as $u) {
            if ($u['email'] === $email && $u['is_active'] == 1) {
                $user_exists = true;
                break;
            }
        }
        
        if ($user_exists) {
            // In a real system, send password reset email here
            $success = 'Password reset instructions have been sent to ' . htmlspecialchars($email) . '. Please check your email inbox.';
        } else {
            // For security, don't reveal if email exists or not
            $success = 'If an account exists with this email, you will receive password reset instructions shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ManageMo | Pampanga State University</title>
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
        .forgotpw-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }
        .forgotpw-left {
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
        }
        .forgotpw-card {
            width: 100%;
            max-width: 400px;
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
            transition: none;
        }
        .forgotpw-header {
            margin-bottom: 35px;
            text-align: left;
        }
        .forgotpw-logo {
            width: 110px;
            height: 65px;
            object-fit: contain;
            margin-bottom: 30px;
            display: block;
            filter: none;
            transition: transform 0.3s;
        }
        .forgotpw-logo:hover {
            transform: scale(1.05);
        }
        .forgotpw-header h1 {
            color: #1a1a1a;
            font-size: 38px;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .forgotpw-header p {
            color: #7F8C8D;
            font-size: 15px;
            margin: 0;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 22px;
            clear: both;
            position: relative;
        }
        .form-group label {
            display: block;
            color: #2C3E50;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 10px;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        .form-control {
            background: #FFFFFF;
            border: 1px solid #e5e7eb;
            padding: 14px 16px;
            border-radius: 6px;
            font-size: 14px;
            color: #2C3E50;
            width: 100%;
            transition: border-color 0.2s;
        }
        .form-control::placeholder {
            color: #BDBDBD;
        }
        .form-control:hover {
            border-color: #CCCCCC;
        }
        .form-control:focus {
            outline: none;
            border-color: #8B0000;
            box-shadow: none;
        }
        .btn-reset {
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
        .btn-reset:hover {
            background: #6B0000;
        }
        .back-to-login {
            text-align: center;
            margin-top: 25px;
        }
        .back-to-login a {
            color: #7F8C8D;
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: color 0.3s;
        }
        .back-to-login a strong,
        .back-to-login a i {
            color: #8B0000;
            font-weight: 700;
            transition: color 0.3s;
        }
        .back-to-login a:hover {
            color: #2C3E50;
        }
        .back-to-login a:hover strong,
        .back-to-login a:hover i {
            color: #6B0000;
            text-decoration: underline;
        }
        .forgotpw-right {
            background: #8B0000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .forgotpw-right::before {
            content: none;
        }
        .info-content {
            max-width: 400px;
            z-index: 1;
        }
        .info-icon {
            font-size: 64px;
            margin-bottom: 24px;
            color: rgba(255, 255, 255, 0.95);
        }
        .info-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .info-text {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .info-steps {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .step-item {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }
        .step-item:last-child {
            margin-bottom: 0;
        }
        .step-number {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
            font-size: 16px;
        }
        .step-text {
            flex: 1;
        }
        .step-text p {
            margin: 0;
            font-size: 13px;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.90);
        }
        .alert {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
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
            flex-shrink: 0;
            margin-top: 2px;
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
            flex-shrink: 0;
        }
        .btn-close:hover {
            opacity: 1;
        }
        @media (max-width: 992px) {
            .forgotpw-wrapper {
                grid-template-columns: 1fr;
            }
            .forgotpw-right {
                display: none;
            }
            .forgotpw-left {
                min-height: 100vh;
            }
        }
    </style>
</head>
<body>
    <div class="forgotpw-wrapper">
        <div class="forgotpw-left">
            <div class="forgotpw-card">
                <div class="forgotpw-header">
                    <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="forgotpw-logo">
                    <h1>Forgot Password?</h1>
                    <p>Don't worry, we'll help you reset your password in a few easy steps.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <div><i class="fas fa-check-circle"></i></div>
                        <div>
                            <span><?php echo $success; ?></span>
                            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none';">&times;</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <div><i class="fas fa-exclamation-circle"></i></div>
                        <div>
                            <span><?php echo $error; ?></span>
                            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.style.display='none';">&times;</button>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required 
                               placeholder="your@email.com" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <p style="font-size: 13px; color: #7F8C8D; margin-bottom: 22px;">
                        <i class="fas fa-info-circle" style="color: #8B0000;"></i>
                        Enter the email address associated with your account and we'll send you a link to reset your password.
                    </p>

                    <button type="submit" class="btn-reset">
                        <i class="fas fa-paper-plane me-2"></i>Send Reset Link
                    </button>

                    <div class="back-to-login">
                        <a href="index.php"><i class="fas fa-arrow-left"></i> <strong>Back to Sign In</strong></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="forgotpw-right">
            <div class="info-content">
                <div class="info-icon">
                    <i class="fas fa-lock-open"></i>
                </div>
                <h2 class="info-title">Reset Your Password</h2>
                <p class="info-text">Follow these simple steps to regain access to your ManageMo account:</p>
                
                <div class="info-steps">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-text">
                            <p><strong>Enter Email</strong><br>Provide the email address associated with your account</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-text">
                            <p><strong>Check Your Email</strong><br>We'll send a password reset link to your inbox</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-text">
                            <p><strong>Create New Password</strong><br>Click the link and set a new secure password</p>
                        </div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-text">
                            <p><strong>Sign In</strong><br>Use your new password to log back into ManageMo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
