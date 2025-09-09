<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$client_id = intval($_SESSION['user_id']);

require_once '../config.php';

if ($id > 0) {
    // Only update if the notification belongs to the current client
    $stmt = $con->prepare("UPDATE notifications_client SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $client_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the referring page, or fallback to client dashboard
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'clients_dashboard.php';
header("Location: $redirect");
exit();