<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has supplier access
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 5) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set header to JSON response
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get the material ID
        if (!isset($_POST['material_id']) || empty($_POST['material_id'])) {
            throw new Exception('Material ID is required');
        }
        $material_id = filter_var($_POST['material_id'], FILTER_VALIDATE_INT);
        
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

        // Check if material with the same name already exists for this supplier (excluding current material)
        $check_sql = "SELECT id FROM suppliers_materials WHERE supplier_id = ? AND LOWER(material_name) = LOWER(?) AND id != ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("isi", $_SESSION['user_id'], $material_name, $material_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            throw new Exception('A material with this name already exists in your inventory.');
        }
        $check_stmt->close();

        // Prepare and execute SQL query
        $sql = "UPDATE suppliers_materials SET 
                material_name = ?,
                brand = ?,
                specification = ?,
                category = ?,
                unit = ?,
                status = ?,
                material_price = ?,
                quantity = ?,
                low_stock_threshold = ?,
                lead_time = ?,
                labor_other = ?
                WHERE id = ?";

        $stmt = $con->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database preparation failed: ' . $con->error);
        }

        $stmt->bind_param(
            "ssssssdiiisi",
            $material_name,
            $brand,
            $specification,
            $category,
            $unit,
            $status,
            $material_price,
            $quantity,
            $low_stock_threshold,
            $lead_time,
            $labor_other,
            $material_id
        );

        if ($stmt->execute()) {
            if (isset($stmt)) {
                $stmt->close();
            }
            $con->close();
            header("Location: supplier_materials.php?success=edit");
            exit();
        } else {
            throw new Exception('Failed to update material: ' . $stmt->error);
        }

    } catch (Exception $e) {
        if (isset($stmt)) {
            $stmt->close();
        }
        $con->close();
        $error = urlencode($e->getMessage());
        header("Location: supplier_materials.php?error=$error");
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}
?>
