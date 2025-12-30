<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/security/auth_checks.php';
require_once __DIR__ . '/helpers/apply_constraint_check.php';

$user = require_seeker();
$body = json_decode(file_get_contents('php://input'), true);
$job_id = intval($body['job_id'] ?? 0);
$cover = $body['cover_letter'] ?? '';
$expected = isset($body['expected_salary']) ? intval($body['expected_salary']) : null;

$res = apply_for_job($conn, $job_id, $user['id'], $cover, $expected);
header('Content-Type: application/json');
echo json_encode($res);
