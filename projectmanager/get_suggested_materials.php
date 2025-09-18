<?php
require_once '../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if category is provided
if (!isset($_GET['category']) || empty(trim($_GET['category']))) {
    echo json_encode(['error' => 'Category is required']);
    exit;
}

$category = trim($_GET['category']);
$response = [];

try {
    // Get the most recent FINISHED project of the same category
    $stmt = $con->prepare("
        SELECT p.project_id 
        FROM projects p
        WHERE p.category = ? 
        AND p.status = 'Finished'  -- Only include completed projects
        ORDER BY p.created_at DESC 
        LIMIT 1  -- Get only the latest project
    ");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $projectIds = [];
    while ($row = $result->fetch_assoc()) {
        $projectIds[] = $row['project_id'];
    }
    
    if (empty($projectIds)) {
        echo json_encode(['suggestions' => []]);
        exit;
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($projectIds) - 1) . '?';
    
    // Get materials from the selected projects
    $stmt = $con->prepare("
        SELECT 
            m.material_id,
            m.material_name,
            m.unit,
            m.material_price,
            m.quantity,
            m.additional_cost,
            (m.material_price * m.quantity + IFNULL(m.additional_cost, 0)) as total_cost,
            COUNT(*) as usage_count
        FROM project_add_materials m
        WHERE m.project_id IN ($placeholders)
        AND m.material_id IS NOT NULL
        AND m.material_id != 0
        GROUP BY m.material_id, m.material_name, m.unit, m.material_price, m.quantity, m.additional_cost, m.added_at
        ORDER BY usage_count DESC, m.added_at DESC
    ");
    
    // Bind parameters dynamically
    $types = str_repeat('i', count($projectIds));
    $stmt->bind_param($types, ...$projectIds);
    $stmt->execute();
    $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $response['suggestions'] = $materials;
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?>
