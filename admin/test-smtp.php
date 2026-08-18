<?php
/**
 * SMTP diagnostic — admin only. Delete after confirming email works.
 */
require_once dirname(__DIR__) . '/config/functions.php';
requireAdmin();

$result  = null;
$error   = null;
$envVars = [];

// Show what env vars were loaded
foreach (['SMTP_HOST','SMTP_PORT','SMTP_ENCRYPTION','SMTP_USERNAME','SMTP_FROM_EMAIL','SMTP_FROM_NAME'] as $k) {
    $envVars[$k] = getenv($k) ?: ($_ENV[$k] ?? '(not set)');
}
// Mask password
$envVars['SMTP_PASSWORD'] = !empty(getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? ''))
    ? str_repeat('*', 8) . ' (set)'
    : '(not set)';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = trim($_POST['to'] ?? '');
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid recipient email.';
    } else {
        try {
            $mailer = SmtpMailer::fromEnv();
            if (!$mailer) {
                $error = 'SmtpMailer::fromEnv() returned null — SMTP_HOST, SMTP_USERNAME, or SMTP_PASSWORD is missing from .env';
            } else {
                $ok = $mailer->sendHtml(
                    $to, $to,
                    '[ManageMo] SMTP Test',
                    '<p>This is a <strong>test email</strong> from ManageMo SMTP diagnostics.</p>',
                    'This is a test email from ManageMo SMTP diagnostics.'
                );
                $result = $ok ? "Email sent successfully to $to" : "send() returned false (unknown error)";
            }
        } catch (\Throwable $e) {
            $error = get_class($e) . ': ' . $e->getMessage();
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="container-fluid mt-4 pb-5" style="max-width:700px;">
    <h4 style="font-weight:800;color:#1a1d23;margin-bottom:4px;">
        <i class="fas fa-envelope-circle-check me-2" style="color:#8B0000;"></i>SMTP Diagnostic
    </h4>
    <p style="color:#6b7280;font-size:.87rem;margin-bottom:24px;">
        Sends a real test email so you can verify SMTP credentials. Delete this file after use.
    </p>

    <!-- Env vars -->
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px 20px;margin-bottom:20px;">
        <div style="font-weight:700;font-size:.85rem;margin-bottom:12px;color:#374151;">
            <i class="fas fa-gear me-1" style="color:#8B0000;"></i>Loaded SMTP config
        </div>
        <table style="width:100%;font-size:.82rem;border-collapse:collapse;">
            <?php foreach ($envVars as $k => $v): ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:6px 0;color:#6b7280;font-family:monospace;width:200px;"><?php echo $k; ?></td>
                <td style="padding:6px 0;font-weight:600;color:<?php echo $v === '(not set)' ? '#ef4444' : '#15803d'; ?>;">
                    <?php echo htmlspecialchars($v); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($result): ?>
    <div class="alert alert-success" style="border-radius:8px;">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($result); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="border-radius:8px;">
        <i class="fas fa-times-circle me-2"></i><strong>Error:</strong><br>
        <code style="font-size:.82rem;"><?php echo htmlspecialchars($error); ?></code>
    </div>
    <?php endif; ?>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px 20px;">
        <form method="POST">
            <label style="font-size:.84rem;font-weight:700;color:#374151;display:block;margin-bottom:8px;">
                Send test email to:
            </label>
            <div style="display:flex;gap:10px;">
                <input type="email" name="to" class="form-control" required
                       placeholder="recipient@example.com"
                       value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>"
                       style="border-radius:6px;font-size:.87rem;">
                <button type="submit" class="btn"
                        style="background:#8B0000;color:#fff;font-weight:700;border-radius:6px;white-space:nowrap;padding:0 20px;">
                    <i class="fas fa-paper-plane me-1"></i>Send Test
                </button>
            </div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
