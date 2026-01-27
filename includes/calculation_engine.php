<?php
// Function to compute Group Ranking
function calculate_event_results($pdo, $event_id) {
    // 1. Get Group Criteria with Weights
    $stmt = $pdo->prepare("SELECT id, weight FROM criteria WHERE event_id = ? AND type = 'group'");
    $stmt->execute([$event_id]);
    $group_criteria = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => weight

    // 2. Get All Teams in Event
    $stmt = $pdo->prepare("SELECT id, team_name, project_title FROM teams WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($teams as $team) {
        $team_id = $team['id'];
        
        // Fetch All Scores for this team
        $stmt_s = $pdo->prepare("SELECT panelist_id, criteria_id, score FROM scores WHERE team_id = ?");
        $stmt_s->execute([$team_id]);
        $scores = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

        // Group scores by panelist
        $panelist_totals = []; 
        $breakdown = []; // criteria_id => [sum, count]

        foreach ($scores as $s) {
            $pid = $s['panelist_id'];
            $cid = $s['criteria_id'];
            $val = $s['score'];

            // Store breakdown for criteria average
            if (!isset($breakdown[$cid])) $breakdown[$cid] = ['sum' => 0, 'count' => 0];
            $breakdown[$cid]['sum'] += $val;
            $breakdown[$cid]['count']++;

            // Calculate Weighted Score per Panelist (assuming score is 0-100)
            if (isset($group_criteria[$cid])) {
                $weight = $group_criteria[$cid];
                if (!isset($panelist_totals[$pid])) $panelist_totals[$pid] = 0;
                // Weighted contribution: Score * (Weight/100)
                $panelist_totals[$pid] += ($val * ($weight / 100)); 
            }
        }

        // Final Score = Average of Panelists' Weighted Totals
        $final_score = 0;
        if (count($panelist_totals) > 0) {
            $final_score = array_sum($panelist_totals) / count($panelist_totals);
        }

        // Criteria Averages (Raw Average of all panelists for that criteria)
        $criteria_averages = [];
        foreach ($breakdown as $cid => $data) {
            $criteria_averages[$cid] = $data['count'] > 0 ? $data['sum'] / $data['count'] : 0;
        }

        $results[] = [
            'id' => $team_id,
            'team_name' => $team['team_name'],
            'project_title' => $team['project_title'],
            'final_score' => $final_score,
            'criteria_averages' => $criteria_averages
        ];
    }

    // Sort Descending
    usort($results, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });

    return $results;
}

// Function to compute Individual Ranking
function calculate_individual_results($pdo, $event_id) {
    // 1. Get Ind Criteria
    $stmt = $pdo->prepare("SELECT id, weight FROM criteria WHERE event_id = ? AND type = 'individual'");
    $stmt->execute([$event_id]);
    $ind_criteria = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2. Get Members (Names)
    $sql = "SELECT tm.id, tm.member_name as full_name, t.team_name, tm.role_in_project 
            FROM team_members tm
            JOIN teams t ON tm.team_id = t.id 
            WHERE t.event_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$event_id]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];

    foreach ($members as $m) {
        $mid = $m['id'];
        
        $stmt_s = $pdo->prepare("SELECT panelist_id, criteria_id, score FROM individual_scores WHERE team_member_id = ?");
        $stmt_s->execute([$mid]);
        $scores = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

        $panelist_totals = [];

        foreach ($scores as $s) {
            $pid = $s['panelist_id'];
            $cid = $s['criteria_id'];
            $val = $s['score'];

            if (isset($ind_criteria[$cid])) {
                $weight = $ind_criteria[$cid];
                if (!isset($panelist_totals[$pid])) $panelist_totals[$pid] = 0;
                 $panelist_totals[$pid] += ($val * ($weight / 100));
            }
        }

        $final_score = 0;
        if (count($panelist_totals) > 0) {
            $final_score = array_sum($panelist_totals) / count($panelist_totals);
        }

        if ($final_score > 0) {
            $results[] = [
                'id' => $mid,
                'full_name' => $m['full_name'],
                'team_name' => $m['team_name'],
                'role' => $m['role_in_project'],
                'final_score' => $final_score
            ];
        }
    }

    usort($results, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });

    return $results;
}

// Function to Calculate Special Awards (Best Poster, Innovation, etc.)
function calculate_special_awards($pdo, $event_id) {
    // 1. Get all group criteria for this event
    $stmt = $pdo->prepare("SELECT id, criteria_name, category FROM criteria WHERE event_id = ? AND type = 'group'");
    $stmt->execute([$event_id]);
    $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $awards = [];
    
    // Mapping Award Name => Criteria Category
    $category_map = [
        'Best Capstone Paper' => 'Manuscripts',
        'Best Poster' => 'Poster',
        'Best Brochure' => 'Brochure',
        'Best Teaser Video' => 'Teaser'
    ];

    foreach ($category_map as $award_name => $cat_name) {
        // Find all criteria IDs in this category
        $target_ids = [];
        foreach ($criteria as $c) {
            if ($c['category'] == $cat_name) {
                $target_ids[] = $c['id'];
            }
        }

        if (!empty($target_ids)) {
            $placeholders = rtrim(str_repeat('?,', count($target_ids)), ',');
            $sql = "
                SELECT t.team_name, AVG(s.score) as cat_avg 
                FROM scores s 
                JOIN teams t ON s.team_id = t.id 
                WHERE s.criteria_id IN ($placeholders) 
                GROUP BY t.id 
                ORDER BY cat_avg DESC 
                LIMIT 1
            ";
            $stmt_win = $pdo->prepare($sql);
            $stmt_win->execute($target_ids);
            $winner = $stmt_win->fetch(PDO::FETCH_ASSOC);

            if ($winner) {
                $awards[$award_name] = [
                    'team' => $winner['team_name'],
                    'score' => (float)$winner['cat_avg']
                ];
            }
        }
    }
    
    return $awards;
}
?>
