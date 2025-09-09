<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();
require_once '../config.php';

function json_error($msg) {
    echo json_encode(['success'=>false, 'message'=>$msg]);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 5) {
    json_error('Unauthorized access');
}
if (!isset($_POST['backorder_id']) || !isset($_POST['status'])) {
    json_error('Missing required parameters');
}

$backorderId = $con->real_escape_string($_POST['backorder_id']);
$status = $con->real_escape_string($_POST['status']);
$userId = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? '';

if (!in_array($status, ['Approved', 'Rejected', 'Pending'])) {
    json_error('Invalid status');
}

// Get supplier_id from suppliers table using user's email
$supplier_id = null;
$supplier_name = '';
$supplier_q = $con->prepare("SELECT id, supplier_name FROM suppliers WHERE email = ?");
$supplier_q->bind_param('s', $user_email);
$supplier_q->execute();
$supplier_res = $supplier_q->get_result();
if ($supplier_row = $supplier_res->fetch_assoc()) {
    $supplier_id = $supplier_row['id'];
    $supplier_name = $supplier_row['supplier_name'];
} else {
    json_error('Supplier not found for user');
}
$supplier_q->close();

try {
    $con->begin_transaction();

    // Update the backorder status
    $updateQuery = "UPDATE back_orders SET status = ? WHERE id = ?";
    $stmt = $con->prepare($updateQuery);
    $stmt->bind_param('si', $status, $backorderId);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update backorder status");
    }

    if ($status === 'Approved') {
        // Get the backorder details
        $backorderQuery = "SELECT 
                                bo.material_id, 
                                bo.reason, 
                                bo.requested_by, 
                                bo.quantity
                            FROM back_orders bo 
                            WHERE bo.id = ?";
        $stmt = $con->prepare($backorderQuery);
        $stmt->bind_param('i', $backorderId);
        $stmt->execute();
        $backorder = $stmt->get_result()->fetch_assoc();
        if (!$backorder) throw new Exception("Backorder not found!");

        // Get material details from materials table using material_id
        $materialQuery = "SELECT material_name, brand, specification FROM materials WHERE id = ?";
        $stmt = $con->prepare($materialQuery);
        $stmt->bind_param('i', $backorder['material_id']);
        $stmt->execute();
        $material = $stmt->get_result()->fetch_assoc();
        if (!$material) throw new Exception("Material not found!");

        // Insert into suppliers_orders_approved using the logged-in user's ID
        $insertQuery = "INSERT INTO suppliers_orders_approved (user_id, material_name, quantity, type) 
                       VALUES (?, ?, ?, ?)";
        $stmt = $con->prepare($insertQuery);
        $orderType = ($backorder['reason'] === 'Reorder') ? 'reorder' : 'backorder';
        $mat_name = $material['material_name'];
        $logged_in_user_id = $_SESSION['user_id'];
        $stmt->bind_param('isis', $logged_in_user_id, $mat_name, $backorder['quantity'], $orderType);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert into approved orders: " . $con->error);
        }

        // Assign material values to variables for bind_param
        $mat_brand = $material['brand'];
        $mat_spec = $material['specification'];

        // Now use ONLY the values from materials for the supplier lookup!
        $supplierMaterialQuery = "SELECT id, quantity FROM suppliers_materials 
                                 WHERE supplier_id = ? AND material_name = ? AND brand = ? AND specification = ? LIMIT 1";
        $stmt = $con->prepare($supplierMaterialQuery);
        $stmt->bind_param('isss', $supplier_id, $mat_name, $mat_brand, $mat_spec);
        $stmt->execute();
        $supplierMaterial = $stmt->get_result()->fetch_assoc();

        if (!$supplierMaterial || $supplierMaterial['quantity'] < $backorder['quantity']) {
            throw new Exception("Insufficient quantity available (supplier_id: $supplier_id, material_name: {$mat_name})");
        }

        // Decide material delivery status based on backorder/reorder
        $delivery_status = ($backorder['reason'] === 'Reorder') ? 'Reorder Delivery' : 'Backorder Delivery';

        // Update material delivery status
        $updateStmt = $con->prepare("
            UPDATE materials 
            SET delivery_status = ?
            WHERE material_name = ? AND brand = ? AND specification = ?
        ");
        $updateStmt->bind_param('ssss', $delivery_status, $mat_name, $mat_brand, $mat_spec);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update material delivery status: " . $updateStmt->error);
        }
        $updateStmt->close();

        // Only reduce supplier's quantity (no increment on materials)
        $updateStmt = $con->prepare("
            UPDATE suppliers_materials
            SET quantity = quantity - ?
            WHERE id = ?
        ");
        $updateStmt->bind_param('ii', $backorder['quantity'], $supplierMaterial['id']);
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update supplier's material quantity: " . $updateStmt->error);
        }
        $updateStmt->close();

        // Create notification for the requester
        $message = "Your " . ($orderType) . " for " . 
                  htmlspecialchars($mat_name) . " has been approved and is now in delivery.";
        $notifQuery = "INSERT INTO notifications_procurement 
                      (user_id, notif_type, message, is_read, created_at) 
                      VALUES (?, ?, ?, 0, NOW())";
        $notif_type = $orderType . '_approved';
        $stmt = $con->prepare($notifQuery);
        $stmt->bind_param('iss', $backorder['requested_by'], $notif_type, $message);
        $stmt->execute();
    }

    $con->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Backorder status updated successfully',
        'status' => $status
    ]);
} catch (Exception $e) {
    $con->rollback();
    json_error('Error: ' . $e->getMessage());
}
$con->close();
?>