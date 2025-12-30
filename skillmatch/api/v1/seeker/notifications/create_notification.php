<?php
require_once "../../../../includes/config.php";

function create_notification($seeker_id, $title, $message) {
    global $conn;

    $stmt = $conn->prepare(
        "INSERT INTO notifications (seeker_id, title, message) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iss", $seeker_id, $title, $message);
    return $stmt->execute();
}
