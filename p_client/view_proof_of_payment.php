<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 6) {
    header("HTTP/1.1 403 Forbidden");
    exit('Unauthorized access');
}

$client_id = $_SESSION['user_id'];
$proof_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($proof_id <= 0) {
    header("HTTP/1.1 400 Bad Request");
    exit('Invalid proof ID');
}

try {
    // Get proof of payment details and verify ownership
    $query = "SELECT * FROM proof_of_payments WHERE id = ? AND user_id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param('ii', $proof_id, $client_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("HTTP/1.1 404 Not Found");
        exit('Proof of payment not found');
    }
    
    $proofOfPayment = $result->fetch_assoc();
    $filePath = $proofOfPayment['file_path'];
    $fileName = $proofOfPayment['file_name'];
    
    // Check if file exists
    if (!file_exists($filePath)) {
        header("HTTP/1.1 404 Not Found");
        exit('File not found');
    }
    
    // Security check: ensure file is within uploads directory
    $realPath = realpath($filePath);
    $uploadsDir = realpath('../uploads/proof_of_payments/');
    
    if (strpos($realPath, $uploadsDir) !== 0) {
        header("HTTP/1.1 403 Forbidden");
        exit('Access denied');
    }
    
    // Get file info
    $fileSize = filesize($filePath);
    $fileType = mime_content_type($filePath);
    
    // Set headers for file download/view
    header('Content-Type: ' . $fileType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output file content
    readfile($filePath);
    
} catch (Exception $e) {
    header("HTTP/1.1 500 Internal Server Error");
    exit('Server error: ' . $e->getMessage());
}

$con->close();
?>
