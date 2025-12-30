<?php
// create_job.php
// Create a job posting (employer only)

require_once __DIR__ . '/auth_helper.php';
list($pdo, $employer) = require_employer(); // returns [$pdo, $user_assoc]

// Read JSON body (primary) or fallback to $_POST for form-data
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    // fallback to form-data/POST
    $input = $_POST;
}

// Basic helper local wrappers
function val_str($arr, $key, $default = null, $max = 1000) {
    if (!isset($arr[$key])) return $default;
    $v = (string)$arr[$key];
    $v = trim($v);
    if ($v === '') return $default;
    return mb_substr($v, 0, $max);
}
function val_int($arr, $key, $default = 0) {
    if (!isset($arr[$key]) || $arr[$key] === '' || $arr[$key] === null) return $default;
    return (int)$arr[$key];
}
function val_float($arr, $key, $default = 0.0) {
    if (!isset($arr[$key]) || $arr[$key] === '' || $arr[$key] === null) return $default;
    return (float)$arr[$key];
}
function json_or_default($v) {
    if ($v === null || $v === '') return '[]';
    if (is_string($v)) {
        // if user passed a JSON string, try to validate
        $dec = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
            return json_encode($dec, JSON_UNESCAPED_UNICODE);
        }
        // treat as comma separated list
        $parts = array_filter(array_map('trim', explode(',', $v)));
        return json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
    }
    if (is_array($v)) {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }
    // fallback
    return '[]';
}

// Allowed status values
$allowed_status = ['draft','published','closed'];

// Required minimal inputs: title (we require a non-empty title)
$title = val_str($input, 'title', null, 200);
if ($title === null) {
    json_response(['status' => false, 'message' => 'title is required'], 400);
}

// Optional / defaults (guarantee non-null)
$location = val_str($input, 'location', 'Location not provided', 255);
$description = val_str($input, 'description', 'No description provided', 5000);
$requirements = json_or_default($input['requirements'] ?? null);
$skills = json_or_default($input['skills'] ?? null);
$salary_min = val_int($input, 'salary_min', 0);
$salary_max = val_int($input, 'salary_max', 0);
$job_type = val_str($input, 'job_type', 'Full-time', 50);
$category = val_str($input, 'category', 'General', 100);
$latitude = val_float($input, 'latitude', 0.0);
$longitude = val_float($input, 'longitude', 0.0);
$address = val_str($input, 'address', 'Address not provided', 1000);
$required_skills = val_str($input, 'required_skills', 'Not specified', 500);

// status (validate)
$status_in = isset($input['status']) ? val_str($input, 'status', null, 20) : 'published';
$status = in_array($status_in, $allowed_status, true) ? $status_in : 'published';

// company existence: ensure this employer has a company record
try {
    $stmt = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ? LIMIT 1");
    $stmt->execute([$employer['id']]);
    $company = $stmt->fetch();
    if (!$company) {
        json_response(['status' => false, 'message' => 'Create your company profile before posting jobs'], 400);
    }
    $company_id = (int)$company['id'];

    // Insert job (transaction)
    $pdo->beginTransaction();

    $sql = "INSERT INTO jobs
        (company_id, title, location, description, requirements, skills, status, salary_min, salary_max, job_type, category, latitude, longitude, address, required_skills, created_at, updated_at)
        VALUES
        (:company_id, :title, :location, :description, :requirements, :skills, :status, :salary_min, :salary_max, :job_type, :category, :latitude, :longitude, :address, :required_skills, NOW(), NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $company_id,
        ':title' => $title,
        ':location' => $location,
        ':description' => $description,
        ':requirements' => $requirements,
        ':skills' => $skills,
        ':status' => $status,
        ':salary_min' => $salary_min,
        ':salary_max' => $salary_max,
        ':job_type' => $job_type,
        ':category' => $category,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':address' => $address,
        ':required_skills' => $required_skills,
    ]);

    $job_id = (int)$pdo->lastInsertId();
    $pdo->commit();

    // Return created response
    http_response_code(201);
    json_response([
        'status' => true,
        'message' => 'Job created successfully',
        'job_id' => $job_id
    ], 201);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // For debugging locally you can show $e->getMessage() but in production avoid leaking details
    json_response(['status' => false, 'message' => 'Server error', 'error' => $e->getMessage()], 500);
}
