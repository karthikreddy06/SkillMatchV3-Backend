<?php
function log_sync_change($seeker_id, $table, $row_id, $operation) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, updated_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("isis", $seeker_id, $table, $row_id, $operation);
    $stmt->execute();
    $stmt->close();
}
