<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if required parameters are provided
if (!isset($_GET['size']) || !is_numeric($_GET['size']) || !isset($_GET['category'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$size = floatval($_GET['size']);
$category = trim($_GET['category']);

if ($size <= 0) {
    echo json_encode(['success' => false, 'message' => 'Size must be greater than 0']);
    exit;
}

try {
    // Get all projects that have expenses
    $query = "SELECT 
                p.project_id,
                p.project,
                p.size as project_size,
                SUM(e.expense) as total_expense
              FROM projects p
              INNER JOIN expenses e ON p.project_id = e.project_id
              WHERE e.expensecategory = 'Project' 
              AND e.expense > 0
              AND p.size > 0
              GROUP BY p.project_id, p.project, p.size";
    
    $result = mysqli_query($con, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($con));
    }
    
    $project_costs = [];
    $project_sizes = [];
    $projects_used = [];
    $project_count = 0;
    
    // Collect project costs and sizes
    while ($row = mysqli_fetch_assoc($result)) {
        $project_expense = floatval($row['total_expense']);
        $project_size = floatval($row['project_size']);
        
        if ($project_size > 0 && $project_expense > 0) {
            $project_costs[] = $project_expense;
            $project_sizes[] = $project_size;
            $project_count++;
            
            $projects_used[] = [
                'name' => $row['project'],
                'cost' => $project_expense,
                'size' => $project_size,
                'cost_per_sqm' => $project_expense / $project_size
            ];
        }
    }
    
    // Calculate averages
    $average_cost = !empty($project_costs) ? array_sum($project_costs) / count($project_costs) : 0;
    $average_size = !empty($project_sizes) ? array_sum($project_sizes) / count($project_sizes) : 0;
    
    echo json_encode([
        'success' => true,
        'average_cost' => round($average_cost, 2),
        'average_size' => round($average_size, 2),
        'sample_size' => $project_count,
        'projects_used' => $projects_used
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating forecast: ' . $e->getMessage()
    ]);
}
