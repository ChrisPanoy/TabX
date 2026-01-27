<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('dean');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = sanitize($_POST['username']);
        $fullname = sanitize($_POST['full_name']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $password, $fullname, $role]);
            $message = "User account created successfully!";
        } catch (PDOException $e) {
            $error = "Username already exists.";
        }
    }

    if (isset($_POST['delete_user'])) {
        $id = intval($_POST['user_id']);
        if ($id != $_SESSION['user_id']) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $message = "User deleted.";
        } else {
            $error = "You cannot delete your own account.";
        }
    }

    if (isset($_POST['update_user'])) {
        $id = intval($_POST['user_id']);
        $fullname = sanitize($_POST['full_name']);
        $role = $_POST['role'];
        
        $pdo->prepare("UPDATE users SET full_name = ?, role = ? WHERE id = ?")->execute([$fullname, $role, $id]);
        
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$pass, $id]);
        }
        $message = "User updated.";
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();

render_head("User Management");
render_navbar($_SESSION['full_name'], 'dean', '../', "Account Management");
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Account Management</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">Create and manage system access for all participants.</p>
        </div>
        <button onclick="openCreateModal()" class="btn btn-primary" style="padding: 0.75rem 1.5rem; border-radius: var(--radius-lg);">
            <span style="font-size: 1.25rem; line-height: 1;">+</span>
            <span>Create New Account</span>
        </button>
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

    <div class="card stat-card animate-fade-in primary-top" style="padding: 0;">
        <div style="padding: 1.75rem 2rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; letter-spacing: -0.01em;">User Directory</h3>
                <p style="color: var(--text-light); font-size: 0.8125rem; margin-top: 0.15rem;">Active accounts registered in the system.</p>
            </div>
            <div style="background: var(--surface); padding: 4px 12px; border-radius: 20px; border: 1px solid var(--border); font-size: 0.7rem; font-weight: 800; color: var(--text-light);">TOTAL: <?= count($users) ?></div>
        </div>
        <div class="table-responsive">
            <table style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th style="width: 180px;">Access Role</th>
                    <th>User Identity</th>
                    <th>Account Info</th>
                    <th style="width: 150px; text-align: right;">Operations</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td>
                        <?php 
                            $role_color = match($u['role']) {
                                'dean' => 'indigo',
                                'panelist' => 'blue',
                                default => 'emerald'
                            };
                        ?>
                        <span style="font-size: 0.65rem; font-weight: 800; padding: 0.4rem 0.75rem; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.05em;
                            background: var(--<?= $role_color ?>-subtle, #eef2ff); color: var(--<?= $role_color ?>-dark, #312e81); border: 1px solid rgba(0,0,0,0.05);">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; border-radius: 12px; background: var(--primary-subtle); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800;">
                                <?= substr($u['full_name'], 0, 1) ?>
                            </div>
                            <strong><?= htmlspecialchars($u['full_name']) ?></strong>
                        </div>
                    </td>
                    <td>
                        <div style="display: flex; flex-direction: column;">
                            <span style="font-size: 0.875rem; color: var(--text-main); font-family: monospace;"><?= htmlspecialchars($u['username']) ?></span>
                            <span style="font-size: 0.75rem; color: var(--text-light);">ID: #<?= str_pad($u['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <button onclick='editUser(<?= json_encode($u) ?>)' class="btn btn-secondary" style="padding: 0.5rem; width: 36px; height: 36px;" title="Edit Account">✏️</button>
                            <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Permanently delete this account?')">
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.5rem; width: 36px; height: 36px; color: var(--danger);" title="Delete Account">🗑️</button>
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

<!-- Modern Modal -->
<div id="userModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(7, 9, 25, 0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 1000; align-items:center; justify-content:center; padding: 1.5rem;">
    <div class="card animate-fade-in primary-top" style="width: 100%; max-width: 480px; padding: 2.5rem; border: none; box-shadow: var(--shadow-lg); background: var(--surface);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="modalTitle" style="font-size: 1.5rem; margin: 0;">Create User Account</h3>
            <button onclick="closeModal()" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: var(--text-light);">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" id="modal_full_name" class="form-control" required placeholder="John Doe">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">System Role</label>
                    <select name="role" id="modal_role" class="form-control" required style="appearance: auto;">
                        <option value="student">Student (Capstone Leader)</option>
                        <option value="panelist">Panelist (Judge)</option>
                        <option value="dean">Administrator (Dean)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="modal_username" class="form-control" required placeholder="jdoe_2026">
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label class="form-label">Account Password <span id="pwdLabel" style="font-weight: normal; font-size: 0.75rem; color: var(--primary);">(Optional if updating)</span></label>
                <input type="password" name="password" id="modal_password" class="form-control" placeholder="••••••••">
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button type="submit" name="add_user" id="submitBtn" class="btn btn-primary" style="flex: 2; height: 50px; font-weight: 700;">Save Account Data</button>
                <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1; height: 50px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('userModal');
const submitBtn = document.getElementById('submitBtn');
const pwdLabel = document.getElementById('pwdLabel');

function openCreateModal() {
    document.getElementById('modalTitle').innerText = 'Create User Account';
    document.getElementById('modal_user_id').value = '';
    document.getElementById('modal_full_name').value = '';
    document.getElementById('modal_username').value = '';
    document.getElementById('modal_username').readOnly = false;
    document.getElementById('modal_role').value = 'student';
    document.getElementById('modal_password').required = true;
    pwdLabel.style.display = 'none';
    submitBtn.name = 'add_user';
    submitBtn.innerText = 'Create Account';
    modal.style.display = 'flex';
}

function editUser(user) {
    document.getElementById('modalTitle').innerText = 'Edit User Account';
    document.getElementById('modal_user_id').value = user.id;
    document.getElementById('modal_full_name').value = user.full_name;
    document.getElementById('modal_username').value = user.username;
    document.getElementById('modal_username').readOnly = true;
    document.getElementById('modal_role').value = user.role;
    document.getElementById('modal_password').required = false;
    pwdLabel.style.display = 'inline';
    submitBtn.name = 'update_user';
    submitBtn.innerText = 'Update Account';
    modal.style.display = 'flex';
}

function closeModal() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) closeModal();
}
</script>

<?php render_footer(); ?>
