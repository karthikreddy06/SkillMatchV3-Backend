<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'db.php';
require_once 'auth_helper.php';

try {
    $user = getAuthUser($pdo);

    $stmt = $pdo->prepare("
        SELECT
            j.id AS id,
            j.title,
            j.location,
            j.job_type AS jobType,
            s.created_at AS savedAt
        FROM saved_jobs s
        JOIN jobs j ON j.id = s.job_id
        WHERE s.seeker_id = ?
        ORDER BY s.created_at DESC
    ");

    $stmt->execute([$user['id']]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => true,
        'jobs' => $jobs ?: []
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'jobs' => [],
        'message' => $e->getMessage()
    ]);
    exit;
}