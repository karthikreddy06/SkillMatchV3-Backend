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

    /* Fetch applied jobs */
    $stmt = $pdo->prepare("
        SELECT 
            a.id AS application_id,
            j.title AS job_title,
            c.company_name,
            a.status
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN companies c ON c.id = j.company_id
        WHERE a.seeker_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$seekerId]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'status' => true,
        'applications' => $rows
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
