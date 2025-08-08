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
$response = ['success' => false, 'budget' => null];

$projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($projectId > 0) {
    $stmt = $con->prepare("SELECT * FROM project_budget_approval WHERE project_id=? LIMIT 1");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $response['success'] = true;
        $response['budget'] = $row;
    } else {
        $response['success'] = true;
        $response['budget'] = ['status' => 'Not Uploaded'];
    }
}
echo json_encode($response);
?>