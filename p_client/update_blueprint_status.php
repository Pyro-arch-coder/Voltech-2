<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['blueprint_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$blueprint_id = (int)$_POST['blueprint_id'];
$status = $_POST['status'];

// Validate status
$allowed_statuses = ['Approved', 'Rejected', 'Pending'];
if (!in_array($status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Database connection
require_once '../config.php';

// Check if the blueprint exists and belongs to a project the client has access to (by client_email)
$check_sql = "SELECT b.*, p.project_id, p.user_id, p.project, p.client_email 
              FROM blueprints b 
              INNER JOIN projects p ON b.project_id = p.project_id 
              WHERE b.id = ? AND p.client_email = ?";

$stmt = $con->prepare($check_sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: prepare failed']);
    exit();
}
$stmt->bind_param('is', $blueprint_id, $_SESSION['email']);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: execute failed']);
    exit();
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Blueprint not found or access denied']);
    exit();
}
$blueprint_row = $result->fetch_assoc();

// Update the blueprint status and ensure it belongs to the correct project
$update_sql = "UPDATE blueprints b 
               INNER JOIN projects p ON b.project_id = p.project_id 
               SET b.status = ? 
               WHERE b.id = ? AND p.client_email = ?";
$stmt = $con->prepare($update_sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: prepare failed (update)']);
    exit();
}
$stmt->bind_param('sis', $status, $blueprint_id, $_SESSION['email']);

if ($stmt->execute()) {
    // Insert notification for the project manager if status is Approved or Rejected
    if ($status === 'Approved' || $status === 'Rejected') {
        // Get project manager user_id and project name
        $pm_user_id = $blueprint_row['user_id'];
        $project_name = $blueprint_row['project'];
        $notif_type = "Blueprint $status";
        $message = "Blueprint for project" . htmlspecialchars($project_name) . "has been $status by the client.";
        $insert_notif = $con->prepare("INSERT INTO notifications_projectmanager (user_id, notif_type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
        if ($insert_notif) {
            $insert_notif->bind_param('iss', $pm_user_id, $notif_type, $message);
            $insert_notif->execute();
        }
    }
    // Custom message for rejection
    if ($status === 'Rejected') {
        echo json_encode([
            'success' => true,
            'message' => 'Blueprint status updated successfully. Sorry, please re-upload.'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Blueprint status updated successfully'
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update blueprint status']);
}

$con->close();
?>