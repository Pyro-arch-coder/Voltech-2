<?php
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

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_level'] != 6) {
    sendJsonResponse(false, null, 'Unauthorized access', 401);
}

// Get POST data
$receiver_id = filter_input(INPUT_POST, 'receiver_id', FILTER_VALIDATE_INT);
$message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));
$sender_id = $_SESSION['user_id'];

// Validate input
if (!$receiver_id || empty($message)) {
    sendJsonResponse(false, null, 'Invalid input: Missing receiver ID or message', 400);
}

// Verify receiver is a project manager (user_level = 3)
$check_user = $con->prepare("SELECT id, user_level FROM users WHERE id = ? AND user_level = 3");
$check_user->bind_param("i", $receiver_id);
$check_user->execute();
$result = $check_user->get_result();

if ($result->num_rows === 0) {
    sendJsonResponse(false, null, 'Invalid recipient', 400);
}

try {
    // Insert message into pm_client_messages table
    $stmt = $con->prepare("INSERT INTO pm_client_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $con->error);
    }
    
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    // Get the inserted message data
    $message_id = $stmt->insert_id;
    $stmt->close();
    
    // Get the message with sender info for the response
    $query = "SELECT m.*, u.firstname, u.lastname, u.profile_path 
              FROM pm_client_messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE m.id = ?";
    
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $message_data = $result->fetch_assoc();
    
    // Format the response
    $response = [
        'id' => $message_data['id'],
        'sender_id' => $message_data['sender_id'],
        'receiver_id' => $message_data['receiver_id'],
        'message' => htmlspecialchars($message_data['message']),
        'date_sent' => $message_data['date_sent'],
        'is_read' => (bool)$message_data['is_read'],
        'sender_name' => $message_data['firstname'] . ' ' . $message_data['lastname'],
        'sender_avatar' => !empty($message_data['profile_path']) ? 
            '../uploads/' . $message_data['profile_path'] : 
            '../uploads/default_profile.png'
    ];
    
    sendJsonResponse(true, $response);
    
} catch (Exception $e) {
    error_log('Error sending message: ' . $e->getMessage());
    sendJsonResponse(false, null, 'Internal server error', 500);
}

$con->close();
?>
