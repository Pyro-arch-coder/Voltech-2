<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['project_id']) || !isset($data['materials']) || !is_array($data['materials'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$project_id = intval($data['project_id']);
$materials = $data['materials'];

if ($project_id <= 0 || empty($materials)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project ID or empty materials']);
    exit;
}

try {
    // Start transaction
    mysqli_begin_transaction($con);
    
    // Prepare the insert statement
    $stmt = mysqli_prepare($con, "
        INSERT INTO project_estimating_materials 
        (project_id, material_id, material_name, unit, material_price, quantity, added_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . mysqli_error($con));
    }
    
    // Bind parameters
    mysqli_stmt_bind_param($stmt, 'iissdi', 
        $project_id_param,
        $material_id,
        $material_name,
        $unit,
        $material_price,
        $quantity
    );
    
    $project_id_param = $project_id;
    $inserted_count = 0;
    
    // Insert each material
    foreach ($materials as $material) {
        $material_id = intval($material['material_id'] ?? 0);
        $material_name = $material['material_name'];
        $unit = $material['unit'] ?? 'pcs';
        $material_price = floatval($material['material_price']);
        $quantity = floatval($material['quantity']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to insert material: ' . mysqli_stmt_error($stmt));
        }
        
        $inserted_count++;
    }
    
    // Commit transaction
    mysqli_commit($con);
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'Successfully added ' . $inserted_count . ' materials to project',
        'redirect' => 'projects.php?id=' . $project_id . '&tab=materials'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($con)) {
        mysqli_rollback($con);
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving materials: ' . $e->getMessage()
    ]);
}
?>
