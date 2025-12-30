<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $conn = new PDO("mysql:host=127.0.0.1:3307;dbname=skillmatch", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read params
    $q = $_POST['q'] ?? $_GET['q'] ?? '';
    $location = $_POST['location'] ?? $_GET['location'] ?? '';

    if (empty($q)) {
        echo json_encode(["status" => false, "message" => "Search query required"]);
        exit;
    }

    $likeQ = "%".$q."%";

    if (!empty($location)) {
        $likeL = "%".$location."%";

        $stmt = $conn->prepare("
            SELECT id, title, salary_min, salary_max, address AS location
            FROM jobs
            WHERE (title LIKE ? OR description LIKE ?)
              AND address LIKE ?
        ");
        $stmt->execute([$likeQ, $likeQ, $likeL]);

    } else {

        $stmt = $conn->prepare("
            SELECT id, title, salary_min, salary_max, address AS location
            FROM jobs
            WHERE title LIKE ? OR description LIKE ?
        ");
        $stmt->execute([$likeQ, $likeQ]);
    }

    echo json_encode([
        "status" => true,
        "results" => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ]);
}
?>
