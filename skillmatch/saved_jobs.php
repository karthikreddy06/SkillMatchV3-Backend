<?php
header('Content-Type: application/json; charset=utf-8');

// Load config (same as login/register)
$config = require __DIR__ . '/config.php';

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
    exit;
}

$token  = trim($input['access_token'] ?? '');
$job_id = (int)($input['job_id'] ?? 0);

if ($token === '' || $job_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Token and job_id required']);
    exit;
}

try {
    // Connect DB (PDO — SAME as login.php)
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // 1️⃣ Get user from token
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    $user_id = (int)$user['id'];

    // 2️⃣ Save job (ignore duplicates)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO saved_jobs (user_id, job_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$user_id, $job_id]);

    echo json_encode([
        'status' => true,
        'message' => 'Job saved successfully'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
    exit;
}
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
$jobId = (int)($input['job_id'] ?? 0);

if ($jobId <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid job_id']);
    exit;
}

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

    /* Get seeker */
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $seekerId = (int)$user['id'];

    /* Prevent duplicate save */
    $chk = $pdo->prepare("
        SELECT id FROM saved_jobs
        WHERE seeker_id = ? AND job_id = ?
        LIMIT 1
    ");
    $chk->execute([$seekerId, $jobId]);

    if ($chk->fetch()) {
        echo json_encode(['status' => false, 'message' => 'Already saved']);
        exit;
    }

    /* Insert */
    $ins = $pdo->prepare("
        INSERT INTO saved_jobs (seeker_id, job_id)
        VALUES (?, ?)
    ");
    $ins->execute([$seekerId, $jobId]);

    echo json_encode(['status' => true, 'message' => 'Job saved']);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
    exit;
}
