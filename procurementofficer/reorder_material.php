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
    
    // Get material info
    $material_query = "SELECT * FROM materials WHERE id = $material_id";
    $material_result = $con->query($material_query);
    
    if ($material_result && $material_result->num_rows > 0) {
        $material = $material_result->fetch_assoc();
        
        // Insert into back_orders table with Pending status (set quantity to 0)
        $quantity = 0;
        $insert = $con->prepare("INSERT INTO back_orders (material_id, quantity, reason, requested_by, created_at) VALUES (?, ?, 'Reorder', ?, NOW())");
        $insert->bind_param("iii", $material_id, $quantity, $user_id);
        if ($insert->execute()) {
            $backorder_id = $con->insert_id;
            $insert->close();
            
            // Update material status and delivery status
            $update_material = $con->prepare("UPDATE materials SET delivery_status = 'Pending Reorder' WHERE id = ?");
            $update_material->bind_param("i", $material_id);
            $update_material->execute();
            $update_material->close();
            
            // Insert supplier notification (no quantity in message)
            $supplier_id = isset($material['supplier_id']) ? intval($material['supplier_id']) : 0;
            $notif_type = 'Material Reorder';
            $message = "A reorder for material '{$material['material_name']}' was placed and is pending supplier action.";
            $is_read = 0;

            $insert_notif = $con->prepare("INSERT INTO notifications_supplier (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, NOW())");
            $insert_notif->bind_param("isss", $supplier_id, $notif_type, $message, $is_read);
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