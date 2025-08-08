<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 5) {
    header("Location: supplier_dashboard.php");
    exit();
}
require_once '../config.php';
$con->query("DELETE FROM notifications_supplier");
header("Location: " . $_SERVER['HTTP_REFERER']);
exit(); 