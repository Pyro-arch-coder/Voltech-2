<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config.php';

$response = ['success' => false, 'message' => 'An error occurred'];

try {
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $contractType = isset($_POST['contract_type']) ? trim($_POST['contract_type']) : '';
    
    if ($projectId <= 0) throw new Exception('Missing or invalid project ID.');
    if (!in_array($contractType, ['original', 'yoursigned', 'clientsigned'])) {
        throw new Exception('Invalid contract type.');
    }

    // Start transaction
    $con->begin_transaction();

    try {
        // Get the file path before deleting
        $stmt = $con->prepare("SELECT file_path FROM project_contracts WHERE project_id = ? AND contract_type = ?");
        $stmt->bind_param("is", $projectId, $contractType);
        $stmt->execute();
        $result = $stmt->get_result();
        $filePath = null;
        
        if ($row = $result->fetch_assoc()) {
            $filePath = $row['file_path'];
        }
        $stmt->close();

        // Delete from database
        $stmt = $con->prepare("DELETE FROM project_contracts WHERE project_id = ? AND contract_type = ?");
        $stmt->bind_param("is", $projectId, $contractType);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No contract found to delete.');
        }
        $stmt->close();

        // Delete the file if it exists
        if ($filePath) {
            $fullPath = __DIR__ . '/' . $filePath;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $con->commit();
        $response = [
            'success' => true,
            'message' => 'Contract deleted successfully!'
        ];
    } catch (Exception $e) {
        $con->rollback();
        throw $e;
    }
} catch (Exception $e) {
    error_log('Delete contract error: ' . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
