<?php
session_start();

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

// Check if form is submitted with required fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && isset($_POST['id']) && isset($_POST['warehouse'])) {
    // Get and validate input
    $id = (int)$_POST['id'];
    $warehouse = trim($_POST['warehouse']);
    
    // Input validation
    if (empty($warehouse) || strlen($warehouse) < 2 || strlen($warehouse) > 100) {
        header('Location: po_warehouse_materials.php?error=invalid_name');
        exit();
    }
    
    // Check if warehouse name already exists (case-insensitive check)
    $check_sql = "SELECT id FROM warehouses WHERE LOWER(warehouse) = LOWER(?) AND id != ?";
    $check_stmt = $con->prepare($check_sql);
    $check_stmt->bind_param("si", $warehouse, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: po_warehouse_materials.php?error=name_exists');
        exit();
    }
    
    // Update the warehouse using prepared statement
    $update_sql = "UPDATE warehouses SET warehouse = ? WHERE id = ?";
    $update_stmt = $con->prepare($update_sql);
    $update_stmt->bind_param("si", $warehouse, $id);
    
    if ($update_stmt->execute()) {
        header('Location: po_warehouse_materials.php?updated=1');
        exit();
    } else {
        header('Location: po_warehouse_materials.php?error=update_failed');
        exit();
    }
} else {
    // If not a POST request or missing required fields, redirect back
    header('Location: po_warehouse_materials.php');
    exit();
}