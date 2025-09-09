<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config.php';

$response = ['success' => false, 'message' => 'An error occurred', 'permits' => []];

try {
    // Check if user is logged in and has appropriate permissions
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        throw new Exception('Unauthorized access. Please log in.');
    }

    // Get project ID from query parameters
    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if ($projectId <= 0) {
        throw new Exception('Invalid project ID');
    }

    // Prepare the query to get permits for the project
    $query = "SELECT 
                id, 
                project_id, 
                permit_type, 
                file_path, 
                uploaded_at,
                (SELECT CONCAT(firstname, ' ', lastname) FROM users WHERE id = uploaded_by) as uploaded_by_name
              FROM project_permits 
              WHERE project_id = ?
              ORDER BY permit_type";
    
    $stmt = $con->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $con->error);
    }
    
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result === false) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $permits = [];
    while ($row = $result->fetch_assoc()) {
        $filePath = $row['file_path'];
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Voltech-2/projectmanager/' . $filePath;
        $fileInfo = [
            'file_name' => basename($filePath),
            'file_size' => 0,
            'file_exists' => false
        ];

        if (file_exists($fullPath)) {
            $fileInfo['file_size'] = filesize($fullPath);
            $fileInfo['file_exists'] = true;
        }

        $permits[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'permit_type' => $row['permit_type'],
            'file_path' => $filePath,
            'file_name' => $fileInfo['file_name'],
            'file_size' => $fileInfo['file_size'],
            'file_exists' => $fileInfo['file_exists'],
            'uploaded_at' => $row['uploaded_at'],
            'uploaded_by' => $row['uploaded_by_name'] ?? 'Unknown'
        ];
    }
    
    $response = [
        'success' => true,
        'message' => 'Permits retrieved successfully',
        'permits' => $permits
    ];
    
} catch (Exception $e) {
    error_log('Error in get_permits.php: ' . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'permits' => []
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
