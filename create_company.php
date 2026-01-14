<?php
// create_company.php
require __DIR__ . '/auth_helper.php';

list($pdo, $user) = require_employer();

// Accept JSON or form-data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$company_name = sanitize_string($input['company_name'] ?? '', 255);
$tagline = sanitize_string($input['tagline'] ?? '', 255);
$website = filter_var($input['website'] ?? null, FILTER_SANITIZE_URL);
$location = sanitize_string($input['location'] ?? '', 255);
$industry = sanitize_string($input['industry'] ?? '', 100);
$size = sanitize_string($input['size'] ?? '', 50);
$description = sanitize_string($input['description'] ?? '', 2000);
$logo_url = sanitize_string($input['logo_url'] ?? null, 512);

if (!$company_name) json_response(['status'=>false,'message'=>'company_name is required'], 400);

// check existing company for this employer
$stmt = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare("UPDATE companies SET company_name=?, tagline=?, website=?, location=?, industry=?, size=?, description=?, logo_url=?, updated_at=NOW() WHERE employer_id=?");
    $stmt->execute([$company_name,$tagline,$website,$location,$industry,$size,$description,$logo_url,$user['id']]);
    $company_id = (int)$existing['id'];
    json_response(['status'=>true,'message'=>'Company profile updated','company_id'=>$company_id]);
} else {
    $stmt = $pdo->prepare("INSERT INTO companies (employer_id, company_name, tagline, website, location, industry, size, description, logo_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$user['id'],$company_name,$tagline,$website,$location,$industry,$size,$description,$logo_url]);
    $company_id = (int)$pdo->lastInsertId();
    json_response(['status'=>true,'message'=>'Company profile created','company_id'=>$company_id], 201);
}
