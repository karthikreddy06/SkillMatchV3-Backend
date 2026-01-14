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

/* ---------- INPUT ---------- */
$lat = $_GET['lat'] ?? $_POST['lat'] ?? null;
$lng = $_GET['lng'] ?? $_POST['lng'] ?? null;
$radius = $_GET['radius'] ?? $_POST['radius'] ?? 10;

if ($lat === null || $lng === null) {
    echo json_encode([
        'status' => false,
        'message' => 'Latitude & longitude required'
    ]);
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

    /* ---------- QUERY ---------- */
    $sql = "
        SELECT
            j.id,
            j.title,
            j.location,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.latitude,
            j.longitude,
            c.name AS company_name,
            (
                6371 * ACOS(
                    COS(RADIANS(:lat)) *
                    COS(RADIANS(CAST(j.latitude AS DECIMAL(10,6)))) *
                    COS(RADIANS(CAST(j.longitude AS DECIMAL(10,6))) - RADIANS(:lng)) +
                    SIN(RADIANS(:lat)) *
                    SIN(RADIANS(CAST(j.latitude AS DECIMAL(10,6))))
                )
            ) AS distance
        FROM jobs j
        INNER JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'published'
        HAVING distance <= :radius
        ORDER BY distance ASC
        LIMIT 100
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'lat' => $lat,
        'lng' => $lng,
        'radius' => $radius
    ]);

    echo json_encode([
        'status' => true,
        'jobs' => $stmt->fetchAll()
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Server error'
    ]);
    exit;
}