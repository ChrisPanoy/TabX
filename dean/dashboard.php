<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php'; // Import Components
requireRole('dean');

$active_sy = get_active_school_year($pdo);
$curr_event = get_current_event($pdo);

render_head("Dashboard");
render_navbar($_SESSION['full_name'], 'dean', '../', "Dashboard");
?>
    <div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
        <div class="page-header" style="margin-bottom: 3rem;">
            <div>
                <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Dashboard</h1>
                <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">Welcome back, <span style="color: var(--primary); font-weight: 600;"><?= htmlspecialchars($_SESSION['full_name']) ?></span>. Here's what's happening today.</p>
            </div>
            <div style="background: white; padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem;">
                <div style="background: var(--primary-subtle); width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">📅</div>
                <div>
                    <span style="display: block; font-size: 0.75rem; color: var(--text-light); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Current Date</span>
                    <strong style="color: var(--dark); font-size: 0.9375rem;"><?= date('F j, Y') ?></strong>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <!-- School Year Card -->
            <div class="card stat-card animate-fade-in primary-top">
                <span class="stat-label">Academic Year</span>
                <div class="stat-value">
                    <?= $active_sy ? htmlspecialchars($active_sy['year_label']) : 'Not Set' ?>
                </div>
                <div style="margin-top: auto;">
                    <a href="academic.php" class="btn btn-secondary" style="width: 100%; justify-content: space-between; padding: 0.75rem 1rem;">
                        <span style="font-weight: 700;">Manage Session</span>
                        <span style="font-size: 1.25rem;">&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Events Card -->
            <div class="card stat-card animate-fade-in primary-top" style="animation-delay: 0.1s;">
                <span class="stat-label">Total Events</span>
                <div class="stat-value">
                    <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) FROM events");
                        echo $stmt->fetchColumn();
                    ?>
                </div>
                <div style="margin-top: auto; display: flex; gap: 0.75rem;">
                    <a href="events.php" class="btn btn-primary" style="flex: 1.5; padding: 0.75rem;">Manage Events</a>
                    <a href="teams.php" class="btn btn-secondary" style="flex: 1; padding: 0.75rem;">Teams</a>
                </div>
            </div>

            <!-- Users Card -->
            <div class="card stat-card animate-fade-in primary-top" style="animation-delay: 0.15s;">
                <span class="stat-label">Active Users</span>
                <div class="stat-value">
                    <?php 
                        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                        echo $stmt->fetchColumn();
                    ?>
                </div>
                <div style="margin-top: auto;">
                    <a href="users.php" class="btn btn-secondary" style="width: 100%; justify-content: space-between; padding: 0.75rem 1rem;">
                        <span style="font-weight: 700;">Manage Accounts</span>
                        <span style="font-size: 1.25rem;">&rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Active Event Status -->
            <div class="card animate-fade-in" style="grid-column: 1 / -1; animation-delay: 0.25s; border: none; background: linear-gradient(135deg, var(--primary-dark), var(--primary)); color: white; padding: 2.5rem;">
                <?php if($curr_event): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2rem;">
                        <div style="flex: 1; min-width: 300px;">
                            <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; background: rgba(255,255,255,0.2); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; color: white;">LIVE SESSION ACTIVE</span>
                            <h2 style="margin: 0; font-size: 2rem; color: white;"><?= htmlspecialchars($curr_event['title']) ?></h2>
                            <div style="display: flex; gap: 2rem; margin-top: 1rem; opacity: 0.9;">
                                <span>📅 <?= date('F j, Y', strtotime($curr_event['event_date'])) ?></span>
                                <span>📍 <?= htmlspecialchars($curr_event['venue']) ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                             <a href="manage_event.php?id=<?= $curr_event['id'] ?>" class="btn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.3);">Event Settings</a>
                             <a href="results.php?event_id=<?= $curr_event['id'] ?>" class="btn" style="background: white; color: var(--primary); font-weight: 700; padding: 0.75rem 2rem;">
                                 <span>Live Results</span>
                             </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 1.5rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📢</div>
                        <h2 style="color: white; margin-bottom: 0.5rem;">Ready to start an event?</h2>
                        <p style="opacity: 0.9; margin-bottom: 2rem; max-width: 500px; margin-left: auto; margin-right: auto;">Initialize a new event session to begin the real-time scoring and tabulation process.</p>
                        <a href="events.php" class="btn" style="background: white; color: var(--primary); font-weight: 700; padding: 1rem 2.5rem;">Create New Event Session</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php render_footer(); ?>
