<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Start session and include config
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate project_id
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit();
}

try {
    // Check database connection
    if ($con->connect_error) {
        throw new Exception('Database connection failed: ' . $con->connect_error);
    }

    // First, check if the table exists
    $tableCheck = $con->query("SHOW TABLES LIKE 'project_timeline'");
    if ($tableCheck === false) {
        throw new Exception('Error checking table existence: ' . $con->error);
    }
    
    if ($tableCheck->num_rows === 0) {
        // Try with different case sensitivity
        $tables = $con->query("SHOW TABLES")->fetch_all();
        $matchingTables = [];
        foreach ($tables as $table) {
            if (strtolower($table[0]) === 'project_timeline') {
                $matchingTables[] = $table[0];
            }
        }
        
        if (empty($matchingTables)) {
            throw new Exception('The project_timeline table does not exist. Available tables: ' . 
                implode(', ', array_map(function($t) { return $t[0]; }, $tables)));
        } else {
            // Use the first matching table name (with correct case)
            $tableName = $matchingTables[0];
        }
    } else {
        $tableName = 'project_timeline';
    }

    // Get schedule items
    $sql = "SELECT * FROM `$tableName` 
            WHERE project_id = ? 
            ORDER BY start_date ASC, task_name ASC";
            
    error_log("Executing query: " . $sql . " with project_id: " . $project_id);
    
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param("i", $project_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }
    
    $schedule_items = [];
    while ($row = $result->fetch_assoc()) {
        $schedule_items[] = [
            'id' => $row['id'],
            'task_name' => htmlspecialchars($row['task_name'] ?? ''),
            'description' => htmlspecialchars($row['description'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'status' => $row['status'] ?? 'Not Started',
            'progress' => isset($row['progress']) ? intval($row['progress']) : 0
        ];
    }
    
    // Debug output
    error_log('Sending response: ' . print_r($schedule_items, true));
    
    echo json_encode([
        'success' => true,
        'data' => $schedule_items
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_schedule_items.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'debug' => [
            'project_id' => $project_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

$con->close();