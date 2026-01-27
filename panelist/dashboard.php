<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('panelist');

$panelist_id = $_SESSION['user_id'];

// Fetch assigned teams for active events (Excluding finalized ones)
$stmt = $pdo->prepare("
    SELECT t.*, e.title as event_title, e.status as event_status,
    (SELECT COUNT(*) FROM scores s WHERE s.team_id = t.id AND s.panelist_id = ?) as score_count,
    (SELECT COUNT(*) FROM criteria c WHERE c.event_id = e.id AND c.type='group') as criteria_count
    FROM teams t
    JOIN panelist_assignments pa ON t.id = pa.team_id
    JOIN events e ON pa.event_id = e.id
    WHERE pa.panelist_id = ? 
    AND e.status IN ('ongoing', 'upcoming')
    AND t.id NOT IN (SELECT team_id FROM score_locks WHERE panelist_id = ? AND is_locked = 1)
    ORDER BY e.event_date ASC
");
$stmt->execute([$panelist_id, $panelist_id, $panelist_id]);
$teams = $stmt->fetchAll();

render_head("Evaluations Board");
render_navbar($_SESSION['full_name'], 'panelist', '../', "Evaluation Board");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Evaluation Board</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">You have <span style="color: var(--primary); font-weight: 700;"><?= count($teams) ?></span> pending assignments to evaluate.</p>
        </div>
        <div style="background: white; padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem;">
             <span style="font-size: 1.25rem;">⚖️</span>
             <div>
                <span style="display: block; font-size: 0.7rem; color: var(--text-light); font-weight: 700; text-transform: uppercase;">Session Date</span>
                <strong style="color: var(--dark); font-size: 0.9375rem;"><?= date('l, F j') ?></strong>
             </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <?php foreach($teams as $team): ?>
            <?php 
                $isCompleted = $team['score_count'] >= $team['criteria_count'] && $team['criteria_count'] > 0;
                $statusClass = $isCompleted ? 'btn-secondary' : 'btn-primary';
                $btnText = $isCompleted ? 'Resume Evaluation' : 'Start Scoring';
                $statusText = $isCompleted ? 'DRAFT' : 'PENDING';
                $badgeStyle = $isCompleted ? 'background: var(--success-subtle); color: var(--success); border-color: var(--success);' : 'background: var(--primary-subtle); color: var(--primary); border-color: var(--primary);';
            ?>
            <div class="card animate-fade-in" style="display: flex; flex-direction: column; padding: 2rem; border-top: 5px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($team['event_title']) ?></span>
                        <h3 style="margin-top: 0.5rem; font-size: 1.25rem; letter-spacing: -0.01em;"><?= htmlspecialchars($team['team_name']) ?></h3>
                    </div>
                    <span style="<?= $badgeStyle ?> padding: 0.4rem 0.75rem; border-radius: 20px; font-size: 0.65rem; font-weight: 800; border: 1px solid; letter-spacing: 0.05em;">
                        <?= $statusText ?>
                    </span>
                </div>
                
                <div style="background: var(--light); padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                    <p style="font-size: 0.8125rem; color: var(--text-light); text-transform: uppercase; font-weight: 700; margin-bottom: 0.5rem; letter-spacing: 0.05em;">Project Title</p>
                    <p style="font-size: 1rem; color: var(--dark); font-weight: 600; line-height: 1.4;">
                        <?= htmlspecialchars($team['project_title']) ?>
                    </p>
                </div>
                
                <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <a href="score_team.php?team_id=<?= $team['id'] ?>" class="btn <?= $statusClass ?>" style="width: 100%; height: 50px; font-weight: 700;">
                        <?= $btnText ?> &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if(empty($teams)): ?>
            <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 5rem 2rem; background: transparent; border: 2px dashed var(--border);">
                <div style="font-size: 3.5rem; margin-bottom: 1rem;">📋</div>
                <h3 style="color: var(--text-light); font-weight: 600;">No assignments currently scheduled.</h3>
                <p style="color: var(--text-light); margin-top: 0.5rem;">Please check back once the administrator assigns you to a session.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
