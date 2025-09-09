<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);

// Set headers first to ensure no output before them
header('Content-Type: application/json; charset=utf-8');

// Start output buffering to catch any unwanted output
ob_start();

try {
    // Include necessary files
    require_once '../config.php';

    // Check if user is logged in and has the right permissions
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Get project ID from query string
    $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

    if (!$project_id) {
        throw new Exception('Invalid project ID');
    }

    // Get project budget and approved billing requests
    $query = "
        SELECT 
            br.id,
            br.project_id,
            br.user_id,
            br.amount,
            br.status,
            br.request_date,
            p.budget as project_budget,
            (SELECT COALESCE(SUM(amount), 0) 
             FROM billing_requests 
             WHERE project_id = ? 
             AND status = 'approved') as total_approved_amount
        FROM billing_requests br
        JOIN projects p ON br.project_id = p.project_id
        WHERE br.project_id = ?
        AND br.status = 'approved'
        ORDER BY br.request_date DESC
    ";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }
    
    $stmt->bind_param('ii', $project_id, $project_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Failed to get result set: ' . $stmt->error);
    }
    
    $approved_requests = [];
    while ($row = $result->fetch_assoc()) {
        $approved_requests[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'user_id' => $row['user_id'],
            'amount' => $row['amount'],
            'project_budget' => $row['project_budget'],
            'request_date' => $row['request_date'],
            'status' => $row['status']
        ];
    }
    
    // Clear any output that might have been generated
    ob_end_clean();
    
    // Calculate total approved amount
    $total_approved = 0;
    if (!empty($approved_requests)) {
        $total_approved = (float)$approved_requests[0]['total_approved_amount'];
    }
    
    // Send JSON response with total approved amount
    echo json_encode([
        'success' => true,
        'data' => $approved_requests,
        'total_approved' => $total_approved
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Clean any output buffer
    ob_end_clean();
    
    // Log the error
    error_log('Error in get_approved_billing_requests.php: ' . $e->getMessage());
    
    // Send error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching approved billing requests.',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}

// Make sure no additional output is sent
exit;
?>
