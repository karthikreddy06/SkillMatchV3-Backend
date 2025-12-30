<?php
// security/auth_checks.php
// Lightweight wrappers that use your existing require_auth() from includes/auth_helper.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/skillmatch/includes/auth_helper.php';

/**
 * Require the current user to be authenticated and return the user array.
 * Dies with JSON if not authenticated.
 * @return array ['id'=>int,'role'=>string,'token'=>string]
 */
function require_user() {
    // expect require_auth() to echo/exit on failure
    $user = require_auth();
    if (!is_array($user) || empty($user['id'])) {
        http_response_code(401);
        echo json_encode(["status" => false, "message" => "Unauthorized"]);
        exit;
    }
    return $user;
}

/**
 * Require seeker role (403 otherwise)
 */
function require_seeker() {
    $u = require_user();
    if (($u['role'] ?? '') !== 'seeker') {
        http_response_code(403);
        echo json_encode(["status" => false, "message" => "Forbidden: seeker only"]);
        exit;
    }
    return $u;
}

/**
 * Require employer role (403 otherwise)
 */
function require_employer() {
    $u = require_user();
    if (($u['role'] ?? '') !== 'employer') {
        http_response_code(403);
        echo json_encode(["status" => false, "message" => "Forbidden: employer only"]);
        exit;
    }
    return $u;
}

/**
 * Require admin role (if needed)
 */
function require_admin() {
    $u = require_user();
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(["status" => false, "message" => "Forbidden: admin only"]);
        exit;
    }
    return $u;
}
