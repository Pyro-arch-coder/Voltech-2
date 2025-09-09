<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'No file uploaded or upload error']));
}

$file = $_FILES['file'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.']));
}

// Validate file size
if ($file['size'] > $max_size) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit']));
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/messages/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = uniqid('file_', true) . '.' . $file_ext;
$destination = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Return relative path for database storage
    $relative_path = 'uploads/messages/' . $new_filename;
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'file_path' => $relative_path,
        'file_name' => $file['name'],
        'file_type' => $file['type']
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
}
