<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Function to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response = array_merge($response, $data);
    echo json_encode($response);
    exit();
}

// Start session and check login
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    sendJsonResponse(false, 'Unauthorized access');
}

// Database connection
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    sendJsonResponse(false, 'Database connection failed');
}
$con->set_charset("utf8mb4");

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (!isset($_POST['material_id']) || !isset($_POST['quantity'])) {
            throw new Exception('Missing required fields');
        }

        $material_id = intval($_POST['material_id']);
        $quantity = intval($_POST['quantity']);
        $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

        if ($material_id <= 0 || $quantity <= 0) {
            throw new Exception('Invalid material ID or quantity');
        }

        // Start transaction
        $con->begin_transaction();

        try {
            // 1. Get material and supplier details
            $material_query = "SELECT m.*, s.supplier_name 
                             FROM materials m 
                             JOIN suppliers s ON m.supplier_name = s.supplier_name 
                             WHERE m.id = ?";
            $stmt = $con->prepare($material_query);
            if (!$stmt) {
                throw new Exception('Failed to prepare material query: ' . $con->error);
            }
            $stmt->bind_param("i", $material_id);
            $stmt->execute();
            $material_result = $stmt->get_result();

            if ($material_result->num_rows === 0) {
                throw new Exception('Material not found');
            }

            $material = $material_result->fetch_assoc();
            $supplier_name = $material['supplier_name'];

            // 2. Check if supplier has enough stock
            $check_stock = "SELECT sm.quantity 
                           FROM suppliers_materials sm 
                           JOIN suppliers s ON sm.supplier_id = s.id 
                           WHERE s.supplier_name = ? AND sm.material_name = ?";
            $stmt = $con->prepare($check_stock);
            $stmt->bind_param("ss", $supplier_name, $material['material_name']);
            $stmt->execute();
            $stock_result = $stmt->get_result();
            $stock_data = $stock_result->fetch_assoc();

            if (!$stock_data || $stock_data['quantity'] < $quantity) {
                throw new Exception('Insufficient stock at supplier');
            }

            // 3. Insert into back_orders first
            $backorder_insert = "INSERT INTO back_orders (material_id, quantity, reason, requested_by, created_at) 
                              VALUES (?, ?, 'Reorder', ?, NOW())";
            $stmt = $con->prepare($backorder_insert);
            $stmt->bind_param("iii", $material_id, $quantity, $user_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to create backorder record');
            }

            // 4. Update material quantity
            $update_material = "UPDATE materials SET quantity = quantity + ? WHERE id = ?";
            $stmt = $con->prepare($update_material);
            $stmt->bind_param("ii", $quantity, $material_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update material quantity');
            }

            // 5. Deduct from supplier's stock
            $update_supplier = "UPDATE suppliers_materials sm 
                              JOIN suppliers s ON sm.supplier_id = s.id 
                              SET sm.quantity = sm.quantity - ? 
                              WHERE s.supplier_name = ? AND sm.material_name = ?";
            $stmt = $con->prepare($update_supplier);
            $stmt->bind_param("iss", $quantity, $supplier_name, $material['material_name']);
            if (!$stmt->execute()) {
                throw new Exception('Failed to update supplier stock');
            }

            // 6. Record the transaction in order_expenses
            $expense_amount = $quantity * $material['material_price'];
            $expense_description = "Material Reorder: {$material['material_name']} (Qty: $quantity)";
            $expense_query = "INSERT INTO order_expenses 
                            (user_id, expense, expensedate, expensecategory, description) 
                            VALUES (?, ?, CURDATE(), 'Material', ?)";
            $stmt = $con->prepare($expense_query);
            $stmt->bind_param("ids", $user_id, $expense_amount, $expense_description);
            if (!$stmt->execute()) {
                throw new Exception('Failed to record expense');
            }

            // 7. Check if supplier needs restocking (simplified to just check quantity)
            $check_restock = "SELECT sm.quantity 
                             FROM suppliers_materials sm 
                             JOIN suppliers s ON sm.supplier_id = s.id 
                             WHERE s.supplier_name = ? AND sm.material_name = ?";
            $stmt = $con->prepare($check_restock);
            $stmt->bind_param("ss", $supplier_name, $material['material_name']);
            $stmt->execute();
            $restock_data = $stmt->get_result()->fetch_assoc();

            // Optional: Add a low stock threshold if needed
            $low_stock_threshold = 10; // Example threshold, adjust as needed
            if ($restock_data && $restock_data['quantity'] < $low_stock_threshold) {
                $message = "Low stock alert for {$material['material_name']} at $supplier_name. " . 
                          "Current quantity: {$restock_data['quantity']}";
                $notif_query = "INSERT INTO notifications_procurement 
                              (notif_type, message, is_read, created_at) 
                              VALUES ('Low Stock', ?, 0, NOW())";
                $stmt = $con->prepare($notif_query);
                $stmt->bind_param("s", $message);
                $stmt->execute();
            }

            // Commit transaction if all queries succeeded
            $con->commit();
            
            // Return success response
            sendJsonResponse(true, 'Reorder processed successfully', [
                'material_id' => $material_id,
                'material_name' => $material['material_name'],
                'quantity' => $quantity,
                'supplier' => $supplier_name
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            $con->rollback();
            throw $e;
        }

    } catch (Exception $e) {
        // Log the error
        error_log("Reorder Error: " . $e->getMessage());
        
        // Return error response
        sendJsonResponse(false, 'Failed to process reorder: ' . $e->getMessage());
    }
} else {
    // Not a POST request
    sendJsonResponse(false, 'Invalid request method');
}

// Close database connection
$con->close();
