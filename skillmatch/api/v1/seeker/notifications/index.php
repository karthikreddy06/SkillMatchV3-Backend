<?php
// index.php (notifications folder)
// Path: C:\xampp\htdocs\skillmatch\api\v1\seeker\notifications\index.php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load DB (adjust path if your includes are elsewhere)
require_once "../../../../includes/config.php";

// Load helper that contains create_notification($seeker_id, $title, $message)
require_once __DIR__ . "/create_notification.php";

// --- Simple token auth validation (checks token exists in users table) ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;
if (!$auth) {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Missing Authorization header"]);
    exit;
}

// Accept "Bearer <token>" or raw token
$token = trim(str_ireplace("Bearer", "", $auth));
if ($token === '') {
    http_response_code(401);
    echo json_encode(["status" => false, "message" => "Empty token"]);
    exit;
}

// Verify token in DB and fetch requester id (so we can audit or enforce permissions)
$stmt = $conn->prepare("SELECT id, role FROM users WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Invalid token"]);
    exit;
}

// parse JSON body
$input = json_decode(file_get_contents("php://input"), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid JSON body"]);
    exit;
}

$seeker_id = intval($input['seeker_id'] ?? 0);
$title = trim($input['title'] ?? '');
$message = trim($input['message'] ?? '');

if ($seeker_id <= 0 || $title === '' || $message === '') {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "seeker_id, title and message required"]);
    exit;
}

// Optionally: verify seeker exists
$sstmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'seeker' LIMIT 1");
$sstmt->bind_param("i", $seeker_id);
$sstmt->execute();
$seekerExists = $sstmt->get_result()->fetch_assoc();
$sstmt->close();

if (!$seekerExists) {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Seeker not found"]);
    exit;
}

// Call your helper function to insert notification
$ok = create_notification($seeker_id, $title, $message);

if ($ok) {
    // create_notification uses global $conn, so last insert id is available
    $notif_id = $conn->insert_id ?? null;
    echo json_encode(["status" => true, "message" => "Notification created", "id" => $notif_id]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Failed to create notification"]);
    exit;
}
