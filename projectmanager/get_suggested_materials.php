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
    // Get multiple recent FINISHED projects of the same category (check up to 10 projects)
    $stmt = $con->prepare("
        SELECT p.project_id 
        FROM projects p
        WHERE p.category = ? 
        AND p.status = 'Finished'  -- Only include completed projects
        ORDER BY p.created_at DESC 
        LIMIT 10  -- Get up to 10 recent projects to check
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
    
    // Check each project one by one until we find one with materials
    $materials = [];
    foreach ($projectIds as $projectId) {
        // Check if this project has materials
        $check_stmt = $con->prepare("
            SELECT COUNT(*) as material_count
            FROM project_add_materials
            WHERE project_id = ?
            AND material_id IS NOT NULL
            AND material_id != 0
        ");
        $check_stmt->bind_param("i", $projectId);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();
        
        // If this project has materials, get them and break
        if ($check_row['material_count'] > 0) {
            $stmt = $con->prepare("
                SELECT 
                    m.material_id,
                    m.material_name,
                    m.unit,
                    m.material_price,
                    m.quantity,
                    (m.material_price * m.quantity) as total_cost,
                    COUNT(*) as usage_count
                FROM project_add_materials m
                WHERE m.project_id = ?
                AND m.material_id IS NOT NULL
                AND m.material_id != 0
                GROUP BY m.material_id, m.material_name, m.unit, m.material_price, m.quantity, m.added_at
                ORDER BY usage_count DESC, m.added_at DESC
            ");
            $stmt->bind_param("i", $projectId);
            $stmt->execute();
            $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            break; // Found materials, stop checking other projects
        }
    }
    
    $response['suggestions'] = $materials;
} catch (Exception $e) {
    http_response_code(500);
    $response = ['error' => 'Database error: ' . $e->getMessage()];
}

echo json_encode($response);
?>
