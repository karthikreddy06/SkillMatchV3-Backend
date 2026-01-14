<?php
require_once __DIR__ . '/skillmatch/auth_helper.php';

echo json_encode([
    'status' => true,
    'message' => 'Test file runs successfully',
    'headers' => getallheaders()
]);
