<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $material_name = mysqli_real_escape_string($con, $_POST['material_name']);
    $category = mysqli_real_escape_string($con, $_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $unit = mysqli_real_escape_string($con, $_POST['unit']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $supplier_name = mysqli_real_escape_string($con, $_POST['supplier_name']);
    $material_price = (float)$_POST['material_price'];
    $labor_other = (float)$_POST['labor_other'];
    $brand = isset($_POST['brand']) ? mysqli_real_escape_string($con, $_POST['brand']) : '';
    $specification = isset($_POST['specification']) ? mysqli_real_escape_string($con, $_POST['specification']) : '';
    $delivery_status = isset($_POST['delivery_status']) ? mysqli_real_escape_string($con, $_POST['delivery_status']) : 'Pending';
    $total_amount = $material_price + $labor_other;
    
    // First, get the current delivery status to check if it's changing to 'Delivered'
    $current_status_query = $con->query("SELECT delivery_status FROM materials WHERE id = $id");
    $current_status = $current_status_query ? $current_status_query->fetch_assoc()['delivery_status'] : '';
    
    $update_query = "UPDATE materials SET 
        material_name='$material_name', 
        category='$category', 
        quantity=$quantity, 
        unit='$unit', 
        status='$status', 
        supplier_name='$supplier_name', 
        material_price=$material_price, 
        labor_other=$labor_other, 
        brand='$brand',
        specification='$specification',
        delivery_status='$delivery_status',
        total_amount=$total_amount 
        WHERE id=$id";
    
    if ($con->query($update_query)) {
        // Send notification if status changed to 'Delivered'
        if ($delivery_status === 'Delivered' && $current_status !== 'Delivered') {
            $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
            
            // Record expense if there's a price
            if ($material_price > 0) {
                $expense_description = "Purchased Material: $material_name";
                $expense_query = "INSERT INTO order_expenses 
                                (user_id, expense, expensedate, expensecategory, description) 
                                VALUES (?, ?, CURDATE(), 'Materials', ?)";
                $stmt = $con->prepare($expense_query);
                $stmt->bind_param("ids", $user_id, $material_price, $expense_description);
                $stmt->execute();
                $stmt->close();
            }
            
            $notif_type = 'New added material';
            $message = "New added material: $material_name";
            $message_esc = mysqli_real_escape_string($con, $message);
            
            // Find project manager user_id (assuming user_level 3 for project manager)
            $pm_res = $con->query("SELECT id FROM users WHERE user_level = 3 LIMIT 1");
            $pm_id = ($pm_res && $pm_row = $pm_res->fetch_assoc()) ? intval($pm_row['id']) : 1;
            
            $con->query("INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) 
                         VALUES ('$pm_id', '$notif_type', '$message_esc', 0, NOW())");
        }
        
        header('Location: po_materials.php?updated=1');
        exit();
    } else {
        header('Location: po_materials.php?error=1');
        exit();
    }
} else {
    header('Location: po_materials.php');
    exit();
} 