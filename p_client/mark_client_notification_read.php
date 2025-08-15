<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Notification ID is required');
}

require_once '../config.php';

$notification_id = intval($_GET['id']);
$client_email = $_SESSION['email'];

// Mark the notification as read
$stmt = $con->prepare("UPDATE notifications_client SET is_read = 1 WHERE id = ? AND client_email = ?");
$stmt->bind_param("is", $notification_id, $client_email);

if ($stmt->execute()) {
    // Return success response
    echo json_encode(['success' => true]);
} else {
    // Return error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
}

$stmt->close();
?>
