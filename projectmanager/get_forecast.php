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

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Category is required']);
    exit;
}

try {
    // Get all finished, non-archived projects that have expenses in the specified category
    $query = "SELECT 
                p.project_id,
                p.project,
                p.size as project_size,
                p.category,
                SUM(e.expense) as total_expense
              FROM projects p
              INNER JOIN expenses e ON p.project_id = e.project_id
              WHERE e.expensecategory = 'Project' 
              AND e.expense > 0
              AND p.size > 0
              AND p.status = 'Finished'
              AND (p.archived IS NULL OR p.archived = 0)
              AND p.category = ?
              GROUP BY p.project_id, p.project, p.size, p.category";
    
    $stmt = mysqli_prepare($con, $query);
    if (!$stmt) {
        throw new Exception(mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $category);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        throw new Exception(mysqli_error($con));
    }
    
    // If no projects found for this category, try to find similar categories
    if (mysqli_num_rows($result) === 0) {
        // Try to find similar categories (e.g., if 'House' not found, try 'House Electrical')
        $similarCategoryQuery = "SELECT 
                                p.project_id,
                                p.project,
                                p.size as project_size,
                                p.category,
                                SUM(e.expense) as total_expense
                              FROM projects p
                              INNER JOIN expenses e ON p.project_id = e.project_id
                              WHERE e.expensecategory = 'Project' 
                              AND e.expense > 0
                              AND p.size > 0
                              AND p.status = 'Finished'
                              AND (p.archived IS NULL OR p.archived = 0)
                              AND p.category LIKE ?
                              GROUP BY p.project_id, p.project, p.size, p.category";
        
        $similarStmt = mysqli_prepare($con, $similarCategoryQuery);
        if ($similarStmt) {
            $likeCategory = "%$category%";
            mysqli_stmt_bind_param($similarStmt, "s", $likeCategory);
            mysqli_stmt_execute($similarStmt);
            $result = mysqli_stmt_get_result($similarStmt);
        }
    }
    
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
    
    // Calculate cost per square meter for the forecast
    $cost_per_sqm = ($average_size > 0) ? ($average_cost / $average_size) : 0;
    $forecasted_cost = $cost_per_sqm * $size;
    
    echo json_encode([
        'success' => true,
        'average_cost' => round($average_cost, 2),
        'average_size' => round($average_size, 2),
        'cost_per_sqm' => round($cost_per_sqm, 2),
        'forecasted_cost' => round($forecasted_cost, 2),
        'sample_size' => $project_count,
        'projects_used' => $projects_used,
        'category' => $category
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error calculating forecast: ' . $e->getMessage()
    ]);
}
