<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('dean');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_event'])) {
        $title = sanitize($_POST['title']);
        $date = $_POST['event_date'];
        $venue = sanitize($_POST['venue']);
        $sy = $_POST['school_year_id'];
        
        $stmt = $pdo->prepare("INSERT INTO events (title, event_date, venue, school_year_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $date, $venue, $sy]);
    }
    
    if (isset($_POST['update_event'])) {
        $id = intval($_POST['event_id']);
        $title = sanitize($_POST['title']);
        $date = $_POST['event_date'];
        $venue = sanitize($_POST['venue']);
        
        $stmt = $pdo->prepare("UPDATE events SET title = ?, event_date = ?, venue = ? WHERE id = ?");
        $stmt->execute([$title, $date, $venue, $id]);
    }
    
    if (isset($_POST['delete_event'])) {
        $id = intval($_POST['event_id']);
        // Check for dependencies? scores, teams, assignments will be deleted via CASCADE if set in SQL.
        // If not, we should be careful. Database schema shows ON DELETE CASCADE for most things.
        $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
    }
    
    header("Location: events.php");
    exit;
}

$events = $pdo->query("SELECT e.*, s.year_label FROM events e LEFT JOIN school_years s ON e.school_year_id = s.id ORDER BY event_date DESC")->fetchAll();
$active_sy = get_active_school_year($pdo);

render_head("Event Management");
render_navbar($_SESSION['full_name'], 'dean', '../', "Event Management");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Events</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">Manage evaluation sessions and rubric settings.</p>
        </div>
        <div>
            <div style="background: var(--success-subtle); padding: 0.6rem 1rem; border-radius: var(--radius-md); border: 1px solid var(--success); color: var(--success); font-weight: 700; font-size: 0.8125rem;">
                 Active: <?= $active_sy['year_label'] ?? 'None' ?>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 4rem; overflow: hidden; border-top: 5px solid var(--primary);">
        <div style="padding: 2rem;">
            <h3 style="margin-bottom: 2rem; font-size: 1.25rem;">Create New Event Session</h3>
            <form method="POST">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; align-items: flex-end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Event Title</label>
                        <input type="text" name="title" class="form-control" required placeholder=>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Date & Time</label>
                        <input type="datetime-local" name="event_date" class="form-control" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Venue</label>
                        <input type="text" name="venue" class="form-control" placeholder=>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <input type="hidden" name="add_event" value="1">
                        <input type="hidden" name="school_year_id" value="<?= $active_sy ? $active_sy['id'] : '' ?>">
                        <button type="submit" class="btn btn-primary" style="width: 100%; height: 46px;" <?= !$active_sy ? 'disabled' : '' ?>>
                             Create Event
                        </button>
                    </div>
                </div>
                <?php if(!$active_sy): ?>
                    <p style="color: var(--danger); font-size: 0.8125rem; margin-top: 1rem; font-weight: 600;">⚠️ Please set an active Academic Year first.</p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <h2 style="margin-bottom: 2rem; font-size: 1.5rem; letter-spacing: -0.01em;">Existing Sessions</h2>
    <div class="dashboard-grid">
        <?php foreach($events as $event): ?>
            <div class="card animate-fade-in" style="display: flex; flex-direction: column; padding: 2rem; border-left: 4px solid var(--primary-light); position: relative;">
                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h3 style="margin: 0; font-size: 1.25rem; line-height: 1.3;"><?= htmlspecialchars($event['title']) ?></h3>
                        <div style="display: flex; gap: 0.5rem;">
                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($event)) ?>)" style="background: none; border: none; cursor: pointer; color: var(--text-light); font-size: 1.1rem; padding: 0.25rem;" title="Edit Event">✏️</button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this event? This will remove all associated teams, criteria, and scores.')" style="display: inline;">
                                <input type="hidden" name="delete_event" value="1">
                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                <button type="submit" style="background: none; border: none; cursor: pointer; color: var(--danger); font-size: 1.1rem; padding: 0.25rem;" title="Delete Event">&times;</button>
                            </form>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem;">
                        <span style="color: var(--text-light); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">📅</span> <?= date('F j, g:i A', strtotime($event['event_date'])) ?>
                        </span>
                        <span style="color: var(--text-light); font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="font-size: 1rem;">📍</span> <?= htmlspecialchars($event['venue']) ?>
                        </span>
                    </div>
                </div>
                
                <div style="margin-top: auto; display: flex; gap: 0.75rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <a href="manage_event.php?id=<?= $event['id'] ?>" class="btn btn-secondary" style="flex: 1; font-size: 0.8125rem;">
                        ⚙️ Settings
                    </a>
                    <a href="results.php?event_id=<?= $event['id'] ?>" class="btn btn-primary" style="flex: 1; font-size: 0.8125rem;">
                        📊 Results
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="card animate-fade-in" style="width: 100%; max-width: 500px; padding: 2.5rem; position: relative;">
        <button onclick="closeEditModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer;">&times;</button>
        <h3 style="margin-bottom: 2rem; font-size: 1.5rem; letter-spacing: -0.01em;">Edit Event Details</h3>
        
        <form method="POST">
            <input type="hidden" name="update_event" value="1">
            <input type="hidden" name="event_id" id="edit_event_id">
            
            <div class="form-group">
                <label class="form-label">Event Title</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Date & Time</label>
                <input type="datetime-local" name="event_date" id="edit_date" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Venue</label>
                <input type="text" name="venue" id="edit_venue" class="form-control">
            </div>
            
            <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 2;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(event) {
    document.getElementById('edit_event_id').value = event.id;
    document.getElementById('edit_title').value = event.title;
    // Format date for datetime-local input (YYYY-MM-DDTHH:MM)
    const date = new Date(event.event_date);
    const formattedDate = date.toISOString().slice(0, 16);
    document.getElementById('edit_date').value = formattedDate;
    document.getElementById('edit_venue').value = event.venue;
    
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) {
        closeEditModal();
    }
}
</script>

<?php render_footer(); ?>
