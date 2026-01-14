<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';
require_once 'auth_helper.php';

$user = getAuthUser($pdo);
$data = json_decode(file_get_contents('php://input'), true);
$skills = $data['skills'] ?? [];

$pdo->prepare("DELETE FROM user_skills WHERE user_id = ?")->execute([$user['id']]);

$stmt = $pdo->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
foreach ($skills as $skillId) {
    $stmt->execute([$user['id'], (int)$skillId]);
}

echo json_encode(['status' => true, 'message' => 'Skills updated']);
