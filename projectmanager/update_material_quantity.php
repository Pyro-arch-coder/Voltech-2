<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request with update_quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    header('Content-Type: application/json');
    
    $pem_id = isset($_POST['pem_id']) ? intval($_POST['pem_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    
    if (!$pem_id || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }
    
    // Update the quantity in project_estimating_materials table
    $update_sql = "UPDATE project_estimating_materials 
                   SET quantity = $quantity 
                   WHERE id = $pem_id";
    
    if ($con->query($update_sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Quantity updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $con->error
        ]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 