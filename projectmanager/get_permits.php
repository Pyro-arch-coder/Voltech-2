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
    'message' => 'An error occurred',
    'permits' => []
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

    // Get permits for this project
    $stmt = $con->prepare("SELECT * FROM project_permits WHERE project_id = ? ORDER BY permit_type");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    $permits = [];
    while ($row = $result->fetch_assoc()) {
        $permits[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'permit_type' => $row['permit_type'],
            'file_path' => $row['file_path'],
            'uploaded_at' => $row['uploaded_at'],
            'uploaded_by' => $row['uploaded_by']
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Permits retrieved successfully';
    $response['permits'] = $permits;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>