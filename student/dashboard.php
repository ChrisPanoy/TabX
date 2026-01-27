<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../components/ui.php';
requireRole('student');

$student_id = $_SESSION['user_id'];
$message = '';
$error = '';

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
    // Handle Member Management
    // Handle Post Requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Member Management
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

        // Document Submission
        if (isset($_POST['upload_doc'])) {
            $type = $_POST['doc_type'];
            if (in_array($type, ['emrad', 'poster', 'brochure'])) {
                if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['pdf_file']['tmp_name'];
                    $fileName = $_FILES['pdf_file']['name'];
                    $fileSize = $_FILES['pdf_file']['size'];
                    $fileType = $_FILES['pdf_file']['type'];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));

                    if ($fileExtension === 'pdf') {
                        $uploadFileDir = '../uploads/submissions/';
                        if (!is_dir($uploadFileDir)) {
                            mkdir($uploadFileDir, 0777, true);
                        }
                        $newFileName = $team['id'] . "_" . $type . "_" . time() . ".pdf";
                        $dest_path = $uploadFileDir . $newFileName;
                        $db_path = 'uploads/submissions/' . $newFileName;

                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            // Check for existing
                            $stmt_check = $pdo->prepare("SELECT id, file_path FROM submissions WHERE team_id = ? AND file_type = ?");
                            $stmt_check->execute([$team['id'], $type]);
                            $existing = $stmt_check->fetch();

                            if ($existing) {
                                // Delete old file
                                $old_file = '../' . $existing['file_path'];
                                if (file_exists($old_file)) {
                                    unlink($old_file);
                                }
                                $stmt_upd = $pdo->prepare("UPDATE submissions SET file_path = ?, original_name = ?, uploaded_at = CURRENT_TIMESTAMP WHERE id = ?");
                                $stmt_upd->execute([$db_path, $fileName, $existing['id']]);
                            } else {
                                $stmt_ins = $pdo->prepare("INSERT INTO submissions (team_id, file_type, file_path, original_name) VALUES (?, ?, ?, ?)");
                                $stmt_ins->execute([$team['id'], $type, $db_path, $fileName]);
                            }
                            $message = ucfirst($type) . " uploaded successfully.";
                        } else {
                            $error = "There was an error moving the uploaded file.";
                        }
                    } else {
                        $error = "Only PDF files are allowed.";
                    }
                } else {
                    $error = "Please select a file to upload.";
                }
            }
        }
    }

    // Get current submissions
    $stmt_sub = $pdo->prepare("SELECT file_type, submissions.* FROM submissions WHERE team_id = ?");
    $stmt_sub->execute([$team['id']]);
    $submissions = $stmt_sub->fetchAll(PDO::FETCH_UNIQUE); // Keyed by file_type

    // Get Members
    $stmt_mem = $pdo->prepare("SELECT id, member_name, role_in_project FROM team_members WHERE team_id = ?");
    $stmt_mem->execute([$team['id']]);
    $members = $stmt_mem->fetchAll();

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

render_head("Leader Dashboard");
render_navbar($_SESSION['full_name'], 'student');
?>

<div class="container" style="margin-top: 3rem; padding-bottom: 5rem;">
    <?php if($team): ?>
        <div class="page-header" style="margin-bottom: 3rem;">
            <div>
                <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Capstone Group Hub</h1>
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

        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <!-- Left Side: Members & Info -->
            <div style="display: grid; gap: 2rem;">
                <!-- Members Management -->
                <div class="card" style="padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <h3 style="margin: 0; font-size: 1.25rem; letter-spacing: -0.01em;">Group Members</h3>
                        <span style="background: var(--primary-subtle); color: var(--primary); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                            <?= count($members) ?> Enrolled
                        </span>
                    </div>

                    <div style="margin-bottom: 2.5rem;">
                         <form method="POST" style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 1rem; align-items: flex-end; background: var(--light); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border);">
                            <input type="hidden" name="add_member" value="1">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light);">Full Name</label>
                                <input type="text" name="member_name" class="form-control" placeholder="Enter student name" required>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-light);">Project Role</label>
                                <input type="text" name="member_role" class="form-control" placeholder="e.g. Lead Coder">
                            </div>
                            <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 1.5rem;">Add Member</button>
                         </form>
                    </div>

                    <div class="table-container" style="border: 1px solid var(--border); border-radius: var(--radius-md);">
                        <table style="margin-bottom: 0;">
                            <thead>
                                <tr style="background: var(--light);">
                                    <th style="padding: 1rem;">Member Name</th>
                                    <th style="padding: 1rem;">Designation</th>
                                    <th style="width: 50px; padding: 1rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($members as $m): ?>
                                <tr>
                                    <td style="padding: 1.25rem 1rem;">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 32px; height: 32px; border-radius: 8px; background: var(--secondary-subtle, #f1f5f9); color: var(--text-main); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">
                                                <?= substr($m['member_name'], 0, 1) ?>
                                            </div>
                                            <strong><?= htmlspecialchars($m['member_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td style="padding: 1.25rem 1rem;">
                                        <span style="font-size: 0.8125rem; color: var(--text-light); font-weight: 600; background: var(--light); padding: 0.25rem 0.6rem; border-radius: 5px;">
                                            <?= htmlspecialchars($m['role_in_project'] ?: 'Member') ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1.25rem 1rem; text-align: right;">
                                        <form method="POST" onsubmit="return confirm('Remove this member from the group?');" style="display: inline;">
                                            <input type="hidden" name="remove_member" value="1">
                                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                            <button type="submit" style="background: var(--danger-subtle); border: none; color: var(--danger); cursor: pointer; width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;">&times;</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($members)): ?>
                                    <tr><td colspan="3" style="text-align: center; color: var(--text-light); padding: 3rem;">No members added yet. Enrollment is required for individual evaluation.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Document Submissions -->
                <div class="card" style="padding: 2rem;">
                    <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem; letter-spacing: -0.01em;">Project Artifacts</h3>
                    <p style="color: var(--text-light); font-size: 0.9375rem; margin-bottom: 2rem;">
                        Upload required documents in **PDF format** to provide panelists with review material.
                    </p>
                    
                    <div style="display: grid; gap: 1.25rem;">
                        <?php 
                        $types = [
                            'emrad' => ['label' => 'EMRAD Document', 'icon' => '📄'],
                            'poster' => ['label' => 'Research Poster', 'icon' => '🖼️'],
                            'brochure' => ['label' => 'Project Brochure', 'icon' => '📚']
                        ];
                        foreach($types as $key => $info): 
                            $sub = $submissions[$key] ?? null;
                        ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border); transition: all 0.2s ease;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 1.25rem;">
                                    <div style="background: <?= $sub ? 'var(--success-subtle)' : 'var(--primary-subtle)' ?>; color: <?= $sub ? 'var(--success)' : 'var(--primary)' ?>; width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 1px solid rgba(0,0,0,0.03);">
                                        <?= $info['icon'] ?>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0; font-size: 1.05rem; font-weight: 700;"><?= $info['label'] ?></h4>
                                        <div style="margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <?php if($sub): ?>
                                                <span style="font-size: 0.75rem; color: var(--success); font-weight: 700; display: flex; align-items: center; gap: 0.25rem;">
                                                    <span style="font-size: 1rem;">✓</span> Completed
                                                </span>
                                                <span style="font-size: 0.75rem; color: var(--text-light);">• <?= date('M j, g:i A', strtotime($sub['uploaded_at'])) ?></span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: var(--danger); font-weight: 700; display: flex; align-items: center; gap: 0.25rem;">
                                                    <span style="font-size: 1rem;">⚠️</span> Action Required
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 0.75rem;">
                                    <?php if($sub): ?>
                                        <a href="../<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 700;">
                                            Preview
                                        </a>
                                    <?php endif; ?>
                                    <button onclick="document.getElementById('upload-area-<?= $key ?>').style.display='block'; this.parentElement.parentElement.style.display='none';" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 700;">
                                        <?= $sub ? 'Update File' : 'Upload PDF' ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Interactive Upload Area -->
                            <div id="upload-area-<?= $key ?>" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed var(--border);">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="upload_doc" value="1">
                                    <input type="hidden" name="doc_type" value="<?= $key ?>">
                                    <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 0.75rem; align-items: center;">
                                        <input type="file" name="pdf_file" accept=".pdf" class="form-control" required style="font-size: 0.8125rem;">
                                        <button type="submit" class="btn btn-primary" style="height: 42px; font-size: 0.8125rem; font-weight: 700;">Submit</button>
                                        <button type="button" onclick="const p = this.closest('.card'); this.parentElement.parentElement.parentElement.style.display='none'; this.parentElement.parentElement.parentElement.previousElementSibling.style.display='flex';" class="btn btn-secondary" style="height: 42px; font-size: 0.8125rem;">Cancel</button>
                                    </div>
                                    <p style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-light); text-align: center;">Maximum file size: 10MB. Format: PDF only.</p>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Performance & Scheduling -->
            <div style="display: grid; gap: 2rem; align-content: start;">
                <!-- Result Visualization -->
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

                <!-- Session Details -->
                <div class="card" style="padding: 2rem; background: var(--primary-dark); color: white; border: none; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -10px; right: -10px; font-size: 4rem; opacity: 0.1;">⏱️</div>
                    
                    <h4 style="margin-bottom: 1.5rem; color: rgba(255,255,255,0.6); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800;">Defense Schedule</h4>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <div style="font-size: 1.125rem; font-weight: 600; color: rgba(255,255,255,0.9);"><?= $team['schedule_time'] ? date('l, F j, Y', strtotime($team['schedule_time'])) : 'Awaiting Schedule' ?></div>
                        <div style="font-size: 2.5rem; font-weight: 900; color: white; letter-spacing: -0.02em; margin-top: 0.25rem;">
                            <?= $team['schedule_time'] ? date('g:i A', strtotime($team['schedule_time'])) : '--:--' ?>
                        </div>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.1); padding: 1.25rem; border-radius: var(--radius-md); border-left: 3px solid var(--primary-light);">
                        <span style="display: block; font-size: 0.65rem; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 800; margin-bottom: 0.4rem;">Presentation Venue</span>
                        <strong style="font-size: 1rem; color: white;">📍 <?= htmlspecialchars($team['venue'] ?: 'Venue to be announced') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card animate-fade-in" style="text-align: center; padding: 5rem;">
            <h2 style="color: var(--text-light);">No Team assigned to your account.</h2>
            <p style="margin-top: 1rem;">Please coordinate with the Dean to initialize your Capstone Group.</p>
        </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
