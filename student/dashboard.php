<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('student');

$student_id = $_SESSION['user_id'];

// Get student's team (they must be the leader_id now)
$stmt = $pdo->prepare("
    SELECT t.*, e.title as event_title, e.venue, e.event_date, e.is_results_released 
    FROM teams t
    LEFT JOIN events e ON t.event_id = e.id
    WHERE t.leader_id = ?
");
$stmt->execute([$student_id]);
$team = $stmt->fetch();

if ($team) {
    // Get Assigned Panelists
    $stmt_pan = $pdo->prepare("
        SELECT u.full_name 
        FROM panelist_assignments pa 
        JOIN users u ON pa.panelist_id = u.id 
        WHERE pa.team_id = ?
    ");
    $stmt_pan->execute([$team['id']]);
    $panelists = $stmt_pan->fetchAll();

    // Calculate Scores
    $stmt_perc = $pdo->prepare("SELECT id, weight FROM criteria WHERE event_id = ? AND type = 'group'");
    $stmt_perc->execute([$team['event_id']]);
    $criteria_map = $stmt_perc->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt_s = $pdo->prepare("SELECT panelist_id, criteria_id, score FROM scores WHERE team_id = ?");
    $stmt_s->execute([$team['id']]);
    $all_scores = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

    $panelist_weighted = [];
    foreach ($all_scores as $s) {
        $pid = $s['panelist_id'];
        $cid = $s['criteria_id'];
        if (isset($criteria_map[$cid])) {
            if (!isset($panelist_weighted[$pid])) $panelist_weighted[$pid] = 0;
            $panelist_weighted[$pid] += ($s['score'] * ($criteria_map[$cid] / 100));
        }
    }

    $percentage_val = 0;
    if (count($panelist_weighted) > 0) {
        $percentage_val = array_sum($panelist_weighted) / count($panelist_weighted);
    }

    // Category Breakdown Calculation
    $stmt_cat_crit = $pdo->prepare("SELECT id, category FROM criteria WHERE event_id = ? AND type = 'group'");
    $stmt_cat_crit->execute([$team['event_id']]);
    $cat_crit_map = [];
    while($row = $stmt_cat_crit->fetch()) {
        $cat_crit_map[$row['id']] = $row['category'] ?: 'General';
    }

    $category_averages = [];
    $cat_data = []; // category => [panelist_id => [sum, count]]
    foreach ($all_scores as $s) {
        $cat = $cat_crit_map[$s['criteria_id']] ?? 'General';
        $pid = $s['panelist_id'];
        if (!isset($cat_data[$cat][$pid])) $cat_data[$cat][$pid] = ['sum' => 0, 'count' => 0];
        $cat_data[$cat][$pid]['sum'] += $s['score'];
        $cat_data[$cat][$pid]['count']++;
    }

    foreach ($cat_data as $cat => $panelists_scores) {
        $p_averages = [];
        foreach ($panelists_scores as $pid => $data) {
            $p_averages[] = $data['sum'] / $data['count'];
        }
        $category_averages[$cat] = array_sum($p_averages) / count($p_averages);
    }
    
    $is_released = (bool)$team['is_results_released'];
    $display_raw = $is_released ? number_format(array_sum($panelist_weighted) / (count($panelist_weighted) ?: 1), 2) : "Pending";
    $display_perc = $is_released ? number_format($percentage_val, 2) . "%" : "Pending";
}

render_head("Dashboard");
render_navbar($_SESSION['full_name'], 'student', '../', 'Dashboard');
?>

<?php if($team): ?>
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Dashboard</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">
                <strong><?= htmlspecialchars($team['team_name']) ?></strong> &bull; <?= htmlspecialchars($team['project_title']) ?>
            </p>
        </div>
        <div style="background: white; padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border); box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem;">
             <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary-subtle); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800;">
                <?= substr($_SESSION['full_name'], 0, 1) ?>
             </div>
             <div>
                <span style="display: block; font-size: 0.7rem; color: var(--text-light); font-weight: 700; text-transform: uppercase;">Group Leader</span>
                <strong style="color: var(--dark); font-size: 0.9375rem;"><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
             </div>
        </div>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
        <!-- Evaluation Status Card -->
        <div class="card" style="padding: 2.5rem; text-align: center; border-top: 5px solid var(--primary); position: relative; overflow: hidden;">
            <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.05; transform: rotate(15deg);">📊</div>
            
            <h4 style="margin-bottom: 2rem; color: var(--text-light); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800;">Evaluation Status</h4>
            
            <div style="margin: 0 auto; width: 180px; height: 180px; border-radius: 50%; border: 8px solid var(--primary-subtle); display: flex; flex-direction: column; align-items: center; justify-content: center; background: white; box-shadow: var(--shadow-md); position: relative;">
                <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-light); text-transform: uppercase;">Average</span>
                <div style="font-size: 2.5rem; font-weight: 900; color: var(--primary); line-height: 1; margin: 0.25rem 0;"><?= $display_perc ?></div>
                <div style="height: 1px; width: 40px; background: var(--border); margin: 0.5rem 0;"></div>
                <div style="font-size: 0.875rem; font-weight: 700; color: var(--secondary);"><?= $display_raw ?> pts</div>
                
                <?php if($is_released): ?>
                    <div style="position: absolute; bottom: -10px; background: var(--success); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.65rem; font-weight: 800; border: 2px solid white;">OFFICIAL</div>
                <?php else: ?>
                    <div style="position: absolute; bottom: -10px; background: var(--warning, #f59e0b); color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.65rem; font-weight: 800; border: 2px solid white;">PENDING</div>
                <?php endif; ?>
            </div>
            
            <p style="margin-top: 2.5rem; font-size: 0.8125rem; color: var(--text-light); line-height: 1.5; margin-bottom: 2rem;">
                Scores are tabulated after all panelists compute their evaluations. Results are finalized by the Dean.
            </p>

            <?php if($is_released && !empty($category_averages)): ?>
                <div style="border-top: 1px solid var(--border); padding-top: 1.5rem; text-align: left;">
                    <h5 style="font-size: 0.75rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">Category Breakdown</h5>
                    <div style="display: grid; gap: 0.75rem;">
                        <?php foreach($category_averages as $cat => $avg): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--light); padding: 0.75rem 1rem; border-radius: 10px; border: 1px solid var(--border);">
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--primary-dark);"><?= htmlspecialchars($cat) ?></span>
                                <span style="font-weight: 800; color: var(--primary);"><?= number_format($avg, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Defense Schedule Card -->
        <div class="card" style="padding: 2rem; background: var(--primary-dark); color: white; border: none; position: relative; overflow: hidden;">
            <div style="position: absolute; top: -10px; right: -10px; font-size: 4rem; opacity: 0.1;">⏱️</div>
            
            <h4 style="margin-bottom: 1.5rem; color: rgba(255,255,255,0.6); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800;">Defense Schedule</h4>
            
            <div style="margin-bottom: 1.5rem;">
                <div style="font-size: 1.125rem; font-weight: 600; color: rgba(255,255,255,0.9);"><?= $team['schedule_time'] ? date('l, F j, Y', strtotime($team['schedule_time'])) : 'Awaiting Schedule' ?></div>
                <div style="font-size: 2.5rem; font-weight: 900; color: white; letter-spacing: -0.02em; margin-top: 0.25rem;">
                    <?= $team['schedule_time'] ? date('g:i A', strtotime($team['schedule_time'])) : '--:--' ?>
                </div>
            </div>
            
            <div style="background: rgba(255,255,255,0.1); padding: 1.25rem; border-radius: var(--radius-md); border-left: 3px solid var(--primary-light); margin-bottom: 1.5rem;">
                <span style="display: block; font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 800; margin-bottom: 0.4rem;">Presentation Venue</span>
                <strong style="font-size: 1rem; color: white;">📍 <?= htmlspecialchars($team['venue'] ?: 'Venue to be announced') ?></strong>
            </div>

            <?php if(!empty($panelists)): ?>
                <div style="background: rgba(255,255,255,0.05); padding: 1.25rem; border-radius: var(--radius-md);">
                    <span style="display: block; font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 800; margin-bottom: 0.75rem;">Assigned Panelists</span>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <?php foreach($panelists as $p): ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 6px; height: 6px; background: var(--primary-light); border-radius: 50%;"></div>
                                <span style="font-size: 0.875rem; color: rgba(255,255,255,0.9);"><?= htmlspecialchars($p['full_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card animate-fade-in" style="text-align: center; padding: 5rem;">
        <h2 style="color: var(--text-light);">No Team assigned to your account.</h2>
        <p style="margin-top: 1rem;">Please coordinate with the Dean to initialize your Capstone Group.</p>
    </div>
<?php endif; ?>

<?php render_footer(); ?>
