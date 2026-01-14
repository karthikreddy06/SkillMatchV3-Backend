<?php
// employer_list_jobs.php
require_once __DIR__ . '/auth_helper.php';
list($pdo, $employer) = require_employer();

// Read query parameters (works for GET or JSON body if someone posts)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$limit = max(1, min(100, $limit)); // enforce bounds
$status = isset($_GET['status']) ? trim($_GET['status']) : null;
$q = isset($_GET['q']) ? trim($_GET['q']) : null;

// If client sent JSON body instead (rare), prefer those
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (is_array($body)) {
    if (isset($body['page'])) $page = max(1, (int)$body['page']);
    if (isset($body['limit'])) $limit = max(1, min(100, (int)$body['limit']));
    if (isset($body['status'])) $status = trim($body['status']);
    if (isset($body['q'])) $q = trim($body['q']);
}

// Get all company ids owned by this employer
$stmt = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ?");
$stmt->execute([$employer['id']]);
$companyRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!$companyRows) {
    json_response(['status' => true, 'jobs' => [], 'meta' => ['page' => $page, 'limit' => $limit, 'total' => 0]], 200);
}

// Build where clauses
$where = [];
$params = [];

// company filter
$placeholders = implode(',', array_fill(0, count($companyRows), '?'));
$where[] = "company_id IN ($placeholders)";
$params = array_merge($params, $companyRows);

if ($status) {
    $allowed = ['draft','published','closed'];
    if (!in_array($status, $allowed, true)) {
        json_response(['status' => false, 'message' => 'Invalid status filter'], 400);
    }
    $where[] = "status = ?";
    $params[] = $status;
}

if ($q) {
    // simple search across title and description
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// count total
$count_sql = "SELECT COUNT(*) FROM jobs $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

// pagination
$offset = ($page - 1) * $limit;
$limit_int = (int)$limit;
$offset_int = (int)$offset;

// fetch rows with company info
$sql = "SELECT j.id, j.company_id, c.company_name, j.title, j.location, j.description, j.requirements, j.skills, j.status, j.salary_min, j.salary_max, j.job_type, j.category, j.latitude, j.longitude, j.address, j.required_skills, j.created_at, j.updated_at
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        $where_sql
        ORDER BY j.created_at DESC
        LIMIT $limit_int OFFSET $offset_int";

// prepare and execute with the same params (limit/offset already inlined)
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// decode JSON fields to arrays (if desired)
foreach ($jobs as &$job) {
    $job['requirements'] = ($job['requirements'] !== null && $job['requirements'] !== '') ? (json_decode($job['requirements'], true) ?? $job['requirements']) : [];
    $job['skills'] = ($job['skills'] !== null && $job['skills'] !== '') ? (json_decode($job['skills'], true) ?? $job['skills']) : [];
    // keep other fields as-is
}
unset($job);

// build response
$meta = [
    'page' => $page,
    'limit' => $limit_int,
    'total' => $total,
    'pages' => $limit_int ? (int)ceil($total / $limit_int) : 0
];

json_response(['status' => true, 'jobs' => $jobs, 'meta' => $meta], 200);
