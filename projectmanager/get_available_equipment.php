<?php
session_start();
require_once "../config.php";

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

$response = [];

try {
    // Get all equipment that's available, not damaged, and has delivery status 'Delivered'
    $query = "SELECT * FROM equipment 
              WHERE LOWER(COALESCE(status, '')) = 'available'
              AND LOWER(COALESCE(status, '')) NOT IN ('damaged', 'damage')
              AND delivery_status = 'Delivered' 
              ORDER BY equipment_name ASC";
    $result = mysqli_query($con, $query);
    
    if (!$result) {
        throw new Exception("Database error: " . mysqli_error($con));
    }
    
    $equipment = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $equipment[] = [
            'id' => $row['id'],
            'equipment_name' => $row['equipment_name'],
            'equipment_price' => $row['equipment_price'],
            'depreciation' => $row['depreciation'],
            'status' => $row['status']
        ];
    }
    
    echo json_encode($equipment);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

mysqli_close($con);
?>
