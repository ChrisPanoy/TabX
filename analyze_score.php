<?php
require_once 'config/database.php';
// Let's pick a team that has scores
$stmt = $pdo->query("SELECT team_id FROM scores LIMIT 1");
$team_id = $stmt->fetchColumn();

if (!$team_id) die("No scores found to analyze.");

// Get team and event
$stmt = $pdo->prepare("SELECT t.team_name, t.event_id FROM teams t WHERE t.id = ?");
$stmt->execute([$team_id]);
$team = $stmt->fetch();
$event_id = $team['event_id'];

// Get criteria weights
$stmt = $pdo->prepare("SELECT id, criteria_name, weight, category FROM criteria WHERE event_id = ? AND type = 'group'");
$stmt->execute([$event_id]);
$criteria = $stmt->fetchAll();

// Get scores
$stmt = $pdo->prepare("SELECT criteria_id, AVG(score) as avg_score FROM scores WHERE team_id = ? GROUP BY criteria_id");
$stmt->execute([$team_id]);
$scores = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

echo "Calculation Analysis for Team: {$team['team_name']}\n";
echo "--------------------------------------------------\n";
$total_weighted = 0;
foreach($criteria as $c) {
    $score = $scores[$c['id']] ?? 0;
    $contribution = $score * ($c['weight'] / 100);
    $total_weighted += $contribution;
    echo "Criteria: {$c['criteria_name']} ({$c['category']})\n";
    echo " - Score: " . number_format($score, 2) . "\n";
    echo " - Weight: {$c['weight']}%\n";
    echo " - Contribution: " . number_format($contribution, 2) . "\n\n";
}
echo "--------------------------------------------------\n";
echo "FINAL WEIGHTED SCORE: " . number_format($total_weighted, 2) . "%\n";
?>
 Muhammed.
