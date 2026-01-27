<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('student');

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get student's team
$stmt = $pdo->prepare("
    SELECT t.*, e.title as event_title 
    FROM teams t
    LEFT JOIN events e ON t.event_id = e.id
    WHERE t.leader_id = ?
");
$stmt->execute([$student_id]);
$team = $stmt->fetch();

if ($team) {
    // Handle Member Management
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_member'])) {
            $name = sanitize($_POST['member_name']);
            $role = sanitize($_POST['member_role']);
            if ($name) {
                $pdo->prepare("INSERT INTO team_members (team_id, member_name, role_in_project) VALUES (?, ?, ?)")
                    ->execute([$team['id'], $name, $role]);
                $message = "Member added successfully.";
            }
        }
        if (isset($_POST['remove_member'])) {
            $mid = intval($_POST['member_id']);
            $pdo->prepare("DELETE FROM team_members WHERE id = ? AND team_id = ?")
                ->execute([$mid, $team['id']]);
            $message = "Member removed.";
        }
    }

    // Get Members
    $stmt_mem = $pdo->prepare("SELECT id, member_name, role_in_project FROM team_members WHERE team_id = ?");
    $stmt_mem->execute([$team['id']]);
    $members = $stmt_mem->fetchAll();
}

render_head("Group Members");
render_navbar($_SESSION['full_name'], 'student', '../', 'Group Members');
?>

<?php if($team): ?>
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Group Members</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">
                Manage your capstone team members for <strong><?= htmlspecialchars($team['team_name']) ?></strong>
            </p>
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

    <div class="card" style="padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.01em;">Team Roster</h3>
                <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 0.9375rem;">
                    Add and manage members who will be individually evaluated
                </p>
            </div>
            <span style="background: var(--primary-subtle); color: var(--primary); padding: 0.5rem 1.25rem; border-radius: 20px; font-size: 0.875rem; font-weight: 700;">
                <?= count($members) ?> Members Enrolled
            </span>
        </div>

        <!-- Add Member Form -->
        <div style="margin-bottom: 3rem;">
            <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: flex-end; background: var(--light); padding: 2rem; border-radius: var(--radius-lg); border: 2px dashed var(--border);">
                <input type="hidden" name="add_member" value="1">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light);">Full Name</label>
                    <input type="text" name="member_name" class="form-control" placeholder="Enter student name" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light);">Project Role</label>
                    <input type="text" name="member_role" class="form-control" placeholder="e.g. Lead Coder">
                </div>
                <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 2rem; font-weight: 700;">
                    ➕ Add Member
                </button>
            </form>
        </div>

        <!-- Members List -->
        <?php if(!empty($members)): ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach($members as $m): ?>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                            <div style="width: 56px; height: 56px; border-radius: 14px; background: linear-gradient(135deg, var(--primary-subtle) 0%, var(--primary-light) 100%); color: var(--primary-dark); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; box-shadow: var(--shadow-sm);">
                                <?= substr($m['member_name'], 0, 1) ?>
                            </div>
                            <div>
                                <h4 style="margin: 0; font-size: 1.125rem; font-weight: 700; color: var(--dark);">
                                    <?= htmlspecialchars($m['member_name']) ?>
                                </h4>
                                <div style="margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-size: 0.8125rem; color: var(--text-light); font-weight: 600; background: var(--light); padding: 0.25rem 0.75rem; border-radius: 6px;">
                                        <?= htmlspecialchars($m['role_in_project'] ?: 'Member') ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Remove this member from the group?');" style="display: inline;">
                            <input type="hidden" name="remove_member" value="1">
                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 0.625rem 1.25rem; font-weight: 700;">
                                 Remove
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; background: var(--light); border-radius: var(--radius-lg); border: 2px dashed var(--border);">
                <div style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;">👥</div>
                <h3 style="color: var(--text-light); margin-bottom: 0.5rem;">No members added yet</h3>
                <p style="color: var(--text-light); font-size: 0.9375rem;">
                    Use the form above to add team members for individual evaluation
                </p>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card animate-fade-in" style="text-align: center; padding: 5rem;">
        <h2 style="color: var(--text-light);">No Team assigned to your account.</h2>
        <p style="margin-top: 1rem;">Please coordinate with the Dean to initialize your Capstone Group.</p>
    </div>
<?php endif; ?>

<?php render_footer(); ?>
