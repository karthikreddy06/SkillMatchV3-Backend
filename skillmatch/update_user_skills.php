<?php
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__ . '/config.php';

$headers = getallheaders();
$token = trim(str_replace('Bearer', '', $headers['Authorization'] ?? ''));

$data = json_decode(file_get_contents('php://input'), true);
$skills = $data['skills'] ?? [];

if (empty($skills)) {
    echo json_encode(['status' => false, 'message' => 'No skills']);
    exit;
}

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass']
    );

    $u = $pdo->prepare("SELECT id FROM users WHERE token = ?");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $pdo->prepare("DELETE FROM user_skills WHERE user_id = ?")
        ->execute([$user['id']]);

    $ins = $pdo->prepare("
        INSERT INTO user_skills (user_id, skill) VALUES (?, ?)
    ");

    foreach ($skills as $skill) {
        $ins->execute([$user['id'], $skill]);
    }

    echo json_encode(['status' => true]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error']);
}
