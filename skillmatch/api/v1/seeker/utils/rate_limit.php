<?php
require_once "../../../../includes/config.php";

function rate_limit($user_id, $action, $limit = 5, $per_minutes = 1) {
    global $conn;

    $period_seconds = $per_minutes * 60;

    $stmt = $conn->prepare(
        "SELECT count, period_start
         FROM rate_limit
         WHERE user_id = ? AND action = ? LIMIT 1"
    );
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $now = time();

    if (!$res) {
        $stmt = $conn->prepare(
            "INSERT INTO rate_limit (user_id, action, count, period_start)
             VALUES (?, ?, 1, ?)"
        );
        $stmt->bind_param("isi", $user_id, $action, $now);
        $stmt->execute();
        return true;
    }

    $count = $res['count'];
    $period_start = $res['period_start'];

    if (($now - $period_start) > $period_seconds) {
        $stmt = $conn->prepare(
            "UPDATE rate_limit SET count = 1, period_start = ? 
             WHERE user_id = ? AND action = ?"
        );
        $stmt->bind_param("iis", $now, $user_id, $action);
        $stmt->execute();
        return true;
    }

    if ($count >= $limit) return false;

    $stmt = $conn->prepare(
        "UPDATE rate_limit SET count = count + 1
         WHERE user_id = ? AND action = ?"
    );
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
    return true;
}
