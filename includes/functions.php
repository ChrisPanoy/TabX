<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF Token Validation Failed. Please refresh and try again.");
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

function requireRole($role) {
    if (!isLoggedIn() || $_SESSION['role'] !== $role) {
        header("Location: index.php");
        exit;
    }
}

function sanitize($input) {
    return htmlspecialchars(strip_tags($input));
}

function get_total_score($team_id, $pdo) {
    $stmt = $pdo->prepare("SELECT SUM(score) as total FROM scores WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $result = $stmt->fetch();
    return $result['total'] ? $result['total'] : 0;
}

function get_average_score($team_id, $pdo) {
     // A simplified average calculation across all panelists and criteria
    $stmt = $pdo->prepare("SELECT AVG(score) as average FROM scores WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $result = $stmt->fetch();
    return $result['average'] ? number_format($result['average'], 2) : 0;
    
    // Note: Real tabulation usually weights criteria.
    // If criteria have different weights, we need a smarter calculation.
    // Weighted Average Calculation:
    // (Sum of (Score * Weight)) / (Sum of Weights) (assuming Score is raw)
    // Here, let's assume raw score is already weighted or just 1-10 scale and we average that?
    // The schema says 'weight' in criteria.
    // Let's implement weighted average calculation in a separate specialized function if needed.
}

function calculate_weighted_score($team_id, $pdo) {
    // Get all scores for the team
    $sql = "SELECT s.score, c.weight 
            FROM scores s 
            JOIN criteria c ON s.criteria_id = c.id 
            WHERE s.team_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$team_id]);
    $scores = $stmt->fetchAll();

    if (!$scores) return 0;

    $total_weighted_score = 0;
    $total_weight = 0;
    
    // This logic might need adjustment based on how many panelists there are.
    // Usually, it's Average of Panelists' (Weighted Sums).
    
    // Let's change approach: Calculate per panelist first, then average users.
    
    return "N/A"; // Placeholder, logic needs to be robust.
}

function get_active_school_year($pdo) {
    $stmt = $pdo->query("SELECT * FROM school_years WHERE status = 'active' LIMIT 1");
    return $stmt->fetch();
}

function get_current_event($pdo) {
    // Return the event marked as ongoing, or the most recent upcoming
    $stmt = $pdo->query("SELECT * FROM events WHERE status = 'ongoing' ORDER BY event_date ASC LIMIT 1");
    $event = $stmt->fetch();
    if (!$event) {
        $stmt = $pdo->query("SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC LIMIT 1");
        $event = $stmt->fetch();
    }
    return $event;
}
?>
