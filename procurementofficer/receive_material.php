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
        
        // Update material status and delivery status
        $update_material = $con->prepare("UPDATE materials SET status = 'Available', delivery_status = 'Delivered' WHERE id = ?");
        $update_material->bind_param("i", $material_id);
        
        if ($update_material->execute()) {
            // Insert notification
            $notif_type = 'Material Received';
            $message = "Material '{$material['material_name']}' has been received and is now available in stock.";
            $is_read = 0;
            
            // Insert notification for admin or relevant users
            $insert_notif = $con->prepare("INSERT INTO notifications (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, NOW())");
            // Assuming user_id 1 is admin - adjust as needed
            $admin_id = 1;
            $insert_notif->bind_param("issi", $admin_id, $notif_type, $message, $is_read);
            $insert_notif->execute();
            $insert_notif->close();
            
            $_SESSION['success_message'] = 'Material has been marked as received successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to update material status.';
        }
        $update_material->close();
    } else {
        $_SESSION['error_message'] = 'Material not found.';
    }
}

header('Location: po_materials.php');
exit();
?>
