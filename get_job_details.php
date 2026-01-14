<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'db.php';

$jobId = $_GET['job_id'] ?? $_POST['job_id'] ?? null;

if (!$jobId || !is_numeric($jobId)) {
    echo json_encode([
        'status' => false,
        'message' => 'job_id is required'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.title,
        j.description,
        j.location,
        j.latitude,
        j.longitude,
        j.required_skills,
        j.job_type AS jobType,
        j.salary_min AS salaryMin,
        j.salary_max AS salaryMax,
        j.created_at,
        c.company_name AS companyName
    FROM jobs j
    JOIN companies c ON c.id = j.company_id
    WHERE j.id = ?
    LIMIT 1
");

$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    echo json_encode([
        'status' => false,
        'message' => 'Job not found'
    ]);
    exit;
}

/* Normalize */
$job['latitude']  = (float) ($job['latitude'] ?? 0);
$job['longitude'] = (float) ($job['longitude'] ?? 0);
$job['required_skills'] = !empty($job['required_skills'])
    ? array_map('trim', explode(',', $job['required_skills']))
    : [];

echo json_encode([
    'status' => true,
    'job' => $job
]);