<?php
$page_title = 'Submit Request';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();
$campus_id = $current_user['campus_id'];

// Check if item_id is passed from inventory page
$auto_fill_item = null;
$auto_fill_item_id = null;
if (isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    $all_items = getInventory();
    foreach ($all_items as $item) {
        if ($item['id'] === $item_id && $item['status'] === 'available') {
            $auto_fill_item = $item['item_name'];
            $auto_fill_item_id = $item['id'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type = sanitizeInput($_POST['request_type']);
    $urgency = sanitizeInput($_POST['urgency']);

    // Validate approval letter upload
    if (empty($_FILES['approval_letter']['name'])) {
        $error = 'An approval letter from your office/college/department head is required.';
    } elseif ($_FILES['approval_letter']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload failed. Please try again.';
    } else {
        $allowed_types = ['application/pdf','image/jpeg','image/jpg','image/png'];
        $allowed_ext   = ['pdf','jpg','jpeg','png'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['approval_letter']['tmp_name']);
        $ext   = strtolower(pathinfo($_FILES['approval_letter']['name'], PATHINFO_EXTENSION));
        $size  = $_FILES['approval_letter']['size'];

        if (!in_array($mime, $allowed_types) || !in_array($ext, $allowed_ext)) {
            $error = 'Only PDF, JPG, and PNG files are accepted for the approval letter.';
        } elseif ($size > 5 * 1024 * 1024) {
            $error = 'The approval letter file must not exceed 5 MB.';
        }
    }

    if (!isset($error)) {
        // Store the uploaded file
        $upload_dir = dirname(__DIR__) . '/assets/uploads/approval_letters/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $safe_name = 'letter_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES['approval_letter']['tmp_name'], $upload_dir . $safe_name);

        // Generate request number
        $request_number = 'REQ-' . date('YmdHis') . '-' . strtoupper(substr(md5(mt_rand()), 0, 5));

        // In hardcoded mode, just log and redirect
        logActivity($current_user['id'], 'CREATE', "Submitted $request_type request", 'requests', rand(100, 999));
        redirectWithMessage('requests.php', 'Request submitted successfully! Request ID: ' . $request_number, 'success');
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';
?>
<div class="main-wrapper">
<?php
$all_inventory  = getInventory();
$inventory_items = filterByColumn($all_inventory, 'campus_id', $campus_id);
usort($inventory_items, function($a, $b) { return strcmp($a['item_name'], $b['item_name']); });

// Get available items for dropdown (from actual inventory)
$available_items = array_filter($all_inventory, function($item) {
    return $item['status'] === 'available';
});
usort($available_items, function($a, $b) { return strcmp($a['item_name'], $b['item_name']); });

// Group available items by category
$catalog_by_category = [];
foreach ($available_items as $item) {
    $category = $item['category'] ?? 'Other';
    if (!isset($catalog_by_category[$category])) {
        $catalog_by_category[$category] = [];
    }
    $catalog_by_category[$category][] = $item;
}

displayMessage();
?>

<style>
/* ===== SUBMIT REQUEST — REDESIGN ===== */
:root { --red:#8B0000; --red2:#b91c1c; }

/* ── Step indicator ── */
.rq-steps {
    display:flex; align-items:center; justify-content:center;
    gap:0; margin-bottom:28px;
}
.rq-step-item { display:flex; flex-direction:column; align-items:center; flex:1; max-width:140px; position:relative; }
.rq-step-dot {
    width:34px; height:34px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-size:0.78rem; font-weight:800; z-index:2;
    background:rgba(0,0,0,0.06); color:rgba(0,0,0,0.30);
    border:2px solid rgba(0,0,0,0.10);
    transition:all 0.25s;
}
.rq-step-dot.active {
    background:linear-gradient(135deg,var(--red),var(--red2));
    color:#fff; border-color:var(--red);
    box-shadow:0 4px 14px rgba(139,0,0,0.30);
}
.rq-step-dot.done { background:#22c55e; border-color:#22c55e; color:#fff; box-shadow:0 4px 12px rgba(34,197,94,0.25); }
.rq-step-lbl {
    font-size:0.67rem; font-weight:700; margin-top:5px; text-align:center;
    color:rgba(0,0,0,0.35); text-transform:uppercase; letter-spacing:0.3px;
}
.rq-step-lbl.active { color:var(--red); }
.rq-step-lbl.done   { color:#15803d; }
.rq-step-connector {
    flex:1; height:2px; background:rgba(0,0,0,0.08);
    margin-top:-22px; position:relative; z-index:1;
    max-width:80px;
}
.rq-step-connector.done { background:#22c55e; }

/* ── Type cards ── */
.rq-type-grid {
    display:grid; grid-template-columns:repeat(3,1fr); gap:14px;
    margin-bottom:0;
}
@media(max-width:600px){ .rq-type-grid { grid-template-columns:1fr; } }
.rq-type-card {
    background:rgba(255,255,255,0.68);
    backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
    border:2px solid rgba(0,0,0,0.08); border-radius:18px;
    padding:20px 18px 16px; cursor:pointer;
    transition:all 0.20s; user-select:none; position:relative;
    overflow:hidden;
}
.rq-type-card::before {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(139,0,0,0.04),transparent);
    opacity:0; transition:opacity 0.20s;
}
.rq-type-card:hover { border-color:rgba(139,0,0,0.22); transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,0.09); }
.rq-type-card:hover::before { opacity:1; }
.rq-type-card.selected {
    border-color:var(--red) !important;
    background:rgba(139,0,0,0.04) !important;
    box-shadow:0 0 0 4px rgba(139,0,0,0.09), 0 8px 24px rgba(139,0,0,0.12) !important;
    transform:translateY(-2px);
}
.rq-type-card.selected::before { opacity:1; }
.rq-type-check {
    position:absolute; top:12px; right:12px;
    width:20px; height:20px; border-radius:50%;
    background:var(--red); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:0.60rem; opacity:0; transform:scale(0.5);
    transition:all 0.20s;
}
.rq-type-card.selected .rq-type-check { opacity:1; transform:scale(1); }
.rq-type-card input[type=radio] { display:none; }
.rq-type-icon {
    width:48px; height:48px; border-radius:14px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.2rem; margin-bottom:12px;
}
.rq-type-card h6 { font-size:0.93rem; font-weight:800; color:#1a1d23; margin:0 0 5px; }
.rq-type-card p  { font-size:0.76rem; color:rgba(0,0,0,0.42); margin:0; line-height:1.4; }

/* ── Layout wrapper ── */
.rq-layout { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
@media(max-width:900px){ .rq-layout { grid-template-columns:1fr; } }

/* ── Form card ── */
.rq-form-card {
    background:rgba(255,255,255,0.74);
    backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    border:1px solid rgba(0,0,0,0.07); border-radius:20px;
    box-shadow:0 4px 24px rgba(0,0,0,0.07);
    overflow:hidden;
}
.rq-form-head {
    padding:18px 24px 16px;
    border-bottom:1px solid rgba(0,0,0,0.06);
    display:flex; align-items:center; gap:10px;
}
.rq-form-head-icon {
    width:36px; height:36px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:0.95rem; flex-shrink:0;
}
.rq-form-head-title { font-size:0.95rem; font-weight:800; color:#1a1d23; margin:0; }
.rq-form-head-sub   { font-size:0.75rem; color:rgba(0,0,0,0.40); margin:1px 0 0; }
.rq-form-body { padding:22px 24px; }

.rq-section-title {
    font-size:0.70rem; font-weight:800; text-transform:uppercase;
    letter-spacing:0.7px; color:rgba(0,0,0,0.36);
    margin-bottom:14px; display:flex; align-items:center; gap:6px;
}
.rq-section-title::after {
    content:''; flex:1; height:1px; background:rgba(0,0,0,0.07);
}
.rq-divider { border-color:rgba(0,0,0,0.07); margin:20px 0; }

/* Improved form controls */
.rq-field { margin-bottom:18px; }
.rq-field label {
    font-size:0.80rem; font-weight:700; color:rgba(0,0,0,0.60);
    margin-bottom:6px; display:flex; align-items:center; gap:5px;
}
.rq-field label .rq-req { color:var(--red); font-size:0.75rem; }
.rq-input-wrap { position:relative; }
.rq-input-icon {
    position:absolute; left:12px; top:50%; transform:translateY(-50%);
    color:rgba(0,0,0,0.30); font-size:0.80rem; pointer-events:none;
}
.rq-input-icon-ta { top:14px; transform:none; }
.rq-input-wrap .form-control,
.rq-input-wrap .form-select {
    border-radius:11px !important; border:1.5px solid rgba(0,0,0,0.10) !important;
    font-size:0.88rem !important; padding:10px 12px 10px 36px !important;
    background:rgba(255,255,255,0.80) !important;
    transition:border-color 0.15s, box-shadow 0.15s !important;
}
.rq-input-wrap .form-control:focus,
.rq-input-wrap .form-select:focus {
    border-color:var(--red) !important;
    box-shadow:0 0 0 3px rgba(139,0,0,0.09) !important; outline:none !important;
}
/* fields without icon */
.form-control.rq-no-icon, .form-select.rq-no-icon {
    border-radius:11px !important; border:1.5px solid rgba(0,0,0,0.10) !important;
    font-size:0.88rem !important;
    background:rgba(255,255,255,0.80) !important;
    transition:border-color 0.15s, box-shadow 0.15s !important;
}
.form-control.rq-no-icon:focus, .form-select.rq-no-icon:focus {
    border-color:var(--red) !important;
    box-shadow:0 0 0 3px rgba(139,0,0,0.09) !important; outline:none !important;
}
.rq-input-wrap textarea.form-control { padding-top:10px !important; }

/* Urgency cards */
.urgency-group { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
@media(max-width:500px){ .urgency-group { grid-template-columns:repeat(2,1fr); } }
.urgency-pill {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:5px; padding:10px 8px; border-radius:12px;
    border:1.5px solid rgba(0,0,0,0.09);
    background:rgba(255,255,255,0.60); cursor:pointer;
    font-size:0.78rem; font-weight:700; color:rgba(0,0,0,0.50);
    transition:all 0.15s; user-select:none; text-align:center;
}
.urgency-pill input[type=radio] { display:none; }
.urgency-pill i { font-size:1rem; }
.urgency-pill:hover { border-color:rgba(0,0,0,0.20); color:#1a1d23; transform:translateY(-1px); }
.urgency-pill.selected-low      { background:rgba(34,197,94,0.12);  border-color:#22c55e; color:#15803d; }
.urgency-pill.selected-medium   { background:rgba(59,130,246,0.12); border-color:#3b82f6; color:#1d4ed8; }
.urgency-pill.selected-high     { background:rgba(245,158,11,0.12); border-color:#f59e0b; color:#b45309; }
.urgency-pill.selected-critical { background:rgba(239,68,68,0.12);  border-color:#ef4444; color:#b91c1c; }

/* ── Summary sidebar ── */
.rq-summary-card {
    background:rgba(255,255,255,0.72);
    backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
    border:1px solid rgba(0,0,0,0.07); border-radius:20px;
    box-shadow:0 4px 24px rgba(0,0,0,0.07);
    overflow:hidden; position:sticky; top:88px;
}
.rq-summary-head {
    background:linear-gradient(135deg,var(--red),var(--red2));
    padding:16px 18px; color:#fff;
}
.rq-summary-head-title { font-size:0.88rem; font-weight:800; margin:0; }
.rq-summary-head-sub   { font-size:0.72rem; opacity:0.80; margin:2px 0 0; }
.rq-summary-body { padding:16px 18px; }
.rq-summary-row {
    display:flex; align-items:flex-start; gap:8px;
    padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.06);
    font-size:0.80rem;
}
.rq-summary-row:last-child { border-bottom:none; }
.rq-summary-icon {
    width:26px; height:26px; border-radius:7px;
    background:rgba(139,0,0,0.08); color:var(--red);
    display:flex; align-items:center; justify-content:center;
    font-size:0.68rem; flex-shrink:0; margin-top:1px;
}
.rq-summary-label { font-size:0.67rem; font-weight:700; color:rgba(0,0,0,0.38); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:2px; }
.rq-summary-val   { font-weight:600; color:#1a1d23; }
.rq-summary-empty { font-size:0.75rem; color:rgba(0,0,0,0.30); font-style:italic; }
.rq-summary-tip {
    font-size:0.74rem; color:rgba(0,0,0,0.42); line-height:1.5;
    background:rgba(0,0,0,0.03); border-radius:10px; padding:10px 12px;
    margin-top:12px;
}

/* Buttons */
.rq-submit-btn {
    background:linear-gradient(135deg,var(--red),var(--red2)) !important;
    border:none !important; border-radius:12px !important;
    font-weight:700 !important; font-size:0.90rem !important;
    color:#fff !important; padding:12px 28px !important;
    box-shadow:0 4px 14px rgba(139,0,0,0.28) !important;
    transition:transform 0.15s, box-shadow 0.15s !important;
}
.rq-submit-btn:hover {
    transform:translateY(-2px) !important;
    box-shadow:0 8px 22px rgba(139,0,0,0.35) !important;
}
.rq-cancel-btn {
    background:rgba(0,0,0,0.05) !important; border:1.5px solid rgba(0,0,0,0.10) !important;
    border-radius:12px !important; font-weight:600 !important;
    color:rgba(0,0,0,0.50) !important; padding:12px 22px !important;
    transition:all 0.15s !important;
}
.rq-cancel-btn:hover { background:rgba(0,0,0,0.09) !important; color:#1a1d23 !important; }

/* Info chip for read-only desc */
.rq-desc-chip {
    background:rgba(139,0,0,0.05); border:1px solid rgba(139,0,0,0.12);
    border-radius:10px; padding:9px 14px;
    font-size:0.82rem; color:rgba(0,0,0,0.55); line-height:1.45;
    display:flex; align-items:flex-start; gap:7px;
}
/* ── Cart ── */
.rq-cart-area { margin-top:6px; }
.rq-cart-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.rq-cart-badge { background:rgba(139,0,0,0.10); color:#8B0000; border-radius:20px; padding:2px 10px; font-size:0.72rem; font-weight:800; }
.rq-cart-empty { text-align:center; padding:20px 12px; color:rgba(0,0,0,0.30); font-size:0.81rem; background:rgba(0,0,0,0.02); border:1.5px dashed rgba(0,0,0,0.10); border-radius:12px; }
.rq-cart-item { display:flex; align-items:flex-start; gap:10px; background:rgba(255,255,255,0.85); border:1px solid rgba(0,0,0,0.08); border-radius:12px; padding:11px 13px; margin-bottom:8px; }
.rq-cart-item-num { width:24px; height:24px; border-radius:7px; flex-shrink:0; background:linear-gradient(135deg,#8B0000,#b91c1c); color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.68rem; font-weight:800; }
.rq-cart-item-body { flex:1; min-width:0; }
.rq-cart-item-name { font-weight:700; color:#1a1d23; font-size:0.87rem; margin-bottom:3px; }
.rq-cart-item-meta { font-size:0.75rem; color:rgba(0,0,0,0.44); display:flex; flex-wrap:wrap; gap:6px; }
.rq-cart-remove { padding:3px 9px; border-radius:7px; border:none; background:rgba(239,68,68,0.09); color:#dc2626; font-size:0.72rem; font-weight:700; cursor:pointer; flex-shrink:0; }
.rq-cart-remove:hover { background:rgba(239,68,68,0.20); }
.rq-add-btn { display:flex; align-items:center; justify-content:center; gap:7px; width:100%; padding:10px; border-radius:11px; margin-top:10px; border:2px dashed rgba(139,0,0,0.25); background:rgba(139,0,0,0.03); color:#8B0000; font-size:0.84rem; font-weight:700; cursor:pointer; transition:all 0.15s; }
.rq-add-btn:hover { background:rgba(139,0,0,0.08); border-color:rgba(139,0,0,0.50); }
.rq-cart-error { display:none; color:#dc2626; font-size:0.80rem; font-weight:600; margin-top:8px; padding:8px 12px; background:rgba(239,68,68,0.08); border-radius:9px; }
.rq-sum-preview-item { display:flex; align-items:flex-start; gap:7px; padding:5px 0; }
.rq-sum-preview-num { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:5px; background:rgba(139,0,0,0.12); color:#8B0000; font-size:0.60rem; font-weight:800; flex-shrink:0; margin-top:1px; }
/* ── Approval letter upload ── */
.rq-upload-area { border:2px dashed rgba(139,0,0,0.25); border-radius:14px; padding:22px 18px; text-align:center; background:rgba(139,0,0,0.025); cursor:pointer; transition:all 0.18s; position:relative; }
.rq-upload-area:hover, .rq-upload-area.drag-over { background:rgba(139,0,0,0.06); border-color:rgba(139,0,0,0.55); }
.rq-upload-area input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.rq-upload-icon { font-size:2rem; color:rgba(139,0,0,0.30); margin-bottom:8px; }
.rq-upload-label { font-size:0.87rem; font-weight:700; color:#1a1d23; margin-bottom:3px; }
.rq-upload-sub { font-size:0.76rem; color:rgba(0,0,0,0.42); }
.rq-upload-preview { display:none; align-items:center; gap:12px; background:rgba(255,255,255,0.90); border:1px solid rgba(0,0,0,0.09); border-radius:12px; padding:12px 14px; margin-top:10px; }
.rq-upload-preview-icon { width:38px; height:38px; border-radius:10px; background:rgba(139,0,0,0.09); color:#8B0000; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
.rq-upload-preview-name { font-size:0.84rem; font-weight:700; color:#1a1d23; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
.rq-upload-preview-size { font-size:0.72rem; color:rgba(0,0,0,0.40); }
.rq-upload-remove { margin-left:auto; padding:4px 10px; border-radius:7px; border:none; background:rgba(239,68,68,0.09); color:#dc2626; font-size:0.72rem; font-weight:700; cursor:pointer; flex-shrink:0; }
.rq-upload-remove:hover { background:rgba(239,68,68,0.20); }
.rq-upload-error { display:none; color:#dc2626; font-size:0.80rem; font-weight:600; margin-top:8px; padding:8px 12px; background:rgba(239,68,68,0.08); border-radius:9px; }
.rq-upload-view { margin-left:auto; padding:4px 10px; border-radius:7px; border:none; background:rgba(139,0,0,0.09); color:#8B0000; font-size:0.72rem; font-weight:700; cursor:pointer; flex-shrink:0; }
.rq-upload-view:hover { background:rgba(139,0,0,0.18); }
/* Document view modal */
#docViewModal { position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.70); display:flex; align-items:center; justify-content:center; padding:16px; visibility:hidden; opacity:0; transition:visibility 0.2s, opacity 0.2s; pointer-events:none; }
#docViewModal.open { visibility:visible; opacity:1; pointer-events:auto; }
.doc-modal-box { background:#fff; border-radius:18px; width:100%; max-width:780px; max-height:90vh; margin:auto; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,0.35); }
.doc-modal-header { display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid rgba(0,0,0,0.08); flex-shrink:0; }
.doc-modal-title { flex:1; font-size:0.92rem; font-weight:800; color:#1a1d23; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.doc-modal-close { width:32px; height:32px; border-radius:9px; border:none; background:rgba(0,0,0,0.06); color:rgba(0,0,0,0.50); font-size:0.90rem; cursor:pointer; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
.doc-modal-close:hover { background:rgba(239,68,68,0.12); color:#dc2626; }
.doc-modal-body { flex:1; overflow:auto; background:#f4f4f4; display:flex; align-items:center; justify-content:center; min-height:300px; }
.doc-modal-body iframe { width:100%; height:70vh; border:none; }
.doc-modal-body img { max-width:100%; max-height:70vh; object-fit:contain; display:block; }
</style>

<div class="container-fluid mt-4 pb-4">

    <?php if (isset($error)): ?>
    <div class="alert alert-danger mb-4" style="border-radius:14px;">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Step Indicator -->
    <div class="rq-steps mb-4">
        <div class="rq-step-item">
            <div class="rq-step-dot done" id="sdot1"><i class="fas fa-check" style="font-size:0.65rem;"></i></div>
            <div class="rq-step-lbl done" id="slbl1">Request Type</div>
        </div>
        <div class="rq-step-connector done" id="scon1"></div>
        <div class="rq-step-item">
            <div class="rq-step-dot active" id="sdot2">2</div>
            <div class="rq-step-lbl active" id="slbl2">Urgency</div>
        </div>
        <div class="rq-step-connector" id="scon2"></div>
        <div class="rq-step-item">
            <div class="rq-step-dot" id="sdot3">3</div>
            <div class="rq-step-lbl" id="slbl3">Details</div>
        </div>
    </div>

    <!-- Step 1 — Type Selection -->
    <div id="step1_section">
        <div class="rq-type-grid mb-4">
            <label class="rq-type-card selected" onclick="selectType(this,'borrow')">
                <input type="radio" name="_type_vis" value="borrow" checked>
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="background:rgba(59,130,246,0.12);color:#1d4ed8;">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <h6>Borrow Item</h6>
                <p>Temporarily borrow an item from campus inventory</p>
            </label>
            <label class="rq-type-card" onclick="selectType(this,'item')">
                <input type="radio" name="_type_vis" value="item">
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="background:rgba(34,197,94,0.12);color:#15803d;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6>Request Item</h6>
                <p>Request a new item to be procured or purchased</p>
            </label>
            <label class="rq-type-card" onclick="selectType(this,'service')">
                <input type="radio" name="_type_vis" value="service">
                <div class="rq-type-check"><i class="fas fa-check"></i></div>
                <div class="rq-type-icon" style="background:rgba(245,158,11,0.12);color:#b45309;">
                    <i class="fas fa-tools"></i>
                </div>
                <h6>Request Service</h6>
                <p>Request maintenance, repair or inspection</p>
            </label>
        </div>
    </div>

    <!-- Form + Sidebar -->
    <div class="rq-layout">

        <!-- Form Card -->
        <div class="rq-form-card">
            <!-- Dynamic head -->
            <div class="rq-form-head" id="formHead">
                <div class="rq-form-head-icon" id="formHeadIcon"
                     style="background:rgba(59,130,246,0.12);color:#1d4ed8;">
                    <i class="fas fa-hand-holding"></i>
                </div>
                <div>
                    <div class="rq-form-head-title" id="formHeadTitle">Borrow Item</div>
                    <div class="rq-form-head-sub"  id="formHeadSub">Fill in the details for your borrow request</div>
                </div>
            </div>

            <div class="rq-form-body">
            <form method="POST" action="" id="requestForm" novalidate enctype="multipart/form-data">
                <input type="hidden" id="request_type_hidden" name="request_type" value="borrow">

                <!-- Urgency -->
                <div class="rq-section-title"><i class="fas fa-bolt"></i> Priority Level</div>
                <div class="urgency-group mb-4">
                    <?php
                    $urgencies = [
                        'low'      => ['label' => 'Low',      'icon' => 'fa-arrow-down',   'class' => 'selected-low'],
                        'medium'   => ['label' => 'Medium',   'icon' => 'fa-minus',        'class' => 'selected-medium'],
                        'high'     => ['label' => 'High',     'icon' => 'fa-arrow-up',     'class' => 'selected-high'],
                        'critical' => ['label' => 'Critical', 'icon' => 'fa-exclamation',  'class' => 'selected-critical'],
                    ];
                    foreach ($urgencies as $val => $u): ?>
                    <label class="urgency-pill <?php echo $val === 'medium' ? 'selected-medium' : ''; ?>"
                           data-urgency="<?php echo $val; ?>"
                           onclick="selectUrgency(this,'<?php echo $u['class']; ?>')">
                        <input type="radio" name="urgency" value="<?php echo $val; ?>" <?php echo $val === 'medium' ? 'checked' : ''; ?>>
                        <i class="fas <?php echo $u['icon']; ?>"></i>
                        <?php echo $u['label']; ?>
                    </label>
                    <?php endforeach; ?>
                </div>

                <hr class="rq-divider">

                <!-- BORROW FIELDS -->
                <div id="borrow_fields">
                    <div class="rq-section-title"><i class="fas fa-hand-holding"></i> Borrow Details</div>

                    <div class="rq-field">
                        <label>Item to Borrow <span class="rq-req">*</span><?php if ($auto_fill_item): ?> <span style="font-size:0.65rem; font-weight:700; background:rgba(34,197,94,0.12); color:#15803d; padding:2px 8px; border-radius:12px; border:1px solid rgba(34,197,94,0.22); margin-left:6px;"><i class="fas fa-check-circle me-1"></i>Pre-selected</span><?php endif; ?></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-box rq-input-icon"></i>
                            <select class="form-select" id="borrow_catalog_select" name="borrow_item_name"
                                    onchange="handleCatalogChange(this); updateSummary();" required>
                                <option value="">— Select an item —</option>
                                <?php 
                                if (count($catalog_by_category) > 0) {
                                    foreach ($catalog_by_category as $category => $items): 
                                        if (count($items) > 0):
                                ?>
                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                    <?php foreach ($items as $ci): ?>
                                    <option value="<?php echo htmlspecialchars($ci['item_name']); ?>" data-item-id="<?php echo $ci['id']; ?>" <?php if ($auto_fill_item && $ci['item_name'] === $auto_fill_item) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($ci['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php 
                                        endif;
                                    endforeach; 
                                }
                                ?>
                                <option value="__custom__">✏️ Other (specify custom item)</option>
                            </select>
                        </div>
                    </div>

                    <div class="rq-field" id="custom_item_wrap" style="display:none;">
                        <label>Custom Item Name <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-pen rq-input-icon"></i>
                            <input type="text" class="form-control" id="custom_item_name"
                                   name="custom_item_name" placeholder="e.g., Portable Bluetooth Speaker"
                                   oninput="updateSummary()">
                        </div>
                        <div class="form-text mt-1" style="font-size:0.73rem;">Be as specific as possible.</div>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-sm-6">
                            <div class="rq-field mb-0">
                                <label>Expected Return Date <span class="rq-req">*</span></label>
                                <div class="rq-input-wrap">
                                    <i class="fas fa-calendar rq-input-icon"></i>
                                    <input type="date" class="form-control" id="expected_return_date"
                                           name="expected_return_date" required onchange="updateSummary()">
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="rq-field mb-0">
                                <label>Quantity <span class="rq-req">*</span></label>
                                <div class="rq-input-wrap">
                                    <i class="fas fa-hashtag rq-input-icon"></i>
                                    <input type="number" class="form-control" id="borrow_quantity"
                                           name="borrow_quantity" value="1" min="1" required
                                           oninput="updateSummary()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rq-field mt-3">
                        <label>Reason for Borrowing</label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-comment rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="reason" name="reason" rows="3"
                                      placeholder="Briefly explain why you need this item"
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ITEM REQUEST FIELDS -->
                <div id="item_fields" style="display:none;">
                    <div class="rq-section-title"><i class="fas fa-shopping-cart"></i> Item Request Details</div>

                    <div class="rq-field">
                        <label>Item Name <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-box rq-input-icon"></i>
                            <select class="form-select" id="item_description" name="item_description"
                                    onchange="handleItemCatalogChange(this); updateSummary();">
                                <option value="">— Select an item —</option>
                                <?php foreach ($catalog_by_category as $category => $items): ?>
                                <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                    <?php foreach ($items as $ci): ?>
                                    <option value="<?php echo htmlspecialchars($ci['name']); ?>"
                                            data-desc="<?php echo htmlspecialchars($ci['description']); ?>">
                                        <?php echo htmlspecialchars($ci['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                                <option value="__custom__">✏️ Other (specify custom item)</option>
                            </select>
                        </div>
                    </div>

                    <div class="rq-field" id="custom_item_req_wrap" style="display:none;">
                        <label>Custom Item Name <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-pen rq-input-icon"></i>
                            <input type="text" class="form-control" id="custom_item_req_name"
                                   name="custom_item_req_name"
                                   placeholder="e.g., Canon Projector, Dell Desktop Computer"
                                   oninput="updateSummary()">
                        </div>
                    </div>

                    <div class="rq-field" id="item_desc_display_wrap" style="display:none;">
                        <label>Item Description</label>
                        <div class="rq-desc-chip" id="item_desc_chip">
                            <i class="fas fa-info-circle" style="color:var(--red);flex-shrink:0;margin-top:2px;"></i>
                            <span id="item_desc_display_text"></span>
                        </div>
                        <input type="hidden" id="item_desc_display" name="item_desc_detail">
                    </div>

                    <div class="rq-field" style="max-width:160px;">
                        <label>Quantity <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-hashtag rq-input-icon"></i>
                            <input type="number" class="form-control" id="quantity" name="quantity"
                                   value="1" min="1" oninput="updateSummary()">
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Reason for Request <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-comment rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="item_reason" name="reason" rows="3"
                                      placeholder="Explain why this item is needed, where it will be used, etc."
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- SERVICE FIELDS -->
                <div id="service_fields" style="display:none;">
                    <div class="rq-section-title"><i class="fas fa-tools"></i> Service Request Details</div>

                    <div class="rq-field">
                        <label>Item Requiring Service <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-cube rq-input-icon"></i>
                            <select class="form-select" id="item_id" name="item_id"
                                    onchange="updateSummary()">
                                <option value="">— Select an item —</option>
                                <?php foreach ($inventory_items as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Type of Service <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-wrench rq-input-icon"></i>
                            <select class="form-select" id="service_type" name="service_type"
                                    onchange="updateSummary()">
                                <option value="">— Select service type —</option>
                                <option value="repair">🔧 Repair</option>
                                <option value="maintenance">🛠️ Maintenance</option>
                                <option value="inspection">🔍 Inspection</option>
                                <option value="cleaning">🧹 Cleaning</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="rq-field">
                        <label>Service Description <span class="rq-req">*</span></label>
                        <div class="rq-input-wrap">
                            <i class="fas fa-align-left rq-input-icon rq-input-icon-ta"></i>
                            <textarea class="form-control" id="service_description"
                                      name="service_description" rows="3"
                                      placeholder="Describe the issue or service needed in detail."
                                      oninput="updateSummary()"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ─── CART / ITEMS LIST ─── -->
                <div id="rq-cart-area" class="rq-cart-area">
                    <div class="rq-cart-header">
                        <div class="rq-section-title" style="margin-bottom:0;flex:1;"><i class="fas fa-list-ul"></i> Items in This Request</div>
                        <span id="cart-count-badge" class="rq-cart-badge">0</span>
                    </div>
                    <div id="rq-cart-list">
                        <div class="rq-cart-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.22;"></i>No items added yet. Fill in the details above and click <strong>Add to List</strong>.</div>
                    </div>
                    <div id="cart-error" class="rq-cart-error"></div>
                    <button type="button" class="rq-add-btn" onclick="addToCart()">
                        <i class="fas fa-plus-circle"></i> Add to List
                    </button>
                </div>

                <hr class="rq-divider">

                <!-- RECEIVING METHOD -->
                <div id="receiving_method_section">
                    <div class="rq-section-title"><i class="fas fa-truck"></i> Receiving Method</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px;" id="receiving_method_grid">
                        <label class="rq-type-card selected" id="rm-delivery" onclick="selectReceivingMethod('delivery',this)" style="cursor:pointer;">
                            <input type="radio" name="receiving_method" value="delivery" checked>
                            <div class="rq-type-check"><i class="fas fa-check"></i></div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="rq-type-icon" style="background:rgba(59,130,246,0.12);color:#1d4ed8;width:38px;height:38px;min-width:38px;font-size:1rem;border-radius:10px;margin-bottom:0;">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div>
                                    <h6 style="margin:0 0 2px;font-size:0.88rem;">Delivery</h6>
                                    <p style="margin:0;font-size:0.74rem;color:rgba(0,0,0,0.42);">Item delivered to your location</p>
                                </div>
                            </div>
                        </label>
                        <label class="rq-type-card" id="rm-pickup" onclick="selectReceivingMethod('pickup',this)" style="cursor:pointer;">
                            <input type="radio" name="receiving_method" value="pickup">
                            <div class="rq-type-check"><i class="fas fa-check"></i></div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="rq-type-icon" style="background:rgba(34,197,94,0.12);color:#15803d;width:38px;height:38px;min-width:38px;font-size:1rem;border-radius:10px;margin-bottom:0;">
                                    <i class="fas fa-walking"></i>
                                </div>
                                <div>
                                    <h6 style="margin:0 0 2px;font-size:0.88rem;">Pickup</h6>
                                    <p style="margin:0;font-size:0.74rem;color:rgba(0,0,0,0.42);">Pick up the item yourself</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <hr class="rq-divider">
                <!-- ─── APPROVAL LETTER UPLOAD ─── -->
                <div class="rq-section-title"><i class="fas fa-file-signature"></i> Approval Letter <span class="rq-req">*</span></div>
                <p style="font-size:0.80rem;color:rgba(0,0,0,0.45);margin:-6px 0 12px;">Upload the signed approval letter from your office/college/department head. Required before your request can be submitted.</p>
                <div class="rq-upload-area" id="upload-drop-zone">
                    <input type="file" id="approval_letter" name="approval_letter"
                           accept=".pdf,.jpg,.jpeg,.png"
                           onchange="handleLetterUpload(this)">
                    <div id="upload-placeholder">
                        <div class="rq-upload-icon"><i class="fas fa-file-upload"></i></div>
                        <div class="rq-upload-label">Click or drag &amp; drop to upload</div>
                        <div class="rq-upload-sub">PDF, JPG, or PNG &nbsp;·&nbsp; Max 5 MB</div>
                    </div>
                </div>
                <div class="rq-upload-preview" id="upload-preview">
                    <div class="rq-upload-preview-icon" id="upload-preview-icon"><i class="fas fa-file-pdf"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="rq-upload-preview-name" id="upload-preview-name"></div>
                        <div class="rq-upload-preview-size" id="upload-preview-size"></div>
                    </div>
                    <button type="button" class="rq-upload-view" onclick="openUploadModal()"><i class="fas fa-eye me-1"></i>View</button>
                    <button type="button" class="rq-upload-remove" onclick="removeUpload()"><i class="fas fa-times me-1"></i>Remove</button>
                </div>

                <div class="rq-upload-error" id="upload-error"></div>

                <hr class="rq-divider">
                <input type="hidden" id="items_json" name="items_json">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn rq-submit-btn">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <a href="dashboard.php" class="btn rq-cancel-btn">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
            </div><!-- /.rq-form-body -->
        </div><!-- /.rq-form-card -->

        <!-- Summary Sidebar -->
        <div class="rq-summary-card">
            <div class="rq-summary-head">
                <div class="rq-summary-head-title"><i class="fas fa-clipboard-list me-2"></i>Request Summary</div>
                <div class="rq-summary-head-sub">Preview before submitting</div>
            </div>
            <div class="rq-summary-body">
                <div class="rq-summary-row">
                    <div class="rq-summary-icon"><i class="fas fa-tag"></i></div>
                    <div>
                        <div class="rq-summary-label">Type</div>
                        <div class="rq-summary-val" id="sum_type">Borrow Item</div>
                    </div>
                </div>
                <div class="rq-summary-row">
                    <div class="rq-summary-icon"><i class="fas fa-bolt"></i></div>
                    <div>
                        <div class="rq-summary-label">Priority</div>
                        <div class="rq-summary-val" id="sum_urgency">Medium</div>
                    </div>
                </div>
                <div class="rq-summary-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                    <div style="display:flex;align-items:center;gap:6px;width:100%;">
                        <div class="rq-summary-icon"><i class="fas fa-list-ul"></i></div>
                        <div style="flex:1;">
                            <div class="rq-summary-label">Items</div>
                            <div class="rq-summary-val" id="sum_items_count"><span class="rq-summary-empty">None</span></div>
                        </div>
                    </div>
                    <div id="sum_items_list" style="width:100%;padding-left:34px;"></div>
                </div>
                <div class="rq-summary-tip">
                    <i class="fas fa-info-circle me-1" style="color:var(--red);"></i>
                    Requests are reviewed by the admin. You will be notified once a decision is made.
                </div>
            </div>
        </div>

    </div><!-- /.rq-layout -->
</div>
</div>

<script>
/* ── Type selection ── */
const typeConfig = {
    borrow:  { title:'Borrow Item',      sub:'Fill in the details for your borrow request',       icon:'fa-hand-holding', bg:'rgba(59,130,246,0.12)',  color:'#1d4ed8', label:'Borrow Item'      },
    item:    { title:'Request Item',     sub:'Specify the item you need procured',                 icon:'fa-shopping-cart',bg:'rgba(34,197,94,0.12)',   color:'#15803d', label:'Request Item'     },
    service: { title:'Request Service',  sub:'Describe the maintenance or repair needed',          icon:'fa-tools',        bg:'rgba(245,158,11,0.12)',  color:'#b45309', label:'Request Service'  },
};

function selectType(card, type) {
    if (cart.length > 0) { cart = []; renderCart(); hideCartError(); }
    document.querySelectorAll('.rq-type-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    document.getElementById('request_type_hidden').value = type;
    const cfg = typeConfig[type];
    document.getElementById('formHeadIcon').style.background = cfg.bg;
    document.getElementById('formHeadIcon').style.color      = cfg.color;
    document.getElementById('formHeadIcon').innerHTML = '<i class="fas ' + cfg.icon + '"></i>';
    document.getElementById('formHeadTitle').textContent = cfg.title;
    document.getElementById('formHeadSub').textContent   = cfg.sub;
    updateRequestType(type);
    updateSummary();
}

function selectUrgency(pill, selectedClass) {
    document.querySelectorAll('.urgency-pill').forEach(p => {
        p.classList.remove('selected-low','selected-medium','selected-high','selected-critical');
    });
    pill.classList.add(selectedClass);
    pill.querySelector('input[type=radio]').checked = true;
    updateSummary();
}

function updateRequestType(type) {
    ['borrow_fields','item_fields','service_fields'].forEach(id => {
        document.getElementById(id).style.display = 'none';
    });
    ['borrow_catalog_select','expected_return_date','borrow_quantity','item_description','quantity','item_id','service_type','service_description'].forEach(id => {
        document.getElementById(id).removeAttribute('required');
    });
    // Hide receiving method for service requests (not applicable)
    document.getElementById('receiving_method_section').style.display = type === 'service' ? 'none' : 'block';
    if (type === 'borrow') {
        document.getElementById('borrow_fields').style.display = 'block';
        document.getElementById('borrow_catalog_select').setAttribute('required','required');
        document.getElementById('expected_return_date').setAttribute('required','required');
        document.getElementById('borrow_quantity').setAttribute('required','required');
    } else if (type === 'item') {
        document.getElementById('item_fields').style.display = 'block';
        document.getElementById('item_description').setAttribute('required','required');
        document.getElementById('quantity').setAttribute('required','required');
    } else if (type === 'service') {
        document.getElementById('service_fields').style.display = 'block';
        document.getElementById('item_id').setAttribute('required','required');
        document.getElementById('service_type').setAttribute('required','required');
        document.getElementById('service_description').setAttribute('required','required');
    }
}

function selectReceivingMethod(method, el) {
    document.getElementById('rm-delivery').classList.remove('selected');
    document.getElementById('rm-pickup').classList.remove('selected');
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}

/* ── Catalog selects ── */
function handleCatalogChange(select) {
    var wrap  = document.getElementById('custom_item_wrap');
    var input = document.getElementById('custom_item_name');
    if (select.value === '__custom__') {
        wrap.style.display = 'block'; input.setAttribute('required','required');
        select.removeAttribute('required');
    } else {
        wrap.style.display = 'none'; input.removeAttribute('required'); input.value = '';
        select.setAttribute('required','required');
    }
}
function handleItemCatalogChange(select) {
    var customWrap  = document.getElementById('custom_item_req_wrap');
    var customInput = document.getElementById('custom_item_req_name');
    var descWrap    = document.getElementById('item_desc_display_wrap');
    var descInput   = document.getElementById('item_desc_display');
    var descText    = document.getElementById('item_desc_display_text');
    if (select.value === '__custom__') {
        customWrap.style.display = 'block'; customInput.setAttribute('required','required');
        descWrap.style.display = 'none'; descInput.value = '';
        select.removeAttribute('required');
    } else if (select.value !== '') {
        customWrap.style.display = 'none'; customInput.removeAttribute('required'); customInput.value = '';
        select.setAttribute('required','required');
        var desc = select.options[select.selectedIndex].getAttribute('data-desc');
        if (desc) { descInput.value = desc; descText.textContent = desc; descWrap.style.display = 'block'; }
        else       { descWrap.style.display = 'none'; descInput.value = ''; }
    } else {
        customWrap.style.display = 'none'; customInput.removeAttribute('required'); customInput.value = '';
        descWrap.style.display = 'none'; descInput.value = '';
    }
}

/* ── Cart ── */
var cart = [];

function addToCart() {
    var type = document.getElementById('request_type_hidden').value;
    var entry = {};
    if (type === 'borrow') {
        var sel = document.getElementById('borrow_catalog_select');
        var name = (sel.value && sel.value !== '__custom__') ? sel.value : document.getElementById('custom_item_name').value.trim();
        if (!name) { showCartError('Please select or enter an item to borrow.'); return; }
        var rd = document.getElementById('expected_return_date').value;
        if (!rd) { showCartError('Please enter the expected return date.'); return; }
        var qty = parseInt(document.getElementById('borrow_quantity').value) || 1;
        var reason = document.getElementById('reason').value.trim();
        entry = { type:'borrow', name:name, qty:qty, return_date:rd, reason:reason };
    } else if (type === 'item') {
        var sel2 = document.getElementById('item_description');
        var name2 = (sel2.value && sel2.value !== '__custom__') ? sel2.value : document.getElementById('custom_item_req_name').value.trim();
        if (!name2) { showCartError('Please select or enter an item name.'); return; }
        var qty2 = parseInt(document.getElementById('quantity').value) || 1;
        var reason2 = document.getElementById('item_reason').value.trim();
        entry = { type:'item', name:name2, qty:qty2, reason:reason2 };
    } else {
        var itemSel = document.getElementById('item_id');
        if (!itemSel.value) { showCartError('Please select an item requiring service.'); return; }
        var svcType = document.getElementById('service_type').value;
        if (!svcType) { showCartError('Please select a service type.'); return; }
        var svcDesc = document.getElementById('service_description').value.trim();
        if (!svcDesc) { showCartError('Please describe the service needed.'); return; }
        entry = { type:'service', name:itemSel.options[itemSel.selectedIndex].text, item_id:itemSel.value, service_type:svcType, description:svcDesc };
    }
    cart.push(entry);
    renderCart();
    resetStaging(type);
    updateSummary();
    hideCartError();
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
    updateSummary();
}

function renderCart() {
    var listEl = document.getElementById('rq-cart-list');
    var badge  = document.getElementById('cart-count-badge');
    if (badge) badge.textContent = cart.length;
    if (cart.length === 0) {
        listEl.innerHTML = '<div class="rq-cart-empty"><i class="fas fa-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:0.22;"></i>No items added yet. Fill in the details above and click <strong>Add to List</strong>.</div>';
        return;
    }
    var svcLabels = { repair:'Repair', maintenance:'Maintenance', inspection:'Inspection', cleaning:'Cleaning', other:'Other' };
    var html = '';
    cart.forEach(function(e, i) {
        var meta = [];
        if (e.qty && e.qty > 1) meta.push('Qty: ' + e.qty);
        if (e.return_date) meta.push('Return: ' + new Date(e.return_date + 'T00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
        if (e.service_type) meta.push(svcLabels[e.service_type] || e.service_type);
        if (e.description)  meta.push(e.description.substring(0,40) + (e.description.length > 40 ? '…' : ''));
        if (e.reason)       meta.push(e.reason.substring(0,40) + (e.reason.length > 40 ? '…' : ''));
        html += '<div class="rq-cart-item">' +
            '<div class="rq-cart-item-num">' + (i+1) + '</div>' +
            '<div class="rq-cart-item-body">' +
                '<div class="rq-cart-item-name">' + escHtml(e.name) + '</div>' +
                (meta.length ? '<div class="rq-cart-item-meta">' + meta.map(function(m){ return '<span>' + escHtml(m) + '</span>'; }).join(' · ') + '</div>' : '') +
            '</div>' +
            '<button type="button" class="rq-cart-remove" onclick="removeFromCart(' + i + ')"><i class="fas fa-times"></i></button>' +
        '</div>';
    });
    listEl.innerHTML = html;
}

function resetStaging(type) {
    if (type === 'borrow') {
        document.getElementById('borrow_catalog_select').value = '';
        document.getElementById('custom_item_name').value = '';
        document.getElementById('custom_item_wrap').style.display = 'none';
        document.getElementById('expected_return_date').value = '';
        document.getElementById('borrow_quantity').value = 1;
        document.getElementById('reason').value = '';
    } else if (type === 'item') {
        document.getElementById('item_description').value = '';
        document.getElementById('custom_item_req_name').value = '';
        document.getElementById('custom_item_req_wrap').style.display = 'none';
        document.getElementById('item_desc_display_wrap').style.display = 'none';
        document.getElementById('quantity').value = 1;
        document.getElementById('item_reason').value = '';
    } else {
        document.getElementById('item_id').value = '';
        document.getElementById('service_type').value = '';
        document.getElementById('service_description').value = '';
    }
}

function showCartError(msg) {
    var el = document.getElementById('cart-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; el.scrollIntoView({behavior:'smooth',block:'nearest'}); }
}
function hideCartError() {
    var el = document.getElementById('cart-error');
    if (el) el.style.display = 'none';
}
function escHtml(str) {
    var d = document.createElement('div'); d.textContent = String(str); return d.innerHTML;
}

/* ── Approval letter upload ── */
function handleLetterUpload(input) {
    var file = input.files[0];
    if (!file) return;
    var allowed = ['application/pdf','image/jpeg','image/png'];
    var allowedExt = ['pdf','jpg','jpeg','png'];
    var ext = file.name.split('.').pop().toLowerCase();
    if (!allowedExt.includes(ext)) {
        showUploadError('Only PDF, JPG, and PNG files are accepted.'); input.value = ''; return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showUploadError('File must not exceed 5 MB.'); input.value = ''; return;
    }
    hideUploadError();
    var icon = ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
    document.getElementById('upload-preview-icon').innerHTML = '<i class="fas ' + icon + '"></i>';
    document.getElementById('upload-preview-name').textContent = file.name;
    document.getElementById('upload-preview-size').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('upload-drop-zone').style.display = 'none';
    document.getElementById('upload-preview').style.display = 'flex';
    // Store object URL for preview modal
    if (window._uploadObjectURL) URL.revokeObjectURL(window._uploadObjectURL);
    window._uploadObjectURL = URL.createObjectURL(file);
    window._uploadFileExt   = ext;
    window._uploadFileName  = file.name;
}
function openUploadModal() {
    if (!window._uploadObjectURL) return;
    var body   = document.getElementById('modal-file-body');
    var title  = document.getElementById('modal-file-name');
    var icon   = document.getElementById('modal-file-icon');
    title.textContent = window._uploadFileName;
    icon.innerHTML = '<i class="fas ' + (window._uploadFileExt === 'pdf' ? 'fa-file-pdf' : 'fa-file-image') + '"></i>';
    if (window._uploadFileExt === 'pdf') {
        body.innerHTML = '<iframe src="' + window._uploadObjectURL + '"></iframe>';
    } else {
        body.innerHTML = '<img src="' + window._uploadObjectURL + '" alt="Letter preview">';
    }
    document.getElementById('docViewModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeUploadModal() {
    document.getElementById('docViewModal').classList.remove('open');
    document.getElementById('modal-file-body').innerHTML = '';
    document.body.style.overflow = '';
}
function removeUpload() {
    var inp = document.getElementById('approval_letter');
    inp.value = '';
    if (window._uploadObjectURL) { URL.revokeObjectURL(window._uploadObjectURL); window._uploadObjectURL = null; }
    document.getElementById('upload-drop-zone').style.display = '';
    document.getElementById('upload-preview').style.display = 'none';
}
function showUploadError(msg) {
    var el = document.getElementById('upload-error');
    if (el) { el.textContent = msg; el.style.display = 'block'; }
}
function hideUploadError() {
    var el = document.getElementById('upload-error');
    if (el) el.style.display = 'none';
}
// Drag-and-drop
(function() {
    var zone = document.getElementById('upload-drop-zone');
    if (!zone) return;
    zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', function()  { zone.classList.remove('drag-over'); });
    zone.addEventListener('drop', function(e) {
        e.preventDefault(); zone.classList.remove('drag-over');
        var dt = e.dataTransfer;
        if (dt && dt.files.length) {
            var inp = document.getElementById('approval_letter');
            // Transfer files to the input
            try {
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(dt.files[0]);
                inp.files = dataTransfer.files;
            } catch(ex) {}
            handleLetterUpload(inp);
        }
    });
})();

/* ── Live summary ── */
function updateSummary() {
    var type    = document.getElementById('request_type_hidden').value;
    var urgency = document.querySelector('input[name=urgency]:checked');
    document.getElementById('sum_type').textContent    = typeConfig[type]?.label || '—';
    document.getElementById('sum_urgency').textContent = urgency ? urgency.value.charAt(0).toUpperCase() + urgency.value.slice(1) : '—';

    var sumItemsEl = document.getElementById('sum_items_list');
    var sumCountEl = document.getElementById('sum_items_count');
    if (sumCountEl) sumCountEl.textContent = cart.length ? cart.length + ' item' + (cart.length !== 1 ? 's' : '') : '—';
    if (!sumItemsEl) return;

    if (cart.length === 0) {
        var stagingName = '';
        if (type === 'borrow') {
            var sel = document.getElementById('borrow_catalog_select');
            stagingName = (sel.value && sel.value !== '__custom__') ? sel.value : document.getElementById('custom_item_name').value;
        } else if (type === 'item') {
            var sel2 = document.getElementById('item_description');
            stagingName = (sel2.value && sel2.value !== '__custom__') ? sel2.value : document.getElementById('custom_item_req_name').value;
        } else {
            var sel3 = document.getElementById('item_id');
            stagingName = sel3.value ? sel3.options[sel3.selectedIndex].text : '';
        }
        sumItemsEl.innerHTML = stagingName
            ? '<div class="rq-sum-preview-item"><span class="rq-sum-preview-num">?</span><span style="font-size:0.82rem;font-weight:600;">' + escHtml(stagingName) + '</span> <span style="font-size:0.72rem;color:rgba(0,0,0,0.35);">(not added)</span></div>'
            : '<div style="font-size:0.75rem;color:rgba(0,0,0,0.28);font-style:italic;padding:2px 0;">Nothing staged yet</div>';
    } else {
        var previewHtml = '';
        cart.forEach(function(e, i) {
            previewHtml += '<div class="rq-sum-preview-item">' +
                '<span class="rq-sum-preview-num">' + (i+1) + '</span>' +
                '<span style="font-size:0.82rem;font-weight:600;">' + escHtml(e.name) + '</span>' +
                (e.qty > 1 ? ' <span style="font-size:0.72rem;color:rgba(0,0,0,0.40);">×' + e.qty + '</span>' : '') +
            '</div>';
        });
        sumItemsEl.innerHTML = previewHtml;
    }
}

/* ── Form submit ── */
document.getElementById('requestForm').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        showCartError('Please add at least one item to your request before submitting.');
        document.getElementById('rq-cart-area').scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }
    var letterInput = document.getElementById('approval_letter');
    if (!letterInput || !letterInput.files || !letterInput.files.length) {
        e.preventDefault();
        showUploadError('An approval letter from your office/college/department head is required.');
        document.getElementById('upload-drop-zone').scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }
    document.getElementById('items_json').value = JSON.stringify(cart);
});

// Init
renderCart();
updateSummary();

// Pre-select item in dropdown if coming from inventory page
<?php if ($auto_fill_item): ?>
    const selectElement = document.getElementById('borrow_catalog_select');
    if (selectElement) {
        selectElement.value = '<?php echo htmlspecialchars($auto_fill_item); ?>';
        handleCatalogChange(selectElement);
        
        // Set a default return date (7 days from today)
        const returnDateInput = document.getElementById('expected_return_date');
        if (!returnDateInput.value) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 7);
            const year = tomorrow.getFullYear();
            const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
            const day = String(tomorrow.getDate()).padStart(2, '0');
            returnDateInput.value = `${year}-${month}-${day}`;
        }
        
        updateSummary();
        
        // Scroll to form
        setTimeout(() => {
            selectElement.closest('.rq-form-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    }
<?php endif; ?>
</script>

<!-- Document view modal (outside form/card so position:fixed works correctly) -->
<div id="docViewModal" onclick="if(event.target===this)closeUploadModal()">
    <div class="doc-modal-box">
        <div class="doc-modal-header">
            <div class="rq-upload-preview-icon" id="modal-file-icon" style="width:32px;height:32px;font-size:0.95rem;"><i class="fas fa-file-pdf"></i></div>
            <div class="doc-modal-title" id="modal-file-name"></div>
            <button type="button" class="doc-modal-close" onclick="closeUploadModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="doc-modal-body" id="modal-file-body"></div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
