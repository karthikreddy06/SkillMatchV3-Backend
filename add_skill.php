<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

/* ---------- AUTH ---------- */
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode([
        'status' => false,
        'message' => 'Missing token'
    ]);
    exit;
}

$token = trim(str_replace('Bearer', '', $headers['Authorization']));

/* ---------- INPUT ---------- */
$input = json_decode(file_get_contents('php://input'), true);
$skillName = trim($input['name'] ?? '');

if ($skillName === '') {
    echo json_encode([
        'status' => false,
        'message' => 'Skill name is required'
    ]);
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

    /* ---------- VALIDATE USER ---------- */
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ?");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode([
            'status' => false,
            'message' => 'Invalid token'
        ]);
        exit;
    }

    /* ---------- CHECK IF SKILL EXISTS ---------- */
    $check = $pdo->prepare("
        SELECT id 
        FROM skills 
        WHERE LOWER(name) = LOWER(?)
        LIMIT 1
    ");
    $check->execute([$skillName]);
    $existing = $check->fetch();

    if ($existing) {
        echo json_encode([
            'status' => true,
            'skill' => [
                'id' => (int)$existing['id'],
                'name' => $skillName,
                'created' => false
            ]
        ]);
        exit;
    }

    /* ---------- INSERT NEW SKILL ---------- */
    $insert = $pdo->prepare("
        INSERT INTO skills (name) 
        VALUES (?)
    ");
    $insert->execute([$skillName]);

    echo json_encode([
        'status' => true,
        'skill' => [
            'id' => (int)$pdo->lastInsertId(),
            'name' => $skillName,
            'created' => true
        ]
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
