<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
$con = new mysqli("localhost", "root", "", "voltech2");
// Delete all notifications (no user_id filter)
$con->query("DELETE FROM notifications_procurement");
header("Location: " . $_SERVER['HTTP_REFERER']);
exit();