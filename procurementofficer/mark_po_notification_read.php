<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 4 || !isset($_GET['id'])) {
    header('Location: po_dashboard.php');
    exit();
}
require_once '../config.php';
$id = intval($_GET['id']);

// Mark the notification as read (no user_id check needed)
$con->query("UPDATE notifications_procurement SET is_read = 1 WHERE id = $id");

// Redirect back to the previous page or dashboard
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'po_dashboard.php';
header("Location: $redirect");
exit();