<?php
// update_location.php
header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/auth_helper.php';

// Try to load external sync helper
$log_sync_path = $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/log_sync.php';
if (file_exists($log_sync_path)) {
    require_once $log_sync_path;
}

// fallback log_sync (same as other files)
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
$lat = isset($input['latitude']) ? floatval($input['latitude']) : null;
$lng = isset($input['longitude']) ? floatval($input['longitude']) : null;

if ($lat === null || $lng === null) {
    echo json_encode(["status" => false, "message" => "latitude and longitude required"]);
    exit;
}

// simple validation
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(["status" => false, "message" => "Invalid coordinates"]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET latitude = ?, longitude = ? WHERE id = ?");
if (!$stmt) {
    echo json_encode(["status" => false, "message" => "Server error (DB prepare)"]);
    exit;
}
$stmt->bind_param("ddi", $lat, $lng, $seeker_id);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    echo json_encode(["status" => false, "message" => "Server error (DB update)"]);
    exit;
}

// Log sync
if (function_exists('log_sync')) {
    log_sync($seeker_id, 'users', $seeker_id, 'location_update', ['lat' => $lat, 'lng' => $lng]);
}

echo json_encode(["status" => true, "message" => "Location updated"]);
exit;
