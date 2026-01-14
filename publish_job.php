<?php
require_once __DIR__ . '/auth_helper.php';

list($pdo, $user) = require_employer();

/**
 * Read JSON body
 */
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    json_response(['status' => false, 'message' => 'Invalid JSON body'], 400);
}

$job_id = (int)($input['job_id'] ?? 0);
$status = $input['status'] ?? '';

$allowed_status = ['published', 'closed'];

if ($job_id <= 0) {
    json_response(['status' => false, 'message' => 'Valid job_id required'], 422);
}

if (!in_array($status, $allowed_status, true)) {
    json_response([
        'status' => false,
        'message' => 'Status must be published or closed'
    ], 422);
}

/**
 * Ensure employer owns the job
 */
$stmt = $pdo->prepare("
    SELECT j.id
    FROM jobs j
    JOIN companies c ON j.company_id = c.id
    WHERE j.id = ? AND c.employer_id = ?
    LIMIT 1
");
$stmt->execute([$job_id, $user['id']]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['status' => false, 'message' => 'Job not found or forbidden'], 403);
}

/**
 * Update job status
 */
$update = $pdo->prepare("
    UPDATE jobs
    SET status = ?, updated_at = CURRENT_TIMESTAMP
    WHERE id = ?
");
$update->execute([$status, $job_id]);

json_response([
    'status' => true,
    'message' => "Job {$status} successfully",
    'job_id' => $job_id,
    'new_status' => $status
], 200);
