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
?>
<div class="topbar" id="topbar">
    <div class="topbar-left">
        <h5 class="topbar-title"><?php echo htmlspecialchars($label); ?></h5>
    </div>
    <div class="topbar-right">
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
