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

// Get employer
$stmt = $pdo->prepare("SELECT id FROM users WHERE token = ? AND role = 'employer'");
$stmt->execute([$token]);
$employer = $stmt->fetch();

if (!$employer) {
    echo json_encode(["status" => false, "message" => "Invalid token"]);
    exit;
}

$jobId = $_GET['job_id'] ?? null;

if (!$jobId) {
    echo json_encode(["status" => false, "message" => "Job ID required"]);
    exit;
}

// Fetch applicants
$sql = "
    SELECT 
        a.id AS application_id,
        a.status,
        a.applied_at,
        u.name AS seeker_name,
        u.email
    FROM applications a
    JOIN users u ON a.seeker_id = u.id
    WHERE a.job_id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$jobId]);

echo json_encode([
    "status" => true,
    "applicants" => $stmt->fetchAll()
]);
