<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

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

    $u = $pdo->prepare("
        SELECT id, name, email, phone, latitude, longitude, resume_path
        FROM users WHERE token = ? LIMIT 1
    ");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $s = $pdo->prepare("
        SELECT s.id, s.name
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id = ?
    ");
    $s->execute([$user['id']]);
    $skills = $s->fetchAll();

    echo json_encode([
        'status' => true,
        'profile' => $user,
        'skills' => $skills
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
    exit;
}
