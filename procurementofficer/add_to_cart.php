<?php
session_start();
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart'])) {
    foreach ($_POST['cart'] as $material_id) {
        $material_id = intval($material_id);
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $reason = 'Batch Reorder';
        $quantity = 1; // You can enhance this to allow custom quantity
        $con->query("INSERT INTO back_orders (material_id, quantity, reason, requested_by) VALUES ($material_id, $quantity, '$reason', $user_id)");
    }
    header('Location: po_materials.php?cart_added=1');
    exit();
} else {
    header('Location: po_materials.php');
    exit();
} 