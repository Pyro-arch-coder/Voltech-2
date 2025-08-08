<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 4) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_materials') {
    // Get all unique material names from suppliers_materials table
    $sql = "SELECT DISTINCT material_name FROM suppliers_materials ORDER BY material_name";
    $result = $con->query($sql);
    
    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row['material_name'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($materials);
    exit();
}

if ($action === 'compare_suppliers') {
    $material_name = isset($_GET['material_name']) ? trim($_GET['material_name']) : '';
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
    
    if (!$material_name) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Material name is required']);
        exit();
    }
    
    // Get supplier pricing data for the material
    $sql = "SELECT 
                s.supplier_name,
                sm.material_price,
                sm.lead_time,
                sm.unit,
                sm.brand,
                sm.specification,
                sm.category,
                sm.quantity,
                sm.labor_other,
                (sm.material_price * ?) as total_cost
            FROM suppliers_materials sm
            JOIN suppliers s ON sm.supplier_id = s.id
            WHERE sm.material_name = ?
            ORDER BY sm.material_price ASC";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("is", $quantity, $material_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suppliers = [];
    $rank = 1;
    $cheapest = null;
    $fastest = null;
    $best_value = null;
    $min_price = PHP_FLOAT_MAX;
    $min_lead_time = PHP_INT_MAX;
    $best_value_score = 0;
    
    while ($row = $result->fetch_assoc()) {
        $supplier_data = [
            'rank' => $rank,
            'supplier_name' => $row['supplier_name'],
            'material_price' => floatval($row['material_price']),
            'lead_time' => intval($row['lead_time']),
            'category' => $row['category'],
            'unit' => $row['unit'], // Add unit field to the response
            'quantity' => intval($row['quantity']), // Add quantity field to the response
            'labor_other' => isset($row['labor_other']) ? floatval($row['labor_other']) : 0,
            'total_cost' => floatval($row['total_cost']),
            'brand' => $row['brand'],                
            'specification' => $row['specification'],
            'best_deal' => ''
        ];
        
        // Track cheapest
        if ($row['material_price'] < $min_price) {
            $min_price = $row['material_price'];
            $cheapest = $row['supplier_name'];
        }
        
        // Track fastest delivery
        if ($row['lead_time'] < $min_lead_time) {
            $min_lead_time = $row['lead_time'];
            $fastest = $row['supplier_name'];
        }
        
        // Calculate best value (price + lead time factor)
        $value_score = $row['material_price'] + ($row['lead_time'] * 0.1); // Lead time factor
        if ($best_value === null || $value_score < $best_value_score) {
            $best_value_score = $value_score;
            $best_value = $row['supplier_name'];
        }
        
        $suppliers[] = $supplier_data;
        $rank++;
    }
    
    // Mark best deals
    foreach ($suppliers as &$supplier) {
        if ($supplier['supplier_name'] === $cheapest) {
            $supplier['best_deal'] = 'Cheapest';
        } elseif ($supplier['supplier_name'] === $fastest) {
            $supplier['best_deal'] = 'Fastest';
        } elseif ($supplier['supplier_name'] === $best_value) {
            $supplier['best_deal'] = 'Best Value';
        }
    }
    
    $response = [
        'suppliers' => $suppliers,
        'summary' => [
            'cheapest' => $cheapest,
            'fastest' => $fastest,
            'best_value' => $best_value
        ],
        'recommendation' => $best_value ? "We recommend $best_value for the best overall value considering both price and delivery time." : "No suppliers found for this material."
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Default response
header('Content-Type: application/json');
echo json_encode(['error' => 'Invalid action']);
?> 