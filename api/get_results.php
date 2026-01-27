<?php
require_once '../config/database.php';
header('Content-Type: application/json');

try {
    // Calculate total scores for each team
    // We sum the scores given by panelists. 
    // To get a fair score, we might want to average the panelists' totals.
    // Let's assume: Final Score = Average of (Sum of Criteria Scores per Panelist)
    
    // Subquery: Get total score per panelist per team
    $sql = "
        SELECT t.id, t.team_name, t.project_title,
        AVG(panelist_total) as final_score
        FROM teams t
        LEFT JOIN (
            SELECT team_id, panelist_id, SUM(score) as panelist_total
            FROM scores
            GROUP BY team_id, panelist_id
        ) as sub ON t.id = sub.team_id
        GROUP BY t.id
        ORDER BY final_score DESC
    ";
    
    $stmt = $pdo->query($sql);
    $teams = $stmt->fetchAll();
    
    // Normalize nulls to 0
    foreach ($teams as &$team) {
        if ($team['final_score'] === null) {
            $team['final_score'] = 0;
        } else {
            $team['final_score'] = number_format($team['final_score'], 2);
        }
    }
    
    echo json_encode($teams);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
