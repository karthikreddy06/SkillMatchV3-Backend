<?php
header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';

$headers = getallheaders();
$token = trim(str_replace('Bearer','',$headers['Authorization'] ?? ''));

$input = json_decode(file_get_contents('php://input'), true);

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
);

$user = $pdo->prepare("SELECT id FROM users WHERE token=? LIMIT 1");
$user->execute([$token]);
$u = $user->fetch();

if(!$u){ echo json_encode(['status'=>false,'message'=>'Invalid token']); exit; }

$q = $pdo->prepare("
INSERT INTO job_preferences
(user_id,salary_min,salary_max,commute_distance,job_types,shift_preferences,experience_levels)
VALUES (?,?,?,?,?,?,?)
ON DUPLICATE KEY UPDATE
salary_min=VALUES(salary_min),
salary_max=VALUES(salary_max),
commute_distance=VALUES(commute_distance),
job_types=VALUES(job_types),
shift_preferences=VALUES(shift_preferences),
experience_levels=VALUES(experience_levels)
");

$q->execute([
 $u['id'],
 $input['salary_min'] ?? 0,
 $input['salary_max'] ?? 0,
 $input['commute_distance'] ?? 25,
 json_encode($input['job_types'] ?? []),
 json_encode($input['shift_preferences'] ?? []),
 json_encode($input['experience_levels'] ?? [])
]);

echo json_encode(['status'=>true,'message'=>'Preferences saved']);
