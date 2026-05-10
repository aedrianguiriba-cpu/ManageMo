<?php
$current_user = getCurrentUser();
if (!$current_user) {
    return;
}

$is_admin = $current_user['role'] === 'admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Floating burger button (always visible) -->
<button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle Sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Header: Logo + Close -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <img src="<?php echo BASE_URL; ?>assets/pics/logo.png" alt="ManageMo Logo" class="sidebar-logo-img">
            </div>
            <div>
                <span class="sidebar-brand-text">ManageMo</span>
                
            </div>
        </div>
        <button class="sidebar-close-btn" id="sidebarClose" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
        <?php if ($is_admin): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/dashboard.php" title="Dashboard">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/inventory.php" title="All Items">
                    <span class="nav-icon"><i class="fas fa-warehouse"></i></span>
                    <span class="nav-text">All Items</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'inventory-campus.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/inventory-campus.php" title="By Campus">
                    <span class="nav-icon"><i class="fas fa-building"></i></span>
                    <span class="nav-text">By Campus</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'requests.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/requests.php" title="Requests">
                    <span class="nav-icon"><i class="fas fa-list-alt"></i></span>
                    <span class="nav-text">Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/analytics.php" title="Analytics">
                    <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                    <span class="nav-text">Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/reports.php" title="Reports">
                    <span class="nav-icon"><i class="fas fa-file-alt"></i></span>
                    <span class="nav-text">Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/users.php" title="Users">
                    <span class="nav-icon"><i class="fas fa-users"></i></span>
                    <span class="nav-text">Users</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/settings.php" title="Settings">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/dashboard.php" title="Dashboard">
                    <span class="nav-icon"><i class="fas fa-home"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'inventory.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/inventory.php" title="Inventory">
                    <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                    <span class="nav-text">Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'requests.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/requests.php" title="Submit Request">
                    <span class="nav-icon"><i class="fas fa-paper-plane"></i></span>
                    <span class="nav-text">Submit Request</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'my-requests.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/my-requests.php" title="Track Requests">
                    <span class="nav-icon"><i class="fas fa-map-marker-alt"></i></span>
                    <span class="nav-text">Track Requests</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'borrow-records.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/borrow-records.php" title="My Records">
                    <span class="nav-icon"><i class="fas fa-history"></i></span>
                    <span class="nav-text">My Records</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>user/settings.php" title="Settings">
                    <span class="nav-icon"><i class="fas fa-cog"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
        <?php endif; ?>
        </ul>
    </nav>

</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const overlay = document.getElementById('sidebarOverlay');
    const mainWrapper = document.querySelector('.main-wrapper');
    const topbar = document.getElementById('topbar');

    function setSidebarOpen(open) {
        if (open) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('closed');
            if (mainWrapper) { mainWrapper.classList.remove('sidebar-collapsed'); mainWrapper.classList.remove('sidebar-closed'); }
            if (topbar) { topbar.classList.remove('sidebar-collapsed'); topbar.classList.remove('sidebar-closed'); }
            if (window.innerWidth < 992) overlay.classList.add('show');
            toggleBtn.style.display = 'none';
        } else {
            sidebar.classList.add('collapsed');
            if (mainWrapper) mainWrapper.classList.add('sidebar-collapsed');
            if (topbar) topbar.classList.add('sidebar-collapsed');
            overlay.classList.remove('show');
            toggleBtn.style.display = 'flex';
        }
        localStorage.setItem('sidebar-collapsed', !open);
    }

    function toggleSidebar() {
        const isOpen = !sidebar.classList.contains('collapsed') && !sidebar.classList.contains('closed');
        setSidebarOpen(!isOpen);
    }

    // Restore state
    const isSidebarCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
    if (isSidebarCollapsed || window.innerWidth < 992) {
        setSidebarOpen(false);
    } else {
        setSidebarOpen(true);
    }

    // Event listeners
    toggleBtn.addEventListener('click', toggleSidebar);
    closeBtn.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
    
    // Close sidebar on link click (mobile only)
    const sidebarLinks = sidebar.querySelectorAll('.nav-link');
    sidebarLinks.forEach(link => {
        // Add title attribute from span text for tooltips
        const spanText = link.querySelector('span');
        if (spanText && !link.getAttribute('title')) {
            link.setAttribute('title', spanText.textContent.trim());
        }
        
        link.addEventListener('click', function() {
            if (window.innerWidth < 992 && !sidebar.classList.contains('closed')) {
                sidebar.classList.add('closed');
                if (mainWrapper) mainWrapper.classList.add('sidebar-closed');
                overlay.classList.remove('show');
            }
        });
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            overlay.classList.remove('show');
        }
    });
});
</script>

<?php require_once __DIR__ . '/topbar.php'; ?>
