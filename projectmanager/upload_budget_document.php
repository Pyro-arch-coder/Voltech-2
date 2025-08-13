<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

// Helper to send JSON response
function sendJsonResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response = array_merge($response, $data);
    echo json_encode($response);
    exit;
}

// Check: user logged in, POST, has file and project_id
if (!isset($_SESSION['user_id'])) sendJsonResponse(false, 'Unauthorized access.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonResponse(false, 'Invalid request method.');
if (!isset($_FILES['file']) || !isset($_POST['project_id'])) sendJsonResponse(false, 'Missing required parameters.');

$projectId = (int)$_POST['project_id'];
$file = $_FILES['file'];

// Validate project ID
if ($projectId <= 0) sendJsonResponse(false, 'Invalid project ID');

// Validate file type
if (mime_content_type($file['tmp_name']) !== 'application/pdf') sendJsonResponse(false, 'Only PDF files allowed');

// Validate file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) sendJsonResponse(false, 'File size exceeds 10MB limit');

// DB connect
require_once '../config.php'; // $con is defined here

// Upload dir
$uploadDir = '../uploads/budget_approvals/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

// Unique filename
$fileName = 'budget_' . $projectId . '_' . time() . '.pdf';
$filePath = $uploadDir . $fileName;

// Check if record exists for this project_id
$stmt = $con->prepare("SELECT id, estimation_pdf FROM project_pdf_approval WHERE project_id = ? LIMIT 1");
if (!$stmt) sendJsonResponse(false, 'Database prepare failed: ' . $con->error);
$stmt->bind_param("i", $projectId);
$stmt->execute();
$stmt->store_result();

$existingId = null;
$existingFile = null;
if ($stmt->num_rows > 0) {
    $stmt->bind_result($existingId, $existingFile);
    $stmt->fetch();
}
$stmt->close();

// Move the uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) sendJsonResponse(false, 'Failed to move uploaded file');

// If exists, delete old file and update record
if ($existingId) {
    if ($existingFile && file_exists($existingFile)) {
        @unlink($existingFile);
    }
    $stmt = $con->prepare("UPDATE project_pdf_approval SET estimation_pdf = ?, status = 'Not Show', updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        @unlink($filePath);
        sendJsonResponse(false, 'Database prepare failed: ' . $con->error);
    }
    $stmt->bind_param("si", $filePath, $existingId);
    if (!$stmt->execute()) {
        @unlink($filePath);
        sendJsonResponse(false, 'Database update failed: ' . $stmt->error);
    }
    $stmt->close();
    $document_id = $existingId;
} else {
    // Insert new record
    $stmt = $con->prepare("INSERT INTO project_pdf_approval (project_id, estimation_pdf, status, created_at, updated_at) VALUES (?, ?, 'Not Show', NOW(), NOW())");
    if (!$stmt) {
        @unlink($filePath);
        sendJsonResponse(false, 'Database prepare failed: ' . $con->error);
    }
    $stmt->bind_param("is", $projectId, $filePath);
    if (!$stmt->execute()) {
        @unlink($filePath);
        sendJsonResponse(false, 'Database insert failed: ' . $stmt->error);
    }
    $document_id = $con->insert_id;
    $stmt->close();
}

sendJsonResponse(true, 'File uploaded and record saved.', [
    'document_id' => $document_id,
    'file_path' => $filePath,
    'file_name' => $file['name'],
    'file_size' => $file['size']
]);

$con->close();
?>