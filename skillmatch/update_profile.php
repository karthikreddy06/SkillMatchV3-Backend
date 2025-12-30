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

/* ---------- INPUT ---------- */
$input = json_decode(file_get_contents('php://input'), true);

$name      = trim($input['name'] ?? '');
$phone     = trim($input['phone'] ?? '');
$latitude  = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $u = $pdo->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
    $u->execute([$token]);
    $user = $u->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE users 
        SET name = ?, phone = ?, latitude = ?, longitude = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $phone,
        $latitude,
        $longitude,
        $user['id']
    ]);

    echo json_encode([
        'status' => true,
        'message' => 'Profile updated successfully'
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
