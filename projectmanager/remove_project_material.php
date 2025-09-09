<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include_once "../config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pem_id'])) {
    try {
        $pem_id = intval($_POST['pem_id']);
        
        if ($pem_id <= 0) {
            throw new Exception('Invalid material ID');
        }
        
        // Remove from database using the PEM ID
        $delete_query = "DELETE FROM project_estimating_materials WHERE id = ?";
        $stmt = $con->prepare($delete_query);
        $stmt->bind_param("i", $pem_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to remove material: ' . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Material not found or already removed');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Material removed successfully!'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request. Missing required parameters.'
    ]);
}

$con->close();
?>