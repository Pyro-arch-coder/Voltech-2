<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration
include_once "../config.php";

// Get and validate parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if (empty($type) || $projectId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Map permit types to their respective tables
$tables = [
    'lgu' => 'project_lgu_permit',
    'barangay' => 'project_barangay_clearance',
    'fire' => 'project_fire_clearance',
    'occupancy' => 'project_occupancy_permit'
];

if (!isset($tables[$type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid permit type']);
    exit();
}

$tableName = $tables[$type];

try {
    // First, get the file path to delete the actual file
    $stmt = $con->prepare("SELECT file_path FROM $tableName WHERE project_id = ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filePath = '../' . $row['file_path'];
        
        // Delete the file if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete the record from the database
        $deleteStmt = $con->prepare("DELETE FROM $tableName WHERE project_id = ?");
        if (!$deleteStmt) {
            throw new Exception('Database prepare failed: ' . $con->error);
        }
        
        $deleteStmt->bind_param('i', $projectId);
        $deleteResult = $deleteStmt->execute();
        
        if ($deleteResult) {
            echo json_encode(['success' => true, 'message' => 'Permit deleted successfully']);
        } else {
            throw new Exception('Failed to delete record from database');
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No record found to delete']);
    }
} catch (Exception $e) {
    error_log('Delete permit error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting the permit: ' . $e->getMessage()
    ]);
}
