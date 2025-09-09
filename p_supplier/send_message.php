<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to user
ini_set('log_errors', 1);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require_once '../config.php';

// Function to send JSON response and exit
function sendJsonResponse($success, $data = null, $error = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = ['success' => $success];
    if ($data !== null) $response['data'] = $data;
    if ($error !== null) $response['error'] = $error;
    
    // Clear any previous output
    if (ob_get_length()) ob_end_clean();
    
    echo json_encode($response);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_level'])) {
    sendJsonResponse(false, null, 'Not authenticated', 401);
}

// Get POST data
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));
$table = filter_input(INPUT_POST, 'table', FILTER_SANITIZE_STRING);
$sender_id = $_SESSION['user_id'];

// Validate input
if (!$receiver_id || empty($message)) {
    sendJsonResponse(false, null, 'Invalid input: Missing receiver ID or message', 400);
}

// Validate table name to prevent SQL injection
$valid_tables = ['pm_supplier_messages', 'pm_procurement_messages'];
if (!in_array($table, $valid_tables, true)) {
    sendJsonResponse(false, null, 'Invalid message table specified. Valid tables are: ' . implode(', ', $valid_tables), 400);
}

try {
    // Prepare and execute the insert query
    $stmt = $con->prepare("INSERT INTO `$table` (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }
    
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $message_id = $con->insert_id;
    
    // Get the inserted message with additional details
    $query = "SELECT m.*, u.firstname, u.lastname 
              FROM `$table` m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.id = ?";
    
    $stmt = $con->prepare($query);
    if ($stmt === false) {
        throw new Exception('Failed to prepare select statement: ' . $con->error);
    }
    
    $stmt->bind_param("i", $message_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch sent message: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $message_data = $result->fetch_assoc();
    
    if (!$message_data) {
        throw new Exception('Failed to retrieve sent message details');
    }
    
    sendJsonResponse(true, $message_data);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Message send error: ' . $e->getMessage());
    
    // Send a generic error message to the client
    sendJsonResponse(false, null, 'Failed to send message. Please try again.', 500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($con)) $con->close();
    
    // Clean any output buffer
    if (ob_get_length()) ob_end_clean();
}
?>
