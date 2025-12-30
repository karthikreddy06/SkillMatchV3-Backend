<?php
header('Content-Type: application/json; charset=utf-8');

/* ---------- CONFIG ---------- */
$config = require __DIR__ . '/config.php';

/* ---------- AUTH ---------- */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (!preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid or missing token'
    ]);
    exit;
}

$token = $matches[1]; // ✅ clean token, no spaces

/* ---------- INPUT ---------- */
$input = json_decode(file_get_contents('php://input'), true);
$jobId = (int)($input['job_id'] ?? 0);

if ($jobId <= 0) {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid job_id'
    ]);
    exit;
}

/* ---------- DB ---------- */
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

    /* ---------- GET SEEKER FROM TOKEN ---------- */
    $userStmt = $pdo->prepare(
        "SELECT id FROM users WHERE token = ? AND role = 'seeker' LIMIT 1"
    );
    $userStmt->execute([$token]);
    $user = $userStmt->fetch();

    if (!$user) {
        echo json_encode([
            'status' => false,
            'message' => 'Invalid token'
        ]);
        exit;
    }

    $seekerId = (int)$user['id'];

    /* ---------- CHECK JOB EXISTS & IS OPEN ---------- */
    $jobStmt = $pdo->prepare(
        "SELECT id FROM jobs WHERE id = ? AND status = 'published' LIMIT 1"
    );
    $jobStmt->execute([$jobId]);

    if (!$jobStmt->fetch()) {
        echo json_encode([
            'status' => false,
            'message' => 'Job not available'
        ]);
        exit;
    }

    /* ---------- PREVENT DUPLICATE APPLICATION ---------- */
    $checkStmt = $pdo->prepare(
        "SELECT id FROM applications WHERE job_id = ? AND seeker_id = ? LIMIT 1"
    );
    $checkStmt->execute([$jobId, $seekerId]);

    if ($checkStmt->fetch()) {
        echo json_encode([
            'status' => false,
            'message' => 'Already applied to this job'
        ]);
        exit;
    }

    /* ---------- INSERT APPLICATION ---------- */
    $insertStmt = $pdo->prepare(
        "INSERT INTO applications (job_id, seeker_id, status, applied_at)
         VALUES (?, ?, 'pending', NOW())"
    );
    $insertStmt->execute([$jobId, $seekerId]);

    echo json_encode([
        'status' => true,
        'message' => 'Job applied successfully'
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
