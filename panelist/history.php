<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('panelist');

$panelist_id = $_SESSION['user_id'];

// Fetch Judged Projects with calculated grades
$stmt_judged = $pdo->prepare("
    SELECT 
        t.id, 
        t.team_name, 
        t.project_title,
        e.title as event_title,
        sl.locked_at,
        (
            SELECT SUM(s.score * (c.weight / 100))
            FROM scores s
            JOIN criteria c ON s.criteria_id = c.id
            WHERE s.team_id = t.id AND s.panelist_id = ?
        ) as total_grade
    FROM teams t
    JOIN score_locks sl ON t.id = sl.team_id
    JOIN events e ON t.event_id = e.id
    WHERE sl.panelist_id = ? AND sl.is_locked = 1
    ORDER BY sl.locked_at DESC
");
$stmt_judged->execute([$panelist_id, $panelist_id]);
$judged_teams = $stmt_judged->fetchAll();

render_head("Evaluation History");
render_navbar($_SESSION['full_name'], 'panelist', '../', "Evaluation History");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem; max-width: 1200px;">
    
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 12px; height: 12px; background: var(--success); border-radius: 50%; box-shadow: 0 0 0 5px var(--success-subtle);"></div>
            <div>
                <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--primary-dark); letter-spacing: -0.02em;">Evaluation History</h1>
                <p style="color: var(--text-light); margin-top: 0.25rem; font-size: 1.1rem;">Comprehensive record of your finalized project assessments.</p>
            </div>
        </div>
        <div style="background: var(--primary-dark); padding: 0.75rem 1.5rem; border-radius: 50px; color: white; display: flex; align-items: center; gap: 1rem; box-shadow: var(--shadow-md);">
            <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.8;">Total Evaluated</span>
            <span style="font-size: 1.5rem; font-weight: 900;"><?= count($judged_teams) ?></span>
        </div>
    </div>

    <div class="card" style="padding: 3rem; background: white; border-top: 5px solid var(--success); box-shadow: var(--shadow-lg);">
        <div style="display: grid; gap: 2.5rem;">
            <?php if(empty($judged_teams)): ?>
                <div style="text-align: center; padding: 6rem 2rem; background: var(--light); border-radius: var(--radius-xl); border: 2px dashed var(--border);">
                    <span style="font-size: 4rem; display: block; margin-bottom: 1.5rem;">📜</span>
                    <h3 style="color: var(--text-light); font-weight: 600; font-size: 1.5rem;">No assessments found.</h3>
                    <p style="color: var(--text-light); margin-top: 0.75rem; font-size: 1.1rem;">Your completed evaluations will appear here once they are finalized.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(500px, 1fr)); gap: 2rem;">
                    <?php foreach($judged_teams as $jt): ?>
                        <div style="background: white; border: 1px solid var(--border); border-radius: var(--radius-xl); padding: 2.5rem; transition: all 0.3s; position: relative; display: flex; flex-direction: column;" class="history-item">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
                                <div style="max-width: 70%;">
                                    <span style="font-size: 0.8125rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.12em; background: var(--primary-subtle); padding: 4px 12px; border-radius: 4px;"><?= htmlspecialchars($jt['event_title']) ?></span>
                                    <h3 style="margin-top: 1rem; font-size: 1.75rem; color: var(--primary-dark); font-weight: 800; line-height: 1.1; letter-spacing: -0.01em;"><?= htmlspecialchars($jt['team_name']) ?></h3>
                                </div>
                                <div style="text-align: right; background: var(--light); padding: 1rem 1.5rem; border-radius: 16px; border: 1px solid var(--border);">
                                    <div style="font-size: 2.25rem; font-weight: 900; color: var(--success); line-height: 1;"><?= number_format($jt['total_grade'], 1) ?></div>
                                    <span style="font-size: 0.65rem; color: var(--text-light); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-top: 4px;">Total Score</span>
                                </div>
                            </div>
                            
                            <div style="background: var(--light); padding: 1.75rem; border-radius: 16px; margin-bottom: 2rem; flex: 1;">
                                 <p style="font-size: 0.8125rem; color: var(--text-light); text-transform: uppercase; font-weight: 800; margin-bottom: 0.75rem; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
                                    <span>📘</span> Project Title
                                 </p>
                                 <p style="font-size: 1.125rem; color: var(--dark); font-weight: 600; line-height: 1.5;">
                                    <?= htmlspecialchars($jt['project_title']) ?>
                                 </p>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 1.75rem; margin-top: auto;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-light); font-size: 0.875rem; font-weight: 600;">
                                    <span style="font-size: 1.25rem;">📅</span>
                                    <span>Finalized <?= date('M d, Y', strtotime($jt['locked_at'])) ?></span>
                                </div>
                                <a href="score_team.php?team_id=<?= $jt['id'] ?>" class="btn btn-secondary" style="padding: 0.875rem 1.75rem; border-radius: 50px; font-weight: 700; border-width: 2px;">
                                    Review Scorecard &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .history-item:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-lg);
        transform: translateY(-5px);
    }
    
    @media (max-width: 650px) {
        .history-item {
            padding: 1.5rem !important;
        }
        div[style*="grid-template-columns: repeat(auto-fill"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>


<?php render_footer(); ?>
