<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output

session_start();
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_level'] != 6) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once '../config.php';

if (!isset($_GET['project_id']) || !is_numeric($_GET['project_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

$project_id = intval($_GET['project_id']);

try {
    // Verify that the client has access to this project
    $client_email = $_SESSION['email'];
    $project_check = "SELECT project_id FROM projects WHERE project_id = ? AND client_email = ?";
    $stmt = $con->prepare($project_check);
    if (!$stmt) {
        throw new Exception("Database error: " . $con->error);
    }
    $stmt->bind_param("is", $project_id, $client_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied to this project']);
        exit();
    }

    // Check if project_permits table exists
    $table_check = "SHOW TABLES LIKE 'project_permits'";
    $table_result = $con->query($table_check);
    if ($table_result->num_rows === 0) {
        // Table doesn't exist, return empty permits array
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'permits' => [],
            'message' => 'No permits table found'
        ]);
        exit();
    }

    // Fetch permits for the project
    $permits_query = "SELECT 
                        id, 
                        project_id, 
                        permit_type, 
                        file_path, 
                        uploaded_at,
                        (SELECT CONCAT(firstname, ' ', lastname) FROM users WHERE id = uploaded_by) as uploaded_by_name
                      FROM project_permits 
                      WHERE project_id = ?
                      ORDER BY permit_type";
    $stmt = $con->prepare($permits_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $con->error);
    }
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

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
            'permit_type' => $row['permit_type'],
            'file_path' => '../projectmanager/' . $filePath, // Add relative path to projectmanager directory
            'file_name' => $fileInfo['file_name'],
            'upload_date' => $row['uploaded_at'],
            'file_size' => $fileInfo['file_size']
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'permits' => $permits
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in get_client_permits.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'debug' => $e->getMessage()
    ]);
}
?>
