<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client (user_level = 6)
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if project_id is provided
if (!isset($_POST['project_id']) || !is_numeric($_POST['project_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

$project_id = intval($_POST['project_id']);
$response = ['success' => false, 'is_overdue' => false];

try {
    // Get project details
    $stmt = $con->prepare("SELECT project_id, deadline, status FROM projects WHERE project_id = ? AND (archived IS NULL OR archived = 0)");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($project = $result->fetch_assoc()) {
        // Set timezone to Philippines
        date_default_timezone_set('Asia/Manila');
        $today = date('Y-m-d');
        
        // Check if project is not already marked as Overdue, Finished, Completed, or Override Finished, and deadline has passed
        if (!in_array($project['status'], ['Overdue', 'Finished', 'Completed', 'Override Finished']) && $today > $project['deadline']) {
            // Update status to Overdue
            $update = $con->prepare("UPDATE projects SET status = 'Overdue' WHERE project_id = ?");
            $update->bind_param("i", $project_id);
            
            if ($update->execute()) {
                $response['success'] = true;
                $response['is_overdue'] = true;
                $response['message'] = 'Project marked as overdue';
            } else {
                throw new Exception('Failed to update project status');
            }
        } else {
            $response['success'] = true;
            $response['is_overdue'] = ($project['status'] === 'Overdue');
            $response['message'] = 'Status checked successfully';
        }
    } else {
        throw new Exception('Project not found');
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>
