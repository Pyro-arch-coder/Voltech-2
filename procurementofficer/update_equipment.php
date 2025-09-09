<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header("Location: ../login.php");
    exit();
}
require_once '../config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['equipment_id'])) {
    $equipment_id = (int)$_POST['equipment_id'];
    $equipment_name = mysqli_real_escape_string($con, $_POST['equipment_name']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    $depreciation = isset($_POST['depreciation']) && $_POST['depreciation'] !== '' ? floatval($_POST['depreciation']) : null;
    $equipment_price = isset($_POST['equipment_price']) && $_POST['equipment_price'] !== '' ? floatval($_POST['equipment_price']) : null;
    $location = isset($_POST['location']) ? mysqli_real_escape_string($con, $_POST['location']) : null;
    $brand = isset($_POST['brand']) ? mysqli_real_escape_string($con, $_POST['brand']) : null;
    $specification = isset($_POST['specification']) ? mysqli_real_escape_string($con, $_POST['specification']) : null;
    $delivery_status = isset($_POST['delivery_status']) ? mysqli_real_escape_string($con, $_POST['delivery_status']) : 'Pending';

    // First, get the current delivery status to check if it's being changed to 'Delivered'
    $current_status_query = "SELECT delivery_status, equipment_price, equipment_name FROM equipment WHERE id = $equipment_id";
    $current_status_result = $con->query($current_status_query);
    $current_data = $current_status_result->fetch_assoc();
    $was_delivered = ($current_data['delivery_status'] === 'Delivered');

    $update_query = "UPDATE equipment SET 
        equipment_name='$equipment_name', 
        location=" . ($location ? "'$location'" : "NULL") . ", 
        status='$status', 
        depreciation=" . ($depreciation !== null ? "'$depreciation'" : "NULL") . ", 
        equipment_price=" . ($equipment_price !== null ? "'$equipment_price'" : "NULL") . ",
        brand=" . ($brand ? "'$brand'" : "NULL") . ",
        specification=" . ($specification ? "'$specification'" : "NULL") . ",
        delivery_status='$delivery_status'
        WHERE id=$equipment_id";
    
    if ($con->query($update_query)) {
        // If status changed to 'Delivered' and there's a price, record the expense and send notification
        if ($delivery_status === 'Delivered' && !$was_delivered) {
            $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
            
            // Record expense if there's a price
            if ($equipment_price > 0) {
                $expense_description = "Purchased A $equipment_name";
                $expense_query = "INSERT INTO order_expenses 
                                (user_id, expense, expensedate, expensecategory, description) 
                                VALUES (?, ?, CURDATE(), 'Equipment', ?)";
                $stmt = $con->prepare($expense_query);
                $stmt->bind_param("ids", $user_id, $equipment_price, $expense_description);
                $stmt->execute();
                $stmt->close();
            }
            
            // Send notification to project manager
            $notif_type = "New added equipment";
            $notif_message = "New added equipment: $equipment_name";
            
            $notif_query = "INSERT INTO notifications_projectmanager 
                          (user_id, notif_type, message, is_read, created_at) 
                          VALUES (?, ?, ?, 0, NOW())";
            $stmt = $con->prepare($notif_query);
            $stmt->bind_param("iss", $user_id, $notif_type, $notif_message);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: po_equipment.php?success=edit');
        exit();
    } else {
        $err = urlencode($con->error);
        header("Location: po_equipment.php?error=$err");
        exit();
    }
}
?>