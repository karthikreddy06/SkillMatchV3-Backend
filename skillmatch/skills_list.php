<?php
header('Content-Type: application/json; charset=utf-8');

$config = require __DIR__ . '/config.php';

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

    $stmt = $pdo->query("SELECT id, name FROM skills ORDER BY name ASC");
    $skills = $stmt->fetchAll();

    echo json_encode([
        'status' => true,
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
