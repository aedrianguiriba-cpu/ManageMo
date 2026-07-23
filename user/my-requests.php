<?php
$page_title = 'My Requests';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();
$user_id      = $current_user['id'];
$status_filter = $_GET['status'] ?? '';
$type_filter   = $_GET['type']   ?? '';

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
$all_requests   = getRequests();
$all_inventory  = getInventory();
$all_users      = getUsers();
$admin_user     = null;

// Build enriched request list for this user (one row per DB record)
$raw_requests = [];
foreach ($all_requests as $req) {
    if ($req['user_id'] != $user_id) continue;
    $item     = !empty($req['inventory_id']) ? findById($all_inventory, (int)$req['inventory_id']) : null;
    $approver = $req['approved_by'] ? findById($all_users, $req['approved_by']) : null;
    $req['item_name']     = $item['item_name']     ?? null;
    $req['item_category'] = $item['category']      ?? null;
    $req['approver_name'] = $approver['full_name'] ?? null;
    $raw_requests[] = $req;
}

// Filter individual rows first (so stats remain accurate)
$filtered_mine = $raw_requests;
if ($status_filter) {
    $filtered_mine = array_values(array_filter($filtered_mine, fn($r) => $r['status'] === $status_filter));
}
if ($type_filter) {
    $filtered_mine = array_values(array_filter($filtered_mine, fn($r) => $r['request_type'] === $type_filter));
}

// Group by group_id; each group → one card
$my_groups = [];
foreach ($filtered_mine as $req) {
    $gkey = !empty($req['group_id']) ? 'gid:' . $req['group_id'] : 'id:' . $req['id'];
    if (!isset($my_groups[$gkey])) {
        $my_groups[$gkey] = ['rows' => [], 'first' => $req];
    }
    $my_groups[$gkey]['rows'][] = $req;
}
$my_groups = array_values($my_groups);

// Sort groups newest-first by first row's created_at
usort($my_groups, fn($a, $b) => strcmp($b['first']['created_at'], $a['first']['created_at']));

// Build display-ready $my_requests (one entry per group)
$my_requests = [];
foreach ($my_groups as $grp) {
    $req  = $grp['first'];
    $rows = $grp['rows'];
    // Collect item names
    $names = [];
    foreach ($rows as $_r) {
        $n = $_r['item_name'] ?? null;
        if ($n) $names[] = $n;
    }
    $names = array_values(array_unique($names));
    $req['item_name']  = !empty($names) ? implode(', ', array_slice($names, 0, 2)) . (count($names) > 2 ? '…' : '') : null;
    $req['unit_count'] = count($rows);
    $req['group_rows'] = $rows;
    $my_requests[] = $req;
}

// Stats (unfiltered, counted by group)
$all_mine_raw = array_filter($all_requests, fn($r) => $r['user_id'] == $user_id);
$all_mine_groups = [];
foreach ($all_mine_raw as $r) {
    $k = !empty($r['group_id']) ? 'gid:'.$r['group_id'] : 'id:'.$r['id'];
    $all_mine_groups[$k] = $r; // last row per group; status should be uniform
}
$stat_total       = count($all_mine_groups);
$stat_pending     = count(array_filter($all_mine_groups, fn($r) => $r['status'] === 'pending'));
$stat_approved    = count(array_filter($all_mine_groups, fn($r) => $r['status'] === 'approved'));
$stat_disapproved = count(array_filter($all_mine_groups, fn($r) => $r['status'] === 'disapproved'));

displayMessage();
?>

<style>
/* ===== MY REQUESTS TRACKER ===== */
.mrt-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.mrt-title {
    font-size:1.35rem; font-weight:800; color:#1a1d23; margin:0;
    display:flex; align-items:center; gap:10px;
}
.mrt-title-icon {
    width:40px; height:40px;
    background:#8B0000;
    border-radius:8px; display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1rem; flex-shrink:0;
}

/* Stat cards */
.mrt-stats { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px; }
.mrt-stat-card {
    flex:1; min-width:130px;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    padding:14px 16px;
    display:flex; align-items:center; gap:12px;
    cursor:pointer; transition:border-color 0.15s;
    text-decoration:none; color:inherit;
}
.mrt-stat-card:hover { border-color:rgba(139,0,0,0.25); color:inherit; text-decoration:none; }
.mrt-stat-card.active-filter { border-color:#8B0000; }
.mrt-stat-icon { width:32px; height:32px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.mrt-stat-val  { font-size:1.5rem; font-weight:800; color:#111; line-height:1; }
.mrt-stat-lbl  { font-size:0.73rem; color:#555; font-weight:600; margin-top:2px; }

/* Filter bar */
.mrt-filter {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    padding:14px 18px; margin-bottom:20px;
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
}
.mrt-filter-label { font-size:0.71rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#999; margin-bottom:4px; }

/* Request card */
.mrt-card {
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    box-shadow:0 1px 4px rgba(0,0,0,0.06);
    margin-bottom:16px; overflow:hidden;
    transition:border-color 0.15s;
}
.mrt-card:hover { border-color:rgba(139,0,0,0.20); }

.mrt-card-head {
    padding:16px 20px 14px;
    display:flex; align-items:flex-start; justify-content:space-between;
    gap:12px; flex-wrap:wrap;
    border-bottom:1px solid rgba(0,0,0,0.06);
}
.mrt-req-num {
    font-size:0.75rem; font-weight:700; font-family:monospace;
    background:rgba(139,0,0,0.07); color:#8B0000;
    border-radius:6px; padding:2px 8px; display:inline-block; margin-bottom:4px;
}
.mrt-item-name { font-size:1rem; font-weight:700; color:#1a1d23; margin:0 0 4px; }
.mrt-meta      { font-size:0.78rem; color:rgba(0,0,0,0.42); }

/* Type badge */
.mrt-type-badge {
    display:inline-flex; align-items:center; gap:5px;
    font-size:0.73rem; font-weight:700; padding:4px 10px; border-radius:20px;
}
.mrt-type-borrow  { background:rgba(59,130,246,0.10);  color:#1d4ed8;  border:1px solid rgba(59,130,246,0.20); }
.mrt-type-item    { background:rgba(34,197,94,0.10);   color:#15803d;  border:1px solid rgba(34,197,94,0.20); }
.mrt-type-service { background:rgba(245,158,11,0.10);  color:#b45309;  border:1px solid rgba(245,158,11,0.20); }

/* Urgency badge */
.mrt-urgency {
    display:inline-flex; align-items:center; gap:5px;
    font-size:0.70rem; font-weight:700; padding:3px 9px; border-radius:20px;
}
.mrt-urgency-low      { background:rgba(34,197,94,0.10);  color:#15803d; }
.mrt-urgency-medium   { background:rgba(59,130,246,0.10); color:#1d4ed8; }
.mrt-urgency-high     { background:rgba(245,158,11,0.10); color:#b45309; }
.mrt-urgency-critical { background:rgba(239,68,68,0.12);  color:#b91c1c; }

/* Status tracker stepper */
.mrt-tracker {
    padding:18px 20px;
    border-bottom:1px solid #e5e7eb;
}
.mrt-tracker-label {
    font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
    color:#999; margin-bottom:14px;
}
.mrt-steps { display:flex; align-items:center; position:relative; }
.mrt-step  { display:flex; flex-direction:column; align-items:center; flex:1; position:relative; }
.mrt-step-dot {
    width:32px; height:32px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:0.75rem; font-weight:700;
    border:2px solid #e5e7eb;
    background:#fff; color:#999;
    position:relative; z-index:2;
    transition:all 0.2s;
}
.mrt-step-dot.done     { background:#22c55e; border-color:#22c55e; color:#fff; }
.mrt-step-dot.active   { background:#8B0000; border-color:#8B0000; color:#fff; }
.mrt-step-dot.rejected { background:#ef4444; border-color:#ef4444; color:#fff; }
.mrt-step-dot.pending-dot { background:#fff; border-color:#f59e0b; color:#b45309; }
.mrt-step-lbl {
    font-size:0.67rem; font-weight:700; text-align:center; margin-top:6px;
    color:#999; line-height:1.25; max-width:70px;
}
.mrt-step-lbl.done-lbl     { color:#15803d; }
.mrt-step-lbl.active-lbl   { color:#8B0000; }
.mrt-step-lbl.rejected-lbl { color:#b91c1c; }
.mrt-step-lbl.pending-lbl  { color:#b45309; }
.mrt-step-line {
    flex:1; height:2px; background:#e5e7eb;
    margin: 0 -1px; margin-top:-22px; position:relative; z-index:1;
}
.mrt-step-line.done-line { background:#22c55e; }
.mrt-step-line.partial-line { background:#22c55e; }

/* Details section */
.mrt-card-body {
    padding:14px 20px;
    display:flex; flex-wrap:wrap; gap:16px;
}
.mrt-detail-group { flex:1; min-width:160px; }
.mrt-detail-label { font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:rgba(0,0,0,0.36); margin-bottom:3px; }
.mrt-detail-val   { font-size:0.84rem; color:#374151; font-weight:500; }

/* Notes box */
.mrt-notes {
    margin:0 20px 16px; padding:10px 14px;
    border-radius:10px; font-size:0.82rem;
    display:flex; align-items:flex-start; gap:8px;
}
.mrt-notes-approved    { background:rgba(34,197,94,0.08);  border:1px solid rgba(34,197,94,0.18);  color:#15803d; }
.mrt-notes-disapproved { background:rgba(239,68,68,0.08);  border:1px solid rgba(239,68,68,0.18);  color:#b91c1c; }
.mrt-notes-pending     { background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.18); color:#b45309; }

/* Empty state */
.mrt-empty {
    text-align:center; padding:60px 20px;
    background:#fff; border:1px solid #e5e7eb; border-radius:8px;
    color:#999;
}
.mrt-empty-icon {
    width:56px; height:56px;
    display:flex; align-items:center; justify-content:center; font-size:1.8rem;
    margin:0 auto 16px; color:#999;
}
.mrt-empty h5 { color:#555; font-size:1rem; margin:0 0 6px; }
.mrt-empty p  { font-size:0.85rem; margin:0; }

.mrt-new-btn {
    background:#8B0000 !important;
    border:none !important; border-radius:6px !important;
    font-weight:700 !important; font-size:0.88rem !important;
    color:#fff !important; padding:9px 20px !important;
    text-decoration:none; display:inline-flex; align-items:center; gap:7px;
    transition:background 0.15s;
}
.mrt-new-btn:hover { background:#7a0000 !important; color:#fff !important; }
</style>

<div class="container-fluid mt-4 pb-4">

    <!-- New Request Button -->
    <div class="d-flex justify-content-end mb-4">
        <a href="requests.php" class="mrt-new-btn">
            <i class="fas fa-plus"></i> New Request
        </a>
    </div>

    <!-- Stats -->
    <div class="mrt-stats">
        <a href="my-requests.php" class="mrt-stat-card <?php echo !$status_filter ? 'active-filter' : ''; ?>">
            <div class="mrt-stat-icon" style="color:#6b7280;">
                <i class="fas fa-layer-group"></i>
            </div>
            <div>
                <div class="mrt-stat-val"><?php echo $stat_total; ?></div>
                <div class="mrt-stat-lbl">All Requests</div>
            </div>
        </a>
        <a href="my-requests.php?status=pending" class="mrt-stat-card <?php echo $status_filter === 'pending' ? 'active-filter' : ''; ?>">
            <div class="mrt-stat-icon" style="color:#b45309;">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div>
                <div class="mrt-stat-val"><?php echo $stat_pending; ?></div>
                <div class="mrt-stat-lbl">Pending</div>
            </div>
        </a>
        <a href="my-requests.php?status=approved" class="mrt-stat-card <?php echo $status_filter === 'approved' ? 'active-filter' : ''; ?>">
            <div class="mrt-stat-icon" style="color:#15803d;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="mrt-stat-val"><?php echo $stat_approved; ?></div>
                <div class="mrt-stat-lbl">Approved</div>
            </div>
        </a>
        <a href="my-requests.php?status=disapproved" class="mrt-stat-card <?php echo $status_filter === 'disapproved' ? 'active-filter' : ''; ?>">
            <div class="mrt-stat-icon" style="color:#b91c1c;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div>
                <div class="mrt-stat-val"><?php echo $stat_disapproved; ?></div>
                <div class="mrt-stat-lbl">Disapproved</div>
            </div>
        </a>
    </div>

    <!-- Type Filter -->
    <div class="mrt-filter">
        <div>
            <div class="mrt-filter-label">Request Type</div>
            <div class="d-flex gap-2 flex-wrap">
                <?php
                $types = [
                    ''        => ['label' => 'All Types',       'icon' => 'fa-layer-group'],
                    'borrow'  => ['label' => 'Borrow',          'icon' => 'fa-hand-holding'],
                    'item'    => ['label' => 'Item Request',     'icon' => 'fa-shopping-cart'],
                    'service' => ['label' => 'Service Request',  'icon' => 'fa-tools'],
                ];
                foreach ($types as $val => $t):
                    $active = $type_filter === $val;
                    $link = 'my-requests.php?' . ($status_filter ? 'status=' . $status_filter . '&' : '') . 'type=' . $val;
                ?>
                <a href="<?php echo $link; ?>"
                   style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:20px;font-size:0.80rem;font-weight:700;text-decoration:none;
                          border:1.5px solid <?php echo $active ? '#8B0000' : 'rgba(0,0,0,0.10)'; ?>;
                          background:<?php echo $active ? 'rgba(139,0,0,0.08)' : 'rgba(255,255,255,0.60)'; ?>;
                          color:<?php echo $active ? '#8B0000' : 'rgba(0,0,0,0.55)'; ?>;">
                    <i class="fas <?php echo $t['icon']; ?>"></i> <?php echo $t['label']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Request Cards -->
    <?php if (count($my_requests) === 0): ?>
    <div class="mrt-empty">
        <div class="mrt-empty-icon"><i class="fas fa-inbox"></i></div>
        <h5>No requests found</h5>
        <p>
            <?php if ($status_filter || $type_filter): ?>
                No requests match the current filter. <a href="my-requests.php" style="color:#8B0000;">Clear filters</a>
            <?php else: ?>
                You haven't submitted any requests yet.
            <?php endif; ?>
        </p>
        <a href="requests.php" class="mrt-new-btn mt-3" style="display:inline-flex;">
            <i class="fas fa-plus"></i> Submit Your First Request
        </a>
    </div>
    <?php else: ?>
    <?php foreach ($my_requests as $req):
        $status  = $req['status'];  // pending | approved | disapproved
        $rtype   = $req['request_type'];
        $urgency = $req['urgency'] ?? 'medium';

        // Type config
        $type_map = [
            'borrow'  => ['label' => 'Borrow Item',      'icon' => 'fa-hand-holding',  'class' => 'mrt-type-borrow'],
            'item'    => ['label' => 'Item Request',      'icon' => 'fa-shopping-cart', 'class' => 'mrt-type-item'],
            'service' => ['label' => 'Service Request',   'icon' => 'fa-tools',         'class' => 'mrt-type-service'],
        ];
        $tc = $type_map[$rtype] ?? ['label' => ucfirst($rtype), 'icon' => 'fa-file', 'class' => 'mrt-type-borrow'];

        $urgency_map = [
            'low'      => ['label' => 'Low',      'icon' => 'fa-arrow-down',   'class' => 'mrt-urgency-low'],
            'medium'   => ['label' => 'Medium',   'icon' => 'fa-minus',        'class' => 'mrt-urgency-medium'],
            'high'     => ['label' => 'High',     'icon' => 'fa-arrow-up',     'class' => 'mrt-urgency-high'],
            'critical' => ['label' => 'Critical', 'icon' => 'fa-exclamation',  'class' => 'mrt-urgency-critical'],
        ];
        $uc = $urgency_map[$urgency] ?? $urgency_map['medium'];

        // Stepper logic
        // Steps: Submitted → Under Review → Approved → Out for Delivery → Delivered
        // pending      → step 2 active (hourglass), steps 3–5 inactive
        // approved     → steps 1–3 done, step 4 active (out for delivery), step 5 inactive
        // delivered    → all 5 done
        // disapproved  → steps 1–2 done, step 3 rejected, steps 4–5 inactive
        //
        // delivery_status: 'pending_delivery' | 'out_for_delivery' | 'delivered'
        // Derived: approved → 'out_for_delivery'; we store it on the req if present.
        $delivery_status = $req['delivery_status'] ?? ($status === 'approved' ? 'out_for_delivery' : null);

        $s1_dot = 'done'; $s1_lbl = 'done-lbl';

        if ($status === 'pending') {
            $s2_dot = 'pending-dot'; $s2_lbl = 'pending-lbl';
            $s3_dot = '';            $s3_lbl = '';
            $s4_dot = '';            $s4_lbl = '';
            $s5_dot = '';            $s5_lbl = '';
            $line1 = 'done-line'; $line2 = ''; $line3 = ''; $line4 = '';
        } elseif ($status === 'disapproved') {
            $s2_dot = 'done';     $s2_lbl = 'done-lbl';
            $s3_dot = 'rejected'; $s3_lbl = 'rejected-lbl';
            $s4_dot = '';         $s4_lbl = '';
            $s5_dot = '';         $s5_lbl = '';
            $line1 = 'done-line'; $line2 = ''; $line3 = ''; $line4 = '';
        } elseif ($delivery_status === 'delivered') {
            $s2_dot = 'done'; $s2_lbl = 'done-lbl';
            $s3_dot = 'done'; $s3_lbl = 'done-lbl';
            $s4_dot = 'done'; $s4_lbl = 'done-lbl';
            $s5_dot = 'done'; $s5_lbl = 'done-lbl';
            $line1 = 'done-line'; $line2 = 'done-line'; $line3 = 'done-line'; $line4 = 'done-line';
        } elseif ($delivery_status === 'out_for_delivery') {
            $s2_dot = 'done';        $s2_lbl = 'done-lbl';
            $s3_dot = 'done';        $s3_lbl = 'done-lbl';
            $s4_dot = 'active';      $s4_lbl = 'active-lbl';
            $s5_dot = '';            $s5_lbl = '';
            $line1 = 'done-line'; $line2 = 'done-line'; $line3 = 'done-line'; $line4 = '';
        } else { // approved, pending_delivery
            $s2_dot = 'done';        $s2_lbl = 'done-lbl';
            $s3_dot = 'done';        $s3_lbl = 'done-lbl';
            $s4_dot = 'pending-dot'; $s4_lbl = 'pending-lbl';
            $s5_dot = '';            $s5_lbl = '';
            $line1 = 'done-line'; $line2 = 'done-line'; $line3 = ''; $line4 = '';
        }

        // Item display
        $item_display = $req['item_name'] ?? null;
        if (!$item_display) {
            $item_display = $req['service_description']
                ? (mb_strlen($req['service_description']) > 60 ? mb_substr($req['service_description'], 0, 57) . '…' : $req['service_description'])
                : 'Custom Request';
        }

        $reason = $req['reason_for_request'] ?? $req['service_description'] ?? null;
    ?>
    <div class="mrt-card">
        <!-- Head -->
        <div class="mrt-card-head">
            <div>
                <div class="mrt-req-num">
                    <?php echo htmlspecialchars($req['request_number']); ?>
                    <?php if ($req['unit_count'] > 1): ?>
                    <span style="font-size:.70rem;background:rgba(139,0,0,.15);color:#8B0000;border-radius:3px;padding:0 6px;margin-left:4px;"><?php echo $req['unit_count']; ?> units</span>
                    <?php endif; ?>
                </div>
                <div class="mrt-item-name"><?php echo htmlspecialchars($item_display); ?></div>
                <div class="mrt-meta">
                    Submitted <?php echo date('M j, Y g:i A', strtotime($req['created_at'])); ?>
                    <?php if (!empty($req['group_id'])): ?>
                    &bull; <span style="font-family:monospace;font-size:.72rem;color:rgba(0,0,0,.38);"><?php echo htmlspecialchars($req['group_id']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-2">
                <span class="mrt-type-badge <?php echo $tc['class']; ?>">
                    <i class="fas <?php echo $tc['icon']; ?>"></i> <?php echo $tc['label']; ?>
                </span>
                <span class="mrt-urgency <?php echo $uc['class']; ?>">
                    <i class="fas <?php echo $uc['icon']; ?>"></i> <?php echo $uc['label']; ?> Priority
                </span>
            </div>
        </div>

        <!-- Stepper -->
        <div class="mrt-tracker">
            <div class="mrt-tracker-label"><i class="fas fa-map-signs me-1"></i>Request Progress</div>
            <div class="mrt-steps">

                <!-- Step 1: Submitted -->
                <div class="mrt-step">
                    <div class="mrt-step-dot done">
                        <i class="fas fa-paper-plane" style="font-size:0.72rem;"></i>
                    </div>
                    <div class="mrt-step-lbl done-lbl">Submitted</div>
                </div>

                <div class="mrt-step-line <?php echo $line1; ?>"></div>

                <!-- Step 2: Under Review -->
                <div class="mrt-step">
                    <div class="mrt-step-dot <?php echo $s2_dot; ?>">
                        <?php if ($s2_dot === 'done'): ?><i class="fas fa-search" style="font-size:0.72rem;"></i>
                        <?php else: ?><i class="fas fa-hourglass-half" style="font-size:0.72rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mrt-step-lbl <?php echo $s2_lbl; ?>">Under Review</div>
                </div>

                <div class="mrt-step-line <?php echo $line2; ?>"></div>

                <!-- Step 3: Approved / Disapproved -->
                <div class="mrt-step">
                    <div class="mrt-step-dot <?php echo $s3_dot; ?>">
                        <?php if ($s3_dot === 'done'): ?><i class="fas fa-check" style="font-size:0.72rem;"></i>
                        <?php elseif ($s3_dot === 'rejected'): ?><i class="fas fa-times" style="font-size:0.72rem;"></i>
                        <?php else: ?><i class="fas fa-ellipsis-h" style="font-size:0.72rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mrt-step-lbl <?php echo $s3_lbl; ?>"><?php echo $status === 'disapproved' ? 'Disapproved' : 'Approved'; ?></div>
                </div>

                <?php if ($status !== 'disapproved'): ?>

                <div class="mrt-step-line <?php echo $line3; ?>"></div>

                <!-- Step 4: Out for Delivery -->
                <div class="mrt-step">
                    <div class="mrt-step-dot <?php echo $s4_dot; ?>">
                        <?php if ($s4_dot === 'done'): ?><i class="fas fa-truck" style="font-size:0.72rem;"></i>
                        <?php elseif ($s4_dot === 'active'): ?><i class="fas fa-truck" style="font-size:0.72rem;"></i>
                        <?php elseif ($s4_dot === 'pending-dot'): ?><i class="fas fa-box-open" style="font-size:0.72rem;"></i>
                        <?php else: ?><i class="fas fa-truck" style="font-size:0.72rem;opacity:0.35;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mrt-step-lbl <?php echo $s4_lbl; ?>"><?php echo $s4_dot === 'active' ? 'Out for Delivery' : ($s4_dot === 'done' ? 'Delivered' : 'Preparing'); ?></div>
                </div>

                <div class="mrt-step-line <?php echo $line4; ?>"></div>

                <!-- Step 5: Delivered -->
                <div class="mrt-step">
                    <div class="mrt-step-dot <?php echo $s5_dot; ?>">
                        <?php if ($s5_dot === 'done'): ?><i class="fas fa-box-open" style="font-size:0.72rem;"></i>
                        <?php else: ?><i class="fas fa-box-open" style="font-size:0.72rem;opacity:0.35;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="mrt-step-lbl <?php echo $s5_lbl; ?>">Delivered</div>
                </div>

                <?php endif; ?>

            </div>
        </div>

        <!-- Details -->
        <div class="mrt-card-body">
            <?php if ($req['item_name']): ?>
            <div class="mrt-detail-group">
                <div class="mrt-detail-label"><i class="fas fa-box me-1"></i>Item</div>
                <div class="mrt-detail-val"><?php echo htmlspecialchars($req['item_name']); ?>
                    <?php if ($req['item_category']): ?>
                        <span style="font-size:0.70rem;color:rgba(0,0,0,0.38);margin-left:4px;">(<?php echo htmlspecialchars($req['item_category']); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="mrt-detail-group">
                <div class="mrt-detail-label"><i class="fas fa-calendar me-1"></i>Date Submitted</div>
                <div class="mrt-detail-val"><?php echo date('F j, Y', strtotime($req['created_at'])); ?></div>
            </div>

            <?php if ($req['expected_return_date']): ?>
            <div class="mrt-detail-group">
                <div class="mrt-detail-label"><i class="fas fa-calendar-check me-1"></i>Return Date</div>
                <div class="mrt-detail-val"><?php echo date('F j, Y', strtotime($req['expected_return_date'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($req['approved_at']): ?>
            <div class="mrt-detail-group">
                <div class="mrt-detail-label"><i class="fas fa-user-check me-1"></i>Reviewed By</div>
                <div class="mrt-detail-val">
                    <?php echo htmlspecialchars($req['approver_name'] ?? 'Administrator'); ?>
                    <span style="font-size:0.72rem;color:rgba(0,0,0,0.38);"> — <?php echo date('M j, Y', strtotime($req['approved_at'])); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($status === 'approved'): ?>
            <div class="mrt-detail-group">
                <div class="mrt-detail-label"><i class="fas fa-truck me-1"></i>Delivery Status</div>
                <div class="mrt-detail-val">
                    <?php
                    $ds_labels = [
                        'out_for_delivery'  => ['label' => 'Out for Delivery',  'color' => '#1d4ed8',  'bg' => 'rgba(59,130,246,0.10)',  'icon' => 'fa-truck'],
                        'delivered'         => ['label' => 'Delivered',          'color' => '#15803d',  'bg' => 'rgba(34,197,94,0.10)',   'icon' => 'fa-box-open'],
                        'pending_delivery'  => ['label' => 'Preparing for Delivery', 'color' => '#b45309', 'bg' => 'rgba(245,158,11,0.10)', 'icon' => 'fa-box'],
                    ];
                    $ds = $delivery_status ?? 'pending_delivery';
                    $dsc = $ds_labels[$ds] ?? $ds_labels['pending_delivery'];
                    ?>
                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:0.78rem;font-weight:700;
                                 padding:3px 10px;border-radius:20px;
                                 background:<?php echo $dsc['bg']; ?>;color:<?php echo $dsc['color']; ?>;">
                        <i class="fas <?php echo $dsc['icon']; ?>"></i> <?php echo $dsc['label']; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($reason): ?>
            <div class="mrt-detail-group" style="flex-basis:100%;">
                <div class="mrt-detail-label"><i class="fas fa-comment me-1"></i>Reason / Description</div>
                <div class="mrt-detail-val"><?php echo htmlspecialchars($reason); ?></div>
            </div>
            <?php endif; ?>
        </div>


        <!-- Delivery Banner -->
        <?php if ($status === 'approved'): ?>
        <?php if ($delivery_status === 'out_for_delivery'): ?>
        <div class="mrt-notes" style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.20);color:#1d4ed8;margin-bottom:16px;">
            <i class="fas fa-truck fa-bounce" style="margin-top:2px;flex-shrink:0;"></i>
            <div><strong>Your item is on the way!</strong> It has been dispatched and is currently out for delivery to your campus.</div>
        </div>
        <?php elseif ($delivery_status === 'delivered'): ?>
        <div class="mrt-notes" style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.20);color:#15803d;margin-bottom:16px;">
            <i class="fas fa-box-open" style="margin-top:2px;flex-shrink:0;"></i>
            <div><strong>Item delivered!</strong> Your request has been fulfilled. Please check with the admin office to collect your item.</div>
        </div>
        <?php else: ?>
        <div class="mrt-notes" style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.20);color:#b45309;margin-bottom:16px;">
            <i class="fas fa-box" style="margin-top:2px;flex-shrink:0;"></i>
            <div><strong>Preparing for delivery.</strong> Your approved request is being processed and will be dispatched soon.</div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>

</div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
