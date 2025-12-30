<?php
header('Content-Type: application/json');

// Show headers sent by client (curl/Postman)
$headers = function_exists("getallheaders") ? getallheaders() : [];
$server = $_SERVER;

echo json_encode([
    "headers" => $headers,
    "server"  => [
        "REDIRECT_HTTP_AUTHORIZATION" => $server["REDIRECT_HTTP_AUTHORIZATION"] ?? null,
        "HTTP_AUTHORIZATION" => $server["HTTP_AUTHORIZATION"] ?? null
    ]
], JSON_PRETTY_PRINT);
