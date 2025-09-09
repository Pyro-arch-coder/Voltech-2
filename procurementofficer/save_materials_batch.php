<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $materials_json = isset($_POST['materials']) ? $_POST['materials'] : '';
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    
    // Debug: Log the received data
    error_log("Received materials JSON: " . $materials_json);
    error_log("User ID: " . $user_id);
    
    // Decode JSON data
    $materials = json_decode($materials_json, true);
    
    if (empty($materials) || !is_array($materials)) {
        error_log("Failed to decode materials JSON or empty array");
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No materials to save or invalid data format']);
        exit();
    }
    
    error_log("Decoded materials count: " . count($materials));
    
    $success_count = 0;
    $errors = [];
    
    foreach ($materials as $material) {
        error_log("Processing material: " . json_encode($material));
        
        $material_name = isset($material['material_name']) ? trim($material['material_name']) : '';
        $category = isset($material['category']) ? trim($material['category']) : '';
        $quantity = isset($material['quantity']) ? intval($material['quantity']) : 0;
        $unit = isset($material['unit']) ? trim($material['unit']) : '';
        $status = isset($material['status']) ? trim($material['status']) : 'Available';
        $location = isset($material['location']) ? trim($material['location']) : '';
        $supplier_name = isset($material['supplier_name']) ? trim($material['supplier_name']) : '';
        $brand = isset($material['brand']) ? trim($material['brand']) : '';
        $specification = isset($material['specification']) ? trim($material['specification']) : ''; 
        $material_price = isset($material['material_price']) ? floatval($material['material_price']) : 0;
        $labor_other = isset($material['labor_other']) ? floatval($material['labor_other']) : 0;
        $amount = ($material_price + $labor_other) * $quantity;
        $total_amount = $amount;
        
        error_log("Processed values - Name: $material_name, Category: $category, Quantity: $quantity, Unit: $unit, Supplier: $supplier_name, Price: $material_price");
        
        // Validate required fields
        if (!$material_name || !$category || !$quantity || !$unit || !$supplier_name || !$material_price) {
            $errors[] = "Missing required fields for material: $material_name";
            error_log("Validation failed for material: $material_name");
            continue;
        }
        
        // Check if material with same name and supplier already exists
        $check_sql = "SELECT id, supplier_name FROM materials WHERE material_name = ? AND supplier_name = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("ss", $material_name, $supplier_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Material '$material_name' from supplier '$supplier_name' already exists. Cannot add duplicate materials from the same supplier.";
            error_log("Duplicate material found: $material_name from supplier $supplier_name");
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();
        
        // Always use today's date for purchase_date
        $purchase_date = date('Y-m-d');

        $sql = "INSERT INTO materials (material_name, category, quantity, unit, status, location, supplier_name, purchase_date, material_price, labor_other, total_amount, user_id, brand, specification, low_stock_threshold, max_stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 10, 100)";
        $stmt = $con->prepare($sql);
        $total_amount_int = intval($total_amount);
        $stmt->bind_param("ssisssssddisss", $material_name, $category, $quantity, $unit, $status, $location, $supplier_name, $purchase_date, $material_price, $labor_other, $total_amount_int, $user_id, $brand, $specification);
        
        if ($stmt->execute()) {
            $success_count++;
            error_log("Successfully saved material: $material_name");
        } else {
            $errors[] = "Failed to save material: $material_name - " . $con->error;
            error_log("Failed to save material: $material_name - " . $con->error);
        }
        $stmt->close();
    }
    
    $response = [
        'success' => $success_count > 0,
        'message' => $success_count > 0 ? "Successfully saved $success_count materials." : "Materials Already Exists.",
        'saved_count' => $success_count,
        'errors' => $errors
    ];
    
    // No batch notification needed as we're sending per-material notifications now
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Default response
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?> 