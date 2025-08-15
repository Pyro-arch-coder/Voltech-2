<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get receiver ID from query string
$receiver_id = isset($_GET['receiver_id']) ? intval($_GET['receiver_id']) : 0;
$current_user_id = $_SESSION['user_id'];

if ($receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid receiver ID']);
    exit();
}

// Mark messages as read when they are fetched
$mark_read_query = "UPDATE pm_client_messages 
                   SET is_read = 1 
                   WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
                   
if ($stmt = $con->prepare($mark_read_query)) {
    $stmt->bind_param("ii", $receiver_id, $current_user_id);
    $stmt->execute();
    $stmt->close();
}

// Fetch messages between the current user and the receiver
$query = "
    SELECT 
        m.id,
        m.sender_id,
        m.receiver_id,
        m.message,
        m.date_sent,
        m.is_read,
        u1.firstname as sender_firstname,
        u1.lastname as sender_lastname,
        u1.profile_path as sender_profile_path
    FROM pm_client_messages m
    JOIN users u1 ON m.sender_id = u1.id
    WHERE 
        (m.sender_id = ? AND m.receiver_id = ?) OR 
        (m.sender_id = ? AND m.receiver_id = ?)
    ORDER BY m.date_sent ASC
";

$response = ['success' => false, 'messages' => []];

if ($stmt = $con->prepare($query)) {
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
            'date_sent' => $row['date_sent'],
            'is_read' => (bool)$row['is_read'],
            'sender_name' => $row['sender_firstname'] . ' ' . $row['sender_lastname'],
            'sender_avatar' => !empty($row['sender_profile_path']) ? 
                '../uploads/' . $row['sender_profile_path'] : 
                '../uploads/default_profile.png'
        ];
    }
    
    $response = [
        'success' => true,
        'messages' => $messages
    ];
    
    $stmt->close();
}

echo json_encode($response);
$con->close();
?>
