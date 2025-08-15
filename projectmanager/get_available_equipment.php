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
    // Get category filter if set
    $category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
    
    // Build the base query
    $query = "SELECT e.*, c.category_name 
              FROM equipment e
              LEFT JOIN electrical_equipment_categories c ON e.equipment_categories = c.id
              WHERE LOWER(COALESCE(e.status, '')) = 'available'
              AND LOWER(COALESCE(e.status, '')) NOT IN ('damaged', 'damage')";
              
    // Add category filter if specified
    if ($category_filter > 0) {
        $query .= " AND e.equipment_categories = " . $category_filter;
    }
    
    $query .= " ORDER BY e.equipment_name ASC";
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
            'status' => $row['status'],
            'category_name' => $row['category_name'] ?? 'Uncategorized',
            'category_id' => $row['equipment_categories'] ?? 0
        ];
    }
    
    echo json_encode($equipment);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

mysqli_close($con);
?>
