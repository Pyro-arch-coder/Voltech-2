<?php
session_start();
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
require_once '../config.php';
if ($id > 0) {
    $con->query("UPDATE notifications_supplier SET is_read = 1 WHERE id = $id");
}
// Redirect back to the referring page, or fallback to dashboard
$redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'supplier_dashboard.php';
header("Location: $redirect");
exit(); 