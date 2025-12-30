<?php
// my_applications.php
// Returns paginated list of applications for the authenticated seeker.
// Path: C:\xampp\htdocs\skillmatch\api\v1\seeker\applications\my_applications.php

header('Content-Type: application/json; charset=utf-8');

require_once "../../../../includes/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/security/auth_checks.php';

// enforce seeker authentication
$user = require_seeker();
$seeker_id = intval($user['id']);

// read query params
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;

// sanitize
if ($limit <= 0) $limit = 20;
if ($limit > 200) $limit = 200;
if ($offset < 0) $offset = 0;

try {
    // 1) total count (with optional status filter)
    if ($statusFilter) {
        $cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE seeker_id = ? AND status = ?");
        $cntStmt->bind_param("is", $seeker_id, $statusFilter);
    } else {
        $cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM applications WHERE seeker_id = ?");
        $cntStmt->bind_param("i", $seeker_id);
    }
    if (!$cntStmt->execute()) throw new Exception("Count query failed: " . $cntStmt->error);
    $cntRes = $cntStmt->get_result()->fetch_assoc();
    $total = intval($cntRes['total'] ?? 0);
    $cntStmt->close();

    // 2) fetch paginated applications with job summary
    // Note: We join jobs to return title, city/address, salary range and employer_id
    $sql = "
        SELECT
            a.id AS application_id,
            a.job_id,
            a.cover_letter,
            a.expected_salary,
            a.status,
            a.applied_at,
            j.title AS job_title,
            j.employer_id,
            j.salary_min,
            j.salary_max,
            j.category,
            j.latitude,
            j.longitude,
            j.address
        FROM applications a
        LEFT JOIN jobs j ON j.id = a.job_id
        WHERE a.seeker_id = ?
    ";

    if ($statusFilter) {
        $sql .= " AND a.status = ?";
        $sql .= " ORDER BY a.applied_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        // bind: seeker_id (i), status (s), offset (i), limit (i)
        $stmt->bind_param("isii", $seeker_id, $statusFilter, $offset, $limit);
    } else {
        $sql .= " ORDER BY a.applied_at DESC LIMIT ?, ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        // bind: seeker_id (i), offset (i), limit (i)
        $stmt->bind_param("iii", $seeker_id, $offset, $limit);
    }

    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $res = $stmt->get_result();
    $apps = [];
    while ($row = $res->fetch_assoc()) {
        $apps[] = [
            'application_id' => intval($row['application_id']),
            'job_id' => intval($row['job_id']),
            'cover_letter' => $row['cover_letter'],
            'expected_salary' => $row['expected_salary'] !== null ? intval($row['expected_salary']) : null,
            'status' => $row['status'] ?? null,
            'applied_at' => $row['applied_at'],
            'job' => [
                'title' => $row['job_title'],
                'employer_id' => $row['employer_id'] !== null ? intval($row['employer_id']) : null,
                'salary_min' => $row['salary_min'] !== null ? intval($row['salary_min']) : null,
                'salary_max' => $row['salary_max'] !== null ? intval($row['salary_max']) : null,
                'category' => $row['category'],
                'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
                'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
                'address' => $row['address']
            ]
        ];
    }
    $stmt->close();

    // 3) return JSON
    echo json_encode([
        'status' => true,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'applications' => $apps
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
