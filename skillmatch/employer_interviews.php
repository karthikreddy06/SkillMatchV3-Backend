<?php
require_once __DIR__ . '/auth_helper.php';

list($pdo, $employer) = require_employer();

// pagination
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// fetch interviews scheduled by this employer
$sql = "
    SELECT
        i.id AS interview_id,
        i.job_id,
        j.title AS job_title,
        i.applicant_id,
        i.start_time,
        i.duration_minutes,
        i.type,
        i.location_or_link,
        i.status,
        i.notes,
        i.created_at
    FROM interviews i
    INNER JOIN jobs j ON j.id = i.job_id
    WHERE i.scheduled_by = ?
    ORDER BY i.start_time DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$employer['id']]);
$interviews = $stmt->fetchAll();

// count total
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM interviews
    WHERE scheduled_by = ?
");
$countStmt->execute([$employer['id']]);
$total = (int)$countStmt->fetchColumn();

json_response([
    'status' => true,
    'interviews' => $interviews,
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);
