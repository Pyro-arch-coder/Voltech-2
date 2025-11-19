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
    'cost_estimates' => []
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

    // Get cost estimate files for this project
    $stmt = $con->prepare("SELECT * FROM cost_estimate_files WHERE project_id = ? ORDER BY upload_date DESC");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cost_estimates = [];
    while ($row = $result->fetch_assoc()) {
        // Normalize file path to ensure it has the correct prefix
        $filePath = $row['file_path'];
        $fileName = basename($filePath);
        
        // Adjust path for client access (from p_client folder)
        // The file_path in DB is like: uploads/cost_estimates/filename.pdf (relative to projectmanager folder)
        // We need: ../projectmanager/uploads/cost_estimates/filename.pdf
        if (strpos($filePath, '../') === false && strpos($filePath, 'projectmanager/') === false) {
            // Path doesn't have projectmanager prefix, add it
            $filePath = '../projectmanager/' . $filePath;
        } else if (strpos($filePath, 'projectmanager/') !== false && strpos($filePath, '../') === false) {
            // Path has projectmanager but no ../ prefix, add it
            $filePath = '../' . $filePath;
        }
        
        $cost_estimates[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'user_id' => $row['user_id'],
            'file_name' => $row['file_name'],
            'file_path' => $filePath,
            'upload_date' => $row['upload_date'],
            'status' => $row['status'],
            'cost_type' => $row['cost_type'],
            'estimated_cost' => $row['estimated_cost']
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Cost estimates retrieved successfully';
    $response['cost_estimates'] = $cost_estimates;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

