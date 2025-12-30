<?php
// auth_helper.php
// Central auth + validation helpers for employer endpoints

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Send JSON response and exit
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ---- FIXED & FULLY COMPATIBLE TOKEN EXTRACTION ----
 * Works for:
 *  - curl
 *  - Postman Header tab
 *  - Postman Authorization tab
 *  - Windows XAMPP Apache (which sometimes uses REDIRECT_HTTP_AUTHORIZATION)
 *  - PHP getallheaders() / apache_request_headers()
 *  - Form POST "token"
 */
function get_bearer_token() {

    $possible = [];

    // 1) Standard PHP server variables
    $possible[] = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    $possible[] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    $possible[] = $_SERVER['Authorization'] ?? null;

    // 2) getallheaders()
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) {
            foreach ($h as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    $possible[] = $v;
                }
            }
        }
    }

    // 3) apache_request_headers()
    if (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        if (is_array($h)) {
            foreach ($h as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    $possible[] = $v;
                }
            }
        }
    }

    // 4) POST token fallback (multipart/form-data or form-data)
    if (!empty($_POST['token'])) {
        $possible[] = 'Bearer ' . trim($_POST['token']);
    }

    // Extract Bearer token from all candidates
    foreach ($possible as $auth) {
        if (!$auth) continue;

        $auth = trim($auth);

        // Normalize weird Postman newlines: remove \r, \n
        $auth = str_replace(["\r", "\n"], '', $auth);

        if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return trim($m[1]);
        }
    }

    return null;
}

/**
 * PDO connection
 */
function get_pdo() {
    $config = require __DIR__ . '/config.php';
    return new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

/**
 * Require employer authentication
 */
function require_employer() {
    $token = get_bearer_token();
    if (!$token)
        json_response(['status' => false, 'message' => 'Missing Authorization token'], 401);

    $pdo = get_pdo();
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, is_verified
        FROM users
        WHERE token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user)
        json_response(['status' => false, 'message' => 'Invalid or expired token'], 401);

    if ($user['role'] !== 'employer')
        json_response(['status' => false, 'message' => 'Forbidden: employer access only'], 403);

    $user['id'] = (int)$user['id'];
    return [$pdo, $user];
}

/**
 * Sanitizers
 */
function sanitize_string($s, $max = 1000) {
    $s = trim((string)$s);
    if ($s === '') return null;
    return mb_substr($s, 0, $max);
}

function validate_enum($val, $allowed, $default = null) {
    if ($val === null) return $default;
    return in_array($val, $allowed, true) ? $val : $default;
}
