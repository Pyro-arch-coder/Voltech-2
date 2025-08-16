<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$client_id = $_SESSION['user_id'];
$project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit();
}

$file = $_FILES['proof_of_payment'];
$fileName = $file['name'];
$fileSize = $file['size'];
$fileTmpName = $file['tmp_name'];
$fileType = $file['type'];

// Validate file type
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF, JPG, and PNG files are allowed.']);
    exit();
}

// Validate file size (5MB max)
$maxFileSize = 5 * 1024 * 1024; // 5MB
if ($fileSize > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum size is 5MB.']);
    exit();
}

// Generate unique filename
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$uniqueFileName = 'proof_payment_' . $project_id . '_' . $client_id . '_' . time() . '.' . $fileExtension;

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/proof_of_payments/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploadPath = $uploadDir . $uniqueFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpName, $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit();
}

try {
    // Check if proof of payment already exists for this project and client
    $checkQuery = "SELECT id FROM proof_of_payments WHERE project_id = ? AND user_id = ?";
    $checkStmt = $con->prepare($checkQuery);
    $checkStmt->bind_param('ii', $project_id, $client_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $updateQuery = "UPDATE proof_of_payments SET file_name = ?, file_path = ?, upload_date = NOW(), status = 'pending' WHERE project_id = ? AND user_id = ?";
        $updateStmt = $con->prepare($updateQuery);
        $updateStmt->bind_param('ssii', $fileName, $uploadPath, $project_id, $client_id);
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Proof of payment updated successfully',
                'file_name' => $fileName,
                'file_path' => $uploadPath
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update proof of payment']);
        }
    } else {
        // Insert new record
        $insertQuery = "INSERT INTO proof_of_payments (project_id, user_id, file_name, file_path, upload_date, status) VALUES (?, ?, ?, ?, NOW(), 'pending')";
        $insertStmt = $con->prepare($insertQuery);
        $insertStmt->bind_param('iiss', $project_id, $client_id, $fileName, $uploadPath);
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Proof of payment uploaded successfully',
                'file_name' => $fileName,
                'file_path' => $uploadPath
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save proof of payment']);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$con->close();
?>
