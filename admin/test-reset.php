<?php
/**
 * Password-reset flow diagnostic — admin only. Delete after confirming it works.
 */
require_once dirname(__DIR__) . '/config/functions.php';
requireAdmin();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';

$steps  = [];
$email  = trim($_POST['email'] ?? '');

function step(string $label, bool $ok, string $detail = ''): array {
    return ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email) {

    // Step 1: SMTP configured?
    $mailer = SmtpMailer::fromEnv();
    $steps[] = step(
        'SMTP credentials loaded',
        $mailer !== null,
        $mailer ? 'Host: ' . (getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?? '?') : 'SmtpMailer::fromEnv() returned null — check SMTP_HOST / SMTP_USERNAME / SMTP_PASSWORD in .env'
    );

    // Step 2: User found?
    $matched_user = null;
    foreach (getUsers() as $u) {
        if (strtolower($u['email']) === strtolower($email) && $u['is_active'] == 1) {
            $matched_user = $u;
            break;
        }
    }
    $steps[] = step(
        'User found in database',
        $matched_user !== null,
        $matched_user
            ? 'Found: ' . $matched_user['full_name'] . ' &lt;' . htmlspecialchars($matched_user['email']) . '&gt; (id=' . $matched_user['id'] . ')'
            : 'No active user with email "' . htmlspecialchars($email) . '" — check spelling or try a different email'
    );

    if ($matched_user && $mailer) {
        // Step 3: Token file writable?
        $dir   = sys_get_temp_dir() . '/managemo_pwr';
        $canW  = is_dir($dir) ? is_writable($dir) : @mkdir($dir, 0700, true);
        $steps[] = step('Token directory writable', (bool)$canW, $dir);

        // Step 4: Build URL
        $token     = bin2hex(random_bytes(32));
        $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'];
        $reset_url = $scheme . '://' . $host . rtrim(BASE_URL, '/') . '/reset-password.php?token=' . urlencode($token);
        $steps[] = step('Reset URL built', true, htmlspecialchars($reset_url));

        // Step 5: Actually send
        $sendErr = '';
        $sent    = false;
        try {
            $sent = $mailer->sendHtml(
                $matched_user['email'],
                $matched_user['full_name'],
                '[ManageMo] Password Reset Test',
                '<p>This is a <strong>password-reset diagnostic email</strong> from ManageMo.<br>Reset link: <a href="' . htmlspecialchars($reset_url) . '">' . htmlspecialchars($reset_url) . '</a></p>',
                'Password reset test. Link: ' . $reset_url
            );
        } catch (\Throwable $e) {
            $sendErr = get_class($e) . ': ' . $e->getMessage();
        }
        $steps[] = step(
            'Email sent to ' . htmlspecialchars($matched_user['email']),
            $sent,
            $sendErr ?: ($sent ? 'Check inbox (and spam folder)' : 'send() returned false')
        );
    }
}
?>
<div class="container-fluid mt-4 pb-5" style="max-width:700px;">
    <h4 style="font-weight:800;color:#1a1d23;margin-bottom:4px;">
        <i class="fas fa-key me-2" style="color:#8B0000;"></i>Password-Reset Diagnostic
    </h4>
    <p style="color:#6b7280;font-size:.87rem;margin-bottom:24px;">
        Runs the full forgot-password flow step-by-step and shows exactly where it fails.
        <strong>Delete this file after use.</strong>
    </p>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px 20px;margin-bottom:20px;">
        <form method="POST">
            <label style="font-size:.84rem;font-weight:700;color:#374151;display:block;margin-bottom:8px;">
                Test with this email address:
            </label>
            <div style="display:flex;gap:10px;">
                <input type="email" name="email" class="form-control" required
                       placeholder="user@example.com"
                       value="<?php echo htmlspecialchars($email); ?>"
                       style="border-radius:6px;font-size:.87rem;">
                <button type="submit" class="btn"
                        style="background:#8B0000;color:#fff;font-weight:700;border-radius:6px;white-space:nowrap;padding:0 20px;">
                    <i class="fas fa-play me-1"></i>Run Test
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($steps)): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
        <?php foreach ($steps as $i => $s): ?>
        <div style="display:flex;gap:14px;align-items:flex-start;padding:14px 18px;<?php echo $i ? 'border-top:1px solid #f3f4f6;' : ''; ?>">
            <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;
                        background:<?php echo $s['ok'] ? '#dcfce7' : '#fee2e2'; ?>;
                        color:<?php echo $s['ok'] ? '#15803d' : '#b91c1c'; ?>;font-size:13px;">
                <i class="fas fa-<?php echo $s['ok'] ? 'check' : 'times'; ?>"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:.87rem;color:#1a1d23;"><?php echo htmlspecialchars($s['label']); ?></div>
                <?php if ($s['detail']): ?>
                <div style="font-size:.80rem;color:<?php echo $s['ok'] ? '#6b7280' : '#b91c1c'; ?>;margin-top:3px;">
                    <?php echo $s['detail']; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
