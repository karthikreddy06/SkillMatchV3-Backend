<?php
// register.php (debug version) — show full errors for troubleshooting ONLY
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// Load config (ensure config.php is in same folder)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => "Missing config.php at {$configPath}"]);
    exit;
}
$config = require $configPath;

// Basic input parsing
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid JSON or empty body']);
    exit;
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';
$role = ($input['role'] ?? 'seeker') === 'employer' ? 'employer' : 'seeker';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Name, valid email and password(>=8) required']);
    exit;
}

// Connect to DB with clear error handling
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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'DB connection failed', 'error' => $e->getMessage()]);
    exit;
}

try {
    // Check for existing email or phone
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->execute([$email, $phone]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['status' => false, 'message' => 'Email or phone already registered']);
        exit;
    }

    // Hash password and insert
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Ensure these columns exist in your users table:
    // name, email, phone, password_hash, role, is_verified, created_at, updated_at
    $insert = $pdo->prepare("
        INSERT INTO users (name, email, phone, password_hash, role, is_verified, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $insert->execute([$name, $email, $phone, $password_hash, $role]);

    $userId = (int)$pdo->lastInsertId();

    // Return success
    echo json_encode([
        'status' => true,
        'message' => 'Registered successfully (DEBUG)',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_verified' => false
        ]
    ]);
    exit;

} catch (PDOException $e) {
    // Return the full SQL error so we can fix it (debug only)
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Server error (DB)',
        'error' => $e->getMessage(),
        'query' => isset($insert) ? $insert->queryString : null
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
