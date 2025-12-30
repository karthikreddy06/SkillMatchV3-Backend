<?php
// apply_job.php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/auth_helper.php';

// Try to load external sync helper if present
$log_sync_path = $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/log_sync.php';
if (file_exists($log_sync_path)) {
    require_once $log_sync_path;
}

// Robust fallback log_sync if not provided (safe, best-effort)
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
                    if (!$stmt) return false;
                    $stmt->bind_param("isss", $seeker_id, $table_name, $operation, $meta_json);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, meta, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id, $operation, $meta_json);
                }
            } elseif ($schema['has_meta'] && $schema['has_updated_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, meta, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("isss", $seeker_id, $table_name, $operation, $meta_json);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, meta, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("isiss", $seeker_id, $table_name, $row_id, $operation, $meta_json);
                }
            } elseif ($schema['has_created_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, created_at) VALUES (?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, created_at) VALUES (?, ?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("isis", $seeker_id, $table_name, $row_id, $operation);
                }
            } elseif ($schema['has_updated_at']) {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation, updated_at) VALUES (?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation, updated_at) VALUES (?, ?, ?, ?, NOW())");
                    if (!$stmt) return false;
                    $stmt->bind_param("isis", $seeker_id, $table_name, $row_id, $operation);
                }
            } else {
                if ($row_id === null) {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, operation) VALUES (?, ?, ?)");
                    if (!$stmt) return false;
                    $stmt->bind_param("iss", $seeker_id, $table_name, $operation);
                } else {
                    $stmt = $conn->prepare("INSERT INTO sync_changes (seeker_id, table_name, row_id, operation) VALUES (?, ?, ?, ?)");
                    if (!$stmt) return false;
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

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$job_id = isset($input['job_id']) ? intval($input['job_id']) : 0;
$cover_letter = trim($input['cover_letter'] ?? '');
$expected_salary = isset($input['expected_salary']) ? intval($input['expected_salary']) : 0;

if ($job_id <= 0) {
    echo json_encode(["status" => false, "message" => "Invalid job_id"]);
    exit;
}

// Verify job exists
$stmt = $conn->prepare("SELECT id, employer_id FROM jobs WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Server error (DB prepare)"]);
    exit;
}
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    echo json_encode(["status" => false, "message" => "Job not found"]);
    exit;
}

// Prevent duplicate application
$stmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Server error (DB prepare)"]);
    exit;
}
$stmt->bind_param("ii", $job_id, $seeker_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    // optional: log duplicate event
    if (function_exists('log_sync')) {
        log_sync($seeker_id, 'applications', intval($existing['id']), 'apply_duplicate', ['job_id' => $job_id]);
    }
    echo json_encode(["status" => "exists", "application_id" => intval($existing['id']), "db_status" => ""]);
    exit;
}

// Insert application
$stmt = $conn->prepare("INSERT INTO applications (job_id, seeker_id, cover_letter, expected_salary, status, applied_at) VALUES (?, ?, ?, ?, 'applied', NOW())");
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Server error (DB prepare)"]);
    exit;
}
$stmt->bind_param("iisi", $job_id, $seeker_id, $cover_letter, $expected_salary);
$ok = $stmt->execute();
$app_id = $conn->insert_id;
$stmt->close();

if (!$ok) {
    echo json_encode(["status" => false, "message" => "Server error (DB insert)"]);
    exit;
}

// Log sync
if (function_exists('log_sync')) {
    log_sync($seeker_id, 'applications', intval($app_id), 'apply', ['job_id' => $job_id, 'expected_salary' => $expected_salary]);
}

echo json_encode(["status" => true, "message" => "Application submitted", "application_id" => intval($app_id)]);
exit;
