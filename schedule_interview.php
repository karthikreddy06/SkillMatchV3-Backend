<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new PDO(
        "mysql:host=127.0.0.1:3307;dbname=skillmatch;charset=utf8mb4",
        "root",
        ""
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["status" => false, "message" => "Invalid JSON"]);
        exit;
    }

    $token            = trim($data['token'] ?? '');
    $application_id   = (int)($data['application_id'] ?? 0);
    $job_id           = (int)($data['job_id'] ?? 0);
    $start_time       = trim($data['start_time'] ?? '');
    $type             = trim($data['type'] ?? '');
    $location_or_link = trim($data['location_or_link'] ?? '');
    $notes            = trim($data['notes'] ?? '');

    if (
        $token === '' ||
        $application_id <= 0 ||
        $job_id <= 0 ||
        $start_time === '' ||
        $type === '' ||
        $location_or_link === ''
    ) {
        echo json_encode(["status" => false, "message" => "Invalid data"]);
        exit;
    }

    // Validate employer
    $stmt = $conn->prepare("
        SELECT id 
        FROM users 
        WHERE token = ? AND role = 'employer'
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $employer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employer) {
        echo json_encode(["status" => false, "message" => "Invalid employer token"]);
        exit;
    }

    $employer_id = (int)$employer['id'];

    // ✅ Correct ownership validation (THIS WAS THE ISSUE)
    $stmt = $conn->prepare("
        SELECT a.id
        FROM applications a
        JOIN jobs j       ON j.id = a.job_id
        JOIN companies c  ON c.id = j.company_id
        WHERE a.id = ?
          AND a.job_id = ?
          AND c.employer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$application_id, $job_id, $employer_id]);

    if (!$stmt->fetch()) {
        echo json_encode([
            "status" => false,
            "message" => "Application not found or unauthorized"
        ]);
        exit;
    }

    // Insert interview
    $stmt = $conn->prepare("
        INSERT INTO interviews (
            applicant_id,
            job_id,
            start_time,
            type,
            location_or_link,
            notes,
            status,
            created_at,
            updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), NOW()
        )
    ");
    $stmt->execute([
        $application_id,
        $job_id,
        $start_time,
        $type,
        $location_or_link,
        $notes
    ]);

    // Update application status
    $stmt = $conn->prepare("
        UPDATE applications
        SET status = 'interview_scheduled',
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$application_id]);

    echo json_encode([
        "status" => true,
        "message" => "Interview scheduled successfully"
    ]);

} catch (Throwable $e) {
    echo json_encode([
        "status" => false,
        "error" => $e->getMessage(),
        "line" => $e->getLine()
    ]);
}
