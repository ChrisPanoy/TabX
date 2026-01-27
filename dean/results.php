<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/calculation_engine.php';
require_once '../components/ui.php';
requireRole('dean');

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) {
    $curr = get_current_event($pdo);
    if($curr) header("Location: results.php?event_id=" . $curr['id']);
    else die("No active event found. Please create an event session.");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_release'])) {
    $new_status = $_POST['release_status'] ? 0 : 1;
    $pdo->prepare("UPDATE events SET is_results_released = ? WHERE id = ?")->execute([$new_status, $event_id]);
    header("Location: results.php?event_id=" . $event_id);
    exit;
}

// Data Processing
$team_results = calculate_event_results($pdo, $event_id);
$ind_results = calculate_individual_results($pdo, $event_id);
$special_awards = calculate_special_awards($pdo, $event_id);

$overall_winner = !empty($team_results) ? $team_results[0] : null;
$best_presenter = !empty($ind_results) ? $ind_results[0] : null;

// Extract specific category awards
$best_paper = $special_awards['Best Capstone Paper'] ?? null;
$best_poster = $special_awards['Best Poster'] ?? null;
$best_brochure = $special_awards['Best Brochure'] ?? null;

// Check if any scores have been submitted at all
$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM scores s JOIN teams t ON s.team_id = t.id WHERE t.event_id = ?");
$stmt_check->execute([$event_id]);
$total_scores = $stmt_check->fetchColumn();

// Check ind scores too
$stmt_check_ind = $pdo->prepare("SELECT COUNT(*) FROM individual_scores ids JOIN team_members tm ON ids.team_member_id = tm.id JOIN teams t ON tm.team_id = t.id WHERE t.event_id = ?");
$stmt_check_ind->execute([$event_id]);
$total_ind_scores = $stmt_check_ind->fetchColumn();

$has_scores = ($total_scores > 0 || $total_ind_scores > 0);

// Criteria
$stmt_crit = $pdo->prepare("SELECT id, criteria_name FROM criteria WHERE event_id = ? AND type = 'group' ORDER BY display_order");
$stmt_crit->execute([$event_id]);
$criteria_headers = $stmt_crit->fetchAll();

render_head("Live Results: " . $event['title']);
render_navbar($_SESSION['full_name'], 'dean', '../', "Tabulation Results");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Tabulation Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
                 <span style="color: var(--text-light);"><?= htmlspecialchars($event['title']) ?></span>
                 <span style="width: 4px; height: 4px; border-radius: 50%; background: var(--border);"></span>
                 <span style="color: var(--text-light); font-weight: 500;">Live Feed: <?= date('h:i A') ?></span>
            </div>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
             <form method="POST" style="display: inline;">
                <input type="hidden" name="toggle_release" value="1">
                <input type="hidden" name="release_status" value="<?= $event['is_results_released'] ?>">
                <button type="submit" class="btn <?= $event['is_results_released'] ? 'btn-secondary' : 'btn-primary' ?>" style="font-weight: 700;">
                    <?= $event['is_results_released'] ? ' Unpublish Results' : ' Publish Live Results' ?>
                </button>
            </form>
            <div style="display: flex; gap: 0.5rem;">
                <button onclick="exportToExcel('team_table', 'Team_Rankings.xls')" class="btn btn-secondary" style="font-weight: 700;">📈 Excel</button>
                <a href="print_grading_sheets.php?event_id=<?= $event_id ?>" target="_blank" class="btn btn-secondary" style="font-weight: 700;">🖨️ Print Batch</a>
            </div>
        </div>
    </div>

    <?php if(!$has_scores): ?>
        <div class="card animate-fade-in" style="text-align: center; padding: 5rem; background: white; border: 2px dashed var(--border);">
            <div style="font-size: 5rem; margin-bottom: 2rem; opacity: 0.3;"></div>
            <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 1rem;">Waiting for Judge Evaluations</h2>
            <p style="color: var(--text-light); max-width: 500px; margin: 0 auto; font-size: 1.1rem; line-height: 1.6;">
                Tabulation results and award winners will automatically appear here once panelists begin submitting their evaluations.
            </p>
            <div style="margin-top: 3rem; display: flex; justify-content: center; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--text-light); font-weight: 600;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #e2e8f0; animation: pulse 2s infinite;"></span>
                    Awaiting Live Data...
                </div>
            </div>
        </div>
        
        <style>
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(226, 232, 240, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(226, 232, 240, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(226, 232, 240, 0); }
        }
        </style>

    <?php else: ?>
        <!-- Award Highlights -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800;"> Major Awards & Recognition</h3>
        </div>
        
        <div class="dashboard-grid" style="margin-bottom: 4rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
            <!-- Best Capstone Paper -->
            <div class="card animate-fade-in" style="border-top: 4px solid #4f46e5; background: white; text-align: center; padding: 2rem;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">📄</div>
                <p style="color: var(--text-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Best Capstone Paper</p>
                <?php if($best_paper): ?>
                    <h4 style="font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 1rem; min-height: 3em; display: flex; align-items: center; justify-content: center;"><?= htmlspecialchars($best_paper['team']) ?></h4>
                    <div style="background: #eef2ff; color: #4338ca; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 800; display: inline-block;">
                        <?= number_format($best_paper['score'], 2) ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-light); font-style: italic;">No scores recorded</p>
                <?php endif; ?>
            </div>

            <!-- Best Presenter -->
            <div class="card animate-fade-in" style="border-top: 4px solid #10b981; background: white; text-align: center; padding: 2rem; animation-delay: 0.1s;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🎤</div>
                <p style="color: var(--text-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Best Presenter</p>
                <?php if($best_presenter): ?>
                    <h4 style="font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 1rem; min-height: 3em; display: flex; align-items: center; justify-content: center;"><?= htmlspecialchars($best_presenter['full_name']) ?></h4>
                    <div style="background: #ecfdf5; color: #059669; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 800; display: inline-block;">
                        <?= number_format($best_presenter['final_score'], 2) ?>
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-light); margin-top: 0.5rem;"><?= htmlspecialchars($best_presenter['team_name']) ?></div>
                <?php else: ?>
                    <p style="color: var(--text-light); font-style: italic;">No scores recorded</p>
                <?php endif; ?>
            </div>

            <!-- Best Poster -->
            <div class="card animate-fade-in" style="border-top: 4px solid #f59e0b; background: white; text-align: center; padding: 2rem; animation-delay: 0.2s;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🖼️</div>
                <p style="color: var(--text-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Best Poster</p>
                <?php if($best_poster): ?>
                    <h4 style="font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 1rem; min-height: 3em; display: flex; align-items: center; justify-content: center;"><?= htmlspecialchars($best_poster['team']) ?></h4>
                    <div style="background: #fffbeb; color: #b45309; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 800; display: inline-block;">
                        <?= number_format($best_poster['score'], 2) ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-light); font-style: italic;">No scores recorded</p>
                <?php endif; ?>
            </div>

            <!-- Best Brochure -->
            <div class="card animate-fade-in" style="border-top: 4px solid #06b6d4; background: white; text-align: center; padding: 2rem; animation-delay: 0.3s;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">📂</div>
                <p style="color: var(--text-light); font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Best Brochure</p>
                <?php if($best_brochure): ?>
                    <h4 style="font-size: 1.25rem; color: var(--primary-dark); margin-bottom: 1rem; min-height: 3em; display: flex; align-items: center; justify-content: center;"><?= htmlspecialchars($best_brochure['team']) ?></h4>
                    <div style="background: #ecfeff; color: #0891b2; padding: 0.5rem 1.25rem; border-radius: 50px; font-weight: 800; display: inline-block;">
                        <?= number_format($best_brochure['score'], 2) ?>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-light); font-style: italic;">No scores recorded</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overall Champion -->
        <?php if($overall_winner): ?>
        <div class="card animate-fade-in" style="margin-bottom: 4rem; background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%); color: white; padding: 3rem; text-align: center; border: none; overflow: hidden; position: relative;">
            <div style="position: absolute; top: -50px; right: -50px; font-size: 15rem; opacity: 0.1; rotate: 15deg;">🏆</div>
            <div style="position: relative; z-index: 1;">
                <span style="background: rgba(255,255,255,0.2); padding: 0.5rem 1.5rem; border-radius: 50px; font-size: 0.8125rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.2em; border: 1px solid rgba(255,255,255,0.3);">Overall Champion</span>
                <h2 style="font-size: 3rem; margin: 1.5rem 0; letter-spacing: -0.03em; color: white;"><?= htmlspecialchars($overall_winner['team_name']) ?></h2>
                <p style="font-size: 1.25rem; opacity: 0.9; margin-bottom: 2rem; font-weight: 400;"><?= htmlspecialchars($overall_winner['project_title']) ?></p>
                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="font-size: 0.875rem; font-weight: 600; opacity: 0.8;">weighted final score</div>
                    <div style="font-size: 2.5rem; font-weight: 900;"><?= number_format($overall_winner['final_score'], 2) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Team Rankings -->
        <div class="card primary-top" style="margin-bottom: 3rem; padding: 0;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em;">Main Tabulation Matrix (Group)</h2>
                <div style="font-size: 0.8125rem; color: var(--text-light); font-weight: 600;">Ranked by Weighted Average</div>
            </div>
            <div class="table-responsive">
                <table id="team_table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Project & Team</th>
                            <?php foreach($criteria_headers as $ch): ?>
                                <th style="text-align: center; font-size: 0.7rem;"><?= htmlspecialchars($ch['criteria_name']) ?></th>
                            <?php endforeach; ?>
                            <th style="width: 120px; text-align: right; background: var(--primary-dark); color: white;">Final Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($team_results as $index => $t): ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if($index < 3): ?>
                                    <span style="font-size: 1.25rem; font-weight: bold;"><?= ($index==0 ? '🥇' : ($index==1 ? '🥈' : '🥉')) ?></span>
                                    <div style="font-size: 0.65rem; font-weight: 700; color: var(--text-light);"><?= ($index==0 ? 'GOLD' : ($index==1 ? 'SILVER' : 'BRONZE')) ?></div>
                                <?php else: ?>
                                    <span style="font-weight: 700; color: var(--secondary);"><?= $index + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="display: block; font-size: 1rem; color: var(--primary-dark);"><?= htmlspecialchars($t['project_title']) ?></strong>
                                <span style="font-size: 0.8125rem; color: var(--text-light);">Team: <?= htmlspecialchars($t['team_name']) ?></span>
                            </td>
                            <?php foreach($criteria_headers as $ch): $val = $t['criteria_averages'][$ch['id']] ?? 0; ?>
                                <td style="text-align: center; font-size: 0.9375rem; color: var(--text-main); font-weight: 500;">
                                    <?= $val > 0 ? number_format($val, 2) : '<span style="color: #cbd5e1;">-</span>' ?>
                                </td>
                            <?php endforeach; ?>
                            <td style="text-align: right; font-weight: 900; font-size: 1.25rem; color: var(--primary); background: #f0f9ff;">
                                <?= number_format($t['final_score'], 2) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Individual Rankings -->
        <div class="card success-top" style="padding: 0;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em;">Individual Performance (Top 10)</h2>
                <div style="font-size: 0.8125rem; color: var(--text-light); font-weight: 600;">Outstanding Students</div>
            </div>
            <div class="table-responsive">
                <table id="ind_table">
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">Rank</th>
                            <th>Student Information</th>
                            <th>Assigned Team</th>
                            <th style="width: 150px; text-align: right;">Average Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ind_results as $index => $s): if($index >= 10) break; ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--secondary);"><?= $index + 1 ?></td>
                            <td>
                                <strong style="display: block;"><?= htmlspecialchars($s['full_name']) ?></strong>
                                <span style="font-size: 0.75rem; color: var(--text-light); text-transform: uppercase; font-weight: 600;"><?= htmlspecialchars($s['role']) ?></span>
                            </td>
                            <td style="font-size: 0.875rem; color: var(--secondary);"><?= htmlspecialchars($s['team_name']) ?></td>
                            <td style="text-align: right; font-weight: 700; font-size: 1.125rem; color: var(--primary-dark);"><?= number_format($s['final_score'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function exportToExcel(tableId, filename) {
    var table = document.getElementById(tableId);
    var html = table.outerHTML;
    var url = 'data:application/vnd.ms-excel;base64,' + btoa(unescape(encodeURIComponent(html)));
    var link = document.createElement('a');
    link.download = filename;
    link.href = url;
    link.click();
}
</script>

<?php render_footer(); ?>
