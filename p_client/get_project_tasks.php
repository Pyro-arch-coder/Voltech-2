<?php
session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

$response = ['success' => false, 'message' => '', 'tasks' => []];

if (isset($_GET['project_id']) && is_numeric($_GET['project_id'])) {
    $project_id = (int)$_GET['project_id'];
    
    try {
        // Fetch tasks from project_timeline table
        $query = "SELECT id, task_name, progress, status, start_date, end_date, description 
                  FROM project_timeline 
                  WHERE project_id = ? 
                  ORDER BY start_date ASC, id ASC";
        
        $stmt = $con->prepare($query);
        if (!$stmt) {
            throw new Exception('Failed to prepare query: ' . $con->error);
        }
        
        $stmt->bind_param("i", $project_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to execute query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $tasks = [];
        
        while ($row = $result->fetch_assoc()) {
            // Ensure progress is numeric
            $row['progress'] = is_numeric($row['progress']) ? (int)$row['progress'] : 0;
            
            // Ensure status has a default value
            $row['status'] = $row['status'] ?: 'Not Started';
            
            $tasks[] = $row;
        }
        
        $stmt->close();
        
        $response['success'] = true;
        $response['tasks'] = $tasks;
        $response['message'] = 'Tasks fetched successfully';
        
    } catch (Exception $e) {
        $response['message'] = 'Error fetching tasks: ' . $e->getMessage();
        error_log('Error in get_project_tasks.php: ' . $e->getMessage());
    }
} else {
    $response['message'] = 'Invalid project ID';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
