<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';
require_once 'auth_helper.php';

$user = getAuthUser($pdo);
$input = json_decode(file_get_contents('php://input'), true);

$jobId = (int)($input['job_id'] ?? 0);

if ($jobId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid job_id'
    ]);
    exit;
}

/* Check if already applied */
$check = $pdo->prepare("
    SELECT id
    FROM applications
    WHERE seeker_id = ? AND job_id = ?
");
$check->execute([$user['id'], $jobId]);

if ($check->fetch()) {
    echo json_encode([
        'success' => true,
        'message' => 'Already applied'
    ]);
    exit;
}

/* Insert application */
$stmt = $pdo->prepare("
    INSERT INTO applications (
        seeker_id,
        job_id,
        status,
        applied_at,
        updated_at
    ) VALUES (?, ?, 'pending', NOW(), NOW())
");
$stmt->execute([$user['id'], $jobId]);

echo json_encode([
    'success' => true,
    'message' => 'Applied successfully'
]);
exit;