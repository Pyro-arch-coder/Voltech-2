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
$default_step = 1; // Default to step 1 if no record exists

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
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    
    if ($project_id <= 0) {
        // If no project_id provided, get the latest project for the client
        $query = "SELECT client_step_progress, project_id FROM projects WHERE client_email = ? ORDER BY project_id DESC LIMIT 1";
        $stmt = $con->prepare($query);
        $stmt->bind_param('s', $client_email);
    } else {
        // Get specific project for the client
        $query = "SELECT client_step_progress FROM projects WHERE project_id = ? AND client_email = ? LIMIT 1";
        $stmt = $con->prepare($query);
        $stmt->bind_param('is', $project_id, $client_email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_step = intval($row['client_step_progress']);
        
        // Validate step number (1-6)
        if ($current_step < 1 || $current_step > 6) {
            $current_step = $default_step;
        }
    } else {
        $current_step = $default_step;
    }
    
    echo json_encode([
        'success' => true, 
        'current_step' => $current_step
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'current_step' => $default_step
    ]);
}

$con->close();
?>
