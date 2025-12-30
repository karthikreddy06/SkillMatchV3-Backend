<?php
// post_job.php
require __DIR__ . '/auth_helper.php';
list($pdo, $user) = require_employer();

// Accept JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// minimal validations
$job_id = isset($input['id']) ? (int)$input['id'] : 0;
$title = sanitize_string($input['title'] ?? '', 255);
$location = sanitize_string($input['location'] ?? '', 255);
$job_type = sanitize_string($input['job_type'] ?? 'Full-time', 50);
$min_salary = isset($input['min_salary']) ? (int)$input['min_salary'] : null;
$max_salary = isset($input['max_salary']) ? (int)$input['max_salary'] : null;
$description = sanitize_string($input['description'] ?? '', 5000);
$requirements = $input['requirements'] ?? []; // expect array
$skills = $input['skills'] ?? []; // expect array
$status = validate_enum($input['status'] ?? 'draft', ['draft','published','closed'], 'draft');
$hours_per_week = isset($input['hours_per_week']) ? (int)$input['hours_per_week'] : null;
$shift = sanitize_string($input['shift'] ?? null, 50);

if (!$title) json_response(['status'=>false,'message'=>'title is required'], 400);

// ensure company exists for this employer
$stmt = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$company = $stmt->fetch();
if (!$company) json_response(['status'=>false,'message'=>'Create company profile first'], 400);
$company_id = (int)$company['id'];

// normalize arrays
if (!is_array($requirements)) $requirements = [$requirements];
if (!is_array($skills)) $skills = [$skills];

// sanitize small arrays items
$requirements = array_slice(array_map(function($v){ return sanitize_string($v,500); }, $requirements), 0, 50);
$skills = array_slice(array_map(function($v){ return sanitize_string($v,100); }, $skills), 0, 50);

// convert to JSON for storage (MySQL JSON column)
$requirements_json = json_encode(array_values(array_filter($requirements)));
$skills_json = json_encode(array_values(array_filter($skills)));

if ($job_id > 0) {
    // ensure job belongs to this company
    $check = $pdo->prepare("SELECT id FROM jobs WHERE id = ? AND company_id = ? LIMIT 1");
    $check->execute([$job_id, $company_id]);
    if (!$check->fetch()) json_response(['status'=>false,'message'=>'Job not found or permission denied'], 403);

    $update = $pdo->prepare("UPDATE jobs SET title=?, location=?, job_type=?, salary_min=?, salary_max=?, description=?, requirements=?, skills=?, status=?, hours_per_week=?, shift=?, updated_at=NOW() WHERE id=?");
    $update->execute([$title,$location,$job_type,$min_salary,$max_salary,$description,$requirements_json,$skills_json,$status,$hours_per_week,$shift,$job_id]);
    json_response(['status'=>true,'message'=>'Job updated','job_id'=>$job_id]);
} else {
    $insert = $pdo->prepare("INSERT INTO jobs (company_id, title, location, job_type, salary_min, salary_max, description, requirements, skills, status, hours_per_week, shift, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $insert->execute([$company_id,$title,$location,$job_type,$min_salary,$max_salary,$description,$requirements_json,$skills_json,$status,$hours_per_week,$shift]);
    $newId = (int)$pdo->lastInsertId();
    json_response(['status'=>true,'message'=>'Job created','job_id'=>$newId], 201);
}
