<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header('Content-Type: application/json');
    echo json_encode(['exists' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['material_name'])) {
    $material_name = trim($_GET['material_name']);
    $supplier_name = isset($_GET['supplier_name']) ? trim($_GET['supplier_name']) : '';
    
    if (empty($material_name)) {
        header('Content-Type: application/json');
        echo json_encode(['exists' => false, 'error' => 'Material name is required']);
        exit();
    }
    
    // Check if material exists with same name and supplier
    if (!empty($supplier_name)) {
        $check_sql = "SELECT id, approval, supplier_name FROM materials WHERE material_name = ? AND supplier_name = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("ss", $material_name, $supplier_name);
    } else {
        // If no supplier specified, check by material name only (for backward compatibility)
        $check_sql = "SELECT id, approval, supplier_name FROM materials WHERE material_name = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("s", $material_name);
    }
    
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $existing_material = $check_result->fetch_assoc();
        $check_stmt->close();
        $con->close();
        
        header('Content-Type: application/json');
        echo json_encode([
            'exists' => true,
            'approval' => $existing_material['approval'],
            'supplier_name' => $existing_material['supplier_name']
        ]);
        exit();
    }
    
    $check_stmt->close();
    $con->close();
    
    header('Content-Type: application/json');
    echo json_encode(['exists' => false]);
    exit();
}

// Default response
header('Content-Type: application/json');
echo json_encode(['exists' => false, 'error' => 'Invalid request']);
?> 