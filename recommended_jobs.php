<?php
header('Content-Type: application/json; charset=utf-8');
$config = require __DIR__ . '/config.php';

/* ---------- AUTH ---------- */
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

if (!$authHeader || stripos($authHeader, 'Bearer') === false) {
    echo json_encode(['status' => false, 'jobs' => []]);
    exit;
}

$token = trim(str_ireplace('Bearer', '', $authHeader));

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

    /* ---------- USER ---------- */
    $u = $pdo->prepare(
        "SELECT id FROM users WHERE token = ? AND role = 'seeker' LIMIT 1"
    );
    $u->execute([$token]);
    $user = $u->fetch();

    if (!$user) {
        echo json_encode(['status' => false, 'jobs' => []]);
        exit;
    }

    /* ---------- SKILLS ---------- */
    $s = $pdo->prepare("
        SELECT s.name
        FROM user_skills us
        JOIN skills s ON s.id = us.skill_id
        WHERE us.user_id = ?
    ");
    $s->execute([$user['id']]);
    $skills = $s->fetchAll(PDO::FETCH_COLUMN);

    if (empty($skills)) {
        echo json_encode(['status' => true, 'jobs' => []]);
        exit;
    }

    /* ---------- REGEXP ---------- */
    $pattern = implode('|', array_map('preg_quote', $skills));

    /* ---------- JOB MATCH ---------- */
    $q = $pdo->prepare("
        SELECT
            j.id,
            j.title,
            j.location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.description,
            c.name AS company_name
        FROM jobs j
        INNER JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'published'
          AND (
              COALESCE(j.required_skills, '') REGEXP :skills
              OR j.title REGEXP :skills
          )
        ORDER BY j.created_at DESC
        LIMIT 20
    ");

    $q->execute(['skills' => $pattern]);

    echo json_encode([
        'status' => true,
        'jobs' => $q->fetchAll()
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['status' => false, 'jobs' => []]);
    exit;
}