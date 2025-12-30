<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new PDO("mysql:host=127.0.0.1:3307;dbname=skillmatch", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Accept POST first, GET second
    $job_id = $_POST['id'] ?? $_GET['id'] ?? '';

    if (empty($job_id)) {
        echo json_encode(["status"=>false, "message"=>"Job ID required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo json_encode(["status"=>false, "message"=>"Job not found"]);
        exit;
    }

    echo json_encode(["status"=>true, "job"=>$job]);

} catch(Exception $e) {
    echo json_encode(["status"=>false, "message"=>"Server error", "error"=>$e->getMessage()]);
}
?>
