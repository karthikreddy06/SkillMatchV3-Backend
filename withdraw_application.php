<?php
// withdraw_application.php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/auth_helper.php';

// Try to load external sync helper if present
$log_sync_path = $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/log_sync.php';
if (file_exists($log_sync_path)) {
    require_once $log_sync_path;
}

// Fallback log_sync as above (reuse same function if not defined)
if (!function_exists('log_sync')) {
    function log_sync(int $seeker_id, string $table_name, $row_id = null, string $operation = 'update', $meta = null): bool {
        global $conn;
        if (!isset($conn)) return false;
        if (!$seeker_id || !$table_name || !$operation) return false;
        static $schema = null;
        if ($schema === null) {
            $schema = ['has_meta'=>false,'has_created_at'=>false,'has_updated_at'=>false];
            $res = $conn->query("SHOW COLUMNS FROM `sync_changes`");
            if ($res) {
                while ($col = $res->fetch_assoc()) {
                    $f = strtolower($col['Field']);
                    if ($f === 'meta') $schema['has_meta'] = true;
                    if ($f === 'created_at') $schema['has_created_at'] = true;
                    if ($f === 'updated_at') $schema['has_updated_at'] = true;
                }
                $res->free();
            }
        }
        $meta_json = null;
        if (is_array($meta) || is_object($meta)) {
            $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;
        }
        try {
            if ($schema['has_meta'] && $schema['has_created_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, meta, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $seeker_id, $table_name, $operation, $meta_json);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, meta, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id, $operation, $meta_json);
                }
            } elseif ($schema['has_meta'] && $schema['has_updated_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, meta, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isss", $seeker_id, $table_name, $operation, $meta_json);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, meta, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id, $operation, $meta_json);
                }
            } elseif ($schema['has_created_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isis", $seeker_id, $table_name, $row_id, $operation);
                }
            } elseif ($schema['has_updated_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, updated_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->bind_param("isis", $seeker_id, $table_name, $row_id, $operation);
                }
            } else {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isis", $seeker_id, $table_name, $row_id, $operation);
                }
            }
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

$user = require_auth();
$seeker_id = intval($user['id']);

if ($user['role'] !== 'seeker') {
    echo json_encode(["status" => false, "message" => "Only seekers allowed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => false, "message" => "Only POST allowed"]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$application_id = isset($input['application_id']) ? intval($input['application_id']) : 0;

if ($application_id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid application_id"]);
    exit;
}

// Fetch application and verify ownership
$stmt = $conn->prepare("SELECT id, job_id, seeker_id, status FROM applications WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) {
    echo json_encode(["status" => false, "message" => "Application not found"]);
    exit;
}
if (intval($app['seeker_id']) !== $seeker_id) {
    echo json_encode(["status" => false, "message" => "Not allowed"]);
    exit;
}

// Only allow withdraw when in allowed states (applied or pending). Adjust as per your rules.
$cur_status = $app['status'] ?? '';
$allowed = ['applied', 'pending', ''];
if (!in_array($cur_status, $allowed, true)) {
    echo json_encode(["status" => false, "message" => "Cannot withdraw application in current state"]);
    exit;
}

// Update status to withdrawn
$stmt = $conn->prepare("UPDATE applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ? AND seeker_id = ?");
$stmt->bind_param("ii", $application_id, $seeker_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(["status" => false, "message" => "Server error (DB update)"]);
    exit;
}

// Log sync
if (function_exists('log_sync')) {
    log_sync($seeker_id, 'applications', $application_id, 'withdraw', ['previous_status' => $cur_status]);
}

echo json_encode(["status" => true, "message" => "Application withdrawn", "application_id" => $application_id]);
exit;
