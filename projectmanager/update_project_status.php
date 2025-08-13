<?php
// Set the default timezone to Philippine time
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../config.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 3) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['project_id']) || !isset($_POST['status'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$project_id = intval($_POST['project_id']);
$status = $_POST['status'];
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['Ongoing', 'Finished', 'Cancelled', 'On Hold'];
if (!in_array($status, $valid_statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Start transaction
$con->begin_transaction();

try {
    // Update project status
    $update_query = "UPDATE projects SET status = ? WHERE project_id = ? AND user_id = ?";
    $stmt = $con->prepare($update_query);
    $stmt->bind_param('sii', $status, $project_id, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update project status');
    }
    
    // Update start_date to today when status is set to 'Ongoing'
    if ($status === 'Ongoing') {
        $today = date('Y-m-d');
        $update_date = $con->prepare("UPDATE projects SET start_date = ? WHERE project_id = ?");
        $update_date->bind_param('si', $today, $project_id);
        if (!$update_date->execute()) {
            throw new Exception('Failed to update project start date: ' . $con->error);
        }
    }
    
    // Commit transaction
    $con->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $con->rollback();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while updating the project status: ' . $e->getMessage()
    ]);
}

$con->close();
?>
