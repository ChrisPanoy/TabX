<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('panelist');

$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
$panelist_id = $_SESSION['user_id'];

if (!$team_id) {
    header("Location: dashboard.php");
    exit;
}

// Fetch team details and Event ID
$stmt = $pdo->prepare("SELECT t.*, e.id as event_id, e.title as event_title FROM teams t JOIN events e ON t.event_id = e.id WHERE t.id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();

if (!$team) die("Team not found.");

// Check Lock Status
$stmt_lock = $pdo->prepare("SELECT is_locked FROM score_locks WHERE team_id = ? AND panelist_id = ?");
$stmt_lock->execute([$team_id, $panelist_id]);
$lock_status = $stmt_lock->fetch();
$is_locked = $lock_status && $lock_status['is_locked'];

// Data Fetching logic
$group_criteria = $pdo->prepare("SELECT * FROM criteria WHERE event_id = ? AND type = 'group' ORDER BY category, display_order ASC");
$group_criteria->execute([$team['event_id']]);
$group_criteria = $group_criteria->fetchAll();

$grouped_group_criteria = [];
foreach($group_criteria as $c) {
    $grouped_group_criteria[($c['category'] ?: 'General')][] = $c;
}

$ind_criteria = $pdo->prepare("SELECT * FROM criteria WHERE event_id = ? AND type = 'individual' ORDER BY category, display_order ASC");
$ind_criteria->execute([$team['event_id']]);
$ind_criteria = $ind_criteria->fetchAll();

// Fetch members directly from team_members (names only)
$members = $pdo->prepare("SELECT id, member_name, role_in_project FROM team_members WHERE team_id = ?");
$members->execute([$team_id]);
$members = $members->fetchAll();

// Fetch submissions
$stmt_sub = $pdo->prepare("SELECT file_type, submissions.* FROM submissions WHERE team_id = ?");
$stmt_sub->execute([$team_id]);
$submissions = $stmt_sub->fetchAll(PDO::FETCH_UNIQUE);

$existing_scores = [];
$existing_comments = [];
$stmt = $pdo->prepare("SELECT criteria_id, score, comments FROM scores WHERE team_id = ? AND panelist_id = ?");
$stmt->execute([$team_id, $panelist_id]);
while ($row = $stmt->fetch()) {
    $existing_scores[$row['criteria_id']] = $row['score'];
    $existing_comments[$row['criteria_id']] = $row['comments'];
}

$existing_ind_scores = [];
$existing_ind_comments = [];
$stmt = $pdo->prepare("SELECT team_member_id, criteria_id, score, comments FROM individual_scores WHERE panelist_id = ?");
$stmt->execute([$panelist_id]);
while ($row = $stmt->fetch()) {
    $existing_ind_scores[$row['team_member_id']][$row['criteria_id']] = $row['score'];
    $existing_ind_comments[$row['team_member_id']][$row['criteria_id']] = $row['comments'];
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken($_POST['csrf_token']);
    if ($is_locked) {
        $error = "This evaluation is finalized and locked.";
    } else {
        try {
            $pdo->beginTransaction();
            $finalize = isset($_POST['finalize_submission']);
            
            $sql_upsert = "INSERT INTO scores (team_id, panelist_id, criteria_id, score, comments) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), comments = VALUES(comments)";
            $stmt_upsert = $pdo->prepare($sql_upsert);
            foreach ($group_criteria as $criterion) {
                $cid = $criterion['id'];
                if (isset($_POST['g_score_' . $cid])) {
                    $score_val = floatval($_POST['g_score_' . $cid]);
                    $comment_val = $_POST['g_comment_' . $cid] ?? '';
                    if ($score_val < $criterion['min_score'] || $score_val > $criterion['max_score']) {
                        throw new Exception("Score range error.");
                    }
                    $stmt_upsert->execute([$team_id, $panelist_id, $cid, $score_val, $comment_val]);
                }
            }
            
            $sql_ind_upsert = "INSERT INTO individual_scores (team_member_id, panelist_id, criteria_id, score, comments) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), comments = VALUES(comments)";
            $stmt_ind = $pdo->prepare($sql_ind_upsert);
            foreach ($members as $mem) {
                $mid = $mem['id'];
                foreach ($ind_criteria as $ic) {
                    $icid = $ic['id'];
                    $field_name = 'i_score_' . $mid . '_' . $icid;
                    $comment_name = 'i_comment_' . $mid . '_' . $icid;
                    if (isset($_POST[$field_name])) {
                        $val = floatval($_POST[$field_name]);
                        $icomment = $_POST[$comment_name] ?? '';
                        if ($val < $ic['min_score'] || $val > $ic['max_score']) throw new Exception("Range error.");
                        $stmt_ind->execute([$mid, $panelist_id, $icid, $val, $icomment]);
                    }
                }
            }
            
            if ($finalize) {
                $pdo->prepare("INSERT INTO score_locks (event_id, team_id, panelist_id, is_locked, locked_at) VALUES (?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_locked = 1, locked_at = NOW()")
                    ->execute([$team['event_id'], $team_id, $panelist_id]);
                $is_locked = true;
            }
            
            $pdo->commit();
            header("Location: score_team.php?team_id=$team_id&success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

if(isset($_GET['success'])) $message = "Scores updated successfully!";

render_head("Evaluate: " . $team['team_name']);
render_navbar($_SESSION['full_name'], 'panelist');
?>

<style>
    .step-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2rem;
        position: relative;
        padding: 0 1rem;
    }
    .step-header::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--border);
        z-index: 1;
    }
    .step-item {
        position: relative;
        z-index: 2;
        background: white;
        padding: 0 1rem;
        text-align: center;
        width: 100px;
    }
    .step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--light);
        color: var(--text-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        margin: 0 auto 0.5rem;
        border: 2px solid var(--border);
        transition: all 0.3s;
    }
    .step-item.active .step-circle {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px var(--primary-subtle);
    }
    .step-item.completed .step-circle {
        background: var(--success);
        color: white;
        border-color: var(--success);
    }
    .step-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--text-light);
        letter-spacing: 0.05em;
    }
    .step-item.active .step-label {
        color: var(--primary);
    }

    .form-step {
        display: none;
        animation: slideUp 0.4s ease-out;
    }
    .form-step.active {
        display: block;
    }

    .criterion-row {
        padding: 2.5rem;
        transition: all 0.3s;
        border-bottom: 1px solid var(--border);
        position: relative;
    }
    .criterion-row:last-child {
        border-bottom: none;
    }
    .criterion-row:hover {
        background: #fafafa;
    }
    .criterion-row.active {
        background: #f8fbff;
        border-left: 4px solid var(--primary);
        padding-left: calc(2.5rem - 4px);
    }

    .category-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border);
        overflow: hidden;
        margin-bottom: 4rem;
    }
    .category-header {
        background: var(--light);
        padding: 1.25rem 2.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .category-badge {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        background: var(--primary-subtle);
        padding: 0.4rem 1rem;
        border-radius: 50px;
    }

    /* Individual items refinement */
    .member-card {
        margin-bottom: 4rem;
    }
    .ind-criterion-row {
        padding: 1.5rem 0;
        border-bottom: 1px solid var(--border);
    }
    .ind-criterion-row:last-child {
        border-bottom: none;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pagination-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        background: white;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
    }

    /* Sticky Assets Bar */
    .sticky-assets {
        position: sticky;
        top: 80px;
        z-index: 900;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--border);
        margin: -2.5rem -1rem 2rem -1rem;
        padding: 0.75rem 1rem;
        display: flex;
        gap: 1rem;
        justify-content: center;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .asset-pill {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid var(--border);
        border-radius: 50px;
        text-decoration: none;
        color: var(--text-main);
        font-size: 0.8125rem;
        font-weight: 700;
        transition: all 0.2s;
    }
    .asset-pill:hover {
        background: var(--primary-subtle);
        border-color: var(--primary);
        transform: translateY(-1px);
    }
    .asset-pill.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
</style>

<div class="container" style="max-width: 1000px; margin-top: 2.5rem; padding-bottom: 8rem;">
    
    <div class="page-header">
        <div>
            <a href="dashboard.php" style="color: var(--primary); font-weight: 600; font-size: 0.875rem;">&larr; Back to Teams</a>
            <h1 style="margin-top: 0.5rem;">Evaluation Sheet</h1>
            <p style="color: var(--text-light);"><?= htmlspecialchars($team['event_title']) ?> &bull; <strong style="color: var(--text-main);"><?= htmlspecialchars($team['team_name']) ?></strong></p>
        </div>
        <div style="text-align: right;">
             <?php if($is_locked): ?>
                <div class="alert alert-success" style="margin: 0; padding: 0.5rem 1rem;">
                    <span>🔒 Evaluation Finalized</span>
                </div>
             <?php else: ?>
                <div class="alert alert-danger" style="margin: 0; padding: 0.5rem 1rem; background: #fff7ed; color: #9a3412; border-color: #ffedd5;">
                    <span>✍️ Draft Mode</span>
                </div>
             <?php endif; ?>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-success animate-fade-in"><?= $message ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger animate-fade-in"><?= $error ?></div>
    <?php endif; ?>

    <div class="step-header">
        <div class="step-item active" id="step-nav-1">
            <div class="step-circle">1</div>
            <div class="step-label">Ready</div>
        </div>
        <div class="step-item" id="step-nav-2">
            <div class="step-circle">2</div>
            <div class="step-label">Scoring</div>
        </div>
        <div class="step-item" id="step-nav-3">
            <div class="step-circle">3</div>
            <div class="step-label">Review</div>
        </div>
    </div>

    <!-- Persistent Submission Links -->
    <div class="sticky-assets" id="persistentAssets" style="display: none;">
        <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; margin-right: 0.5rem; display: flex; align-items: center;">Project Assets:</span>
        <?php 
        $mini_docs = [
            'emrad' => ['label' => 'Manuscript', 'icon' => '📄'],
            'poster' => ['label' => 'Poster', 'icon' => '🖼️'],
            'brochure' => ['label' => 'Brochure', 'icon' => '📂']
        ];
        foreach($mini_docs as $mtype => $minfo): 
            $msub = $submissions[$mtype] ?? null;
        ?>
            <a href="<?= $msub ? '../'.htmlspecialchars($msub['file_path']) : '#' ?>" 
               target="_blank" 
               class="asset-pill <?= $msub ? '' : 'disabled' ?>"
               title="<?= $msub ? 'Open '.$minfo['label'] : $minfo['label'].' not submitted' ?>">
                <span><?= $minfo['icon'] ?></span>
                <span><?= $minfo['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="POST" id="evaluationForm">
        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
        
        <!-- Step 1: Assets Overview -->
        <div class="form-step active" id="step-1">
            <div class="card" style="margin-bottom: 2rem; border-top: 4px solid var(--secondary); text-align: center; padding: 3rem 2rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;"></div>
                <h3 style="margin: 0; font-size: 1.75rem; color: var(--primary-dark);">Ready to Begin?</h3>
                <p style="margin: 0.5rem auto 2.5rem; font-size: 1rem; color: var(--text-light); max-width: 500px;">Review the team's submissions below one last time, then click the button to start scoring.</p>

                <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; text-align: center;">
                    <?php 
                    $doc_types = [
                        'emrad' => ['label' => 'Manuscripts / EMRAD', 'icon' => '📄'],
                        'poster' => ['label' => 'Research Poster', 'icon' => '🖼️'],
                        'brochure' => ['label' => 'Project Brochure', 'icon' => '📂']
                    ];
                    foreach($doc_types as $type => $info): 
                        $sub = $submissions[$type] ?? null;
                    ?>
                        <div style="background: white; border: 2px solid <?= $sub ? 'var(--primary-light)' : 'var(--border)' ?>; padding: 2rem; border-radius: 16px; position: relative;">
                            <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: <?= $sub ? '1' : '0.3' ?>;"><?= $info['icon'] ?></div>
                            <h4 style="margin: 0; font-size: 1rem; color: var(--text-main);"><?= $info['label'] ?></h4>
                            <?php if($sub): ?>
                                <p style="margin: 0.25rem 0 1.25rem; font-size: 0.75rem; color: var(--success); font-weight: 700;">READY</p>
                                <a href="../<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="btn btn-secondary" style="width: 100%; border-radius: 50px; font-size: 0.8125rem;">View Document</a>
                            <?php else: ?>
                                <p style="margin: 0.25rem 0 1.25rem; font-size: 0.75rem; color: var(--danger); font-weight: 700;">MISSING</p>
                                <button disabled class="btn btn-secondary" style="width: 100%; border-radius: 50px; opacity: 0.4;">Not Available</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="pagination-controls" style="justify-content: center;">
                <button type="button" class="btn btn-primary" onclick="changeStep(2)" style="padding: 1rem 3rem; border-radius: 50px; font-size: 1.1rem; font-weight: 800; box-shadow: var(--shadow-lg);">
                    Start Scoring Sessions &rarr;
                </button>
            </div>
        </div>

        <!-- Step 2: Consolidated Evaluation -->
        <div class="form-step" id="step-2">
            <section id="group-evaluation" style="margin-bottom: 5rem;">
                <div style="margin-bottom: 3rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); display: flex; align-items: center; gap: 0.75rem;">
                        <span style="width: 36px; height: 36px; background: var(--primary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">A</span>
                        Group Performance Matrix
                    </h3>
                    <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.5rem; margin-left: 3rem;">Evaluate the team's overall project quality and presentation effectiveness.</p>
                </div>
                
                <?php foreach($grouped_group_criteria as $category => $criteria): ?>
                    <div class="category-card">
                        <div class="category-header">
                            <span class="category-badge">Category: <?= htmlspecialchars($category) ?></span>
                            <span style="font-size: 0.75rem; color: var(--text-light); font-weight: 600;"><?= count($criteria) ?> Criteria</span>
                        </div>

                        <div class="criteria-list">
                            <?php foreach($criteria as $c): $val = $existing_scores[$c['id']] ?? ''; ?>
                                <div class="criterion-row" onclick="highlightCriterion(this)">
                                    <div style="display:flex; justify-content:space-between; margin-bottom: 2rem; align-items: flex-start;">
                                        <div style="max-width: 65%;">
                                            <h4 style="margin: 0; font-size: 1.35rem; color: var(--primary-dark); font-weight: 800;"><?= htmlspecialchars($c['criteria_name']) ?></h4>
                                            <p style="color: var(--text-light); margin-top: 0.75rem; font-size: 1rem; line-height: 1.6;"><?= htmlspecialchars($c['description'] ?: 'Please rate the team based on official performance standards.') ?></p>
                                        </div>
                                        <div style="text-align: right; background: var(--light); padding: 1.25rem; border-radius: 16px; min-width: 120px; border: 1px solid var(--border);">
                                            <span style="font-size: 0.65rem; color: var(--text-light); font-weight: 800; text-transform: uppercase; display: block; margin-bottom: 6px; letter-spacing: 0.05em;">Maximum Rails</span>
                                            <div style="font-weight: 900; color: var(--primary); font-size: 1.75rem; line-height: 1;"><?= $c['max_score'] ?></div>
                                            <div style="height: 1px; background: var(--border); margin: 0.75rem 0;"></div>
                                            <span style="font-size: 0.7rem; color: var(--text-main); font-weight: 700;">Weight: <?= (float)$c['weight'] ?>%</span>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; align-items: center; gap: 2.5rem; background: var(--light); padding: 2rem; border-radius: 20px; border: 1px solid var(--border);">
                                        <div style="flex: 1; display: flex; align-items: center; gap: 1.5rem;">
                                            <span style="font-size: 1.125rem; font-weight: 800; color: var(--text-light); min-width: 25px; text-align: center;"><?= $c['min_score'] ?></span>
                                            <div style="flex: 1; position: relative; height: 12px; display: flex; align-items: center;">
                                                <input type="range" 
                                                       style="width: 100%; cursor: pointer;" 
                                                       min="<?= $c['min_score'] ?>" max="<?= $c['max_score'] ?>" 
                                                       step="0.01"
                                                       value="<?= $val !== '' ? $val : $c['min_score'] ?>" 
                                                       oninput="updateScoreValue(this, <?= $c['id'] ?>)"
                                                       <?= $is_locked ? 'disabled' : '' ?>>
                                            </div>
                                            <span style="font-size: 1.125rem; font-weight: 800; color: var(--text-light); min-width: 25px; text-align: center;"><?= $c['max_score'] ?></span>
                                        </div>
                                        
                                        <div style="position: relative;">
                                            <input type="number" name="g_score_<?= $c['id'] ?>" id="g_score_<?= $c['id'] ?>"
                                                   style="width: 120px; height: 70px; text-align: center; font-size: 1.75rem; font-weight: 900; border: 3px solid var(--primary); color: var(--primary); border-radius: 16px; background: white; box-shadow: var(--shadow-sm);"
                                                   min="<?= $c['min_score'] ?>" max="<?= $c['max_score'] ?>" 
                                                   step="0.01"
                                                   value="<?= $val ?>" 
                                                   oninput="updateRangeValue(this, <?= $c['id'] ?>)"
                                                   <?= $is_locked ? 'readonly' : '' ?> required placeholder="-">
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 2rem;">
                                        <label style="font-size: 0.75rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; letter-spacing: 0.05em;">
                                            <span>💬</span> Evaluator Feedback
                                        </label>
                                        <textarea name="g_comment_<?= $c['id'] ?>" 
                                                  style="width: 100%; border: 1px solid var(--border); border-radius: 16px; padding: 1.25rem; font-size: 1rem; resize: vertical; min-height: 80px; transition: border-color 0.2s;" 
                                                  placeholder="Tell the team what they did well or where they can improve..."
                                                  <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($existing_comments[$c['id']] ?? '') ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <section id="individual-evaluation" style="margin-bottom: 5rem;">
                <div style="margin-bottom: 3rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); display: flex; align-items: center; gap: 0.75rem;">
                        <span style="width: 36px; height: 36px; background: var(--secondary); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">B</span>
                        Student Panel Performance
                    </h3>
                    <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.5rem; margin-left: 3rem;">Evaluate each member's individual contribution and mastery during the Q&A.</p>
                </div>
                
                <div style="display: grid; gap: 3rem;">
                    <?php foreach($members as $m): ?>
                        <div class="category-card member-card">
                            <div class="category-header" style="background: white; padding: 2rem 2.5rem; border-bottom: 1px solid var(--border);">
                                <div style="display: flex; align-items: center; gap: 1.5rem;">
                                    <div style="background: var(--primary); color: white; width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.5rem; box-shadow: var(--shadow-sm);">
                                        <?= substr($m['member_name'], 0, 1) ?>
                                    </div>
                                    <div>
                                        <h3 style="margin: 0; font-size: 1.5rem; color: var(--dark); font-weight: 800;"><?= htmlspecialchars($m['member_name']) ?></h3>
                                        <span class="category-badge" style="background: var(--light); color: var(--text-light); border: 1px solid var(--border); margin-top: 4px; display: inline-block;"><?= htmlspecialchars($m['role_in_project'] ?: 'Member') ?></span>
                                    </div>
                                </div>
                            </div>

                            <div style="padding: 0 2.5rem 2.5rem;">
                                <?php foreach($ind_criteria as $ic): $ival = $existing_ind_scores[$m['id']][$ic['id']] ?? ''; ?>
                                    <div class="ind-criterion-row" onclick="highlightCriterion(this)">
                                         <div style="display:flex; justify-content:space-between; margin-bottom: 1.5rem; align-items: flex-end;">
                                            <div>
                                                <label style="font-size: 1.125rem; font-weight: 800; color: var(--primary-dark); display: block;"><?= htmlspecialchars($ic['criteria_name']) ?></label>
                                                <?php if($ic['category']): ?>
                                                    <span style="font-size: 0.65rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($ic['category']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="text-align: right;">
                                                <span style="font-size: 0.65rem; color: var(--text-light); font-weight: 800; text-transform: uppercase;">Range: <?= $ic['min_score'] ?> - <?= $ic['max_score'] ?></span>
                                            </div>
                                         </div>
                                         
                                         <div style="display: flex; align-items: center; gap: 2rem; background: var(--light); padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border);">
                                            <input type="range" style="flex: 1;" min="<?= $ic['min_score'] ?>" max="<?= $ic['max_score'] ?>" 
                                                   step="0.01"
                                                   value="<?= $ival !== '' ? $ival : $ic['min_score'] ?>" 
                                                   oninput="updateIndScoreValue(this, <?= $m['id'] ?>, <?= $ic['id'] ?>)"
                                                   <?= $is_locked ? 'disabled' : '' ?>>
                                                   
                                            <input type="number" name="i_score_<?= $m['id'] ?>_<?= $ic['id'] ?>" id="i_score_<?= $m['id'] ?>_<?= $ic['id'] ?>"
                                                   style="width: 100px; height: 56px; text-align: center; font-weight: 900; border: 2px solid var(--primary); border-radius: 12px; font-size: 1.5rem; color: var(--primary); background: white;"
                                                   min="<?= $ic['min_score'] ?>" max="<?= $ic['max_score'] ?>" 
                                                   step="0.01"
                                                   value="<?= $ival ?>" 
                                                   oninput="updateIndRangeValue(this, <?= $m['id'] ?>, <?= $ic['id'] ?>)"
                                                   <?= $is_locked ? 'readonly' : '' ?> required placeholder="-">
                                         </div>
                                         <textarea name="i_comment_<?= $m['id'] ?>_<?= $ic['id'] ?>" 
                                                style="width: 100%; margin-top: 1rem; border: 1px solid var(--border); border-radius: 12px; padding: 1rem; font-size: 0.9375rem; background: white; resize: none;" 
                                                placeholder="Remarks on performance..."
                                                rows="1"
                                                <?= $is_locked ? 'readonly' : '' ?>><?= htmlspecialchars($existing_ind_comments[$m['id']][$ic['id']] ?? '') ?></textarea>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="pagination-controls">
                <button type="button" class="btn btn-secondary" onclick="changeStep(1)" style="padding: 0.75rem 2rem; border-radius: 50px;">
                    &larr; Back to Assets
                </button>
                <button type="button" class="btn btn-primary" onclick="changeStep(3)" style="padding: 0.75rem 2rem; border-radius: 50px;">
                    Review Final Results &rarr;
                </button>
            </div>
        </div>

        <!-- Step 3: Review and Finalize -->
        <div class="form-step" id="step-3">
            <div class="card" style="text-align: center; padding: 4rem 2.5rem; border-top: 4px solid var(--success);">
                <div style="font-size: 4rem; margin-bottom: 1.5rem;">📋</div>
                <h2 style="font-size: 2rem; color: var(--primary-dark); margin-bottom: 1rem;">Ready to Finalize?</h2>
                <p style="color: var(--text-light); max-width: 600px; margin: 0 auto 3rem; font-size: 1.1rem; line-height: 1.6;">
                    Please double-check all scores. Once you click <strong>"Finalize and Lock"</strong>, you will no longer be able to modify your evaluations for this team.
                </p>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border);">
                        <span style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-light); text-transform: uppercase;">Team Members</span>
                        <div style="font-size: 1.75rem; font-weight: 900; color: var(--primary);"><?= count($members) ?></div>
                    </div>
                    <div style="background: var(--light); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--border);">
                        <span style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-light); text-transform: uppercase;">Group Criteria</span>
                        <div style="font-size: 1.75rem; font-weight: 900; color: var(--primary);"><?= count($group_criteria) ?></div>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: center; flex-direction: column; max-width: 400px; margin: 0 auto;">
                    <button type="submit" name="save_draft" class="btn btn-secondary" style="height: 56px; border-radius: 50px; font-weight: 700; font-size: 1rem;">💾 Save Progress as Draft</button>
                    <button type="submit" name="finalize_submission" class="btn btn-primary" style="height: 64px; border-radius: 50px; font-weight: 800; font-size: 1.125rem; box-shadow: var(--shadow-lg);" onclick="return confirm('Confirm final submission? This evaluation will be locked permanently.');">🔒 Finalize and Lock Scoring</button>
                    <button type="button" class="btn btn-link" onclick="changeStep(1)" style="margin-top: 1rem; color: var(--text-light); text-decoration: none; font-weight: 600;">&larr; Go back to the beginning</button>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
    function changeStep(step) {
        // Hide all steps
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        // Show target step
        document.getElementById('step-' + step).classList.add('active');
        
        // Toggle persistent assets bar (show only during actual scoring step 2)
        const assetsBar = document.getElementById('persistentAssets');
        if (step === 2) {
            assetsBar.style.display = 'flex';
        } else {
            assetsBar.style.display = 'none';
        }

        // Update nav
        document.querySelectorAll('.step-item').forEach((nav, idx) => {
            if (idx + 1 < step) {
                nav.classList.add('completed');
                nav.classList.remove('active');
            } else if (idx + 1 === step) {
                nav.classList.add('active');
                nav.classList.remove('completed');
            } else {
                nav.classList.remove('active', 'completed');
            }
        });

        // Scroll top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function highlightCriterion(card) {
        document.querySelectorAll('.criterion-row, .ind-criterion-row').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
    }

    function updateScoreValue(range, id) {
        document.getElementById('g_score_' + id).value = range.value;
    }

    function updateRangeValue(input, id) {
        const range = input.parentElement.previousElementSibling.querySelector('input[type="range"]');
        if (range) range.value = input.value;
    }

    function updateIndScoreValue(range, memberId, criteriaId) {
        document.getElementById('i_score_' + memberId + '_' + criteriaId).value = range.value;
    }

    function updateIndRangeValue(input, memberId, criteriaId) {
        const range = input.parentElement.querySelector('input[type="range"]');
        if (range) range.value = input.value;
    }
</script>

<?php render_footer(); ?>
