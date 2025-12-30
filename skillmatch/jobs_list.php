<?php
header('Content-Type: application/json; charset=utf-8');

/**
 * IMPORTANT:
 * This must match login.php / register.php exactly
 */
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->prepare("
        SELECT
            j.id,
            j.title,
            j.location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.created_at,
            c.company_name
        FROM jobs j
        INNER JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'published'
        ORDER BY j.created_at DESC
    ");

    $stmt->execute();
    $jobs = $stmt->fetchAll();

    echo json_encode([
        'status' => true,
        'jobs' => $jobs
    ]);
    exit;

} catch (Throwable $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
    exit;
}
