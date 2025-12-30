<?php
// includes/log_sync.php
// Usage: require_once __DIR__ . '/log_sync.php'; then call log_sync(...)

// Expects global $conn (mysqli) to be available in calling script.
if (!function_exists('log_sync')) {
    /**
     * Insert a row into sync_changes table.
     *
     * @param int    $seeker_id  The seeker (user) id this change belongs to
     * @param string $table_name Table name affected (e.g. 'applications', 'users', 'jobs')
     * @param int    $row_id     The primary key id of the affected row (nullable)
     * @param string $operation  Short operation tag: create|update|delete|upload_resume|withdraw|location_update etc.
     * @param array|null $meta   Optional associative array to store extra info (will be JSON-encoded)
     * @return bool True on success, false on failure
     */
    function log_sync(int $seeker_id, string $table_name, $row_id = null, string $operation = 'update', $meta = null): bool {
        global $conn;

        if (!isset($conn) || !$seeker_id || !$table_name || !$operation) {
            return false;
        }

        $meta_json = null;
        if (is_array($meta) || is_object($meta)) {
            $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
            if ($meta_json === false) {
                $meta_json = null;
            }
        }

        $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, meta) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            // optionally log error somewhere: error_log("log_sync prepare failed: " . $conn->error);
            return false;
        }

        // use null handling for row_id
        if ($row_id === null) {
            $row_id_param = null;
            $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id_param, $operation, $meta_json);
        } else {
            $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id, $operation, $meta_json);
        }

        $ok = $stmt->execute();
        $stmt->close();

        return (bool)$ok;
    }
}
