<?php
$current_user = getCurrentUser();
if (!$current_user) return;
$current_page = basename($_SERVER['PHP_SELF']);
$page_labels = [
    'dashboard.php'        => 'Dashboard',
    'inventory.php'        => 'Inventory',
    'inventory-campus.php' => 'Inventory by Campus',
    'requests.php'         => 'Requests',
    'analytics.php'        => 'Analytics',
    'settings.php'         => 'Settings',
    'borrow-records.php'   => 'My Records',
];
$label = $page_labels[$current_page] ?? (isset($page_title) ? $page_title : 'ManageMo');
$settings_url = ($current_user['role'] === 'admin') ? BASE_URL . 'admin/settings.php' : BASE_URL . 'user/settings.php';

$_notif_list  = getNotifications((int)$current_user['id'], 10);
$_notif_unread = count(array_filter($_notif_list, fn($n) => !$n['is_read']));
$_notif_icons = ['info' => 'fa-circle-info', 'success' => 'fa-circle-check', 'warning' => 'fa-triangle-exclamation', 'danger' => 'fa-circle-xmark'];
$_notif_colors = ['info' => '#1d4ed8', 'success' => '#15803d', 'warning' => '#b45309', 'danger' => '#b91c1c'];
$_notif_bgs = ['info' => 'rgba(59,130,246,0.10)', 'success' => 'rgba(34,197,94,0.10)', 'warning' => 'rgba(245,158,11,0.10)', 'danger' => 'rgba(239,68,68,0.10)'];
$_notif_redirect = urlencode($_SERVER['REQUEST_URI'] ?? (BASE_URL . ($current_user['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php')));
?>
<div class="topbar" id="topbar">
    <div class="topbar-left">
        <h5 class="topbar-title"><?php echo htmlspecialchars($label); ?></h5>
    </div>
    <div class="topbar-right">
        <div class="dropdown">
            <div class="topbar-bell" data-bs-toggle="dropdown" aria-expanded="false" role="button" tabindex="0"
                 style="position:relative;width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:rgba(0,0,0,0.55);margin-right:6px;">
                <i class="fas fa-bell" style="font-size:1.05rem;"></i>
                <?php if ($_notif_unread > 0): ?>
                <span style="position:absolute;top:3px;right:3px;min-width:16px;height:16px;padding:0 3px;border-radius:8px;background:#8B0000;color:#fff;font-size:0.62rem;font-weight:800;display:flex;align-items:center;justify-content:center;line-height:1;">
                    <?php echo $_notif_unread > 9 ? '9+' : $_notif_unread; ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="dropdown-menu dropdown-menu-end" style="width:340px;max-width:90vw;padding:0;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e7eb;">
                    <strong style="font-size:0.88rem;">Notifications</strong>
                    <?php if ($_notif_unread > 0): ?>
                    <a href="<?php echo BASE_URL; ?>notifications.php?action=mark_all_read&redirect=<?php echo $_notif_redirect; ?>"
                       style="font-size:0.74rem;font-weight:700;color:#8B0000;text-decoration:none;">Mark all read</a>
                    <?php endif; ?>
                </div>
                <div style="max-height:360px;overflow-y:auto;">
                    <?php if (empty($_notif_list)): ?>
                    <div style="padding:32px 16px;text-align:center;color:rgba(0,0,0,0.35);">
                        <i class="fas fa-bell-slash" style="font-size:1.6rem;display:block;margin-bottom:8px;opacity:0.5;"></i>
                        <span style="font-size:0.82rem;">No notifications yet</span>
                    </div>
                    <?php else: foreach ($_notif_list as $n):
                        $icon  = $_notif_icons[$n['type']] ?? $_notif_icons['info'];
                        $color = $_notif_colors[$n['type']] ?? $_notif_colors['info'];
                        $bg    = $_notif_bgs[$n['type']] ?? $_notif_bgs['info'];
                        $href  = BASE_URL . 'notifications.php?action=open&id=' . $n['id']
                               . '&redirect=' . $_notif_redirect
                               . (!empty($n['link']) ? '&link=' . urlencode(BASE_URL . $n['link']) : '');
                    ?>
                    <a href="<?php echo $href; ?>" style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid #f3f4f6;text-decoration:none;color:inherit;<?php echo $n['is_read'] ? '' : 'background:rgba(139,0,0,0.03);'; ?>">
                        <div style="width:32px;height:32px;border-radius:50%;background:<?php echo $bg; ?>;color:<?php echo $color; ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:0.83rem;font-weight:<?php echo $n['is_read'] ? '600' : '800'; ?>;color:#1a1d23;line-height:1.3;">
                                <?php echo htmlspecialchars($n['title']); ?>
                                <?php if (!$n['is_read']): ?><span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#8B0000;margin-left:5px;vertical-align:middle;"></span><?php endif; ?>
                            </div>
                            <?php if (!empty($n['message'])): ?>
                            <div style="font-size:0.78rem;color:rgba(0,0,0,0.50);margin-top:2px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
                                <?php echo htmlspecialchars($n['message']); ?>
                            </div>
                            <?php endif; ?>
                            <div style="font-size:0.70rem;color:rgba(0,0,0,0.35);margin-top:3px;"><?php echo timeAgo($n['created_at']); ?></div>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <div class="dropdown">
            <div class="topbar-user" data-bs-toggle="dropdown" aria-expanded="false" role="button" tabindex="0">
                <div class="topbar-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="topbar-user-info">
                    <span class="topbar-user-name"><?php echo htmlspecialchars(substr($current_user['full_name'], 0, 24)); ?></span>
                    <span class="topbar-user-role"><?php
                        echo ucfirst($current_user['role']);
                        if (!empty($current_user['college_id'])) {
                            echo ' &middot; ' . htmlspecialchars($current_user['college_id']);
                        }
                    ?></span>
                </div>
                <i class="fas fa-chevron-down topbar-caret"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end topbar-dropdown">
                <li class="dropdown-header">
                    <strong><?php echo htmlspecialchars($current_user['full_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($current_user['email']); ?></small>
                    <?php if (!empty($current_user['college_id'])): ?>
                    <br><small style="color:#1d4ed8;"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($current_user['college_id']); ?></small>
                    <?php endif; ?>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="<?php echo $settings_url; ?>">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
