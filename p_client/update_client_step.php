<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$client_id = $_SESSION['user_id'];
$new_step = isset($_POST['new_step']) ? intval($_POST['new_step']) : 1;

// Validate step number (1-6)
if ($new_step < 1 || $new_step > 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid step number']);
    exit();
}

try {
    // Get the client's email from users table
    $email_query = "SELECT email FROM users WHERE id = ?";
    $stmt = $con->prepare($email_query);
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $email_result = $stmt->get_result();
    
    if ($email_result->num_rows === 0) {
        throw new Exception('Client email not found');
    }
    $client_email = $email_result->fetch_assoc()['email'];
    
    // Get project_id from request
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
    if ($project_id <= 0) {
        // If no project_id provided, get the latest project for the client
        $project_query = "SELECT project_id FROM projects WHERE client_email = ? ORDER BY project_id DESC LIMIT 1";
        $stmt = $con->prepare($project_query);
        $stmt->bind_param('s', $client_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('No projects found for this client');
        }
        $project = $result->fetch_assoc();
        $project_id = $project['project_id'];
    } else {
        // Verify the project belongs to this client
        $project_query = "SELECT project_id FROM projects WHERE project_id = ? AND client_email = ? LIMIT 1";
        $stmt = $con->prepare($project_query);
        $stmt->bind_param('is', $project_id, $client_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Project not found or access denied');
        }
    }
    
    // Calculate the progress percentage based on the step (1-6)
    $progress_percentage = min(100, ($new_step / 6) * 100);
    
    // Update both client_step_progress and client_progress_indicator in projects table
    $update_query = "UPDATE projects SET 
                    client_step_progress = ?, 
                    client_progress_indicator = ? 
                    WHERE project_id = ?";
    $update_stmt = $con->prepare($update_query);
    $update_stmt->bind_param('idi', $new_step, $progress_percentage, $project_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Step updated successfully']);
    } else {
        throw new Exception('Failed to update step');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
