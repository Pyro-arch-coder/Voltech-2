<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate project_id from GET URL
if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid project ID']);
    exit();
}

$project_id = intval($_GET['project_id']);

// Database connection
include_once "../config.php";

try {
    // Only fetch blueprints that match the specific project_id
    $stmt = $con->prepare("SELECT * FROM blueprints WHERE project_id = ? ORDER BY created_at DESC");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $con->error);
    }

    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $blueprints = [];
    while ($row = $result->fetch_assoc()) {
        $blueprints[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'image_path' => $row['image_path'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'file_extension' => pathinfo($row['image_path'], PATHINFO_EXTENSION)
        ];
    }

    echo json_encode([
        'success' => true,
        'blueprints' => $blueprints,
        'count' => count($blueprints)
    ]);

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$con->close();
?>
