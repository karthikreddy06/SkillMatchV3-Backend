<?php

function getAuthUser(PDO $pdo): array
{
    // Fetch headers safely (works for Apache, FastCGI, Android)
    $headers = getallheaders();

    if (empty($headers) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    }

    // Normalize header keys (case-insensitive)
    $authHeader = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }

    // Validate Authorization header
    if (!$authHeader || stripos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode([
            'status' => false,
            'message' => 'Missing token'
        ]);
        exit;
    }

    // Extract token safely (remove "Bearer ")
    $token = trim(substr($authHeader, 7));

    if ($token === '') {
        http_response_code(401);
        echo json_encode([
            'status' => false,
            'message' => 'Invalid token'
        ]);
        exit;
    }

    // Validate token against database
    $stmt = $pdo->prepare("
        SELECT id, role 
        FROM users 
        WHERE token = ? 
        LIMIT 1
    ");
    $stmt->execute([$token]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'status' => false,
            'message' => 'Invalid token'
        ]);
        exit;
    }

    return $user;
}