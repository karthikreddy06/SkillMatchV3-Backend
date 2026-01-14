<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON payload',
        'raw' => $raw
    ]);
    exit;
}

$email = $data['email'] ?? null;
$password = $data['password'] ?? null;

if (!$email || !$password) {
    echo json_encode([
        'status' => false,
        'message' => 'Email and password required'
    ]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid credentials'
    ]);
    exit;
}

$token = bin2hex(random_bytes(32));
$pdo->prepare("UPDATE users SET token = ? WHERE id = ?")
    ->execute([$token, $user['id']]);

echo json_encode([
    'status' => true,
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'role' => $user['role']
    ]
]);