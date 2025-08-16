<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true || $_SESSION['user_level'] != 6) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config.php'; // Connection to MySQL

// Suppress errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
ob_clean();

header('Content-Type: application/json');

try {
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if (!$project_id) {
        throw new Exception('Project ID required');
    }

    // Debug: Log the project ID being requested
    error_log("Debug: Fetching pending billing request for project_id: " . $project_id);

    // Check if user has access to this project
    $user_id = $_SESSION['user_id'];
    error_log("Debug: User ID: " . $user_id);
    
    // Fixed: Changed 'id' to 'project_id' in projects table
    $check_query = "SELECT client_email FROM projects WHERE project_id = ? AND client_email = (SELECT email FROM users WHERE id = ?)";
    $check_stmt = $con->prepare($check_query);
    $check_stmt->bind_param("ii", $project_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('Access denied to this project');
    }
    $check_stmt->close();

    // Debug: Check if billing_requests table has pending data for this project
    $debug_query = "SELECT COUNT(*) as count FROM billing_requests WHERE project_id = ? AND status = 'pending'";
    $debug_stmt = $con->prepare($debug_query);
    $debug_stmt->bind_param("i", $project_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    $debug_count = $debug_result->fetch_assoc()['count'];
    error_log("Debug: Found " . $debug_count . " pending billing requests for project " . $project_id);
    $debug_stmt->close();

    // Only fetch pending billing requests
    $query = "SELECT id, amount, status, request_date FROM billing_requests WHERE project_id = ? AND status = 'pending' ORDER BY request_date DESC LIMIT 1";
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }
    
    $stmt->bind_param("i", $project_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        error_log("Debug: Fetched pending data: " . json_encode($row));
        
        $response = [
            'success' => true,
            'data' => [
                'id' => $row['id'], // Include ID for approve/reject actions
                'amount' => number_format($row['amount'], 2),
                'status' => ucfirst($row['status']),
                'request_date' => date('M d, Y', strtotime($row['request_date']))
            ]
        ];
    } else {
        error_log("Debug: No pending billing requests found for project " . $project_id);
        $response = [
            'success' => true,
            'data' => null,
            'message' => 'No pending billing request found for this project'
        ];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Debug: Error occurred: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

if (isset($con)) {
    $con->close();
}

echo json_encode($response);
?>