<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';
require_once 'auth_helper.php';

$user = getAuthUser($pdo);
$input = json_decode(file_get_contents('php://input'), true);

$jobId = (int)($input['job_id'] ?? 0);

if ($jobId <= 0) {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid job_id'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    INSERT IGNORE INTO saved_jobs (user_id, job_id)
    VALUES (?, ?)
");
$stmt->execute([$user['id'], $jobId]);

echo json_encode([
    'status' => true,
    'message' => 'Job saved'
]);