<?php
require_once __DIR__ . '/auth_helper.php';
list($pdo, $employer) = require_employer();

// GET job_id (required)
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
if ($job_id <= 0) {
    json_response(['status' => false, 'message' => 'job_id is required'], 400);
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// STEP 1 — Verify job belongs to employer
$sql = "SELECT j.id, j.title, j.company_id, c.company_name 
        FROM jobs j
        JOIN companies c ON c.id = j.company_id
        WHERE j.id = ? AND c.employer_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$job_id, $employer['id']]);
$job = $stmt->fetch();

if (!$job) {
    json_response(['status' => false, 'message' => 'Invalid job_id or not owned by employer'], 403);
}

// STEP 2 — Count applicants
$sql_count = "SELECT COUNT(*) FROM applications WHERE job_id = ?";
$stmt = $pdo->prepare($sql_count);
$stmt->execute([$job_id]);
$total = (int)$stmt->fetchColumn();

// STEP 3 — Fetch applicants with user details
$sql = "SELECT 
            a.id AS application_id,
            a.cover_letter,
            a.expected_salary,
            a.resume_url,
            a.status,
            a.applied_at,
            a.updated_at,
            u.id AS seeker_id,
            u.name,
            u.email,
            u.phone
        FROM applications a
        JOIN users u ON u.id = a.seeker_id
        WHERE a.job_id = ?
        ORDER BY a.applied_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute([$job_id]);
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Response
json_response([
    'status' => true,
    'job' => [
        'job_id' => $job['id'],
        'title' => $job['title'],
        'company_id' => $job['company_id'],
        'company_name' => $job['company_name']
    ],
    'applicants' => $applicants,
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
], 200);
