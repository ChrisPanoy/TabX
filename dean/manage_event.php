<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('dean');

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$event_id) header("Location: events.php");

// Fetch Event
$stmt_event = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt_event->execute([$event_id]);
$event = $stmt_event->fetch();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_criteria'])) {
        $name = sanitize($_POST['name']);
        $weight = floatval($_POST['weight']);
        $type = $_POST['type'];
        $category = $_POST['category'] ?? 'General';
        $min = intval($_POST['min_score']);
        $max = intval($_POST['max_score']);
        
        $pdo->prepare("INSERT INTO criteria (criteria_name, weight, event_id, type, category, min_score, max_score) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$name, $weight, $event_id, $type, $category, $min, $max]);
    }
    
    if (isset($_POST['assign_panelist'])) {
        $team_id = $_POST['team_id'];
        $panelist_id = $_POST['panelist_id'];
        try {
            $pdo->prepare("INSERT INTO panelist_assignments (event_id, team_id, panelist_id) VALUES (?, ?, ?)")
                ->execute([$event_id, $team_id, $panelist_id]);
        } catch (PDOException $e) { /* Ignore duplicate */ }
    }
    
    if (isset($_POST['remove_assignment'])) {
         $aid = $_POST['assignment_id'];
         $pdo->prepare("DELETE FROM panelist_assignments WHERE id = ?")->execute([$aid]);
    }

    if (isset($_POST['remove_criteria'])) {
         $cid = $_POST['criteria_id'];
         $pdo->prepare("DELETE FROM criteria WHERE id = ?")->execute([$cid]);
    }
    
    if (isset($_POST['import_template'])) {
        $templates = $pdo->query("SELECT * FROM criteria WHERE event_id IS NULL")->fetchAll();
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM criteria WHERE event_id = ? AND criteria_name = ? AND type = ?");
        $stmt_ins = $pdo->prepare("INSERT INTO criteria (criteria_name, weight, event_id, type, category, min_score, max_score) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach($templates as $t) {
            $stmt_check->execute([$event_id, $t['criteria_name'], $t['type']]);
            if ($stmt_check->fetchColumn() == 0) {
                $stmt_ins->execute([$t['criteria_name'], $t['weight'], $event_id, $t['type'], $t['category'] ?? 'General', $t['min_score'], $t['max_score']]);
            }
        }
    }
}

// Data Fetching
$criteria_list = $pdo->prepare("SELECT * FROM criteria WHERE event_id = ? ORDER BY type, category, display_order");
$criteria_list->execute([$event_id]);
$criteria_list = $criteria_list->fetchAll();

$grouped_criteria = [];
foreach($criteria_list as $c) {
    $grouped_criteria[$c['type']][$c['category']][] = $c;
}

$teams_list = $pdo->prepare("SELECT * FROM teams WHERE event_id = ?");
$teams_list->execute([$event_id]);
$teams_list = $teams_list->fetchAll();

$panelists = $pdo->query("SELECT * FROM users WHERE role = 'panelist'")->fetchAll();

$assignments = $pdo->prepare("
    SELECT pa.id, pa.team_id, u.full_name 
    FROM panelist_assignments pa 
    JOIN users u ON pa.panelist_id = u.id 
    WHERE pa.event_id = ?
");
$assignments->execute([$event_id]);
$assignment_map = [];
while($row = $assignments->fetch()) {
    $assignment_map[$row['team_id']][] = $row;
}

render_head("Configuration: " . $event['title']);
render_navbar($_SESSION['full_name'], 'dean');
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <a href="events.php" style="color: var(--primary); font-weight: 700; font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; text-decoration: none;">
                <span>&larr;</span> Back to Events
            </a>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;"><?= htmlspecialchars($event['title']) ?> Configuration</h1>
            <div style="display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem;">
                 <span style="color: var(--text-light);"><?= date('F j, Y', strtotime($event['event_date'])) ?></span>
                 <span style="width: 4px; height: 4px; border-radius: 50%; background: var(--border);"></span>
                 <span style="color: var(--text-light);"><?= htmlspecialchars($event['venue']) ?></span>
            </div>
        </div>
        <div style="background: var(--primary-subtle); padding: 0.75rem 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--primary); display: flex; align-items: center; gap: 0.75rem;">
             <span style="font-size: 1.25rem;">⚙️</span>
             <strong style="color: var(--primary); font-size: 0.9375rem;">Event Management Mode</strong>
        </div>
    </div>


    <div class="card" style="margin-bottom: 4rem; padding: 2.5rem; border-top: 5px solid var(--primary);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.01em;">1. Evaluation Rubrics</h3>
                <p style="color: var(--text-light); margin-top: 0.25rem;">Define group and individual performance criteria.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="import_template" value="1">
                <button type="submit" class="btn btn-secondary" style="font-weight: 700; font-size: 0.8125rem;" onclick="return confirm('Import the default evaluation template for this event?');">
                    📥 Import Default Template
                </button>
            </form>
        </div>

        <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 3rem; background: var(--light); padding: 1.75rem; border-radius: var(--radius-lg); border: 1px solid var(--border); align-items: flex-end;">
            <input type="hidden" name="add_criteria" value="1">
            <div class="form-group" style="margin-bottom: 0; flex-grow: 2;">
                <label class="form-label" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Criteria Name</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Technical Innovation">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Weight (%)</label>
                <input type="number" name="weight" class="form-control" required placeholder="25">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Category</label>
                <select name="category" class="form-control" style="appearance: auto;">
                    <option value="General">General/Defense</option>
                    <option value="Manuscripts">Manuscripts</option>
                    <option value="Poster">Poster</option>
                    <option value="Brochure">Brochure</option>
                    <option value="Teaser">Teaser</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Type</label>
                <select name="type" class="form-control" style="appearance: auto;">
                    <option value="group">Group</option>
                    <option value="individual">Individual</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light);">Scale</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="number" name="min_score" class="form-control" value="0" style="width: 60px;">
                    <span>-</span>
                    <input type="number" name="max_score" class="form-control" value="10" style="width: 60px;">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="height: 46px; font-weight: 700;">Add Item</button>
        </form>

        <div class="table-container" style="border-radius: var(--radius-md); border: 1px solid var(--border); overflow: hidden;">
            <table style="margin-bottom: 0;">
                <thead>
                    <tr style="background: var(--light);">
                        <th style="padding: 1.25rem 1rem;">Criteria Definition</th>
                        <th style="width: 150px; padding: 1.25rem 1rem;">Category</th>
                        <th style="width: 120px; padding: 1.25rem 1rem;">Weight</th>
                        <th style="width: 120px; padding: 1.25rem 1rem;">Scale</th>
                        <th style="width: 100px; text-align: center; padding: 1.25rem 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($criteria_list)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-light); padding: 4rem;">No rubrics defined. Use the form above to initialize scoring criteria.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach(['group', 'individual'] as $type): ?>
                        <?php if(isset($grouped_criteria[$type])): ?>
                            <tr style="background: #f8fafc;">
                                <td colspan="5" style="padding: 0.75rem 1rem; border-bottom: 2px solid var(--border);">
                                    <span style="font-size: 0.7rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: var(--primary);">
                                        <?= $type ?> Evaluation Rubrics
                                    </span>
                                </td>
                            </tr>
                            <?php foreach($grouped_criteria[$type] as $category => $items): ?>
                                <tr style="background: #fff;">
                                    <td colspan="5" style="padding: 0.5rem 1.5rem; border-bottom: 1px solid var(--border); background: #fafafa;">
                                        <span style="font-size: 0.65rem; font-weight: 700; color: var(--text-light); text-transform: uppercase;">Category: <?= $category ?></span>
                                    </td>
                                </tr>
                                <?php foreach($items as $c): ?>
                                <tr>
                                    <td style="padding: 1.25rem 1rem; padding-left: 2rem;">
                                        <strong style="color: var(--dark);"><?= htmlspecialchars($c['criteria_name']) ?></strong>
                                    </td>
                                    <td style="padding: 1.25rem 1rem;">
                                        <span style="font-size: 0.75rem; font-weight: 600; color: var(--secondary);"><?= $c['category'] ?></span>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; font-weight: 700; color: var(--primary);"><?= (float)$c['weight'] ?>%</td>
                                    <td style="padding: 1.25rem 1rem; font-size: 0.875rem; color: var(--text-light);"><?= $c['min_score'] ?> &mdash; <?= $c['max_score'] ?></td>
                                    <td style="padding: 1.25rem 1rem; text-align: center;">
                                        <form method="POST" onsubmit="return confirm('Remove this criteria? This will affect existing scores.')" style="display:inline;">
                                            <input type="hidden" name="remove_criteria" value="1">
                                            <input type="hidden" name="criteria_id" value="<?= $c['id'] ?>">
                                            <button type="submit" style="background: var(--danger-subtle); border: none; color: var(--danger); cursor: pointer; width: 32px; height: 32px; border-radius: 8px; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;">&times;</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="padding: 2.5rem; border-top: 5px solid var(--secondary);">
        <div style="margin-bottom: 2.5rem;">
            <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.01em;">2. Panelist Assignments</h3>
            <p style="color: var(--text-light); margin-top: 0.25rem;">Distribute capstone groups to specific evaluation committees.</p>
        </div>
        
        <div class="dashboard-grid">
            <?php foreach($teams_list as $team): ?>
                <div class="card animate-fade-in" style="padding: 1.75rem; background: white; border: 1px solid var(--border);">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                        <div style="background: var(--primary-subtle); color: var(--primary); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.25rem; border: 1px solid rgba(59, 66, 243, 0.1);">
                            <?= substr($team['team_name'], 0, 1) ?>
                        </div>
                        <h4 style="margin: 0; font-size: 1.125rem; color: var(--primary-dark); letter-spacing: -0.01em;"><?= htmlspecialchars($team['team_name']) ?></h4>
                    </div>
                    
                    <div style="flex-grow: 1; margin-bottom: 2rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                            <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.1em;">Assigned Panelists</span>
                            <span style="font-size: 0.7rem; background: var(--light); padding: 2px 8px; border-radius: 10px; color: var(--text-light); font-weight: 700;">
                                <?= isset($assignment_map[$team['id']]) ? count($assignment_map[$team['id']]) : 0 ?> Panels
                            </span>
                        </div>

                        <div style="display: grid; gap: 0.75rem;">
                            <?php if(isset($assignment_map[$team['id']])): ?>
                                <?php foreach($assignment_map[$team['id']] as $assign): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 0.75rem 1rem; border-radius: 12px; border: 1px solid var(--border); transition: all 0.2s;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--success);"></div>
                                            <span style="font-size: 0.9375rem; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($assign['full_name']) ?></span>
                                        </div>
                                        <form method="POST" onsubmit="return confirm('Revoke this panelist\'s access to this team?');" style="display:inline;">
                                            <input type="hidden" name="remove_assignment" value="1">
                                            <input type="hidden" name="assignment_id" value="<?= $assign['id'] ?>">
                                            <button type="submit" style="background: var(--danger-subtle); border: none; color: var(--danger); cursor: pointer; width: 28px; height: 28px; border-radius: 8px; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">&times;</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="color: var(--text-light); font-size: 0.875rem; font-style: italic; background: #fff7ed; color: #9a3412; padding: 1rem; border-radius: 12px; border: 1px dashed #fdba74; text-align: center;">
                                    <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">📝</div>
                                    No panelists assigned yet
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <form method="POST" style="display: flex; flex-direction: column; gap: 0.75rem; background: var(--light); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                        <input type="hidden" name="assign_panelist" value="1">
                        <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                        <div style="display: flex; gap: 0.5rem;">
                            <select name="panelist_id" class="form-control" style="font-size: 0.875rem; background: white; border-color: var(--border);" required>
                                <option value="">Select Panelist...</option>
                                <?php foreach($panelists as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary" style="padding: 0; width: 44px; height: 44px; flex-shrink: 0; box-shadow: var(--shadow-sm);">+</button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php render_footer(); ?>
