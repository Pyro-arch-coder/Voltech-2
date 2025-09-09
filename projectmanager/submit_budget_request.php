<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$project_manager_id = $_SESSION['user_id'];
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
$budget_amount = isset($_POST['budget_amount']) ? floatval($_POST['budget_amount']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

if ($budget_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid budget amount']);
    exit();
}

try {
    // Check if project exists and belongs to this project manager
    $checkProject = "SELECT project_id, client_email FROM projects WHERE project_id = ?";
    $stmt = $con->prepare($checkProject);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
        exit();
    }
    
    $project = $result->fetch_assoc();
    $client_email = $project['client_email'];
    
    // Check if there's already a pending budget request for this project
    $checkPending = "SELECT id FROM billing_requests WHERE project_id = ? AND status = 'pending'";
    $stmt = $con->prepare($checkPending);
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $pendingResult = $stmt->get_result();
    
    if ($pendingResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot submit new budget request. There is already a pending request for this project.']);
        exit();
    }
    
    // Always insert new record to allow multiple budget requests
    $insertQuery = "INSERT INTO billing_requests (project_id, user_id, amount, request_date, status) 
                   VALUES (?, ?, ?, NOW(), 'pending')";
    $stmt = $con->prepare($insertQuery);
    $stmt->bind_param('iid', $project_id, $project_manager_id, $budget_amount);
    
    if ($stmt->execute()) {
        // Insert notification into notifications_client table
        $notificationMessage = "A new budget request has been submitted for your project. Amount: ₱" . number_format($budget_amount, 2);
        $insertNotification = "INSERT INTO notifications_client (user_id, client_email, notif_type, message, is_read, created_at) 
                             VALUES (?, ?, 'budget_request', ?, 0, NOW())";
        $stmt = $con->prepare($insertNotification);
        $stmt->bind_param('iss', $project_manager_id, $client_email, $notificationMessage);
        $stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Budget request submitted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit budget request']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
