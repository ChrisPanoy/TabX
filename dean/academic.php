<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('dean');

$message = '';
$error = '';
$edit_sy = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_sy') {
            $year = sanitize($_POST['year_label']);
            $pdo->prepare("INSERT INTO school_years (year_label, status) VALUES (?, 'inactive')")->execute([$year]);
            $message = "Academic year added successfully.";
        } elseif ($_POST['action'] === 'set_active_sy') {
            $id = intval($_POST['sy_id']);
            $pdo->query("UPDATE school_years SET status = 'inactive'");
            $pdo->prepare("UPDATE school_years SET status = 'active' WHERE id = ?")->execute([$id]);
            $message = "Primary academic year updated.";
        } elseif ($_POST['action'] === 'delete_sy') {
            $id = intval($_POST['sy_id']);
            // Prevent deleting active SY
            $stmt = $pdo->prepare("SELECT status FROM school_years WHERE id = ?");
            $stmt->execute([$id]);
            $curr = $stmt->fetch();
            if ($curr && $curr['status'] === 'active') {
                $error = "Cannot delete the active academic year.";
            } else {
                $pdo->prepare("DELETE FROM school_years WHERE id = ?")->execute([$id]);
                $message = "Academic year removed.";
            }
        } elseif ($_POST['action'] === 'update_sy') {
            $id = intval($_POST['sy_id']);
            $year = sanitize($_POST['year_label']);
            $pdo->prepare("UPDATE school_years SET year_label = ? WHERE id = ?")->execute([$year, $id]);
            $message = "Academic year updated.";
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM school_years WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_sy = $stmt->fetch();
}

$school_years = $pdo->query("SELECT * FROM school_years ORDER BY id DESC")->fetchAll();

render_head("Academic Configuration");
render_navbar($_SESSION['full_name'], 'dean', '../', "Academic Year");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Academic Year</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">Manage school years and define the active system session.</p>
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

    <div class="dashboard-grid">
        <!-- Form Section -->
        <div class="card animate-fade-in primary-top">
            <div style="margin-bottom: 2rem;">
                <?php if($edit_sy): ?>
                    <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em;">Update Academic Session</h3>
                    <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.25rem;">Modify the label for this school year.</p>
                <?php else: ?>
                    <h3 style="margin: 0; font-size: 1.5rem; letter-spacing: -0.02em;">Register New Session</h3>
                    <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.25rem;">Create a new entry for the academic calendar.</p>
                <?php endif; ?>
            </div>

            <form method="POST" class="animate-fade-in">
                <input type="hidden" name="action" value="<?= $edit_sy ? 'update_sy' : 'add_sy' ?>">
                <?php if($edit_sy): ?>
                    <input type="hidden" name="sy_id" value="<?= $edit_sy['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label class="form-label" style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; letter-spacing: 0.05em; color: var(--text-light);">School Year Label</label>
                    <input type="text" name="year_label" value="<?= $edit_sy ? htmlspecialchars($edit_sy['year_label']) : '' ?>" placeholder="e.g. 2025-2026" class="form-control" required style="height: 52px; font-size: 1rem; border-radius: var(--radius-md);">
                    <small style="color: var(--text-light); display: block; margin-top: 0.75rem; font-style: italic;">
                        <?= $edit_sy ? 'Ensure the label follows the standard academic format.' : 'New sessions are initialized as inactive.' ?>
                    </small>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 2; height: 52px; font-weight: 700; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                        <?= $edit_sy ? '💾 Update Session' : '✨ Create Session' ?>
                    </button>
                    <?php if($edit_sy): ?>
                        <a href="academic.php" class="btn btn-secondary" style="flex: 1; height: 52px; display: flex; align-items: center; justify-content: center; font-weight: 700; border-radius: var(--radius-md);">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- List Section -->
        <div class="card stat-card animate-fade-in primary-top" style="padding: 0; animation-delay: 0.1s;">
            <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; letter-spacing: -0.01em;">Session Archives</h3>
                    <p style="color: var(--text-light); font-size: 0.8125rem; margin-top: 0.15rem;">Review and manage past academic years.</p>
                </div>
                <div style="background: var(--surface); padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border); font-size: 0.7rem; font-weight: 800; color: var(--text-light);">HISTORY</div>
            </div>
            <div class="table-responsive">
                <table style="margin-bottom: 0;">
                    <thead>
                        <tr style="background: white;">
                            <th style="padding: 1.25rem 2rem;">Academic Year</th>
                            <th style="width: 120px; text-align: center; padding: 1.25rem 1rem;">Status</th>
                            <th style="width: 250px; text-align: right; padding: 1.25rem 2rem;">Management</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($school_years as $sy): ?>
                        <tr>
                            <td style="padding: 1.5rem 2rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="width: 10px; height: 10px; border-radius: 50%; background: <?= $sy['status'] === 'active' ? 'var(--success)' : 'var(--border)' ?>"></div>
                                    <strong style="font-size: 1.05rem; color: var(--dark);"><?= htmlspecialchars($sy['year_label']) ?></strong>
                                </div>
                            </td>
                            <td style="text-align: center; padding: 1.5rem 1rem;">
                                <span style="font-size: 0.65rem; font-weight: 800; padding: 5px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em; border-width: 1px; border-style: solid;
                                    <?= $sy['status'] === 'active' ? 'background: #ecfdf5; color: #059669; border-color: #10b981;' : 'background: #f8fafc; color: #64748b; border-color: #e2e8f0;' ?>">
                                    <?= $sy['status'] ?>
                                </span>
                            </td>
                            <td style="text-align: right; padding: 1.5rem 2rem;">
                                <div style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                    <?php if($sy['status'] !== 'active'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="set_active_sy">
                                            <input type="hidden" name="sy_id" value="<?= $sy['id'] ?>">
                                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.75rem; font-weight: 700; border-radius: 10px; box-shadow: var(--shadow-sm);">Activate Now</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="?edit=<?= $sy['id'] ?>" class="btn btn-secondary" style="width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; border-radius: 8px;" title="Edit">✏️</a>

                                    <?php if($sy['status'] !== 'active'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Permanently remove this academic session and all its associated configuration?');">
                                            <input type="hidden" name="action" value="delete_sy">
                                            <input type="hidden" name="sy_id" value="<?= $sy['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" style="width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; color: var(--danger); border-radius: 8px; border-color: var(--danger-subtle);" title="Delete">🗑️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
