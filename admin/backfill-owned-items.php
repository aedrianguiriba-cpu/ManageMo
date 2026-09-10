<?php
/**
 * One-time backfill: for delivered requests that predate (or were confirmed
 * through a path that skipped) the auto-transfer logic, this creates whatever
 * is missing — a user_owned_items record for an item request, or a
 * borrow_records row for a borrow request — using the same
 * processDeliveredRequestUnit() the live "Mark Delivered" flow uses.
 *
 * Run once via browser (admin only), then delete or restrict this file.
 */
$page_title = 'Backfill Delivered Requests';
require_once dirname(__DIR__) . '/config/functions.php';
requireAdmin();
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';

$all_requests    = getRequests();
$all_inventory   = getInventory();
$all_users       = getUsers();
$existing_owned  = getUserOwnedItems();
$existing_borrow = getBorrowRecords();

// Index existing owned items by qr_code_id, and existing borrow records by
// the request_id that opened them, for fast "is this already backfilled?" checks.
$owned_by_qr = [];
foreach ($existing_owned as $o) {
    if (!empty($o['qr_code_id'])) $owned_by_qr[$o['qr_code_id']] = true;
}
$borrow_by_request_id = [];
foreach ($existing_borrow as $b) {
    if (!empty($b['request_id'])) $borrow_by_request_id[(int)$b['request_id']] = true;
}

// Find delivered item/borrow requests missing their corresponding record.
// A request that was delivered and later marked Completed moves its `status`
// to 'completed' (delivery_status stays 'delivered') — still counts as
// delivered for backfill purposes, so check delivery_status/status together
// rather than requiring status to still literally be 'delivered'.
$missing = [];
foreach ($all_requests as $req) {
    if (!in_array($req['request_type'], ['item', 'borrow'], true)) continue;
    $was_delivered = $req['status'] === 'delivered'
        || ($req['delivery_status'] ?? null) === 'delivered'
        || $req['status'] === 'completed';
    if (!$was_delivered) continue;
    if (empty($req['inventory_id'])) continue;

    if ($req['request_type'] === 'item') {
        $inv = findById($all_inventory, (int)$req['inventory_id']);
        $qr  = $req['qr_code_id'] ?? ($inv['qr_code_id'] ?? null);
        if ($qr && isset($owned_by_qr[$qr])) continue; // already transferred
    } else {
        if (isset($borrow_by_request_id[(int)$req['id']])) continue; // already has a borrow record
        $inv = findById($all_inventory, (int)$req['inventory_id']);
    }

    $missing[] = [
        'req'  => $req,
        'inv'  => $inv,
        'user' => findById($all_users, (int)$req['user_id']),
    ];
}

$dry_run = !isset($_POST['confirm']);
$results = [];

if (!$dry_run && !empty($missing)) {
    foreach ($missing as $m) {
        $req  = $m['req'];
        $user = $m['user'];
        processDeliveredRequestUnit($req, $user);

        // Verify it actually landed, so the results table reflects reality
        // rather than assuming processDeliveredRequestUnit() succeeded.
        if ($req['request_type'] === 'item') {
            $inv = findById(getInventory(), (int)$req['inventory_id']);
            $ok  = $inv && $inv['status'] === 'owned';
        } else {
            $ok = !empty(array_filter(getBorrowRecords(), fn($b) => (int)($b['request_id'] ?? 0) === (int)$req['id']));
        }
        $results[] = ['req' => $req, 'inv' => $m['inv'], 'user' => $user, 'ok' => $ok];
    }
}

$type_labels = ['item' => 'Item', 'borrow' => 'Borrow'];
?>
<div class="container-fluid mt-4 pb-5" style="max-width:900px;">
    <div style="margin-bottom:20px;">
        <h4 style="font-weight:800;color:#1a1d23;margin:0;">
            <i class="fas fa-boxes me-2" style="color:#8B0000;"></i>Backfill — Delivered Requests
        </h4>
        <p style="color:#6b7280;font-size:.87rem;margin-top:4px;">
            Finds delivered item requests with no <code>user_owned_items</code> record, and delivered borrow requests with no <code>borrow_records</code> row, then creates them.
        </p>
    </div>

    <?php if (empty($missing) && $dry_run): ?>
    <div class="alert alert-success" style="border-radius:10px;">
        <i class="fas fa-check-circle me-2"></i>
        <strong>All clear.</strong> Every delivered item/borrow request already has its corresponding record. Nothing to backfill.
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
                    <th style="padding:10px 14px;">Type</th>
                    <th style="padding:10px 14px;">User</th>
                    <th style="padding:10px 14px;">Item</th>
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
                <td style="padding:10px 14px;">
                    <span class="ar-badge" style="background:<?php echo $m['req']['request_type']==='borrow' ? 'rgba(245,158,11,0.12);color:#b45309' : 'rgba(34,197,94,0.12);color:#15803d'; ?>;border-radius:4px;padding:2px 8px;font-size:.74rem;font-weight:700;">
                        <?php echo $type_labels[$m['req']['request_type']] ?? ucfirst($m['req']['request_type']); ?>
                    </span>
                </td>
                <td style="padding:10px 14px;font-weight:600;">
                    <?php echo htmlspecialchars($m['user']['full_name'] ?? 'Unknown'); ?><br>
                    <span style="font-size:.75rem;color:#9ca3af;"><?php echo htmlspecialchars($m['user']['email'] ?? ''); ?></span>
                </td>
                <td style="padding:10px 14px;">
                    <?php echo htmlspecialchars($m['inv']['item_name'] ?? '—'); ?><br>
                    <span style="font-size:.75rem;color:#9ca3af;"><?php echo htmlspecialchars($m['inv']['category'] ?? ''); ?></span>
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
            onclick="return confirm('Create <?php echo count($missing); ?> record(s) — ownership transfers and/or borrow records — and update the related inventory status?');">
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
                    <th style="padding:10px 14px;">Type</th>
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
                <td style="padding:10px 14px;">
                    <span class="ar-badge" style="background:<?php echo $r['req']['request_type']==='borrow' ? 'rgba(245,158,11,0.12);color:#b45309' : 'rgba(34,197,94,0.12);color:#15803d'; ?>;border-radius:4px;padding:2px 8px;font-size:.74rem;font-weight:700;">
                        <?php echo $type_labels[$r['req']['request_type']] ?? ucfirst($r['req']['request_type']); ?>
                    </span>
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
