<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT criteria_name, event_id, type, COUNT(*) as c FROM criteria GROUP BY criteria_name, event_id, type HAVING c > 1");
$dupes = $stmt->fetchAll();
if (empty($dupes)) {
    echo "No duplicates found based on name, event, and type.\n";
} else {
    foreach($dupes as $d) {
        echo "DUPE: Name: {$d['criteria_name']} | Event: ".($d['event_id'] ?? 'TPL')." | Type: {$d['type']} | Count: {$d['c']}\n";
    }
}
?>
