<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_id']) && isset($_POST['quantity'])) {
    $material_id = intval($_POST['material_id']);
    $reorder_quantity = intval($_POST['quantity']);
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Get material info
    $material_query = "SELECT * FROM materials WHERE id = $material_id";
    $material_result = $con->query($material_query);
    
    if ($material_result && $material_result->num_rows > 0) {
        $material = $material_result->fetch_assoc();
        
        // Check if total quantity will exceed 1000
        $current_quantity = intval($material['quantity']);
        $total_after_reorder = $current_quantity + $reorder_quantity;
        
        if ($total_after_reorder > 1000) {
            header('Location: po_materials.php?error=' . urlencode('Total quantity cannot exceed 1000. Current: ' . $current_quantity . ', Reorder: ' . $reorder_quantity . ', Total would be: ' . $total_after_reorder));
            exit();
        }
        
        // Insert into back_orders table with Pending status and the specified quantity
        $insert = $con->prepare("INSERT INTO back_orders (material_id, quantity, reason, requested_by, supplier_name, created_at) 
                               SELECT ?, ?, 'Reorder', ?, supplier_name, NOW() 
                               FROM materials 
                               WHERE id = ?");
        $insert->bind_param("iiii", $material_id, $reorder_quantity, $user_id, $material_id);
        if ($insert->execute()) {
            $backorder_id = $con->insert_id;
            $insert->close();
            
            // Update material status and delivery status
            $update_material = $con->prepare("UPDATE materials SET delivery_status = 'Pending Reorder' WHERE id = ?");
            $update_material->bind_param("i", $material_id);
            $update_material->execute();
            $update_material->close();
            
            // Get supplier ID from suppliers table using supplier_name from materials
            $supplier_id = 0;
            $supplier_query = "SELECT id FROM suppliers WHERE supplier_name = ?";
            $supplier_stmt = $con->prepare($supplier_query);
            $supplier_stmt->bind_param("s", $material['supplier_name']);
            if ($supplier_stmt->execute()) {
                $supplier_result = $supplier_stmt->get_result();
                if ($supplier_row = $supplier_result->fetch_assoc()) {
                    $supplier_id = intval($supplier_row['id']);
                }
            }
            $supplier_stmt->close();

            $notif_type = 'Material Reorder';
            $message = "A reorder for {$reorder_quantity} {$material['unit']} of '{$material['material_name']}' was placed and is pending supplier action. Total after reorder: {$total_after_reorder} {$material['unit']}";
            $is_read = 0;

            $insert_notif = $con->prepare("INSERT INTO notifications_supplier (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insert_notif->bind_param("issi", $supplier_id, $notif_type, $message, $is_read);
            $insert_notif->execute();
            $insert_notif->close();
            
            header('Location: po_materials.php?reordered=1');
            exit();
        } else {
            $insert->close();
            header('Location: po_materials.php?error=' . urlencode('Failed to insert back order.'));
            exit();
        }
    } else {
        header('Location: po_materials.php?error=1');
        exit();
    }
}

header('Location: po_materials.php');
exit();
?>