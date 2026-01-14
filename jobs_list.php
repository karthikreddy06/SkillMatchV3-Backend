<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/config.php';

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

    $stmt = $pdo->prepare("
        SELECT
            j.id,
            j.company_id,
            j.title,
            j.location,
            j.latitude,
            j.longitude,
            j.required_skills,
            j.job_type,
            j.salary_min,
            j.salary_max,
            j.created_at,
            c.company_name
        FROM jobs j
        INNER JOIN companies c ON c.id = j.company_id
        WHERE j.status = 'published'
        ORDER BY j.created_at DESC
    ");

    $stmt->execute();
    $jobs = $stmt->fetchAll();

    foreach ($jobs as &$job) {

        // Normalize coordinates
        $job['latitude']  = is_numeric($job['latitude']) ? (float)$job['latitude'] : 0.0;
        $job['longitude'] = is_numeric($job['longitude']) ? (float)$job['longitude'] : 0.0;

        // Normalize required_skills
        if (!empty($job['required_skills'])) {
            $job['required_skills'] = array_map(
                'trim',
                explode(',', $job['required_skills'])
            );
        } else {
            $job['required_skills'] = [];
        }

        // Android-friendly aliases
        $job['companyName'] = $job['company_name'];
        $job['salaryMin']   = (int)$job['salary_min'];
        $job['salaryMax']   = (int)$job['salary_max'];
        $job['jobType']     = $job['job_type'];

        // Helper flag
        $job['has_location'] = !(
            $job['latitude'] == 0.0 && $job['longitude'] == 0.0
        );
    }

    echo json_encode([
        'success' => true,
        'jobs'    => $jobs
    ]);
    exit;

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error'   => $e->getMessage()
    ]);
    exit;
}