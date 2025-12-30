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

$input = json_decode(file_get_contents('php://input'), true);
$appId = (int)($input['application_id'] ?? 0);
$status = trim($input['status'] ?? '');

$allowed = ['pending', 'shortlisted', 'rejected', 'hired'];

if ($appId <= 0 || !in_array($status, $allowed)) {
    echo json_encode(['status' => false, 'message' => 'Invalid input']);
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

    /* Employer from token */
    $u = $pdo->prepare("
        SELECT id FROM users 
        WHERE token = ? AND role = 'employer' 
        LIMIT 1
    ");
    $u->execute([$token]);
    $employer = $u->fetch();

    if (!$employer) {
        echo json_encode(['status' => false, 'message' => 'Unauthorized']);
        exit;
    }

    /* Verify ownership */
    $chk = $pdo->prepare("
        SELECT a.id
        FROM applications a
        JOIN jobs j ON j.id = a.job_id
        JOIN companies c ON c.id = j.company_id
        WHERE a.id = ? AND c.employer_id = ?
        LIMIT 1
    ");
    $chk->execute([$appId, $employer['id']]);

    if (!$chk->fetch()) {
        echo json_encode([
            'status' => false,
            'message' => 'Not allowed'
        ]);
        exit;
    }

    /* Update status */
    $up = $pdo->prepare("
        UPDATE applications 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $up->execute([$status, $appId]);

    echo json_encode([
        'status' => true,
        'message' => 'Application status updated'
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
