<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
include_once "../config.php";
if ($con->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get all floor plans
$result = $con->query("SELECT * FROM floor_plans ORDER BY created_at DESC");
$floorPlans = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $floorPlans[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'location' => $row['location'],
            'image_path' => $row['image_path'],
            'created_at' => $row['created_at']
        ];
    }
}

$con->close();

echo json_encode([
    'success' => true,
    'data' => $floorPlans
]);
?>
