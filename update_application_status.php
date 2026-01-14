<?php
header("Content-Type: application/json");
require_once "db.php";

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$authHeader) {
    echo json_encode(["status" => false, "message" => "Authorization missing"]);
    exit;
}

$token = trim(str_replace("Bearer", "", $authHeader));

// Verify employer
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = ? AND role = 'employer'");
$stmt->execute([$token]);
$employer = $stmt->fetch();

if (!$employer) {
    echo json_encode(["status" => false, "message" => "Invalid token"]);
    exit;
}

$applicationId = $_POST['application_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$applicationId || !in_array($status, ['accepted', 'rejected'])) {
    echo json_encode(["status" => false, "message" => "Invalid input"]);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE applications 
    SET status = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->execute([$status, $applicationId]);

echo json_encode([
    "status" => true,
    "message" => "Application updated"
]);
