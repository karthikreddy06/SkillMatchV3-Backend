<?php
// update_application_status.php
require_once __DIR__ . '/auth_helper.php';
list($pdo, $employer) = require_employer();

// Read body (JSON preferred) or fallback to $_POST
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

// Helpers
function get_int($arr, $k, $def = null) {
    if (!isset($arr[$k]) || $arr[$k] === '') return $def;
    return (int)$arr[$k];
}
function get_str($arr, $k, $def = null, $max = 2000) {
    if (!isset($arr[$k])) return $def;
    $s = trim((string)$arr[$k]);
    if ($s === '') return $def;
    return mb_substr($s, 0, $max);
}

// required
$app_id = get_int($input, 'application_id', 0);
$status = get_str($input, 'status', null, 50);

if ($app_id <= 0) json_response(['status' => false, 'message' => 'application_id is required'], 400);
if ($status === null) json_response(['status' => false, 'message' => 'status is required'], 400);

// allowed statuses
$allowed = ['applied','under_review','shortlisted','interview_scheduled','offered','accepted','rejected'];
if (!in_array($status, $allowed, true)) {
    json_response(['status' => false, 'message' => 'Invalid status'], 400);
}

try {
    // 1) Fetch application and verify employer owns the job
    $sql = "SELECT a.id AS application_id, a.job_id, a.seeker_id, j.company_id
            FROM applications a
            JOIN jobs j ON j.id = a.job_id
            JOIN companies c ON c.id = j.company_id
            WHERE a.id = ? AND c.employer_id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$app_id, $employer['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(['status' => false, 'message' => 'Application not found or not authorized'], 404);
    }

    // Begin transaction
    $pdo->beginTransaction();

    // 2) Update application status
    $upd = $pdo->prepare("UPDATE applications SET status = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$status, $app_id]);

    $response = ['status' => true, 'message' => 'Application status updated', 'application_id' => (int)$app_id];

    // 3) If scheduling interview, optionally create interviews row (if scheduled_at provided)
    if ($status === 'interview_scheduled' && !empty($input['scheduled_at'])) {
        // validate scheduled_at (basic)
        $scheduled_at_raw = get_str($input, 'scheduled_at', null, 50); // expecting 'YYYY-MM-DD HH:MM:SS' or ISO
        $scheduled_at = date('Y-m-d H:i:s', strtotime($scheduled_at_raw));
        if ($scheduled_at === false || $scheduled_at_raw === null) {
            // roll back
            $pdo->rollBack();
            json_response(['status' => false, 'message' => 'Invalid scheduled_at datetime'], 400);
        }

        $duration = get_int($input, 'duration_minutes', 30);
        $type = get_str($input, 'type', 'video', 20);
        $type_allowed = ['video','phone','in-person'];
        if (!in_array($type, $type_allowed, true)) $type = 'video';
        $location_or_link = get_str($input, 'location_or_link', null, 1000);

        // Insert interview — applicant_id should reference applications.id per your schema
        $ins = $pdo->prepare("INSERT INTO interviews (applicant_id, job_id, scheduled_by, start_time, duration_minutes, type, location_or_link, status, created_at, updated_at)
                              VALUES (:applicant_id, :job_id, :scheduled_by, :start_time, :duration_minutes, :type, :location_or_link, 'scheduled', NOW(), NOW())");
        $ins->execute([
            ':applicant_id' => $app_id,
            ':job_id' => $row['job_id'],
            ':scheduled_by' => $employer['id'],
            ':start_time' => $scheduled_at,
            ':duration_minutes' => $duration,
            ':type' => $type,
            ':location_or_link' => $location_or_link
        ]);
        $interview_id = (int)$pdo->lastInsertId();
        $response['interview_id'] = $interview_id;
        $response['message'] = 'Application status updated and interview scheduled';
    }

    // Commit
    $pdo->commit();
    json_response($response, 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // For local debugging include $e->getMessage(), but avoid leaking in production
    json_response(['status' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
}
