<?php
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__ . '/config.php';

/* -------- AUTH -------- */
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['status' => false, 'message' => 'Missing token']);
    exit;
}
$token = trim(str_replace('Bearer', '', $headers['Authorization']));

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    /* Get seeker */
    $u = $pdo->prepare("SELECT id FROM users WHERE token = ?");
    $u->execute([$token]);
    $user = $u->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid token']);
        exit;
    }

    $seekerId = $user['id'];

    /* Get seeker skills */
    $s = $pdo->prepare("SELECT skill FROM user_skills WHERE user_id = ?");
    $s->execute([$seekerId]);
    $skills = $s->fetchAll(PDO::FETCH_COLUMN);

    if (empty($skills)) {
        echo json_encode(['status' => true, 'jobs' => []]);
        exit;
    }

    /* Match jobs */
    $like = implode('|', $skills);

    $q = $pdo->prepare("
        SELECT j.id, j.title, j.location, j.job_type, j.salary_min, j.salary_max,
               c.company_name
        FROM jobs j
        JOIN companies c ON j.company_id = c.id
        WHERE j.status = 'published'
          AND j.required_skills REGEXP ?
        ORDER BY j.created_at DESC
        LIMIT 10
    ");
    $q->execute([$like]);

    echo json_encode([
        'status' => true,
        'jobs' => $q->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Server error'
    ]);
}
