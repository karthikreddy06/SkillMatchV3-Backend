<?php
// login.php
header('Content-Type: application/json; charset=utf-8');

// Load config
$config = require __DIR__ . '/config.php';

// Read JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
    exit;
}

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    echo json_encode(['status' => false, 'message' => 'Email and password required']);
    exit;
}

try {
    // Connect DB
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Get user
    $stmt = $pdo->prepare("
        SELECT id, name, email, password_hash, role, is_verified
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify credentials
    if (!$user || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['status' => false, 'message' => 'Invalid email or password']);
        exit;
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32)); // 64-char random token
    $expires_in = 60 * 60 * 24 * 7; // 7 days

    // Save token in DB
    $up = $pdo->prepare("UPDATE users SET token = ?, updated_at = NOW() WHERE id = ?");
    $up->execute([$token, $user['id']]);

    // Get company id if employer
    $companyId = null;
    if ($user['role'] === 'employer') {
        $cmp = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ? LIMIT 1");
        $cmp->execute([$user['id']]);
        $row = $cmp->fetch();
        if ($row) $companyId = (int)$row['id'];
    }

    // Success response
    echo json_encode([
        'status' => true,
        'message' => 'Login successful',
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => $expires_in,
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'is_verified' => (bool)$user['is_verified'],
            'company_id' => $companyId
        ]
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Server error']);
    exit;
}
