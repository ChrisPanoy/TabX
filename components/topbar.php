<?php
// topbar.php
?>
<header class="topbar">
    <div class="topbar-left">
        <button id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle Sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
        </button>
        <div class="page-title-badge">
            <span class="dot"></span>
            <?= htmlspecialchars($title ?? 'Management System') ?>
        </div>
    </div>
    
    <div class="topbar-right">
        <div class="user-profile">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($user_name) ?></span>
                <span class="user-role"><?= ucfirst($role) ?></span>
            </div>
            <div class="user-avatar">
                <?= $role === 'dean' ? "👤" : ($role === 'panelist' ? "⚖️" : "🎓") ?>
            </div>
        </div>
        
        <div class="divider"></div>
        
        <a href="<?= $base_path ?>logout.php" class="logout-link" title="Logout session">
            <span class="logout-text">Logout</span>
            <span class="logout-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </span>
        </a>
    </div>
</header>
