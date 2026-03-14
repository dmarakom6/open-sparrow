<?php
// api_notifications.php
session_start();
require_once 'includes/db.php';
header('Content-Type: application/json');

// NOTE: Fetch the logged-in user's ID from the session here. 
// Hardcoded to 1 for testing purposes. Replace with e.g., $_SESSION['user_id']
$userId = $_SESSION['user_id'] ?? 1; 

$action = $_GET['action'] ?? 'get_count';
$today = date('Y-m-d');

try {
    $conn = db_connect();

    // Fetches the count of unread notifications for TODAY or from the past
    if ($action === 'get_count') {
        $sql = "SELECT COUNT(*) FROM app.users_notifications 
                WHERE user_id = $1 AND is_read = FALSE AND notify_date <= $2";
        $res = pg_query_params($conn, $sql, array($userId, $today));
        $count = pg_fetch_result($res, 0, 0);
        echo json_encode(['status' => 'success', 'count' => (int)$count]);
        exit;
    }

    // Fetches the list of notifications for the dropdown menu
    if ($action === 'get_list') {
        $sql = "SELECT * FROM app.users_notifications 
                WHERE user_id = $1 AND notify_date <= $2 
                ORDER BY is_read ASC, created_at DESC LIMIT 10";
        $res = pg_query_params($conn, $sql, array($userId, $today));
        $notifications = pg_fetch_all($res) ?: [];
        echo json_encode(['status' => 'success', 'notifications' => $notifications]);
        exit;
    }

    // Marks the notification as read
    if ($action === 'mark_read') {
        $data = json_decode(file_get_contents('php://input'), true);
        $notifId = $data['id'] ?? 0;
        if ($notifId) {
            $sql = "UPDATE app.users_notifications SET is_read = TRUE WHERE id = $1 AND user_id = $2";
            pg_query_params($conn, $sql, array($notifId, $userId));
            echo json_encode(['status' => 'success']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>