<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once "../config.php";
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get project_id from query parameters
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Project ID is required']);
    exit();
}

// Get all blueprints for the project
$query = "SELECT * FROM blueprints WHERE project_id = ? ORDER BY created_at DESC";
$stmt = $con->prepare($query);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$result = $stmt->get_result();

$blueprints = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $blueprints[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'name' => $row['name'],
            'image_path' => '../projectmanager/' . $row['image_path'], // Add relative path to projectmanager directory
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
}

$con->close();

echo json_encode([
    'success' => true,
    'data' => $blueprints
]);
?>
