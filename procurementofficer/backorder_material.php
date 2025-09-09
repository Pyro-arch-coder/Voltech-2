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
        // Insert into back_orders table with supplier_name
        $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, supplier_name, created_at) 
                           SELECT ?, ?, ?, ?, supplier_name, NOW() 
                           FROM materials 
                           WHERE id = ?";
        $stmt = $con->prepare($backorder_insert);
        $stmt->bind_param("iisii", $material_id, $backorder_quantity, $final_reason, $user_id, $material_id);
        $stmt->execute();

        // Deduct from material quantity and set delivery_status to 'Pending Backorder' without adding quantities
        $update_material = "UPDATE materials SET quantity = quantity - ?, delivery_status = 'Pending Backorder' WHERE id = ?";
        $update_stmt = $con->prepare($update_material);
        $update_stmt->bind_param("ii", $backorder_quantity, $material_id);
        $update_stmt->execute();

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

        $notif_type = 'Material Backorder';
        $message = "Backorder placed for material '{$material['material_name']}' (Quantity: $backorder_quantity).";
        $is_read = 0;

        $insert_notif = $con->prepare("INSERT INTO notifications_supplier (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insert_notif->bind_param("issi", $supplier_id, $notif_type, $message, $is_read);
        $insert_notif->execute();
        $insert_notif->close();

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
    if (isset($expense_stmt)) $expense_stmt->close();
    if (isset($insert_notif)) $insert_notif->close();
    if (isset($material_stmt)) $material_stmt->close();
    exit();

} else {
    header('Location: po_materials.php');
    exit();
}
?>