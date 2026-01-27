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
    // Handle Document Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}

render_head("Project Artifacts");
render_navbar($_SESSION['full_name'], 'student', '../', 'Project Artifacts');
?>

<?php if($team): ?>
    <div class="page-header" style="margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.25rem; letter-spacing: -0.02em;">Capstone Project</h1>
            <p style="color: var(--text-light); margin-top: 0.5rem; font-size: 1.1rem;">
                Upload required documents for <strong><?= htmlspecialchars($team['team_name']) ?></strong>
            </p>
        </div>
    </div>

    <?php if($message): ?>
        <div class="alert alert-success animate-fade-in" style="margin-bottom: 2rem; border-left: 4px solid var(--success);">
            <strong>Success:</strong> <?= $message ?>
        </div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger animate-fade-in" style="margin-bottom: 2rem; border-left: 4px solid var(--border);">
            <strong>Error:</strong> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.5rem;">
        <div style="margin-bottom: 2.5rem;">
            <h3 style="margin: 0 0 0.5rem 0; font-size: 1.5rem; letter-spacing: -0.01em;">Document Submissions</h3>
            <p style="color: var(--text-light); font-size: 0.9375rem;">
                Upload required documents in <strong>PDF format</strong> to provide panelists with review material.
            </p>
        </div>
        
        <div style="display: grid; gap: 1.5rem;">
            <?php 
            $types = [
                'emrad' => ['label' => 'EMRAD Document', 'icon' => '📄', 'desc' => 'Extended Methodology, Results, Analysis & Discussion'],
                'poster' => ['label' => 'Research Poster', 'icon' => '🖼️', 'desc' => 'Visual presentation of your research findings'],
                'brochure' => ['label' => 'Project Brochure', 'icon' => '📚', 'desc' => 'Marketing material for your capstone project']
            ];
            foreach($types as $key => $info): 
                $sub = $submissions[$key] ?? null;
            ?>
            <div style="background: white; padding: 2rem; border-radius: var(--radius-lg); border: 2px solid <?= $sub ? 'var(--success)' : 'var(--border)' ?>; transition: all 0.3s ease; position: relative; overflow: hidden;">
                <!-- Status Badge -->
                <?php if($sub): ?>
                    <div style="position: absolute; top: 1rem; right: 1rem; background: var(--success); color: white; padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 0.375rem;">
                        <span>✓</span> SUBMITTED
                    </div>
                <?php else: ?>
                    <div style="position: absolute; top: 1rem; right: 1rem; background: var(--warning); color: white; padding: 0.375rem 0.875rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800; display: flex; align-items: center; gap: 0.375rem;">
                        <span>⚠️</span> PENDING
                    </div>
                <?php endif; ?>

                <div style="display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div style="background: <?= $sub ? 'var(--success-subtle)' : 'var(--primary-subtle)' ?>; color: <?= $sub ? 'var(--success)' : 'var(--primary)' ?>; width: 72px; height: 72px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 1px solid rgba(0,0,0,0.05); flex-shrink: 0;">
                        <?= $info['icon'] ?>
                    </div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; font-weight: 700; color: var(--dark);">
                            <?= $info['label'] ?>
                        </h4>
                        <p style="margin: 0; font-size: 0.875rem; color: var(--text-light); line-height: 1.5;">
                            <?= $info['desc'] ?>
                        </p>
                        <?php if($sub): ?>
                            <div style="margin-top: 0.75rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <span style="font-size: 0.8125rem; color: var(--text-light);">
                                    📅 Uploaded: <strong><?= date('M j, Y • g:i A', strtotime($sub['uploaded_at'])) ?></strong>
                                </span>
                                <span style="font-size: 0.8125rem; color: var(--text-light);">
                                    📎 <strong><?= htmlspecialchars($sub['original_name']) ?></strong>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div id="action-buttons-<?= $key ?>" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <?php if($sub): ?>
                        <a href="../<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 700;">
                             Preview
                        </a>
                    <?php endif; ?>
                    <button onclick="document.getElementById('upload-area-<?= $key ?>').style.display='block'; document.getElementById('action-buttons-<?= $key ?>').style.display='none';" class="btn btn-primary" style="padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 700;">
                        <?= $sub ? ' Update File' : '📤 Upload PDF' ?>
                    </button>
                </div>

                <!-- Upload Area (Hidden by default) -->
                <div id="upload-area-<?= $key ?>" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed var(--border);">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_doc" value="1">
                        <input type="hidden" name="doc_type" value="<?= $key ?>">
                        
                        <div style="background: var(--light); padding: 1.5rem; border-radius: var(--radius-md); border: 2px dashed var(--border); margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.75rem; font-weight: 700; font-size: 0.875rem; color: var(--text-main);">
                                Select PDF File
                            </label>
                            <input type="file" name="pdf_file" accept=".pdf" class="form-control" required style="font-size: 0.875rem;">
                            <p style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--text-light); text-align: center;">
                                📋 Maximum file size: 10MB • Format: PDF only
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                            <button type="button" onclick="document.getElementById('upload-area-<?= $key ?>').style.display='none'; document.getElementById('action-buttons-<?= $key ?>').style.display='flex';" class="btn btn-secondary" style="padding: 0.625rem 1.5rem; font-size: 0.875rem;">
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-primary" style="padding: 0.625rem 1.5rem; font-size: 0.875rem; font-weight: 700;">
                                ✅ Submit Upload
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Info Box -->
        <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, var(--primary-subtle) 0%, rgba(112, 117, 255, 0.1) 100%); border-radius: var(--radius-lg); border-left: 4px solid var(--primary);">
            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                <div style="font-size: 1.5rem; flex-shrink: 0;">💡</div>
                <div>
                    <h4 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 700; color: var(--primary-dark);">
                        Important Reminders
                    </h4>
                    <ul style="margin: 0; padding-left: 1.25rem; color: var(--text-main); font-size: 0.875rem; line-height: 1.6;">
                        <li>All documents must be in PDF format</li>
                        <li>Ensure files are properly formatted and readable before uploading</li>
                        <li>Panelists will review these documents during evaluation</li>
                        <li>You can update documents anytime before the defense schedule</li>
                    </ul>
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

<?php render_footer(); ?>
