<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('dean');

$active_sy = get_active_school_year($pdo);
$curr_event = get_current_event($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_team'])) {
        $name = sanitize($_POST['team_name']);
        $title = sanitize($_POST['project_title']);
        $desc = sanitize($_POST['description']);
        $event_id = !empty($_POST['event_id']) ? $_POST['event_id'] : null;
        $schedule = !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : null;
        $leader_id = !empty($_POST['leader_id']) ? $_POST['leader_id'] : null;

        if ($name && $title && $leader_id) {
            $stmt = $pdo->prepare("INSERT INTO teams (team_name, project_title, description, event_id, schedule_time, leader_id) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$name, $title, $desc, $event_id, $schedule, $leader_id])) {
                $message = "Team created successfully!";
            } else {
                $error = "Failed to create team.";
            }
        } else {
            $error = "Please provide Group Name, Title, and select a Leader.";
        }
    }
    
    if (isset($_POST['delete_team'])) {
        $tid = $_POST['team_id'];
        $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([$tid]);
        $message = "Team deleted.";
    }
}

$events = $pdo->query("SELECT id, title FROM events ORDER BY event_date DESC")->fetchAll();
// Only show students who are NOT already leaders of a team
$available_leaders = $pdo->query("
    SELECT u.id, u.full_name 
    FROM users u 
    LEFT JOIN teams t ON u.id = t.leader_id 
    WHERE u.role = 'student' AND t.id IS NULL
    ORDER BY u.full_name
")->fetchAll();

$teams = $pdo->query("
    SELECT t.*, e.title as event_title, u.full_name as leader_name 
    FROM teams t 
    LEFT JOIN events e ON t.event_id = e.id 
    LEFT JOIN users u ON t.leader_id = u.id
    ORDER BY t.id DESC
")->fetchAll();

$members_map = [];
$m_stmt = $pdo->query("SELECT id, team_id, member_name, role_in_project FROM team_members");
while ($row = $m_stmt->fetch()) {
    $members_map[$row['team_id']][] = $row;
}

$submissions_map = [];
$s_stmt = $pdo->query("SELECT * FROM submissions");
while ($row = $s_stmt->fetch()) {
    $submissions_map[$row['team_id']][$row['file_type']] = $row;
}

render_head("Manage Teams");
render_navbar($_SESSION['full_name'], 'dean', '../', "Capstone Groups");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Capstone Groups</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">Initialize groups and assign student leader accounts.</p>
        </div>
       
    </div>

    <?php if($message): ?>
        <div class="alert alert-success animate-fade-in" style="margin-bottom: 2rem; border-left: 4px solid var(--success);">
            <strong>Success:</strong> <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger animate-fade-in" style="margin-bottom: 2rem; border-left: 4px solid var(--danger);">
            <strong>Error:</strong> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Create Team Form -->
    <div class="card primary-top animate-fade-in" style="margin-bottom: 4rem; overflow: hidden; padding: 2.5rem;">
        <div style="margin-bottom: 2.5rem;">
            <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em;">Initialize New Team Profile</h3>
            <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.25rem;">Create a formal entry for a capstone research group.</p>
        </div>

        <form method="POST">
            <input type="hidden" name="create_team" value="1">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.75rem; margin-bottom: 2rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Group Name</label>
                    <input type="text" name="team_name" class="form-control" required placeholder="e.g. Team Alpha" style="height: 52px; border-radius: var(--radius-md);">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Project Title</label>
                    <input type="text" name="project_title" class="form-control" required placeholder="Enter full research title" style="height: 52px; border-radius: var(--radius-md);">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Group Leader (Account)</label>
                    <select name="leader_id" class="form-control" required style="height: 52px; border-radius: var(--radius-md); appearance: auto;">
                        <option value="">-- Choose Account --</option>
                        <?php foreach($available_leaders as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.75rem; margin-bottom: 2rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Event Session</label>
                    <select name="event_id" class="form-control" style="height: 52px; border-radius: var(--radius-md); appearance: auto;">
                        <option value="">-- Assign Later --</option>
                        <?php foreach($events as $evt): ?>
                            <option value="<?= $evt['id'] ?>" <?= ($curr_event && $curr_event['id'] == $evt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($evt['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Presentation Schedule</label>
                    <input type="datetime-local" name="schedule_time" class="form-control" style="height: 52px; border-radius: var(--radius-md);">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 3rem;">
                <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">Project Abstract</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe the capstone project..." style="border-radius: var(--radius-md);"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="padding: 0 2.5rem; height: 56px; font-weight: 700; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                <span> Initialize Team Profile</span>
            </button>
        </form>
    </div>

    <!-- Team Matrix -->
    <h2 style="margin-bottom: 2rem; font-size: 1.5rem; letter-spacing: -0.01em;">Active Capstone Groups</h2>
    <div class="dashboard-grid">
        <?php foreach($teams as $team): ?>
            <div class="card stat-card animate-fade-in primary-top" style="display: flex; flex-direction: column; padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                    <div style="flex: 1; padding-right: 1rem;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.1em; display: block; margin-bottom: 0.25rem;">Group Entry</span>
                        <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em; color: var(--primary-dark); line-height: 1.1;"><?= htmlspecialchars($team['team_name']) ?></h3>
                    </div>
                    <form method="POST" onsubmit="return confirm('Permanently remove this team and all associated data?');">
                        <input type="hidden" name="delete_team" value="1">
                        <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                        <button type="submit" style="background: var(--danger-subtle); border: none; color: var(--danger); cursor: pointer; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; transition: all 0.2s;" title="Delete Team">&times;</button>
                    </form>
                </div>

                <div style="margin-bottom: 2rem;">
                    <h4 style="font-size: 0.9375rem; color: var(--text-main); font-weight: 700; margin: 0 0 1rem 0; line-height: 1.4;"><?= htmlspecialchars($team['project_title']) ?></h4>
                    <div style="display: grid; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8125rem; color: var(--text-light); border: 1px solid var(--border); padding: 0.6rem 1rem; border-radius: 12px; background: var(--light);">
                            <span style="font-size: 1.1rem;">👤</span>
                            <div style="display: flex; flex-direction: column;">
                                <span style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase; opacity: 0.7;">Team Leader</span>
                                <span style="font-weight: 700; color: var(--primary-dark);"><?= htmlspecialchars($team['leader_name'] ?: 'Not assigned') ?></span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.8125rem; color: var(--text-light); border: 1px solid var(--border); padding: 0.6rem 1rem; border-radius: 12px;">
                            <span style="font-size: 1.1rem;">📅</span>
                            <div style="display: flex; flex-direction: column;">
                                <span style="font-size: 0.6rem; font-weight: 800; text-transform: uppercase; opacity: 0.7;">Schedule</span>
                                <span style="font-weight: 600; color: var(--text-main);"><?= $team['schedule_time'] ? date('M j, g:i A', strtotime($team['schedule_time'])) : 'Schedule Not Set' ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px dashed var(--border); padding-top: 1.75rem; flex-grow: 1; display: flex; flex-direction: column;">
                    <div style="margin-bottom: 2rem;">
                        <p style="font-size: 0.7rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 6px; height: 6px; background: var(--primary); border-radius: 1px; rotate: 45deg;"></span>
                            Team Roster
                        </p>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.625rem;">
                            <?php if(isset($members_map[$team['id']])): ?>
                                <?php foreach($members_map[$team['id']] as $m): ?>
                                    <span style="background: var(--primary-subtle); padding: 0.4rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; color: var(--primary); border: 1px solid rgba(59, 66, 243, 0.1);">
                                        <?= htmlspecialchars($m['member_name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--text-light); font-size: 0.8125rem; font-style: italic; background: var(--light); padding: 0.75rem 1rem; border-radius: 10px; width: 100%; text-align: center;">No registered members.</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: auto;">
                        <p style="font-size: 0.7rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="width: 6px; height: 6px; background: var(--success); border-radius: 50%;"></span>
                            Project Outputs
                        </p>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php 
                            $doc_labels = ['emrad' => 'EMRAD', 'poster' => 'POSTER', 'brochure' => 'BROCHURE'];
                            $team_subs = $submissions_map[$team['id']] ?? [];
                            if(empty($team_subs)):
                            ?>
                                <div style="font-size: 0.75rem; color: var(--text-light); font-weight: 600; background: var(--light); padding: 0.75rem 1rem; border-radius: 10px; width: 100%; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="opacity: 0.5;">⏸️</span> No submissions found
                                </div>
                            <?php else: ?>
                                <?php foreach($doc_labels as $key => $lbl): if(isset($team_subs[$key])): ?>
                                    <a href="../<?= htmlspecialchars($team_subs[$key]['file_path']) ?>" target="_blank" 
                                       style="font-size: 0.7rem; background: var(--surface); color: var(--success); padding: 6px 14px; border-radius: 50px; text-decoration: none; font-weight: 800; border: 1.5px solid var(--success); display: flex; align-items: center; gap: 0.4rem; transition: all 0.2s;">
                                       📄 <?= $lbl ?>
                                    </a>
                                <?php endif; endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php render_footer(); ?>
