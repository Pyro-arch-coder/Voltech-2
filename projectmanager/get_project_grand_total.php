<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include_once "../config.php";

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : null;

try {
    if ($project_id) {
        // Calculate grand total from ALL materials in the project
        $query = "SELECT COALESCE(SUM((pem.material_price + COALESCE(m.labor_other, 0)) * pem.quantity), 0) as grand_total 
                  FROM project_estimating_materials pem
                  LEFT JOIN materials m ON pem.material_id = m.id
                  WHERE pem.project_id = ?";
        
        $stmt = $con->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            $grand_total = floatval($row['grand_total']);
            
            echo json_encode([
                'success' => true,
                'grand_total' => $grand_total
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error calculating grand total'
            ]);
        }
        
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