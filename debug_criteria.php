<?php
require_once 'config/database.php';
$stmt = $pdo->query("SELECT id, event_id, criteria_name, type, category FROM criteria ORDER BY event_id, criteria_name");
$rows = $stmt->fetchAll();
foreach($rows as $row) {
    echo "ID: {$row['id']} | Event: ".($row['event_id'] ?? 'TPL')." | Name: {$row['criteria_name']} | Type: {$row['type']} | Category: {$row['category']}\n";
}
?>
