<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

$stmt = $pdo->query("SELECT id, name FROM skills ORDER BY name ASC");

echo json_encode([
    'status' => true,
    'skills' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
