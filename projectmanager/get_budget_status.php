<?php
// Suppress error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

try {
    // Check database connection
    if (!$con) {
        throw new Exception('Database connection failed');
    }
    
    // Debug: Log the query being executed
    error_log("Debug: Executing query for project_id: " . $project_id);
    
    // Get all budget requests for this project (ordered by most recent first)
    $query = "SELECT * FROM billing_requests WHERE project_id = ? ORDER BY request_date DESC";
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }
    
    $stmt->bind_param('i', $project_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $budgetRequests = [];
        $hasPendingRequest = false;
        
        while ($row = $result->fetch_assoc()) {
            $budgetRequests[] = [
                'id' => $row['id'],
                'requested_amount' => $row['amount'],
                'request_date' => $row['request_date'],
                'status' => $row['status']
            ];
            
            // Check if there's a pending request
            if ($row['status'] === 'pending') {
                $hasPendingRequest = true;
            }
        }
        
        // Debug: Log the data being returned
        error_log("Debug: Found " . count($budgetRequests) . " budget requests: " . json_encode($budgetRequests));
        
        $response = [
            'success' => true,
            'data' => $budgetRequests,
            'latest' => $budgetRequests[0], // First one is the most recent
            'total_requests' => count($budgetRequests),
            'has_pending_request' => $hasPendingRequest
        ];
        
        error_log("Debug: Sending response: " . json_encode($response));
        echo json_encode($response);
    } else {
        error_log("Debug: No budget request found for project_id: " . $project_id);
        echo json_encode([
            'success' => true,
            'data' => [],
            'latest' => null,
            'total_requests' => 0,
            'has_pending_request' => false,
            'message' => 'No budget request found for this project'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

if (isset($con)) {
    $con->close();
}
?>