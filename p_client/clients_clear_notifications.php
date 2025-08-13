<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

$client_id = intval($_SESSION['user_id']);
require_once '../config.php';

// Delete only the current client's notifications
$stmt = $con->prepare("DELETE FROM notifications_client WHERE user_id = ?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$stmt->close();

// Redirect back to the referring page
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'clients_dashboard.php';
header("Location: $redirect");
exit();