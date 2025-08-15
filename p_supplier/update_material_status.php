<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 5) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$materialId = (int)$_POST['id'];
$action = $_POST['action'];
$userId = $_SESSION['user_id'];
$currentDate = date('Y-m-d H:i:s');

try {
    // Start transaction
    $con->begin_transaction();

    // Get current material status
    $stmt = $con->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->bind_param("i", $materialId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Material not found');
    }
    
    $material = $result->fetch_assoc();
    
    // Only allow approve action
    if ($action === 'approve') {
        // Get material details and check supplier's quantity
        $materialQuery = "SELECT m.material_name, m.quantity, sm.quantity as supplier_quantity, sm.id as supplier_material_id 
                         FROM materials m
                         LEFT JOIN suppliers_materials sm ON m.id = sm.material_id 
                         WHERE m.id = ? AND sm.supplier_id = (SELECT supplier_id FROM users WHERE id = ?)";
        $stmt = $con->prepare($materialQuery);
        $stmt->bind_param("ii", $materialId, $userId);
        $stmt->execute();
        $material = $stmt->get_result()->fetch_assoc();
        
        if (!$material) {
            throw new Exception("Material not found or you don't have permission to approve this material");
        }
        
        if ($material['supplier_quantity'] < $material['quantity']) {
            throw new Exception("Insufficient quantity available in your inventory");
        }
        
        // Start transaction for multiple operations
        $con->begin_transaction();
        
        try {
            // Update material status to 'On Delivery'
            $updateStmt = $con->prepare("
                UPDATE materials 
                SET delivery_status = 'Material On Delivery'
                WHERE id = ?
            
                
                UPDATE suppliers_materials
                SET quantity = quantity - ?
                WHERE id = ?
                
                
                INSERT INTO suppliers_orders_approved (user_id, material_name, quantity, type) 
                VALUES (?, ?, ?, 'material')
            ");
            
            if (!$updateStmt) {
                throw new Exception("Failed to prepare update statement: " . $con->error);
            }
            
            $updateStmt->bind_param('iiisi', 
                $materialId,
                $material['quantity'],
                $material['supplier_material_id'],
                $userId,
                $material['material_name'],
                $material['quantity']
            );
            
            if (!$updateStmt->execute()) {
                throw new Exception("Failed to update material status and quantity: " . $updateStmt->error);
            }
            
            // Commit the transaction if all operations succeed
            $con->commit();

        // Insert notification
        $notifMessage = "Your material request has been approved and is now on delivery";
        $notifStmt = $con->prepare("
            INSERT INTO notifications_procurement 
            (user_id, notif_type, message, created_at) 
            VALUES (?, 'material_approved', ?, ?)
        ");
        $notifStmt->bind_param("iss", $material['user_id'], $notifMessage, $currentDate);
        $notifStmt->execute();

        $newStatus = 'On Delivery';
    } else {
        throw new Exception('Invalid action');
    }
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update material status');
    }
    
    // If everything is successful, commit the transaction
    $con->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Material request approved successfully',
        'material_id' => $materialId,
        'new_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($con) && $con) {
        $con->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>