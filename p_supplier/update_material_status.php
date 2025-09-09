<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

// Only handle AJAX request logic here, no HTML!
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 5) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}
if (!isset($_POST['id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$materialId = (int)$_POST['id'];
$action = $_POST['action'];
$userId = $_SESSION['user_id'];
$currentDate = date('Y-m-d H:i:s');

try {
    $con->begin_transaction();

    // 1. Get material details (including who requested it)
    $stmt = $con->prepare("SELECT m.*, u.id as requester_id FROM materials m LEFT JOIN users u ON m.user_id = u.id WHERE m.id = ?");
    $stmt->bind_param("i", $materialId);
    $stmt->execute();
    $material = $stmt->get_result()->fetch_assoc();
    if (!$material) throw new Exception('Material not found.');

    // 2. Get supplier ID from session user (assuming this user is a supplier)
    $supplierEmail = $_SESSION['email'] ?? '';
    $supplier_q = $con->prepare("SELECT id FROM suppliers WHERE email = ?");
    $supplier_q->bind_param('s', $supplierEmail);
    $supplier_q->execute();
    $supplier_row = $supplier_q->get_result()->fetch_assoc();
    if (!$supplier_row) throw new Exception('Supplier not found for user.');
    $supplier_id = $supplier_row['id'];

    // 3. Get supplier's material stock (match by supplier_id and material_name)
    $supplierMaterialQuery = "SELECT id, quantity FROM suppliers_materials WHERE supplier_id = ? AND material_name = ? LIMIT 1";
    $stmt = $con->prepare($supplierMaterialQuery);
    $stmt->bind_param('is', $supplier_id, $material['material_name']);
    $stmt->execute();
    $supplierMaterial = $stmt->get_result()->fetch_assoc();
    if (!$supplierMaterial) throw new Exception("No stock for this material in your inventory.");
    if ($supplierMaterial['quantity'] < $material['quantity']) throw new Exception("Insufficient quantity available in your inventory.");

    if ($action === 'approve') {
        // 4. Update material status to 'On Delivery'
        $updateStmt = $con->prepare("UPDATE materials SET delivery_status = 'Material On Delivery' WHERE id = ?");
        $updateStmt->bind_param('i', $materialId);
        if (!$updateStmt->execute()) throw new Exception("Failed to update material status: " . $updateStmt->error);
        $updateStmt->close();

        // 5. Decrement supplier stock
        $updateStmt = $con->prepare("UPDATE suppliers_materials SET quantity = quantity - ? WHERE id = ?");
        $updateStmt->bind_param('ii', $material['quantity'], $supplierMaterial['id']);
        if (!$updateStmt->execute()) throw new Exception("Failed to update supplier's material quantity: " . $updateStmt->error);
        $updateStmt->close();

        // 6. Insert into suppliers_orders_approved using the logged-in user's ID
        $insertStmt = $con->prepare("INSERT INTO suppliers_orders_approved (user_id, material_name, quantity, type) VALUES (?, ?, ?, 'material')");
        $insertStmt->bind_param('isi', $userId, $material['material_name'], $material['quantity']);
        if (!$insertStmt->execute()) throw new Exception("Failed to insert into approved orders: " . $insertStmt->error);
        $insertStmt->close();

        // 7. Insert notification for requester
        $notifMessage = "Your material request for " . htmlspecialchars($material['material_name']) . " has been approved and is now on delivery.";
        $notifStmt = $con->prepare("INSERT INTO notifications_procurement (user_id, notif_type, message, created_at) VALUES (?, 'material_approved', ?, ?)");
        $notifStmt->bind_param("iss", $material['requester_id'], $notifMessage, $currentDate);
        $notifStmt->execute();
        $notifStmt->close();

        $newStatus = 'On Delivery';
    } else {
        throw new Exception('Invalid action');
    }

    $con->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Material request approved successfully',
        'material_id' => $materialId,
        'new_status' => $newStatus ?? 'N/A'
    ]);

} catch (Exception $e) {
    if (isset($con) && $con) $con->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>