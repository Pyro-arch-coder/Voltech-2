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
    
    $material_query = "SELECT * FROM materials WHERE id = $material_id";
    $material_result = $con->query($material_query);
    
    if ($material_result && $material_result->num_rows > 0) {
        $material = $material_result->fetch_assoc();
        
        // Calculate reorder quantity (25% of max_stock, or 10 if max_stock is not set)
        $max_stock = isset($material['max_stock']) ? $material['max_stock'] : 100;
        $reorder_quantity = max(10, intval($max_stock * 0.25));
        
        // Check supplier availability for this material
        $supplier_name = $material['supplier_name'];
        $supplier_available_qty = 0;
        
        if ($supplier_name) {
            // Get supplier ID first
            $supplier_query = "SELECT id FROM suppliers WHERE supplier_name = ?";
            $supplier_stmt = $con->prepare($supplier_query);
            $supplier_stmt->bind_param("s", $supplier_name);
            $supplier_stmt->execute();
            $supplier_result = $supplier_stmt->get_result();
            
            if ($supplier_result && $supplier_result->num_rows > 0) {
                $supplier_data = $supplier_result->fetch_assoc();
                $supplier_id = $supplier_data['id'];
                
                // Check available quantity in supplier_materials
                $supplier_materials_query = "SELECT quantity FROM suppliers_materials WHERE supplier_id = ? AND material_name = ?";
                $supplier_materials_stmt = $con->prepare($supplier_materials_query);
                $supplier_materials_stmt->bind_param("is", $supplier_id, $material['material_name']);
                $supplier_materials_stmt->execute();
                $supplier_materials_result = $supplier_materials_stmt->get_result();
                
                if ($supplier_materials_result && $supplier_materials_result->num_rows > 0) {
                    $supplier_materials_data = $supplier_materials_result->fetch_assoc();
                    $supplier_available_qty = intval($supplier_materials_data['quantity']);
                }
            }
        }
        
        // Validate if supplier has enough quantity
        if ($supplier_available_qty < $reorder_quantity) {
            $error_message = "Cannot reorder: Supplier only has $supplier_available_qty units available, but reorder requires $reorder_quantity units.";
            header('Location: po_materials.php?error=' . urlencode($error_message));
            exit();
        }
        
        // Start transaction for atomic operations
        $con->begin_transaction();
        
        try {
            // Insert into back_orders table
            $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, created_at) 
                                VALUES (?, ?, 'Reorder', ?, NOW())";
            
            $stmt = $con->prepare($backorder_insert);
            $stmt->bind_param("iii", $material_id, $reorder_quantity, $user_id);
            $stmt->execute();
            
            // Insert into order_expenses for the reorder
            $expense_description = "Material Reorder: {$material['material_name']} (Qty: $reorder_quantity)";
            $expense_amount = $reorder_quantity * $material['material_price'];
            $expense_insert = "INSERT INTO order_expenses (user_id, expense, expensedate, expensecategory, description) 
                             VALUES (?, ?, CURDATE(), 'Material', ?)";
            $expense_stmt = $con->prepare($expense_insert);
            $expense_stmt->bind_param("ids", $user_id, $expense_amount, $expense_description);
            $expense_stmt->execute();
            
            // Update the material quantity by adding the reorder quantity
            $update_quantity = "UPDATE materials SET quantity = quantity + ? WHERE id = ?";
            $update_stmt = $con->prepare($update_quantity);
            $update_stmt->bind_param("ii", $reorder_quantity, $material_id);
            $update_stmt->execute();
            
            // Deduct from supplier's stock
            $update_supplier_stock = "UPDATE suppliers_materials sm 
                                    INNER JOIN suppliers s ON sm.supplier_id = s.id 
                                    SET sm.quantity = GREATEST(0, sm.quantity - ?) 
                                    WHERE s.supplier_name = ? AND sm.material_name = ?";
            $update_supplier_stmt = $con->prepare($update_supplier_stock);
            $update_supplier_stmt->bind_param("iss", $reorder_quantity, $supplier_name, $material['material_name']);
            $update_supplier_stmt->execute();
            
            // Check if supplier has sufficient stock after deduction
            $check_stock = "SELECT sm.quantity FROM suppliers_materials sm 
                           INNER JOIN suppliers s ON sm.supplier_id = s.id 
                           WHERE s.supplier_name = ? AND sm.material_name = ?";
            $check_stmt = $con->prepare($check_stock);
            $check_stmt->bind_param("ss", $supplier_name, $material['material_name']);
            $check_stmt->execute();
            $stock_result = $check_stmt->get_result();
            $stock_data = $stock_result->fetch_assoc();
            
            // If stock is low after deduction, send notification
            if ($stock_data && $stock_data['quantity'] < $reorder_quantity) {
                $notif_type = 'Low Stock Alert';
                $message = "Supplier '$supplier_name' has low stock for material '{$material['material_name']}'. Current stock: {$stock_data['quantity']}";
                $message_esc = mysqli_real_escape_string($con, $message);
                
                // Insert notification for procurement
                $con->query("INSERT INTO notifications_procurement (notif_type, message, is_read, created_at) 
                            VALUES ('$notif_type', '$message_esc', 0, NOW())");
            }
            
            // Commit all changes if everything is successful
            $con->commit();
            header('Location: po_materials.php?reordered=1');
            
        } catch (Exception $e) {
            // Rollback on any error
            $con->rollback();
            error_log("Error in reorder process: " . $e->getMessage());
            header('Location: po_materials.php?error=' . urlencode("Failed to process reorder: " . $e->getMessage()));
            exit();
        } finally {
            // Close all statements
            if (isset($stmt)) $stmt->close();
            if (isset($update_stmt)) $update_stmt->close();
            if (isset($update_supplier_stmt)) $update_supplier_stmt->close();
            if (isset($check_stmt)) $check_stmt->close();
        }
    } else {
        header('Location: po_materials.php?error=1');
        exit();
    }
}

// Handle backorder confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_backorder' && isset($_POST['backorder_id'])) {
    $backorder_id = intval($_POST['backorder_id']);
    $result = confirmBackorder($con, $backorder_id);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

header('Location: po_materials.php');
exit();
?>