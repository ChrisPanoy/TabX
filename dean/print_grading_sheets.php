<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
requireRole('dean');

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) die("Event ID required.");

// Fetch Event
$evt = $pdo->prepare("SELECT title, event_date, venue FROM events WHERE id = ?");
$evt->execute([$event_id]);
$event = $evt->fetch();

// Fetch Teams
$stmt = $pdo->prepare("SELECT id, team_name, project_title, schedule_time FROM teams WHERE event_id = ? ORDER BY id");
$stmt->execute([$event_id]);
$teams = $stmt->fetchAll();

// Fetch Rubrics
$stmt_crit = $pdo->prepare("SELECT criteria_name, category, weight, min_score, max_score FROM criteria WHERE event_id = ? ORDER BY type, category ASC, display_order");
$stmt_crit->execute([$event_id]);
$all_criteria = $stmt_crit->fetchAll();

$grouped_criteria = [];
foreach($all_criteria as $c) {
    $grouped_criteria[($c['category'] ?: 'General')][] = $c;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grading Sheet - <?= htmlspecialchars($event['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --dark: #0f172a;
            --text-main: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
        }
        body { font-family: 'Inter', sans-serif; padding: 40px; color: var(--text-main); line-height: 1.5; }
        .no-print { 
            position: sticky; top: 0; background: white; padding: 20px; 
            border-bottom: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); 
            z-index: 100; margin-bottom: 40px; display: flex; justify-content: center; gap: 1rem;
        }
        .header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 3px double var(--dark); }
        .sheet { page-break-after: always; max-width: 800px; margin: 0 auto 50px auto; padding: 50px; background: white; border: 1px solid var(--border); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        h1, h2, h3 { font-family: 'Outfit', sans-serif; margin: 0; color: var(--dark); }
        h2 { font-weight: 800; letter-spacing: -0.02em; font-size: 1.75rem; text-transform: uppercase; }
        h3 { font-weight: 400; color: var(--text-light); margin-top: 5px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; }
        .meta-label { font-size: 0.7rem; font-weight: 800; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; display: block; }
        .meta-val { font-weight: 700; color: var(--dark); }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { border: 1px solid #cbd5e1; padding: 15px; text-align: left; }
        th { background: #f1f5f9; font-family: 'Outfit', sans-serif; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; color: var(--text-light); letter-spacing: 0.05em; }
        .score-col { width: 120px; background: white !important; }
        .signature-area { margin-top: 60px; display: flex; justify-content: space-between; align-items: flex-end; }
        .sig-box { width: 250px; border-top: 2px solid var(--dark); text-align: center; padding-top: 10px; font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .btn { padding: 12px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-secondary { background: #f1f5f9; color: var(--text-main); border: 1px solid var(--border); }
        @media print {
            .no-print { display: none; }
            body { padding: 0; background: white; }
            .sheet { border: none; box-shadow: none; padding: 0; width: 100%; max-width: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">
            <span>🖨️</span> Print All Sheets (PDF)
        </button>
        <a href="results.php?event_id=<?= $event_id ?>" class="btn btn-secondary">
            <span>&larr;</span> Back to Results
        </a>
    </div>

    <!-- Generate one sheet per team -->
    <?php foreach($teams as $team): ?>
    <div class="sheet">
        <div class="header">
            <h2>Competition Evaluation Sheet</h2>
            <h3><?= htmlspecialchars($event['title']) ?></h3>
            <p style="margin-top: 10px; font-weight: 500; font-size: 0.875rem; color: var(--text-light);">
                📅 <?= date('F j, Y', strtotime($event['event_date'])) ?> &nbsp; | &nbsp; 📍 <?= htmlspecialchars($event['venue']) ?>
            </p>
        </div>

        <div class="meta">
            <div>
                <span class="meta-label">Project Representative</span>
                <span class="meta-val" style="font-size: 1.1rem;"><?= htmlspecialchars($team['team_name']) ?></span>
                <div style="margin-top: 10px;">
                    <span class="meta-label">Project Title</span>
                    <span class="meta-val" style="font-size: 0.9rem;"><?= htmlspecialchars($team['project_title']) ?></span>
                </div>
            </div>
            <div style="border-left: 2px solid #e2e8f0; padding-left: 20px;">
                <span class="meta-label">Assigned Evaluator</span>
                <div style="border-bottom: 2px solid #cbd5e1; height: 35px; margin-top: 10px;"></div>
                <div style="margin-top: 20px;">
                    <span class="meta-label">Date of Evaluation</span>
                    <div style="border-bottom: 2px solid #cbd5e1; height: 35px; margin-top: 10px;"></div>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Criteria & Performance Indicators</th>
                    <th style="width: 80px; text-align: center;">Weight</th>
                    <th style="width: 100px; text-align: center;">Target</th>
                    <th class="score-col" style="text-align: center;">Actual Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($grouped_criteria as $category => $items): ?>
                    <tr style="background: #f8fafc;">
                        <td colspan="4" style="padding: 10px 15px; border-bottom: 2px solid var(--border);">
                            <span style="font-size: 0.7rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 0.05em;">
                                Category: <?= htmlspecialchars($category) ?>
                            </span>
                        </td>
                    </tr>
                    <?php foreach($items as $c): ?>
                    <tr>
                        <td style="padding-left: 2rem;">
                            <strong style="display: block; margin-bottom: 5px;"><?= htmlspecialchars($c['criteria_name']) ?></strong>
                            <span style="font-size: 0.65rem; color: var(--text-light);"><?= htmlspecialchars($category) ?> Matrix Component</span>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= $c['weight'] ?>%</td>
                        <td style="text-align: center; color: var(--text-light); font-size: 0.8rem;"><?= $c['min_score'] ?> - <?= $c['max_score'] ?></td>
                        <td class="score-col"></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <tr style="background: #f1f5f9;">
                    <td colspan="3" style="text-align: right; font-weight: 800; font-size: 1rem; font-family: 'Outfit', sans-serif;">FINAL CUMULATIVE SCORE</td>
                    <td class="score-col" style="border: 3px solid var(--dark); background: white !important;"></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 30px; border: 2px solid #cbd5e1; border-radius: 12px; padding: 20px;">
            <span class="meta-label" style="margin-bottom: 15px;">Qualitative Feedback & Development Areas</span>
            <div style="height: 120px;"></div>
        </div>

        <div class="signature-area">
             <div style="font-size: 0.7rem; color: var(--text-light); max-width: 300px;">
                * This document serves as an official grading record for the evaluation. Please ensure all fields are computed accurately before signing.
             </div>
            <div class="sig-box">
                Signature over Printed Name
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>
