<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ID is provided
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid floor plan ID']);
    exit();
}

$planId = intval($_POST['id']);

// Database connection
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// First, get the image path
$stmt = $con->prepare("SELECT image_path FROM floor_plans WHERE id = ?");
$stmt->bind_param("i", $planId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $con->close();
    echo json_encode(['success' => false, 'message' => 'Floor plan not found']);
    exit();
}

$plan = $result->fetch_assoc();
$stmt->close();

// Delete the file if it exists
$filePath = '../' . $plan['image_path'];
if (file_exists($filePath)) {
    unlink($filePath);
}

// Delete the record from database
$stmt = $con->prepare("DELETE FROM floor_plans WHERE id = ?");
$stmt->bind_param("i", $planId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Floor plan deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete floor plan']);
}

$stmt->close();
$con->close();
?>
