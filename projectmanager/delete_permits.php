<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config.php';

$response = [
    'success' => false,
    'message' => 'An error occurred'
];

try {
    // Validate user session
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate required parameters
    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $permitType = isset($_POST['permit_type']) ? trim(strtolower($_POST['permit_type'])) : '';
    
    if ($projectId <= 0) {
        throw new Exception('Missing or invalid project ID.');
    }
    
    $allowedTypes = ['lgu', 'barangay', 'fire', 'zoning', 'occupancy'];
    if (!in_array($permitType, $allowedTypes)) {
        throw new Exception('Invalid permit type.');
    }

    // Start transaction
    $con->begin_transaction();

    try {
        // First, get the file path to delete the actual file
        $stmt = $con->prepare("SELECT file_path FROM project_permits WHERE project_id = ? AND permit_type = ?");
        $stmt->bind_param("is", $projectId, $permitType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc() && !empty($row['file_path'])) {
            $filePath = '../' . ltrim($row['file_path'], '/');
            // Delete the file if it exists
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        
        // Then delete the record from the database
        $stmt = $con->prepare("DELETE FROM project_permits WHERE project_id = ? AND permit_type = ?");
        $stmt->bind_param("is", $projectId, $permitType);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $con->commit();
            $response['success'] = true;
            $response['message'] = 'Permit deleted successfully';
        } else {
            throw new Exception('No matching record found to delete');
        }
    } catch (Exception $e) {
        $con->rollback();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
