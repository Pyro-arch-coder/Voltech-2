<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 2) {
    header("Location: admin_manage_users.php");
    exit();
}
require_once '../config.php';
$con->query("DELETE FROM notifications_admin");
header("Location: " . $_SERVER['HTTP_REFERER']);
exit(); 