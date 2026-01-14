<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';
require_once 'auth_helper.php';

$user = getAuthUser($pdo);
$data = json_decode(file_get_contents('php://input'), true);

$name = $data['name'] ?? '';
$phone = $data['phone'] ?? '';
$bio = $data['bio'] ?? '';

$pdo->prepare("
    UPDATE users SET name = ?, phone = ?, bio = ?
    WHERE id = ?
")->execute([$name, $phone, $bio, $user['id']]);

echo json_encode(['status' => true, 'message' => 'Profile updated']);
