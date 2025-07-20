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
    
    // Check if there's already a pending reorder request for this material
    $check_pending = "SELECT id FROM back_orders WHERE material_id = ? AND reason = 'Reorder' AND approval_status = 'Pending'";
    $check_stmt = $con->prepare($check_pending);
    $check_stmt->bind_param("i", $material_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header('Location: po_materials.php?error=There is already a pending reorder request for this material');
        exit();
    }
    
    // Get the original material details
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
        
        // Insert into back_orders table with approval_status 'Pending'
        $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, approval_status, created_at) 
                            VALUES (?, ?, 'Reorder', ?, 'Pending', NOW())";
        
        $stmt = $con->prepare($backorder_insert);
        $stmt->bind_param("iii", $material_id, $reorder_quantity, $user_id);
        
        if ($stmt->execute()) {
            // Create a notification for reorder request
            $notif_type = 'Reorder';
            $message = "Reordered material: " . $material['material_name'] . " (Quantity: $reorder_quantity)";
            $message_esc = mysqli_real_escape_string($con, $message);
            
            // Find admin user_id (assuming user_level 2 for admin)
            $admin_res = $con->query("SELECT id FROM users WHERE user_level = 2 LIMIT 1");
            $admin_id = ($admin_res && $admin_row = $admin_res->fetch_assoc()) ? intval($admin_row['id']) : 1;
            
            // Insert notification
            $notif_insert = "INSERT INTO notifications_admin (user_id, notif_type, message, is_read, created_at) VALUES ('$admin_id', '$notif_type', '$message_esc', 0, NOW())";
            $con->query($notif_insert);
            
            header('Location: po_materials.php?reordered=1');
        } else {
            header('Location: po_materials.php?error=1');
        }
    } else {
        header('Location: po_materials.php?error=1');
    }
} else {
    header('Location: po_materials.php');
}
exit();
?> 