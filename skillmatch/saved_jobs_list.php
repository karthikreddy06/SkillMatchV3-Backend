<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

/* ---------- AUTH ---------- */
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['status' => false, 'message' => 'Missing token']);
    exit;
}
$token = trim(str_replace('Bearer', '', $headers['Authorization']));

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

    /* Get seeker */
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $seekerId = (int)$user['id'];

    /* Fetch saved jobs */
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
        FROM saved_jobs sj
        JOIN jobs j ON j.id = sj.job_id
        JOIN companies c ON c.id = j.company_id
        WHERE sj.seeker_id = ?
        ORDER BY sj.saved_at DESC
    ");
    $stmt->execute([$seekerId]);

    echo json_encode([
        'status' => true,
        'jobs' => $stmt->fetchAll()
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
    exit;
}
