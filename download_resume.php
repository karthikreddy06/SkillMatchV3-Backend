<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/auth_helper.php';
require_once __DIR__ . '/resume_audit_helpers.php';

$user = require_auth();
$seeker_id = $_GET['seeker_id'] ?? $user['id'];

// Fetch filename
$stmt = $conn->prepare("SELECT resume_path FROM users WHERE id = ?");
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['resume_path'])) {
    echo json_encode(["status" => false, "message" => "Resume not found"]);
    exit;
}

$file = $row['resume_path'];
$path = $_SERVER['DOCUMENT_ROOT'] . "/skillmatch/api/v1/seeker/profile/files/uploads/resumes/$file";

if (!file_exists($path)) {
    echo json_encode(["status" => false, "message" => "File missing"]);
    exit;
}

record_resume_download($conn, $seeker_id, $file, $user['id'], $user['role'], "download");

// Output file
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$file\"");
header("Content-Length: " . filesize($path));
readfile($path);
exit;
