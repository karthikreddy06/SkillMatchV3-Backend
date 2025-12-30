<?php
header("Content-Type: application/json");

require_once "../../../../includes/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/skillmatch/security/auth_checks.php";

$user = require_seeker();
$seeker_id = $user['id'];

$data = json_decode(file_get_contents("php://input"), true);
$since = $data["since"] ?? null;

if (!$since) {
    echo json_encode(["status"=>false,"message"=>"Missing 'since' timestamp"]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT table_name, row_id, operation, updated_at 
     FROM sync_changes 
     WHERE seeker_id = ? AND updated_at > ?
     ORDER BY updated_at ASC"
);
$stmt->bind_param("is", $seeker_id, $since);
$stmt->execute();

$result = $stmt->get_result();

echo json_encode([
    "status" => true,
    "changes" => $result->fetch_all(MYSQLI_ASSOC)
], JSON_PRETTY_PRINT);
