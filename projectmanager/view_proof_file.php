<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is a project manager
if (!isset($_SESSION['logged_in']) || $_SESSION['user_level'] != 3) {
    header("HTTP/1.1 403 Forbidden");
    exit('Unauthorized access');
}

$file_path = isset($_GET['file_path']) ? $_GET['file_path'] : '';
$file_name = isset($_GET['file_name']) ? $_GET['file_name'] : '';

// Debug logging (remove this in production)
error_log("Debug - File path: " . $file_path);
error_log("Debug - File name: " . $file_name);

if (empty($file_path) || empty($file_name)) {
    header("HTTP/1.1 400 Bad Request");
    exit('Missing file parameters');
}

// Simplified path resolution
$baseDir = dirname(__FILE__) . '/../';
$uploadsDir = $baseDir . 'uploads/';

// Debug the uploads directory path
error_log("Debug - Base directory: " . $baseDir);
error_log("Debug - Uploads directory: " . $uploadsDir);

// Handle different possible path formats
if (strpos($file_path, '../uploads/') === 0) {
    // Path starts with ../uploads/ (from client upload)
    $requestedFile = $baseDir . substr($file_path, 3);
} elseif (strpos($file_path, 'uploads/') === 0) {
    // Path starts with uploads/
    $requestedFile = $baseDir . $file_path;
} else {
    // Path doesn't start with uploads/, construct it
    $requestedFile = $uploadsDir . $file_path;
}

// Debug logging (remove this in production)
error_log("Debug - File path from DB: " . $file_path);
error_log("Debug - Uploads dir: " . $uploadsDir);
error_log("Debug - Requested file: " . $requestedFile);
error_log("Debug - File exists: " . (file_exists($requestedFile) ? 'Yes' : 'No'));
error_log("Debug - Current script dir: " . dirname(__FILE__));
error_log("Debug - Absolute path: " . realpath($requestedFile));

// Security check: ensure file path is within uploads directory
if (strpos($requestedFile, $uploadsDir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit('Access denied - Invalid file path. Path: ' . $file_path . ', Requested: ' . $requestedFile . ', Uploads: ' . $uploadsDir);
}

// Check if file exists
if (!file_exists($requestedFile)) {
    header("HTTP/1.1 404 Not Found");
    exit('File not found');
}

// Get file info
$fileSize = filesize($requestedFile);
$fileType = mime_content_type($requestedFile);

// Set headers for file viewing
header('Content-Type: ' . $fileType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $file_name . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file content
readfile($requestedFile);
exit();
?>
