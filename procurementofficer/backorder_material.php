<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_id'])) {
    $material_id = intval($_POST['material_id']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Get form data
    $deduct_quantity = isset($_POST['deduct_quantity']) ? intval($_POST['deduct_quantity']) : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $other_reason = isset($_POST['other_reason']) ? trim($_POST['other_reason']) : '';
    
    // Backorder quantity is same as deduct quantity
    $backorder_quantity = $deduct_quantity;
    
    // Validation
    if (!$material_id || $deduct_quantity <= 0 || empty($reason)) {
        header('Location: po_materials.php?error=Invalid input data');
        exit();
    }
    
    // Check if there's already a backorder request for this material
    $check_pending = "SELECT id FROM back_orders WHERE material_id = ? AND reason != 'Reorder'";
    $check_stmt = $con->prepare($check_pending);
    $check_stmt->bind_param("i", $material_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: po_materials.php?error=There is already a backorder request for this material');
        exit();
    }
    
    // Get current material data to check if it exists and get material name
    $material_query = "SELECT * FROM materials WHERE id = ?";
    $material_stmt = $con->prepare($material_query);
    $material_stmt->bind_param("i", $material_id);
    $material_stmt->execute();
    $material_result = $material_stmt->get_result();
    
    if ($material_result->num_rows === 0) {
        header('Location: po_materials.php?error=Material not found');
        exit();
    }
    
    $material = $material_result->fetch_assoc();
    
    // Check if backorder quantity is valid (cannot exceed current stock)
    if ($backorder_quantity > $material['quantity']) {
        header('Location: po_materials.php?error=Backorder quantity cannot exceed current stock');
        exit();
    }
    
    // Prepare reason text
    $final_reason = $reason;
    if ($reason === 'Other' && !empty($other_reason)) {
        $final_reason = 'Other: ' . $other_reason;
    }
    
    // Insert into back_orders table
    $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, created_at) 
                        VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $con->prepare($backorder_insert);
    $stmt->bind_param("iisi", $material_id, $backorder_quantity, $final_reason, $user_id);
    
    if ($stmt->execute()) {
        // Success redirect with new message
        header('Location: po_materials.php?backordered=1');
    } else {
        header('Location: po_materials.php?error=Failed to create backorder request');
    }
    exit();
    
} else {
    header('Location: po_materials.php');
    exit();
}
?> 