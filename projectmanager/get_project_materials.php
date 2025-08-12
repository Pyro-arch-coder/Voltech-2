<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $con->connect_error]);
    exit();
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

try {
    if ($project_id) {
        // Fetch from database
        $query = "SELECT pem.*, m.labor_other, m.supplier_name FROM project_estimating_materials pem 
                  LEFT JOIN materials m ON pem.material_id = m.id 
                  WHERE pem.project_id = ? 
                  ORDER BY pem.added_at ASC";
        $stmt = $con->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $materials = [];
        $total = 0;
        
        while ($row = $result->fetch_assoc()) {
            $material_total = (floatval($row['material_price']) + floatval($row['labor_other'] ?? 0)) * intval($row['quantity']);
            $material = [
                'id' => $row['id'],
                'name' => $row['material_name'],
                'unit' => $row['unit'],
                'material_price' => floatval($row['material_price']),
                'labor_other' => floatval($row['labor_other'] ?? 0),
                'quantity' => intval($row['quantity']),
                'supplier' => $row['supplier_name'] ?? 'N/A',
                'total' => $material_total
            ];
            $materials[] = $material;
            $total += $material_total;
        }
        
        echo json_encode([
            'success' => true,
            'materials' => $materials,
            'total' => $total
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Project ID is required'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$con->close();
?> 