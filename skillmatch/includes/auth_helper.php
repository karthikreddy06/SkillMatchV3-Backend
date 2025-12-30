<?php
function require_auth() {
    // Get Authorization header
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$auth) {
        http_response_code(401);
        echo json_encode(["status" => false, "message" => "Missing Authorization header"]);
        exit;
    }

    // Extract token
    $token = trim(str_ireplace("Bearer", "", $auth));
    if (!$token) {
        http_response_code(401);
        echo json_encode(["status" => false, "message" => "Invalid token format"]);
        exit;
    }

    // You are using tokens stored in DB (not JWT)
    global $conn;
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) {
        http_response_code(403);
        echo json_encode(["status" => false, "message" => "Unauthorized"]);
        exit;
    }

    return [
        "id" => $result["id"],
        "role" => $result["role"],
        "token" => $token
    ];
}
