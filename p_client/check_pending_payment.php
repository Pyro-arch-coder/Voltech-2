<?php
// Enable error reporting but don't display errors (we'll log them instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start output buffering to catch any unwanted output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once "../config.php";

// Set content type to JSON
header('Content-Type: application/json');

// Initialize response
$response = [
    'hasPendingPayment' => false,
    'message' => ''
];

try {
    // Check if user is logged in and has client role
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
        throw new Exception('Unauthorized access');
    }

    // Get project ID from query string
    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if ($projectId <= 0) {
        throw new Exception('Invalid project ID');
    }

    // Check for any payment proof and its status
    $stmt = $con->prepare("SELECT status FROM initial_b_proof_of_payment WHERE project_id = ? ORDER BY upload_date DESC LIMIT 1");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $payment = $result->fetch_assoc();
        $response['status'] = $payment['status']; // 'pending', 'approved', or 'rejected'
        $response['hasPendingPayment'] = ($payment['status'] === 'pending');
        
        switch($payment['status']) {
            case 'pending':
                $response['message'] = 'A payment proof is pending approval';
                break;
            case 'approved':
                $response['message'] = 'Payment has been approved';
                break;
            case 'rejected':
                $response['message'] = 'Payment was rejected';
                break;
        }
    } else {
        $response['status'] = 'none';
        $response['hasPendingPayment'] = false;
        $response['message'] = 'No payment proof found';
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Clear any output that might have been generated
discard_output();

// Return JSON response
echo json_encode($response);

/**
 * Discard any output that might have been generated
 */
function discard_output() {
    // Get the current buffer contents and discard it
    $buffer = ob_get_clean();
    
    // If there was any output, log it for debugging
    if (!empty($buffer)) {
        error_log("Unexpected output in check_pending_payment.php: " . $buffer);
    }
}
?>
