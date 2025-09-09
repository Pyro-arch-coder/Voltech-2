<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config.php';

$response = [
    'success' => false,
    'message' => 'An error occurred',
    'contracts' => []
];

try {
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    $projectId = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
    if ($projectId <= 0) {
        throw new Exception('Missing or invalid project ID.');
    }

    // Only fetch supported contract types
    $stmt = $con->prepare("SELECT * FROM project_contracts WHERE project_id = ? AND (contract_type = 'yoursigned' OR contract_type = 'clientsigned') ORDER BY contract_type");
    $stmt->bind_param("i", $projectId);
    $stmt->execute();
    $result = $stmt->get_result();

    $contracts = [];
    while ($row = $result->fetch_assoc()) {
        $contracts[] = [
            'id' => $row['id'],
            'project_id' => $row['project_id'],
            'contract_type' => $row['contract_type'],
            'file_path' => $row['file_path'],
            // Add these lines if you have these columns
            'file_name' => isset($row['file_name']) ? $row['file_name'] : '',
            'file_size' => isset($row['file_size']) ? $row['file_size'] : 0,
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