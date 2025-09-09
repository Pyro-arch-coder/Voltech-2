<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

require_once '../config.php';

// Get the client's email from session
$client_email = $_SESSION['email'];

// Delete only the current client's notifications
$stmt = $con->prepare("DELETE FROM notifications_client WHERE client_email = ?");
$stmt->bind_param("s", $client_email);

if ($stmt->execute()) {
    $_SESSION['success'] = 'All notifications have been cleared.';
} else {
    $_SESSION['error'] = 'Failed to clear notifications. Please try again.';
}

$stmt->close();

// Redirect back to the referring page
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'clients_dashboard.php';
header("Location: $redirect");
exit;
exit();