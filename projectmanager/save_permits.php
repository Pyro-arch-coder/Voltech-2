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

$response = ['success' => false, 'message' => 'An error occurred', 'file_path' => ''];

try {
    if (!isset($_SESSION['logged_in'])) {
        throw new Exception('Unauthorized access');
    }

    $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    $permitType = isset($_POST['permit_type']) ? trim(strtolower($_POST['permit_type'])) : '';
    if ($projectId <= 0) throw new Exception('Missing or invalid project ID.');

    $allowedTypes = ['lgu', 'barangay', 'fire', 'zoning', 'occupancy'];
    if (!in_array($permitType, $allowedTypes)) throw new Exception('Invalid permit type.');

    if (!isset($_FILES['file_photo']) || $_FILES['file_photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error.');
    }

    // Check if a file was actually uploaded
    if (isset($_FILES['file_photo']) && $_FILES['file_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file_photo'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Accept PDF and common image types
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];
        if (!in_array($mimeType, $allowedMimeTypes)) throw new Exception('File type not allowed.');
        if ($file['size'] > $maxFileSize) throw new Exception('File is too large. Max 10MB.');

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeType = preg_replace('/[^a-z0-9]/i', '', $permitType);

        $uploadDir = __DIR__ . '/uploads/permits/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $fileName = 'permit_' . $safeType . '_' . $projectId . '.' . $extension;
        $filePath = $uploadDir . basename($fileName);

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save file.');
        }
        $dbFilePath = 'uploads/permits/' . basename($fileName);
    }

    $uploadedBy = $_SESSION['user_id'] ?? null;
    if (!$uploadedBy) throw new Exception('Session missing user_id');

    // Check existence
    $checkStmt = $con->prepare("SELECT id FROM project_permits WHERE project_id = ? AND permit_type = ?");
    $checkStmt->bind_param("is", $projectId, $permitType);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $checkStmt->close();

    if ($result && $result->num_rows > 0) {
        // Update
        $stmt = $con->prepare("UPDATE project_permits SET file_path = ?, uploaded_at = NOW(), uploaded_by = ? WHERE project_id = ? AND permit_type = ?");
        $stmt->bind_param("siis", $dbFilePath, $uploadedBy, $projectId, $permitType);
    } else {
        // Insert
        $stmt = $con->prepare("INSERT INTO project_permits (project_id, permit_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $projectId, $permitType, $dbFilePath, $uploadedBy);
    }
    $stmt->execute();
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Permit uploaded successfully!';
    $response['file_path'] = $dbFilePath;

} catch (Exception $e) {
    error_log($e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>