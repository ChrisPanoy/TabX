<?php
require_once 'config/database.php';

try {
    $pdo->beginTransaction();

    // 1. Get all unique (name, event_id, type) that have duplicates
    $stmt = $pdo->query("SELECT criteria_name, event_id, type, COUNT(*) as c 
                         FROM criteria 
                         GROUP BY criteria_name, event_id, type 
                         HAVING c > 1");
    $dupes = $stmt->fetchAll();

    foreach ($dupes as $dupe) {
        $name = $dupe['criteria_name'];
        $eid = $dupe['event_id'];
        $type = $dupe['type'];

        // Get all IDs for this dupe
        if ($eid === null) {
            $stmt_all = $pdo->prepare("SELECT id FROM criteria WHERE criteria_name = ? AND event_id IS NULL AND type = ? ORDER BY id ASC");
            $stmt_all->execute([$name, $type]);
        } else {
            $stmt_all = $pdo->prepare("SELECT id FROM criteria WHERE criteria_name = ? AND event_id = ? AND type = ? ORDER BY id ASC");
            $stmt_all->execute([$name, $eid, $type]);
        }
        $ids = $stmt_all->fetchAll(PDO::FETCH_COLUMN);

        // Determine which ID to keep
        $keep_id = $ids[0]; // Default to first
        $max_scores = -1;

        foreach ($ids as $id) {
            // Check scores
            $s_stmt = $pdo->prepare("SELECT COUNT(*) FROM scores WHERE criteria_id = ?");
            $s_stmt->execute([$id]);
            $s_count = $s_stmt->fetchColumn();

            // Check individual scores
            $is_stmt = $pdo->prepare("SELECT COUNT(*) FROM individual_scores WHERE criteria_id = ?");
            $is_stmt->execute([$id]);
            $is_count = $is_stmt->fetchColumn();

            $total = $s_count + $is_count;
            if ($total > $max_scores) {
                $max_scores = $total;
                $keep_id = $id;
            }
        }

        echo "For '$name' (Event: ".($eid ?? 'TPL').", Type: $type), keeping ID $keep_id\n";

        // Update scores and ind_scores for others to point to keep_id
        foreach ($ids as $id) {
            if ($id == $keep_id) continue;

            // Move scores
            $pdo->prepare("UPDATE IGNORE scores SET criteria_id = ? WHERE criteria_id = ?")->execute([$keep_id, $id]);
            $pdo->prepare("DELETE FROM scores WHERE criteria_id = ?")->execute([$id]); // Clean up duplicates in scores table

            $pdo->prepare("UPDATE IGNORE individual_scores SET criteria_id = ? WHERE criteria_id = ?")->execute([$keep_id, $id]);
            $pdo->prepare("DELETE FROM individual_scores WHERE criteria_id = ?")->execute([$id]);

            // Delete the duplicate criterion
            $pdo->prepare("DELETE FROM criteria WHERE id = ?")->execute([$id]);
            echo " - Deleted ID $id\n";
        }
    }

    $pdo->commit();
    echo "Cleanup complete.\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?>
