<?php
session_start();
require_once "../config.php";

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

// Get parameters
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;
$current_user_id = $_SESSION['user_id'];

if ($receiver_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid receiver ID');
}

try {
    // Mark procurement messages as read
    $updateStmt = $con->prepare("UPDATE pm_procurement_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $updateStmt->bind_param("ii", $receiver_id, $current_user_id);
    $updateStmt->execute();
    
    // Fetch messages between current user and receiver from procurement
    $query = "SELECT m.*, u.firstname, u.lastname, u.profile_path 
              FROM pm_procurement_messages m 
              JOIN users u ON m.sender_id = u.id 
              WHERE (m.sender_id = ? AND m.receiver_id = ?) 
              OR (m.sender_id = ? AND m.receiver_id = ?) 
              ORDER BY m.date_sent ASC";
    
    $stmt = $con->prepare($query);
    $stmt->bind_param("iiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'message' => htmlspecialchars($row['message']),
            'is_read' => $row['is_read'],
            'date_sent' => $row['date_sent'],
            'sender_name' => $row['firstname'] . ' ' . $row['lastname'],
            'sender_avatar' => !empty($row['profile_path']) ? '../' . $row['profile_path'] : '../uploads/default_profile.png',
            'is_me' => $row['sender_id'] == $current_user_id
        ];
    }
    
    // Return messages as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch procurement messages: ' . $e->getMessage()
    ]);
}
?>
