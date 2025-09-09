<?php
// Ensure no output before headers
ob_start();
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has the right permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if ID is provided
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid blueprint ID']);
    exit();
}

$planId = intval($_POST['id']);

// Database connection
include_once "../config.php";

// Set error handler to catch any output
function handleError($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler('handleError');

try {
    // First, get the image path from blueprints table
    $stmt = $con->prepare("SELECT image_path FROM blueprints WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    
    $stmt->bind_param("i", $planId);
    if (!$stmt->execute()) {
        throw new Exception('Database query failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Blueprint not found');
    }

    $plan = $result->fetch_assoc();
    $stmt->close();

    // Delete the file if it exists (remove any '../' from path to prevent directory traversal)
    $filePath = str_replace(['../', '..\\'], '', $plan['image_path']);
    $filePath = __DIR__ . '/' . ltrim($filePath, '/\\');
    
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            throw new Exception('Failed to delete file');
        }
    }

    // Delete the record from database
    $stmt = $con->prepare("DELETE FROM blueprints WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    $stmt->bind_param("i", $planId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete blueprint: ' . $stmt->error);
    }
    
    $stmt->close();
    $con->close();
    
    // Clear any output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    echo json_encode(['success' => true, 'message' => 'Blueprint deleted successfully']);
    
} catch (Exception $e) {
    // Clean any output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Close database connection if still open
    if (isset($stmt) && $stmt) $stmt->close();
    if (isset($con) && $con) $con->close();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit();

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Floor plan deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete floor plan']);
}

$stmt->close();
$con->close();
?>
