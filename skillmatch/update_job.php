<?php
// update_job.php
require_once __DIR__ . '/auth_helper.php';
list($pdo, $employer) = require_employer();

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_response(['status' => false, 'message' => 'Invalid JSON body'], 400);
}

// job_id is required
$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;
if ($job_id <= 0) json_response(['status' => false, 'message' => 'job_id is required'], 400);

// fetch job and ensure it belongs to this employer (via company)
$sql = "SELECT j.id, j.company_id, c.employer_id
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        WHERE j.id = ? LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$job_id]);
$job = $stmt->fetch();
if (!$job) json_response(['status' => false, 'message' => 'Job not found'], 404);
if ((int)$job['employer_id'] !== (int)$employer['id']) {
    json_response(['status' => false, 'message' => 'Forbidden: you do not own this job'], 403);
}

// sanitize updatable fields
$fields = [];
$params = [];

if (isset($input['title'])) {
    $fields[] = 'title = ?';
    $params[] = sanitize_string($input['title'], 255);
}
if (isset($input['location'])) {
    $fields[] = 'location = ?';
    $params[] = sanitize_string($input['location'], 255);
}
if (array_key_exists('job_type', $input)) {
    $fields[] = 'job_type = ?';
    $params[] = sanitize_string($input['job_type'], 50);
}
if (isset($input['salary_min'])) {
    $fields[] = 'salary_min = ?';
    $params[] = ($input['salary_min'] === '' ? null : (int)$input['salary_min']);
}
if (isset($input['salary_max'])) {
    $fields[] = 'salary_max = ?';
    $params[] = ($input['salary_max'] === '' ? null : (int)$input['salary_max']);
}
if (isset($input['description'])) {
    $fields[] = 'description = ?';
    $params[] = sanitize_string($input['description'], 5000);
}
if (array_key_exists('requirements', $input)) {
    $fields[] = 'requirements = ?';
    $params[] = $input['requirements'] === null ? null : json_encode($input['requirements'], JSON_UNESCAPED_UNICODE);
}
if (array_key_exists('skills', $input)) {
    $fields[] = 'skills = ?';
    $params[] = $input['skills'] === null ? null : json_encode($input['skills'], JSON_UNESCAPED_UNICODE);
}
if (isset($input['status'])) {
    $allowed = ['draft','published','closed'];
    $status = in_array($input['status'], $allowed, true) ? $input['status'] : null;
    if ($status === null) json_response(['status' => false, 'message' => 'Invalid status value'], 400);
    $fields[] = 'status = ?';
    $params[] = $status;
}

if (empty($fields)) {
    json_response(['status' => false, 'message' => 'No updatable fields provided'], 400);
}

// Build update
$params[] = $job_id;
$sql = "UPDATE jobs SET " . implode(', ', $fields) . " WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

json_response(['status' => true, 'message' => 'Job updated successfully', 'job_id' => $job_id], 200);
