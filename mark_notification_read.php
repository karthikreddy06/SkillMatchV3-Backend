<?php
require_once __DIR__ . '/auth_helper.php';

[$pdo, $employer] = require_employer();

$input = json_decode(file_get_contents('php://input'), true);
$notification_id = (int)($input['notification_id'] ?? 0);

if ($notification_id <= 0) {
    json_response([
        'status' => false,
        'message' => 'notification_id is required'
    ], 400);
}

/*
  Update only if:
  - notification belongs to this employer
  - recipient_role = employer
*/
$stmt = $pdo->prepare("
    UPDATE notifications
    SET read_status = 'read'
    WHERE id = ?
      AND recipient_id = ?
      AND recipient_role = 'employer'
");
$stmt->execute([
    $notification_id,
    $employer['id']
]);

if ($stmt->rowCount() === 0) {
    json_response([
        'status' => false,
        'message' => 'Notification not found or access denied'
    ], 404);
}

json_response([
    'status' => true,
    'message' => 'Notification marked as read',
    'notification_id' => $notification_id
]);
