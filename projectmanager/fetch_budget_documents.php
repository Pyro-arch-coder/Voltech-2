<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response = array_merge($response, $data);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
        throw new Exception('Invalid project ID');
    }

    $projectId = (int)$_GET['project_id'];
    $userId = $_SESSION['user_id'];

    // Make sure you include your DB connection file before using $con
    require_once '../config.php'; // or wherever you define $con

    if (!isset($con) || !($con instanceof mysqli)) {
        throw new Exception('Database connection failed');
    }

    $query = "SELECT 
                id, 
                project_id, 
                estimation_pdf as file_path,
                created_at as upload_date,
                updated_at,
                status
              FROM project_pdf_approval 
              WHERE project_id = ? 
              ORDER BY created_at DESC";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $con->error);
    }
    
    if (!$stmt->bind_param("i", $projectId)) {
        throw new Exception('Failed to bind parameters: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Failed to get result set: ' . $stmt->error);
    }
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        if (isset($row['file_path'])) {
            $row['original_name'] = basename($row['file_path']);
            $row['file_name'] = $row['original_name'];
            $row['file_size'] = (file_exists($row['file_path']) && is_file($row['file_path'])) 
                ? filesize($row['file_path']) 
                : 0;
            $documents[] = $row;
        }
    }
    
    $stmt->free_result();
    $stmt->close();
    
    sendJsonResponse(true, '', [
        'documents' => $documents
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching budget documents: " . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while fetching budget documents: ' . $e->getMessage());
}

// Only call this if not exited yet, but since sendJsonResponse uses exit, you can REMOVE this line.
$con->close();
?>