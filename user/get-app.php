<?php
$page_title = 'Get the App';
require_once dirname(__DIR__) . '/config/functions.php';

requireUser();

$current_user = getCurrentUser();

require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/navbar.php';

// The APK is too large to go through the FTP deploy pipeline (it was taking
// the whole live site down — see .github/workflows/deploy.yml, which
// excludes android_app/** entirely), so it's served straight from GitHub's
// raw content CDN instead of this host. Local file info (size/date) still
// comes from the repo copy since that's what actually gets pushed to GitHub.
$apk_download_url = 'https://raw.githubusercontent.com/aedrianguiriba-cpu/ManageMo/main/android_app/ManageMo.apk';
$apk_full_path = dirname(__DIR__) . '/android_app/ManageMo.apk';
$apk_exists = file_exists($apk_full_path);
$apk_size_mb = $apk_exists ? round(filesize($apk_full_path) / 1048576, 1) : 0;
$apk_modified = $apk_exists ? date('M d, Y', filemtime($apk_full_path)) : '';
?>
<div class="main-wrapper">
<div class="container-fluid mt-4 pb-5" style="max-width:760px;">

    <div style="background:#8B0000;border-radius:16px;padding:36px 32px;color:#fff;display:flex;align-items:center;gap:24px;flex-wrap:wrap;box-shadow:0 6px 20px rgba(139,0,0,0.18);">
        <div style="background:#fff;border-radius:18px;padding:14px;flex-shrink:0;">
            <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo" style="width:64px;height:64px;display:block;object-fit:contain;">
        </div>
        <div style="flex:1;min-width:220px;">
            <div style="font-size:1.5rem;font-weight:900;letter-spacing:-0.3px;">ManageMo Scanner</div>
            <div style="font-size:0.92rem;color:rgba(255,255,255,0.85);margin-top:4px;">
                The companion Android app for scanning and confirming your deliveries on the go.
            </div>
        </div>
    </div>

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px 26px;margin-top:22px;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <?php if ($apk_exists): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="font-weight:800;font-size:1.05rem;color:#1a1d23;">
                    <i class="fas fa-mobile-screen-button me-2" style="color:#8B0000;"></i>Android APK
                </div>
                <div style="font-size:0.82rem;color:#6b7280;margin-top:4px;">
                    <?php echo $apk_size_mb; ?> MB &middot; Updated <?php echo htmlspecialchars($apk_modified); ?>
                </div>
            </div>
            <a href="<?php echo $apk_download_url; ?>" download="ManageMo.apk"
               style="background:#8B0000;color:#fff;font-weight:700;border-radius:10px;padding:12px 26px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:0.95rem;box-shadow:0 2px 8px rgba(139,0,0,0.25);">
                <i class="fas fa-download"></i> Download APK
            </a>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px 0;color:#9ca3af;">
            <i class="fas fa-circle-exclamation" style="font-size:1.6rem;margin-bottom:10px;display:block;"></i>
            The app isn't available for download yet — check back soon.
        </div>
        <?php endif; ?>
    </div>

    <?php if ($apk_exists): ?>
    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:26px;margin-top:18px;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-weight:800;font-size:0.95rem;color:#1a1d23;margin-bottom:14px;">
            <i class="fas fa-list-ol me-2" style="color:#8B0000;"></i>Installation steps
        </div>
        <ol style="font-size:0.87rem;color:#374151;line-height:1.9;padding-left:20px;margin:0;">
            <li>Tap <strong>Download APK</strong> above on your Android phone.</li>
            <li>Once it finishes downloading, open the file from your notification bar or Downloads folder.</li>
            <li>If prompted, allow your browser to <strong>install unknown apps</strong> — this app isn't from the Play Store, so Android requires that permission once.</li>
            <li>Tap <strong>Install</strong>, then open the app and log in with your ManageMo account.</li>
        </ol>
        <div style="margin-top:16px;padding:12px 14px;background:rgba(139,0,0,0.05);border:1px solid rgba(139,0,0,0.15);border-radius:10px;font-size:0.80rem;color:#6b7280;">
            <i class="fas fa-shield-halved me-1" style="color:#8B0000;"></i>
            This app is provided directly by your institution — only download it from this page.
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
