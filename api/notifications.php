<?php
/**
 * api/notifications.php  -  JSON polling endpoint for pop-up messages.
 *
 * The STAFF phone and the CHEF phone call this address every few
 * seconds (see assets/js/notifications.js). It returns the unread
 * notifications for the role of the person who is logged in, then
 * marks them as read so the same message never pops up twice.
 *
 * SECURITY
 *   - Requires a logged-in manager or chef (no guest access).
 *   - Returns only the notification text - no passwords, no student
 *     contact details, no money apart from the amount already shown
 *     on the staff board.
 *   - The response is JSON, so nothing can be reflected as HTML.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$me = current_user();

if ($me === null || !in_array($me['role'], ['manager', 'chef'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'not_allowed']);
    exit;
}

try {
    $rows = notifications_take($me['role']);

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'order_id' => $row['order_id'] !== null ? (int)$row['order_id'] : null,
            'title'    => (string)$row['title'],
            'message'  => (string)$row['message'],
            'at'       => date('h:i A', strtotime($row['created_at'])),
        ];
    }

    echo json_encode(['ok' => true, 'notifications' => $items], JSON_UNESCAPED_UNICODE);
} catch (Exception $ex) {
    error_log('Notification poll failed: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
