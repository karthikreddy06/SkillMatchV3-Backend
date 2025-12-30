<?php
header("Content-Type: application/json");

require_once "../../../../includes/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/skillmatch/security/auth_checks.php";

$user = require_seeker();
$seeker_id = $user['id'];

$stmt = $conn->prepare(
    "SELECT id, title, message, read_status, created_at 
     FROM notifications 
     WHERE seeker_id = ?
     ORDER BY created_at DESC"
);
$stmt->bind_param("i", $seeker_id);
$stmt->execute();

$result = $stmt->get_result();

echo json_encode([
    "status" => true,
    "notifications" => $result->fetch_all(MYSQLI_ASSOC)
], JSON_PRETTY_PRINT);
