<?php
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__ . '/config.php';

$headers = getallheaders();
$token = trim(str_replace('Bearer', '', $headers['Authorization'] ?? ''));

if (!$token) {
    echo json_encode(['status' => false, 'message' => 'No token']);
    exit;
}

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

    // Get user
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ?");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    // Fetch user skills
    $stmt = $pdo->prepare("
        SELECT s.id, s.name
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id = ?
        ORDER BY s.name ASC
    ");
    $stmt->execute([$user['id']]);

    echo json_encode([
        'status' => true,
        'skills' => $stmt->fetchAll()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
}
