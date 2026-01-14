<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new PDO("mysql:host=127.0.0.1:3307;dbname=skillmatch", "root", "");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';
$lat = $data['latitude'] ?? null;
$lng = $data['longitude'] ?? null;

if(empty($token)) die(json_encode(["status"=>false,"message"=>"Token required"]));
if(!is_numeric($lat) || !is_numeric($lng)) die(json_encode(["status"=>false,"message"=>"Invalid coordinates"]));
if($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) die(json_encode(["status"=>false,"message"=>"Coordinates out of range"]));

$stmt = $conn->prepare("UPDATE users SET latitude=?, longitude=? WHERE token=? AND role='seeker'");
$affected = $stmt->execute([$lat, $lng, $token]);

echo json_encode(["status"=>true,"message"=>"Location updated"]);
?>