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
    $contractType = isset($_POST['contract_type']) ? trim($_POST['contract_type']) : '';
    if ($projectId <= 0) throw new Exception('Missing or invalid project ID.');
    if (!in_array($contractType, ['original', 'yoursigned', 'clientsigned'])) throw new Exception('Invalid contract type.');

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

    $uploadDir = __DIR__ . '/uploads/contracts/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    $fileName = 'contract_' . $contractType . '_' . $projectId . '_' . time() . '.pdf';
    $filePath = $uploadDir . basename($fileName);

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

    // --- NOTIFICATION LOGIC START ---
    // Get client_email and user_id for this project
    $client_email = '';
    $user_id = null;
    $stmt_proj = $con->prepare("SELECT client_email, user_id FROM projects WHERE project_id = ?");
    $stmt_proj->bind_param("i", $projectId);
    $stmt_proj->execute();
    $stmt_proj->bind_result($client_email, $user_id);
    $stmt_proj->fetch();
    $stmt_proj->close();

    if ($client_email && $user_id) {
        $notif_type = 'contract_upload_' . $contractType;
        $type_map = [
            'original' => 'Original Contract',
            'yoursigned' => 'Your Signed Contract',
            'clientsigned' => 'Client Signed Contract'
        ];
        $contractLabel = $type_map[$contractType] ?? ucfirst($contractType);
        $message = "A new {$contractLabel} has been uploaded for your project.";
        $is_read = 0;

        $stmt_notif = $con->prepare(
            "INSERT INTO notifications_client (user_id, client_email, notif_type, message, is_read, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt_notif->bind_param(
            "isssi",
            $user_id,
            $client_email,
            $notif_type,
            $message,
            $is_read
        );
        $stmt_notif->execute();
        $stmt_notif->close();
    }
    // --- NOTIFICATION LOGIC END ---

    $response['success'] = true;
    $response['message'] = 'Contract uploaded successfully!';
    $response['filePath'] = $dbFilePath;

} catch (Exception $e) {
    error_log($e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>