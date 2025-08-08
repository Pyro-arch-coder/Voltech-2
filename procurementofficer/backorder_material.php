<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

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
    
    // Removed check for existing backorders as per user request
    
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
    
    // Start transaction
    $con->begin_transaction();
    
    try {
        // Insert into back_orders table
        $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, created_at) 
                            VALUES (?, ?, ?, ?, NOW())";
        $stmt = $con->prepare($backorder_insert);
        $stmt->bind_param("iisi", $material_id, $backorder_quantity, $final_reason, $user_id);
        $stmt->execute();
        
        // Insert into order_expenses for the backorder
        $expense_description = "Material Backorder: {$material['material_name']} (Qty: $backorder_quantity) - Deduction";
        $expense_amount = -1 * ($backorder_quantity * $material['material_price']);
        $expense_insert = "INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) 
                         VALUES (?, ?, CURDATE(), 'Material', ?)";
        $expense_stmt = $con->prepare($expense_insert);
        $expense_stmt->bind_param("ids", $user_id, $expense_amount, $expense_description);
        $expense_stmt->execute();
        
        // Deduct from material quantity
        $update_material = "UPDATE materials SET quantity = quantity - ? WHERE id = ?";
        $update_stmt = $con->prepare($update_material);
        $update_stmt->bind_param("ii", $backorder_quantity, $material_id);
        $update_stmt->execute();
        
        // Add to supplier's stock if supplier exists
        if (!empty($material['supplier_name'])) {
            $update_supplier = "UPDATE suppliers_materials sm 
                              INNER JOIN suppliers s ON sm.supplier_id = s.id 
                              SET sm.quantity = sm.quantity + ? 
                              WHERE s.supplier_name = ? AND sm.material_name = ?";
            $supplier_stmt = $con->prepare($update_supplier);
            $supplier_stmt->bind_param("iss", $backorder_quantity, $material['supplier_name'], $material['material_name']);
            $supplier_stmt->execute();
        }
        
        // Commit transaction
        $con->commit();
        header('Location: po_materials.php?backorder_success=1&material_id=' . $material_id . '&qty=' . $backorder_quantity);
        
    } catch (Exception $e) {
        // Rollback on error
        $con->rollback();
        error_log("Error in backorder process: " . $e->getMessage());
        header('Location: po_materials.php?error=Failed to process backorder: ' . urlencode($e->getMessage()));
    }
    
    // Close statements
    if (isset($stmt)) $stmt->close();
    if (isset($update_stmt)) $update_stmt->close();
    if (isset($supplier_stmt)) $supplier_stmt->close();
    exit();
    
} else {
    header('Location: po_materials.php');
    exit();
}
?> 