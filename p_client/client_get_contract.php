<?php
// Disable error display to prevent HTML output
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Include config
require_once '../config.php';

// Initialize response
$response = [
    'success' => false,
    'message' => 'An error occurred',
    'contracts' => []
];

try {
    // Validate user session
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    // Validate project_id
    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if ($projectId <= 0) {
        throw new Exception('Missing or invalid project ID.');
    }

    // Get contracts for this project
    $stmt = $con->prepare("SELECT * FROM project_contracts WHERE project_id = ? ORDER BY contract_type");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $contracts = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize file path to ensure it has the correct prefix
        $filePath = $row['file_path'];
        $fileName = basename($filePath);
        
        // If the path doesn't start with projectmanager/, add it
        if (strpos($filePath, 'projectmanager/') !== 0) {
            $filePath = 'projectmanager/uploads/contracts/' . $fileName;
        }
        
        $contracts[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'contract_type' => $row['contract_type'],
            'file_path' => $filePath,
            'uploaded_at' => $row['uploaded_at'],
            'uploaded_by' => $row['uploaded_by']
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Contracts retrieved successfully';
    $response['contracts'] = $contracts;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
