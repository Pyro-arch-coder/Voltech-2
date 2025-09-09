<?php
session_start();

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/config.php';

// Check if form is submitted with required fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    // Get and validate input
    $id = (int)$_POST['id'];
    
    // First, check if there are any items in this warehouse
    $check_items_sql = "SELECT COUNT(*) as item_count FROM warehouse_items WHERE warehouse_id = ?";
    $check_stmt = $con->prepare($check_items_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['item_count'] > 0) {
        // If there are items, don't delete, redirect with error
        header('Location: po_warehouse_materials.php?error=warehouse_not_empty');
        exit();
    }
    
    // Delete the warehouse
    $delete_sql = "DELETE FROM warehouses WHERE id = ?";
    $delete_stmt = $con->prepare($delete_sql);
    $delete_stmt->bind_param("i", $id);
    
    if ($delete_stmt->execute()) {
        header('Location: po_warehouse_materials.php?deleted=1');
        exit();
    } else {
        header('Location: po_warehouse_materials.php?error=delete_failed');
        exit();
    }
} else {
    // If not a POST request or missing required fields, redirect back
    header('Location: po_warehouse_materials.php');
    exit();
}
