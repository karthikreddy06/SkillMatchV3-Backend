<?php
require_once __DIR__ . '/auth_helper.php';

[$pdo, $employer] = require_employer();

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

/**
 * Fetch notifications for this employer
 * NOTE: seeker_id is actually user_id in your schema
 */
$stmt = $pdo->prepare("
    SELECT 
        id,
        title,
        message,
        read_status,
        created_at
    FROM notifications
    WHERE seeker_id = ?
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute([$employer['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Total count
 */
$count = $pdo->prepare("
    SELECT COUNT(*) 
    FROM notifications 
    WHERE seeker_id = ?
");
$count->execute([$employer['id']]);
$total = (int)$count->fetchColumn();

json_response([
    'status' => true,
    'notifications' => $notifications,
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => ceil($total / $limit)
    ]
]);
