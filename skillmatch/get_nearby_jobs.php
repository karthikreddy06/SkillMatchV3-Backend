<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new PDO("mysql:host=127.0.0.1:3307;dbname=skillmatch", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $lat = $_POST['lat'] ?? $_GET['lat'] ?? null;
    $lng = $_POST['lng'] ?? $_GET['lng'] ?? null;
    $radius = $_POST['radius'] ?? $_GET['radius'] ?? 10;

    if ($lat === null || $lng === null) {
        echo json_encode(["status" => false, "message" => "Latitude & Longitude required"]);
        exit;
    }

    // prepared statement uses cast inside SQL so string columns still work
    $sql = "
        SELECT id, title, latitude, longitude,
        (
            6371 * ACOS(
                COS(RADIANS(?)) *
                COS(RADIANS(CAST(latitude AS DECIMAL(10,6)))) *
                COS(RADIANS(CAST(longitude AS DECIMAL(10,6))) - RADIANS(?)) +
                SIN(RADIANS(?)) *
                SIN(RADIANS(CAST(latitude AS DECIMAL(10,6))))
            )
        ) AS distance
        FROM jobs
        HAVING distance <= ?
        ORDER BY distance ASC
        LIMIT 100
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$lat, $lng, $lat, $radius]);
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => true, "jobs" => $jobs]);

} catch (Exception $e) {
    echo json_encode(["status" => false, "message" => "Server error", "error" => $e->getMessage()]);
}
?>
