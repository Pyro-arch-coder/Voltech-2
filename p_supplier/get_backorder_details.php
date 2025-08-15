<?php
session_start();
require_once '../config.php';

// Check if user is logged in and has supplier access
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 5) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

$backorderId = $con->real_escape_string($_GET['id']);

// Query to get backorder details with requester information
$query = "SELECT bo.*, m.material_name, u.firstname, u.lastname, u.email as requester_email
          FROM back_orders bo
          LEFT JOIN materials m ON bo.material_id = m.id
          LEFT JOIN users u ON bo.requested_by = u.id
          WHERE bo.id = ?";

$stmt = $con->prepare($query);
$stmt->bind_param('i', $backorderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Backorder not found']);
    exit();
}

$backorder = $result->fetch_assoc();

// Format dates for better readability
if (isset($backorder['created_at'])) {
    $date = new DateTime($backorder['created_at']);
    $backorder['formatted_created_at'] = $date->format('F j, Y \a\t g:i A');
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $backorder
]);

$con->close();
?>
