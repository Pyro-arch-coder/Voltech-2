<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

require_once '../config.php';

$response = ['success' => false, 'message' => 'An error occurred', 'filePath' => ''];

try {
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    if ($projectId <= 0) throw new Exception('Missing or invalid project ID.');
    $contractType = 'clientsigned'; // Only handle client signed contracts

    if (!isset($_FILES['contract_file']) || $_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error.');
    }

    $file = $_FILES['contract_file'];
    $maxFileSize = 5 * 1024 * 1024;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if ($mimeType !== 'application/pdf') throw new Exception('Only PDF files are allowed.');
    if ($file['size'] > $maxFileSize) throw new Exception('File is too large. Max 5MB.');

    $fileName = 'contract_' . $contractType . '_' . $projectId . '_' . time() . '.pdf';
    $uploadDir = __DIR__ . '/../projectmanager/uploads/contracts/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) throw new Exception('Failed to save file.');

    $dbFilePath = 'uploads/contracts/' . basename($fileName);

    $uploadedBy = $_SESSION['user_id'] ?? null;
    if (!$uploadedBy) throw new Exception('Session missing user_id');

    // Check existence
    $checkStmt = $con->prepare("SELECT id FROM project_contracts WHERE project_id = ? AND contract_type = ?");
    $checkStmt->bind_param("is", $projectId, $contractType);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $checkStmt->close();

    if ($result && $result->num_rows > 0) {
        // Update
        $stmt = $con->prepare("UPDATE project_contracts SET file_path = ?, uploaded_at = NOW(), uploaded_by = ? WHERE project_id = ? AND contract_type = ?");
        $stmt->bind_param("siis", $dbFilePath, $uploadedBy, $projectId, $contractType);
    } else {
        // Insert
        $stmt = $con->prepare("INSERT INTO project_contracts (project_id, contract_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $projectId, $contractType, $dbFilePath, $uploadedBy);
    }
    $stmt->execute();
    $stmt->close();

    // ... notification logic unchanged ...

    $response['success'] = true;
    $response['message'] = 'Contract uploaded successfully!';
    $response['filePath'] = $dbFilePath;

} catch (Exception $e) {
    error_log($e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>