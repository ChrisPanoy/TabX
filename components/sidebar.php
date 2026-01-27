<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
         <div class="brand-logo">
            <img src="<?= $base_path ?>assets/img/logo.png" alt="Logo">
         </div>
         <span class="brand-name">Tabulation<span class="x-mark">X</span></span>
    </div>

    <nav class="sidebar-menu">
        <?php if($role === 'dean'): ?>
            <div class="menu-section">General</div>
            <a href="<?= $base_path ?>dean/dashboard.php" class="menu-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Dashboard</span>
            </a>
            <a href="<?= $base_path ?>dean/academic.php" class="menu-item <?= $current_page == 'academic.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Academic Year</span>
            </a>

            <div class="menu-section">Management</div>
            <a href="<?= $base_path ?>dean/events.php" class="menu-item <?= in_array($current_page, ['events.php', 'manage_event.php']) ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Events</span>
            </a>
            <a href="<?= $base_path ?>dean/teams.php" class="menu-item <?= $current_page == 'teams.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Teams</span>
            </a>
            <a href="<?= $base_path ?>dean/results.php" class="menu-item <?= $current_page == 'results.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Results</span>
            </a>

            <div class="menu-section">System</div>
            <a href="<?= $base_path ?>dean/users.php" class="menu-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Accounts</span>
            </a>
        <?php elseif($role === 'panelist'): ?>
            <div class="menu-section">Main</div>
            <a href="<?= $base_path ?>panelist/dashboard.php" class="menu-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Evaluation</span>
            </a>
            <a href="<?= $base_path ?>panelist/history.php" class="menu-item <?= $current_page == 'history.php' ? 'active' : '' ?>">
                <span class="menu-icon"></span>
                <span class="menu-text">Evaluation History</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> TabulationX</p>
            <p class="footer-sub">Premium Version</p>
        </div>
    </div>
</aside>
