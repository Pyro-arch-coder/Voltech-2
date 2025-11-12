<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has supplier access
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get the user email from the session
        $user_email = isset($_SESSION['email']) ? $_SESSION['email'] : null;
        
        if (!$user_email) {
            throw new Exception('User email not found in session');
        }
        
        // Get supplier ID using the email
        $supplier_query = "SELECT id FROM suppliers WHERE email = ?";
        $supplier_stmt = $con->prepare($supplier_query);
        $supplier_stmt->bind_param("s", $user_email);
        $supplier_stmt->execute();
        $supplier_result = $supplier_stmt->get_result();
        
        if ($supplier_result->num_rows === 0) {
            throw new Exception('Supplier record not found for email: ' . $user_email);
        }
        
        $supplier_row = $supplier_result->fetch_assoc();
        $supplier_id = $supplier_row['id'];
        $supplier_stmt->close();

        // Sanitize input data
        $material_name = isset($_POST['material_name']) ? trim($_POST['material_name']) : '';
        $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
        $specification = isset($_POST['specification']) ? trim($_POST['specification']) : '';
        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $unit = isset($_POST['unit']) ? trim($_POST['unit']) : '';
        $material_price = isset($_POST['material_price']) ? floatval($_POST['material_price']) : 0;
        $low_stock_threshold = isset($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10;
        $lead_time = isset($_POST['lead_time']) ? intval($_POST['lead_time']) : 0;
        $labor_other = isset($_POST['labor_other']) ? floatval($_POST['labor_other']) : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : 'Available';

        // Validation
        if (empty($material_name) || strlen($material_name) < 2) {
            throw new Exception('Material name must be at least 2 characters long.');
        }
        
        if (strlen($material_name) > 100) {
            throw new Exception('Material name cannot exceed 100 characters.');
        }
        
        if ($quantity < 0) {
            throw new Exception('Quantity cannot be negative.');
        }
        
        if (empty($unit)) {
            throw new Exception('Please select a unit.');
        }
        
        if ($material_price <= 0) {
            throw new Exception('Material price must be greater than 0.');
        }
        
        if ($low_stock_threshold < 0) {
            throw new Exception('Low stock threshold cannot be negative.');
        }
        
        if ($lead_time < 0) {
            throw new Exception('Lead time cannot be negative.');
        }
        
        if ($labor_other < 0) {
            throw new Exception('Labor/Other cost cannot be negative.');
        }
        
        if (empty($category)) {
            throw new Exception('Please select a category.');
        }

        // Prepare and execute SQL query
        $sql = "INSERT INTO suppliers_materials (
            supplier_id, 
            material_name, 
            brand, 
            specification, 
            category, 
            quantity, 
            unit, 
            material_price, 
            low_stock_threshold, 
            lead_time, 
            labor_other, 
            status, 
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )";

        $stmt = $con->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database preparation failed: ' . $con->error);
        }

        $stmt->bind_param(
            "issssissiiis",
            $supplier_id,
            $material_name,
            $brand,
            $specification,
            $category,
            $quantity,
            $unit,
            $material_price,
            $low_stock_threshold,
            $lead_time,
            $labor_other,
            $status
        );

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Material added successfully';
        } else {
            throw new Exception('Failed to add material: ' . $stmt->error);
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
        $con->close();
        
        // Set the content type to JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
?>
