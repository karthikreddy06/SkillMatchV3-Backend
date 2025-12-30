<?php
header('Content-Type: application/json');
echo json_encode([
    'auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'NOT FOUND'
]);
