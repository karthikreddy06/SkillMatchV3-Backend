<?php
header("Content-Type: application/json");

// Correct path: 4 folders up (applications → seeker → v1 → api)
require_once "../../../../includes/config.php";

// Change this to your logged-in seeker ID
$seeker_id = 4;

$sql = "SELECT id, job_id, seeker_id, status, applied_at 
        FROM applications 
        WHERE seeker_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seeker_id);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode($result->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
