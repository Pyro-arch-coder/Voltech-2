<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has supplier access
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are provided
if (!isset($_POST['backorder_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$backorderId = $con->real_escape_string($_POST['backorder_id']);
$status = $con->real_escape_string($_POST['status']);
$userId = $_SESSION['user_id'];

// Validate status
if (!in_array($status, ['Approved', 'Rejected', 'Pending'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Start transaction
    $con->begin_transaction();
    
    // Update the backorder status
    $updateQuery = "UPDATE back_orders SET status = ? WHERE id = ?";
    $stmt = $con->prepare($updateQuery);
    $stmt->bind_param('si', $status, $backorderId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update backorder status");
    }
    
    // If approved, update the material's delivery status, insert into approved orders, and send notification
    if ($status === 'Approved') {
        // Get the backorder details including the reason and requester
        $backorderQuery = "SELECT bo.material_id, bo.reason, bo.requested_by, bo.quantity, m.material_name 
                          FROM back_orders bo 
                          JOIN materials m ON bo.material_id = m.id 
                          WHERE bo.id = ?";
        $stmt = $con->prepare($backorderQuery);
        $stmt->bind_param('i', $backorderId);
        $stmt->execute();
        $backorder = $stmt->get_result()->fetch_assoc();
        
        // Insert into suppliers_orders_approved
        $insertQuery = "INSERT INTO suppliers_orders_approved (user_id, material_name, quantity, type) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $con->prepare($insertQuery);
        $orderType = ($backorder['reason'] === 'Reorder') ? 'reorder' : 'backorder';
        $stmt->bind_param('isis', $backorder['requested_by'], $backorder['material_name'], 
                         $backorder['quantity'], $orderType);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into approved orders");
        }
        $stmt->bind_param('i', $backorderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($backorder = $result->fetch_assoc()) {
            // Determine the delivery status based on the reason
            $isReorder = ($backorder['reason'] === 'Reorder');
            $deliveryStatus = $isReorder ? 'Reorder Delivery' : 'Backorder Delivery';
            $notifType = $isReorder ? 'reorder_approved' : 'backorder_approved';
            
            // First, get the current supplier's quantity for this material
            $supplierMaterialQuery = "SELECT quantity FROM suppliers_materials 
                                    WHERE material_id = ? AND supplier_id = (SELECT supplier_id FROM users WHERE id = ?)";
            $stmt = $con->prepare($supplierMaterialQuery);
            $stmt->bind_param('ii', $backorder['material_id'], $userId);
            $stmt->execute();
            $supplierMaterial = $stmt->get_result()->fetch_assoc();
            
            if (!$supplierMaterial || $supplierMaterial['quantity'] < $backorder['quantity']) {
                throw new Exception("Insufficient quantity available");
            }
            
            // Update material status to 'On Delivery' and reduce supplier's quantity
            $updateStmt = $con->prepare("
                UPDATE materials 
                SET delivery_status = 'On Delivery'
                WHERE id = ?
            
                
                UPDATE suppliers_materials
                SET quantity = quantity - ?
                WHERE material_id = ? AND supplier_id = (SELECT supplier_id FROM users WHERE id = ?)
            
                
                UPDATE materials
                SET quantity = quantity + ?
                WHERE id = ?
            ");
            
            if (!$updateStmt) {
                throw new Exception("Failed to prepare update statement: " . $con->error);
            }
            
            $updateStmt->bind_param('iiiii', 
                $backorder['material_id'],
                $backorder['quantity'],
                $backorder['material_id'],
                $userId,
                $backorder['quantity'],
                $backorder['material_id']
            );
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update material status and quantity: " . $updateStmt->error);
            }
            
            // Create notification for the requester
            $message = "Your " . ($isReorder ? 'reorder' : 'backorder') . " for " . 
                      htmlspecialchars($backorder['material_name']) . " has been approved and is now in delivery.";
            
            $notifQuery = "INSERT INTO notifications_procurement 
                          (user_id, notif_type, message, is_read, created_at) 
                          VALUES (?, ?, ?, 0, NOW())";
            $stmt = $con->prepare($notifQuery);
            $stmt->bind_param('iss', $backorder['requested_by'], $notifType, $message);
            
            if (!$stmt->execute()) {
                // Don't fail the whole operation if notification fails
                error_log("Failed to create notification: " . $con->error);
            }
        }
    }
    
    // Commit transaction
    $con->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Backorder status updated successfully',
        'status' => $status
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$con->close();
?>
