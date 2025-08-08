<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
include_once "../config.php";
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $con->connect_error]);
    exit();
}

try {
    // Fetch materials for dropdown - only show delivered materials
    $query = "SELECT id, material_name, brand, specification, unit, material_price, labor_other, supplier_name 
              FROM materials 
              WHERE status = 'Available' AND delivery_status = 'delivered' 
              ORDER BY material_name ASC";
    $result = $con->query($query);
    
    if (!$result) {
        throw new Exception('Failed to fetch materials: ' . $con->error);
    }
    
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = [
            'id' => $row['id'],
            'material_name' => $row['material_name'],
            'brand' => $row['brand'],
            'specification' => $row['specification'],
            'unit' => $row['unit'],
            'material_price' => floatval($row['material_price']),
            'labor_other' => floatval($row['labor_other']),
            'supplier_name' => $row['supplier_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'count' => count($materials)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$con->close();
?> 