<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($success, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

// Include database configuration
require_once '../config.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Check if required parameters are provided
    if (!isset($_POST['id']) || !is_numeric($_POST['id']) || 
        !isset($_POST['status']) || !in_array($_POST['status'], ['Show', 'Not Show']) ||
        !isset($_POST['project_id']) || !is_numeric($_POST['project_id'])) {
        throw new Exception('Invalid parameters');
    }

    $documentId = (int)$_POST['id'];
    $status = $_POST['status'];
    $projectId = (int)$_POST['project_id'];
    $userId = $_SESSION['user_id'];

    // Check database connection
    if (!isset($con) || !($con instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }
    // Verify the document belongs to the specified project
    $stmt = $con->prepare("SELECT id FROM project_pdf_approval WHERE id = ? AND project_id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    
    if (!$stmt->bind_param("ii", $documentId, $projectId)) {
        throw new Exception('Failed to bind parameters: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Failed to get result set: ' . $stmt->error);
    }
    
    if ($result->num_rows === 0) {
        throw new Exception('Document not found or does not belong to this project');
    }
    $stmt->close();
    
    // Update the document status
    $stmt = $con->prepare("UPDATE project_pdf_approval SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    
    if (!$stmt->bind_param("si", $status, $documentId)) {
        throw new Exception('Failed to bind parameters: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update document: ' . $stmt->error);
    }
    
    $stmt->close();
    
    // Return success response
    sendJsonResponse(true, 'Document visibility updated successfully');
    
} catch (Exception $e) {
    error_log("Error updating document visibility: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while updating document visibility: ' . $e->getMessage());
}

$conn->close();
