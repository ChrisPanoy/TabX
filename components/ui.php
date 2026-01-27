<?php
// Reusable Head Component
function render_head($title, $base_path = '../') {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - TabulationX</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
</head>
<body>
    <div class="app-container">
<?php
}

// Reusable Navbar Component (Now Sidebar & Topbar)
function render_navbar($user_name, $role, $base_path = '../', $title = 'Dashboard') {
    $current_page = basename($_SERVER['PHP_SELF']);
    include __DIR__ . '/sidebar.php';
    echo '<main class="main-wrapper">';
    include __DIR__ . '/topbar.php';
    echo '<div class="content-body animate-fade-in">';
}

// Reusable Footer Component
function render_footer() {
?>
            </div> <!-- Close content-body -->
            <footer style="margin-top: auto; padding: 2rem; border-top: 1px solid var(--border); background: white;">
                <div style="text-align: center; color: var(--text-light); font-size: 0.75rem;">
                    &copy; <?= date('Y') ?> <span style="color: var(--primary); font-weight: 700;">TabulationX</span>. All rights reserved.
                </div>
            </footer>
        </main> <!-- Close main-wrapper -->
    </div> <!-- Close app-container -->

    <script>
        // Use a self-executing function to avoid global namespace pollution
        (function() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function(e) {
                    if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        })();
    </script>
</body>
</html>
<?php
}
?>
