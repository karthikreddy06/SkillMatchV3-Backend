<?php
// company_upload_document.php
require __DIR__ . '/auth_helper.php';

list($pdo, $user) = require_employer();

// make sure company exists
$stmt = $pdo->prepare("SELECT id FROM companies WHERE employer_id = ? LIMIT 1");
$stmt->execute([$user['id']]);
$company = $stmt->fetch();
if (!$company) json_response(['status'=>false,'message'=>'Create company profile first'], 400);
$company_id = (int)$company['id'];

// must be POST multipart
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['status'=>false,'message'=>'Use POST with multipart/form-data'], 405);

// validate doc_type
$doc_type_raw = $_POST['doc_type'] ?? '';
$doc_type = preg_replace('/[^a-z0-9_\-]+/i','', $doc_type_raw);
if (!$doc_type) json_response(['status'=>false,'message'=>'doc_type required (e.g. business_license)'], 400);

// check file
if (!isset($_FILES['file'])) json_response(['status'=>false,'message'=>'file is required'], 400);
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) json_response(['status'=>false,'message'=>'File upload error: '.$file['error']], 400);

// size limit 10MB
$maxBytes = 10 * 1024 * 1024;
if ($file['size'] > $maxBytes) json_response(['status'=>false,'message'=>'File too large (max 10MB)'], 400);

// validate MIME using finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
if (!array_key_exists($mime, $allowed)) json_response(['status'=>false,'message'=>'Invalid file type. Allowed: PDF, JPG, PNG'], 400);

// safe filename and target
$ext = $allowed[$mime];
$safeBase = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-z0-9_\-\.]/i','_', pathinfo($file['name'], PATHINFO_FILENAME));
$filename = $safeBase . '.' . $ext;
$dir = __DIR__ . '/uploads/company_documents/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$target = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $target)) json_response(['status'=>false,'message'=>'Failed to move uploaded file'], 500);

// store record (file_url relative)
$file_url = '/uploads/company_documents/' . $filename;
$stmt = $pdo->prepare("INSERT INTO company_documents (company_id, doc_type, file_url, status, uploaded_at) VALUES (?, ?, ?, 'uploaded', NOW())");
$stmt->execute([$company_id, $doc_type, $file_url]);

json_response(['status'=>true,'message'=>'File uploaded','file_url'=>$file_url], 201);
