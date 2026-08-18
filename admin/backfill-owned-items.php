<?php
/**
 * One-time backfill: create user_owned_items records for item requests
 * that were delivered BEFORE the auto-transfer logic was added.
 *
 * Run once via browser (admin only), then delete or restrict this file.
 */
$page_title = 'Backfill Owned Items';
require_once dirname(__DIR__) . '/config/functions.php';
requireAdmin();
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';

$all_requests   = getRequests();
$all_inventory  = getInventory();
$all_users      = getUsers();
$existing_owned = getUserOwnedItems();

// Index existing owned items by qr_code_id for fast lookup
$owned_by_qr = [];
foreach ($existing_owned as $o) {
    if (!empty($o['qr_code_id'])) $owned_by_qr[$o['qr_code_id']] = true;
}

// Find delivered item requests that have no ownership record yet
$missing = [];
foreach ($all_requests as $req) {
    if ($req['request_type'] !== 'item') continue;
    if ($req['status'] !== 'delivered')  continue;
    if (empty($req['inventory_id']))      continue;

    $inv = findById($all_inventory, (int)$req['inventory_id']);
    $qr  = $req['qr_code_id'] ?? ($inv['qr_code_id'] ?? null);

    // Skip if already transferred (matched by QR code)
    if ($qr && isset($owned_by_qr[$qr])) continue;

    $missing[] = [
        'req'  => $req,
        'inv'  => $inv,
        'user' => findById($all_users, (int)$req['user_id']),
        'qr'   => $qr,
    ];
}

$dry_run   = !isset($_POST['confirm']);
$results   = [];

if (!$dry_run && !empty($missing)) {
    foreach ($missing as $m) {
        $req  = $m['req'];
        $inv  = $m['inv'];
        $user = $m['user'];
        $qr   = $m['qr'];

        $created = dbCreateUserOwnedItem([
            'user_id'     => (int)$req['user_id'],
            'qr_code_id'  => $qr,
            'item_name'   => $inv['item_name']   ?? 'Unknown Item',
            'category'    => $inv['category']    ?? 'General',
            'description' => $inv['description'] ?? null,
            'year_owned'  => (int)date('Y', strtotime($req['updated_at'] ?? $req['created_at'])),
            'campus_id'   => (int)($user['campus_id'] ?? $inv['campus_id'] ?? 1),
            'quantity'    => 1,
            'condition'   => $inv['condition'] ?? null,
            'notes'       => $req['reason_for_request'] ?? null,
            'group_id'    => $req['group_id'] ?? null,
        ]);

        // Mark inventory as disposed
        if (!empty($req['inventory_id'])) {
            dbUpdateInventory((int)$req['inventory_id'], ['status' => 'disposed']);
        }

        $results[] = ['req' => $req, 'inv' => $inv, 'user' => $user, 'ok' => (bool)$created];
    }
}
?>
<div class="container-fluid mt-4 pb-5" style="max-width:860px;">
    <div style="margin-bottom:20px;">
        <h4 style="font-weight:800;color:#1a1d23;margin:0;">
            <i class="fas fa-boxes me-2" style="color:#8B0000;"></i>Backfill — Ownership Transfer
        </h4>
        <p style="color:#6b7280;font-size:.87rem;margin-top:4px;">
            Finds delivered item requests with no <code>user_owned_items</code> record and creates them.
        </p>
    </div>

    <?php if (empty($missing) && $dry_run): ?>
    <div class="alert alert-success" style="border-radius:10px;">
        <i class="fas fa-check-circle me-2"></i>
        <strong>All clear.</strong> Every delivered item request already has a corresponding ownership record. Nothing to backfill.
    </div>

    <?php elseif ($dry_run && !empty($missing)): ?>
    <div class="alert alert-warning" style="border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong><?php echo count($missing); ?> record<?php echo count($missing) !== 1 ? 's' : ''; ?> will be created.</strong>
        Review the list below, then click <strong>Run Backfill</strong> to apply.
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px;">
        <table class="table table-sm mb-0" style="font-size:.84rem;">
            <thead style="background:#f7f7f7;">
                <tr>
                    <th style="padding:10px 14px;">Request #</th>
                    <th style="padding:10px 14px;">User</th>
                    <th style="padding:10px 14px;">Item</th>
                    <th style="padding:10px 14px;">QR Code</th>
                    <th style="padding:10px 14px;">Delivered</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($missing as $m): ?>
            <tr>
                <td style="padding:10px 14px;">
                    <code style="background:rgba(139,0,0,.07);color:#8B0000;border-radius:4px;padding:1px 6px;font-size:.75rem;">
                        <?php echo htmlspecialchars($m['req']['request_number']); ?>
                    </code>
                </td>
                <td style="padding:10px 14px;font-weight:600;">
                    <?php echo htmlspecialchars($m['user']['full_name'] ?? 'Unknown'); ?><br>
                    <span style="font-size:.75rem;color:#9ca3af;"><?php echo htmlspecialchars($m['user']['email'] ?? ''); ?></span>
                </td>
                <td style="padding:10px 14px;">
                    <?php echo htmlspecialchars($m['inv']['item_name'] ?? '—'); ?><br>
                    <span style="font-size:.75rem;color:#9ca3af;"><?php echo htmlspecialchars($m['inv']['category'] ?? ''); ?></span>
                </td>
                <td style="padding:10px 14px;font-family:monospace;font-size:.75rem;color:#6b7280;">
                    <?php echo htmlspecialchars($m['qr'] ?? '—'); ?>
                </td>
                <td style="padding:10px 14px;color:#6b7280;font-size:.80rem;">
                    <?php echo !empty($m['req']['updated_at']) ? date('M d, Y', strtotime($m['req']['updated_at'])) : '—'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="POST">
        <button type="submit" name="confirm" value="1" class="btn"
            style="background:#8B0000;color:#fff;font-weight:700;border-radius:8px;padding:10px 24px;"
            onclick="return confirm('Create <?php echo count($missing); ?> ownership record(s) and mark inventory as disposed?');">
            <i class="fas fa-play me-2"></i>Run Backfill
        </button>
        <a href="requests.php" class="btn btn-secondary ms-2" style="border-radius:8px;">Cancel</a>
    </form>

    <?php else: ?>
    <!-- Post-run results -->
    <?php $ok = count(array_filter($results, fn($r) => $r['ok']));
          $fail = count($results) - $ok; ?>
    <div class="alert <?php echo $fail > 0 ? 'alert-warning' : 'alert-success'; ?>" style="border-radius:10px;margin-bottom:20px;">
        <i class="fas fa-<?php echo $fail > 0 ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
        <strong><?php echo $ok; ?> record<?php echo $ok !== 1 ? 's' : ''; ?> created successfully<?php echo $fail > 0 ? ", $fail failed" : ''; ?>.</strong>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px;">
        <table class="table table-sm mb-0" style="font-size:.84rem;">
            <thead style="background:#f7f7f7;">
                <tr>
                    <th style="padding:10px 14px;">Request #</th>
                    <th style="padding:10px 14px;">User</th>
                    <th style="padding:10px 14px;">Item</th>
                    <th style="padding:10px 14px;">Result</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td style="padding:10px 14px;">
                    <code style="background:rgba(139,0,0,.07);color:#8B0000;border-radius:4px;padding:1px 6px;font-size:.75rem;">
                        <?php echo htmlspecialchars($r['req']['request_number']); ?>
                    </code>
                </td>
                <td style="padding:10px 14px;font-weight:600;"><?php echo htmlspecialchars($r['user']['full_name'] ?? 'Unknown'); ?></td>
                <td style="padding:10px 14px;"><?php echo htmlspecialchars($r['inv']['item_name'] ?? '—'); ?></td>
                <td style="padding:10px 14px;">
                    <?php if ($r['ok']): ?>
                    <span style="color:#15803d;font-weight:700;"><i class="fas fa-check me-1"></i>Created</span>
                    <?php else: ?>
                    <span style="color:#b91c1c;font-weight:700;"><i class="fas fa-times me-1"></i>Failed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="requests.php" class="btn btn-secondary" style="border-radius:8px;">
        <i class="fas fa-arrow-left me-1"></i>Back to Requests
    </a>
    <?php endif; ?>
</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
